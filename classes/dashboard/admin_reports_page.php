<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use core\context_helper;
use moodle_url;

/**
 * "Reportes y Auditoría" (platform-wide admin audit dashboard) — replaces
 * /report/log/index.php's bare native log table for site admins. The
 * native report still renders (core, can't be skipped — see the
 * placeholder-token comment in templates/drawers.mustache) and is hidden
 * via scss/saec/_admin_reports.scss's body.page-admin-reports rule,
 * exactly like every other *_page overlay in this theme.
 *
 * Every KPI/trend/table row here is computed live from real site data
 * ({user}.lastaccess, {grade_grades}/{grade_items}, {badge_issued},
 * {logstore_standard_log}) — a metric with no underlying data degrades to
 * null/"N/D" rather than a fabricated number.
 */
class admin_reports_page {

    /** @var int Days of daily activity shown in the trend bar chart. */
    const TREND_DAYS = 7;

    /** @var int Rows shown in the recent audit trail table. */
    const AUDIT_TRAIL_LIMIT = 15;

    /**
     * Unified context for templates/admin_reports_page.mustache. Returns
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
            self::get_kpis(),
            self::get_activity_trend(),
            self::get_audit_trail(),
            self::get_export_links()
        );
    }

    /**
     * The 4 top-row KPIs, shaped for theme_saec/components/metric_card.
     *
     * @return array{kpis: array[]}
     */
    private static function get_kpis(): array {
        global $DB, $OUTPUT;

        $icon = fn (string $pix) => $OUTPUT->pix_icon($pix, '', 'moodle', ['class' => 'saec-kpi-card__icon-img']);
        $nodata = get_string('kpinodata', 'theme_saec');

        $activeusers = self::count_active_users_last_days(7);
        $globalaverage = self::compute_global_average_grade();
        $badgecount = (int) $DB->count_records('badge_issued');
        $todayevents = self::count_events_since(usergetmidnight(time()));

        return [
            'kpis' => [
                [
                    'key' => 'activeusers',
                    'icon' => $icon('i/user'),
                    'iconvariant' => 'primary',
                    'label' => get_string('adminreportskpiactiveusers', 'theme_saec'),
                    'value' => (string) $activeusers,
                    'hasvaluesuffix' => false,
                    'hasfootnote' => false,
                ],
                [
                    'key' => 'globalaverage',
                    'icon' => $icon('i/grades'),
                    'iconvariant' => 'accent',
                    'label' => get_string('adminreportskpiglobalaverage', 'theme_saec'),
                    'value' => $globalaverage !== null ? number_format($globalaverage, 1) : $nodata,
                    'hasvaluesuffix' => ($globalaverage !== null),
                    'valuesuffix' => '/ 10',
                    'hasfootnote' => false,
                ],
                [
                    'key' => 'badges',
                    'icon' => $icon('i/badge'),
                    'iconvariant' => 'primary',
                    'label' => get_string('adminreportskpibadges', 'theme_saec'),
                    'value' => (string) $badgecount,
                    'hasvaluesuffix' => false,
                    'hasfootnote' => false,
                ],
                [
                    'key' => 'todayevents',
                    'icon' => $icon('i/report'),
                    'iconvariant' => 'accent',
                    'label' => get_string('adminreportskpitodayevents', 'theme_saec'),
                    'value' => (string) $todayevents,
                    'hasvaluesuffix' => false,
                    'hasfootnote' => false,
                ],
            ],
        ];
    }

    /**
     * Distinct non-deleted, non-suspended users with a recorded access in
     * the last $days days ({user}.lastaccess — updated on every logged-in
     * request, the same signal core's own "active users" admin reports
     * rely on).
     *
     * @param int $days
     * @return int
     */
    private static function count_active_users_last_days(int $days): int {
        global $DB;

        return (int) $DB->count_records_select(
            'user',
            'deleted = 0 AND suspended = 0 AND lastaccess > 0 AND lastaccess >= :since',
            ['since' => time() - ($days * DAYSECS)]
        );
    }

