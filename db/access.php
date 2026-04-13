<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle capability definitions for the Cross Duel activity module.

/**
 * Capability definitions for mod_crossduel.
 *
 * This file tells Moodle what permissions exist for this plugin.
 * Later, Moodle roles such as Teacher, Student, Manager, etc. can
 * be granted or denied these capabilities in the normal Moodle way.
 *
 * For version 1, we keep this simple and define only the capabilities
 * we really need.
 *
 * @package    mod_crossduel
 * @copyright  Your name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    /*
     * -------------------------------------------------------------
     * Standard capability: add a new Cross Duel activity instance
     * -------------------------------------------------------------
     *
     * Why this exists:
     * Teachers or other editing users need permission to add this
     * activity to a course.
     *
     * Why the riskbitmask is RISK_XSS:
     * Activity creation usually involves user-authored content such as
     * names, instructions, and teacher-entered text. Moodle commonly
     * marks addinstance with this risk type.
     */
    'mod/crossduel:addinstance' => [
        'riskbitmask' => RISK_XSS,

        'captype' => 'write',

        'contextlevel' => CONTEXT_COURSE,

        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],

        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ],

    /*
     * -------------------------------------------------------------
     * Custom capability: play/view the Cross Duel activity
     * -------------------------------------------------------------
     *
     * Why this exists:
     * We want a clear plugin-specific capability we can check in
     * view.php later.
     *
     * Students should normally have this permission, as should teachers
     * and managers.
     */
    'mod/crossduel:play' => [
        'captype' => 'read',

        'contextlevel' => CONTEXT_MODULE,

        'archetypes' => [
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];