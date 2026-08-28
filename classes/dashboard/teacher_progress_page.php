<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use context_course;
use core_completion\progress;
use grade_grade;
use grade_item;
use moodle_url;
use stdClass;
use user_picture;

/**
 * "Estudiantes y Progreso" (course-scoped teacher analytics) — replaces
 * /grade/report/user/index.php's bare "all students" table (the native
 * report still renders — core, can't be skipped — and is hidden via
 * scss/saec/_teacher_progress.scss's body.page-teacher-progress rule,
 * exactly like every other *_page overlay in this theme).
 *
 * Scope note: this class only ever builds the "all students" overview.
 * The same URL with a real ?userid=<id> renders core's own untouched
 * single-student report — see layout/drawers.php's own guard on
 * optional_param('userid', 0, PARAM_INT) === 0 before calling
 * get_context() at all, and this class's own roster CTA links, which
 * point straight at that per-student URL.
 *
 * Every metric here is computed live from real enrolment/grade/
 * submission/attendance data — no fabricated numbers. A metric with no
 * underlying data (no course total grade item, no assignments with a due
 * date, mod_attendance not installed) degrades to null/"N/D" rather than
 * a fabricated 0.
 */
class teacher_progress_page {

    /** @var float Below this normalized (0-10) grade, a student counts as at-risk. */
    const AT_RISK_GRADE_THRESHOLD = 7.0;

    /** @var float Below this attendance percentage, a student counts as at-risk. */
    const AT_RISK_ATTENDANCE_THRESHOLD = 80.0;

    /** @var float At/above this normalized grade (and not at-risk), a student is "Acreditado". */
    const ACREDITADO_GRADE_THRESHOLD = 8.0;

    /**
     * Unified context for templates/teacher_progress_page.mustache. Returns
     * null when the logged-in user teaches no course (mirrors every other
     * *_page::get_context()'s role guard).
     *
     * @param int $userid 0 for the current user.
     * @return array|null
     */
    public static function get_context(int $userid = 0): ?array {
        global $USER;
        $userid = $userid ?: (int) $USER->id;

        $courseids = self::get_manageable_courseids($userid);
        if (empty($courseids)) {
            return null;
        }

        $requestedcourseid = optional_param('id', 0, PARAM_INT);
        $courseid = in_array($requestedcourseid, $courseids, true) ? $requestedcourseid : $courseids[0];

        $course = get_course($courseid);
        $context = context_course::instance($courseid);
        $students = self::get_students($courseid);
        $studentids = array_map(fn (stdClass $u): int => (int) $u->id, $students);

        $grades = self::get_student_grades($courseid, $studentids);
        [$attendance, $hasattendance] = self::get_student_attendance($courseid, $studentids);
        $progresspercents = self::get_student_progress($course, $studentids);

        $roster = self::build_roster($students, $courseid, $grades, $attendance, $progresspercents);

        return array_merge(
            [
                'courseid' => $courseid,
                'coursefullname' => format_string($course->fullname, true, ['context' => $context]),
            ],
            self::get_course_selector($courseids, $courseid),
            self::get_kpis($courseid, $studentids, $grades, $attendance, $hasattendance),
            [
                'hasatriskstudents' => !empty(array_filter($roster, fn (array $r): bool => $r['isatrisk'])),
                'atriskstudents' => array_values(array_filter($roster, fn (array $r): bool => $r['isatrisk'])),
                'hasstudents' => !empty($roster),
                'students' => $roster,
                'hasattendance' => $hasattendance,
            ]
        );
    }

    /**
     * Course ids the teacher can manage (moodle/course:update), narrowed
     * from teacher_dashboard::get_taught_courseids() (plain enrolment) —
     * a teacher enrolled as a student elsewhere shouldn't get a "manage"
     * course-selector entry for that course.
     *
     * Public: layout/drawers.php's own nav-drawer builder needs this same
     * list to resolve a real course id for the "Estudiantes y Progreso"
     * link when the teacher isn't currently browsing inside a course —
     * /grade/report/user/index.php's own id param is required_param(), so
     * the link can never be built without one.
     *
     * @param int $userid
     * @return int[]
     */
    public static function get_manageable_courseids(int $userid): array {
        $courseids = [];
        foreach (teacher_dashboard::get_taught_courseids($userid) as $courseid) {
            if (has_capability('moodle/course:update', context_course::instance($courseid), $userid)) {
                $courseids[] = $courseid;
            }
        }
        return $courseids;
    }

