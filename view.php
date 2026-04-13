<?php
// This file is part of Moodle - http://moodle.org/
//
// Main activity page for the Cross Duel activity module.

/**
 * Main view page for mod_crossduel.
 *
 * This version adds the first safe multiplayer invitation milestone:
 * - available partner list
 * - send invitation
 * - incoming invitation banner
 * - accept / decline invitation
 * - active/pending multiplayer status card
 *
 * IMPORTANT:
 * - Single-player solving remains intact.
 * - Multiplayer solving is NOT implemented yet.
 * - No AJAX is used yet.
 * - No Moodle message notification is sent yet.
 *
 * @package    mod_crossduel
 * @copyright  Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT); // Course module id.
$ajax = optional_param('ajax', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('crossduel', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$crossduel = $DB->get_record('crossduel', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/crossduel:play', $context);

crossduel_view_touch_presence((int)$crossduel->id, (int)$USER->id);

$PAGE->set_url('/mod/crossduel/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($crossduel->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');


/**
 * Detect whether this is a real AJAX polling request.
 *
 * Prevent raw JSON from appearing in the browser after timeout/login roundtrips.
 *
 * @return bool
 */
function crossduel_view_is_real_ajax_request(): bool {
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        return false;
    }

    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

$crossduelviewurl = new moodle_url('/mod/crossduel/view.php', ['id' => $cm->id]);


if ($ajax && !crossduel_view_is_real_ajax_request()) {
    redirect(new moodle_url('/mod/crossduel/view.php', ['id' => $cm->id]));
}

$crossduelviewurl = new moodle_url('/mod/crossduel/view.php', ['id' => $cm->id]);

/**
 * Load the approved layout rows joined to their source words.
 *
 * @param int $crossduelid
 * @return array
 */
function crossduel_view_get_approved_layout_rows(int $crossduelid): array {
    global $DB;

    $sql = "
        SELECT
            ls.id AS layoutslotid,
            ls.crossduelid,
            ls.wordid,
            ls.direction,
            ls.startrow,
            ls.startcol,
            ls.cluenumber,
            ls.placementorder,
            ls.isactive,
            w.rawword,
            w.normalizedword,
            w.cluetext,
            w.wordlength,
            w.sortorder
        FROM {crossduel_layoutslot} ls
        JOIN {crossduel_word} w
          ON w.id = ls.wordid
        WHERE ls.crossduelid = :crossduelid
          AND ls.isactive = 1
        ORDER BY ls.cluenumber ASC, ls.placementorder ASC
    ";

    return $DB->get_records_sql($sql, ['crossduelid' => $crossduelid]);
}

/**
 * Build a grid from saved layout rows.
 *
 * @param array $layoutrows
 * @return array
 */
function crossduel_view_build_grid(array $layoutrows): array {
    $grid = [];

    foreach ($layoutrows as $row) {
        $word = $row->normalizedword;
        $length = core_text::strlen($word);

        for ($i = 0; $i < $length; $i++) {
            $letter = core_text::substr($word, $i, 1);

            if ($row->direction === 'H') {
                $gridrow = (int)$row->startrow;
                $gridcol = (int)$row->startcol + $i;
            } else {
                $gridrow = (int)$row->startrow + $i;
                $gridcol = (int)$row->startcol;
            }

            if (!isset($grid[$gridrow])) {
                $grid[$gridrow] = [];
            }

            $grid[$gridrow][$gridcol] = $letter;
        }
    }

    return $grid;
}

/**
 * Build display bounds from saved layout rows.
 *
 * @param array $layoutrows
 * @return array
 */
function crossduel_view_get_bounds(array $layoutrows): array {
    $bounds = [
        'minrow' => 0,
        'maxrow' => 0,
        'mincol' => 0,
        'maxcol' => 0,
    ];

    $first = true;

    foreach ($layoutrows as $row) {
        $length = (int)$row->wordlength;

        if ($row->direction === 'H') {
            $minrow = (int)$row->startrow;
            $maxrow = (int)$row->startrow;
            $mincol = (int)$row->startcol;
            $maxcol = (int)$row->startcol + $length - 1;
        } else {
            $minrow = (int)$row->startrow;
            $maxrow = (int)$row->startrow + $length - 1;
            $mincol = (int)$row->startcol;
            $maxcol = (int)$row->startcol;
        }

        if ($first) {
            $bounds['minrow'] = $minrow;
            $bounds['maxrow'] = $maxrow;
            $bounds['mincol'] = $mincol;
            $bounds['maxcol'] = $maxcol;
            $first = false;
        } else {
            $bounds['minrow'] = min($bounds['minrow'], $minrow);
            $bounds['maxrow'] = max($bounds['maxrow'], $maxrow);
            $bounds['mincol'] = min($bounds['mincol'], $mincol);
            $bounds['maxcol'] = max($bounds['maxcol'], $maxcol);
        }
    }

    return $bounds;
}

/**
 * Get one cell from the grid.
 *
 * @param array $grid
 * @param int $row
 * @param int $col
 * @return string|null
 */
function crossduel_view_get_grid_cell(array $grid, int $row, int $col): ?string {
    if (!isset($grid[$row])) {
        return null;
    }

    if (!array_key_exists($col, $grid[$row])) {
        return null;
    }

    return $grid[$row][$col];
}

/**
 * Split saved layout rows into Across and Down clue arrays.
 *
 * @param array $layoutrows
 * @return array
 */
function crossduel_view_split_clues(array $layoutrows): array {
    $across = [];
    $down = [];

    foreach ($layoutrows as $row) {
        $item = [
            'wordid' => (int)$row->wordid,
            'cluenumber' => (int)$row->cluenumber,
            'clue' => $row->cluetext,
            'word' => $row->rawword,
            'normalized' => $row->normalizedword,
            'length' => (int)$row->wordlength,
            'direction' => $row->direction,
        ];

        if ($row->direction === 'H') {
            $across[] = $item;
        } else {
            $down[] = $item;
        }
    }

    return [
        'across' => $across,
        'down' => $down,
    ];
}

/**
 * Build a lookup of clue numbers by starting cell.
 *
 * @param array $layoutrows
 * @return array
 */
function crossduel_view_get_startcell_numbers(array $layoutrows): array {
    $numbers = [];

    foreach ($layoutrows as $row) {
        $key = (int)$row->startrow . ':' . (int)$row->startcol;

        if (!isset($numbers[$key])) {
            $numbers[$key] = (int)$row->cluenumber;
        } else {
            $numbers[$key] = min($numbers[$key], (int)$row->cluenumber);
        }
    }

    return $numbers;
}

/**
 * Build the ordered cell list for one word.
 *
 * @param stdClass $row
 * @return array
 */
function crossduel_view_get_word_cells(stdClass $row): array {
    $cells = [];
    $word = $row->normalizedword;
    $length = core_text::strlen($word);

    for ($i = 0; $i < $length; $i++) {
        $letter = core_text::substr($word, $i, 1);

        if ($row->direction === 'H') {
            $gridrow = (int)$row->startrow;
            $gridcol = (int)$row->startcol + $i;
        } else {
            $gridrow = (int)$row->startrow + $i;
            $gridcol = (int)$row->startcol;
        }

        $cells[] = [
            'row' => $gridrow,
            'col' => $gridcol,
            'letter' => $letter,
        ];
    }

    return $cells;
}

/**
 * Get or create one single-player attempt for this user and activity.
 *
 * @param int $crossduelid
 * @param int $userid
 * @return stdClass
 */
function crossduel_view_get_or_create_attempt(int $crossduelid, int $userid): stdClass {
    global $DB;

    $attempt = $DB->get_record('crossduel_attempt', [
        'crossduelid' => $crossduelid,
        'userid' => $userid,
    ], '*', IGNORE_MISSING);

    if ($attempt) {
        return $attempt;
    }

    $attempt = new stdClass();
    $attempt->crossduelid = $crossduelid;
    $attempt->userid = $userid;
    $attempt->status = 'inprogress';
    $attempt->timecreated = time();
    $attempt->timemodified = time();

    $attempt->id = $DB->insert_record('crossduel_attempt', $attempt);

    return $attempt;
}

/**
 * Load solved word ids for one attempt.
 *
 * @param int $attemptid
 * @return array
 */
function crossduel_view_get_solved_word_ids(int $attemptid): array {
    global $DB;

    $records = $DB->get_records('crossduel_attempt_word', [
        'attemptid' => $attemptid,
        'issolved' => 1,
    ]);

    $wordids = [];

    foreach ($records as $record) {
        $wordids[(int)$record->wordid] = true;
    }

    return $wordids;
}

/**
 * Build a deterministic revealed-cell map.
 *
 * @param array $layoutrows
 * @param float $revealpercent
 * @param array $solvedwordids
 * @return array
 */
