<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use context_course;
use moodle_url;
use stdClass;

/**
 * Backend data preparation for the Student Tasks Hub ("Mis Tareas",
 * theme/saec/pages/student_tasks.php) — a bird's-eye view of every
 * assignment across every enrolled course, categorized by real submission/
 * grading state rather than just due date, unlike student_dashboard's
 * "Próximas Entregas" widget (which only surfaces upcoming due dates) or
 * its "Tareas" KPI (which only counts dated assignments). This is the
 * complete task list a student would otherwise have to visit every course
 * individually to assemble.
 */
class student_tasks_page {

    /** @var int Seconds before a due date counts as "Urgente". */
    const URGENT_WINDOW = 24 * HOURSECS;

    /** @var int Seconds before a due date counts as "Próximo". */
    const UPCOMING_WINDOW = 3 * DAYSECS;

    /**
     * Unified context for templates/student_tasks_page.mustache. Returns
     * null when the logged-in user is not a student (mirrors every other
     * *_page::get_context()'s role guard) — this hub is student-only, per
     * spec, so admin/teacher never see it even if they navigate here
     * directly.
     *
     * @param int $userid 0 for the current user.
     * @return array|null
     */
    public static function get_context(int $userid = 0): ?array {
        global $USER;
        $userid = $userid ?: (int) $USER->id;

        if (!student_dashboard::is_student($userid)) {
            return null;
        }

        $tasks = self::fetch_tasks($userid);

        // 'cerrada' (closed, never submitted — see export_task()) is
        // deliberately not counted in any of these 3 KPIs: it's neither
        // something actionable ("Pendientes" implies the student can still
        // do something about it) nor "awaiting review" (nothing was ever
        // submitted for a teacher to look at). It still appears under
        // "Todas" and — per this hub's filter grouping — under
        // "Entregadas", each card carrying its own distinct badge.
        $counts = ['pendiente' => 0, 'entregada' => 0, 'calificada' => 0];
        foreach ($tasks as $task) {
            if (isset($counts[$task['status']])) {
                $counts[$task['status']]++;
            }
        }

        return [
            'kpipending' => $counts['pendiente'],
            'kpisubmitted' => $counts['entregada'],
            'kpigraded' => $counts['calificada'],
            'hastasks' => !empty($tasks),
            'tasks' => $tasks,
        ];
    }

