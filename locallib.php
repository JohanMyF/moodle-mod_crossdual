<?php
// This file is part of Moodle - http://moodle.org/
//
// Local helper functions for the Cross Duel activity module.

/**
 * Local helper functions for mod_crossduel.
 *
 * This file contains the first draft-layout generator for Cross Duel.
 *
 * IMPORTANT DESIGN CHOICE:
 * This is intentionally a version-1 generator:
 * - simple
 * - deterministic
 * - easy to debug
 * - not optimized for perfect crossword density
 *
 * The generator works like this:
 * 1. Sort words by descending length.
 * 2. Place the first word horizontally near the middle.
 * 3. For each later word, try to intersect it with already placed words.
 * 4. If no safe placement is found, skip the word.
 *
 * This gives us a reliable first draft preview without pretending to be a
 * full newspaper-style crossword engine.
 *
 * @package    mod_crossduel
 * @copyright  Your name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Build a deterministic draft layout from parsed Cross Duel entries.
 *
 * Expected entry format:
 * [
 *   [
 *     'line' => 1,
 *     'word' => 'algorithm',
 *     'normalized' => 'ALGORITHM',
 *     'clue' => 'A step-by-step procedure for solving a problem',
 *     'length' => 9,
 *   ],
 *   ...
 * ]
 *
 * Return format:
 * [
 *   'success' => true,
 *   'placed' => [...],
 *   'skipped' => [...],
 *   'grid' => [...],
 *   'bounds' => [...],
 *   'warnings' => [...],
 * ]
 *
 * @param array $entries Parsed preview entries
 * @return array Draft layout result
 */
function crossduel_generate_draft_layout(array $entries): array {
    $result = [
        'success' => false,
        'placed' => [],
        'skipped' => [],
        'grid' => [],
        'bounds' => [
            'minrow' => 0,
            'maxrow' => 0,
            'mincol' => 0,
            'maxcol' => 0,
        ],
        'warnings' => [],
    ];

    if (empty($entries)) {
        $result['warnings'][] = 'No valid entries were supplied to the draft layout generator.';
        return $result;
    }

    /*
     * -------------------------------------------------------------
     * Sort by descending word length, then by original line number.
     * This makes generation deterministic and easy to reason about.
     * -------------------------------------------------------------
     */
    usort($entries, function(array $a, array $b): int {
        if ((int)$a['length'] === (int)$b['length']) {
            return ((int)$a['line'] <=> (int)$b['line']);
        }

        return ((int)$b['length'] <=> (int)$a['length']);
    });

    /*
     * -------------------------------------------------------------
     * Place the first word horizontally near the middle.
     *
     * We use a generous conceptual coordinate space around row 50/col 50.
     * This avoids negative positions in early debugging and gives us room
     * in all directions for later vertical and horizontal placements.
     * -------------------------------------------------------------
     */
    $anchor = array_shift($entries);

    $anchorrow = 50;
    $anchorcol = 50;

    $anchorplacement = [
        'line' => $anchor['line'],
        'word' => $anchor['word'],
        'normalized' => $anchor['normalized'],
        'clue' => $anchor['clue'],
        'length' => $anchor['length'],
        'direction' => 'H',
        'startrow' => $anchorrow,
        'startcol' => $anchorcol,
    ];

    crossduel_write_word_to_grid($result['grid'], $anchorplacement);
    $result['placed'][] = $anchorplacement;
    crossduel_update_bounds_from_placement($result['bounds'], $anchorplacement);

    /*
     * -------------------------------------------------------------
     * Try to place each remaining word by legal intersections.
     * If no legal placement is found, skip it for now.
     * -------------------------------------------------------------
     */
    foreach ($entries as $entry) {
        $placement = crossduel_find_placement_for_entry($entry, $result['placed'], $result['grid']);

        if ($placement) {
            crossduel_write_word_to_grid($result['grid'], $placement);
            $result['placed'][] = $placement;
            crossduel_update_bounds_from_placement($result['bounds'], $placement);
        } else {
            $result['skipped'][] = $entry;
        }
    }

    if (!empty($result['placed'])) {
        $result['success'] = true;
    }

    if (!empty($result['skipped'])) {
        $result['warnings'][] = count($result['skipped']) . ' word(s) could not be safely placed in this version-1 draft.';
    }

    if (count($result['placed']) < 2) {
        $result['warnings'][] = 'Only a very small number of words could be placed. The teacher may need a friendlier word set.';
    }

    return $result;
}

/**
 * Try to find a legal placement for one word by intersecting it with any
 * already placed word.
 *
 * Strategy:
 * - If an existing word is horizontal, we try placing the new word vertical.
 * - If an existing word is vertical, we try placing the new word horizontal.
 * - We scan all matching letters between the candidate word and the placed word.
 * - The first legal placement found is accepted.
 *
 * This is deterministic because:
 * - placed words are scanned in the order they were placed
 * - letters are scanned left-to-right through each word
 *
 * @param array $entry Candidate word entry
 * @param array $placedwords Already placed words
 * @param array $grid Current grid
 * @return array|null Placement array or null if no legal placement found
 */
