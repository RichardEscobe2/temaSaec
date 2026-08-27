<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use completion_info;
use context_course;
use context_system;
use moodle_url;
use stdClass;
use user_picture;

/**
 * Backend data preparation for the UPTex Student Dashboard (/my/).
 *
 * Every public get_student_* method returns a self-contained, Mustache-ready
 * context fragment (booleans for {{#has*}} guards, pre-formatted strings via
 * get_string()/format_string()/userdate()). get_dashboard_context() merges
 * them into the single array student_dashboard.mustache will consume.
 *
 * Only real Moodle data is ever returned; when a data source is unavailable
 * (module not installed, no grades recorded, etc.) the affected field
 * degrades to a null/'N/A' fallback instead of being fabricated.
 */
class student_dashboard {

    /** @var int Max upcoming deadlines returned to the "Próximas Entregas" widget. */
    const MAX_DEADLINES = 5;

    /** @var int Max badges returned to the "Mi Mochila" widget. */
    const MAX_BACKPACK_BADGES = 6;

    /** @var bool Guards the require_once calls in bootstrap() against repeated file includes. */
    private static bool $bootstrapped = false;

    /**
     * Requires the optional core subsystem libraries this class depends on
     * (completion, gradebook, badges, calendar) that theme code cannot rely
     * on being loaded already, unlike lib/datalib.php or lib/enrollib.php.
     */
    private static function bootstrap(): void {
        if (self::$bootstrapped) {
            return;
        }
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/badgeslib.php');
        require_once($CFG->dirroot . '/calendar/lib.php');
        self::$bootstrapped = true;
    }

