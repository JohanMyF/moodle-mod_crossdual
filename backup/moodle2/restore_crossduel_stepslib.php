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
 * Restore structure step for mod_crossduel.
 *
 * This restores:
 * - crossduel
 * - crossduel_word
 * - crossduel_layoutslot
 *
 * Runtime/user/session tables are intentionally excluded.
 *
 * @package    mod_crossduel
 * @copyright  Your name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one Cross Duel activity.
 */
class restore_crossduel_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define the structure to be restored.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];

        $paths[] = new restore_path_element('crossduel', '/activity/crossduel');
        $paths[] = new restore_path_element('crossduel_word', '/activity/crossduel/words/word');
        $paths[] = new restore_path_element('crossduel_layoutslot', '/activity/crossduel/layoutslots/layoutslot');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the main activity record.
     *
     * @param array $data
     * @return void
     */
    protected function process_crossduel($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('crossduel', $data);

        // This tells Moodle the new activity instance id.
        $this->apply_activity_instance($newitemid);

        // Store mapping in case anything ever needs it later.
        $this->set_mapping('crossduel', $oldid, $newitemid, true);
    }

    /**
     * Process one restored teacher-authored word row.
     *
     * @param array $data
     * @return void
     */
    protected function process_crossduel_word($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->crossduelid = $this->get_new_parentid('crossduel');

        $newitemid = $DB->insert_record('crossduel_word', $data);
        $this->set_mapping('crossduel_word', $oldid, $newitemid, true);
    }

    /**
     * Process one restored approved layout slot.
     *
     * Important: wordid must be remapped to the restored crossduel_word record.
     *
     * @param array $data
     * @return void
     */
    protected function process_crossduel_layoutslot($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->crossduelid = $this->get_new_parentid('crossduel');
        $data->wordid = $this->get_mappingid('crossduel_word', $data->wordid);

        $newitemid = $DB->insert_record('crossduel_layoutslot', $data);
        $this->set_mapping('crossduel_layoutslot', $oldid, $newitemid);
    }

    /**
     * Restore any related files after all data has been inserted.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_crossduel', 'intro', null);
    }
}
