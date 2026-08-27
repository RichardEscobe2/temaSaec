<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use context_course;
use moodle_url;

/**
 * Backend data preparation for the Admin Global Course Catalog / Academic
 * Operations Center (/my/courses.php, admin branch) — injected by
 * layout/drawers.php exactly like courses_page (Student) and
 * teacher_courses_page (Teacher) already are for their own roles.
 *
 * Unlike admin_dashboard's "Cursos Activos" summary (capped, visible-only,
 * a quick-glance resumen), this is the full audit catalog: every course in
 * the system except the site course, visible AND hidden — an admin auditing
 * the catalog needs to see hidden courses too, not just the ones currently
 * published to students.
 */
class admin_courses_page {

    /**
     * Unified context for templates/admin_courses_page.mustache. Returns
     * null when the logged-in user is not a site admin (mirrors every
     * other *_page::get_context()'s role guard).
     *
     * @param int $userid 0 for the current user.
     * @return array|null
     */
    public static function get_context(int $userid = 0): ?array {
        global $USER;
        $userid = $userid ?: (int) $USER->id;

        if (!is_siteadmin($userid)) {
            return null;
        }

        return array_merge(
            [
                'filterplaceholder' => get_string('adminfiltercatalog', 'theme_saec'),
                'createcourseurl' => (new moodle_url('/course/edit.php', ['category' => 1]))->out(false),
                'csvuploadurl' => (new moodle_url('/admin/tool/uploadcourse/index.php'))->out(false),
                'managecategoriesurl' => (new moodle_url('/course/management.php'))->out(false),
            ],
            self::get_courses()
        );
    }

    /**
     * Every course except the site course, alphabetically — batched student
     * counts and lead-teacher resolution (both single-query-per-catalog
     * lookups, not one per course), each row carrying the full 6-action
     * group plus a visibility badge and a "Sin Docente Asignado" warning
     * when no editing teacher is enrolled.
     *
     * Calificador points at the grader report (grade/report/grader), not
     * the report-overview index admin_dashboard's summary table links to —
     * this catalog is an audit tool, so it goes straight to the gradebook
     * grid rather than the course-selection landing page. Duplicar/
     * Respaldar open core's own course-copy and backup wizards unmodified —
     * no theme-specific backup logic exists here beyond the link.
     *
     * @return array{hascourses: bool, courses: array[]}
     */
    private static function get_courses(): array {
        global $DB;

        $courseids = array_map('intval', array_keys($DB->get_records_select(
            'course',
            'id <> :siteid',
            ['siteid' => SITEID],
            'fullname ASC',
            'id'
        )));

        if (empty($courseids)) {
            return ['hascourses' => false, 'courses' => []];
        }

        $studentcounts = teacher_courses_page::fetch_student_counts($courseids);
        $teachers = self::fetch_lead_teachers($courseids);
        $attendancecourseids = admin_dashboard::fetch_courseids_with_attendance($courseids);
        $categorycache = [];

        $courses = [];
        foreach ($courseids as $courseid) {
            $course = get_course($courseid);
            $context = context_course::instance($courseid);
            $teacher = $teachers[$courseid] ?? null;
            $courseimage = \theme_saec\course_helper::get_course_image_url($course);
            $hasattendance = in_array($courseid, $attendancecourseids, true);

            $courses[] = [
                'id' => $courseid,
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'shortname' => format_string($course->shortname, true, ['context' => $context]),
                'categoryname' => teacher_courses_page::resolve_category_name((int) $course->category, $categorycache),
                'studentcount' => $studentcounts[$courseid] ?? 0,
                'hascourseimage' => !empty($courseimage),
                'courseimage' => $courseimage,
                'showvisibility' => true,
                'isvisible' => (bool) $course->visible,
                'hasteachercolumn' => true,
                'hasteacher' => ($teacher !== null),
                'teachername' => $teacher['name'] ?? null,
                'teacheravatarurl' => $teacher['avatarurl'] ?? null,
                'hasentercourse' => true,
                'entercourseurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
                'configureurl' => (new moodle_url('/course/edit.php', ['id' => $courseid]))->out(false),
                'participantsurl' => (new moodle_url('/user/index.php', ['id' => $courseid]))->out(false),
                'gradesurl' => (new moodle_url('/grade/report/grader/index.php', ['id' => $courseid]))->out(false),
                'hasattendance' => $hasattendance,
                'attendanceurl' => $hasattendance
                    ? (new moodle_url('/mod/attendance/index.php', ['id' => $courseid]))->out(false)
                    : null,
                'hascatalogactions' => true,
                'duplicateurl' => (new moodle_url('/backup/copy.php', ['id' => $courseid]))->out(false),
                'backupurl' => (new moodle_url('/backup/backup.php', ['id' => $courseid]))->out(false),
                // Opt-in: switches admin_course_row.mustache's actions cell
                // to the compact "Entrar al Curso →" primary button + "⋮"
                // dropdown pattern. Left unset by admin_dashboard's own
                // "Cursos Activos" summary rows (same shared partial), whose
                // simpler stacked-links actions column stays unchanged.
                'usecompactactions' => true,
            ];
        }

        return ['hascourses' => !empty($courses), 'courses' => $courses];
    }

    /**
     * Lead (first editing-teacher) per $courseids, reusing
     * instructor_resolver::resolve() — the same single source of truth the
     * course hero and assignment header already use, so this catalog never
     * disagrees with them about who teaches a course. Its own MUC cache
     * (see db/caches.php, 'instructor' definition) already makes repeat
     * calls across a whole catalog page cheap; no separate batching needed
     * here since resolve() is only ever a single get_enrolled_users() query
     * per course, not a heavier join.
     *
     * @param int[] $courseids
     * @return array<int, array{name: string, avatarurl: string, messageurl: string}>
     */
    private static function fetch_lead_teachers(array $courseids): array {
        $teachers = [];
        foreach ($courseids as $courseid) {
            $teacher = instructor_resolver::resolve($courseid, context_course::instance($courseid));
            if ($teacher !== null) {
                $teachers[$courseid] = $teacher;
            }
        }
        return $teachers;
    }
}