    /**
     * True when $userid is a logged-in, non-guest user who is neither a site
     * admin nor a teacher (moodle/course:update) in any of their courses.
     * Mirrors the role split already used by layout/drawers.php's sidebar.
     *
     * @param int $userid 0 for the current user.
     * @return bool
     */
    public static function is_student(int $userid = 0): bool {
        global $USER;
        $userid = $userid ?: (int) $USER->id;

        if (!isloggedin() || isguestuser($userid)) {
            return false;
        }
        if (is_siteadmin($userid)) {
            return false;
        }

        foreach (enrol_get_all_users_courses($userid, true, ['id']) as $course) {
            if (has_capability('moodle/course:update', context_course::instance($course->id), $userid)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Header/greeting data: identity, avatar, and the pending-submissions
     * count for the current week (assignments only, across all enrolled
     * courses).
     *
     * @param int $userid 0 for the current user.
     * @return array
     */
    public static function get_student_header_data(int $userid = 0): array {
        global $USER, $PAGE;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;
        $user = ($userid === (int) $USER->id) ? $USER : \core_user::get_user($userid, '*', IGNORE_MISSING);

        if (!$user) {
            return [
                'firstname' => '', 'fullname' => '', 'avatarurl' => null,
                'greeting' => '', 'pendingcount' => 0, 'haspending' => false,
                'pendingmessage' => '',
            ];
        }

        $courseids = array_keys(enrol_get_all_users_courses($userid, true, ['id']));
        $pendingcount = self::count_pending_assignments_this_week($userid, $courseids);

        $userpicture = new user_picture($user);
        $userpicture->size = 100;

        return [
            'firstname' => $user->firstname,
            'fullname' => fullname($user),
            'avatarurl' => \theme_saec\course_helper::to_relative_url($userpicture->get_url($PAGE)->out(false)),
            'greeting' => get_string('dashboardwelcomeback', 'theme_saec', $user->firstname),
            'pendingcount' => $pendingcount,
            'haspending' => $pendingcount > 0,
            'pendingmessage' => $pendingcount > 0
                ? get_string('dashboardpendingsubmissions', 'theme_saec', $pendingcount)
                : get_string('dashboardnopending', 'theme_saec'),
        ];
    }

    /**
     * Academic activity summary KPIs: GPA, attendance, weekly study hours,
     * and total earned badges. Shaped as a list of theme_saec/components/
     * metric_card contexts, in the mockup's display order.
     *
     * @param int $userid 0 for the current user.
     * @return array{kpis: array[]}
     */
    public static function get_student_kpis(int $userid = 0): array {
        global $USER, $DB, $OUTPUT;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;
        $courseids = array_keys(enrol_get_all_users_courses($userid, true, ['id']));

        $gpa = self::compute_gpa($userid, $courseids);
        $attendance = self::compute_attendance_percentage($userid, $courseids);
        $attendanceurl = self::find_any_attendance_url($courseids);
        $taskstotals = self::compute_task_completion($userid, $courseids);
        $badgecount = $DB->count_records('badge_issued', ['userid' => $userid]);

        $icon = fn (string $pix) => $OUTPUT->pix_icon($pix, '', 'moodle', ['class' => 'saec-kpi-card__icon-img']);
        $nodata = get_string('kpinodata', 'theme_saec');

        $kpis = [
            [
                'key' => 'gpa',
                'icon' => $icon('i/grades'),
                'iconvariant' => 'primary',
                'label' => get_string('kpigpa', 'theme_saec'),
                'value' => $gpa !== null ? number_format($gpa, 1) : $nodata,
                'hasvaluesuffix' => $gpa !== null,
                'valuesuffix' => '/ 10',
                'valuemodifier' => $gpa !== null ? ($gpa >= 8.0 ? 'good' : 'warn') : '',
                'hasfootnote' => false,
                'footnote' => null,
                'hasurl' => true,
                'url' => (new moodle_url('/grade/report/overview/index.php'))->out(false),
            ],
            [
                'key' => 'attendance',
                'icon' => $icon('i/calendar'),
                'iconvariant' => 'accent',
                'label' => get_string('kpiattendance', 'theme_saec'),
                'value' => $attendance !== null ? round($attendance) . '%' : $nodata,
                'hasvaluesuffix' => false,
                'valuesuffix' => null,
                'hasfootnote' => false,
                'footnote' => null,
                'hasurl' => $attendanceurl !== null,
                'url' => $attendanceurl,
            ],
            [
                'key' => 'tasks',
                'icon' => $icon('i/checkedcircle'),
                'iconvariant' => 'muted',
                'label' => get_string('kpitasks', 'theme_saec'),
                'value' => $taskstotals !== null
                    ? get_string('kpitasksvalue', 'theme_saec', (object) $taskstotals)
                    : $nodata,
                'hasvaluesuffix' => false,
                'valuesuffix' => null,
                'hasfootnote' => $taskstotals !== null,
                'footnote' => $taskstotals !== null
                    ? get_string('kpitaskscompletedlabel', 'theme_saec', $taskstotals['percent'])
                    : null,
                'hasurl' => false,
                'url' => null,
            ],
            [
                'key' => 'badges',
                'icon' => $icon('i/badge'),
                'iconvariant' => 'accent',
                'label' => get_string('kpibadges', 'theme_saec'),
                'value' => (string) $badgecount,
                'hasvaluesuffix' => false,
                'valuesuffix' => null,
                'hasfootnote' => true,
                'footnote' => get_string('kpibadgesfootnote', 'theme_saec'),
                'hasurl' => true,
                'url' => (new moodle_url('/badges/mybadges.php'))->out(false),
            ],
        ];

        return ['kpis' => $kpis];
    }

    /**
     * Enrolled courses with completion progress, one card per course.
     *
     * @param int $userid 0 for the current user.
     * @return array{hascourses: bool, courses: array[]}
     */
    public static function get_student_courses_progress(int $userid = 0): array {
        global $USER;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;

        $courses = enrol_get_all_users_courses(
            $userid,
            true,
            'summary, summaryformat, startdate, enddate, category, enablecompletion, visible'
        );

        $cards = [];
        foreach ($courses as $course) {
            if (empty($course->visible)) {
                continue;
            }
            $cards[] = self::export_course_progress_card($course, $userid);
        }

        return ['hascourses' => !empty($cards), 'courses' => $cards];
    }

    /**
     * Upcoming assignment/quiz due dates across enrolled courses, formatted
     * for the "Próximas Entregas" widget (day tag, day number, time).
     *
     * Scope note: uses the classic calendar_get_events() course-events
     * branch, i.e. it surfaces standard (non user-overridden) due dates.
     * Per-user calendar overrides are out of scope for this backend pass.
     *
     * @param int $userid 0 for the current user.
     * @param int $limit
     * @return array{hasdeadlines: bool, deadlines: array[]}
     */
    public static function get_student_upcoming_deadlines(int $userid = 0, int $limit = self::MAX_DEADLINES): array {
        global $USER;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;

        $courseids = array_keys(enrol_get_all_users_courses($userid, true, ['id']));
        if (empty($courseids)) {
            return ['hasdeadlines' => false, 'deadlines' => []];
        }

        $now = time();
        $events = calendar_get_events($now, $now + (30 * DAYSECS), false, false, $courseids, true, true);

        $filtered = array_values(array_filter($events, function (stdClass $event): bool {
            return in_array($event->modulename ?? '', ['assign', 'quiz'], true);
        }));

        usort($filtered, fn (stdClass $a, stdClass $b) => $a->timestart <=> $b->timestart);
        $filtered = array_slice($filtered, 0, $limit);

        $coursecache = [];
        $deadlines = [];
        foreach ($filtered as $event) {
            if (!isset($coursecache[$event->courseid])) {
                $coursecache[$event->courseid] = get_course($event->courseid);
            }
            $deadlines[] = self::export_deadline($event, $coursecache[$event->courseid]);
        }

        return ['hasdeadlines' => !empty($deadlines), 'deadlines' => $deadlines];
    }

    /**
     * Latest awarded badges for the "Mi Mochila" backpack widget.
     *
     * @param int $userid 0 for the current user.
     * @param int $limit
     * @return array{hasbadges: bool, badges: array[], canexport: bool}
     */
    public static function get_student_backpack_data(int $userid = 0, int $limit = self::MAX_BACKPACK_BADGES): array {
        global $USER, $CFG;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;

        if (empty($CFG->enablebadges)) {
            return ['hasbadges' => false, 'badges' => [], 'canexport' => false];
        }

        $records = badges_get_user_badges($userid, 0, 0, $limit) ?: [];
        $dateformat = get_string('strftimedatefullshort', 'langconfig');

        $badges = [];
        foreach ($records as $record) {
            $badges[] = [
                'id' => (int) $record->id,
                'title' => format_string($record->name),
                'imageurl' => \theme_saec\course_helper::to_relative_url(self::resolve_badge_image_url($record)->out(false)),
                'date' => get_string('badgeissued', 'theme_saec', userdate($record->dateissued, $dateformat)),
                'isverified' => (bool) $record->visible,
                'ispending' => false,
            ];
        }

        return [
            'hasbadges' => !empty($badges),
            'badges' => $badges,
            'canexport' => !empty($CFG->badges_allowexternalbackpack),
        ];
    }

    /**
     * Static targets for the Student Dashboard's 1-click quick-action bar.
     * Mirrors teacher_dashboard::get_teacher_quickaction_links() — every
     * link routes to an existing, already-working page:
     * - tasksurl: theme_saec's own Tasks Hub (pages/student_tasks.php).
     * - boletaurl: /grade/report/overview/index.php, the same URL
     *   is_student() gates layout/drawers.php's "Mi Rendimiento" overlay on
     *   — this button is a direct shortcut to that existing sidebar
     *   destination, not a second/different report.
     * - badgesurl: core's own badges/mybadges.php, unstyled by theme_saec
     *   but real and functional.
     * - calendarurl: reused as-is; layout/drawers.php already computes an
     *   identical value under the same key for the sidebar's own calendar
     *   button, so this simply gives the quick-action bar its own copy.
     *
     * @return array{tasksurl: string, boletaurl: string, badgesurl: string, quickcalendarurl: string}
     */
    public static function get_student_quickaction_links(): array {
        return [
            'tasksurl' => (new moodle_url('/theme/saec/pages/student_tasks.php'))->out(false),
            'boletaurl' => (new moodle_url('/grade/report/overview/index.php'))->out(false),
            'badgesurl' => (new moodle_url('/badges/mybadges.php'))->out(false),
            'quickcalendarurl' => (new moodle_url('/calendar/view.php', ['view' => 'month']))->out(false),
        ];
    }

    /**
     * Unified context for student_dashboard.mustache. Returns null when the
     * logged-in user is not a student (teacher/admin dashboards are out of
     * scope for this context builder).
     *
     * @param int $userid 0 for the current user.
     * @return array|null
     */
    public static function get_dashboard_context(int $userid = 0): ?array {
        global $USER;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;

        if (!self::is_student($userid)) {
            return null;
        }

        return array_merge(
            self::get_student_header_data($userid),
            self::get_student_kpis($userid),
            self::get_student_courses_progress($userid),
            self::get_student_upcoming_deadlines($userid),
            self::get_student_backpack_data($userid),
            self::get_student_quickaction_links()
        );
    }

    /**
     * Number of assignments due by the end of the current week (including
     * any already overdue) across $courseids that $userid has not submitted.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return int
     */
    private static function count_pending_assignments_this_week(int $userid, array $courseids): int {
        global $DB;
        if (empty($courseids)) {
            return 0;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $weekend = usergetmidnight(time()) + WEEKSECS;

        $sql = "SELECT COUNT(1)
                  FROM {assign} a
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
             LEFT JOIN {assign_submission} s ON s.assignment = a.id
                        AND s.userid = :userid AND s.latest = 1 AND s.status = 'submitted'
                 WHERE a.course $insql
                       AND cm.visible = 1
                       AND a.duedate > 0
                       AND a.duedate <= :weekend
                       AND s.id IS NULL";
        $params = array_merge($inparams, ['userid' => $userid, 'weekend' => $weekend]);

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Simple (unweighted) mean course grade, normalised to a 0-10 scale, of
     * every enrolled course that has a recorded course-total grade. No
     * credit-hour data exists on the course record to weight by, so a plain
     * mean across graded courses is the closest honest approximation of a
     * "weighted average".
     *
     * @param int $userid
     * @param int[] $courseids
     * @return float|null null when no course has a recorded grade yet.
     */
    private static function compute_gpa(int $userid, array $courseids): ?float {
        $sum = 0.0;
        $count = 0;

        foreach ($courseids as $courseid) {
            $courseitem = \grade_item::fetch_course_item($courseid);
            if (!$courseitem) {
                continue;
            }
            $grade = \grade_grade::fetch(['itemid' => $courseitem->id, 'userid' => $userid]);
            if (!$grade || $grade->finalgrade === null) {
                continue;
            }
            $min = (float) $courseitem->grademin;
            $max = (float) $courseitem->grademax;
            if ($max <= $min) {
                continue;
            }
            $sum += (((float) $grade->finalgrade - $min) / ($max - $min)) * 10;
            $count++;
        }

        return $count > 0 ? ($sum / $count) : null;
    }

    /**
     * Aggregate mod_attendance completion percentage across every
     * attendance instance in $courseids. Returns null when mod_attendance
     * is not installed or the user has no recorded sessions anywhere.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return float|null Percentage 0-100.
     */
    private static function compute_attendance_percentage(int $userid, array $courseids): ?float {
        global $CFG;
        if (empty($courseids) || !file_exists($CFG->dirroot . '/mod/attendance/version.php')) {
            return null;
        }
        require_once($CFG->dirroot . '/mod/attendance/locallib.php');
        require_once($CFG->dirroot . '/mod/attendance/classes/summary.php');

        $totalpoints = 0.0;
        $totalmax = 0.0;
        $hasany = false;

        foreach ($courseids as $courseid) {
            $course = get_course($courseid);
            foreach (get_all_instances_in_course('attendance', $course) as $instance) {
                $summary = new \mod_attendance_summary($instance->id, [$userid]);
                if (!$summary->has_taken_sessions($userid)) {
                    continue;
                }
                $usersummary = $summary->get_taken_sessions_summary_for($userid);
                $totalpoints += (float) $usersummary->takensessionspoints;
                $totalmax += (float) $usersummary->takensessionsmaxpoints;
                $hasany = true;
            }
        }

        if (!$hasany || $totalmax <= 0) {
            return null;
        }
        return ($totalpoints / $totalmax) * 100;
    }

    /**
     * URL for the "attendance" KPI card's click-through: mod_attendance's own
     * view.php?mode=1 ("Todos los cursos" tab) aggregates every attendance
     * instance the viewing user is enrolled in regardless of which single
     * instance's cmid the URL is built from (verified against mod_attendance's
     * own report — the mode=1 branch is student-scoped, not instance-scoped),
     * so any one real cmid the student has access to is a valid entry point.
     * Returns null when mod_attendance isn't installed or the student has no
     * attendance activity in any enrolled course, so the KPI card degrades to
     * non-clickable rather than linking somewhere broken.
     *
     * @param int[] $courseids
     * @return string|null
     */
    private static function find_any_attendance_url(array $courseids): ?string {
        global $CFG;
        if (empty($courseids) || !file_exists($CFG->dirroot . '/mod/attendance/version.php')) {
            return null;
        }
        require_once($CFG->dirroot . '/mod/attendance/locallib.php');

        foreach ($courseids as $courseid) {
            $course = get_course($courseid);
            foreach (get_all_instances_in_course('attendance', $course) as $instance) {
                return (new moodle_url('/mod/attendance/view.php', [
                    'id' => $instance->coursemodule,
                    'mode' => 1,
                ]))->out(false);
            }
        }
        return null;
    }

    /**
     * Submitted-vs-total assignment count across $courseids: every visible
     * assignment with a real due date (undated assignments aren't "tasks"
     * on a timeline, so they're excluded rather than counted as always-
     * pending) versus how many of those $userid has actually submitted.
     * Returns null when there are no such assignments anywhere, so the KPI
     * degrades to "N/D" instead of a fabricated 0/0.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return array{completed:int, total:int, percent:int}|null
     */
    private static function compute_task_completion(int $userid, array $courseids): ?array {
        global $DB;

        if (empty($courseids)) {
            return null;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');

        $totalsql = "SELECT COUNT(1)
                       FROM {assign} a
                       JOIN {course_modules} cm ON cm.instance = a.id
                       JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                      WHERE a.course $insql AND cm.visible = 1 AND a.duedate > 0";
        $total = (int) $DB->count_records_sql($totalsql, $inparams);

        if ($total === 0) {
            return null;
        }

        $completedsql = "SELECT COUNT(1)
                            FROM {assign} a
                            JOIN {course_modules} cm ON cm.instance = a.id
                            JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                            JOIN {assign_submission} s ON s.assignment = a.id
                                 AND s.userid = :userid AND s.latest = 1 AND s.status = 'submitted'
                           WHERE a.course $insql AND cm.visible = 1 AND a.duedate > 0";
        $completed = (int) $DB->count_records_sql($completedsql, array_merge($inparams, ['userid' => $userid]));

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => (int) round(($completed / $total) * 100),
        ];
    }

    /**
     * Exports one enrolled course into a completion-progress card context.
     *
     * @param stdClass $course
     * @param int $userid
     * @return array
     */
    private static function export_course_progress_card(stdClass $course, int $userid): array {
        $courseimage = \theme_saec\course_helper::get_course_image_url($course);

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
        $context = context_course::instance($course->id);
        $instructor = instructor_resolver::resolve((int) $course->id, $context);

        return [
            'id' => (int) $course->id,
            'fullname' => format_string($course->fullname, true, ['context' => $context]),
            'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'boletaurl' => (new moodle_url('/grade/report/user/index.php', ['id' => $course->id]))->out(false),
            'hasinstructor' => ($instructor !== null),
            'instructorname' => $instructor['name'] ?? null,
            'instructoravatarurl' => $instructor['avatarurl'] ?? null,
            'hascourseimage' => !empty($courseimage),
            'courseimage' => $courseimage,
            'hascompletiondata' => $hascompletiondata,
            'progresspercent' => $progresspercent !== null ? (int) round($progresspercent) : 0,
            'progresslabel' => $hascompletiondata
                ? get_string('courseprogresslabel', 'theme_saec', (object) [
                    'completed' => $completedmodules,
                    'total' => $totalmodules,
                ])
                : null,
            'completedmodules' => $completedmodules,
            'totalmodules' => $totalmodules,
        ];
    }

    /**
     * Exports one calendar due-date event into the "Próximas Entregas" tile
     * shape (day tag, day number, time, urgency note).
     *
     * @param stdClass $event Raw {event} row from calendar_get_events().
     * @param stdClass $course
     * @return array
     */
    private static function export_deadline(stdClass $event, stdClass $course): array {
        $now = time();
        $sameday = (userdate($event->timestart, '%Y%m%d') === userdate($now, '%Y%m%d'));

        $daytag = $sameday
            ? \core_text::strtoupper(get_string('deadlinetoday', 'theme_saec'))
            : \core_text::strtoupper(userdate($event->timestart, '%a'));

        $hoursremaining = $sameday ? max(0, (int) round(($event->timestart - $now) / HOURSECS)) : null;

        // 1-click submit shortcut: only for assignments (a quiz's "submission"
        // is taking the attempt itself, which isn't a single safe click to
        // send someone straight into from a dashboard tile) and only when the
        // course module can actually be resolved.
        $submiturl = null;
        if ($event->modulename === 'assign') {
            $cm = get_coursemodule_from_instance('assign', $event->instance, $course->id, false, IGNORE_MISSING);
            if ($cm) {
                $submiturl = (new moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false);
            }
        }

        return [
            'title' => format_string($event->name),
            'coursename' => format_string($course->shortname),
            'daytag' => $daytag,
            'daynumber' => (int) userdate($event->timestart, '%d'),
            'time' => userdate($event->timestart, get_string('strftimetime', 'langconfig')),
            'isurgent' => $sameday,
            'hasclosingnote' => $sameday,
            'closingnote' => $sameday ? get_string('deadlineclosingin', 'theme_saec', $hoursremaining) : null,
            'modulename' => $event->modulename,
            'hassubmiturl' => ($submiturl !== null),
            'submiturl' => $submiturl,
        ];
    }

    /**
     * Resolves the public image URL for a badges_get_user_badges() row.
     * Context follows badge::get_context(): system for site badges, the
     * owning course for course badges.
     *
     * @param stdClass $record Row from badges_get_user_badges() (badge.* + bi.* + u.email).
     * @return moodle_url
     */
    private static function resolve_badge_image_url(stdClass $record): moodle_url {
        $context = ((int) $record->type === BADGE_TYPE_SITE)
            ? context_system::instance()
            : context_course::instance($record->courseid);

        return moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $record->id, '/', 'f1', false);
    }
}
