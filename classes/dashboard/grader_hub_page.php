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
        $categorycache = [];

        $cards = [];
        foreach ($courseids as $courseid) {
            $course = get_course($courseid);
            $context = context_course::instance($courseid);

            $cards[] = [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'shortname' => $course->shortname,
                'categoryname' => teacher_courses_page::resolve_category_name((int) $course->category, $categorycache),
                'studentcount' => $studentcounts[$courseid] ?? 0,
                'gradebookurl' => (new moodle_url('/grade/report/index.php', ['id' => $courseid]))->out(false),
            ];
        }
        return $cards;
    }
}
