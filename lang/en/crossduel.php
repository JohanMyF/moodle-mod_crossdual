<?php
// This file is part of Moodle - http://moodle.org/
//
// Language strings for the Cross Duel activity module.

/**
 * English language strings for mod_crossduel.
 *
 * @package    mod_crossduel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/*
 * -------------------------------------------------------------
 * Core plugin identity
 * -------------------------------------------------------------
 */
$string['pluginname'] = 'Cross Duel';
$string['modulename'] = 'Cross Duel';
$string['modulenameplural'] = 'Cross Duels';
$string['pluginadministration'] = 'Cross Duel administration';
$string['nonewmodules'] = 'No Cross Duel activities have been added to this course yet.';

/*
 * -------------------------------------------------------------
 * Capability strings
 * -------------------------------------------------------------
 */
$string['crossduel:addinstance'] = 'Add a new Cross Duel activity';
$string['crossduel:play'] = 'Play Cross Duel';

/*
 * -------------------------------------------------------------
 * Teacher-facing strings
 * -------------------------------------------------------------
 */
$string['activityname'] = 'Activity name';
$string['wordlist'] = 'Word list';
$string['wordlist_help'] = 'Enter one word and clue per line using the format word|clue.';
$string['revealpercent'] = 'Percentage of letters revealed at the start';
$string['passpercentage'] = 'Passing percentage';

/*
 * -------------------------------------------------------------
 * Learner-facing strings
 * -------------------------------------------------------------
 */
$string['welcome'] = 'Welcome to Cross Duel';
$string['inviteplayer'] = 'Invite player';
$string['waitingforopponent'] = 'Waiting for opponent';
$string['yourturn'] = 'It is your turn';
$string['notyourturn'] = 'Waiting for the other player';
$string['gamefinished'] = 'This game is finished';

/*
 * -------------------------------------------------------------
 * Validation and status strings
 * -------------------------------------------------------------
 */
$string['nowords'] = 'Please enter at least one valid word.';
$string['toomanywords'] = 'Please enter no more than 50 words.';
$string['invalidwordformat'] = 'Each line must use the format word|clue.';
$string['layoutnotapproved'] = 'This puzzle is not ready yet. The teacher must preview and approve a layout first.';

/*
 * -------------------------------------------------------------
 * Privacy API metadata strings
 * -------------------------------------------------------------
 */
$string['privacy:metadata:crossduel_attempt'] = 'Stores a learner\'s single-player attempt record for a Cross Duel activity.';
$string['privacy:metadata:crossduel_attempt:userid'] = 'The ID of the user making the single-player attempt.';
$string['privacy:metadata:crossduel_attempt:status'] = 'The status of the attempt, such as in progress or completed.';
$string['privacy:metadata:crossduel_attempt:timecreated'] = 'The time when the attempt was created.';
$string['privacy:metadata:crossduel_attempt:timemodified'] = 'The time when the attempt was last modified.';

$string['privacy:metadata:crossduel_attempt_word'] = 'Stores per-word answer and solve-state data inside a learner\'s single-player attempt.';
$string['privacy:metadata:crossduel_attempt_word:attemptid'] = 'The attempt to which this word record belongs.';
$string['privacy:metadata:crossduel_attempt_word:wordid'] = 'The puzzle word referenced by this record.';
$string['privacy:metadata:crossduel_attempt_word:issolved'] = 'Whether the learner solved this word correctly.';
$string['privacy:metadata:crossduel_attempt_word:useranswer'] = 'The most recent answer submitted by the learner for this word.';
$string['privacy:metadata:crossduel_attempt_word:timeanswered'] = 'The time when the learner last answered this word.';

$string['privacy:metadata:crossduel_game'] = 'Stores shared multiplayer game session data for Cross Duel.';
$string['privacy:metadata:crossduel_game:playera'] = 'The user ID of Player A in the multiplayer game.';
$string['privacy:metadata:crossduel_game:playerb'] = 'The user ID of Player B in the multiplayer game.';
$string['privacy:metadata:crossduel_game:horizontalplayer'] = 'The user ID assigned the horizontal clues.';
$string['privacy:metadata:crossduel_game:verticalplayer'] = 'The user ID assigned the vertical clues.';
$string['privacy:metadata:crossduel_game:currentturn'] = 'The user ID whose turn it currently is.';
$string['privacy:metadata:crossduel_game:status'] = 'The current status of the multiplayer game.';
$string['privacy:metadata:crossduel_game:lastmove'] = 'A readable summary of the most recent move in the game.';
$string['privacy:metadata:crossduel_game:lastplayer'] = 'The user ID of the learner who made the most recent move.';
$string['privacy:metadata:crossduel_game:lastmovetime'] = 'The time when the most recent move was made.';
$string['privacy:metadata:crossduel_game:timecreated'] = 'The time when the multiplayer game was created.';
$string['privacy:metadata:crossduel_game:timemodified'] = 'The time when the multiplayer game was last modified.';