function crossduel_view_get_revealed_cells(array $layoutrows, float $revealpercent, array $solvedwordids): array {
    $revealed = [];
    $allcells = [];

    foreach ($layoutrows as $row) {
        $wordcells = crossduel_view_get_word_cells($row);

        if (!empty($wordcells)) {
            $firstcell = $wordcells[0];
            $firstkey = $firstcell['row'] . ':' . $firstcell['col'];
            $revealed[$firstkey] = true;
        }

        foreach ($wordcells as $cell) {
            $allcells[] = $cell;
        }
    }

    $uniqueoccupied = [];

    foreach ($allcells as $cell) {
        $key = $cell['row'] . ':' . $cell['col'];
        $uniqueoccupied[$key] = true;
    }

    $totaloccupied = count($uniqueoccupied);

    if ($totaloccupied > 0) {
        $targetcount = (int)ceil(($revealpercent / 100) * $totaloccupied);
        $targetcount = max(1, $targetcount);

        if (count($revealed) < $targetcount) {
            foreach ($allcells as $cell) {
                $key = $cell['row'] . ':' . $cell['col'];

                if (!isset($revealed[$key])) {
                    $revealed[$key] = true;
                }

                if (count($revealed) >= $targetcount) {
                    break;
                }
            }
        }
    }

    foreach ($layoutrows as $row) {
        if (!isset($solvedwordids[(int)$row->wordid])) {
            continue;
        }

        $wordcells = crossduel_view_get_word_cells($row);

        foreach ($wordcells as $cell) {
            $key = $cell['row'] . ':' . $cell['col'];
            $revealed[$key] = true;
        }
    }

    return $revealed;
}

/**
 * Build a rectangular matrix for rendering.
 *
 * @param array $grid
 * @param array $bounds
 * @param array $revealedcells
 * @return array
 */
function crossduel_view_build_matrix(array $grid, array $bounds, array $revealedcells): array {
    $rows = [];

    if (empty($grid)) {
        return $rows;
    }

    for ($row = $bounds['minrow']; $row <= $bounds['maxrow']; $row++) {
        $rowcells = [];

        for ($col = $bounds['mincol']; $col <= $bounds['maxcol']; $col++) {
            $cell = crossduel_view_get_grid_cell($grid, $row, $col);
            $key = $row . ':' . $col;

            $rowcells[] = [
                'row' => $row,
                'col' => $col,
                'letter' => ($cell === null) ? '' : $cell,
                'revealed' => isset($revealedcells[$key]),
            ];
        }

        $rows[] = $rowcells;
    }

    return $rows;
}

/**
 * Store a user answer for one word in the attempt table.
 *
 * @param int $attemptid
 * @param int $wordid
 * @param string $useranswer
 * @param bool $correct
 * @return void
 */
function crossduel_view_store_attempt_word(int $attemptid, int $wordid, string $useranswer, bool $correct): void {
    global $DB;

    $record = $DB->get_record('crossduel_attempt_word', [
        'attemptid' => $attemptid,
        'wordid' => $wordid,
    ], '*', IGNORE_MISSING);

    if ($record) {
        $record->useranswer = $useranswer;
        $record->timeanswered = time();

        if ($correct) {
            $record->issolved = 1;
        }

        $DB->update_record('crossduel_attempt_word', $record);
        return;
    }

    $record = new stdClass();
    $record->attemptid = $attemptid;
    $record->wordid = $wordid;
    $record->issolved = $correct ? 1 : 0;
    $record->useranswer = $useranswer;
    $record->timeanswered = time();

    $DB->insert_record('crossduel_attempt_word', $record);
}

/**
 * Mark attempt completed if all words are solved.
 *
 * @param stdClass $attempt
 * @param array $layoutrows
 * @param array $solvedwordids
 * @return void
 */
function crossduel_view_update_attempt_completion(stdClass $attempt, array $layoutrows, array $solvedwordids): void {
    global $DB;

    $requiredwordids = [];

    foreach ($layoutrows as $row) {
        $requiredwordids[(int)$row->wordid] = true;
    }

    $allsolved = true;

    foreach ($requiredwordids as $wordid => $unused) {
        if (!isset($solvedwordids[$wordid])) {
            $allsolved = false;
            break;
        }
    }

    $newstatus = $allsolved ? 'completed' : 'inprogress';

    if ($attempt->status !== $newstatus) {
        $attempt->status = $newstatus;
        $attempt->timemodified = time();
        $DB->update_record('crossduel_attempt', $attempt);
    }
}

/**
 * Get the user's current active or pending multiplayer game for this activity.
 *
 * @param int $crossduelid
 * @param int $userid
 * @return stdClass|null
 */
function crossduel_view_get_user_current_multiplayer_game(int $crossduelid, int $userid): ?stdClass {
    global $DB;

    $sql = "
        SELECT *
          FROM {crossduel_game}
         WHERE crossduelid = :crossduelid
           AND (playera = :userid1 OR playerb = :userid2)
           AND status IN ('invited', 'active', 'completed')
      ORDER BY id DESC
    ";

    $records = $DB->get_records_sql($sql, [
        'crossduelid' => $crossduelid,
        'userid1' => $userid,
        'userid2' => $userid,
    ], 0, 1);

    if (!$records) {
        return null;
    }

    return reset($records);
}

/**
 * Get incoming invitations for the current user in this activity.
 *
 * @param int $crossduelid
 * @param int $userid
 * @return array
 */
function crossduel_view_get_incoming_invites(int $crossduelid, int $userid): array {
    global $DB;

    return $DB->get_records('crossduel_game', [
        'crossduelid' => $crossduelid,
        'playerb' => $userid,
        'status' => 'invited',
    ], 'id DESC');
}

/**
 * Check whether a user is already busy in an invited or active game for this activity.
 *
 * @param int $crossduelid
 * @param int $userid
 * @return bool
 */
function crossduel_view_user_is_busy_in_multiplayer(int $crossduelid, int $userid): bool {
    return crossduel_view_get_user_current_multiplayer_game($crossduelid, $userid) !== null;
}

/**
 * Determine whether a user has already passed this Cross Duel activity.
 *
 * @param stdClass $crossduel
 * @param int $userid
 * @return bool
 */
function crossduel_view_user_has_passed_activity(stdClass $crossduel, int $userid): bool {
    $percentage = crossduel_get_user_solved_percentage((int)$crossduel->id, $userid);
    $passpercentage = isset($crossduel->passpercentage) ? (float)$crossduel->passpercentage : 60.0;

    return $percentage >= $passpercentage;
}


/**
 * Record that a user is currently present in this Cross Duel activity.
 *
 * The timestamp is refreshed whenever the activity page loads, including
 * lightweight AJAX polling requests, so the lobby can show only learners who
 * are actually here now.
 *
 * @param int $crossduelid
 * @param int $userid
 * @return void
 */
function crossduel_view_touch_presence(int $crossduelid, int $userid): void {
    global $DB;

    $record = $DB->get_record('crossduel_presence', [
        'crossduelid' => $crossduelid,
        'userid' => $userid,
    ], '*', IGNORE_MISSING);

    if ($record) {
        $record->lastseen = time();
        $DB->update_record('crossduel_presence', $record);
        return;
    }

    $record = new stdClass();
    $record->crossduelid = $crossduelid;
    $record->userid = $userid;
    $record->lastseen = time();
    $DB->insert_record('crossduel_presence', $record);
}

/**
 * Returns a friendly label for learners who are currently present in this activity.
 *
 * @param int $lastseen
 * @return string
 */
function crossduel_view_get_presence_label(int $lastseen): string {
    return get_string('presence_lastseen', 'crossduel', userdate($lastseen));
}

/**
 * Get available multiplayer partners who are currently present in this specific activity.
 *
 * Exclude users who:
 * - are the current user
 * - are suspended or deleted
 * - cannot play the activity
 * - already passed this activity
 * - are already in invited or active game state for this activity
 * - have not been seen in this activity recently
 *
 * @param stdClass $crossduel
 * @param stdClass $course
 * @param context_module $context
 * @param int $currentuserid
 * @return array
 */
function crossduel_view_get_available_multiplayer_partners(stdClass $crossduel, stdClass $course, context_module $context, int $currentuserid): array {
    global $DB;

    $cutoff = time() - 180;

    $presence = $DB->get_records_select(
        'crossduel_presence',
        'crossduelid = ? AND lastseen >= ?',
        [(int)$crossduel->id, $cutoff],
        'lastseen DESC'
    );

    if (!$presence) {
        return [];
    }

    $users = get_enrolled_users($context, 'mod/crossduel:play');
    $usersbyid = [];
    foreach ($users as $user) {
        $usersbyid[(int)$user->id] = $user;
    }

    $available = [];

    foreach ($presence as $record) {
        $userid = (int)$record->userid;

        if ($userid === $currentuserid) {
            continue;
        }

        if (!isset($usersbyid[$userid])) {
            continue;
        }

        $user = $usersbyid[$userid];

        if (!empty($user->deleted) || !empty($user->suspended)) {
            continue;
        }

        if (!has_capability('mod/crossduel:play', $context, $userid)) {
            continue;
        }

        if (crossduel_view_user_has_passed_activity($crossduel, $userid)) {
            continue;
        }

        if (crossduel_view_user_is_busy_in_multiplayer((int)$crossduel->id, $userid)) {
            continue;
        }

        $user->crossduel_lastactive = crossduel_view_get_presence_label((int)$record->lastseen);
        $available[] = $user;
    }

    usort($available, function($a, $b) {
        return strcmp(fullname($a), fullname($b));
    });

    return $available;
}

