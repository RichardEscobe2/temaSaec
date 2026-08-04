<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use completion_info;
use context_course;
use grade_grade;
use grade_item;
use moodle_url;
use stdClass;

/**
 * Backend data preparation for "Mi Rendimiento" (/grade/report/overview/index.php
 * — Fase 13). Replaces core's plain grade-overview table for students (hidden
 * via scss/custom.scss) with a KPI/matrix/timeline dashboard, reusing the same
 * GPA/completion computations already established in student_dashboard.php and
 * courses_page.php.
 *
 * Two mockup elements (stitch_uptex/rendimiento_academico) don't correspond to
 * anything Moodle actually tracks — an XP/"Nivel N" gamification layer, and a
 * hardcoded "Certificación en Cloud Computing" milestone name — so both are
 * replaced with honest computed equivalents instead of fabricated numbers:
 * student standing is derived from real GPA, and the milestone card surfaces
 * whichever real enrolled course is closest to completion (see
 * get_next_milestone()/get_student_status()).
 */
class analytics_page {

    /** @var int Max activity timeline entries returned. */
    const MAX_TIMELINE_ITEMS = 8;

    /** @var bool Guards the require_once calls in bootstrap() against repeated file includes. */
    private static bool $bootstrapped = false;

    private static function bootstrap(): void {
        if (self::$bootstrapped) {
            return;
        }
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/badgeslib.php');
        self::$bootstrapped = true;
    }

    /**
     * Unified context for templates/analytics_page.mustache.
     *
     * @param int $userid 0 for the current user.
     * @return array
     */
    public static function get_context(int $userid = 0): array {
        global $USER, $OUTPUT;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;

        $courses = enrol_get_all_users_courses(
            $userid,
            true,
            'shortname, fullname, category, enablecompletion, visible'
        );
        $courses = array_filter($courses, fn (stdClass $c): bool => !empty($c->visible));
        $courseids = array_keys($courses);

        $subjects = [];
        $percentages = [];
        foreach ($courses as $course) {
            $row = self::export_subject_row($course, $userid);
            $subjects[] = $row;
            if ($row['hasprogress']) {
                $percentages[] = $row['progresspercent'];
            }
        }

        $gpa = self::compute_gpa($userid, $courseids);
        $completionrate = !empty($percentages) ? array_sum($percentages) / count($percentages) : null;
        $studyhours = self::compute_total_study_hours($userid);
        $badgecount = count(badges_get_user_badges($userid) ?: []);

        $nodata = get_string('kpinodata', 'theme_saec');
        $timeline = self::get_activity_timeline($userid);

        $icon = fn (string $pix) => $OUTPUT->pix_icon($pix, '', 'moodle', ['class' => 'uptex-metric-card__icon-img']);
        $kpis = [
            [
                'icon' => $icon('i/grades'),
                'iconvariant' => '',
                'label' => get_string('kpigpa', 'theme_saec'),
                'value' => $gpa !== null ? number_format($gpa, 1) : $nodata,
                'hasvaluesuffix' => $gpa !== null,
                'valuesuffix' => '/ 10',
                'hasfootnote' => true,
                'footnote' => get_string('kpigpafootnote', 'theme_saec'),
            ],
            [
                'icon' => $icon('i/calendareventtime'),
                'iconvariant' => 'neutral',
                'label' => get_string('kpistudyhours', 'theme_saec'),
                'value' => get_string('kpistudyhoursvalue', 'theme_saec', $studyhours),
                'hasvaluesuffix' => false,
                'hasfootnote' => true,
                'footnote' => get_string('kpistudyhourstotalfootnote', 'theme_saec'),
            ],
            [
                'icon' => $icon('i/checkedcircle'),
                'iconvariant' => 'accent',
                'label' => get_string('kpicompletionrate', 'theme_saec'),
                'value' => $completionrate !== null ? round($completionrate) . '%' : $nodata,
                'hasvaluesuffix' => false,
                'hasfootnote' => true,
                'footnote' => get_string('kpicompletionratefootnote', 'theme_saec'),
            ],
            [
                'icon' => $icon('i/badge'),
                'iconvariant' => 'accent',
                'label' => get_string('kpibadgesearned', 'theme_saec'),
                'value' => (string) $badgecount,
                'hasvaluesuffix' => false,
                'hasfootnote' => false,
            ],
        ];

        return [
            'kpis' => $kpis,

            'hassubjects' => !empty($subjects),
            'subjects' => $subjects,

            'timeline' => $timeline,
            'hastimeline' => !empty($timeline),

            'nextmilestone' => self::get_next_milestone($courses, $userid),
            'trend' => self::get_trend($userid),
            'studentstatus' => self::get_student_status($gpa, $completionrate),
        ];
    }