$string['privacy:metadata:crossduel_move'] = 'Stores a learner\'s multiplayer moves in Cross Duel.';
$string['privacy:metadata:crossduel_move:userid'] = 'The user ID of the learner who made the move.';
$string['privacy:metadata:crossduel_move:wordid'] = 'The puzzle word attempted in this move.';
$string['privacy:metadata:crossduel_move:direction'] = 'The direction of the word attempted in this move.';
$string['privacy:metadata:crossduel_move:submittedanswer'] = 'The answer submitted by the learner.';
$string['privacy:metadata:crossduel_move:correct'] = 'Whether the submitted answer was correct.';
$string['privacy:metadata:crossduel_move:pointsawarded'] = 'The points awarded for the move.';
$string['privacy:metadata:crossduel_move:movesummary'] = 'A readable summary of the move.';
$string['privacy:metadata:crossduel_move:timecreated'] = 'The time when the move was created.';

$string['privacy:metadata:crossduel_presence'] = 'Stores recent activity presence for a learner inside a specific Cross Duel activity.';
$string['privacy:metadata:crossduel_presence:userid'] = 'The user ID of the learner currently present in the activity.';
$string['privacy:metadata:crossduel_presence:lastseen'] = 'The most recent time the learner was seen in this Cross Duel activity.';

// -------------------------------------------------------------
// View.php UI strings
// -------------------------------------------------------------

$string['layoutnotready'] = 'This activity has been created, but the crossword layout has not yet been previewed and approved by the teacher.';
$string['openpreview'] = 'Open preview page';
$string['layoutmissingrows'] = 'This activity says the layout is approved, but no stored layout rows were found.';
$string['layoutreapprove'] = 'The teacher may need to return to the preview page and approve the draft again.';

$string['boardtitle'] = 'Cross Duel board';
$string['subtitle_single'] = 'You can solve this puzzle one clue at a time, or invite another learner to start a multiplayer Cross Duel session.';
$string['subtitle_active'] = 'You are now in a multiplayer Cross Duel session. Refresh the page to see your partner\'s latest move.';
$string['subtitle_completed'] = 'This multiplayer Cross Duel session has been completed successfully. The final shared board remains visible below.';

$string['multiplayer_title'] = 'Play with another learner';
$string['invitation_sent'] = 'Invitation sent to {$a}.';
$string['waiting_response'] = 'Waiting for response.';
$string['singleplayer_available'] = 'Single-player answering remains available until the invitation is accepted.';
$string['invited_you'] = '{$a} has invited you to play Cross Duel.';
$string['accept_notice'] = 'Accepting will activate multiplayer lock mode for both players.';
$string['accept'] = 'Accept';
$string['decline'] = 'Decline';

$string['already_active'] = 'You are now in a multiplayer Cross Duel session.';
$string['opponent'] = 'Opponent: {$a}';
$string['yourrole'] = 'Your role: {$a}';
$string['currentturn'] = 'Current turn: {$a}';
$string['singleplayer_locked'] = 'Single-player answering is locked while this multiplayer session is active.';
$string['refresh_notice'] = 'Refresh the page to see your partner\'s latest move.';

$string['completed_title'] = 'Multiplayer Cross Duel completed ✓';
$string['completed_credit'] = 'The shared puzzle has been completed. Equal full credit has been awarded to both players.';
$string['completed_board'] = 'The final board remains visible below as the completed multiplayer result.';

$string['invites_waiting'] = 'You have invitation(s) waiting:';
$string['invite_prompt'] = '{$a} wants to play Cross Duel with you.';
$string['invite_start'] = 'Accepting will start the multiplayer session.';

$string['available_learners'] = 'Available learners are currently in this Cross Duel activity, have not yet passed it, and are not already busy in another Cross Duel game.';
$string['no_learners'] = 'No learners are currently available to invite.';
$string['invite'] = 'Invite';

$string['puzzle_complete'] = 'Puzzle completed ✓';
$string['puzzle_done'] = 'Well done. You have solved all the clues in this Cross Duel activity.';

$string['multiplayer_done'] = 'Well done. Both players have successfully completed this assignment.';
$string['multiplayer_done_note'] = 'The completed shared board remains visible below so that both players can see the final result together.';

