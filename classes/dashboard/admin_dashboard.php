<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use context_course;
use moodle_url;
use user_picture;

/**
 * Backend data preparation for the Admin Executive Command Center, injected
 * into /my/ (layout/drawers.php) — the same "replace the dashboard slot for
 * this role" pattern student_dashboard/teacher_dashboard already use.
 *
 * Split out of admin_hub_page (which now holds only the Site Administration
 * settings index at theme/saec/pages/admin_hub.php) so the two jobs — "run
 * the school day-to-day" vs. "configure the site" — live on separate pages,
 * exactly like every other role already separates its dashboard from its
 * settings/management pages in this theme.
 */
class admin_dashboard {

    /** @var int Max rows in the Active Courses summary table. */
    const MAX_COURSES = 10;

    /** @var int Max rows in the User Directory summary table. */
    const MAX_USERS = 10;

    /**
     * Unified context for templates/admin_dashboard.mustache. Returns null
     * when the logged-in user is not a site admin (mirrors every other
     * *_dashboard::get_dashboard_context()'s role guard).
     *
     * @param int $userid 0 for the current user.
     * @return array|null
     */
    public static function get_dashboard_context(int $userid = 0): ?array {
        global $USER;
        $userid = $userid ?: (int) $USER->id;

        if (!is_siteadmin($userid)) {
            return null;
        }

        $context = array_merge(
            self::get_header_data($userid),
            ['sesskey' => sesskey()],
            self::get_kpis(),
            ['quickactions' => self::get_quick_actions()],
            self::get_courses_section(),
            self::get_users_section()
        );

        return array_merge($context, self::get_hero_data($context));
    }

    /**
     * Header/greeting data: identity and avatar, mirroring
     * student_dashboard::get_student_header_data()/
     * teacher_dashboard::get_teacher_header_data() so the unified hero
     * (templates/components/hero_banner.mustache) has the same avatarurl
     * shape to work with regardless of role.
     *
     * @param int $userid
     * @return array
     */
    private static function get_header_data(int $userid): array {
        global $PAGE;

        $user = \core_user::get_user($userid, '*', IGNORE_MISSING);
        $fullname = $user ? fullname($user) : '';
        $avatarurl = null;
        if ($user) {
            $userpicture = new user_picture($user);
            $userpicture->size = 100;
            $avatarurl = \theme_saec\course_helper::to_relative_url($userpicture->get_url($PAGE)->out(false));
        }

        return [
            'fullname' => $fullname,
            'avatarurl' => $avatarurl,
            'greeting' => get_string('adminhubgreeting', 'theme_saec', $fullname),
        ];
    }

    /**
     * Unified hero fields (templates/components/hero_banner.mustache):
     * static role title, the existing greeting as the subtitle, an
     * "Operational" status pill carrying the real active-course count
     * already computed by get_kpis() (no second query), and a "Purge
     * Cache" action button that reuses the exact same
     * data-saec-admin-purge-cache / root.find(...) AJAX wiring
     * templates/admin_dashboard.mustache's inline JS already sets up for
     * the quick-actions bar's own purge button — both fire independently,
     * no extra JS needed here.
     *
     * @param array $context the already-merged context (must contain 'kpis' and 'greeting').
     * @return array
     */
    private static function get_hero_data(array $context): array {
        $activecourses = '0';
        foreach ($context['kpis'] ?? [] as $kpi) {
            if (($kpi['key'] ?? '') === 'activecourses') {
                $activecourses = $kpi['value'];
                break;
            }
        }

        return [
            'herotitle' => get_string('admindashheading', 'theme_saec'),
            'herosubtitle' => $context['greeting'],
            'pillicon' => 'fa-check-circle',
            'pilltext' => get_string('adminsystemstatuspill', 'theme_saec', $activecourses),
            'pillvariant' => 'success',
            'actionlabel' => get_string('adminactionpurgecache', 'theme_saec'),
            'actionurl' => null,
            'ispurgeaction' => true,
        ];
    }

