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