    /**
     * One "Matriz de Materias" row: real course grade (0-10, 1 decimal), real
     * completion progress, and real badge-eligibility status.
     *
     * @param stdClass $course
     * @param int $userid
     * @return array
     */
    private static function export_subject_row(stdClass $course, int $userid): array {
        $context = context_course::instance($course->id);
        $grade = self::compute_course_grade($course->id, $userid);

        $completion = new completion_info($course);
        $hasprogress = false;
        $progresspercent = 0;
        $completedmodules = null;
        $totalmodules = null;
        if ($completion->is_enabled()) {
            $totalmodules = count($completion->get_activities());
            if ($totalmodules > 0) {
                $hasprogress = true;
                $completedmodules = $completion->count_modules_completed($userid);
                $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
                $progresspercent = $percent !== null ? (int) round($percent) : 0;
            }
        }

        $badgestatus = self::resolve_badge_status($course->id, $userid, $hasprogress && $progresspercent >= 100);

        return [
            'courseid' => (int) $course->id,
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            'coursecode' => $course->shortname,
            'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'hasgrade' => $grade !== null,
            'grade' => $grade !== null ? number_format($grade, 1) : null,
            'hasprogress' => $hasprogress,
            'progresspercent' => $progresspercent,
            'hasbadgestatus' => $badgestatus !== null,
            'badgestatus' => $badgestatus['label'] ?? null,
            'badgestatusvariant' => $badgestatus['variant'] ?? null,
        ];
    }

    /**
     * Course total grade normalised to a 0-10 scale — same normalisation
     * student_dashboard::compute_gpa() uses, but returning the individual
     * course's value instead of an average across courses.
     *
     * @param int $courseid
     * @param int $userid
     * @return float|null
     */
    private static function compute_course_grade(int $courseid, int $userid): ?float {
        $courseitem = grade_item::fetch_course_item($courseid);
        if (!$courseitem) {
            return null;
        }
        $grade = grade_grade::fetch(['itemid' => $courseitem->id, 'userid' => $userid]);
        if (!$grade || $grade->finalgrade === null) {
            return null;
        }
        $min = (float) $courseitem->grademin;
        $max = (float) $courseitem->grademax;
        if ($max <= $min) {
            return null;
        }
        return (((float) $grade->finalgrade - $min) / ($max - $min)) * 10;
    }

    /**
     * Simple (unweighted) mean of every enrolled course's normalised grade —
     * identical method to student_dashboard::compute_gpa(), duplicated here
     * rather than cross-called to keep this page's backend self-contained.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return float|null
     */
    private static function compute_gpa(int $userid, array $courseids): ?float {
        $sum = 0.0;
        $count = 0;
        foreach ($courseids as $courseid) {
            $grade = self::compute_course_grade($courseid, $userid);
            if ($grade === null) {
                continue;
            }
            $sum += $grade;
            $count++;
        }
        return $count > 0 ? ($sum / $count) : null;
    }

    /**
     * Course badge eligibility for the "Insignia" column: real
     * badges_get_badges() lookup, not fabricated. Returns null when the
     * course simply has no course-badge configured (column stays blank
     * rather than showing a false "Pendiente").
     *
     * @param int $courseid
     * @param int $userid
     * @param bool $iscoursecompleted
     * @return array{label: string, variant: string}|null
     */
    private static function resolve_badge_status(int $courseid, int $userid, bool $iscoursecompleted): ?array {
        $coursebadges = badges_get_badges(BADGE_TYPE_COURSE, $courseid, '', '', 0, 0, $userid);
        if (empty($coursebadges)) {
            return null;
        }
        foreach ($coursebadges as $badge) {
            if (!empty($badge->dateissued)) {
                return ['label' => get_string('badgestatusobtained', 'theme_saec'), 'variant' => 'granted'];
            }
        }
        if ($iscoursecompleted) {
            return ['label' => get_string('badgestatuseligible', 'theme_saec'), 'variant' => 'eligible'];
        }
        return ['label' => get_string('badgestatuspending', 'theme_saec'), 'variant' => 'pending'];
    }

