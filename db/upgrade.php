<?php
// This file is part of Moodle - http://moodle.org/
//
// Upgrade steps for the Cross Duel activity module.

/**
 * Upgrade code for mod_crossduel.
 *
 * IMPORTANT:
 * - install.xml is used for fresh installs
 * - upgrade.php is used for schema changes on already-installed plugins
 *
 * In this upgrade file we currently add:
 * - crossduel_attempt
 * - crossduel_attempt_word
 * - crossduel_presence
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_crossduel_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026033002) {

        /*
         * ---------------------------------------------------------
         * Table: crossduel_attempt
         * ---------------------------------------------------------
         */
        $table = new xmldb_table('crossduel_attempt');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('crossduelid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'inprogress');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('crossduelid_idx', XMLDB_INDEX_NOTUNIQUE, ['crossduelid']);
        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('crossduel_user_idx', XMLDB_INDEX_UNIQUE, ['crossduelid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        /*
         * ---------------------------------------------------------
         * Table: crossduel_attempt_word
         * ---------------------------------------------------------
         */
        $table = new xmldb_table('crossduel_attempt_word');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('wordid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('issolved', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('useranswer', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timeanswered', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('attemptid_idx', XMLDB_INDEX_NOTUNIQUE, ['attemptid']);
        $table->add_index('wordid_idx', XMLDB_INDEX_NOTUNIQUE, ['wordid']);
        $table->add_index('attempt_word_idx', XMLDB_INDEX_UNIQUE, ['attemptid', 'wordid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        /*
         * Savepoint
         */
        upgrade_mod_savepoint(true, 2026033002, 'crossduel');
    }

    if ($oldversion < 2026040101) {

        /*
         * ---------------------------------------------------------
         * Table: crossduel_presence
         * ---------------------------------------------------------
         */
        $table = new xmldb_table('crossduel_presence');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('crossduelid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastseen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('crossduelid_idx', XMLDB_INDEX_NOTUNIQUE, ['crossduelid']);
        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('lastseen_idx', XMLDB_INDEX_NOTUNIQUE, ['lastseen']);
        $table->add_index('crossduel_user_uix', XMLDB_INDEX_UNIQUE, ['crossduelid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        /*
         * Savepoint
         */
        upgrade_mod_savepoint(true, 2026040101, 'crossduel');
    }

    if ($oldversion < 2026040106) {

        /*
         * ---------------------------------------------------------
         * SAFETY STEP: ensure crossduel_presence exists even if a
         * previous version bump skipped the original 2026040101 block.
         * ---------------------------------------------------------
         */
        $table = new xmldb_table('crossduel_presence');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('crossduelid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastseen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('crossduelid_idx', XMLDB_INDEX_NOTUNIQUE, ['crossduelid']);
        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('lastseen_idx', XMLDB_INDEX_NOTUNIQUE, ['lastseen']);
        $table->add_index('crossduel_user_uix', XMLDB_INDEX_UNIQUE, ['crossduelid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026040106, 'crossduel');
    }

    return true;
}
