<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Student Tasks Hub ("Mis Tareas").
 *
 * A standalone theme_saec page, not a core/plugin override: mod_assign has
 * no cross-course landing page of its own — its own per-activity pages
 * only ever show one assignment at a time, and student_dashboard's
 * "Próximas Entregas" widget only surfaces upcoming due dates, not every
 * assignment's real submission/grading state. Same pattern as
 * pages/attendance_hub.php: header/sidebar/footer still go through the
 * theme's own layout/drawers.php via the standard 'standard' pagelayout,
 * so navigation stays identical to every other page.
 *
 * @package   theme_saec
 * @copyright 2026 SAEC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use theme_saec\dashboard\student_dashboard;
use theme_saec\dashboard\student_tasks_page;

require_login(null, false);

if (isguestuser()) {
    redirect(new moodle_url('/my/'));
}

// Only ever linked from the student-only sidebar item — a non-student
// reaching this URL directly gets sent back to their own dashboard rather
// than an empty/broken page.
if (!student_dashboard::is_student()) {
    redirect(new moodle_url('/my/'));
}

$PAGE->set_url(new moodle_url('/theme/saec/pages/student_tasks.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('studenttaskspagetitle', 'theme_saec'));
$PAGE->set_heading(get_string('studenttaskspagetitle', 'theme_saec'));

$context = student_tasks_page::get_context();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_saec/student_tasks_page', $context ?? ['hastasks' => false]);
echo $OUTPUT->footer();