    /**
     * Course-selector dropdown data — every manageable course, alphabetical,
     * with a real switch URL (?id=<courseid>) and an isselected flag for
     * the currently-viewed one.
     *
     * @param int[] $courseids
     * @param int $selectedcourseid
     * @return array{courses: array[]}
     */
    private static function get_course_selector(array $courseids, int $selectedcourseid): array {
        $courses = [];
        foreach ($courseids as $courseid) {
            $course = get_course($courseid);
            $courses[] = [
                'id' => $courseid,
                'fullname' => format_string($course->fullname, true, ['context' => context_course::instance($courseid)]),
                'url' => (new moodle_url('/grade/report/user/index.php', ['id' => $courseid]))->out(false),
                'isselected' => ($courseid === $selectedcourseid),
            ];
        }
        usort($courses, fn (array $a, array $b): int => strcasecmp($a['fullname'], $b['fullname']));
        return ['courses' => $courses];
    }

    /**
     * Actively-enrolled students in $courseid, same "student" definition
     * teacher_courses_page::fetch_student_counts() already uses site-wide
     * (active user_enrolments + enrol instance, role shortname 'student')
     * — kept consistent rather than reinvented here.
     *
     * @param int $courseid
     * @return stdClass[] rows with id/firstname/lastname/... user fields.
     */
    private static function get_students(int $courseid): array {
        global $DB;

        $namefields = implode(', ', \core_user\fields::get_name_fields());
        $sql = "SELECT DISTINCT u.id, $namefields, u.email, u.picture, u.imagealt
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid
                  JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :contextcourse
                  JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                 WHERE ue.status = 0 AND u.deleted = 0
              ORDER BY u.lastname ASC, u.firstname ASC";

        return array_values($DB->get_records_sql($sql, ['courseid' => $courseid, 'contextcourse' => CONTEXT_COURSE]));
    }

    /**
     * Per-student course total grade, normalized to a 0-10 scale
     * (matches student_dashboard::compute_gpa()'s own normalization, so a
     * student never sees two different GPA numbers for the same course
     * across this theme). A student with no recorded final grade maps to
     * null, never a fabricated 0.
     *
     * @param int $courseid
     * @param int[] $studentids
     * @return array<int, float|null> keyed by userid.
     */
    private static function get_student_grades(int $courseid, array $studentids): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        $grades = array_fill_keys($studentids, null);
        if (empty($studentids)) {
            return $grades;
        }

        $courseitem = grade_item::fetch_course_item($courseid);
        if (!$courseitem || (float) $courseitem->grademax <= (float) $courseitem->grademin) {
            return $grades;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'student');
        $sql = "SELECT userid, finalgrade
                  FROM {grade_grades}
                 WHERE itemid = :itemid AND userid $insql AND finalgrade IS NOT NULL";
        $params = array_merge($inparams, ['itemid' => $courseitem->id]);

