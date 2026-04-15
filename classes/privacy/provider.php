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
 * Privacy provider for mod_crossduel.
 *
 * @package    mod_crossduel
 * @author     Johan Venter <johan@myfutureway.co.za>
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_crossduel\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for Cross Duel.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the personal data stored by Cross Duel.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('crossduel_attempt', [
            'userid' => 'privacy:metadata:crossduel_attempt:userid',
            'status' => 'privacy:metadata:crossduel_attempt:status',
            'timecreated' => 'privacy:metadata:crossduel_attempt:timecreated',
            'timemodified' => 'privacy:metadata:crossduel_attempt:timemodified',
        ], 'privacy:metadata:crossduel_attempt');

        $collection->add_database_table('crossduel_attempt_word', [
            'attemptid' => 'privacy:metadata:crossduel_attempt_word:attemptid',
            'wordid' => 'privacy:metadata:crossduel_attempt_word:wordid',
            'issolved' => 'privacy:metadata:crossduel_attempt_word:issolved',
            'useranswer' => 'privacy:metadata:crossduel_attempt_word:useranswer',
            'timeanswered' => 'privacy:metadata:crossduel_attempt_word:timeanswered',
        ], 'privacy:metadata:crossduel_attempt_word');

        $collection->add_database_table('crossduel_game', [
            'playera' => 'privacy:metadata:crossduel_game:playera',
            'playerb' => 'privacy:metadata:crossduel_game:playerb',
            'horizontalplayer' => 'privacy:metadata:crossduel_game:horizontalplayer',
            'verticalplayer' => 'privacy:metadata:crossduel_game:verticalplayer',
            'currentturn' => 'privacy:metadata:crossduel_game:currentturn',
            'status' => 'privacy:metadata:crossduel_game:status',
            'lastmove' => 'privacy:metadata:crossduel_game:lastmove',
            'lastplayer' => 'privacy:metadata:crossduel_game:lastplayer',
            'lastmovetime' => 'privacy:metadata:crossduel_game:lastmovetime',
            'timecreated' => 'privacy:metadata:crossduel_game:timecreated',
            'timemodified' => 'privacy:metadata:crossduel_game:timemodified',
        ], 'privacy:metadata:crossduel_game');

        $collection->add_database_table('crossduel_move', [
            'userid' => 'privacy:metadata:crossduel_move:userid',
            'wordid' => 'privacy:metadata:crossduel_move:wordid',
            'direction' => 'privacy:metadata:crossduel_move:direction',
            'submittedanswer' => 'privacy:metadata:crossduel_move:submittedanswer',
            'correct' => 'privacy:metadata:crossduel_move:correct',
            'pointsawarded' => 'privacy:metadata:crossduel_move:pointsawarded',
            'movesummary' => 'privacy:metadata:crossduel_move:movesummary',
            'timecreated' => 'privacy:metadata:crossduel_move:timecreated',
        ], 'privacy:metadata:crossduel_move');

        $collection->add_database_table('crossduel_presence', [
            'userid' => 'privacy:metadata:crossduel_presence:userid',
            'lastseen' => 'privacy:metadata:crossduel_presence:lastseen',
        ], 'privacy:metadata:crossduel_presence');

        return $collection;
    }

    /**
     * Get contexts containing data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "
            SELECT DISTINCT ctx.id
              FROM {context} ctx
              JOIN {course_modules} cm
                ON cm.id = ctx.instanceid
              JOIN {modules} m
                ON m.id = cm.module
               AND m.name = :modname
              LEFT JOIN {crossduel_attempt} a
                ON a.crossduelid = cm.instance
               AND a.userid = :userid1
              LEFT JOIN {crossduel_presence} p
                ON p.crossduelid = cm.instance
               AND p.userid = :userid2
              LEFT JOIN {crossduel_game} g
                ON g.crossduelid = cm.instance
               AND (g.playera = :userid3 OR g.playerb = :userid4)
              LEFT JOIN {crossduel_move} mv
                ON mv.gameid = g.id
               AND mv.userid = :userid5
             WHERE ctx.contextlevel = :contextlevel
               AND (
                    a.id IS NOT NULL OR
                    p.id IS NOT NULL OR
                    g.id IS NOT NULL OR
                    mv.id IS NOT NULL
               )
        ";

        $params = [
            'modname' => 'crossduel',
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
            'userid5' => $userid,
            'contextlevel' => CONTEXT_MODULE,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $contexts = [];
        foreach ($contextlist as $context) {
            if ($context instanceof context_module) {
                $contexts[$context->id] = $context;
            }
        }

        if (empty($contexts)) {
            return;
        }

        $contexttocrossduel = self::get_crossduelids_from_contexts($contexts);
        if (empty($contexttocrossduel)) {
            return;
        }

        $crossduelids = array_values(array_unique($contexttocrossduel));
        list($insql, $params) = $DB->get_in_or_equal($crossduelids, SQL_PARAMS_NAMED);

        $attempts = $DB->get_records_select(
            'crossduel_attempt',
            "crossduelid $insql AND userid = :userid",
            $params + ['userid' => $userid],
            'id ASC'
        );
        $attemptids = array_keys($attempts);

        $attemptwords = [];
        if ($attemptids) {
            list($ainsql, $aparams) = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED);
            $attemptwords = $DB->get_records_select(
                'crossduel_attempt_word',
                "attemptid $ainsql",
                $aparams,
                'id ASC'
            );
        }

        $attemptwordmap = [];
        foreach ($attemptwords as $word) {
            $attemptwordmap[$word->attemptid][] = $word;
        }

        $games = $DB->get_records_select(
            'crossduel_game',
            "crossduelid $insql AND (playera = :userid1 OR playerb = :userid2)",
            $params + ['userid1' => $userid, 'userid2' => $userid],
            'id ASC'
        );
        $gameids = array_keys($games);

        $moves = [];
        if ($gameids) {
            list($ginsql, $gparams) = $DB->get_in_or_equal($gameids, SQL_PARAMS_NAMED);
            $moves = $DB->get_records_select(
                'crossduel_move',
                "gameid $ginsql AND userid = :userid",
                $gparams + ['userid' => $userid],
                'id ASC'
            );
        }

        $movemap = [];
        foreach ($moves as $move) {
            $movemap[$move->gameid][] = $move;
        }

        $presence = $DB->get_records_select(
            'crossduel_presence',
            "crossduelid $insql AND userid = :userid",
            $params + ['userid' => $userid]
        );
        $presencemap = [];
        foreach ($presence as $record) {
            $presencemap[$record->crossduelid] = $record;
        }

        foreach ($contexts as $contextid => $context) {
            if (empty($contexttocrossduel[$contextid])) {
                continue;
            }

            $crossduelid = $contexttocrossduel[$contextid];

            $attemptexport = [];
            foreach ($attempts as $attempt) {
                if ((int)$attempt->crossduelid !== (int)$crossduelid) {
                    continue;
                }

                $attemptitem = [
                    'status' => $attempt->status,
                    'timecreated' => transform::datetime($attempt->timecreated),
                    'timemodified' => transform::datetime($attempt->timemodified),
                    'words' => [],
                ];

                if (!empty($attemptwordmap[$attempt->id])) {
                    foreach ($attemptwordmap[$attempt->id] as $word) {
                        $attemptitem['words'][] = [
                            'wordid' => (int)$word->wordid,
                            'issolved' => (int)$word->issolved,
                            'useranswer' => $word->useranswer,
                            'timeanswered' => transform::datetime($word->timeanswered),
                        ];
                    }
                }

                $attemptexport[] = $attemptitem;
            }

            $gameexport = [];
            foreach ($games as $game) {
                if ((int)$game->crossduelid !== (int)$crossduelid) {
                    continue;
                }

                $role = '';
                if ((int)$game->playera === (int)$userid) {
                    $role = 'playera';
                } else if ((int)$game->playerb === (int)$userid) {
                    $role = 'playerb';
                }

                $gameitem = [
                    'gameid' => (int)$game->id,
                    'yourrole' => $role,
                    'status' => $game->status,
                    'timecreated' => transform::datetime($game->timecreated),
                    'timemodified' => transform::datetime($game->timemodified),
                    'lastmove' => $game->lastmove,
                    'lastmovetime' => transform::datetime($game->lastmovetime),
                    'movesmadebyyou' => [],
                ];

                if (!empty($movemap[$game->id])) {
                    foreach ($movemap[$game->id] as $move) {
                        $gameitem['movesmadebyyou'][] = [
                            'wordid' => (int)$move->wordid,
                            'direction' => $move->direction,
                            'submittedanswer' => $move->submittedanswer,
                            'correct' => (int)$move->correct,
                            'pointsawarded' => (float)$move->pointsawarded,
                            'movesummary' => $move->movesummary,
                            'timecreated' => transform::datetime($move->timecreated),
                        ];
                    }
                }

                $gameexport[] = $gameitem;
            }

            $presenceexport = null;
            if (!empty($presencemap[$crossduelid])) {
                $presenceexport = [
                    'lastseen' => transform::datetime($presencemap[$crossduelid]->lastseen),
                ];
            }

            $data = (object) [
                'attempts' => $attemptexport,
                'games' => $gameexport,
                'presence' => $presenceexport,
            ];

            writer::with_context($context)->export_data([], $data);
        }
    }

    /**
     * Delete all user data for all users in a context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        $crossduelid = self::get_crossduelid_from_context($context);
        if (!$crossduelid) {
            return;
        }

        $attempts = $DB->get_records('crossduel_attempt', ['crossduelid' => $crossduelid]);
        if ($attempts) {
            $attemptids = array_keys($attempts);
            list($insql, $params) = $DB->get_in_or_equal($attemptids);
            $DB->delete_records_select('crossduel_attempt_word', "attemptid $insql", $params);
        }

        $games = $DB->get_records('crossduel_game', ['crossduelid' => $crossduelid]);
        if ($games) {
            $gameids = array_keys($games);
            list($insql, $params) = $DB->get_in_or_equal($gameids);
            $DB->delete_records_select('crossduel_move', "gameid $insql", $params);
        }

        $DB->delete_records('crossduel_presence', ['crossduelid' => $crossduelid]);
        $DB->delete_records('crossduel_attempt', ['crossduelid' => $crossduelid]);
        $DB->delete_records('crossduel_game', ['crossduelid' => $crossduelid]);
    }

    /**
     * Delete user data for approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;

        $contexts = [];
        foreach ($contextlist as $context) {
            if ($context instanceof context_module) {
                $contexts[$context->id] = $context;
            }
        }

        if (empty($contexts)) {
            return;
        }

        $contexttocrossduel = self::get_crossduelids_from_contexts($contexts);
        foreach ($contexttocrossduel as $crossduelid) {
            self::delete_user_data_in_activity((int)$crossduelid, (int)$userid);
        }
    }

    /**
     * Get the users who have data in the specified context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        $crossduelid = self::get_crossduelid_from_context($context);
        if (!$crossduelid) {
            return;
        }

        $userlist->add_from_sql(
            'userid',
            "SELECT DISTINCT userid
               FROM {crossduel_attempt}
              WHERE crossduelid = :crossduelid1",
            ['crossduelid1' => $crossduelid]
        );

        $userlist->add_from_sql(
            'userid',
            "SELECT DISTINCT userid
               FROM {crossduel_presence}
              WHERE crossduelid = :crossduelid2",
            ['crossduelid2' => $crossduelid]
        );

        $userlist->add_from_sql(
            'userid',
            "SELECT DISTINCT mv.userid
               FROM {crossduel_move} mv
               JOIN {crossduel_game} g
                 ON g.id = mv.gameid
              WHERE g.crossduelid = :crossduelid3",
            ['crossduelid3' => $crossduelid]
        );

        $userlist->add_from_sql(
            'playera',
            "SELECT DISTINCT playera
               FROM {crossduel_game}
              WHERE crossduelid = :crossduelid4
                AND playera > 0",
            ['crossduelid4' => $crossduelid]
        );

        $userlist->add_from_sql(
            'playerb',
            "SELECT DISTINCT playerb
               FROM {crossduel_game}
              WHERE crossduelid = :crossduelid5
                AND playerb > 0",
            ['crossduelid5' => $crossduelid]
        );
    }

    /**
     * Delete user data for multiple users in one context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        $crossduelid = self::get_crossduelid_from_context($context);
        if (!$crossduelid) {
            return;
        }

        $userids = array_map('intval', $userlist->get_userids());
        if (empty($userids)) {
            return;
        }

        list($uinsql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $attempts = $DB->get_records_select(
            'crossduel_attempt',
            "crossduelid = :crossduelid AND userid $uinsql",
            ['crossduelid' => $crossduelid] + $uparams
        );

        if ($attempts) {
            $attemptids = array_keys($attempts);
            list($ainsql, $aparams) = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('crossduel_attempt_word', "attemptid $ainsql", $aparams);
        }

        $DB->delete_records_select(
            'crossduel_attempt',
            "crossduelid = :crossduelid AND userid $uinsql",
            ['crossduelid' => $crossduelid] + $uparams
        );

        $games = $DB->get_records_select(
            'crossduel_game',
            "crossduelid = :crossduelid AND (playera $uinsql OR playerb $uinsql)",
            ['crossduelid' => $crossduelid] + $uparams
        );

        if ($games) {
            $gameids = array_keys($games);
            list($ginsql, $gparams) = $DB->get_in_or_equal($gameids, SQL_PARAMS_NAMED);
            list($muinsql, $muparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

            $DB->delete_records_select(
                'crossduel_move',
                "gameid $ginsql AND userid $muinsql",
                $gparams + $muparams
            );

            foreach ($games as $game) {
                if (in_array((int)$game->playera, $userids, true)) {
                    $game->playera = 0;
                }
                if (in_array((int)$game->playerb, $userids, true)) {
                    $game->playerb = 0;
                }
                if (in_array((int)$game->horizontalplayer, $userids, true)) {
                    $game->horizontalplayer = 0;
                }
                if (in_array((int)$game->verticalplayer, $userids, true)) {
                    $game->verticalplayer = 0;
                }
                if (in_array((int)$game->currentturn, $userids, true)) {
                    $game->currentturn = 0;
                }
                if (in_array((int)$game->lastplayer, $userids, true)) {
                    $game->lastplayer = 0;
                }

                $game->timemodified = time();
                $DB->update_record('crossduel_game', $game);
            }
        }

        $DB->delete_records_select(
            'crossduel_presence',
            "crossduelid = :crossduelid AND userid $uinsql",
            ['crossduelid' => $crossduelid] + $uparams
        );
    }

    /**
     * Delete one user's data inside one Cross Duel activity.
     *
     * Shared game rows are anonymised rather than deleted.
     *
     * @param int $crossduelid
     * @param int $userid
     * @return void
     */
    protected static function delete_user_data_in_activity(int $crossduelid, int $userid): void {
        global $DB;

        $attempts = $DB->get_records('crossduel_attempt', [
            'crossduelid' => $crossduelid,
            'userid' => $userid,
        ]);

        if ($attempts) {
            $attemptids = array_keys($attempts);
            list($insql, $params) = $DB->get_in_or_equal($attemptids);
            $DB->delete_records_select('crossduel_attempt_word', "attemptid $insql", $params);
        }

        $DB->delete_records('crossduel_attempt', [
            'crossduelid' => $crossduelid,
            'userid' => $userid,
        ]);

        $games = $DB->get_records_select(
            'crossduel_game',
            'crossduelid = :crossduelid AND (playera = :userid1 OR playerb = :userid2)',
            [
                'crossduelid' => $crossduelid,
                'userid1' => $userid,
                'userid2' => $userid,
            ]
        );

        if ($games) {
            $gameids = array_keys($games);
            list($insql, $params) = $DB->get_in_or_equal($gameids);
            $params['userid'] = $userid;
            $DB->delete_records_select('crossduel_move', "gameid $insql AND userid = :userid", $params);

            foreach ($games as $game) {
                if ((int)$game->playera === $userid) {
                    $game->playera = 0;
                }
                if ((int)$game->playerb === $userid) {
                    $game->playerb = 0;
                }
                if ((int)$game->horizontalplayer === $userid) {
                    $game->horizontalplayer = 0;
                }
                if ((int)$game->verticalplayer === $userid) {
                    $game->verticalplayer = 0;
                }
                if ((int)$game->currentturn === $userid) {
                    $game->currentturn = 0;
                }
                if ((int)$game->lastplayer === $userid) {
                    $game->lastplayer = 0;
                }

                $game->timemodified = time();
                $DB->update_record('crossduel_game', $game);
            }
        }

        $DB->delete_records('crossduel_presence', [
            'crossduelid' => $crossduelid,
            'userid' => $userid,
        ]);
    }

    /**
     * Resolve Cross Duel instance ids for multiple module contexts.
     *
     * @param array $contexts
     * @return array
     */
    protected static function get_crossduelids_from_contexts(array $contexts): array {
        global $DB;

        $contexttocrossduel = [];
        $cmids = [];

        foreach ($contexts as $contextid => $context) {
            if ($context instanceof context_module) {
                $cmids[$contextid] = (int)$context->instanceid;
            }
        }

        if (empty($cmids)) {
            return $contexttocrossduel;
        }

        list($insql, $params) = $DB->get_in_or_equal(array_values($cmids), SQL_PARAMS_NAMED);

        $sql = "
            SELECT cm.id AS cmid, cm.instance
              FROM {course_modules} cm
              JOIN {modules} m
                ON m.id = cm.module
             WHERE cm.id $insql
               AND m.name = :modname
        ";

        $records = $DB->get_records_sql($sql, $params + ['modname' => 'crossduel']);

        $cmidtoinstance = [];
        foreach ($records as $record) {
            $cmidtoinstance[(int)$record->cmid] = (int)$record->instance;
        }

        foreach ($cmids as $contextid => $cmid) {
            if (isset($cmidtoinstance[$cmid])) {
                $contexttocrossduel[$contextid] = $cmidtoinstance[$cmid];
            }
        }

        return $contexttocrossduel;
    }

    /**
     * Resolve the Cross Duel instance id from a module context.
     *
     * @param context $context
     * @return int
     */
    protected static function get_crossduelid_from_context(context $context): int {
        global $DB;

        if (!$context instanceof context_module) {
            return 0;
        }

        $sql = "
            SELECT cm.instance
              FROM {course_modules} cm
              JOIN {modules} m
                ON m.id = cm.module
             WHERE cm.id = :cmid
               AND m.name = :modname
        ";

        $record = $DB->get_record_sql($sql, [
            'cmid' => $context->instanceid,
            'modname' => 'crossduel',
        ], IGNORE_MISSING);

        if (!$record) {
            return 0;
        }

        return (int)$record->instance;
    }
}
