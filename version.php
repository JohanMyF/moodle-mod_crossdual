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
 * [Short description of what this file does]
 *
 * @package    mod_crossduel
 * @author     Johan Venter <johan@myfutureway.co.za>
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The global $plugin object is how Moodle reads plugin metadata.
 */
$plugin->component = 'mod_crossduel';

/**
 * Version number in YYYYMMDDXX format.
 *
 * Meaning of this example:
 * - 2026 03 29  = date
 * - 00          = first build on that date
 *
 * Every time we later make a database schema change or a code upgrade
 * that Moodle must notice, we will increase this number.
 */
$plugin->version = 2026040280;

/**
 * Minimum Moodle version required.
 *
 * We will keep this aligned with a modern Moodle build suitable for your site.
 * If needed later, we can adjust this to match your exact production version.
 */
$plugin->requires = 2023100900;

/**
 * Maturity level.
 *
 * MATURITY_ALPHA is appropriate while the plugin is still under development.
 * Later, when the plugin is stable, we can raise this.
 */
$plugin->maturity = MATURITY_ALPHA;

/**
 * Human-readable release label.
 */
$plugin->release = '0.1 alpha';