    /**
     * Every 'assign' activity across every course $userid is actively
     * enrolled in, each carrying its real submission/grading state. A
     * single batched query (course modules + assign + this student's own
     * submission + this student's own grade, all LEFT JOINed) rather than
     * one pair of lookups per activity — the same "batch across courses,
     * not per-course" discipline every other provider in this theme follows.
     *
     * @param int $userid
     * @return array[] Newest-due-first; undated assignments last.
     */
    private static function fetch_tasks(int $userid): array {
        global $DB;

        $courseids = array_keys(enrol_get_all_users_courses($userid, true, ['id']));
        if (empty($courseids)) {
            return [];
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');

        // assign_grades has a UNIQUE(assignment, userid, attemptnumber) index —
        // an assignment that allows multiple attempts can have several grade
        // rows per student, one per attempt. Joining on attemptnumber (not
        // just assignment+userid) picks the grade for the student's current
        // (latest submission's) attempt only, instead of fanning this query
        // out into one duplicate task card per historical attempt.
        $sql = "SELECT cm.id AS cmid, cm.course AS courseid, a.id AS assignid, a.name, a.duedate, a.grade AS maxgrade,
                       s.status AS submissionstatus, s.timemodified AS submittedon,
                       g.grade AS grade, g.timemodified AS gradedon
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                  JOIN {assign} a ON a.id = cm.instance
             LEFT JOIN {assign_submission} s ON s.assignment = a.id AND s.userid = :userid1 AND s.latest = 1
             LEFT JOIN {assign_grades} g ON g.assignment = a.id AND g.userid = :userid2
                        AND g.attemptnumber = COALESCE(s.attemptnumber, 0)
                 WHERE cm.course $insql AND cm.visible = 1 AND cm.deletioninprogress = 0
              ORDER BY CASE WHEN a.duedate = 0 THEN 1 ELSE 0 END, a.duedate ASC";
        $params = array_merge($inparams, ['userid1' => $userid, 'userid2' => $userid]);

        $rows = $DB->get_records_sql($sql, $params);
        if (empty($rows)) {
            return [];
        }

        $coursecache = [];
        $tasks = [];
        foreach ($rows as $row) {
            if (!isset($coursecache[$row->courseid])) {
                $coursecache[$row->courseid] = get_course($row->courseid);
            }
            $tasks[] = self::export_task($row, $coursecache[$row->courseid]);
        }
        return $tasks;
    }

    /**
     * Exports one joined row into the task-card shape. Status is derived
     * from real data only: "calificada" requires an actual recorded grade
     * (assign_grades.grade <> -1 — Moodle's own "not graded yet" sentinel,
     * the same one mod/assign/locallib.php checks), "entregada" requires a
     * submission whose status is literally 'submitted', "cerrada" is a
     * never-submitted assignment whose due date has already passed (per
     * this hub's spec — a simplification of Moodle's own submission-
     * eligibility rule, which really hinges on `cutoffdate`, not `duedate`;
     * an assignment with no cutoff date technically still accepts a late
     * submission after its due date. Documented here rather than silently
     * assumed, since it's a deliberate product simplification, not an
     * oversight), and everything else — including a never-touched activity
     * still inside its window, or one left as a draft — is "pendiente".
     *
     * @param stdClass $row One row from fetch_tasks()'s joined query.
     * @param stdClass $course
     * @return array
     */
    private static function export_task(stdClass $row, stdClass $course): array {
        $hasgrade = ($row->grade !== null && (float) $row->grade >= 0);
        $duedate = (int) $row->duedate;
        $ispastdue = ($duedate > 0 && $duedate < time());

        if ($hasgrade) {
            $status = 'calificada';
        } else if ($row->submissionstatus === 'submitted') {
            $status = 'entregada';
        } else if ($ispastdue) {
            $status = 'cerrada';
        } else {
            $status = 'pendiente';
        }

        $urgency = self::compute_urgency($duedate, $status);

        $viewurl = (new moodle_url('/mod/assign/view.php', ['id' => (int) $row->cmid]))->out(false);

        $context = context_course::instance($course->id);

        return [
            'cmid' => (int) $row->cmid,
            'courseid' => (int) $row->courseid,
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            'courseshortname' => $course->shortname,
            'title' => format_string($row->name),
            'hasduedate' => ($duedate > 0),
            'duedatelabel' => ($duedate > 0)
                ? userdate($duedate, get_string('strftimedatetimeshort', 'langconfig'))
                : get_string('studenttasksnoduedate', 'theme_saec'),
            'status' => $status,
            'statuslabel' => get_string('studenttasksstatus' . $status, 'theme_saec'),
            'urgency' => $urgency,
            'hasurgencylabel' => ($urgency !== null),
            'urgencylabel' => $urgency ? get_string('studenttasksurgency' . $urgency, 'theme_saec') : null,
            'ispending' => ($status === 'pendiente'),
            'issubmitted' => ($status === 'entregada'),
            'isgraded' => ($status === 'calificada'),
            'isclosed' => ($status === 'cerrada'),
            'viewurl' => $viewurl,
            'hasgradelabel' => $hasgrade,
            'gradelabel' => $hasgrade ? self::format_grade((float) $row->grade, (float) $row->maxgrade) : null,
        ];
    }

    /**
     * "Urgente" (overdue or due within URGENT_WINDOW) / "proximo" (due
     * within UPCOMING_WINDOW) / "contiempo" (further out) / null (no due
     * date, or already resolved — a graded or submitted task isn't
     * "urgent" anymore regardless of its due date, that semaphore is a
     * to-do signal, not a historical record).
     *
     * @param int $duedate 0 for no due date.
     * @param string $status
     * @return string|null
     */
    private static function compute_urgency(int $duedate, string $status): ?string {
        if ($duedate <= 0 || $status !== 'pendiente') {
            return null;
        }

        $secondsleft = $duedate - time();
        if ($secondsleft <= self::URGENT_WINDOW) {
            return 'urgente';
        }
        if ($secondsleft <= self::UPCOMING_WINDOW) {
            return 'proximo';
        }
        return 'contiempo';
    }

    /**
     * "8.5/10" for point-based assignments (assign.grade > 0, the max
     * points). Scale-based assignments (assign.grade < 0, a scale id) have
     * no single "out of N" to show, so only the raw value is shown — an
     * honest partial label rather than a fabricated denominator.
     *
     * @param float $grade
     * @param float $maxgrade
     * @return string
     */
    private static function format_grade(float $grade, float $maxgrade): string {
        if ($maxgrade > 0) {
            return get_string('studenttasksgradevalue', 'theme_saec', (object) [
                'grade' => format_float($grade, 1),
                'max' => format_float($maxgrade, 1),
            ]);
        }
        return format_float($grade, 1);
    }
}
