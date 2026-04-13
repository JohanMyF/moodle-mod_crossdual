<?php
// This file is part of Moodle - http://moodle.org/
//
// Core library functions for the Cross Duel activity module.

/**
 * Core library functions for mod_crossduel.
 *
 * This version includes:
 * - parsing and storage of teacher-authored word rows
 * - gradebook item creation
 * - single-player grade calculation from solved words
 *
 * IMPORTANT DESIGN CHOICE:
 * Grade is currently based on:
 *   solved approved words / total approved words
 *
 * This is appropriate for the current single-player mode.
 *
 * Later, when two-player mode is introduced, we can adapt the grade logic
 * so that both collaborating players receive equivalent completion credit
 * for a fully solved puzzle.
 *
 * @package    mod_crossduel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the features supported by this activity module.
 *
 * @param string $feature A Moodle feature constant.
 * @return mixed
 */
function crossduel_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;

        case FEATURE_SHOW_DESCRIPTION:
            return true;

        case FEATURE_GRADE_HAS_GRADE:
            return true;

        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;

        case FEATURE_BACKUP_MOODLE2:
            return true;

        default:
            return null;
    }
}

/**
 * Parse the raw teacher-entered word list into structured rows.
 *
 * Expected input format per non-blank line:
 *   word|clue
 *
 * @param string $rawtext
 * @return array
 */
function crossduel_parse_wordlist(string $rawtext): array {
    $rows = [];
    $lines = preg_split('/\r\n|\r|\n/', $rawtext);
    $sortorder = 1;

    foreach ($lines as $line) {
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

        if ($normalized === '') {
            continue;
        }

        $rows[] = [
            'rawword' => $word,
            'normalizedword' => $normalized,
            'cluetext' => $clue,
            'wordlength' => core_text::strlen($normalized),
            'sortorder' => $sortorder,
        ];

        $sortorder++;
    }

    return $rows;
}

/**
 * Replace all structured word rows for one Cross Duel activity.
 *
 * @param int $crossduelid
 * @param string $rawwordlist
 * @return void
 */
function crossduel_refresh_word_rows(int $crossduelid, string $rawwordlist): void {
    global $DB;

    $DB->delete_records('crossduel_word', ['crossduelid' => $crossduelid]);

    $rows = crossduel_parse_wordlist($rawwordlist);

    foreach ($rows as $row) {
        $record = new stdClass();
        $record->crossduelid = $crossduelid;
        $record->rawword = $row['rawword'];
        $record->normalizedword = $row['normalizedword'];
        $record->cluetext = $row['cluetext'];
        $record->wordlength = $row['wordlength'];
        $record->sortorder = $row['sortorder'];
        $record->enabled = 1;

        $DB->insert_record('crossduel_word', $record);
    }
}

/**
 * Creates a new Cross Duel activity instance.
 *
 * @param stdClass $data
 * @param mod_crossduel_mod_form $mform
 * @return int
 */
function crossduel_add_instance($data, $mform) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = time();
    $data->layoutapproved = 0;

    $data->id = $DB->insert_record('crossduel', $data);

    crossduel_refresh_word_rows((int)$data->id, (string)($data->wordlist ?? ''));

    crossduel_grade_item_update($data);

    return $data->id;
}

/**
 * Updates an existing Cross Duel activity instance.
 *
 * IMPORTANT:
 * Once learner play data exists, the puzzle definition is treated as locked.
 * Teachers should duplicate or create a new activity rather than altering a
 * live puzzle that already has attempts or multiplayer games attached to it.
 *
 * @param stdClass $data
 * @param mod_crossduel_mod_form $mform
 * @return bool
 */
function crossduel_update_instance($data, $mform) {
    global $DB;

    $data->id = $data->instance;

    $hasgames = $DB->record_exists('crossduel_game', ['crossduelid' => $data->id]);
    $hasattempts = $DB->record_exists('crossduel_attempt', ['crossduelid' => $data->id]);

    if ($hasgames || $hasattempts) {
        throw new moodle_exception(
            'This Cross Duel activity already contains learner play data and can no longer be edited safely. Please duplicate or create a new activity instead.'
        );
    }

    $data->timemodified = time();

    // Editing the activity invalidates any previously approved layout.
    $data->layoutapproved = 0;

    $result = $DB->update_record('crossduel', $data);

    crossduel_refresh_word_rows((int)$data->id, (string)($data->wordlist ?? ''));
    $DB->delete_records('crossduel_layoutslot', ['crossduelid' => $data->id]);

    crossduel_grade_item_update($data);

    return $result;
}

/**
 * Deletes a Cross Duel activity instance and its related data.
 *
 * @param int $id
 * @return bool
 */
function crossduel_delete_instance($id) {
    global $DB;

    $crossduel = $DB->get_record('crossduel', ['id' => $id], '*', IGNORE_MISSING);

    if (!$crossduel) {
        return false;
    }

    $games = $DB->get_records('crossduel_game', ['crossduelid' => $crossduel->id]);

    if ($games) {
        $gameids = array_keys($games);
        list($insql, $params) = $DB->get_in_or_equal($gameids);

        $DB->delete_records_select('crossduel_move', "gameid $insql", $params);
    }

    $attempts = $DB->get_records('crossduel_attempt', ['crossduelid' => $crossduel->id]);
    if ($attempts) {
        $attemptids = array_keys($attempts);
        list($insql, $params) = $DB->get_in_or_equal($attemptids);

        $DB->delete_records_select('crossduel_attempt_word', "attemptid $insql", $params);
    }

    $DB->delete_records('crossduel_attempt', ['crossduelid' => $crossduel->id]);
    $DB->delete_records('crossduel_game', ['crossduelid' => $crossduel->id]);
    $DB->delete_records('crossduel_layoutslot', ['crossduelid' => $crossduel->id]);
    $DB->delete_records('crossduel_word', ['crossduelid' => $crossduel->id]);
    $DB->delete_records('crossduel', ['id' => $crossduel->id]);

    crossduel_grade_item_delete($crossduel);

    return true;
}

