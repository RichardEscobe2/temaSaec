<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * "Control de Asistencia" Course Selection Hub — Sprint 9.
 *
 * A standalone theme_saec page, not a core/plugin override: mod_attendance
 * has no cross-course landing page of its own (its only course-scoped page,
 * mod/attendance/index.php, lists one course's activities with no metrics),
 * so there is no native page to overlay via layout/drawers.php the way
 * every other Sprint this project built on. This is the first such
 * standalone page in theme_saec — everything it renders (header, sidebar,
 * footer) still goes through the theme's own layout/drawers.php via the
 * standard 'standard' pagelayout, so navigation stays identical to every
 * other page.
 *
 * @package   theme_saec
 * @copyright 2026 SAEC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use theme_saec\dashboard\attendance_hub_page;
use theme_saec\dashboard\teacher_dashboard;

require_login(null, false);

if (isguestuser()) {
    redirect(new moodle_url('/my/'));
}

$PAGE->set_url(new moodle_url('/theme/saec/pages/attendance_hub.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('attendancehubtitle', 'theme_saec'));
$PAGE->set_heading(get_string('attendancehubtitle', 'theme_saec'));

if (!teacher_dashboard::is_teacher()) {
    // Only ever linked from the teacher-only sidebar item — a non-teacher
    // reaching this URL directly gets sent back to their own dashboard
    // rather than an empty/broken page.
    redirect(new moodle_url('/my/'));
}

$context = attendance_hub_page::get_context();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_saec/attendance_hub', $context ?? ['hasactivities' => false, 'activities' => []]);
echo $OUTPUT->footer();
