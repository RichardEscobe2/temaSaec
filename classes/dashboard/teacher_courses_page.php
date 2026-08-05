<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use context_course;
use moodle_url;
use stdClass;

/**
 * Backend data preparation for the Teacher "Mis Cursos" catalog
 * (/my/courses.php). Replaces block_myoverview's rendered output for
 * teachers, the same way courses_page.php already does for students —
 * scss/custom.scss's `body.page-mycourses .block_myoverview { display:
 * none; }` rule hides the native block unconditionally on that page (see
 * the big comment above that rule), which previously left teachers with a
 * blank content area since only the student catalog was ever built to fill
 * the gap. This class is the teacher-role counterpart, injected by
 * layout/drawers.php into the same page-content slot.
 */
class teacher_courses_page {

    /**
     * Unified context for templates/teacher_courses_page.mustache. Returns
     * null when the logged-in user is not a teacher (mirrors
     * student_dashboard::is_student()'s role guard, so the layout only
     * needs one truthiness check per page).
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
        $courses = self::get_course_cards($userid, $courseids);
        $periods = self::collect_periods($courses);
        $managementtoolcourses = self::get_management_tool_courses($courses, $userid);

        return [
            'sesskey' => sesskey(),
            'hascourses' => !empty($courses),
            'courses' => $courses,
            'hasperiods' => (count($periods) > 1),
            'periods' => $periods,
            'searchplaceholder' => get_string('teachercoursessearchplaceholder', 'theme_saec'),
            'hasmanagementtools' => !empty($managementtoolcourses),
            'managementtoolcourses' => $managementtoolcourses,
        ];
    }

    /**
     * Per-course URL set for the "Herramientas de Gestión" course picker —
     * one option per course where $userid actually holds moodle/course:update,
     * each carrying its own Import/Question Bank/Grade Settings/Completion
     * Settings links bound to that specific course id.
     *
     * get_taught_courseids() (and therefore $courses) is enrolment-based,
     * not capability-based — it returns every course the user is actively
     * enrolled in, including ones where their role is a non-editing
     * "teacher" (moodle/course:update = Prevent by default) rather than
     * "editingteacher". All 4 tools these links point to
     * (backup/import.php, question/edit.php, grade/edit/tree/index.php,
     * course/completion.php) require moodle/course:update, so without this
     * filter a teacher who is enrolled — but not an editing teacher — in
     * one of their courses would see it in the picker and hit a
     * required_capability_exception/nopermissions error the moment they
     * picked it. This mirrors the same has_capability('moodle/course:visibility', ...)
     * gate get_course_cards() already applies to the per-card visibility
     * switch below, just applied to course *inclusion* here instead of a
     * single control's enabled state.
     *
     * @param array[] $courses Cards from get_course_cards() (already carry
     *                         id/fullname/shortname, no extra queries here).
     * @param int $userid
     * @return array[]
     */
    private static function get_management_tool_courses(array $courses, int $userid): array {
        $options = [];
        foreach ($courses as $card) {
            $courseid = $card['id'];
            $context = context_course::instance($courseid);
            if (!has_capability('moodle/course:update', $context, $userid)) {
                continue;
            }

            $options[] = [
                'id' => $courseid,
                'label' => $card['shortname'] . ' — ' . $card['fullname'],
                'importurl' => (new moodle_url('/backup/import.php', ['id' => $courseid]))->out(false),
                'questionbankurl' => (new moodle_url('/question/edit.php', ['courseid' => $courseid]))->out(false),
                'gradesettingsurl' => (new moodle_url('/grade/edit/tree/index.php', ['id' => $courseid]))->out(false),
                'completionsettingsurl' => (new moodle_url('/course/completion.php', ['id' => $courseid]))->out(false),
            ];
        }
        return $options;
    }

