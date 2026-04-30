<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Display and control a Cross Duel activity instance.
 *
 * @package    mod_crossduel
 * @author     Johan Venter <johan@myfutureway.co.za>
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


use mod_crossduel\local\answer_manager;
use mod_crossduel\local\attempt_manager;
use mod_crossduel\local\multiplayer_manager;
use mod_crossduel\local\presence_manager;
use mod_crossduel\local\puzzle_manager;

require('../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT); // Course module id.

$cm = get_coursemodule_from_id('crossduel', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$crossduel = $DB->get_record('crossduel', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/crossduel:play', $context);

presence_manager::touch_presence((int)$crossduel->id, (int)$USER->id);

$PAGE->set_url('/mod/crossduel/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($crossduel->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');


$crossduelviewurl = new moodle_url('/mod/crossduel/view.php', ['id' => $cm->id]);
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

/*
 * -------------------------------------------------------------
 * Multiplayer actions and state
 * -------------------------------------------------------------
 */
$multiplayermessage = '';
$multiplayerok = false;
$currentmultiplayergame = multiplayer_manager::get_user_current_game((int)$crossduel->id, (int)$USER->id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (optional_param('inviteplayer', '', PARAM_TEXT) !== '') {
        require_sesskey();

        $inviteuserid = required_param('inviteuserid', PARAM_INT);

        if ($currentmultiplayergame) {
            redirect($crossduelviewurl, get_string('err_active_or_pending', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
        } else {
            $availableusers = presence_manager::get_available_multiplayer_partners($crossduel, $course, $context, (int)$USER->id);
            $availableids = array_map(function($u) {
                return (int)$u->id;
            }, $availableusers);

            if (!in_array($inviteuserid, $availableids, true)) {
                redirect($crossduelviewurl, get_string('err_learner_not_available', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
            } else {
                multiplayer_manager::create_invitation($crossduel, (int)$USER->id, $inviteuserid);
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
            multiplayer_manager::accept_invitation($game);
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
            multiplayer_manager::decline_invitation($game);
            redirect($crossduelviewurl, get_string('invitation_declined', 'crossduel'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}

$incominginvites = multiplayer_manager::get_incoming_invites((int)$crossduel->id, (int)$USER->id);
$availablepartners = [];

if (!$currentmultiplayergame) {
    $availablepartners = presence_manager::get_available_multiplayer_partners($crossduel, $course, $context, (int)$USER->id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param('submitmultiplayeranswer', '', PARAM_TEXT) !== '') {
    require_sesskey();

    $currentmultiplayergame = multiplayer_manager::get_user_current_game((int)$crossduel->id, (int)$USER->id);

    if (!$currentmultiplayergame || $currentmultiplayergame->status !== 'active') {
        redirect($crossduelviewurl, get_string('mp_no_active', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    if ((int)$currentmultiplayergame->currentturn !== (int)$USER->id) {
        redirect($crossduelviewurl, get_string('mp_not_turn', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    $userdirection = multiplayer_manager::get_user_direction($currentmultiplayergame, (int)$USER->id);
    if ($userdirection === '') {
        redirect($crossduelviewurl, get_string('mp_role_error', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    $layoutrows_for_submit = puzzle_manager::get_approved_layout_rows((int)$crossduel->id);
    $submittedwordid = required_param('wordid', PARAM_INT);
    $submittedanswer = trim((string)required_param('useranswer', PARAM_TEXT));

    $targetrow = answer_manager::find_target_row($layoutrows_for_submit, $submittedwordid);

    if (!$targetrow) {
        redirect($crossduelviewurl, get_string('mp_clue_missing', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    if (!multiplayer_manager::word_allowed($targetrow, $userdirection)) {
        redirect($crossduelviewurl, get_string('mp_wrong_direction', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    $multiplayersolved = multiplayer_manager::get_solved_word_ids($currentmultiplayergame);
    if (isset($multiplayersolved[(int)$targetrow->wordid])) {
        redirect($crossduelviewurl, get_string('mp_already_solved', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    if ($submittedanswer === '') {
        redirect($crossduelviewurl, get_string('error_emptyanswer', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
    }

    $correct = answer_manager::is_correct($submittedanswer, $targetrow);

    multiplayer_manager::store_move($currentmultiplayergame, $layoutrows_for_submit, (int)$USER->id, $targetrow, $submittedanswer, $correct);
    $currentmultiplayergame = $DB->get_record('crossduel_game', ['id' => $currentmultiplayergame->id], '*', MUST_EXIST);
    multiplayer_manager::finalize_if_complete($currentmultiplayergame, $layoutrows_for_submit);

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
    $layoutrows = puzzle_manager::get_approved_layout_rows((int)$crossduel->id);

    if (!empty($layoutrows)) {
        $attempt = attempt_manager::get_or_create_attempt((int)$crossduel->id, (int)$USER->id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param('submitanswer', '', PARAM_TEXT) !== '') {
            require_sesskey();

            $submittedwordid = required_param('wordid', PARAM_INT);
            $submittedanswer = trim((string)required_param('useranswer', PARAM_TEXT));

            $targetrow = answer_manager::find_target_row($layoutrows, $submittedwordid);

            if (!$targetrow) {
                redirect($crossduelviewurl, get_string('error_notfound', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
            } else if ($submittedanswer === '') {
                redirect($crossduelviewurl, get_string('error_emptyanswer', 'crossduel'), null, \core\output\notification::NOTIFY_WARNING);
            } else {
                $correct = answer_manager::is_correct($submittedanswer, $targetrow);

                attempt_manager::store_attempt_word(
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
            $solvedwordids = multiplayer_manager::get_solved_word_ids($currentmultiplayergame);
        } else {
            $solvedwordids = attempt_manager::get_solved_word_ids((int)$attempt->id);
            attempt_manager::update_attempt_completion($attempt, $layoutrows, $solvedwordids);
        }

        $grid = puzzle_manager::build_grid($layoutrows);
        $bounds = puzzle_manager::get_bounds($layoutrows);
        $clues = puzzle_manager::split_clues($layoutrows);
        $startcellnumbers = puzzle_manager::get_startcell_numbers($layoutrows);
        $revealedcells = puzzle_manager::get_revealed_cells($layoutrows, (float)$crossduel->revealpercent, $solvedwordids);
        $matrix = puzzle_manager::build_matrix($grid, $bounds, $revealedcells, $startcellnumbers);
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
    $userdirection = multiplayer_manager::get_user_direction($currentmultiplayergame, (int)$USER->id);

    foreach ($layoutrows as $row) {
        $issolved = isset($solvedwordids[(int)$row->wordid]);

        if (!$issolved) {
            $multiplayerallsolved = false;
        }

        if (!multiplayer_manager::word_allowed($row, $userdirection)) {
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



$renderer = $PAGE->get_renderer('mod_crossduel');

echo $OUTPUT->header();
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

    $layoutstatuscontext = [
        'layoutnotapproved' => true,
        'layoutmissingrows' => false,
        'layoutnotready' => get_string('layoutnotready', 'crossduel'),
        'layoutreapprove' => '',
        'showpreview' => has_capability('mod/crossduel:addinstance', $context),
        'previewurl' => (new moodle_url('/mod/crossduel/preview.php', ['id' => $cm->id]))->out(false),
    ];

    echo $renderer->render_from_template('mod_crossduel/layout_status', $layoutstatuscontext);
    echo $OUTPUT->footer();
    exit;
}

if (empty($layoutrows)) {
    echo $OUTPUT->notification(
        get_string('layoutmissingrows', 'crossduel'),
        'warning'
    );

    $layoutstatuscontext = [
        'layoutnotapproved' => false,
        'layoutmissingrows' => true,
        'layoutnotready' => '',
        'layoutreapprove' => get_string('layoutreapprove', 'crossduel'),
        'showpreview' => false,
        'previewurl' => '',
    ];

    echo $renderer->render_from_template('mod_crossduel/layout_status', $layoutstatuscontext);
    echo $OUTPUT->footer();
    exit;
}
/*
 * -------------------------------------------------------------
 * Multiplayer invitation/status section
 * -------------------------------------------------------------
 */
$statuscontext = [
    'title' => get_string('multiplayer_title', 'crossduel'),
    'hasgame' => !empty($currentmultiplayergame),
    'hasnogame' => empty($currentmultiplayergame),
    'hasinvitedsent' => false,
    'hasinvitedreceived' => false,
    'hasactive' => false,
    'hascompleted' => false,
    'hascollapsiblecontent' => true,
    'sesskey' => sesskey(),
    'actionurl' => $PAGE->url->out(false),
    'acceptlabel' => get_string('accept', 'crossduel'),
    'declinelabel' => get_string('decline', 'crossduel'),
    'invitelabel' => get_string('invite', 'crossduel'),
];

if ($currentmultiplayergame) {
    $opponentid = ((int)$currentmultiplayergame->playera === (int)$USER->id)
        ? (int)$currentmultiplayergame->playerb
        : (int)$currentmultiplayergame->playera;

    $opponent = core_user::get_user($opponentid, '*', IGNORE_MISSING);
    $opponentname = $opponent ? fullname($opponent) : get_string('unknownlearner', 'crossduel');
    $rolelabel = multiplayer_manager::get_role_label($currentmultiplayergame, (int)$USER->id);

    if ($currentmultiplayergame->status === 'invited' && (int)$currentmultiplayergame->playera === (int)$USER->id) {
        $statuscontext['hasinvitedsent'] = true;
        $statuscontext['invitationtext'] = get_string('invitation_sent', 'crossduel', s($opponentname));
        $statuscontext['waitingtext'] = get_string('waiting_response', 'crossduel');
        $statuscontext['singleplayertext'] = get_string('singleplayer_available', 'crossduel');
    } else if ($currentmultiplayergame->status === 'invited' && (int)$currentmultiplayergame->playerb === (int)$USER->id) {
        $statuscontext['hasinvitedreceived'] = true;
        $statuscontext['gameid'] = (int)$currentmultiplayergame->id;
        $statuscontext['invitedtext'] = get_string('invited_you', 'crossduel', s($opponentname));
        $statuscontext['acceptnotice'] = get_string('accept_notice', 'crossduel');
        $statuscontext['singleplayeruntilaccept'] = get_string('singleplayer_available_until_accept', 'crossduel');
    } else if ($currentmultiplayergame->status === 'active') {
        $turnuser = core_user::get_user((int)$currentmultiplayergame->currentturn, '*', IGNORE_MISSING);
        $turnname = $turnuser ? fullname($turnuser) : get_string('unknownlearner', 'crossduel');

        $statuscontext['hasactive'] = true;
        $statuscontext['activetext'] = get_string('already_active', 'crossduel');
        $statuscontext['opponenttext'] = get_string('opponent', 'crossduel', s($opponentname));
        $statuscontext['roletext'] = get_string('yourrole', 'crossduel', s($rolelabel));
        $statuscontext['turntext'] = get_string('currentturn', 'crossduel', s($turnname));
        $statuscontext['lockedtext'] = get_string('singleplayer_locked', 'crossduel');
        $statuscontext['refreshtext'] = get_string('refresh_notice', 'crossduel');
    } else if ($currentmultiplayergame->status === 'completed') {
        $statuscontext['hascompleted'] = true;
        $statuscontext['completedtitle'] = get_string('completed_title', 'crossduel');
        $statuscontext['opponenttext'] = get_string('opponent', 'crossduel', s($opponentname));
        $statuscontext['roletext'] = get_string('yourrole', 'crossduel', s($rolelabel));
        $statuscontext['finalstatustext'] = get_string('finalsharedstatus_completed', 'crossduel');
        $statuscontext['completedcredit'] = get_string('completed_credit', 'crossduel');
        $statuscontext['completedboard'] = get_string('completed_board', 'crossduel');
    }
} else {
    $statuscontext['hasincominginvites'] = !empty($incominginvites);
    $statuscontext['incominginvites'] = [];
    $statuscontext['availabletext'] = get_string('available_learners', 'crossduel');
    $statuscontext['nolearnerstext'] = get_string('no_learners', 'crossduel');
    $statuscontext['haspartners'] = !empty($availablepartners);
    $statuscontext['nopartners'] = empty($availablepartners);
    $statuscontext['availablepartners'] = [];

    if (!empty($incominginvites)) {
        $statuscontext['inviteswaiting'] = get_string('invites_waiting', 'crossduel');

        foreach ($incominginvites as $invite) {
            $inviter = core_user::get_user((int)$invite->playera, '*', IGNORE_MISSING);
            $invitername = $inviter ? fullname($inviter) : get_string('anotherlearner', 'crossduel');

            $statuscontext['incominginvites'][] = [
                'gameid' => (int)$invite->id,
                'prompt' => get_string('invite_prompt', 'crossduel', s($invitername)),
                'starttext' => get_string('invite_start', 'crossduel'),
                'sesskey' => sesskey(),
                'actionurl' => $PAGE->url->out(false),
                'acceptlabel' => get_string('accept', 'crossduel'),
                'declinelabel' => get_string('decline', 'crossduel'),
            ];
        }
    }

    foreach ($availablepartners as $partner) {
        $statuscontext['availablepartners'][] = [
            'userid' => (int)$partner->id,
            'fullname' => s(fullname($partner)),
            'lastactive' => s($partner->crossduel_lastactive),
            'sesskey' => sesskey(),
            'actionurl' => $PAGE->url->out(false),
            'invitelabel' => get_string('invite', 'crossduel'),
        ];
    }
}

echo $renderer->render_from_template('mod_crossduel/multiplayer_status_panel', $statuscontext);

echo html_writer::start_div('crossduel-layout');
/*
 * -------------------------------------------------------------
 * Board and action area
 * -------------------------------------------------------------
 */
echo html_writer::start_div();

if ($currentmultiplayergame && in_array($currentmultiplayergame->status, ['active', 'completed'])) {
    $boardnote = get_string('boardnote_multiplayer', 'crossduel');
} else {
    $boardnote = get_string('boardnote_single', 'crossduel');
}

$boardcontext = [
    'hasmatrix' => !empty($matrix),
    'matrix' => $matrix,
    'boardnote' => $boardnote,
];

echo $renderer->render_from_template('mod_crossduel/puzzle_board', $boardcontext);

$hassingleplayerpanel = !$allwordssolved && !($currentmultiplayergame && in_array($currentmultiplayergame->status, ['active', 'completed']));
$singleplayeroptions = [];

foreach ($clueselectoptions as $option) {
    $singleplayeroptions[] = [
        'wordid' => (int)$option['wordid'],
        'label' => $option['solved'] ? '✓ ' . $option['label'] : $option['label'],
        'selected' => !$option['solved'] && (int)$option['wordid'] === (int)$firstunsolvedwordid,
        'disabled' => !empty($option['solved']),
    ];
}

$singleplayercontext = [
    'hassingleplayerpanel' => $hassingleplayerpanel,
    'solvedcount' => count($solvedwordids),
    'totalcount' => count($layoutrows),
    'progressnote' => get_string('solvedwordsprogress', 'crossduel', [
        'solved' => count($solvedwordids),
        'total' => count($layoutrows),
    ]),
    'sesskey' => sesskey(),
    'actionurl' => $PAGE->url->out(false),
    'clueselectoptions' => $singleplayeroptions,
];

echo $renderer->render_from_template('mod_crossduel/singleplayer_answer_panel', $singleplayercontext);

$multiplayeroptions = [];
$selecteddone = false;

foreach ($multiplayerclueselectoptions as $option) {
    $selected = false;

    if (empty($option['solved']) && !$selecteddone) {
        $selected = true;
        $selecteddone = true;
    }

    $multiplayeroptions[] = [
        'wordid' => (int)$option['wordid'],
        'label' => $option['solved'] ? '✓ ' . $option['label'] : $option['label'],
        'selected' => $selected,
        'disabled' => !empty($option['solved']),
    ];
}

$hasactivemultiplayerpanel = $currentmultiplayergame && $currentmultiplayergame->status === 'active' && !$allwordssolved;
$hascompletedmultiplayerpanel = $currentmultiplayergame && $currentmultiplayergame->status === 'completed';
$multiplayerrolelabel = '';
$ismyturn = false;

if ($currentmultiplayergame) {
    $multiplayerrolelabel = multiplayer_manager::get_role_label($currentmultiplayergame, (int)$USER->id);
    $ismyturn = ((int)$currentmultiplayergame->currentturn === (int)$USER->id);
}

$multiplayercontext = [
    'hasactivepanel' => $hasactivemultiplayerpanel,
    'hascompletedpanel' => $hascompletedmultiplayerpanel,
    'rolelabel' => $multiplayerrolelabel,
    'turnmessage' => $ismyturn ? get_string('mp_turn_yes', 'crossduel') : get_string('mp_turn_no', 'crossduel'),
    'multiplayerallsolved' => $multiplayerallsolved,
    'hasnooptions' => !$multiplayerallsolved && empty($multiplayeroptions),
    'isnotmyturn' => !$multiplayerallsolved && !empty($multiplayeroptions) && !$ismyturn,
    'showform' => !$multiplayerallsolved && !empty($multiplayeroptions) && $ismyturn,
    'sesskey' => sesskey(),
    'actionurl' => $PAGE->url->out(false),
    'clueselectoptions' => $multiplayeroptions,
];

echo $renderer->render_from_template('mod_crossduel/multiplayer_panel', $multiplayercontext);
/*
 * -------------------------------------------------------------
 * Clues panel first
 * -------------------------------------------------------------
 */
$acrossclues = [];
foreach ($clues['across'] as $clue) {
    $issolved = isset($solvedwordids[$clue['wordid']]);
    $status = $issolved ? get_string('solved', 'crossduel') : get_string('unsolved', 'crossduel');

    $acrossclues[] = [
        'cluenumber' => (int)$clue['cluenumber'],
        'clue' => s($clue['clue']),
        'tickhtml' => $issolved ? html_writer::tag('span', '✓', ['class' => 'crossduel-tick']) : '',
        'meta' => get_string('answerlengthstatus', 'crossduel', [
            'length' => (int)$clue['length'],
            'status' => $status,
        ]),
    ];
}

$downclues = [];
foreach ($clues['down'] as $clue) {
    $issolved = isset($solvedwordids[$clue['wordid']]);
    $status = $issolved ? get_string('solved', 'crossduel') : get_string('unsolved', 'crossduel');

    $downclues[] = [
        'cluenumber' => (int)$clue['cluenumber'],
        'clue' => s($clue['clue']),
        'tickhtml' => $issolved ? html_writer::tag('span', '✓', ['class' => 'crossduel-tick']) : '',
        'meta' => get_string('answerlengthstatus', 'crossduel', [
            'length' => (int)$clue['length'],
            'status' => $status,
        ]),
    ];
}

$cluescontext = [
    'hasacross' => !empty($acrossclues),
    'across' => $acrossclues,
    'hasdown' => !empty($downclues),
    'down' => $downclues,
];

echo $renderer->render_from_template('mod_crossduel/clues_panel', $cluescontext);

$completioncardscontext = [
    'showpuzzlecomplete' => $allwordssolved && !($currentmultiplayergame && $currentmultiplayergame->status === 'completed'),
    'showmultiplayercomplete' => $currentmultiplayergame && $currentmultiplayergame->status === 'completed',
];

echo $renderer->render_from_template('mod_crossduel/completion_cards', $completioncardscontext);
echo html_writer::end_div(); // layout

echo html_writer::end_div(); // shell

/*
 * -------------------------------------------------------------
 * Automatic polling using Moodle AMD + External Services
 * -------------------------------------------------------------
 */
$initialpollstate = [
    'gameid' => $currentmultiplayergame ? (int)$currentmultiplayergame->id : 0,
    'status' => $currentmultiplayergame ? (string)$currentmultiplayergame->status : '',
    'timemodified' => $currentmultiplayergame ? (int)$currentmultiplayergame->timemodified : 0,
    'lastmovetime' => $currentmultiplayergame ? (int)$currentmultiplayergame->lastmovetime : 0,
];

$PAGE->requires->js_call_amd('mod_crossduel/ui', 'init');

$PAGE->requires->js_call_amd('mod_crossduel/poller', 'init', [
    (int)$cm->id,
    $initialpollstate,
]);

echo $OUTPUT->footer();
