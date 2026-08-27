<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use completion_info;
use context_course;
use moodle_url;
use stdClass;

/**
 * Backend data preparation for the "Mis Cursos" catalog (/my/courses.php —
 * Fase 8). Replaces block_myoverview's rendered output for students (hidden
 * via scss/custom.scss) with a catalog matching stitch_uptex's design:
 * real completion-based "En Curso"/"Completados" classification (Moodle's
 * own native grouping is date-based, not completion-based) and a real
 * "Disponibles" list of self-enrolable courses the student hasn't joined.
 *
 * block_myoverview's own tab filters can't be extended with new
 * classification values without editing core (course/externallib.php
 * validates a fixed enum), so this class/page pair stands entirely apart
 * from that block rather than templating over it.
 */
class courses_page {

    /** @var int Max self-enrolable courses returned to the "Disponibles" tab. */
    const MAX_AVAILABLE_COURSES = 12;

    /** @var bool Guards the require_once call in bootstrap() against repeated file includes. */
    private static bool $bootstrapped = false;

    private static function bootstrap(): void {
        if (self::$bootstrapped) {
            return;
        }
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        self::$bootstrapped = true;
    }

    /**
     * Unified context for templates/my_courses_page.mustache.
     *
     * @param int $userid 0 for the current user.
     * @return array
     */
    public static function get_context(int $userid = 0): array {
        global $USER;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;

        $enrolledcourses = enrol_get_all_users_courses(
            $userid,
            true,
            'summary, summaryformat, startdate, enddate, category, enablecompletion, visible'
        );

        $categorycache = [];
        $inprogress = [];
        $completed = [];
        $enrolledids = [];

        foreach ($enrolledcourses as $course) {
            if (empty($course->visible)) {
                continue;
            }
            $enrolledids[] = (int) $course->id;
            $card = self::export_enrolled_card($course, $userid, $categorycache);
            if ($card['showcompletedbuttons']) {
                $completed[] = $card;
            } else {
                $inprogress[] = $card;
            }
        }

        $available = self::get_available_courses($enrolledids, $categorycache);

        return [
            'hasinprogress' => !empty($inprogress),
            'inprogresscourses' => $inprogress,
            'hascompleted' => !empty($completed),
            'completedcourses' => $completed,
            'hasavailable' => !empty($available),
            'availablecourses' => $available,
        ];
    }

    /**
     * Exports one enrolled course into a catalog-card context, classified by
     * REAL completion percentage (>= 100%) rather than Moodle's native
     * date-based "past" grouping.
     *
     * @param stdClass $course
     * @param int $userid
     * @param array $categorycache Passed by reference, shared across calls.
     * @return array
     */
    private static function export_enrolled_card(stdClass $course, int $userid, array &$categorycache): array {
        $completion = new completion_info($course);
        $progresspercent = null;
        $completedmodules = null;
        $totalmodules = null;

        if ($completion->is_enabled()) {
            $totalmodules = count($completion->get_activities());
            if ($totalmodules > 0) {
                $completedmodules = $completion->count_modules_completed($userid);
                $progresspercent = \core_completion\progress::get_course_progress_percentage($course, $userid);
            }
        }

        $hascompletiondata = ($totalmodules !== null && $totalmodules > 0);
        $progresspercent = $progresspercent !== null ? (int) round($progresspercent) : 0;
        $iscompleted = ($hascompletiondata && $progresspercent >= 100);

        return array_merge(
            self::export_course_base($course, $categorycache),
            [
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'hasprogressbar' => $hascompletiondata && !$iscompleted,
                'progresspercent' => $progresspercent,
                'progresslabel' => $hascompletiondata
                    ? get_string('courseprogresslabel', 'theme_saec', (object) [
                        'completed' => $completedmodules,
                        'total' => $totalmodules,
                    ])
                    : null,
                'showcontinuebutton' => !$iscompleted,
                'showcompletedbuttons' => $iscompleted,
                'showenrolbutton' => false,
                'badgeurl' => (new moodle_url('/badges/mybadges.php'))->out(false),
            ]
        );
    }

    /**
     * Self-enrolable courses the student is NOT already enrolled in, for the
     * "Disponibles" tab. "Available" is scoped to courses with an enabled
     * enrol_self instance — visible-but-not-self-enrolable courses (manual
     * enrolment only, etc.) are not something a student can actually act on
     * from this page, so they're excluded rather than shown as a dead end.
     *
     * @param int[] $enrolledids
     * @param array $categorycache Passed by reference, shared across calls.
     * @return array
     */
    private static function get_available_courses(array $enrolledids, array &$categorycache): array {
        global $DB;

        $params = ['siteid' => SITEID];
        $notinsql = '';
        if (!empty($enrolledids)) {
            list($notinsql, $notinparams) = $DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED, 'enrolled', false);
            $notinsql = "AND c.id $notinsql";
            $params = array_merge($params, $notinparams);
        }

        $sql = "SELECT DISTINCT c.id
                  FROM {course} c
                  JOIN {enrol} e ON e.courseid = c.id AND e.enrol = 'self' AND e.status = 0
                 WHERE c.visible = 1 AND c.id <> :siteid $notinsql
              ORDER BY c.id";
        $ids = array_keys($DB->get_records_sql($sql, $params, 0, self::MAX_AVAILABLE_COURSES));

        $available = [];
        foreach ($ids as $courseid) {
            $course = get_course($courseid);
            $available[] = array_merge(
                self::export_course_base($course, $categorycache),
                [
                    'enrolurl' => (new moodle_url('/enrol/index.php', ['id' => $course->id]))->out(false),
                    'hasprogressbar' => false,
                    'progresslabel' => null,
                    'showcontinuebutton' => false,
                    'showcompletedbuttons' => false,
                    'showenrolbutton' => true,
                ]
            );
        }
        return $available;
    }

    /**
     * Fields shared by every card regardless of tab: identity, course image,
     * and category badge text.
     *
     * @param stdClass $course
     * @param array $categorycache Passed by reference, shared across calls.
     * @return array
     */
    private static function export_course_base(stdClass $course, array &$categorycache): array {
        $courseimage = \theme_saec\course_helper::get_course_image_url($course);

        return [
            'id' => (int) $course->id,
            'fullname' => format_string($course->fullname, true, ['context' => context_course::instance($course->id)]),
            'hascourseimage' => !empty($courseimage),
            'courseimage' => $courseimage,
            'categoryname' => self::resolve_category_name((int) $course->category, $categorycache),
        ];
    }

    /**
     * Resolves + caches a course category's display name (one query per
     * distinct category id per request, not per course).
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
}