    /**
     * One card per taught course: category badge, real active-student
     * count (batched mdl_enrol/mdl_user_enrolments join — one query for
     * every course at once, not one query per course), visibility toggle
     * state, and the Enter/Gradebook/Attendance action row.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return array[]
     */
    private static function get_course_cards(int $userid, array $courseids): array {
        global $CFG;

        if (empty($courseids)) {
            return [];
        }

        $studentcounts = self::fetch_student_counts($courseids);
        $attendanceinstalled = file_exists($CFG->dirroot . '/mod/attendance/version.php');
        $categorycache = [];

        $cards = [];
        foreach ($courseids as $courseid) {
            $course = get_course($courseid);
            $context = context_course::instance($courseid);

            $cards[] = [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'shortname' => $course->shortname,
                'categoryname' => self::resolve_category_name((int) $course->category, $categorycache),
                'period' => userdate($course->startdate, '%Y'),
                'studentcount' => $studentcounts[$courseid] ?? 0,
                'isvisible' => (bool) $course->visible,
                'canchangevisibility' => has_capability('moodle/course:visibility', $context, $userid),
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
                'gradebookurl' => (new moodle_url('/grade/report/index.php', ['id' => $courseid]))->out(false),
                'hasattendance' => $attendanceinstalled,
                'attendanceurl' => $attendanceinstalled
                    ? (new moodle_url('/mod/attendance/index.php', ['id' => $courseid]))->out(false)
                    : null,
            ];
        }
        return $cards;
    }

    /**
     * Active (status=0) student-role enrolment count for every $courseids
     * at once — a single batched join rather than one count_enrolled_users()
     * call per course, per this page's explicit "efficient mdl_enrol/
     * mdl_user_enrolments" requirement. MUC-cached (see db/caches.php,
     * 'teachercoursedetails' definition).
     *
     * @param int[] $courseids
     * @return array<int, int> courseid => student count.
     */
    private static function fetch_student_counts(array $courseids): array {
        global $DB;

        sort($courseids);
        $cache = \cache::make('theme_saec', 'teachercoursedetails');
        $cachekey = 'students_' . implode('_', $courseids);
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');

        $sql = "SELECT e.courseid, COUNT(DISTINCT ue.userid) AS studentcount
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                  JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :contextcourse
                  JOIN {role_assignments} ra ON ra.userid = ue.userid AND ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                 WHERE e.courseid $insql AND ue.status = 0
              GROUP BY e.courseid";
        $params = array_merge($inparams, ['contextcourse' => CONTEXT_COURSE]);

        $counts = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $counts[(int) $row->courseid] = (int) $row->studentcount;
        }

        $cache->set($cachekey, $counts);
        return $counts;
    }

    /**
     * Resolves + caches a course category's display name — same
     * per-request pattern as courses_page::resolve_category_name(), kept as
     * its own copy rather than shared since that method is private to its
     * own class and this page's category cache has a different lifetime
     * (function-local, not instance state).
     *
     * @param int $categoryid
     * @param array $cache Passed by reference, shared across calls.
     * @return string
     */
    private static function resolve_category_name(int $categoryid, array &$cache): string {
        global $DB;
        if (!array_key_exists($categoryid, $cache)) {
            $name = $DB->get_field('course_categories', 'name', ['id' => $categoryid]);
            $cache[$categoryid] = ($name !== false) ? format_string($name) : '';
        }
        return $cache[$categoryid];
    }

    /**
     * Distinct period labels (course start-date years) present across
     * $courses, newest first — options for the period filter dropdown.
     * "Period/term" isn't a field Moodle courses actually have, so this
     * derives an honest real value (startdate's year) rather than
     * fabricating an academic-cycle concept the data model doesn't have.
     *
     * @param array[] $courses Cards from get_course_cards().
     * @return string[]
     */
    private static function collect_periods(array $courses): array {
        $periods = [];
        foreach ($courses as $card) {
            $periods[$card['period']] = true;
        }
        $periods = array_keys($periods);
        rsort($periods);
        return $periods;
    }
}
