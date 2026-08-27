<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

/**
 * MUC definitions for theme_saec.
 *
 * "instructor" backs instructor_resolver::resolve() — the first
 * editing-teacher lookup shown in both the course hero and the assignment
 * header. Teacher assignment for a course changes rarely (a re-enrolment,
 * not a per-request event), so a short TTL is enough to cut a
 * get_enrolled_users() query off nearly every student page load without
 * risking meaningfully stale data.
 *
 * "teachercourses" backs teacher_dashboard::get_taught_courseids() — the
 * enrol_get_all_users_courses() lookup shared by is_teacher(), the KPI
 * cards, the pending-grading table and the deadlines widget (all on the
 * same /my/ request). Same rationale/TTL as "instructor": a teacher's own
 * course list changes about as rarely as who teaches a given course.
 *
 * "pendinggrading" backs teacher_dashboard::fetch_pending_grading() and
 * ::fetch_total_submitted_count() — the cross-course "needs grading" join
 * (and its un-filtered total-submitted sibling query) that the
 * pending-submissions KPI, its grading-efficiency rate, and the "Por
 * Calificar" table all read from. Unlike the two caches above, this
 * reflects genuinely volatile state (a student submitting, a teacher
 * grading), so it uses a much shorter TTL: long enough to avoid running the
 * join twice on the same page load, short enough that a newly graded
 * submission drops off the list within a couple of minutes rather than
 * lingering for an hour. Stores stdClass row objects, so simpledata is off.
 *
 * "nextclass" backs teacher_dashboard::fetch_next_attendance_session() —
 * the welcome hero's "Próxima clase" lookup. TTL is intentionally the
 * shortest of the bunch: a session's in-progress/upcoming state is a pure
 * function of wall-clock time, so caching it as long as "pendinggrading"
 * would visibly desync the badge from reality near a session boundary.
 *
 * "teachercoursedetails" backs teacher_courses_page::get_context() — the
 * batched mdl_enrol/mdl_user_enrolments student-count join for the "Mis
 * Cursos" card grid. Enrolment counts drift slowly (a manual enrolment here
 * and there), so a moderate TTL keeps that join off nearly every
 * /my/courses.php load without meaningfully stale headcounts.
 *
 * "adminkpis" backs admin_hub_page::get_kpis() — the 4 sitewide COUNT()
 * queries (active students, enrolled teachers, active courses, issued
 * badges) behind the Admin Command Center's top KPI row. Same drift profile
 * as "teachercourses" (an enrolment or a badge issuance here and there, not
 * a per-request event), so it gets the same moderate TTL.
 */
$definitions = [
    'instructor' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 3600,
    ],
    'teachercourses' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 3600,
    ],
    'pendinggrading' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 120,
    ],
    'nextclass' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 60,
    ],
    'teachercoursedetails' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 600,
    ],
    'adminkpis' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 600,
    ],
];
