<?php
// This file is part of Moodle - http://moodle.org/
//
// Teacher preview page for the Cross Duel activity module.

/**
 * Preview page for mod_crossduel.
 *
 * This version:
 * - parses the stored word list
 * - generates an in-memory draft layout
 * - shows placed and skipped words
 * - renders a visible draft grid
 * - allows the teacher to approve the draft
 * - saves approved placed words into crossduel_layoutslot
 *
 * @package    mod_crossduel
 * @copyright  Your name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT); // Course module id.

$cm = get_coursemodule_from_id('crossduel', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$crossduel = $DB->get_record('crossduel', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/crossduel:addinstance', $context);

$PAGE->set_url('/mod/crossduel/preview.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title('Preview: ' . format_string($crossduel->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

/**
 * Parse the stored word list into structured rows.
 *
 * Each non-blank line must be:
 *   word|clue
 *
 * @param string $rawtext Raw word list from the activity settings
 * @return array Parsed entries
 */
function crossduel_preview_parse_wordlist(string $rawtext): array {
    $entries = [];

    $lines = preg_split('/\r\n|\r|\n/', $rawtext);

    foreach ($lines as $index => $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        if (substr_count($line, '|') !== 1) {
            continue;
        }

        list($word, $clue) = array_map('trim', explode('|', $line, 2));

        if ($word === '' || $clue === '') {
            continue;
        }

        $normalized = core_text::strtoupper($word);
        $normalized = preg_replace('/[^[:alnum:]]/u', '', $normalized);

        $entries[] = [
            'line' => $index + 1,
            'word' => $word,
            'normalized' => $normalized,
            'clue' => $clue,
            'length' => core_text::strlen($normalized),
        ];
    }

    return $entries;
}

/**
 * Build a lookup of structured stored words keyed by normalized word + sort order.
 *
 * This helps us match the preview-generated placed words back to actual
 * crossduel_word table rows.
 *
 * @param int $crossduelid
 * @return array
 */
function crossduel_preview_get_stored_word_lookup(int $crossduelid): array {
    global $DB;

    $records = $DB->get_records(
        'crossduel_word',
        ['crossduelid' => $crossduelid],
        'sortorder ASC'
    );

    $lookup = [];

    foreach ($records as $record) {
        $key = $record->normalizedword . '|' . $record->sortorder;
        $lookup[$key] = $record;
    }

    return $lookup;
}

/**
 * Save the approved placed words into crossduel_layoutslot and mark the
 * activity layout as approved.
 *
 * Matching rule:
 * - We match by normalized word + original line number.
 * - In this plugin, preview line number corresponds to stored sortorder.
 *
 * @param stdClass $crossduel
 * @param array $layoutresult
 * @return void
 */
function crossduel_preview_save_approved_layout(stdClass $crossduel, array $layoutresult): void {
    global $DB;

    $lookup = crossduel_preview_get_stored_word_lookup((int)$crossduel->id);

    $transaction = $DB->start_delegated_transaction();

    // Remove any previous stored layout for this activity.
    $DB->delete_records('crossduel_layoutslot', ['crossduelid' => $crossduel->id]);

    $cluenumber = 1;
    $placementorder = 1;

    foreach ($layoutresult['placed'] as $placed) {
        $key = $placed['normalized'] . '|' . $placed['line'];

        if (!isset($lookup[$key])) {
            // Fail safely if we cannot match a placed word back to its source row.
            throw new moodle_exception(
                'Could not match a placed word back to its stored crossduel_word record: ' . $placed['normalized']
            );
        }

        $storedword = $lookup[$key];

        $record = new stdClass();
        $record->crossduelid = $crossduel->id;
        $record->wordid = $storedword->id;
        $record->direction = $placed['direction'];
        $record->startrow = $placed['startrow'];
        $record->startcol = $placed['startcol'];
        $record->cluenumber = $cluenumber;
        $record->placementorder = $placementorder;
        $record->isactive = 1;

        $DB->insert_record('crossduel_layoutslot', $record);

        $cluenumber++;
        $placementorder++;
    }

    $crossduel->layoutapproved = 1;
    $crossduel->timemodified = time();
    $DB->update_record('crossduel', $crossduel);

    $transaction->allow_commit();
}

$entries = crossduel_preview_parse_wordlist((string)$crossduel->wordlist);
$layoutresult = crossduel_generate_draft_layout($entries);
$matrix = crossduel_build_render_matrix($layoutresult);