function crossduel_find_placement_for_entry(array $entry, array $placedwords, array $grid): ?array {
    $candidate = $entry['normalized'];
    $candidatelen = core_text::strlen($candidate);

    foreach ($placedwords as $placedword) {
        $placedtext = $placedword['normalized'];
        $placeddir = $placedword['direction'];
        $placedlen = core_text::strlen($placedtext);

        // Alternate direction relative to the already placed word.
        $newdir = ($placeddir === 'H') ? 'V' : 'H';

        for ($i = 0; $i < $candidatelen; $i++) {
            $candidatechar = core_text::substr($candidate, $i, 1);

            for ($j = 0; $j < $placedlen; $j++) {
                $placedchar = core_text::substr($placedtext, $j, 1);

                if ($candidatechar !== $placedchar) {
                    continue;
                }

                if ($placeddir === 'H') {
                    /*
                     * Existing word is horizontal.
                     * Candidate tries vertical.
                     *
                     * Existing letter position:
                     *   row = placed startrow
                     *   col = placed startcol + j
                     *
                     * Candidate letter i must land there.
                     */
                    $startrow = $placedword['startrow'] - $i;
                    $startcol = $placedword['startcol'] + $j;
                } else {
                    /*
                     * Existing word is vertical.
                     * Candidate tries horizontal.
                     *
                     * Existing letter position:
                     *   row = placed startrow + j
                     *   col = placed startcol
                     *
                     * Candidate letter i must land there.
                     */
                    $startrow = $placedword['startrow'] + $j;
                    $startcol = $placedword['startcol'] - $i;
                }

                $trialplacement = [
                    'line' => $entry['line'],
                    'word' => $entry['word'],
                    'normalized' => $entry['normalized'],
                    'clue' => $entry['clue'],
                    'length' => $entry['length'],
                    'direction' => $newdir,
                    'startrow' => $startrow,
                    'startcol' => $startcol,
                ];

                if (crossduel_is_placement_legal($trialplacement, $grid)) {
                    return $trialplacement;
                }
            }
        }
    }

    return null;
}

/**
 * Check whether a proposed placement is legal in the current grid.
 *
 * A placement is legal if:
 * 1. Every occupied cell either:
 *    - is empty, or
 *    - already contains the same letter
 * 2. For any new non-intersecting cell, there is no side-adjacency that would
 *    create accidental touching words.
 * 3. The cell immediately before the word and immediately after the word are empty.
 *
 * This is a cautious rule set intended to avoid ugly accidental collisions.
 *
 * @param array $placement Proposed placement
 * @param array $grid Current grid
 * @return bool True if legal
 */
function crossduel_is_placement_legal(array $placement, array $grid): bool {
    $word = $placement['normalized'];
    $length = core_text::strlen($word);
    $dir = $placement['direction'];
    $startrow = (int)$placement['startrow'];
    $startcol = (int)$placement['startcol'];

    /*
     * -------------------------------------------------------------
     * Rule 1: before/after cells must be empty
     * -------------------------------------------------------------
     */
    if ($dir === 'H') {
        if (crossduel_grid_has_cell($grid, $startrow, $startcol - 1)) {
            return false;
        }
        if (crossduel_grid_has_cell($grid, $startrow, $startcol + $length)) {
            return false;
        }
    } else {
        if (crossduel_grid_has_cell($grid, $startrow - 1, $startcol)) {
            return false;
        }
        if (crossduel_grid_has_cell($grid, $startrow + $length, $startcol)) {
            return false;
        }
    }

    /*
     * -------------------------------------------------------------
     * Rule 2: every cell must be empty or matching
     * -------------------------------------------------------------
     * Rule 3: non-intersecting cells must not side-touch another word
     * -------------------------------------------------------------
     */
    for ($i = 0; $i < $length; $i++) {
        $letter = core_text::substr($word, $i, 1);

        if ($dir === 'H') {
            $row = $startrow;
            $col = $startcol + $i;
        } else {
            $row = $startrow + $i;
            $col = $startcol;
        }

        $existing = crossduel_grid_get_cell($grid, $row, $col);

        if ($existing !== null && $existing !== $letter) {
            return false;
        }

        $isintersection = ($existing === $letter);

        /*
         * For newly occupied cells only, check side adjacency.
         *
         * Horizontal word:
         * - above and below must be empty
         *
         * Vertical word:
         * - left and right must be empty
         *
         * Intersections are exempt because they are the intended crossing.
         */
        if (!$isintersection) {
            if ($dir === 'H') {
                if (crossduel_grid_has_cell($grid, $row - 1, $col)) {
                    return false;
                }
                if (crossduel_grid_has_cell($grid, $row + 1, $col)) {
                    return false;
                }
            } else {
                if (crossduel_grid_has_cell($grid, $row, $col - 1)) {
                    return false;
                }
                if (crossduel_grid_has_cell($grid, $row, $col + 1)) {
                    return false;
                }
            }
        }
    }

    return true;
}

