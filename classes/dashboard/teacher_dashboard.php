<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use context_course;
use context_system;
use moodle_url;
use stdClass;
use user_picture;

/**
 * Backend data preparation for the UPTex Teacher Dashboard (/my/), Sprint 1.
 *
 * Mirrors student_dashboard's shape: every public get_teacher_* method
 * returns a self-contained, Mustache-ready context fragment, and
 * get_dashboard_context() merges them for templates/teacher_dashboard.mustache.
 * Rendered into layout/drawers.php's page-content region (see
 * templates/drawers.mustache, showteacherdashboard branch) only when the
 * logged-in user is a teacher — students/admins keep seeing their own
 * dashboards untouched.
 *
 * Only real Moodle data is ever returned; when a data source is unavailable
 * (no assignments, no announcements forum, etc.) the affected section
 * degrades to an empty-state flag instead of being fabricated.
 */
class teacher_dashboard {

    /** @var int Max rows in the "Por Calificar" pending-grading table. */
    const MAX_PENDING_GRADING = 8;

    /** @var int Max upcoming deadlines returned to the sidebar widget. */
    const MAX_DEADLINES = 5;

    /** @var int Max institutional announcements returned to the sidebar widget. */
    const MAX_ANNOUNCEMENTS = 3;

    /** @var bool Guards the require_once calls in bootstrap() against repeated file includes. */
    private static bool $bootstrapped = false;

    /** @var array<int, array> Per-request memo for get_dashboard_context(), keyed by userid. */
    private static array $contextmemo = [];

    /**
     * Requires the optional core subsystem libraries this class depends on
     * (gradebook, calendar, forum) that theme code cannot rely on being
     * loaded already.
     */
    private static function bootstrap(): void {
        if (self::$bootstrapped) {
            return;
        }
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/calendar/lib.php');
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        self::$bootstrapped = true;
    }