    /**
     * Grand mean of every individual student's course-total grade
     * (normalized 0-10), across every visible course except the site
     * course — a single aggregate SQL pass rather than one query per
     * course, since this is a sitewide figure with no per-course
     * breakdown to preserve.
     *
     * @return float|null null when no course has any recorded final grade yet.
     */
    private static function compute_global_average_grade(): ?float {
        global $DB;

        $sql = "SELECT AVG((gg.finalgrade - gi.grademin) / (gi.grademax - gi.grademin) * 10)
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'course'
                  JOIN {course} c ON c.id = gi.courseid AND c.visible = 1 AND c.id <> :siteid
                 WHERE gg.finalgrade IS NOT NULL AND gi.grademax > gi.grademin";

        $value = $DB->get_field_sql($sql, ['siteid' => SITEID]);
        return ($value === false || $value === null) ? null : (float) $value;
    }

    /**
     * Total logstore_standard_log rows recorded from $since (a UNIX
     * timestamp) onward.
     *
     * @param int $since
     * @return int
     */
    private static function count_events_since(int $since): int {
        global $DB;
        return (int) $DB->count_records_select('logstore_standard_log', 'timecreated >= :since', ['since' => $since]);
    }

    /**
     * Daily event counts for the last TREND_DAYS days (today inclusive),
     * oldest first, rendered by admin_reports_page.mustache as pure-CSS
     * bars (no charting library) — each day carries its own
     * heightpercent (0-100, relative to the tallest day in the window) so
     * the template only ever sets one dynamic inline style, matching this
     * theme's existing progress-bar convention (components/
     * course_progress_card.mustache).
     *
     * @return array{trenddays: array[], hastrenddata: bool}
     */
    private static function get_activity_trend(): array {
        $midnight = usergetmidnight(time());
        $counts = [];

        for ($i = self::TREND_DAYS - 1; $i >= 0; $i--) {
            $daystart = $midnight - ($i * DAYSECS);
            $dayend = $daystart + DAYSECS;
            $counts[] = [
                'label' => userdate($daystart, get_string('strftimedateshort', 'langconfig')),
                'count' => self::count_events_between($daystart, $dayend),
            ];
        }

        $max = max(array_column($counts, 'count')) ?: 1;
        $trenddays = array_map(fn (array $day): array => $day + [
            'heightpercent' => (int) round(($day['count'] / $max) * 100),
        ], $counts);

        return [
            'trenddays' => $trenddays,
            'hastrenddata' => (array_sum(array_column($counts, 'count')) > 0),
        ];
    }

    /**
     * @param int $start
     * @param int $end
     * @return int
     */
    private static function count_events_between(int $start, int $end): int {
        global $DB;
        return (int) $DB->count_records_select(
            'logstore_standard_log',
            'timecreated >= :start AND timecreated < :end',
            ['start' => $start, 'end' => $end]
        );
    }