$approvalmessage = '';
$approvalsuccess = false;

/*
 * -------------------------------------------------------------
 * Handle teacher approval
 * -------------------------------------------------------------
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param('approvelayout', '', PARAM_TEXT) !== '') {
    require_sesskey();

    if (empty($layoutresult['placed'])) {
        $approvalmessage = 'There is no draft layout to approve.';
    } else {
        try {
            crossduel_preview_save_approved_layout($crossduel, $layoutresult);

            // Refresh the main activity record after saving approval.
            $crossduel = $DB->get_record('crossduel', ['id' => $crossduel->id], '*', MUST_EXIST);

            $approvalsuccess = true;
            $approvalmessage = 'Draft layout approved and saved successfully.';
        } catch (Exception $e) {
            $approvalmessage = $e->getMessage();
        }
    }
}

/*
 * -------------------------------------------------------------
 * Simple preview styles
 * -------------------------------------------------------------
 */
$styles = '
.crossduel-preview-grid {
    border-collapse: collapse;
    margin-top: 1rem;
    margin-bottom: 1rem;
}
.crossduel-preview-grid td {
    width: 36px;
    height: 36px;
    border: 1px solid #cbd5e1;
    text-align: center;
    vertical-align: middle;
    font-weight: 700;
    font-size: 1rem;
    padding: 0;
}
.crossduel-preview-grid td.crossduel-filled {
    background: #ffffff;
    color: #111827;
}
.crossduel-preview-grid td.crossduel-empty {
    background: #1f2937;
    border-color: #1f2937;
}
.crossduel-preview-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: #ffffff;
    padding: 1rem;
    margin-bottom: 1rem;
}
.crossduel-preview-note {
    color: #475467;
}
.crossduel-direction-pill {
    display: inline-block;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    font-size: 0.85rem;
    font-weight: 600;
}
.crossduel-skipped-pill {
    display: inline-block;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: #fff7ed;
    border: 1px solid #fdba74;
    font-size: 0.85rem;
    font-weight: 600;
}
.crossduel-action-row {
    margin-top: 1rem;
    display: flex;
    gap: 0.6rem;
    flex-wrap: wrap;
}
';

echo $OUTPUT->header();
echo html_writer::tag('style', $styles);
echo $OUTPUT->heading('Cross Duel preview');

if ($approvalmessage !== '') {
    if ($approvalsuccess) {
        echo $OUTPUT->notification($approvalmessage, 'success');
    } else {
        echo $OUTPUT->notification($approvalmessage, 'warning');
    }
}

/*
 * -------------------------------------------------------------
 * Introductory explanation
 * -------------------------------------------------------------
 */
echo $OUTPUT->box(
    html_writer::tag('p', 'This page generates a cautious version-1 draft layout from the stored word list.') .
    html_writer::tag('p', 'The generator prioritises reliability and clean intersections over perfect density.'),
    'generalbox'
);

/*
 * -------------------------------------------------------------
 * Activity summary
 * -------------------------------------------------------------
 */
$summaryitems = [];
$summaryitems[] = html_writer::tag('li', 'Activity: ' . s($crossduel->name));
$summaryitems[] = html_writer::tag('li', 'Stored entries found: ' . count($entries));
$summaryitems[] = html_writer::tag('li', 'Placed in draft: ' . count($layoutresult['placed']));
$summaryitems[] = html_writer::tag('li', 'Skipped in draft: ' . count($layoutresult['skipped']));
$summaryitems[] = html_writer::tag('li', 'Reveal percentage: ' . s($crossduel->revealpercent));
$summaryitems[] = html_writer::tag('li', 'Pass percentage: ' . s($crossduel->passpercentage));
$summaryitems[] = html_writer::tag('li', 'Layout approved: ' . ($crossduel->layoutapproved ? 'Yes' : 'No'));

echo $OUTPUT->box(
    html_writer::tag('h3', 'Activity summary') .
    html_writer::tag('ul', implode('', $summaryitems)),
    'generalbox'
);

/*
 * -------------------------------------------------------------
 * Warnings
 * -------------------------------------------------------------
 */
if (!empty($layoutresult['warnings'])) {
    foreach ($layoutresult['warnings'] as $warning) {
        echo $OUTPUT->notification($warning, 'warning');
    }
}

/*
 * -------------------------------------------------------------
 * Placed words
 * -------------------------------------------------------------
 */