    /**
     * Distinct hours (all-time) in which the user generated at least one log
     * entry — the same honest proxy for "study time" used by
     * student_dashboard::compute_study_hours_this_week(), without that
     * method's current-week boundary.
     *
     * @param int $userid
     * @return int
     */
    private static function compute_total_study_hours(int $userid): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            return 0;
        }
        $sql = "SELECT DISTINCT FLOOR(l.timecreated / 3600) AS hourbucket
                  FROM {logstore_standard_log} l
                 WHERE l.userid = :userid AND l.anonymous = 0";
        try {
            return count($DB->get_records_sql($sql, ['userid' => $userid]));
        } catch (\dml_exception $e) {
            return 0;
        }
    }

    /**
     * Recent academic activity, merged from three real sources: graded items
     * WITH teacher feedback text, graded items without feedback, and
     * assignment submissions. Sorted newest first, capped to
     * MAX_TIMELINE_ITEMS.
     *
     * @param int $userid
     * @return array
     */
    private static function get_activity_timeline(int $userid): array {
        global $DB;

        $items = [];

        $gradesql = "SELECT g.id, g.finalgrade, g.feedback, g.timemodified,
                            gi.itemname, gi.grademax, gi.courseid
                       FROM {grade_grades} g
                       JOIN {grade_items} gi ON gi.id = g.itemid
                      WHERE g.userid = :userid AND g.finalgrade IS NOT NULL
                            AND gi.itemtype = 'mod'
                   ORDER BY g.timemodified DESC";
        $graderows = $DB->get_records_sql($gradesql, ['userid' => $userid], 0, self::MAX_TIMELINE_ITEMS * 2);

        foreach ($graderows as $row) {
            if (!empty(trim((string) $row->feedback))) {
                $items[] = [
                    'type' => 'feedback',
                    'timestamp' => (int) $row->timemodified,
                    'title' => get_string('timelinefeedback', 'theme_saec'),
                    'detail' => trim(strip_tags($row->feedback)),
                ];
            } else {
                $items[] = [
                    'type' => 'grade',
                    'timestamp' => (int) $row->timemodified,
                    'title' => format_string($row->itemname),
                    'detail' => get_string('timelinegrade', 'theme_saec', number_format((float) $row->finalgrade, 1) . '/' . number_format((float) $row->grademax, 1)),
                ];
            }
        }

        if ($DB->get_manager()->table_exists('assign_submission')) {
            $submissionsql = "SELECT s.id, s.timemodified, a.name AS assignname
                                 FROM {assign_submission} s
                                 JOIN {assign} a ON a.id = s.assignment
                                WHERE s.userid = :userid AND s.status = 'submitted'
                             ORDER BY s.timemodified DESC";
            $submissionrows = $DB->get_records_sql($submissionsql, ['userid' => $userid], 0, self::MAX_TIMELINE_ITEMS);
            foreach ($submissionrows as $row) {
                $items[] = [
                    'type' => 'submission',
                    'timestamp' => (int) $row->timemodified,
                    'title' => get_string('timelinesubmission', 'theme_saec'),
                    'detail' => format_string($row->assignname),
                ];
            }
        }

        usort($items, fn (array $a, array $b) => $b['timestamp'] <=> $a['timestamp']);
        $items = array_slice($items, 0, self::MAX_TIMELINE_ITEMS);

        $dateformat = get_string('strftimedatetimeshort', 'langconfig');
        foreach ($items as &$item) {
            $item['date'] = userdate($item['timestamp'], $dateformat);
            $item['isfeedback'] = ($item['type'] === 'feedback');
        }
        return $items;
    }

    /**
     * The enrolled course closest to finishing (highest completion % below
     * 100), for the "Próximo Hito" card. Returns hasmilestone=false — never
     * a fabricated certification name — when no course qualifies.
     *
     * @param stdClass[] $courses
     * @param int $userid
     * @return array
     */
    private static function get_next_milestone(array $courses, int $userid): array {
        $best = null;
        foreach ($courses as $course) {
            $completion = new completion_info($course);
            if (!$completion->is_enabled()) {
                continue;
            }
            $total = count($completion->get_activities());
            if ($total === 0) {
                continue;
            }
            $completed = $completion->count_modules_completed($userid);
            $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
            $percent = $percent !== null ? (int) round($percent) : 0;
            if ($percent >= 100) {
                continue;
            }
            if ($best === null || $percent > $best['progresspercent']) {
                $best = [
                    'coursename' => format_string($course->fullname, true, ['context' => context_course::instance($course->id)]),
                    'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    'progresspercent' => $percent,
                    'remainingmodules' => $total - $completed,
                ];
            }
        }

        if ($best === null) {
            return ['hasmilestone' => false];
        }
        return array_merge(['hasmilestone' => true], $best, [
            'remainingnote' => get_string('milestoneremaining', 'theme_saec', $best['remainingmodules']),
        ]);
    }

    /** @var int Delta threshold (percentage points) below which a trend reads as "neutral" rather than a false up/down swing. */
    const TREND_NEUTRAL_THRESHOLD = 3;

    /**
     * Rolling 30-day grade trend from grade_grades_history — the current
     * 30-day window's average normalised grade vs the preceding 30-day
     * window's, both real historical data, never a fabricated percentage.
     * Returns hastrend=false when either window has no history for this
     * user rather than inventing a delta.
     *
     * @param int $userid
     * @return array
     */
    private static function get_trend(int $userid): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('grade_grades_history')) {
            return ['hastrend' => false];
        }

        $windowsize = 30 * DAYSECS;
        $now = time();
        $currentwindowstart = $now - $windowsize;
        $previouswindowstart = $now - (2 * $windowsize);

        $sql = "SELECT h.id, h.finalgrade, h.timemodified, gi.grademin, gi.grademax
                  FROM {grade_grades_history} h
                  JOIN {grade_items} gi ON gi.id = h.itemid
                 WHERE h.userid = :userid AND h.finalgrade IS NOT NULL AND h.timemodified >= :windowstart
              ORDER BY h.timemodified DESC";
        $rows = $DB->get_records_sql($sql, ['userid' => $userid, 'windowstart' => $previouswindowstart], 0, 1000);
        if (empty($rows)) {
            return ['hastrend' => false];
        }

        $currentvalues = [];
        $previousvalues = [];
        foreach ($rows as $row) {
            $max = (float) $row->grademax;
            $min = (float) $row->grademin;
            if ($max <= $min) {
                continue;
            }
            $normalised = (((float) $row->finalgrade - $min) / ($max - $min)) * 10;
            if ((int) $row->timemodified >= $currentwindowstart) {
                $currentvalues[] = $normalised;
            } else {
                $previousvalues[] = $normalised;
            }
        }

        if (empty($currentvalues) || empty($previousvalues)) {
            return ['hastrend' => false];
        }

        $latestavg = array_sum($currentvalues) / count($currentvalues);
        $previousavg = array_sum($previousvalues) / count($previousvalues);
        if ($previousavg <= 0) {
            return ['hastrend' => false];
        }

        $deltapercent = (($latestavg - $previousavg) / $previousavg) * 100;
        $absdelta = abs(round($deltapercent));

        if ($absdelta < self::TREND_NEUTRAL_THRESHOLD) {
            $state = 'neutral';
        } else if ($deltapercent > 0) {
            $state = 'up';
        } else {
            $state = 'down';
        }

        $trendnote = match ($state) {
            'up' => get_string('trendup', 'theme_saec', $absdelta),
            'down' => get_string('trenddown', 'theme_saec', $absdelta),
            default => get_string('trendneutral', 'theme_saec'),
        };

        // Real relative bar heights (previous window vs current window),
        // 0-10 normalised average scaled to a 0-100% bar height — a genuine
        // 2-point mini chart, not decorative fake data.
        return [
            'hastrend' => true,
            'isup' => ($state === 'up'),
            'isdown' => ($state === 'down'),
            'isneutral' => ($state === 'neutral'),
            'trendnote' => $trendnote,
            'previousbarheight' => max(6, (int) round(($previousavg / 10) * 100)),
            'currentbarheight' => max(6, (int) round(($latestavg / 10) * 100)),
        ];
    }

    /**
     * Real academic-standing label derived from GPA, plus the overall
     * completion percentage as a progress indicator — replaces the mockup's
     * fabricated XP/"Nivel N" gamification with numbers that actually mean
     * something.
     *
     * @param float|null $gpa
     * @param float|null $completionrate
     * @return array
     */
    private static function get_student_status(?float $gpa, ?float $completionrate): array {
        if ($gpa === null) {
            $label = get_string('statusnodata', 'theme_saec');
            $variant = 'neutral';
        } else if ($gpa >= 9) {
            $label = get_string('statusoutstanding', 'theme_saec');
            $variant = 'granted';
        } else if ($gpa >= 7) {
            $label = get_string('statusgood', 'theme_saec');
            $variant = 'eligible';
        } else if ($gpa >= 6) {
            $label = get_string('statuspassing', 'theme_saec');
            $variant = 'pending';
        } else {
            $label = get_string('statusatrisk', 'theme_saec');
            $variant = 'danger';
        }

        $percent = $completionrate !== null ? (int) round($completionrate) : 0;
        return [
            'statuslabel' => $label,
            'statusvariant' => $variant,
            'completionpercent' => $percent,
            'hascompletion' => $completionrate !== null,
        ];
    }
}