    /**
     * The 4 top-row KPI cards, shaped for the shared
     * theme_saec/components/metric_card partial. MUC-cached (see
     * db/caches.php, 'adminkpis' definition) — 4 sitewide COUNT() queries
     * that would otherwise run on every /my/ load for an admin.
     *
     * @return array{kpis: array[]}
     */
    private static function get_kpis(): array {
        global $OUTPUT;

        $cache = \cache::make('theme_saec', 'adminkpis');
        $counts = $cache->get('counts');
        if ($counts === false) {
            $counts = [
                'students' => self::count_users_with_role(['student']),
                'teachers' => self::count_users_with_role(['editingteacher', 'teacher']),
                'courses' => self::count_active_courses(),
                'badges' => self::count_issued_badges(),
            ];
            $cache->set('counts', $counts);
        }

        $icon = fn (string $pix) => $OUTPUT->pix_icon($pix, '', 'moodle', ['class' => 'saec-kpi-card__icon-img']);

        return [
            'kpis' => [
                [
                    'key' => 'activestudents',
                    'icon' => $icon('i/user'),
                    'iconvariant' => 'primary',
                    'label' => get_string('kpiactivestudents', 'theme_saec'),
                    'value' => (string) $counts['students'],
                    'hasvaluesuffix' => false,
                    'hasfootnote' => false,
                    'hasurl' => false,
                ],
                [
                    'key' => 'enrolledteachers',
                    'icon' => $icon('i/user'),
                    'iconvariant' => 'accent',
                    'label' => get_string('kpienrolledteachers', 'theme_saec'),
                    'value' => (string) $counts['teachers'],
                    'hasvaluesuffix' => false,
                    'hasfootnote' => false,
                    'hasurl' => false,
                ],
                [
                    'key' => 'activecourses',
                    'icon' => $icon('i/course'),
                    'iconvariant' => 'primary',
                    'label' => get_string('kpiactivecourses', 'theme_saec'),
                    'value' => (string) $counts['courses'],
                    'hasvaluesuffix' => false,
                    'hasfootnote' => false,
                    'hasurl' => false,
                ],
                [
                    'key' => 'issuedbadges',
                    'icon' => $icon('i/badge'),
                    'iconvariant' => 'accent',
                    'label' => get_string('kpiissuedbadges', 'theme_saec'),
                    'value' => (string) $counts['badges'],
                    'hasvaluesuffix' => false,
                    'hasfootnote' => false,
                    'hasurl' => false,
                ],
            ],
        ];
    }

    /**
     * Distinct, non-deleted, non-suspended users holding at least one of
     * $shortnames in any course context.
     *
     * @param string[] $shortnames
     * @return int
     */
    private static function count_users_with_role(array $shortnames): int {
        global $DB;

        list($insql, $inparams) = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED, 'role');

        $sql = "SELECT COUNT(DISTINCT ra.userid)
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid AND r.shortname $insql
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :contextcourse
                  JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0";
        $params = array_merge($inparams, ['contextcourse' => CONTEXT_COURSE]);

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Visible courses excluding the site course itself.
     *
     * @return int
     */
    private static function count_active_courses(): int {
        global $DB;
        return (int) $DB->count_records_select('course', 'visible = 1 AND id <> :siteid', ['siteid' => SITEID]);
    }

    /**
     * @return int
     */
    private static function count_issued_badges(): int {
        global $DB;
        return (int) $DB->count_records('badge_issued');
    }

