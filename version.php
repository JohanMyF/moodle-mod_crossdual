<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle uses version.php to identify a plugin, check compatibility,
// and decide whether an install or upgrade is required.

/**
 * Version information for the Cross Duel activity module.
 *
 * This plugin is a custom Moodle activity module.
 * Its full component name must match the folder location:
 *
 *   /mod/crossduel
 *
 * Therefore the component name below must be:
 *
 *   mod_crossduel
 *
 * @package    mod_crossduel
 * @copyright  Your name
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
$plugin->version = 2026040106;

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