$string['clues'] = 'Clues';
$string['across'] = 'Across';
$string['down'] = 'Down';
$string['no_across'] = 'No Across clues in this approved layout.';
$string['no_down'] = 'No Down clues in this approved layout.';

$string['answer_length'] = 'Answer length: {$a}';
$string['status'] = 'Status: {$a}';
$string['solved'] = 'Solved';
$string['unsolved'] = 'Unsolved';

$string['error_notfound'] = 'The selected clue could not be found in this approved layout.';
$string['error_emptyanswer'] = 'Please type an answer before submitting.';
$string['correct_answer'] = 'Correct. That word is now revealed on your board and your grade has been updated.';
$string['incorrect_answer'] = 'That answer is not correct yet. Please try again.';

$string['mp_no_active'] = 'There is no active multiplayer game for this activity.';
$string['mp_not_turn'] = 'It is not your turn.';
$string['mp_role_error'] = 'Your multiplayer role is not assigned correctly.';
$string['mp_clue_missing'] = 'The selected multiplayer clue could not be found.';
$string['mp_wrong_direction'] = 'You may only answer clues from your own multiplayer direction.';
$string['mp_already_solved'] = 'That multiplayer clue has already been solved.';
$string['mp_empty'] = 'Please type an answer before submitting.';
$string['mp_correct'] = 'Correct multiplayer answer submitted. Refresh the other browser to see the shared update.';
$string['mp_incorrect'] = 'That multiplayer answer is not correct. Turn has passed to the other player.';

$string['presence_lastseen'] = 'In this activity now · Last seen: {$a}';

$string['err_active_or_pending'] = 'You already have an active or pending multiplayer game in this activity.';
$string['err_learner_not_available'] = 'That learner is no longer available for this activity.';
$string['invitation_sent_success'] = 'Invitation sent successfully.';
$string['err_invitation_unavailable'] = 'This invitation is no longer available.';
$string['mp_already_active'] = 'This multiplayer game is already active.';
$string['err_another_active_or_pending'] = 'You already have another active or pending multiplayer game in this activity.';
$string['invitation_accepted_active'] = 'Invitation accepted. The multiplayer game is now active.';
$string['invitation_already_declined'] = 'This invitation was already declined.';
$string['invitation_declined'] = 'Invitation declined.';

$string['unknownlearner'] = 'Unknown learner';
$string['anotherlearner'] = 'Another learner';
$string['singleplayer_available_until_accept'] = 'Single-player answering remains available until you accept the invitation.';
$string['finalsharedstatus_completed'] = 'Final shared status: Completed';

$string['yourpuzzleboard'] = 'Your puzzle board';
$string['nogridreconstructed'] = 'No grid could be reconstructed from the saved layout.';
$string['boardnote_multiplayer'] = 'Numbered cells mark the start of clues. Blue-tinted cells are prefilled clue letters. The board is visible in read-only multiplayer mode and updates when you refresh.';
$string['boardnote_single'] = 'Numbered cells mark the start of clues. Blue-tinted cells are prefilled clue letters. Hidden cells will become visible when you solve a word.';

$string['singleplayerpanel'] = 'Single-player action panel';
$string['solvedwordsprogress'] = 'Solved words: {$a->solved} of {$a->total}.';
$string['chooseclue'] = 'Choose clue';
$string['youranswer'] = 'Your answer';
$string['submitanswer'] = 'Submit answer';

$string['multiplayerpanel'] = 'Multiplayer action panel';
$string['yourmultiplayerrole'] = 'Your multiplayer role: {$a}.';
$string['mp_turn_yes'] = 'It is your turn. You may answer one clue from your own direction.';
$string['mp_turn_no'] = 'It is not your turn yet. Refresh after the other player moves.';
$string['mp_all_solved'] = 'All multiplayer clues are solved.';
$string['mp_none_in_direction'] = 'No unsolved clues remain in your direction.';
$string['mp_remaining_other'] = 'The remaining clues belong to the other player. Refresh after they move.';
$string['mp_refresh_your_turn'] = 'Refresh this page when it becomes your turn.';
$string['chooseyourclue'] = 'Choose your clue';
$string['submitmultiplayeranswer'] = 'Submit multiplayer answer';
$string['multiplayercompleted'] = 'Multiplayer completed';
$string['mp_no_further_answers'] = 'No further answers are needed. This shared puzzle has been completed successfully.';
$string['mp_refresh_confirm'] = 'You may refresh to confirm the final shared board and grade state, but the activity will remain in multiplayer completion view rather than dropping back to solo mode.';

$string['answerlengthstatus'] = 'Answer length: {$a->length} | Status: {$a->status}';