    /**
     * Static quick-action bar: the handful of operations the Command Center
     * spec calls out as 1-click destinations. "Purgar Caché" carries no
     * href — it's a data-attribute button wired to ajax/purge_caches.php by
     * templates/admin_dashboard.mustache's inline JS — the other 4 are
     * plain links.
     *
     * @return array[]
     */
    private static function get_quick_actions(): array {
        global $OUTPUT;

        $icon = fn (string $pix) => $OUTPUT->pix_icon($pix, '', 'moodle', ['class' => 'saec-quickaction__icon-img']);

        return [
            [
                'label' => get_string('adminactioncreatecourse', 'theme_saec'),
                'icon' => $icon('i/course'),
                'url' => (new moodle_url('/course/edit.php', ['category' => 1]))->out(false),
                'ispurge' => false,
            ],
            [
                'label' => get_string('adminactionnewuser', 'theme_saec'),
                'icon' => $icon('i/user'),
                'url' => (new moodle_url('/user/editadvanced.php', ['id' => -1]))->out(false),
                'ispurge' => false,
            ],
            [
                'label' => get_string('adminactionbulkupload', 'theme_saec'),
                'icon' => $icon('i/upload'),
                'url' => (new moodle_url('/admin/tool/uploaduser/index.php'))->out(false),
                'ispurge' => false,
            ],
            [
                'label' => get_string('adminactionbadges', 'theme_saec'),
                'icon' => $icon('i/badge'),
                // badges/index.php does required_param('type', PARAM_INT) —
                // no 'type' means an uncaught invalid_parameter_exception,
                // not a graceful native fallback. 1 = BADGE_TYPE_SITE (the
                // constant itself lives in lib/badgeslib.php, not loaded on
                // most pages this renders from — the literal is what the
                // constant would resolve to, not a magic number).
                'url' => (new moodle_url('/badges/index.php', ['type' => 1]))->out(false),
                'ispurge' => false,
            ],
            [
                'label' => get_string('adminactionpurgecache', 'theme_saec'),
                'icon' => $icon('i/reload'),
                'url' => null,
                'ispurge' => true,
            ],
        ];
    }

    /**
     * Active Courses summary table: the most recent visible courses (site
     * course excluded) capped at MAX_COURSES — a "resumen", not the full
     * catalog (that's admin_courses_page, reachable via "Ver todos los
     * cursos" / the Mis Cursos sidebar link). Batched student counts (reuses
     * teacher_courses_page::fetch_student_counts() — same query shape,
     * teacher-scoped there only because its $courseids argument is
     * teacher-scoped, not because the SQL itself is), each row carrying
     * Configurar/Participantes/Calificador/Asistencia shortcuts.
     *
     * @return array{hascourses: bool, courses: array[], coursesmoreurl: string}
     */
    private static function get_courses_section(): array {
        global $DB;

        $courseids = array_map('intval', array_keys($DB->get_records_select(
            'course',
            'visible = 1 AND id <> :siteid',
            ['siteid' => SITEID],
            'fullname ASC',
            'id',
            0,
            self::MAX_COURSES
        )));

        if (empty($courseids)) {
            return ['hascourses' => false, 'courses' => [], 'coursesmoreurl' => self::management_url()];
        }

        $studentcounts = teacher_courses_page::fetch_student_counts($courseids);
        $attendancecourseids = self::fetch_courseids_with_attendance($courseids);
        $categorycache = [];

        $courses = [];
        foreach ($courseids as $courseid) {
            $course = get_course($courseid);
            $context = context_course::instance($courseid);

            $courses[] = [
                'id' => $courseid,
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'categoryname' => teacher_courses_page::resolve_category_name((int) $course->category, $categorycache),
                'studentcount' => $studentcounts[$courseid] ?? 0,
                'hasentercourse' => false,
                'entercourseurl' => null,
                'configureurl' => (new moodle_url('/course/edit.php', ['id' => $courseid]))->out(false),
                'participantsurl' => (new moodle_url('/user/index.php', ['id' => $courseid]))->out(false),
                'gradesurl' => (new moodle_url('/grade/report/index.php', ['id' => $courseid]))->out(false),
                'hasattendance' => in_array($courseid, $attendancecourseids, true),
                'attendanceurl' => in_array($courseid, $attendancecourseids, true)
                    ? (new moodle_url('/mod/attendance/index.php', ['id' => $courseid]))->out(false)
                    : null,
            ];
        }

        return [
            'hascourses' => true,
            'courses' => $courses,
            'coursesmoreurl' => self::management_url(),
        ];
    }

    /**
     * @return string
     */
    private static function management_url(): string {
        return (new moodle_url('/my/courses.php'))->out(false);
    }

