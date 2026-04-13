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
 * Backup task for mod_crossduel.
 *
 * This version intentionally backs up only the authored activity structure:
 * - crossduel
 * - crossduel_word
 * - crossduel_layoutslot
 *
 * It does NOT back up runtime or user/session data such as:
 * - crossduel_game
 * - crossduel_move
 * - crossduel_attempt
 * - crossduel_attempt_word
 *
 * That means a duplicated or restored activity starts as a fresh playable
 * instance, which is the safest first implementation.
 *
 * @package    mod_crossduel
 * @copyright  Your name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/crossduel/backup/moodle2/backup_crossduel_stepslib.php');

/**
 * Defines the backup task for Cross Duel.
 */
class backup_crossduel_activity_task extends backup_activity_task {

    /**
     * Define any particular settings for this activity.
     *
     * We do not need custom settings yet.
     *
     * @return void
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define the particular backup steps for this activity.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new backup_crossduel_activity_structure_step(
            'crossduel_structure',
            'crossduel.xml'
        ));
    }

    /**
     * Encode links to the activity so they can be correctly restored.
     *
     * @param string $content Some HTML/text content
     * @return string Encoded content
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Link to the activity index page in a course.
        $search = '/(' . $base . '\/mod\/crossduel\/index\.php\?id=)([0-9]+)/';
        $content = preg_replace($search, '$@CROSSDUELINDEX*$2@$', $content);

        // Link to a specific activity by course module id.
        $search = '/(' . $base . '\/mod\/crossduel\/view\.php\?id=)([0-9]+)/';
        $content = preg_replace($search, '$@CROSSDUELVIEWBYID*$2@$', $content);

        return $content;
    }
}