/**
 * Write a placed word into the grid.
 *
 * Grid format:
 * [
 *   rownumber => [
 *     colnumber => 'A',
 *     ...
 *   ],
 *   ...
 * ]
 *
 * @param array $grid Grid passed by reference
 * @param array $placement Placement data
 * @return void
 */
function crossduel_write_word_to_grid(array &$grid, array $placement): void {
    $word = $placement['normalized'];
    $length = core_text::strlen($word);
    $dir = $placement['direction'];
    $startrow = (int)$placement['startrow'];
    $startcol = (int)$placement['startcol'];

    for ($i = 0; $i < $length; $i++) {
        $letter = core_text::substr($word, $i, 1);

        if ($dir === 'H') {
            $row = $startrow;
            $col = $startcol + $i;
        } else {
            $row = $startrow + $i;
            $col = $startcol;
        }

        if (!isset($grid[$row])) {
            $grid[$row] = [];
        }

        $grid[$row][$col] = $letter;
    }
}

/**
 * Update overall grid bounds from one placement.
 *
 * @param array $bounds Bounds passed by reference
 * @param array $placement Placement data
 * @return void
 */
function crossduel_update_bounds_from_placement(array &$bounds, array $placement): void {
    $length = (int)$placement['length'];
    $dir = $placement['direction'];
    $startrow = (int)$placement['startrow'];
    $startcol = (int)$placement['startcol'];

    if ($dir === 'H') {
        $minrow = $startrow;
        $maxrow = $startrow;
        $mincol = $startcol;
        $maxcol = $startcol + $length - 1;
    } else {
        $minrow = $startrow;
        $maxrow = $startrow + $length - 1;
        $mincol = $startcol;
        $maxcol = $startcol;
    }

    if ($bounds['minrow'] === 0 && $bounds['maxrow'] === 0 && $bounds['mincol'] === 0 && $bounds['maxcol'] === 0) {
        $bounds['minrow'] = $minrow;
        $bounds['maxrow'] = $maxrow;
        $bounds['mincol'] = $mincol;
        $bounds['maxcol'] = $maxcol;
        return;
    }

    $bounds['minrow'] = min($bounds['minrow'], $minrow);
    $bounds['maxrow'] = max($bounds['maxrow'], $maxrow);
    $bounds['mincol'] = min($bounds['mincol'], $mincol);
    $bounds['maxcol'] = max($bounds['maxcol'], $maxcol);
}

/**
 * Return the letter at a grid position, or null if empty.
 *
 * @param array $grid Current grid
 * @param int $row Row number
 * @param int $col Column number
 * @return string|null
 */
function crossduel_grid_get_cell(array $grid, int $row, int $col): ?string {
    if (!isset($grid[$row])) {
        return null;
    }

    if (!array_key_exists($col, $grid[$row])) {
        return null;
    }

    return $grid[$row][$col];
}

/**
 * Check whether a grid cell is occupied.
 *
 * @param array $grid Current grid
 * @param int $row Row number
 * @param int $col Column number
 * @return bool
 */
function crossduel_grid_has_cell(array $grid, int $row, int $col): bool {
    return crossduel_grid_get_cell($grid, $row, $col) !== null;
}

/**
 * Build a simple printable rectangular matrix from a draft layout result.
 *
 * This is useful for preview rendering.
 *
 * Return format:
 * [
 *   'rows' => [
 *      [ 'A', 'L', 'G', '', '' ],
 *      ...
 *   ],
 *   'minrow' => ...,
 *   'maxrow' => ...,
 *   'mincol' => ...,
 *   'maxcol' => ...,
 * ]
 *
 * @param array $layoutresult Result from crossduel_generate_draft_layout()
 * @return array
 */
function crossduel_build_render_matrix(array $layoutresult): array {
    $matrix = [
        'rows' => [],
        'minrow' => 0,
        'maxrow' => 0,
        'mincol' => 0,
        'maxcol' => 0,
    ];

    if (empty($layoutresult['placed']) || empty($layoutresult['grid'])) {
        return $matrix;
    }

    $bounds = $layoutresult['bounds'];

    $matrix['minrow'] = (int)$bounds['minrow'];
    $matrix['maxrow'] = (int)$bounds['maxrow'];
    $matrix['mincol'] = (int)$bounds['mincol'];
    $matrix['maxcol'] = (int)$bounds['maxcol'];

    for ($row = $matrix['minrow']; $row <= $matrix['maxrow']; $row++) {
        $rowcells = [];

        for ($col = $matrix['mincol']; $col <= $matrix['maxcol']; $col++) {
            $cell = crossduel_grid_get_cell($layoutresult['grid'], $row, $col);
            $rowcells[] = ($cell === null) ? '' : $cell;
        }

        $matrix['rows'][] = $rowcells;
    }

    return $matrix;
}