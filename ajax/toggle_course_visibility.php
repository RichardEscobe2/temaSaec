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
 * Course-visibility toggle for the teacher "Mis Cursos" grid
 * (templates/teacher_courses_page.mustache). POST-only, sesskey- and
 * capability-guarded — flips exactly one course's visible flag via core's
 * own course_change_visibility(), the same function the native course
 * management page uses, so no theme-specific course-state logic exists
 * here beyond wiring the request through.
 *
 * @package    theme_saec
 * @copyright  2026 SAEC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die;
}

require_sesskey();

$courseid = required_param('courseid', PARAM_INT);
$visible = required_param('visible', PARAM_BOOL);

if ($courseid == SITEID) {
    http_response_code(400);
    die;
}

$course = get_course($courseid);
$context = context_course::instance($course->id);
require_capability('moodle/course:visibility', $context);

course_change_visibility($course->id, $visible);

header('Content-Type: application/json');
echo json_encode(['courseid' => $course->id, 'visible' => $visible]);