/**
 * Send a multiplayer invitation by creating an invited crossduel_game row.
 *
 * @param stdClass $crossduel
 * @param int $fromuserid
 * @param int $touserid
 * @return void
 */
function crossduel_view_create_invitation(stdClass $crossduel, int $fromuserid, int $touserid): void {
    global $DB;

    $game = new stdClass();
    $game->crossduelid = (int)$crossduel->id;
    $game->playera = $fromuserid;
    $game->playerb = $touserid;
    $game->horizontalplayer = 0;
    $game->verticalplayer = 0;
    $game->currentturn = 0;
    $game->status = 'invited';
    $game->boardstatejson = null;
    $game->revealedcellsjson = null;
    $game->solvedwordsjson = '[]';
    $game->playerascore = 0;
    $game->playerbscore = 0;
    $game->lastmove = 'Invitation sent';
    $game->lastplayer = $fromuserid;
    $game->lastmovetime = time();
    $game->timecreated = time();
    $game->timemodified = time();

    $DB->insert_record('crossduel_game', $game);
}

/**
 * Accept an invitation safely.
 *
 * @param stdClass $game
 * @return void
 */
function crossduel_view_accept_invitation(stdClass $game): void {
    global $DB;

    $game->status = 'active';
    $game->horizontalplayer = (int)$game->playera;
    $game->verticalplayer = (int)$game->playerb;
    $game->currentturn = (int)$game->playera;
    $game->solvedwordsjson = '[]';
    $game->lastmove = 'Invitation accepted';
    $game->lastplayer = (int)$game->playerb;
    $game->lastmovetime = time();
    $game->timemodified = time();

    $DB->update_record('crossduel_game', $game);
}

/**
 * Decline an invitation safely.
 *
 * @param stdClass $game
 * @return void
 */
function crossduel_view_decline_invitation(stdClass $game): void {
    global $DB;

    $game->status = 'declined';
    $game->lastmove = 'Invitation declined';
    $game->lastplayer = (int)$game->playerb;
    $game->lastmovetime = time();
    $game->timemodified = time();

    $DB->update_record('crossduel_game', $game);
}

/**
 * Get a simple role label for the current user in an active game.
 *
 * @param stdClass $game
 * @param int $userid
 * @return string
 */
function crossduel_view_get_multiplayer_role_label(stdClass $game, int $userid): string {
    if ((int)$game->horizontalplayer === $userid) {
        return 'Across';
    }

    if ((int)$game->verticalplayer === $userid) {
        return 'Down';
    }

    return 'Not yet assigned';
}

/*
 * -------------------------------------------------------------
 * Mark the activity as viewed for completion tracking
 * -------------------------------------------------------------
 */

/**
 * Decode shared solved word ids from a multiplayer game.
 *
 * @param stdClass $game
 * @return array
 */
function crossduel_view_get_multiplayer_solved_word_ids(stdClass $game): array {
    $decoded = [];
    $raw = isset($game->solvedwordsjson) ? (string)$game->solvedwordsjson : '[]';
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        return $decoded;
    }

    foreach ($data as $wordid) {
        $decoded[(int)$wordid] = true;
    }

    return $decoded;
}

/**
 * Save shared solved word ids back to the multiplayer game row.
 *
 * @param stdClass $game
 * @param array $solvedwordids
 * @return void
 */
function crossduel_view_save_multiplayer_solved_word_ids(stdClass $game, array $solvedwordids): void {
    global $DB;

    $ids = array_map('intval', array_keys($solvedwordids));
    sort($ids);

    $game->solvedwordsjson = json_encode(array_values($ids));
    $game->timemodified = time();

    $DB->update_record('crossduel_game', $game);
}

/**
 * Get the direction owned by the current user in an active multiplayer game.
 *
 * @param stdClass $game
 * @param int $userid
 * @return string Empty string if none
 */
function crossduel_view_get_multiplayer_user_direction(stdClass $game, int $userid): string {
    if ((int)$game->horizontalplayer === $userid) {
        return 'H';
    }

    if ((int)$game->verticalplayer === $userid) {
        return 'V';
    }

    return '';
}

/**
 * Check whether a multiplayer word belongs to the user's role direction.
 *
 * @param stdClass $row
 * @param string $userdirection
 * @return bool
 */
function crossduel_view_multiplayer_word_allowed(stdClass $row, string $userdirection): bool {
    return $userdirection !== '' && $row->direction === $userdirection;
}


/**
 * Check whether there are unsolved multiplayer words remaining for a direction.
 *
 * @param array $layoutrows
 * @param array $solvedwordids
 * @param string $direction
 * @return bool
 */
function crossduel_view_multiplayer_has_unsolved_direction_words(array $layoutrows, array $solvedwordids, string $direction): bool {
    foreach ($layoutrows as $row) {
        if ($row->direction !== $direction) {
            continue;
        }

        if (!isset($solvedwordids[(int)$row->wordid])) {
            return true;
        }
    }

    return false;
}

/**
 * Choose the next multiplayer turn intelligently.
 *
 * Rule:
 * - Normally pass to the other player.
 * - If the other player's direction has no unsolved clues left, keep the turn with the
 *   current player if their own direction still has unsolved clues.
 * - If neither direction has unsolved clues left, leave current turn unchanged and let
 *   completion logic close the game.
 *
 * @param stdClass $game
 * @param array $layoutrows
 * @param array $solvedwordids
 * @param int $currentuserid
 * @return int
 */
function crossduel_view_get_next_multiplayer_turn(stdClass $game, array $layoutrows, array $solvedwordids, int $currentuserid): int {
    $playeradirection = ((int)$game->horizontalplayer === (int)$game->playera) ? 'H' : 'V';
    $playerbdirection = ((int)$game->horizontalplayer === (int)$game->playerb) ? 'H' : 'V';

    $playerahas = crossduel_view_multiplayer_has_unsolved_direction_words($layoutrows, $solvedwordids, $playeradirection);
    $playerbhas = crossduel_view_multiplayer_has_unsolved_direction_words($layoutrows, $solvedwordids, $playerbdirection);

    $otheruserid = ((int)$currentuserid === (int)$game->playera) ? (int)$game->playerb : (int)$game->playera;

    if ((int)$otheruserid === (int)$game->playera && $playerahas) {
        return (int)$game->playera;
    }

    if ((int)$otheruserid === (int)$game->playerb && $playerbhas) {
        return (int)$game->playerb;
    }

    if ((int)$currentuserid === (int)$game->playera && $playerahas) {
        return (int)$game->playera;
    }

    if ((int)$currentuserid === (int)$game->playerb && $playerbhas) {
        return (int)$game->playerb;
    }

    return (int)$game->currentturn;
}

/**
 * Store one multiplayer move and update shared solved words if correct.
 *
 * @param stdClass $game
 * @param int $userid
 * @param stdClass $targetrow
 * @param string $submittedanswer
 * @param bool $correct
 * @return void
 */
function crossduel_view_store_multiplayer_move(stdClass $game, array $layoutrows, int $userid, stdClass $targetrow, string $submittedanswer, bool $correct): void {
    global $DB;

    $move = new stdClass();
    $move->gameid = (int)$game->id;
    $move->userid = $userid;
    $move->wordid = (int)$targetrow->wordid;
    $move->direction = $targetrow->direction;
    $move->submittedanswer = $submittedanswer;
    $move->correct = $correct ? 1 : 0;
    $move->pointsawarded = 0;
    $move->movesummary = $correct ? 'Correct multiplayer answer' : 'Incorrect multiplayer answer';
    $move->timecreated = time();

    $DB->insert_record('crossduel_move', $move);

    $solvedwordids = crossduel_view_get_multiplayer_solved_word_ids($game);
    if ($correct) {
        $solvedwordids[(int)$targetrow->wordid] = true;
    }

    $game->lastmove = $correct ? 'Correct multiplayer answer submitted' : 'Incorrect multiplayer answer submitted';
    $game->lastplayer = $userid;
    $game->lastmovetime = time();

    $game->currentturn = crossduel_view_get_next_multiplayer_turn($game, $layoutrows, $solvedwordids, $userid);

    crossduel_view_save_multiplayer_solved_word_ids($game, $solvedwordids);
}

/**
 * If all words are solved in multiplayer, complete the game and award equal grades.
 *
 * @param stdClass $game
 * @param array $layoutrows
 * @return void
 */
/**
 * Push an explicit final multiplayer grade to the gradebook.
 *
 * @param stdClass $crossduel
 * @param int $userid
 * @param float $rawgrade
 * @return void
 */