    /**
     * True when $userid is a logged-in, non-guest, non-admin user with
     * moodle/course:update in at least one of their enrolled courses.
     * Mirrors the exact role-detection convention already used by
     * layout/drawers.php's own $is_teacher computation, kept here too so
     * this class is safe to call standalone (same pattern as
     * student_dashboard::is_student()).
     *
     * @param int $userid 0 for the current user.
     * @return bool
     */
    public static function is_teacher(int $userid = 0): bool {
        global $USER;
        $userid = $userid ?: (int) $USER->id;

        if (!isloggedin() || isguestuser($userid)) {
            return false;
        }
        if (is_siteadmin($userid)) {
            return false;
        }

        foreach (self::get_taught_courseids($userid) as $courseid) {
            if (has_capability('moodle/course:update', context_course::instance($courseid), $userid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Header/greeting data: identity, avatar, and course count.
     *
     * @param int $userid 0 for the current user.
     * @return array
     */
    public static function get_teacher_header_data(int $userid = 0): array {
        global $USER, $PAGE;
        $userid = $userid ?: (int) $USER->id;
        $user = ($userid === (int) $USER->id) ? $USER : \core_user::get_user($userid, '*', IGNORE_MISSING);

        if (!$user) {
            return ['fullname' => '', 'avatarurl' => null, 'greeting' => ''];
        }

        $userpicture = new user_picture($user);
        $userpicture->size = 100;

        return [
            'fullname' => fullname($user),
            'avatarurl' => \theme_saec\course_helper::to_relative_url($userpicture->get_url($PAGE)->out(false)),
            'greeting' => get_string('teacherdashwelcome', 'theme_saec', fullname($user)),
        ];
    }

    /**
     * KPI summary: active (visible) courses taught — shaped as a
     * theme_saec/components/metric_card context, the shared component also
     * used by the Student Dashboard and Calificador/Asistencia/Rendimiento
     * pages (left untouched so those keep rendering exactly as before).
     *
     * @param int $userid 0 for the current user.
     * @return array{activecourseskpi: array}
     */
    public static function get_teacher_kpis(int $userid = 0): array {
        global $USER, $OUTPUT;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;

        $courseids = self::get_taught_courseids($userid);
        $activecourses = self::count_active_courses($courseids);

        $icon = fn (string $pix) => $OUTPUT->pix_icon($pix, '', 'moodle', ['class' => 'saec-kpi-card__icon-img']);

        return [
            'activecourseskpi' => [
                'key' => 'activecourses',
                'icon' => $icon('i/course'),
                'iconvariant' => 'primary',
                'label' => get_string('kpiactivecourses', 'theme_saec'),
                'value' => (string) $activecourses,
                'hasvaluesuffix' => false,
                'valuesuffix' => null,
                'hasfootnote' => false,
                'footnote' => null,
            ],
        ];
    }

    /**
     * Pending-submissions KPI + grading-efficiency rate, shaped for the
     * bespoke theme_saec/components/teacher_grading_kpi_card partial (NOT
     * the shared metric_card — that component has no progress-bar slot and
     * is reused verbatim by the Student Dashboard, so it is left alone
     * rather than extended). Efficiency = graded / total-submitted across
     * every course this teacher can update; null (no bar shown) when
     * nothing has been submitted yet anywhere, since a 0-of-0 rate isn't a
     * real measurement.
     *
     * @param int $userid 0 for the current user.
     * @return array{gradingkpi: array}
     */
    public static function get_teacher_grading_kpi(int $userid = 0): array {
        global $USER, $OUTPUT;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;

        $courseids = self::get_taught_courseids($userid);
        $pendingcount = count(self::fetch_pending_grading($userid, $courseids));
        $totalsubmitted = self::fetch_total_submitted_count($userid, $courseids);

        $hasefficiency = $totalsubmitted > 0;
        $efficiencypercent = $hasefficiency
            ? (int) round((($totalsubmitted - $pendingcount) / $totalsubmitted) * 100)
            : null;

        $icon = $OUTPUT->pix_icon('i/scheduled', '', 'moodle', ['class' => 'saec-kpi-card__icon-img']);

        return [
            'gradingkpi' => [
                'icon' => $icon,
                'label' => get_string('kpipendingsubmissions', 'theme_saec'),
                'value' => (string) $pendingcount,
                'hasefficiency' => $hasefficiency,
                'efficiencypercent' => $efficiencypercent,
                'efficiencylabel' => $hasefficiency
                    ? get_string('gradingefficiencylabel', 'theme_saec', $efficiencypercent)
                    : null,
            ],
        ];
    }

    /**
     * Nearest mod_attendance session (in progress, or the next upcoming one)
     * across every course this teacher teaches — backs the welcome hero's
     * "Próxima clase" micro-badge and its "Iniciar Pase de Lista" quick
     * action. Returns hasnextclass=false when mod_attendance isn't
     * installed, no course has a session module, or the teacher lacks
     * mod/attendance:takeattendances on the one session found (a stray
     * link to a page they'd just get a permission error on is worse than no
     * badge at all).
     *
     * No room/location field exists anywhere in mod_attendance's schema, so
     * unlike the design mockup this never fabricates one — only real
     * course + time data is shown.
     *
     * @param int $userid 0 for the current user.
     * @return array{hasnextclass: bool, nextclass?: array}
     */
    public static function get_teacher_next_class(int $userid = 0): array {
        global $USER, $CFG;
        $userid = $userid ?: (int) $USER->id;

        if (!file_exists($CFG->dirroot . '/mod/attendance/version.php')) {
            return ['hasnextclass' => false];
        }

        $courseids = self::get_taught_courseids($userid);
        $session = self::fetch_next_attendance_session($userid, $courseids);
        if ($session === null) {
            return ['hasnextclass' => false];
        }

        $modcontext = \context_module::instance($session->cmid);
        if (!has_capability('mod/attendance:takeattendances', $modcontext, $userid)) {
            return ['hasnextclass' => false];
        }

        $course = get_course($session->courseid);
        $now = time();
        $inprogress = ($session->sessdate <= $now) && ($now < ($session->sessdate + $session->duration));
        $sameday = (userdate($session->sessdate, '%Y%m%d') === userdate($now, '%Y%m%d'));

        $daylabel = $sameday
            ? get_string('deadlinetoday', 'theme_saec')
            : userdate($session->sessdate, get_string('strftimedateshort', 'langconfig'));

        return [
            'hasnextclass' => true,
            'nextclass' => [
                'coursename' => format_string($course->shortname),
                'time' => userdate($session->sessdate, get_string('strftimetime', 'langconfig')),
                'daylabel' => $daylabel,
                'inprogress' => $inprogress,
                'takeattendanceurl' => (new moodle_url('/mod/attendance/take.php', [
                    'id' => $session->cmid,
                    'sessionid' => $session->id,
                    'grouptype' => \mod_attendance_structure::SESSION_COMMON,
                ]))->out(false),
            ],
        ];
    }

    /**
     * "Por Calificar" table: submissions awaiting grading across every
     * course this teacher can update, oldest first (most overdue on top).
     *
     * @param int $userid 0 for the current user.
     * @param int $limit
     * @return array{haspendinggrading: bool, pendinggrading: array[]}
     */
    public static function get_teacher_pending_grading(int $userid = 0, int $limit = self::MAX_PENDING_GRADING): array {
        global $USER;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;

        $courseids = self::get_taught_courseids($userid);
        $rows = array_slice(self::fetch_pending_grading($userid, $courseids), 0, $limit);

        $coursecache = [];
        $items = [];
        foreach ($rows as $row) {
            if (!isset($coursecache[$row->courseid])) {
                $coursecache[$row->courseid] = get_course($row->courseid);
            }
            $items[] = self::export_pending_grading_row($row, $coursecache[$row->courseid]);
        }

        return ['haspendinggrading' => !empty($items), 'pendinggrading' => $items];
    }

    /**
     * Upcoming assignment/quiz due dates across every course this teacher
     * teaches, formatted for theme_saec/components/deadline_item.
     *
     * @param int $userid 0 for the current user.
     * @param int $limit
     * @return array{hasdeadlines: bool, deadlines: array[]}
     */
    public static function get_teacher_upcoming_deadlines(int $userid = 0, int $limit = self::MAX_DEADLINES): array {
        global $USER;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;

        $courseids = self::get_taught_courseids($userid);
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
     * Recent posts from the site's own "news"/announcements forum — the
     * institutional-wide channel, as opposed to course_view_page's
     * per-course announcements widget.
     *
     * @param int $limit
     * @return array{hasannouncements: bool, announcements: array[]}
     */
    public static function get_institutional_announcements(int $limit = self::MAX_ANNOUNCEMENTS): array {
        global $DB, $SITE;
        self::bootstrap();

        $forum = $DB->get_record('forum', ['course' => $SITE->id, 'type' => 'news'], '*', IGNORE_MULTIPLE);
        if (!$forum) {
            return ['hasannouncements' => false, 'announcements' => []];
        }

        $sql = "SELECT d.id, d.name, p.message, p.modified
                  FROM {forum_discussions} d
                  JOIN {forum_posts} p ON p.discussion = d.id AND p.parent = 0
                 WHERE d.forum = :forumid
              ORDER BY p.modified DESC";
        $records = $DB->get_records_sql($sql, ['forumid' => $forum->id], 0, $limit);

        $dateformat = get_string('strftimedatefullshort', 'langconfig');
        $items = [];
        foreach ($records as $record) {
            $snippet = trim(strip_tags($record->message));
            if (\core_text::strlen($snippet) > 140) {
                $snippet = \core_text::substr($snippet, 0, 140) . '…';
            }
            $items[] = [
                'title' => format_string($record->name),
                'snippet' => $snippet,
                'date' => userdate((int) $record->modified, $dateformat),
                'url' => (new moodle_url('/mod/forum/discuss.php', ['d' => $record->id]))->out(false),
            ];
        }

        return ['hasannouncements' => !empty($items), 'announcements' => $items];
    }

    /**
     * Static targets for the two 1-click quick-action bar buttons (Grader
     * Hub, Attendance Hub) — both native Moodle URLs whose page content
     * theme_saec overlays entirely (see layout/drawers.php's
     * $isanalyticspage/$graderhubpagehtml and $attendancehubpagehtml
     * blocks), not standalone theme_saec/pages/*.php controllers.
     *
     * @return array{graderhuburl: string, attendancehuburl: string}
     */
    public static function get_teacher_quickaction_links(): array {
        return [
            'graderhuburl' => (new moodle_url('/grade/report/overview/index.php'))->out(false),
            'attendancehuburl' => (new moodle_url('/theme/saec/pages/attendance_hub.php'))->out(false),
        ];
    }

    /**
     * Course lists backing the two 2-click course-picker modals (+ Nueva
     * Tarea, + Nuevo Aviso) — supersedes the old single-click "post to the
     * first course's forum" shortcut (get_teacher_quick_announcement(), now
     * removed) with a real choice across every course the teacher can
     * actually manage activities in, matching
     * theme_saec/components/course_picker_modal's expected shape.
     *
     * Each list is independently filtered:
     * - taskcourses: every visible taught course where the teacher holds
     *   moodle/course:manageactivities (the exact capability
     *   course/modedit.php itself requires to add a module) — its
     *   courseurl opens the assignment-creation form directly.
     * - noticecourses: the same, further narrowed to courses that actually
     *   have a news/announcements forum — its courseurl opens that forum's
     *   add-discussion editor directly. A course without a news forum is
     *   simply absent from this list rather than linking somewhere broken.
     *
     * @param int $userid 0 for the current user.
     * @return array{taskpicker: array, noticepicker: array}
     */
    public static function get_teacher_course_pickers(int $userid = 0): array {
        global $USER;
        $userid = $userid ?: (int) $USER->id;

        $taskcourses = [];
        $noticecourses = [];

        foreach (self::get_taught_courseids($userid) as $courseid) {
            $course = get_course($courseid);
            if (empty($course->visible)) {
                continue;
            }

            $context = context_course::instance($courseid);
            if (!has_capability('moodle/course:manageactivities', $context, $userid)) {
                continue;
            }

            $fullname = format_string($course->fullname, true, ['context' => $context]);
            $shortname = format_string($course->shortname, true, ['context' => $context]);

            $taskcourses[] = [
                'id' => $courseid,
                'fullname' => $fullname,
                'shortname' => $shortname,
                'courseurl' => (new moodle_url('/course/modedit.php', [
                    'add' => 'assign',
                    'section' => 0,
                    'course' => $courseid,
                    'return' => 0,
                ]))->out(false),
            ];

            $forum = self::fetch_course_news_forum($courseid);
            if ($forum) {
                $noticecourses[] = [
                    'id' => $courseid,
                    'fullname' => $fullname,
                    'shortname' => $shortname,
                    'courseurl' => (new moodle_url('/mod/forum/post.php', ['forum' => $forum->id]))->out(false),
                ];
            }
        }

        return [
            'taskpicker' => [
                'modalid' => 'modalSelectCourseTask',
                'modaltitle' => get_string('taskpickertitle', 'theme_saec'),
                'haslistcourses' => !empty($taskcourses),
                'courses' => $taskcourses,
            ],
            'noticepicker' => [
                'modalid' => 'modalSelectCourseNotice',
                'modaltitle' => get_string('noticepickertitle', 'theme_saec'),
                'haslistcourses' => !empty($noticecourses),
                'courses' => $noticecourses,
            ],
        ];
    }

    /**
     * The course's "news"/announcements forum record, if one exists. Same
     * lookup as course_view_page::get_news_forum(), kept as its own small
     * copy here since that method is private to its own class and this one
     * only ever needs the bare forum id — not worth sharing a cache for a
     * handful-of-courses-per-request lookup.
     *
     * @param int $courseid
     * @return stdClass|null
     */
    private static function fetch_course_news_forum(int $courseid): ?stdClass {
        global $DB;
        return $DB->get_record('forum', ['course' => $courseid, 'type' => 'news'], '*', IGNORE_MULTIPLE) ?: null;
    }

    /**
     * Unified context for templates/teacher_dashboard.mustache. Returns null
     * when the logged-in user is not a teacher (student/admin dashboards are
     * out of scope for this context builder). Per-request memoized since
     * layout/drawers.php only calls this once per page load, but repeat
     * calls (e.g. from a future block) shouldn't repeat the underlying
     * queries within the same request.
     *
     * @param int $userid 0 for the current user.
     * @return array|null
     */
    public static function get_dashboard_context(int $userid = 0): ?array {
        global $USER;
        $userid = $userid ?: (int) $USER->id;

        if (array_key_exists($userid, self::$contextmemo)) {
            return self::$contextmemo[$userid] ?: null;
        }

        if (!self::is_teacher($userid)) {
            self::$contextmemo[$userid] = null;
            return null;
        }

        $context = array_merge(
            self::get_teacher_header_data($userid),
            self::get_teacher_next_class($userid),
            self::get_teacher_kpis($userid),
            self::get_teacher_grading_kpi($userid),
            self::get_teacher_pending_grading($userid),
            self::get_teacher_upcoming_deadlines($userid),
            self::get_institutional_announcements(),
            self::get_teacher_quickaction_links(),
            self::get_teacher_course_pickers($userid)
        );

        self::$contextmemo[$userid] = $context;
        return $context;
    }

    /**
     * Course ids where $userid is actively enrolled, MUC-cached (see
     * db/caches.php, 'teachercourses' definition) since it backs
     * is_teacher(), get_teacher_kpis(), get_teacher_pending_grading() and
     * get_teacher_upcoming_deadlines() — all called on the same /my/
     * request — and a teacher's own course list changes about as rarely as
     * the per-course instructor lookup instructor_resolver already caches.
     *
     * Public: also reused by teacher_courses_page::get_context() so the /my/
     * dashboard and /my/courses.php read the exact same cached course list
     * on a typical two-page teacher session instead of computing it twice.
     *
     * @param int $userid
     * @return int[]
     */
    public static function get_taught_courseids(int $userid): array {
        $cache = \cache::make('theme_saec', 'teachercourses');
        $cachekey = (string) $userid;
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $courseids = array_keys(enrol_get_all_users_courses($userid, true, ['id']));
        $cache->set($cachekey, $courseids);
        return $courseids;
    }

    /**
     * Raw "needs grading" submission rows across $courseids: the latest
     * submission per student is newer than its latest recorded grade (or
     * was never graded at all). MUC-cached (see db/caches.php,
     * 'pendinggrading' definition) — this query fans out across every
     * course a teacher owns on every /my/ load, and a short TTL is enough
     * to keep the KPI count and the "Por Calificar" table in sync with each
     * other within one cache generation without re-running the join twice
     * per request.
     *
     * @param int $userid Cache-scoping key only; the query itself is
     *                     course-scoped, not submitter-scoped.
     * @param int[] $courseids
     * @return stdClass[] Rows: id, studentid, assignmentid, cmid, courseid,
     *                     name, duedate, timemodified.
     */
    private static function fetch_pending_grading(int $userid, array $courseids): array {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        $cache = \cache::make('theme_saec', 'pendinggrading');
        $cachekey = (string) $userid;
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');

        $sql = "SELECT s.id, s.userid AS studentid, a.id AS assignmentid, cm.id AS cmid, a.course AS courseid,
                       a.name, a.duedate, s.timemodified
                  FROM {assign_submission} s
                  JOIN {assign} a ON a.id = s.assignment
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
             LEFT JOIN {assign_grades} g ON g.assignment = a.id AND g.userid = s.userid
                        AND g.attemptnumber = s.attemptnumber
                 WHERE a.course $insql
                       AND cm.visible = 1
                       AND s.latest = 1
                       AND s.status = 'submitted'
                       AND (g.id IS NULL OR g.timemodified < s.timemodified)
              ORDER BY s.timemodified ASC";

        $rows = array_values($DB->get_records_sql($sql, $inparams));
        $cache->set($cachekey, $rows);
        return $rows;
    }

    /**
     * Total submitted assignment count across $courseids, regardless of
     * grading state — the denominator for get_teacher_grading_kpi()'s
     * efficiency rate. MUC-cached (see db/caches.php, 'pendinggrading'
     * definition — shared with fetch_pending_grading() under a distinct key
     * prefix since both are read on the same request and have the same
     * volatility/TTL rationale).
     *
     * @param int $userid
     * @param int[] $courseids
     * @return int
     */
    private static function fetch_total_submitted_count(int $userid, array $courseids): int {
        global $DB;

        if (empty($courseids)) {
            return 0;
        }

        $cache = \cache::make('theme_saec', 'pendinggrading');
        $cachekey = 'total_' . $userid;
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');

        $sql = "SELECT COUNT(1)
                  FROM {assign_submission} s
                  JOIN {assign} a ON a.id = s.assignment
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                 WHERE a.course $insql
                       AND cm.visible = 1
                       AND s.latest = 1
                       AND s.status = 'submitted'";

        $count = (int) $DB->count_records_sql($sql, $inparams);
        $cache->set($cachekey, $count);
        return $count;
    }

    /**
     * Nearest mod_attendance session across $courseids that hasn't finished
     * yet (in progress or upcoming), earliest first. MUC-cached (see
     * db/caches.php, 'nextclass' definition) with a short TTL — a session
     * flips from "upcoming" to "in progress" to "finished" purely by wall
     * clock, so this can't be cached as long as the course-list lookups
     * above without the badge visibly lagging reality.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return stdClass|null Row: id, sessdate, duration, courseid, cmid.
     */
    private static function fetch_next_attendance_session(int $userid, array $courseids): ?stdClass {
        global $DB;

        if (empty($courseids)) {
            return null;
        }

        $cache = \cache::make('theme_saec', 'nextclass');
        $cachekey = (string) $userid;
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached ?: null;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $params = array_merge($inparams, ['now' => time()]);

        $sql = "SELECT s.id, s.sessdate, s.duration, a.course AS courseid, cm.id AS cmid
                  FROM {attendance_sessions} s
                  JOIN {attendance} a ON a.id = s.attendanceid
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'attendance'
                 WHERE a.course $insql
                       AND cm.visible = 1
                       AND (s.sessdate + s.duration) >= :now
              ORDER BY s.sessdate ASC";

        $rows = $DB->get_records_sql($sql, $params, 0, 1);
        $session = $rows ? reset($rows) : null;
        $cache->set($cachekey, $session ?? []);
        return $session;
    }

    /**
     * Number of $courseids that are still visible — i.e. "active" from the
     * teacher's point of view, mirroring the visibility filter
     * student_dashboard::get_student_courses_progress() already applies to
     * a student's own course cards.
     *
     * @param int[] $courseids
     * @return int
     */
    private static function count_active_courses(array $courseids): int {
        if (empty($courseids)) {
            return 0;
        }
        $count = 0;
        foreach ($courseids as $courseid) {
            $course = get_course($courseid);
            if (!empty($course->visible)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Exports one "needs grading" row into the pending-grading table shape.
     *
     * @param stdClass $row One row from fetch_pending_grading().
     * @param stdClass $course
     * @return array
     */
    private static function export_pending_grading_row(stdClass $row, stdClass $course): array {
        $student = \core_user::get_user($row->studentid, '*', IGNORE_MISSING);
        $studentname = $student ? fullname($student) : get_string('unknownuser', 'moodle');

        $isoverdue = ((int) $row->duedate > 0) && ((int) $row->timemodified > (int) $row->duedate);

        return [
            'studentname' => $studentname,
            'assignmentname' => format_string($row->name),
            'coursename' => format_string($course->shortname),
            'submittedon' => userdate((int) $row->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
            'isoverdue' => $isoverdue,
            'statuslabel' => $isoverdue
                ? get_string('statuslate', 'theme_saec')
                : get_string('statusontime', 'theme_saec'),
            'gradeurl' => (new moodle_url('/mod/assign/view.php', [
                'id' => $row->cmid,
                'action' => 'grading',
            ]))->out(false),
        ];
    }

    /**
     * Exports one calendar due-date event into the deadline_item tile shape
     * — same layout as student_dashboard::export_deadline().
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
        ];
    }
}