/**
 * Count how many approved words exist in the currently active layout.
 *
 * @param int $crossduelid
 * @return int
 */
function crossduel_count_approved_words(int $crossduelid): int {
    global $DB;

    return (int)$DB->count_records('crossduel_layoutslot', [
        'crossduelid' => $crossduelid,
        'isactive' => 1,
    ]);
}

/**
 * Count how many approved words a given user has solved.
 *
 * @param int $crossduelid
 * @param int $userid
 * @return int
 */
function crossduel_count_user_solved_words(int $crossduelid, int $userid): int {
    global $DB;

    $sql = "
        SELECT COUNT(aw.id)
          FROM {crossduel_attempt_word} aw
          JOIN {crossduel_attempt} a
            ON a.id = aw.attemptid
         WHERE a.crossduelid = :crossduelid
           AND a.userid = :userid
           AND aw.issolved = 1
    ";

    return (int)$DB->count_records_sql($sql, [
        'crossduelid' => $crossduelid,
        'userid' => $userid,
    ]);
}

/**
 * Calculate the solved percentage for one user on one activity.
 *
 * @param int $crossduelid
 * @param int $userid
 * @return float
 */
function crossduel_get_user_solved_percentage(int $crossduelid, int $userid): float {
    $totalwords = crossduel_count_approved_words($crossduelid);

    if ($totalwords <= 0) {
        return 0.0;
    }

    $solvedwords = crossduel_count_user_solved_words($crossduelid, $userid);

    return ($solvedwords / $totalwords) * 100.0;
}

/**
 * Build one Moodle grade object for a user from solved progress.
 *
 * IMPORTANT:
 * Moodle grade_update works best when each user grade is a stdClass object
 * with fields such as userid and rawgrade.
 *
 * @param stdClass $crossduel
 * @param int $userid
 * @return stdClass|null
 */
function crossduel_get_user_grade_record(stdClass $crossduel, int $userid): ?stdClass {
    $maxgrade = isset($crossduel->grade) ? (float)$crossduel->grade : 100.0;

    if ($maxgrade <= 0) {
        return null;
    }

    $percentage = crossduel_get_user_solved_percentage((int)$crossduel->id, $userid);
    $rawgrade = round(($percentage / 100.0) * $maxgrade, 2);

    $grade = new stdClass();
    $grade->userid = $userid;
    $grade->rawgrade = $rawgrade;
    $grade->datesubmitted = time();
    $grade->dategraded = time();

    return $grade;
}

/**
 * Push one user’s current progress grade to the gradebook.
 *
 * @param stdClass $crossduel
 * @param int $userid
 * @return int
 */
function crossduel_update_user_grade(stdClass $crossduel, int $userid) {
    $grade = crossduel_get_user_grade_record($crossduel, $userid);

    if ($grade === null) {
        return crossduel_grade_item_update($crossduel);
    }

    // Key by userid for Moodle gradebook friendliness.
    $grades = [
        $userid => $grade,
    ];

    return crossduel_grade_item_update($crossduel, $grades);
}

/**
 * Creates or updates the gradebook item for this activity.
 *
 * @param stdClass $crossduel
 * @param mixed $grades
 * @return int
 */
function crossduel_grade_item_update($crossduel, $grades = null) {
    require_once(__DIR__ . '/../../lib/gradelib.php');

    $item = [];
    $item['itemname'] = clean_param($crossduel->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax'] = isset($crossduel->grade) ? (float)$crossduel->grade : 100.0;
    $item['grademin'] = 0;

    return grade_update(
        'mod/crossduel',
        $crossduel->course,
        'mod',
        'crossduel',
        $crossduel->id,
        0,
        $grades,
        $item
    );
}

/**
 * Deletes the gradebook item for this activity.
 *
 * @param stdClass $crossduel
 * @return int
 */
function crossduel_grade_item_delete($crossduel) {
    require_once(__DIR__ . '/../../lib/gradelib.php');

    $item = [];
    $item['deleted'] = 1;

    return grade_update(
        'mod/crossduel',
        $crossduel->course,
        'mod',
        'crossduel',
        $crossduel->id,
        0,
        null,
        $item
    );
}

/**
 * Updates grades for a specific user or for all users.
 *
 * @param stdClass $crossduel
 * @param int $userid
 * @return void
 */
function crossduel_update_grades($crossduel, $userid = 0) {
    global $DB;

    if (!empty($userid)) {
        crossduel_update_user_grade($crossduel, (int)$userid);
        return;
    }

    $attempts = $DB->get_records('crossduel_attempt', ['crossduelid' => $crossduel->id]);

    if (!$attempts) {
        crossduel_grade_item_update($crossduel);
        return;
    }

    $grades = [];

    foreach ($attempts as $attempt) {
        $grade = crossduel_get_user_grade_record($crossduel, (int)$attempt->userid);

        if ($grade !== null) {
            $grades[(int)$attempt->userid] = $grade;
        }
    }

    crossduel_grade_item_update($crossduel, $grades);
}
