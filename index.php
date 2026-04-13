<?php
// This file is part of Moodle - http://moodle.org/
//
// Course-level listing page for all Cross Duel instances in a course.

/**
 * Displays a list of all Cross Duel instances in a given course.
 *
 * This is a standard Moodle activity-module page.
 *
 * For now, this file is deliberately simple and safe.
 *
 * @package    mod_crossduel
 * @copyright  Your name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course id.

$course = get_course($id);
require_login($course);

$PAGE->set_url('/mod/crossduel/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('modulenameplural', 'crossduel'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('modulenameplural', 'crossduel'));

if (!$crossduels = get_all_instances_in_course('crossduel', $course)) {
    notice(get_string('nonewmodules', 'crossduel'), new moodle_url('/course/view.php', ['id' => $course->id]));
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

$table->head = [
    get_string('name'),
];

$table->data = [];

foreach ($crossduels as $crossduel) {
    $link = html_writer::link(
        new moodle_url('/mod/crossduel/view.php', ['id' => $crossduel->coursemodule]),
        format_string($crossduel->name)
    );

    $table->data[] = [$link];
}

echo html_writer::table($table);

echo $OUTPUT->footer();