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
 * Backup structure step for mod_crossduel.
 *
 * This backs up only the authored puzzle definition:
 * - main activity settings
 * - parsed words
 * - approved layout slots
 *
 * Runtime/user data is intentionally excluded from version 1.
 *
 * @package    mod_crossduel
 * @copyright  Your name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to back up one Cross Duel activity.
 */
class backup_crossduel_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure of the backup data for this activity.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        // No userinfo is included in this first implementation.

        // Main activity element.
        $crossduel = new backup_nested_element('crossduel', ['id'], [
            'name',
            'intro',
            'introformat',
            'wordlist',
            'revealpercent',
            'passpercentage',
            'grade',
            'layoutapproved',
            'timecreated',
            'timemodified',
        ]);

        // Teacher-authored parsed words.
        $words = new backup_nested_element('words');
        $word = new backup_nested_element('word', ['id'], [
            'rawword',
            'normalizedword',
            'cluetext',
            'wordlength',
            'sortorder',
            'enabled',
        ]);

        // Approved puzzle layout.
        $layoutslots = new backup_nested_element('layoutslots');
        $layoutslot = new backup_nested_element('layoutslot', ['id'], [
            'wordid',
            'direction',
            'startrow',
            'startcol',
            'cluenumber',
            'placementorder',
            'isactive',
        ]);

        // Build the tree.
        $crossduel->add_child($words);
        $words->add_child($word);

        $crossduel->add_child($layoutslots);
        $layoutslots->add_child($layoutslot);

        // Data sources.
        $crossduel->set_source_table('crossduel', ['id' => backup::VAR_ACTIVITYID]);
        $word->set_source_table('crossduel_word', ['crossduelid' => backup::VAR_PARENTID]);
        $layoutslot->set_source_table('crossduel_layoutslot', ['crossduelid' => backup::VAR_PARENTID]);

        // Related files for the intro editor.
        $crossduel->annotate_files('mod_crossduel', 'intro', null);

        return $this->prepare_activity_structure($crossduel);
    }
}