echo html_writer::start_div('crossduel-preview-card');
echo $OUTPUT->heading('Placed words in draft layout', 3);

if (empty($layoutresult['placed'])) {
    echo html_writer::tag('p', 'No words could be placed in the current draft.', ['class' => 'crossduel-preview-note']);
} else {
    $table = new html_table();
    $table->head = [
        'Line',
        'Word',
        'Normalized',
        'Direction',
        'Start row',
        'Start col',
        'Length',
        'Clue',
    ];
    $table->attributes['class'] = 'generaltable';

    $table->data = [];

    foreach ($layoutresult['placed'] as $placed) {
        $directionlabel = ($placed['direction'] === 'H') ? 'Horizontal' : 'Vertical';

        $table->data[] = [
            $placed['line'],
            s($placed['word']),
            s($placed['normalized']),
            html_writer::tag('span', $directionlabel, ['class' => 'crossduel-direction-pill']),
            $placed['startrow'],
            $placed['startcol'],
            $placed['length'],
            s($placed['clue']),
        ];
    }

    echo html_writer::table($table);
}
echo html_writer::end_div();

/*
 * -------------------------------------------------------------
 * Skipped words
 * -------------------------------------------------------------
 */
echo html_writer::start_div('crossduel-preview-card');
echo $OUTPUT->heading('Skipped words', 3);

if (empty($layoutresult['skipped'])) {
    echo html_writer::tag('p', 'No words were skipped in this draft.', ['class' => 'crossduel-preview-note']);
} else {
    $table = new html_table();
    $table->head = [
        'Line',
        'Word',
        'Normalized',
        'Length',
        'Clue',
        'Status',
    ];
    $table->attributes['class'] = 'generaltable';

    $table->data = [];

    foreach ($layoutresult['skipped'] as $skipped) {
        $table->data[] = [
            $skipped['line'],
            s($skipped['word']),
            s($skipped['normalized']),
            $skipped['length'],
            s($skipped['clue']),
            html_writer::tag('span', 'Skipped', ['class' => 'crossduel-skipped-pill']),
        ];
    }

    echo html_writer::table($table);
}
echo html_writer::end_div();

/*
 * -------------------------------------------------------------
 * Draft grid
 * -------------------------------------------------------------
 */
echo html_writer::start_div('crossduel-preview-card');
echo $OUTPUT->heading('Draft grid preview', 3);

if (empty($matrix['rows'])) {
    echo html_writer::tag('p', 'No grid could be rendered from the current draft.', ['class' => 'crossduel-preview-note']);
} else {
    echo html_writer::tag(
        'p',
        'Filled cells represent letters placed by the generator. Dark cells are unused spaces inside the current rectangular preview window.',
        ['class' => 'crossduel-preview-note']
    );

    echo html_writer::start_tag('table', ['class' => 'crossduel-preview-grid']);

    foreach ($matrix['rows'] as $rowcells) {
        echo html_writer::start_tag('tr');

        foreach ($rowcells as $cell) {
            if ($cell === '') {
                echo html_writer::tag('td', '', ['class' => 'crossduel-empty']);
            } else {
                echo html_writer::tag('td', s($cell), ['class' => 'crossduel-filled']);
            }
        }

        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('table');
}
echo html_writer::end_div();

/*
 * -------------------------------------------------------------
 * Action area
 * -------------------------------------------------------------
 */
$viewurl = new moodle_url('/mod/crossduel/view.php', ['id' => $cm->id]);
$editurl = new moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]);

echo $OUTPUT->box_start('generalbox');
echo html_writer::tag('h3', 'Next step');
echo html_writer::tag(
    'p',
    'You can now approve this preview-only draft and save the placed words into the layout table.'
);

echo html_writer::start_div('crossduel-action-row');

echo html_writer::link($editurl, 'Edit settings', ['class' => 'btn btn-secondary']);
echo html_writer::link($viewurl, 'Back to activity', ['class' => 'btn btn-secondary']);

if (!empty($layoutresult['placed'])) {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $PAGE->url->out(false),
        'style' => 'display:inline;'
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey(),
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'name' => 'approvelayout',
        'value' => 'Approve this draft layout',
        'class' => 'btn btn-primary',
    ]);

    echo html_writer::end_tag('form');
}

echo html_writer::end_div();
echo $OUTPUT->box_end();

echo $OUTPUT->footer();