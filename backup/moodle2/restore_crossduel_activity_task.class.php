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
 * Restore task for mod_crossduel.
 *
 * This restores only the authored activity structure and deliberately leaves
 * out runtime/user/session data.
 *
 * @package    mod_crossduel
 * @copyright  Your name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/crossduel/backup/moodle2/restore_crossduel_stepslib.php');

/**
 * Defines the restore task for Cross Duel.
 */
class restore_crossduel_activity_task extends restore_activity_task {

    /**
     * Define any particular settings for this restore task.
     *
     * @return void
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define the restore steps for this activity.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_crossduel_activity_structure_step(
            'crossduel_structure',
            'crossduel.xml'
        ));
    }

    /**
     * Define the contents in this activity that must have links decoded.
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('crossduel', ['intro'], 'crossduel');
        return $contents;
    }

    /**
     * Define the rules used to decode links back into restored URLs.
     *
     * @return array
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule(
            'CROSSDUELINDEX',
            '/mod/crossduel/index.php?id=$1',
            'course'
        );

        $rules[] = new restore_decode_rule(
            'CROSSDUELVIEWBYID',
            '/mod/crossduel/view.php?id=$1',
            'course_module'
        );

        return $rules;
    }

    /**
     * Define restore log rules.
     *
     * We return an empty array for now.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Define course-level restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }
}