        $min = (float) $courseitem->grademin;
        $max = (float) $courseitem->grademax;
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $grades[(int) $row->userid] = ((float) $row->finalgrade - $min) / ($max - $min) * 10;
        }

        return $grades;
    }

    /**
     * Per-student attendance percentage across every mod_attendance
     * instance in $courseid, using the same weighted takensessionspoints/
     * takensessionsmaxpoints logic student_dashboard::compute_attendance_percentage()
     * already relies on — but batched via mod_attendance_summary's own
     * $userids array constructor argument (one query pass per instance
     * for every student at once, not one per student).
     *
     * @param int $courseid
     * @param int[] $studentids
     * @return array{0: array<int, float|null>, 1: bool} [percentages keyed by userid, hasattendance]
     */
    private static function get_student_attendance(int $courseid, array $studentids): array {
        global $CFG;

        $percentages = array_fill_keys($studentids, null);
        if (empty($studentids) || !file_exists($CFG->dirroot . '/mod/attendance/version.php')) {
            return [$percentages, false];
        }
        require_once($CFG->dirroot . '/mod/attendance/locallib.php');
        require_once($CFG->dirroot . '/mod/attendance/classes/summary.php');

        $course = get_course($courseid);
        $instances = get_all_instances_in_course('attendance', $course);
        if (empty($instances)) {
            return [$percentages, false];
        }

        $points = array_fill_keys($studentids, 0.0);
        $maxpoints = array_fill_keys($studentids, 0.0);
        $hasany = false;

        foreach ($instances as $instance) {
            $summary = new \mod_attendance_summary($instance->id, $studentids);
            foreach ($studentids as $studentid) {
                if (!$summary->has_taken_sessions($studentid)) {
                    continue;
                }
                $usersummary = $summary->get_taken_sessions_summary_for($studentid);
                $points[$studentid] += (float) $usersummary->takensessionspoints;
                $maxpoints[$studentid] += (float) $usersummary->takensessionsmaxpoints;
                $hasany = true;
            }
        }

        if (!$hasany) {
            return [$percentages, false];
        }

        foreach ($studentids as $studentid) {
            if ($maxpoints[$studentid] > 0) {
                $percentages[$studentid] = ($points[$studentid] / $maxpoints[$studentid]) * 100;
            }
        }

        return [$percentages, true];
    }

    /**
     * Per-student course completion percentage via core's own progress
     * API — already returns null (not 0) when completion tracking is off
     * or the student isn't tracked, matching this page's own
     * never-fabricate-a-number rule for free.
     *
     * @param \stdClass $course
     * @param int[] $studentids
     * @return array<int, int|null> keyed by userid, 0-100.
     */
    private static function get_student_progress(stdClass $course, array $studentids): array {
        $percents = [];
        foreach ($studentids as $studentid) {
            $percents[$studentid] = progress::get_course_progress_percentage($course, $studentid);
        }
        return $percents;
    }

    /**
     * Course-wide KPI row (theme_saec/components/metric_card contexts):
     * enrolled count, course average grade (10-scale), assignment
     * submission rate, and attendance average — every metric degrading to
     * a "N/D" value tile instead of a fabricated number when its
     * underlying data doesn't exist yet (no course total grade item, no
     * due-dated assignments, mod_attendance not installed/configured).
     *
     * @param int $courseid
     * @param int[] $studentids
     * @param array<int, float|null> $grades keyed by userid.
     * @param array<int, float|null> $attendance keyed by userid.
     * @param bool $hasattendance
     * @return array{kpis: array[]}
     */
    private static function get_kpis(
        int $courseid,
        array $studentids,
        array $grades,
        array $attendance,
        bool $hasattendance
    ): array {
        global $OUTPUT;

        $icon = fn (string $pix) => $OUTPUT->pix_icon($pix, '', 'moodle', ['class' => 'saec-kpi-card__icon-img']);
        $nodata = get_string('kpinodata', 'theme_saec');

        $realgrades = array_filter($grades, fn (?float $g): bool => $g !== null);
        $avggrade = !empty($realgrades) ? array_sum($realgrades) / count($realgrades) : null;

        $realattendance = array_filter($attendance, fn (?float $a): bool => $a !== null);
        $avgattendance = !empty($realattendance) ? array_sum($realattendance) / count($realattendance) : null;

        $submissionrate = self::get_submission_rate($courseid, $studentids);

        return [
            'kpis' => [
                [
                    'key' => 'enrolled',
                    'icon' => $icon('i/users'),
                    'iconvariant' => 'primary',
                    'label' => get_string('teacherprogresskpienrolled', 'theme_saec'),
                    'value' => (string) count($studentids),
                    'hasvaluesuffix' => false,
                    'hasfootnote' => false,
                ],
                [
                    'key' => 'avggrade',
                    'icon' => $icon('i/grades'),
                    'iconvariant' => 'accent',
                    'label' => get_string('teacherprogresskpiavggrade', 'theme_saec'),
                    'value' => $avggrade !== null ? number_format($avggrade, 1) : $nodata,
                    'hasvaluesuffix' => ($avggrade !== null),
                    'valuesuffix' => '/ 10',
                    'valuemodifier' => $avggrade !== null ? ($avggrade >= self::ACREDITADO_GRADE_THRESHOLD ? 'good' : 'warn') : '',
                    'hasfootnote' => false,
                ],
                [
                    'key' => 'submissionrate',
                    'icon' => $icon('i/checkedcircle'),
                    'iconvariant' => 'primary',
                    'label' => get_string('teacherprogresskpisubmissionrate', 'theme_saec'),
                    'value' => $submissionrate !== null ? ((string) $submissionrate . '%') : $nodata,
                    'hasvaluesuffix' => false,
                    'hasfootnote' => false,
                ],
                [
                    'key' => 'attendance',
                    'icon' => $icon('i/calendar'),
                    'iconvariant' => 'accent',
                    'label' => get_string('teacherprogresskpiattendance', 'theme_saec'),
                    'value' => $avgattendance !== null
                        ? ((string) round($avgattendance) . '%')
                        : ($hasattendance ? $nodata : get_string('teacherprogressattendanceunavailable', 'theme_saec')),
                    'hasvaluesuffix' => false,
                    'hasfootnote' => false,
                ],
            ],
        ];
    }

    /**
     * Submitted-vs-expected assignment rate across every visible,
     * real-due-date assignment in $courseid — same eligibility filter
     * (cm.visible = 1 AND a.duedate > 0) student_dashboard::
     * compute_task_completion() already uses per-student, applied here
     * course-wide: expected = eligible assignments × enrolled students.
     *
     * @param int $courseid
     * @param int[] $studentids
     * @return int|null percentage, or null when there's nothing to measure yet.
     */
    private static function get_submission_rate(int $courseid, array $studentids): ?int {
        global $DB;

        if (empty($studentids)) {
            return null;
        }

        $assigncount = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {assign} a
               JOIN {course_modules} cm ON cm.instance = a.id
               JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
              WHERE a.course = :courseid AND cm.visible = 1 AND a.duedate > 0",
            ['courseid' => $courseid]
        );

        $expected = $assigncount * count($studentids);
        if ($expected === 0) {
            return null;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'student');
        $submitted = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {assign_submission} s
               JOIN {assign} a ON a.id = s.assignment
               JOIN {course_modules} cm ON cm.instance = a.id
               JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
              WHERE a.course = :courseid AND cm.visible = 1 AND a.duedate > 0
                AND s.latest = 1 AND s.status = 'submitted' AND s.userid $insql",
            array_merge(['courseid' => $courseid], $inparams)
        );

        return (int) round(($submitted / $expected) * 100);
    }

    /**
     * One roster row per student — identity, progress bar, current grade,
     * attendance, at-risk flag, and a status badge
     * (Acreditado/Regular/En Riesgo — see the class constants for the exact
     * thresholds), plus the CTA straight into core's own per-student
     * report at this same URL (?userid=<id>, never intercepted by this
     * overlay — see the class docblock).
     *
     * @param \stdClass[] $students
     * @param int $courseid
     * @param array<int, float|null> $grades
     * @param array<int, float|null> $attendance
     * @param array<int, int|null> $progresspercents
     * @return array[]
     */
    private static function build_roster(
        array $students,
        int $courseid,
        array $grades,
        array $attendance,
        array $progresspercents
    ): array {
        global $PAGE;

        $roster = [];
        foreach ($students as $student) {
            $studentid = (int) $student->id;
            $grade = $grades[$studentid] ?? null;
            $attendancepercent = $attendance[$studentid] ?? null;
            $progresspercent = $progresspercents[$studentid] ?? null;

            $isatrisk = ($grade !== null && $grade < self::AT_RISK_GRADE_THRESHOLD)
                || ($attendancepercent !== null && $attendancepercent < self::AT_RISK_ATTENDANCE_THRESHOLD);

            if ($isatrisk) {
                $status = 'enriesgo';
            } else if ($grade !== null && $grade >= self::ACREDITADO_GRADE_THRESHOLD) {
                $status = 'acreditado';
            } else {
                $status = 'regular';
            }

            $userpicture = new user_picture($student);
            $userpicture->size = 60;

            $roster[] = [
                'id' => $studentid,
                'fullname' => fullname($student),
                'email' => $student->email,
                // user_picture::get_url() always returns a real image (the
                // site's default avatar/gravatar when no photo is uploaded),
                // never empty — matches components/grade_table.mustache's
                // own hasavatar convention with hasavatar always true here.
                'hasavatar' => true,
                'avatarurl' => \theme_saec\course_helper::to_relative_url($userpicture->get_url($PAGE)->out(false)),
                'hasprogress' => ($progresspercent !== null),
                'progresspercent' => $progresspercent,
                'hasgrade' => ($grade !== null),
                'grade' => $grade !== null ? number_format($grade, 1) : null,
                'hasattendance' => ($attendancepercent !== null),
                'attendancepercent' => $attendancepercent !== null ? round($attendancepercent) : null,
                'isatrisk' => $isatrisk,
                'status' => $status,
                'statuslabel' => get_string('teacherprogressstatus' . $status, 'theme_saec'),
                'reporturl' => (new moodle_url('/grade/report/user/index.php', [
                    'id' => $courseid,
                    'userid' => $studentid,
                ]))->out(false),
            ];
        }

        return $roster;
    }
}
