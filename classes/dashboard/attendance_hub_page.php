<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use moodle_url;

/**
 * Backend data preparation for the "Control de Asistencia" Course
 * Selection Hub (theme/saec/pages/attendance_hub.php) — Sprint 9.
 *
 * mod_attendance has no cross-course landing page of its own: its only
 * course-scoped page (mod/attendance/index.php) lists the activities in
 * ONE course with no metrics, and every other page is per-activity. This
 * class fills that real gap the same way grader_hub_page fills the
 * equivalent gap for grades — one card per attendance activity across
 * every course the teacher teaches (a course can have zero, one, or
 * several attendance activities, so the natural unit here is the
 * activity, not the course).
 *
 * The "Overall Attendance Rate" KPI is the same weighted percentage
 * mod_attendance's own gradebook sync uses (attendance_update_users_grade):
 * earned grade points ÷ (log count × that activity's own max status
 * grade) — not an invented "% marked present" figure, since "counts as
 * present" isn't a stable, acronym-independent concept across custom
 * status sets, but every status set already has real per-status grade
 * values.
 */
class attendance_hub_page {

    /**
     * Unified context for templates/attendance_hub.mustache. Returns null
     * when the logged-in user teaches no course (mirrors
     * grader_hub_page::get_context()'s role guard).
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
        $activities = self::get_activity_cards($courseids);
        $header = teacher_dashboard::get_teacher_header_data($userid);

        return [
            'fullname' => $header['fullname'],
            'avatarurl' => $header['avatarurl'],
            'activitycount' => count($activities),
            'hasactivities' => !empty($activities),
            'activities' => $activities,
        ];
    }

    /**
     * One card per attendance activity across $courseids, each carrying a
     * real weighted attendance-rate percentage and taken/total session
     * counts.
     *
     * @param int[] $courseids
     * @return array[]
     */
    private static function get_activity_cards(array $courseids): array {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');

        $sql = "SELECT cm.id AS cmid, cm.course AS courseid, c.fullname AS coursename, a.id AS attendanceid, a.name AS activityname
                  FROM {course_modules} cm
                  JOIN {modules} md ON md.id = cm.module AND md.name = 'attendance'
                  JOIN {attendance} a ON a.id = cm.instance
                  JOIN {course} c ON c.id = cm.course
                 WHERE cm.course $insql AND cm.deletioninprogress = 0
              ORDER BY c.fullname, a.name";
        $activities = $DB->get_records_sql($sql, $inparams);
        if (empty($activities)) {
            return [];
        }

        $attendanceids = array_map(fn ($a) => (int) $a->attendanceid, $activities);
        $sessioncounts = self::fetch_session_counts($attendanceids);
        $rates = self::fetch_attendance_rates($attendanceids);

        $cards = [];
        foreach ($activities as $activity) {
            $attendanceid = (int) $activity->attendanceid;
            $sessions = $sessioncounts[$attendanceid] ?? ['total' => 0, 'taken' => 0];
            $rate = $rates[$attendanceid] ?? null;

            $cards[] = [
                'cmid' => (int) $activity->cmid,
                'coursename' => format_string($activity->coursename),
                'activityname' => format_string($activity->activityname),
                'sessionssummary' => get_string(
                    'attendancehubsessions',
                    'theme_saec',
                    (object) ['total' => $sessions['total'], 'taken' => $sessions['taken']]
                ),
                'hasrate' => ($rate !== null),
                'rate' => ($rate !== null) ? format_float($rate, 1) : null,
                'manageurl' => (new moodle_url('/mod/attendance/manage.php', ['id' => $activity->cmid]))->out(false),
            ];
        }
        return $cards;
    }

    /**
     * Total vs taken (has at least one real log entry) session counts per
     * attendance activity, batched into 2 queries rather than one pair
     * per activity.
     *
     * @param int[] $attendanceids
     * @return array<int, array{total:int, taken:int}>
     */
    private static function fetch_session_counts(array $attendanceids): array {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($attendanceids, SQL_PARAMS_NAMED, 'att');

        $totals = $DB->get_records_sql(
            "SELECT attendanceid, COUNT(*) AS total
               FROM {attendance_sessions}
              WHERE attendanceid $insql
           GROUP BY attendanceid",
            $inparams
        );

        $taken = $DB->get_records_sql(
            "SELECT s.attendanceid, COUNT(DISTINCT l.sessionid) AS taken
               FROM {attendance_log} l
               JOIN {attendance_sessions} s ON s.id = l.sessionid
              WHERE s.attendanceid $insql
           GROUP BY s.attendanceid",
            $inparams
        );

        $counts = [];
        foreach ($attendanceids as $id) {
            $counts[$id] = [
                'total' => isset($totals[$id]) ? (int) $totals[$id]->total : 0,
                'taken' => isset($taken[$id]) ? (int) $taken[$id]->taken : 0,
            ];
        }
        return $counts;
    }

    /**
     * Weighted attendance-rate percentage per activity — the same
     * earned-grade-over-max-grade ratio mod_attendance's own gradebook
     * sync computes per student, aggregated across every logged mark
     * instead of one student. Activities with no logs yet are omitted
     * (an honest "no data" rather than a fabricated 0%).
     *
     * @param int[] $attendanceids
     * @return array<int, float>
     */
    private static function fetch_attendance_rates(array $attendanceids): array {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($attendanceids, SQL_PARAMS_NAMED, 'att');

        $earned = $DB->get_records_sql(
            "SELECT s.attendanceid, SUM(st.grade) AS earnedsum, COUNT(*) AS logcount
               FROM {attendance_log} l
               JOIN {attendance_sessions} s ON s.id = l.sessionid
               JOIN {attendance_statuses} st ON st.id = l.statusid
              WHERE s.attendanceid $insql
           GROUP BY s.attendanceid",
            $inparams
        );

        $maxgrades = $DB->get_records_sql(
            "SELECT attendanceid, MAX(grade) AS maxgrade
               FROM {attendance_statuses}
              WHERE attendanceid $insql AND deleted = 0
           GROUP BY attendanceid",
            $inparams
        );

        $rates = [];
        foreach ($earned as $attendanceid => $row) {
            $maxgrade = isset($maxgrades[$attendanceid]) ? (float) $maxgrades[$attendanceid]->maxgrade : 0.0;
            $logcount = (int) $row->logcount;
            if ($maxgrade > 0 && $logcount > 0) {
                $rates[$attendanceid] = ((float) $row->earnedsum) / ($logcount * $maxgrade) * 100;
            }
        }
        return $rates;
    }
}
