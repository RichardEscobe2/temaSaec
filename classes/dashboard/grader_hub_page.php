<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use context_course;
use moodle_url;

/**
 * Backend data preparation for the Gradebook "Course Selection Hub"
 * (/grade/report/overview/index.php), teacher-facing branch — the
 * counterpart to analytics_page.php, which already replaces this exact
 * page's output for Students.
 *
 * Only reachable when grade_report_overview::print_teacher_table() is the
 * branch core would have run: $PAGE->course->id == SITEID (see
 * layout/drawers.php's guard) and the viewer teaches at least one course.
 * That native table is a bare <table class="generaltable"> of course-name
 * links only — no category, no student count, nothing a real CTA button
 * can be built from — so unlike the pure-CSS pass this page already got
 * for #overview-grade (scss/saec/_grade_overview.scss), this branch needs
 * real backend data. It still renders (core, can't be skipped) and is
 * hidden via CSS (body.page-grader-hub, scss/saec/_grader_hub.scss) rather
 * than touched.
 */
class grader_hub_page {

    /**
     * Unified context for templates/grader_hub_page.mustache. Returns null
     * when the logged-in user teaches no course (mirrors
     * teacher_courses_page::get_context()'s role guard).
     *
     * @param int $userid 0 for the current user.
     * @return array|null
     */
    public static function get_context(int $userid = 0): ?array {
        global $USER;
        $userid = $userid ?: (int) $USER->id;

        if (!teacher_dashboard::is_teacher($userid)) {
            return null;
        }

        $courseids = teacher_dashboard::get_taught_courseids($userid);
        $courses = self::get_course_cards($courseids);
        $header = teacher_dashboard::get_teacher_header_data($userid);

        return [
            'fullname' => $header['fullname'],
            'avatarurl' => $header['avatarurl'],
            'coursecount' => count($courses),
            'hascourses' => !empty($courses),
            'courses' => $courses,
        ];
    }

    /**
     * One card per taught course: category badge, real active-student
     * count (reuses teacher_courses_page's batched query + cache — same
     * underlying data, no reason to run it twice), and the gradebook CTA.
     *
     * @param int[] $courseids
     * @return array[]
     */
    private static function get_course_cards(array $courseids): array {
        if (empty($courseids)) {
            return [];
        }

        $studentcounts = teacher_courses_page::fetch_student_counts($courseids);
        $studentsbycoures = self::fetch_students_per_course($courseids);
        $categorycache = [];

        $cards = [];
        foreach ($courseids as $courseid) {
            $course = get_course($courseid);
            $context = context_course::instance($courseid);
            $students = $studentsbycoures[$courseid] ?? [];

            $cards[] = [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'shortname' => $course->shortname,
                'categoryname' => teacher_courses_page::resolve_category_name((int) $course->category, $categorycache),
                'studentcount' => $studentcounts[$courseid] ?? 0,
                'gradebookurl' => (new moodle_url('/grade/report/index.php', ['id' => $courseid]))->out(false),
                'boletabaseurl' => (new moodle_url('/grade/report/user/index.php'))->out(false),
                'hasstudents' => !empty($students),
                'students' => $students,
            ];
        }
        return $cards;
    }

    /**
     * Enrolled students (id + display name) per course, one batched query
     * for every card on this hub — same "single join, not one query per
     * course" convention as teacher_courses_page::fetch_student_counts(),
     * which this method mirrors closely. Backs each course card's "Ver
     * boleta de alumno" quick-jump select
     * (grade/report/user/index.php?id=<courseid>&userid=<studentid>),
     * collapsing what would otherwise be a Calificador -> Grader Hub ->
     * switch report type -> search-by-name flow (4 clicks) into a single
     * select-and-go action from the hub itself.
     *
     * @param int[] $courseids
     * @return array<int, array{id: int, fullname: string}[]> courseid => students, lastname-sorted.
     */
    private static function fetch_students_per_course(array $courseids): array {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        sort($courseids);
        $cache = \cache::make('theme_saec', 'teachercoursedetails');
        $cachekey = 'studentlist_' . implode('_', $courseids);
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $namefields = \core_user\fields::for_name()->get_sql('u');

        $sql = "SELECT ue.id AS uniqueid, e.courseid, u.id AS userid {$namefields->selects}
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                  JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
                  JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :contextcourse
                  JOIN {role_assignments} ra ON ra.userid = ue.userid AND ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                 WHERE e.courseid $insql AND ue.status = 0
              ORDER BY e.courseid, u.lastname, u.firstname";
        $params = array_merge($inparams, ['contextcourse' => CONTEXT_COURSE]);

        $bycourse = array_fill_keys($courseids, []);
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $bycourse[(int) $row->courseid][] = [
                'id' => (int) $row->userid,
                'fullname' => fullname($row),
            ];
        }

        $cache->set($cachekey, $bycourse);
        return $bycourse;
    }
}