function crossduel_view_push_explicit_grade(stdClass $crossduel, int $userid, float $rawgrade): void {
    require_once(__DIR__ . '/../../lib/gradelib.php');

    $item = [];
    $item['itemname'] = clean_param($crossduel->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax'] = isset($crossduel->grade) ? (float)$crossduel->grade : 100.0;
    $item['grademin'] = 0;

    $grade = new stdClass();
    $grade->userid = $userid;
    $grade->rawgrade = $rawgrade;
    $grade->datesubmitted = time();
    $grade->dategraded = time();

    grade_update(
        'mod/crossduel',
        $crossduel->course,
        'mod',
        'crossduel',
        $crossduel->id,
        0,
        [$userid => $grade],
        $item
    );
}

function crossduel_view_finalize_multiplayer_if_complete(stdClass $game, array $layoutrows): void {
    global $DB;

    $required = [];
    foreach ($layoutrows as $row) {
        $required[(int)$row->wordid] = true;
    }

    $solved = crossduel_view_get_multiplayer_solved_word_ids($game);

    foreach ($required as $wordid => $unused) {
        if (!isset($solved[$wordid])) {
            return;
        }
    }

    if ($game->status !== 'completed') {
        $game->status = 'completed';
        $game->lastmove = 'Multiplayer puzzle completed';
        $game->lastmovetime = time();
        $game->timemodified = time();
        $DB->update_record('crossduel_game', $game);
    }

    $crossduel = $DB->get_record('crossduel', ['id' => $game->crossduelid], '*', MUST_EXIST);
    $finalgrade = isset($crossduel->grade) ? (float)$crossduel->grade : 100.0;

    // Equal full credit to both players for a completed shared multiplayer puzzle.
    crossduel_view_push_explicit_grade($crossduel, (int)$game->playera, $finalgrade);
    crossduel_view_push_explicit_grade($crossduel, (int)$game->playerb, $finalgrade);
}


if (!$ajax) {
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

    $event = \mod_crossduel\event\course_module_viewed::create([
        'objectid' => $crossduel->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('crossduel', $crossduel);
    $event->trigger();
}

/*
 * -------------------------------------------------------------
 * Multiplayer actions and state
 * -------------------------------------------------------------
 */
$multiplayermessage = '';
$multiplayerok = false;
$currentmultiplayergame = crossduel_view_get_user_current_multiplayer_game((int)$crossduel->id, (int)$USER->id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (optional_param('inviteplayer', '', PARAM_TEXT) !== '') {
        require_sesskey();

        $inviteuserid = required_param('inviteuserid', PARAM_INT);

        if ($currentmultiplayergame) {
            redirect($crossduelviewurl, get_string('err_active_or_pending', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
        } else {
            $availableusers = crossduel_view_get_available_multiplayer_partners($crossduel, $course, $context, (int)$USER->id);
            $availableids = array_map(function($u) {
                return (int)$u->id;
            }, $availableusers);

            if (!in_array($inviteuserid, $availableids, true)) {
                redirect($crossduelviewurl, get_string('err_learner_not_available', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
            } else {
                crossduel_view_create_invitation($crossduel, (int)$USER->id, $inviteuserid);
                redirect($crossduelviewurl, get_string('invitation_sent_success', 'crossduel'), null, \core\output\notification::NOTIFY_SUCCESS);
            }
        }
    }

    if (optional_param('acceptinvite', '', PARAM_TEXT) !== '') {
        require_sesskey();

        $acceptgameid = required_param('gameid', PARAM_INT);
        $game = $DB->get_record('crossduel_game', ['id' => $acceptgameid], '*', IGNORE_MISSING);

        if (!$game ||
            (int)$game->crossduelid !== (int)$crossduel->id ||
            (int)$game->playerb !== (int)$USER->id) {
            redirect($crossduelviewurl, get_string('err_invitation_unavailable', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
        } else if ($game->status === 'active') {
            // A stale second click or refresh after activation should not be treated as an error.
            redirect($crossduelviewurl, get_string('mp_already_active', 'crossduel'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($game->status !== 'invited') {
            redirect($crossduelviewurl, get_string('err_invitation_unavailable', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
        } else if ($currentmultiplayergame && (int)$currentmultiplayergame->id !== (int)$game->id) {
            redirect($crossduelviewurl, get_string('err_another_active_or_pending', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
        } else {
            crossduel_view_accept_invitation($game);
            redirect($crossduelviewurl, get_string('invitation_accepted_active', 'crossduel'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }

    if (optional_param('declineinvite', '', PARAM_TEXT) !== '') {
        require_sesskey();

        $declinegameid = required_param('gameid', PARAM_INT);
        $game = $DB->get_record('crossduel_game', ['id' => $declinegameid], '*', IGNORE_MISSING);

        if (!$game ||
            (int)$game->crossduelid !== (int)$crossduel->id ||
            (int)$game->playerb !== (int)$USER->id) {
            redirect($crossduelviewurl, get_string('err_invitation_unavailable', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
        } else if ($game->status === 'declined') {
            redirect($crossduelviewurl, get_string('invitation_already_declined', 'crossduel'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($game->status === 'active') {
            redirect($crossduelviewurl, get_string('mp_already_active', 'crossduel'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($game->status !== 'invited') {
            redirect($crossduelviewurl, get_string('err_invitation_unavailable', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
        } else {
            crossduel_view_decline_invitation($game);
            redirect($crossduelviewurl, get_string('invitation_declined', 'crossduel'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}

$incominginvites = crossduel_view_get_incoming_invites((int)$crossduel->id, (int)$USER->id);
$availablepartners = [];

/*
 * -------------------------------------------------------------
 * AJAX polling responses
 * -------------------------------------------------------------
 */
if ($ajax && !$currentmultiplayergame) {
    $latestgameforpoll = $DB->get_records_select(
        'crossduel_game',
        'crossduelid = ? AND (playera = ? OR playerb = ?)',
        [(int)$crossduel->id, (int)$USER->id, (int)$USER->id],
        'id DESC',
        '*',
        0,
        1
    );

    $latestgameforpoll = $latestgameforpoll ? reset($latestgameforpoll) : false;

    $payload = [
        'mode' => 'lobby',
        'pendinginvitecount' => count($incominginvites),
        'latestgameid' => $latestgameforpoll ? (int)$latestgameforpoll->id : 0,
        'latestgamestatus' => $latestgameforpoll ? (string)$latestgameforpoll->status : '',
        'latesttimemodified' => $latestgameforpoll ? (int)$latestgameforpoll->timemodified : 0,
        'latestlastmovetime' => $latestgameforpoll ? (int)$latestgameforpoll->lastmovetime : 0,
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

if ($ajax && $currentmultiplayergame) {
    $payload = [
        'mode' => 'game',
        'gameid' => (int)$currentmultiplayergame->id,
        'status' => (string)$currentmultiplayergame->status,
        'playera' => (int)$currentmultiplayergame->playera,
        'playerb' => (int)$currentmultiplayergame->playerb,
        'horizontalplayer' => (int)$currentmultiplayergame->horizontalplayer,
        'verticalplayer' => (int)$currentmultiplayergame->verticalplayer,
        'currentturn' => (int)$currentmultiplayergame->currentturn,
        'timemodified' => (int)$currentmultiplayergame->timemodified,
        'lastmove' => (string)$currentmultiplayergame->lastmove,
        'lastplayer' => (int)$currentmultiplayergame->lastplayer,
        'lastmovetime' => (int)$currentmultiplayergame->lastmovetime,
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

if (!$currentmultiplayergame) {
    $availablepartners = crossduel_view_get_available_multiplayer_partners($crossduel, $course, $context, (int)$USER->id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param('submitmultiplayeranswer', '', PARAM_TEXT) !== '') {
    require_sesskey();

    $currentmultiplayergame = crossduel_view_get_user_current_multiplayer_game((int)$crossduel->id, (int)$USER->id);

    if (!$currentmultiplayergame || $currentmultiplayergame->status !== 'active') {
        redirect($crossduelviewurl, get_string('mp_no_active', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    if ((int)$currentmultiplayergame->currentturn !== (int)$USER->id) {
        redirect($crossduelviewurl, get_string('mp_not_turn', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    $userdirection = crossduel_view_get_multiplayer_user_direction($currentmultiplayergame, (int)$USER->id);
    if ($userdirection === '') {
        redirect($crossduelviewurl, get_string('mp_role_error', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    $layoutrows_for_submit = crossduel_view_get_approved_layout_rows((int)$crossduel->id);
    $submittedwordid = required_param('wordid', PARAM_INT);
    $submittedanswer = trim((string)required_param('useranswer', PARAM_TEXT));

    $targetrow = null;
    foreach ($layoutrows_for_submit as $row) {
        if ((int)$row->wordid === $submittedwordid) {
            $targetrow = $row;
            break;
        }
    }

    if (!$targetrow) {
        redirect($crossduelviewurl, get_string('mp_clue_missing', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    if (!crossduel_view_multiplayer_word_allowed($targetrow, $userdirection)) {
        redirect($crossduelviewurl, get_string('mp_wrong_direction', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    $multiplayersolved = crossduel_view_get_multiplayer_solved_word_ids($currentmultiplayergame);
    if (isset($multiplayersolved[(int)$targetrow->wordid])) {
        redirect($crossduelviewurl, get_string('mp_already_solved', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    if ($submittedanswer === '') {
        redirect($crossduelviewurl, get_string('error_emptyanswer', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    $normalizedanswer = core_text::strtoupper($submittedanswer);
    $normalizedanswer = preg_replace('/[^[:alnum:]]/u', '', $normalizedanswer);
    $correct = ($normalizedanswer === $targetrow->normalizedword);

    crossduel_view_store_multiplayer_move($currentmultiplayergame, $layoutrows_for_submit, (int)$USER->id, $targetrow, $submittedanswer, $correct);
    $currentmultiplayergame = $DB->get_record('crossduel_game', ['id' => $currentmultiplayergame->id], '*', MUST_EXIST);
    crossduel_view_finalize_multiplayer_if_complete($currentmultiplayergame, $layoutrows_for_submit);

    if ($correct) {
        redirect($crossduelviewurl, get_string('mp_correct', 'crossduel'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($crossduelviewurl, get_string('mp_incorrect', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }
}

/*
 * -------------------------------------------------------------
 * Load approved layout if present
 * -------------------------------------------------------------
 */
$layoutrows = [];
$grid = [];
$bounds = [];
$matrix = [];
$clues = [
    'across' => [],
    'down' => [],
];
$startcellnumbers = [];
$revealedcells = [];
$attempt = null;
$solvedwordids = [];
$submissionmessage = '';
$submissionok = false;

if (!empty($crossduel->layoutapproved)) {
    $layoutrows = crossduel_view_get_approved_layout_rows((int)$crossduel->id);

    if (!empty($layoutrows)) {
        $attempt = crossduel_view_get_or_create_attempt((int)$crossduel->id, (int)$USER->id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param('submitanswer', '', PARAM_TEXT) !== '') {
            require_sesskey();

            $submittedwordid = required_param('wordid', PARAM_INT);
            $submittedanswer = trim((string)required_param('useranswer', PARAM_TEXT));

            $targetrow = null;
            foreach ($layoutrows as $row) {
                if ((int)$row->wordid === $submittedwordid) {
                    $targetrow = $row;
                    break;
                }
            }

            if (!$targetrow) {
                redirect($crossduelviewurl, get_string('error_notfound', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
            } else if ($submittedanswer === '') {
                redirect($crossduelviewurl, get_string('error_emptyanswer', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
            } else {
                $normalizedanswer = core_text::strtoupper($submittedanswer);
                $normalizedanswer = preg_replace('/[^[:alnum:]]/u', '', $normalizedanswer);

                $correct = ($normalizedanswer === $targetrow->normalizedword);

                crossduel_view_store_attempt_word(
                    (int)$attempt->id,
                    (int)$targetrow->wordid,
                    $submittedanswer,
                    $correct
                );

                if ($correct) {
                    crossduel_update_user_grade($crossduel, (int)$USER->id);
                    redirect($crossduelviewurl, get_string('correct_answer', 'crossduel'), null, \core\output\notification::NOTIFY_SUCCESS);
                } else {
                    redirect($crossduelviewurl, get_string('incorrect_answer', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
                }
            }
        }

        if ($currentmultiplayergame && in_array($currentmultiplayergame->status, ['active', 'completed'])) {
            $solvedwordids = crossduel_view_get_multiplayer_solved_word_ids($currentmultiplayergame);
        } else {
            $solvedwordids = crossduel_view_get_solved_word_ids((int)$attempt->id);
            crossduel_view_update_attempt_completion($attempt, $layoutrows, $solvedwordids);
        }

        $grid = crossduel_view_build_grid($layoutrows);
        $bounds = crossduel_view_get_bounds($layoutrows);
        $clues = crossduel_view_split_clues($layoutrows);
        $startcellnumbers = crossduel_view_get_startcell_numbers($layoutrows);
        $revealedcells = crossduel_view_get_revealed_cells($layoutrows, (float)$crossduel->revealpercent, $solvedwordids);
        $matrix = crossduel_view_build_matrix($grid, $bounds, $revealedcells);
    }
}

/*
 * -------------------------------------------------------------
 * Build one combined clue selection list and detect completion
 * -------------------------------------------------------------
 */
$clueselectoptions = [];
$firstunsolvedwordid = 0;
$allwordssolved = !empty($layoutrows);

if (!empty($layoutrows)) {
    foreach ($layoutrows as $row) {
        $directionlabel = ($row->direction === 'H') ? get_string('across', 'crossduel') : get_string('down', 'crossduel');
        $issolved = isset($solvedwordids[(int)$row->wordid]);

        if (!$issolved && $firstunsolvedwordid === 0) {
            $firstunsolvedwordid = (int)$row->wordid;
        }

        if (!$issolved) {
            $allwordssolved = false;
        }

        $clueselectoptions[] = [
            'wordid' => (int)$row->wordid,
            'label' => $row->cluenumber . ' ' . $directionlabel . ' - ' . $row->cluetext,
            'solved' => $issolved,
        ];
    }
}

/*
 * -------------------------------------------------------------
 * Page styles
 * -------------------------------------------------------------
 */

$multiplayerclueselectoptions = [];
$multiplayerallsolved = !empty($layoutrows);

if (!empty($layoutrows) && $currentmultiplayergame && in_array($currentmultiplayergame->status, ['active', 'completed'])) {
    $userdirection = crossduel_view_get_multiplayer_user_direction($currentmultiplayergame, (int)$USER->id);

    foreach ($layoutrows as $row) {
        $issolved = isset($solvedwordids[(int)$row->wordid]);

        if (!$issolved) {
            $multiplayerallsolved = false;
        }

        if (!crossduel_view_multiplayer_word_allowed($row, $userdirection)) {
            continue;
        }

        $directionlabel = ($row->direction === 'H') ? get_string('across', 'crossduel') : get_string('down', 'crossduel');
        $multiplayerclueselectoptions[] = [
            'wordid' => (int)$row->wordid,
            'label' => $row->cluenumber . ' ' . $directionlabel . ' - ' . $row->cluetext,
            'solved' => $issolved,
        ];
    }
}

$styles = '
.crossduel-shell {
    max-width: 1180px;
}
.crossduel-status-card,
.crossduel-board-card,
.crossduel-clues-card,
.crossduel-answer-card,
.crossduel-complete-card,
.crossduel-multiplayer-card {
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: #ffffff;
    padding: 1rem;
    margin-bottom: 1rem;
}
.crossduel-complete-card {
    background: linear-gradient(180deg, #ecfdf3 0%, #dcfce7 100%);
    border-color: #86efac;
}
.crossduel-complete-card h3,
.crossduel-multiplayer-card h3 {
    margin-top: 0;
    margin-bottom: 0.5rem;
}
.crossduel-hero {
    border: 1px solid #d9e2f2;
    background: linear-gradient(180deg, #f7faff 0%, #eef4ff 100%);
    border-radius: 14px;
    padding: 1.2rem;
    margin-bottom: 1rem;
}
.crossduel-hero h2 {
    margin: 0 0 0.35rem 0;
    font-size: 2rem;
}
.crossduel-subtitle {
    font-size: 1.05rem;
    color: #344054;
    margin-bottom: 0;
}
.crossduel-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    align-items: start;
}
.crossduel-grid {
    border-collapse: collapse;
    margin-top: 0.5rem;
}
.crossduel-grid td {
    width: 40px;
    height: 40px;
    border: 1px solid #cbd5e1;
    padding: 0;
}
.crossduel-grid td.crossduel-revealed {
    background: #eef6ff;
    color: #111827;
}
.crossduel-grid td.crossduel-hidden {
    background: #ffffff;
    color: #111827;
}
.crossduel-grid td.crossduel-empty {
    background: #1f2937;
    border-color: #1f2937;
}
.crossduel-cell-inner {
    position: relative;
    width: 100%;
    height: 100%;
}
.crossduel-cell-number {
    position: absolute;
    top: 2px;
    left: 3px;
    font-size: 0.58rem;
    line-height: 1;
    color: #475467;
    font-weight: 700;
}
.crossduel-cell-letter {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    color: #111827;
}
.crossduel-cell-hidden-blank {
    position: absolute;
    inset: 0;
    display: block;
}
.crossduel-note {
    color: #475467;
}
.crossduel-clue-section {
    margin-bottom: 1.25rem;
}
.crossduel-clue-section h3 {
    margin-top: 0;
    margin-bottom: 0.75rem;
}
.crossduel-clue-list,
.crossduel-partner-list,
.crossduel-invite-list {
    list-style: none;
    margin: 0;
    padding-left: 0;
}
.crossduel-clue-list li,
.crossduel-partner-list li,
.crossduel-invite-list li {
    margin-bottom: 0.75rem;
}
.crossduel-cluenumber {
    font-weight: 700;
}
.crossduel-meta {
    color: #667085;
    font-size: 0.92rem;
}
.crossduel-action-row {
    display: flex;
    gap: 0.6rem;
    flex-wrap: wrap;
    margin-top: 0.9rem;
}
.crossduel-answer-form label {
    font-weight: 700;
    display: block;
    margin-bottom: 0.35rem;
}
.crossduel-answer-form select,
.crossduel-answer-form input[type=text] {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    min-height: 2.8rem;
    margin-bottom: 0.9rem;
}
.crossduel-progress-note {
    font-size: 0.95rem;
    color: #475467;
}
.crossduel-tick {
    color: #15803d;
    font-weight: 700;
    margin-right: 0.35rem;
}
.crossduel-partner-row,
.crossduel-invite-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.8rem;
    align-items: center;
    border-bottom: 1px solid #f0f2f5;
    padding: 0.7rem 0;
}
.crossduel-partner-row:last-child,
.crossduel-invite-row:last-child {
    border-bottom: none;
}
.crossduel-small-note {
    color: #667085;
    font-size: 0.92rem;
}
@media (max-width: 900px) {
    .crossduel-layout,
    .crossduel-partner-row,
    .crossduel-invite-row {
        grid-template-columns: 1fr;
    }
}
';

echo $OUTPUT->header();
echo html_writer::tag('style', $styles);
echo html_writer::start_div('crossduel-shell');

echo $OUTPUT->heading(format_string($crossduel->name));

if (trim((string)$crossduel->intro) !== '') {
    echo $OUTPUT->box(format_module_intro('crossduel', $crossduel, $cm->id), 'generalbox mod_introbox', 'crossduelintro');
}


if (empty($crossduel->layoutapproved)) {
    echo $OUTPUT->notification(
        get_string('layoutnotapproved', 'crossduel'),
        'warning'
    );

    echo html_writer::start_div('crossduel-status-card');
    echo html_writer::tag(
        'p',
        get_string('layoutnotready', 'crossduel')
    );

    $previewurl = new moodle_url('/mod/crossduel/preview.php', ['id' => $cm->id]);

    if (has_capability('mod/crossduel:addinstance', $context)) {
        echo html_writer::div(
            html_writer::link($previewurl, get_string('openpreview', 'crossduel'), ['class' => 'btn btn-secondary']),
            'crossduel-action-row'
        );
    }

    echo html_writer::end_div();

    echo $OUTPUT->footer();
    exit;
}

if (empty($layoutrows)) {
    echo $OUTPUT->notification(
        get_string('layoutmissingrows', 'crossduel'),
        'warning'
    );

    echo html_writer::start_div('crossduel-status-card');
    echo html_writer::tag(
        'p',
        get_string('layoutreapprove', 'crossduel')
    );
    echo html_writer::end_div();

    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_div('crossduel-hero');
echo html_writer::tag('h2', get_string('boardtitle', 'crossduel'));
if ($currentmultiplayergame && $currentmultiplayergame->status === 'active') {
    echo html_writer::tag(
        'p',
        get_string('subtitle_active', 'crossduel'),
        ['class' => 'crossduel-subtitle']
    );
} else if ($currentmultiplayergame && $currentmultiplayergame->status === 'completed') {
    echo html_writer::tag(
        'p',
        get_string('subtitle_completed', 'crossduel'),
        ['class' => 'crossduel-subtitle']
    );
} else {
    echo html_writer::tag(
        'p',
        get_string('subtitle_single', 'crossduel'),
        ['class' => 'crossduel-subtitle']
    );
}
echo html_writer::end_div();

/*
 * -------------------------------------------------------------
 * Multiplayer invitation/status section
 * -------------------------------------------------------------
 */
echo html_writer::start_div('crossduel-multiplayer-card');
echo html_writer::tag('h3', get_string('multiplayer_title', 'crossduel'));

if ($currentmultiplayergame) {
    $opponentid = ((int)$currentmultiplayergame->playera === (int)$USER->id)
        ? (int)$currentmultiplayergame->playerb
        : (int)$currentmultiplayergame->playera;

    $opponent = core_user::get_user($opponentid, '*', IGNORE_MISSING);
    $opponentname = $opponent ? fullname($opponent) : get_string('unknownlearner', 'crossduel');

    if ($currentmultiplayergame->status === 'invited' && (int)$currentmultiplayergame->playera === (int)$USER->id) {
        echo html_writer::tag('p', get_string('invitation_sent', 'crossduel', s($opponentname)));
        echo html_writer::tag('p', get_string('waiting_response', 'crossduel'), ['class' => 'crossduel-small-note']);
        echo html_writer::tag('p', get_string('singleplayer_available', 'crossduel'), ['class' => 'crossduel-small-note']);
    } else if ($currentmultiplayergame->status === 'invited' && (int)$currentmultiplayergame->playerb === (int)$USER->id) {
        echo html_writer::tag('p', get_string('invited_you', 'crossduel', s($opponentname)));
        echo html_writer::tag('p', get_string('accept_notice', 'crossduel'), ['class' => 'crossduel-small-note']);

        echo html_writer::start_div('crossduel-action-row');

        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $PAGE->url->out(false),
            'style' => 'display:inline;'
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'gameid', 'value' => (int)$currentmultiplayergame->id]);
        echo html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'acceptinvite', 'value' => get_string('accept', 'crossduel'), 'class' => 'btn btn-primary']);
        echo html_writer::end_tag('form');

        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $PAGE->url->out(false),
            'style' => 'display:inline;'
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'gameid', 'value' => (int)$currentmultiplayergame->id]);
        echo html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'declineinvite', 'value' => get_string('decline', 'crossduel'), 'class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');

        echo html_writer::end_div();

        echo html_writer::tag('p', get_string('singleplayer_available_until_accept', 'crossduel'), ['class' => 'crossduel-small-note']);
    } else if ($currentmultiplayergame->status === 'active') {
        $rolelabel = crossduel_view_get_multiplayer_role_label($currentmultiplayergame, (int)$USER->id);
        $turnuser = core_user::get_user((int)$currentmultiplayergame->currentturn, '*', IGNORE_MISSING);
        $turnname = $turnuser ? fullname($turnuser) : get_string('unknownlearner', 'crossduel');

        echo html_writer::tag('p', get_string('already_active', 'crossduel'));
        echo html_writer::start_tag('ul');
        echo html_writer::tag('li', get_string('opponent', 'crossduel', s($opponentname)));
        echo html_writer::tag('li', get_string('yourrole', 'crossduel', s($rolelabel)));
        echo html_writer::tag('li', get_string('currentturn', 'crossduel', s($turnname)));
        echo html_writer::end_tag('ul');
        echo html_writer::tag('p', get_string('singleplayer_locked', 'crossduel'), ['class' => 'crossduel-small-note']);
        echo html_writer::tag('p', get_string('refresh_notice', 'crossduel'), ['class' => 'crossduel-small-note']);
    } else if ($currentmultiplayergame->status === 'completed') {
        $rolelabel = crossduel_view_get_multiplayer_role_label($currentmultiplayergame, (int)$USER->id);

        echo html_writer::tag('p', get_string('completed_title', 'crossduel'));
        echo html_writer::start_tag('ul');
        echo html_writer::tag('li', get_string('opponent', 'crossduel', s($opponentname)));
        echo html_writer::tag('li', get_string('yourrole', 'crossduel', s($rolelabel)));
        echo html_writer::tag('li', get_string('finalsharedstatus_completed', 'crossduel'));
        echo html_writer::end_tag('ul');
        echo html_writer::tag('p', get_string('completed_credit', 'crossduel'), ['class' => 'crossduel-small-note']);
        echo html_writer::tag('p', get_string('completed_board', 'crossduel'), ['class' => 'crossduel-small-note']);
    }
} else {
    if (!empty($incominginvites)) {
        echo html_writer::tag('p', get_string('invites_waiting', 'crossduel'));
        echo html_writer::start_tag('ul', ['class' => 'crossduel-invite-list']);

        foreach ($incominginvites as $invite) {
            $inviter = core_user::get_user((int)$invite->playera, '*', IGNORE_MISSING);
            $invitername = $inviter ? fullname($inviter) : get_string('anotherlearner', 'crossduel');

            echo html_writer::start_tag('li', ['class' => 'crossduel-invite-row']);
            echo html_writer::start_div();
            echo html_writer::tag('div', get_string('invite_prompt', 'crossduel', s($invitername)));
            echo html_writer::tag('div', get_string('invite_start', 'crossduel'), ['class' => 'crossduel-small-note']);
            echo html_writer::end_div();

            echo html_writer::start_div('crossduel-action-row');

            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $PAGE->url->out(false),
                'style' => 'display:inline;'
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'gameid', 'value' => (int)$invite->id]);
            echo html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'acceptinvite', 'value' => get_string('accept', 'crossduel'), 'class' => 'btn btn-primary']);
            echo html_writer::end_tag('form');

            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $PAGE->url->out(false),
                'style' => 'display:inline;'
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'gameid', 'value' => (int)$invite->id]);
            echo html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'declineinvite', 'value' => get_string('decline', 'crossduel'), 'class' => 'btn btn-secondary']);
            echo html_writer::end_tag('form');

            echo html_writer::end_div();
            echo html_writer::end_tag('li');
        }

        echo html_writer::end_tag('ul');
    }

    echo html_writer::tag('p', get_string('available_learners', 'crossduel'));

    if (empty($availablepartners)) {
        echo html_writer::tag('p', get_string('no_learners', 'crossduel'), ['class' => 'crossduel-small-note']);
    } else {
        echo html_writer::start_tag('ul', ['class' => 'crossduel-partner-list']);

        foreach ($availablepartners as $partner) {
            echo html_writer::start_tag('li', ['class' => 'crossduel-partner-row']);

            echo html_writer::start_div();
            echo html_writer::tag('div', s(fullname($partner)));
            echo html_writer::tag('div', s($partner->crossduel_lastactive), ['class' => 'crossduel-small-note']);
            echo html_writer::end_div();

            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $PAGE->url->out(false),
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'inviteuserid', 'value' => (int)$partner->id]);
            echo html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'inviteplayer', 'value' => get_string('invite', 'crossduel'), 'class' => 'btn btn-secondary']);
            echo html_writer::end_tag('form');

            echo html_writer::end_tag('li');
        }

        echo html_writer::end_tag('ul');
    }
}

echo html_writer::end_div();

if ($allwordssolved && !($currentmultiplayergame && $currentmultiplayergame->status === 'completed')) {
    echo html_writer::start_div('crossduel-complete-card');
    echo html_writer::tag('h3', get_string('puzzle_complete', 'crossduel'));
    echo html_writer::tag(
        'p',
        get_string('puzzle_done', 'crossduel')
    );
    echo html_writer::end_div();
}

if ($currentmultiplayergame && $currentmultiplayergame->status === 'completed') {
    echo html_writer::start_div('crossduel-complete-card');
    echo html_writer::tag('h3', get_string('completed_title', 'crossduel'));
    echo html_writer::tag(
        'p',
        get_string('multiplayer_done', 'crossduel')
    );
    echo html_writer::tag(
        'p',
        get_string('multiplayer_done_note', 'crossduel'),
        ['class' => 'crossduel-small-note']
    );
    echo html_writer::end_div();
}

echo html_writer::start_div('crossduel-layout');

/*
 * -------------------------------------------------------------
 * Clues panel first
 * -------------------------------------------------------------
 */
echo html_writer::start_div('crossduel-clues-card');
echo $OUTPUT->heading(get_string('clues', 'crossduel'), 3);

echo html_writer::start_div('crossduel-clue-section');
echo html_writer::tag('h3', get_string('across', 'crossduel'));

if (empty($clues['across'])) {
    echo html_writer::tag('p', get_string('no_across', 'crossduel'), ['class' => 'crossduel-note']);
} else {
    echo html_writer::start_tag('ul', ['class' => 'crossduel-clue-list']);

    foreach ($clues['across'] as $clue) {
        $issolved = isset($solvedwordids[$clue['wordid']]);
        $status = $issolved ? get_string('solved', 'crossduel') : get_string('unsolved', 'crossduel');
        $tick = $issolved ? html_writer::tag('span', '✓', ['class' => 'crossduel-tick']) : '';

        $text = $tick .
            html_writer::tag('span', $clue['cluenumber'] . '. ', ['class' => 'crossduel-cluenumber']) .
            s($clue['clue']) .
            html_writer::tag(
                'div',
                get_string('answerlengthstatus', 'crossduel', ['length' => $clue['length'], 'status' => $status]),
                ['class' => 'crossduel-meta']
            );

        echo html_writer::tag('li', $text);
    }

    echo html_writer::end_tag('ul');
}
echo html_writer::end_div();

echo html_writer::start_div('crossduel-clue-section');
echo html_writer::tag('h3', get_string('down', 'crossduel'));

if (empty($clues['down'])) {
    echo html_writer::tag('p', get_string('no_down', 'crossduel'), ['class' => 'crossduel-note']);
} else {
    echo html_writer::start_tag('ul', ['class' => 'crossduel-clue-list']);

    foreach ($clues['down'] as $clue) {
        $issolved = isset($solvedwordids[$clue['wordid']]);
        $status = $issolved ? get_string('solved', 'crossduel') : get_string('unsolved', 'crossduel');
        $tick = $issolved ? html_writer::tag('span', '✓', ['class' => 'crossduel-tick']) : '';

        $text = $tick .
            html_writer::tag('span', $clue['cluenumber'] . '. ', ['class' => 'crossduel-cluenumber']) .
            s($clue['clue']) .
            html_writer::tag(
                'div',
                get_string('answerlengthstatus', 'crossduel', ['length' => $clue['length'], 'status' => $status]),
                ['class' => 'crossduel-meta']
            );

        echo html_writer::tag('li', $text);
    }

    echo html_writer::end_tag('ul');
}
echo html_writer::end_div();

echo html_writer::end_div(); // clues card

/*
 * -------------------------------------------------------------
 * Board and action area
 * -------------------------------------------------------------
 */
echo html_writer::start_div();

echo html_writer::start_div('crossduel-board-card');
echo $OUTPUT->heading(get_string('yourpuzzleboard', 'crossduel'), 3);

if (empty($matrix)) {
    echo html_writer::tag('p', get_string('nogridreconstructed', 'crossduel'), ['class' => 'crossduel-note']);
} else {
    if ($currentmultiplayergame && in_array($currentmultiplayergame->status, ['active', 'completed'])) {
        echo html_writer::tag(
            'p',
            get_string('boardnote_multiplayer', 'crossduel'),
            ['class' => 'crossduel-note']
        );
    } else {
        echo html_writer::tag(
            'p',
            get_string('boardnote_single', 'crossduel'),
            ['class' => 'crossduel-note']
        );
    }

    echo html_writer::start_tag('table', ['class' => 'crossduel-grid']);

    foreach ($matrix as $rowcells) {
        echo html_writer::start_tag('tr');

        foreach ($rowcells as $cell) {
            if ($cell['letter'] === '') {
                echo html_writer::tag('td', '', ['class' => 'crossduel-empty']);
            } else {
                $key = $cell['row'] . ':' . $cell['col'];
                $numberhtml = '';

                if (isset($startcellnumbers[$key])) {
                    $numberhtml = html_writer::tag(
                        'span',
                        (string)$startcellnumbers[$key],
                        ['class' => 'crossduel-cell-number']
                    );
                }

                if ($cell['revealed']) {
                    $contenthtml = html_writer::tag(
                        'span',
                        s($cell['letter']),
                        ['class' => 'crossduel-cell-letter']
                    );

                    $innerhtml = html_writer::tag(
                        'div',
                        $numberhtml . $contenthtml,
                        ['class' => 'crossduel-cell-inner']
                    );

                    echo html_writer::tag('td', $innerhtml, ['class' => 'crossduel-revealed']);
                } else {
                    $contenthtml = html_writer::tag(
                        'span',
                        '&nbsp;',
                        ['class' => 'crossduel-cell-hidden-blank']
                    );

                    $innerhtml = html_writer::tag(
                        'div',
                        $numberhtml . $contenthtml,
                        ['class' => 'crossduel-cell-inner']
                    );

                    echo html_writer::tag('td', $innerhtml, ['class' => 'crossduel-hidden']);
                }
            }
        }

        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('table');
}
echo html_writer::end_div();

if (!$allwordssolved && !($currentmultiplayergame && in_array($currentmultiplayergame->status, ['active', 'completed']))) {
    echo html_writer::start_div('crossduel-answer-card');
    echo $OUTPUT->heading(get_string('singleplayerpanel', 'crossduel'), 3);

    $solvedcount = count($solvedwordids);
    $totalcount = count($layoutrows);

    echo html_writer::tag(
        'p',
        get_string('solvedwordsprogress', 'crossduel', ['solved' => $solvedcount, 'total' => $totalcount]),
        ['class' => 'crossduel-progress-note']
    );

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $PAGE->url->out(false),
        'class' => 'crossduel-answer-form',
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey(),
    ]);

    echo html_writer::tag('label', get_string('chooseclue', 'crossduel'), ['for' => 'wordid']);
    echo html_writer::start_tag('select', [
        'name' => 'wordid',
        'id' => 'wordid',
    ]);

    foreach ($clueselectoptions as $option) {
        $attributes = ['value' => $option['wordid']];

        if ($option['solved']) {
            $attributes['disabled'] = 'disabled';
        } else if ($option['wordid'] === $firstunsolvedwordid) {
            $attributes['selected'] = 'selected';
        }

        $label = $option['solved'] ? '✓ ' . $option['label'] : $option['label'];

        echo html_writer::tag('option', s($label), $attributes);
    }

    echo html_writer::end_tag('select');

    echo html_writer::tag('label', get_string('youranswer', 'crossduel'), ['for' => 'useranswer']);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'useranswer',
        'id' => 'useranswer',
        'value' => '',
        'autocomplete' => 'off',
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'name' => 'submitanswer',
        'value' => get_string('submitanswer', 'crossduel'),
        'class' => 'btn btn-primary',
    ]);

    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}

if ($currentmultiplayergame && $currentmultiplayergame->status === 'active' && !$allwordssolved) {
    $rolelabel = crossduel_view_get_multiplayer_role_label($currentmultiplayergame, (int)$USER->id);
    $ismyturn = ((int)$currentmultiplayergame->currentturn === (int)$USER->id);

    echo html_writer::start_div('crossduel-answer-card');
    echo $OUTPUT->heading(get_string('multiplayerpanel', 'crossduel'), 3);
    echo html_writer::tag('p', get_string('yourmultiplayerrole', 'crossduel', s($rolelabel)));
    echo html_writer::tag(
        'p',
        $ismyturn ? get_string('mp_turn_yes', 'crossduel') : get_string('mp_turn_no', 'crossduel'),
        ['class' => 'crossduel-progress-note']
    );

    if ($multiplayerallsolved) {
        echo html_writer::tag('p', get_string('mp_all_solved', 'crossduel'), ['class' => 'crossduel-small-note']);
    } else if (empty($multiplayerclueselectoptions)) {
        echo html_writer::tag('p', get_string('mp_none_in_direction', 'crossduel'), ['class' => 'crossduel-small-note']);
        echo html_writer::tag('p', get_string('mp_remaining_other', 'crossduel'), ['class' => 'crossduel-small-note']);
    } else if (!$ismyturn) {
        echo html_writer::tag('p', get_string('mp_refresh_your_turn', 'crossduel'), ['class' => 'crossduel-small-note']);
    } else {
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $PAGE->url->out(false),
            'class' => 'crossduel-answer-form',
        ]);

        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
        ]);

        echo html_writer::tag('label', get_string('chooseyourclue', 'crossduel'), ['for' => 'wordid']);
        echo html_writer::start_tag('select', [
            'name' => 'wordid',
            'id' => 'wordid',
        ]);

        $selecteddone = false;
        foreach ($multiplayerclueselectoptions as $option) {
            $attributes = ['value' => $option['wordid']];
            if ($option['solved']) {
                $attributes['disabled'] = 'disabled';
            } else if (!$selecteddone) {
                $attributes['selected'] = 'selected';
                $selecteddone = true;
            }

            $label = $option['solved'] ? '✓ ' . $option['label'] : $option['label'];
            echo html_writer::tag('option', s($label), $attributes);
        }

        echo html_writer::end_tag('select');

        echo html_writer::tag('label', get_string('youranswer', 'crossduel'), ['for' => 'useranswer']);
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'useranswer',
            'id' => 'useranswer',
            'value' => '',
            'autocomplete' => 'off',
        ]);

        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'name' => 'submitmultiplayeranswer',
            'value' => get_string('submitmultiplayeranswer', 'crossduel'),
            'class' => 'btn btn-primary',
        ]);

        echo html_writer::end_tag('form');
    }

    echo html_writer::end_div();
}

if ($currentmultiplayergame && $currentmultiplayergame->status === 'completed') {
    $rolelabel = crossduel_view_get_multiplayer_role_label($currentmultiplayergame, (int)$USER->id);

    echo html_writer::start_div('crossduel-answer-card');
    echo $OUTPUT->heading(get_string('multiplayercompleted', 'crossduel'), 3);
    echo html_writer::tag('p', get_string('yourmultiplayerrole', 'crossduel', s($rolelabel)));
    echo html_writer::tag(
        'p',
        get_string('mp_no_further_answers', 'crossduel'),
        ['class' => 'crossduel-progress-note']
    );
    echo html_writer::tag(
        'p',
        get_string('mp_refresh_confirm', 'crossduel'),
        ['class' => 'crossduel-small-note']
    );
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo html_writer::end_div(); // layout

echo html_writer::end_div(); // shell

/*
 * -------------------------------------------------------------
 * Automatic polling for lobby mode and current game mode
 * -------------------------------------------------------------
 */
if (!$currentmultiplayergame) {
    $pollurl = new moodle_url('/mod/crossduel/view.php', [
        'id' => $cm->id,
        'ajax' => 1,
    ]);

    $latestgameforpoll = $DB->get_records_select(
        'crossduel_game',
        'crossduelid = ? AND (playera = ? OR playerb = ?)',
        [(int)$crossduel->id, (int)$USER->id, (int)$USER->id],
        'id DESC',
        '*',
        0,
        1
    );

    $latestgameforpoll = $latestgameforpoll ? reset($latestgameforpoll) : false;

    $initialstate = [
        'mode' => 'lobby',
        'pendinginvitecount' => count($incominginvites),
        'latestgameid' => $latestgameforpoll ? (int)$latestgameforpoll->id : 0,
        'latestgamestatus' => $latestgameforpoll ? (string)$latestgameforpoll->status : '',
        'latesttimemodified' => $latestgameforpoll ? (int)$latestgameforpoll->timemodified : 0,
        'latestlastmovetime' => $latestgameforpoll ? (int)$latestgameforpoll->lastmovetime : 0,
    ];

    $lobbyjs = "
    (function() {
        var pollUrl = " . json_encode($pollurl->out(false)) . ";
        var currentState = " . json_encode($initialstate) . ";

        function statesDiffer(a, b) {
            return (
                parseInt(a.pendinginvitecount) !== parseInt(b.pendinginvitecount) ||
                parseInt(a.latestgameid) !== parseInt(b.latestgameid) ||
                String(a.latestgamestatus) !== String(b.latestgamestatus) ||
                parseInt(a.latesttimemodified) !== parseInt(b.latesttimemodified) ||
                parseInt(a.latestlastmovetime) !== parseInt(b.latestlastmovetime)
            );
        }

        function pollServer() {
            if (document.hidden) {
                return;
            }

            fetch(pollUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Lobby polling HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(function(serverState) {
                if (statesDiffer(currentState, serverState)) {
                    window.location.reload();
                }
            })
            .catch(function(error) {
                console.log('crossduel lobby polling error', error);
            });
        }

        window.setInterval(pollServer, 3000);
    })();
    ";

    $PAGE->requires->js_init_code($lobbyjs);
} else {
    $pollurl = new moodle_url('/mod/crossduel/view.php', [
        'id' => $cm->id,
        'ajax' => 1,
    ]);

    $initialstate = [
        'mode' => 'game',
        'gameid' => (int)$currentmultiplayergame->id,
        'status' => (string)$currentmultiplayergame->status,
        'playera' => (int)$currentmultiplayergame->playera,
        'playerb' => (int)$currentmultiplayergame->playerb,
        'horizontalplayer' => (int)$currentmultiplayergame->horizontalplayer,
        'verticalplayer' => (int)$currentmultiplayergame->verticalplayer,
        'currentturn' => (int)$currentmultiplayergame->currentturn,
        'timemodified' => (int)$currentmultiplayergame->timemodified,
        'lastmove' => (string)$currentmultiplayergame->lastmove,
        'lastplayer' => (int)$currentmultiplayergame->lastplayer,
        'lastmovetime' => (int)$currentmultiplayergame->lastmovetime,
    ];

    $gamejs = "
    (function() {
        var pollUrl = " . json_encode($pollurl->out(false)) . ";
        var currentState = " . json_encode($initialstate) . ";
        var isSubmitting = false;
        var multiplayerSubmit = document.querySelector('input[name=\"submitmultiplayeranswer\"]');

        if (multiplayerSubmit && multiplayerSubmit.form) {
            multiplayerSubmit.form.addEventListener('submit', function() {
                isSubmitting = true;
            });
        }

        function statesDiffer(a, b) {
            return (
                parseInt(a.gameid) !== parseInt(b.gameid) ||
                String(a.status) !== String(b.status) ||
                parseInt(a.playera) !== parseInt(b.playera) ||
                parseInt(a.playerb) !== parseInt(b.playerb) ||
                parseInt(a.horizontalplayer) !== parseInt(b.horizontalplayer) ||
                parseInt(a.verticalplayer) !== parseInt(b.verticalplayer) ||
                parseInt(a.currentturn) !== parseInt(b.currentturn) ||
                parseInt(a.timemodified) !== parseInt(b.timemodified) ||
                String(a.lastmove) !== String(b.lastmove) ||
                parseInt(a.lastplayer) !== parseInt(b.lastplayer) ||
                parseInt(a.lastmovetime) !== parseInt(b.lastmovetime)
            );
        }

        function pollServer() {
            if (isSubmitting || document.hidden) {
                return;
            }

            fetch(pollUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Game polling HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(function(serverState) {
                if (statesDiffer(currentState, serverState)) {
                    window.location.reload();
                }
            })
            .catch(function(error) {
                console.log('crossduel game polling error', error);
            });
        }

        window.setInterval(pollServer, 3000);
    })();
    ";

    $PAGE->requires->js_init_code($gamejs);
}

echo $OUTPUT->footer();
