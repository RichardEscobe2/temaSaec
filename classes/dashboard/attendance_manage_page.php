<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

/**
 * Backend data preparation for the Session Management Panel
 * (mod/attendance/manage.php) card overlay — Sprint 9.
 *
 * Unlike the Assignment/Boleta/Grader pages, this does NOT replace or hide
 * mod_attendance's native session table — that table's real action links
 * (take/edit/delete) carry a real sesskey and this theme has no business
 * reconstructing state-changing URLs itself. Instead a small JS pass
 * (registered in layout/drawers.php) reads each row's own "take
 * attendance" link to recover its real sessionid, then injects the
 * real-data status pill + attendee-count badge this class computes,
 * keyed by sessionid, into that exact row — the native links/markup
 * underneath are never touched.
 *
 * "Taken" uses attendance_sessions.lasttaken (a real timestamp mod_attendance
 * itself sets on submission — not a guess). The attendee count uses the
 * status with the highest configured grade in that session's status set
 * as "present" — not a hardcoded 'P' acronym assumption, since custom
 * status sets aren't guaranteed to keep that exact acronym, but every
 * status set has real per-status grade values and "highest grade = full
 * attendance credit" is a safe, name-independent proxy.
 */
class attendance_manage_page {

    /**
     * @param int $cmid
     * @return array<int, array{taken: bool, present: int, total: int}> keyed by sessionid.
     */
    public static function get_session_badges(int $cmid): array {
        global $DB;

        $cm = get_coursemodule_from_id('attendance', $cmid, 0, false, MUST_EXIST);
        $attendanceid = (int) $cm->instance;

        $sessions = $DB->get_records('attendance_sessions', ['attendanceid' => $attendanceid], '', 'id, lasttaken, statusset');
        if (empty($sessions)) {
            return [];
        }

        $totalstudents = self::count_active_students($cm->course);

        // Highest-grade status per set actually used by these sessions —
        // one small query per distinct set (typically 1), not one per
        // session. attendance_sessions calls this column "statusset";
        // attendance_statuses calls the same concept "setnumber" — real,
        // confirmed via get_columns(), not a typo to "fix" into matching.
        $statussets = array_unique(array_map(fn ($s) => (int) $s->statusset, $sessions));
        $topstatusbyset = [];
        foreach ($statussets as $statusset) {
            $top = $DB->get_record_sql(
                "SELECT id, grade
                   FROM {attendance_statuses}
                  WHERE attendanceid = :attendanceid AND setnumber = :setnumber AND deleted = 0
               ORDER BY grade DESC",
                ['attendanceid' => $attendanceid, 'setnumber' => $statusset],
                IGNORE_MULTIPLE
            );
            $topstatusbyset[$statusset] = $top ? (int) $top->id : 0;
        }

        $sessionids = array_map(fn ($s) => (int) $s->id, $sessions);
        [$insql, $inparams] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'sess');

        // get_records_sql() keys by the first selected column only when
        // it's unique; sessionid+statusid pairs aren't, so this needs a
        // manually-keyed recordset instead.
        $countsbysession = [];
        foreach ($DB->get_recordset_sql(
            "SELECT sessionid, statusid, COUNT(*) AS cnt
               FROM {attendance_log}
              WHERE sessionid $insql
           GROUP BY sessionid, statusid",
            $inparams
        ) as $row) {
            $countsbysession[(int) $row->sessionid][(int) $row->statusid] = (int) $row->cnt;
        }

        $badges = [];
        foreach ($sessions as $session) {
            $sessionid = (int) $session->id;
            $topstatusid = $topstatusbyset[(int) $session->statusset] ?? 0;
            $present = $countsbysession[$sessionid][$topstatusid] ?? 0;

            $badges[$sessionid] = [
                'taken' => !empty($session->lasttaken),
                'present' => $present,
                'total' => $totalstudents,
            ];
        }
        return $badges;
    }

    /**
     * Active student-role enrolment count for the course — same batched
     * pattern as teacher_courses_page::fetch_student_counts(), simplified
     * to one course since this page is always scoped to one activity.
     *
     * @param int $courseid
     * @return int
     */
    private static function count_active_students(int $courseid): int {
        global $DB;

        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid
               JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :contextcourse
               JOIN {role_assignments} ra ON ra.userid = ue.userid AND ra.contextid = ctx.id
               JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
              WHERE ue.status = 0",
            ['courseid' => $courseid, 'contextcourse' => CONTEXT_COURSE]
        );
    }
}