    /**
     * Course ids among $courseids that have at least one non-deleted
     * mod_attendance instance — a single batched query rather than one
     * file_exists()-guarded lookup per row. Public: also reused by
     * admin_courses_page (the full Course Catalog), so both admin course
     * tables agree on which courses actually have attendance.
     *
     * @param int[] $courseids
     * @return int[]
     */
    public static function fetch_courseids_with_attendance(array $courseids): array {
        global $CFG, $DB;

        if (!file_exists($CFG->dirroot . '/mod/attendance/version.php') || empty($courseids)) {
            return [];
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $sql = "SELECT DISTINCT cm.course
                  FROM {course_modules} cm
                  JOIN {modules} md ON md.id = cm.module AND md.name = 'attendance'
                 WHERE cm.course $insql AND cm.deletioninprogress = 0";

        return array_map('intval', array_keys($DB->get_records_sql($sql, $inparams)));
    }

    /**
     * User Directory summary table: the most recently created non-deleted,
     * non-guest users, each carrying a real (not fabricated) highest-
     * priority role label derived from their own course-level role
     * assignments — never invented when a user holds no role anywhere yet.
     *
     * @return array{hasusers: bool, users: array[], usersmoreurl: string}
     */
    private static function get_users_section(): array {
        global $CFG, $DB;

        $namefields = implode(', ', \core_user\fields::get_name_fields());
        $records = $DB->get_records_select(
            'user',
            'deleted = 0 AND id <> :guestid',
            ['guestid' => (int) $CFG->siteguest],
            'timecreated DESC',
            "id, $namefields, email, suspended",
            0,
            self::MAX_USERS
        );

        if (empty($records)) {
            return ['hasusers' => false, 'users' => [], 'usersmoreurl' => self::user_list_url()];
        }

        $userids = array_map('intval', array_keys($records));
        $rolelabels = self::fetch_role_labels($userids);

        $users = [];
        foreach ($records as $record) {
            $userid = (int) $record->id;
            $users[] = [
                'id' => $userid,
                'fullname' => fullname($record),
                'email' => $record->email,
                'rolelabel' => $rolelabels[$userid] ?? get_string('adminrolenone', 'theme_saec'),
                'issuspended' => (bool) $record->suspended,
                'statuslabel' => $record->suspended
                    ? get_string('adminuserstatussuspended', 'theme_saec')
                    : get_string('adminuserstatusactive', 'theme_saec'),
                'editurl' => (new moodle_url('/user/editadvanced.php', ['id' => $userid]))->out(false),
                'rolesurl' => (new moodle_url('/admin/roles/usersroles.php', [
                    'userid' => $userid,
                    'courseid' => SITEID,
                ]))->out(false),
            ];
        }

        return [
            'hasusers' => true,
            'users' => $users,
            'usersmoreurl' => self::user_list_url(),
        ];
    }

    /**
     * @return string
     */
    private static function user_list_url(): string {
        return (new moodle_url('/admin/user.php'))->out(false);
    }

    /**
     * One display role label per user id — the highest-priority course-level
     * role each holds (Docente > Estudiante), omitted entirely for users
     * with neither (get_users_section() falls back to "adminrolenone" for
     * those, honest rather than a guessed default).
     *
     * @param int[] $userids
     * @return array<int, string>
     */
    private static function fetch_role_labels(array $userids): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $sql = "SELECT DISTINCT ra.userid, r.shortname
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('editingteacher', 'teacher', 'student')
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :contextcourse
                 WHERE ra.userid $insql";
        $params = array_merge($inparams, ['contextcourse' => CONTEXT_COURSE]);

        $byuser = [];
        $rows = $DB->get_recordset_sql($sql, $params);
        foreach ($rows as $row) {
            $byuser[(int) $row->userid][] = $row->shortname;
        }
        $rows->close();

        $labels = [];
        foreach ($byuser as $userid => $shortnames) {
            if (in_array('editingteacher', $shortnames, true) || in_array('teacher', $shortnames, true)) {
                $labels[$userid] = get_string('adminroleteacher', 'theme_saec');
            } else if (in_array('student', $shortnames, true)) {
                $labels[$userid] = get_string('adminrolestudent', 'theme_saec');
            }
        }
        return $labels;
    }
}