    /**
     * Last AUDIT_TRAIL_LIMIT logged events, newest first — timestamp,
     * acting user (or "Sistema" for userid=0 CLI/cron events, or
     * "Anónimo" when the event itself is flagged anonymous per Moodle's
     * own privacy convention), a human event name (resolved from the
     * stored event class via its own static get_name(), never a raw
     * class-name string), IP, and a context-level label.
     *
     * @return array{hasauditlog: bool, auditlog: array[]}
     */
    private static function get_audit_trail(): array {
        $rows = self::fetch_log_rows(self::AUDIT_TRAIL_LIMIT);

        $auditlog = [];
        foreach ($rows as $row) {
            $auditlog[] = [
                'timestamp' => userdate((int) $row->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'userfullname' => self::resolve_actor_label($row),
                'eventname' => self::resolve_event_name($row->eventname),
                'ip' => $row->ip ?: '—',
                'contextlabel' => self::resolve_context_label((int) $row->contextlevel),
            ];
        }

        return ['hasauditlog' => !empty($auditlog), 'auditlog' => $auditlog];
    }

    /**
     * Raw logstore_standard_log rows (joined with {user} for the acting
     * user's name fields/email), newest first — the shared query behind
     * both get_audit_trail() (15-row dashboard widget) and
     * get_export_rows() (theme/saec/pages/export_report.php's full CSV/
     * Excel download), so the two can never drift apart on what counts as
     * "the log."
     *
     * @param int $limit
     * @return \stdClass[]
     */
    private static function fetch_log_rows(int $limit): array {
        global $DB;

        // \core_user\fields::get_name_fields() returns bare column names
        // (firstname, lastname, ...) with no table prefix — safe to use
        // unqualified here since {logstore_standard_log} has no columns of
        // the same name to collide with (same pattern already used by
        // admin_dashboard::get_users_section()).
        $namefields = implode(', ', \core_user\fields::get_name_fields());
        $sql = "SELECT l.id, l.timecreated, l.eventname, l.component, l.action, l.contextlevel, l.ip,
                       l.userid, l.anonymous, u.id AS uid, u.email, $namefields
                  FROM {logstore_standard_log} l
             LEFT JOIN {user} u ON u.id = l.userid
              ORDER BY l.timecreated DESC, l.id DESC";

        return array_values($DB->get_records_sql($sql, [], 0, $limit));
    }

    /**
     * Full export rows (theme/saec/pages/export_report.php) — same acting-
     * user/event-name/context resolution as the dashboard's own audit
     * trail widget, plus email and a raw component/action column, as
     * plain formatted strings ready for fputcsv()/an HTML table (no
     * Mustache-shaped booleans here, this never touches a template).
     *
     * @param int $limit
     * @return array{header: string[], rows: array[]}
     */
    public static function get_export_rows(int $limit = 500): array {
        $header = [
            get_string('adminreportscoltimestamp', 'theme_saec'),
            get_string('adminreportscoluser', 'theme_saec'),
            get_string('adminreportscoluseremail', 'theme_saec'),
            get_string('adminreportscolevent', 'theme_saec'),
            get_string('adminreportscolcomponentaction', 'theme_saec'),
            get_string('adminreportscolip', 'theme_saec'),
            get_string('adminreportscolcontext', 'theme_saec'),
        ];

        $rows = [];
        foreach (self::fetch_log_rows($limit) as $row) {
            $rows[] = [
                userdate((int) $row->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                self::resolve_actor_label($row),
                (!empty($row->anonymous) || empty($row->userid)) ? '—' : (string) $row->email,
                self::resolve_event_name($row->eventname),
                trim($row->component . ' / ' . $row->action),
                $row->ip ?: '—',
                self::resolve_context_label((int) $row->contextlevel),
            ];
        }

        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * @param \stdClass $row a logstore_standard_log row joined with {user}.
     * @return string
     */
    private static function resolve_actor_label(\stdClass $row): string {
        if (!empty($row->anonymous)) {
            return get_string('adminreportsactoranonymous', 'theme_saec');
        }
        if (empty($row->userid) || empty($row->uid)) {
            return get_string('adminreportsactorsystem', 'theme_saec');
        }
        return fullname($row);
    }

    /**
     * Resolves a stored eventname (a fully-qualified event class name,
     * e.g. "\core\event\user_loggedin") to its own human-readable label
     * via that class's static get_name() — every core_component::
     * event class extends \core\event\base and implements this. Falls
     * back to the raw stored string when the class no longer exists (a
     * plugin that logged this event may since have been uninstalled).
     *
     * @param string $eventname
     * @return string
     */
    private static function resolve_event_name(string $eventname): string {
        if ($eventname !== '' && class_exists($eventname) && is_subclass_of($eventname, \core\event\base::class)) {
            try {
                return $eventname::get_name();
            } catch (\Throwable $e) {
                // Some event classes call get_string() with a component
                // that's since been uninstalled — degrade to the raw
                // class name rather than let a stale log row 500 this page.
                return $eventname;
            }
        }
        return $eventname;
    }

    /**
     * @param int $contextlevel
     * @return string
     */
    private static function resolve_context_label(int $contextlevel): string {
        try {
            return context_helper::get_level_name($contextlevel);
        } catch (\Throwable $e) {
            return '—';
        }
    }

    /**
     * Direct download links to this theme's own export handler
     * (pages/export_report.php). Originally these pointed straight at the
     * native Site Logs report's $logformat = optional_param('download', ...)
     * branch — but that branch only ever runs when chooselog=1 is ALSO
     * present (report/log/index.php gates its whole table-setup-and-export
     * flow behind "if (!empty($chooselog))"), so a plain ?download=csv
     * link silently did nothing. Rather than reverse-engineer that native
     * report's full postback contract, this theme owns a small, self-
     * contained handler that reuses the exact same query as the dashboard's
     * own audit trail widget (get_export_rows()).
     *
     * @return array{exportcsvurl: string, exportexcelurl: string}
     */
    private static function get_export_links(): array {
        return [
            'exportcsvurl' => (new moodle_url('/theme/saec/pages/export_report.php', [
                'format' => 'csv',
                'sesskey' => sesskey(),
            ]))->out(false),
            'exportexcelurl' => (new moodle_url('/theme/saec/pages/export_report.php', [
                'format' => 'excel',
                'sesskey' => sesskey(),
            ]))->out(false),
        ];
    }
}
