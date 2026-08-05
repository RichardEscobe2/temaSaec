<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use context_module;
use stdClass;

/**
 * Backend data preparation for the "Assignment View" SaaS overlay, teacher
 * branch (/mod/assign/view.php) — the counterpart to assign_view_page,
 * which explicitly excludes graders ("profesores ven la página nativa de
 * calificación sin ninguna interferencia de este tema", see that class's
 * docblock). This is what fills that gap.
 *
 * All numbers come straight from \assign::get_assign_grading_summary_renderable()
 * — the exact same real, capability/team/group-aware renderable core's own
 * assign_grading_summary table uses — never a reimplementation of
 * participant/submission counting (team submissions, drafts, groups, and
 * hidden-until-cutoff rules are genuinely complex core logic, unsafe to
 * duplicate). Native output is hidden via CSS (scss/saec/_assign_teacher.scss,
 * body.saec-assign-teacher-summary-active) rather than touched.
 */
class teacher_assign_view_page {

    /** @var bool Guards the require_once calls in bootstrap() against repeated file includes. */
    private static bool $bootstrapped = false;

    private static function bootstrap(): void {
        if (self::$bootstrapped) {
            return;
        }
        global $CFG;
        // Same non-namespaced, non-autoloaded legacy class situation as
        // assign_view_page.php — must be require_once'd explicitly.
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');
        self::$bootstrapped = true;
    }

    /**
     * Unified context for templates/components/assign_teacher_summary.mustache.
     * Returns null whenever the overlay doesn't apply (editing mode, guest,
     * no grading capability, missing/invalid course module).
     *
     * @param int $cmid
     * @return array|null
     */
    public static function get_context(int $cmid): ?array {
        global $PAGE;
        self::bootstrap();

        if ($PAGE->user_is_editing() || isguestuser()) {
            return null;
        }

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $context = context_module::instance($cm->id);

        if (!has_capability('mod/assign:grade', $context)) {
            // Students/parents/non-grading roles keep whatever they'd
            // otherwise see (assign_view_page for genuine students).
            return null;
        }

        $assign = new \assign($context, $cm, $course);
        $summary = $assign->get_assign_grading_summary_renderable();

        return self::get_pills($summary) + [
            'kpis' => self::get_kpis($summary),
        ];
    }

    /**
     * Open/close date status pills — same real values and the same
     * duedate/cutoffdate comparison logic as
     * mod/assign/classes/output/renderer.php::render_assign_grading_summary()
     * (the table this replaces), just restyled as pills instead of table
     * rows. Skipped entirely when the assignment has no due date, same as
     * core.
     *
     * @param \assign_grading_summary $summary
     * @return array
     */
    private static function get_pills(\assign_grading_summary $summary): array {
        if (!$summary->duedate) {
            return ['haspills' => false, 'pills' => []];
        }

        $pills = [];
        $time = time();
        $duedate = $summary->duedate;

        if ($summary->courserelativedatesmode) {
            $timeremainingtext = get_string('relativedatessubmissiontimeleft', 'mod_assign');
        } else if ($duedate - $time <= 0) {
            $timeremainingtext = get_string('assignmentisdue', 'assign');
        } else {
            $timeremainingtext = format_time($duedate - $time);
        }
        $pills[] = [
            'variant' => ($duedate - $time <= 0) ? 'danger' : 'primary',
            'label' => get_string('timeremaining', 'assign'),
            'value' => $timeremainingtext,
        ];

        if ($duedate < $time && $summary->cutoffdate) {
            $late = ($summary->cutoffdate > $time)
                ? get_string('latesubmissionsaccepted', 'assign', userdate($summary->cutoffdate))
                : get_string('nomoresubmissionsaccepted', 'assign');
            $pills[] = [
                'variant' => ($summary->cutoffdate > $time) ? 'warning' : 'danger',
                'label' => get_string('latesubmissions', 'assign'),
                'value' => $late,
            ];
        }

        return ['haspills' => true, 'pills' => $pills];
    }

    /**
     * KPI cards, shaped for the shared theme_saec/components/metric_card
     * partial (same one Calificador/Asistencia/Boleta already use) —
     * Participants (or Teams, for team submissions), Submitted (only when
     * submissions are enabled), Needs Grading (alert-tinted once > 0), and
     * Drafts (only when draft submissions are actually enabled) — the same
     * conditional set core's own table shows, restyled.
     *
     * @param \assign_grading_summary $summary
     * @return array
     */
    private static function get_kpis(\assign_grading_summary $summary): array {
        global $OUTPUT;

        $icon = fn (string $pix) => $OUTPUT->pix_icon($pix, '', 'moodle', ['class' => 'saec-kpi-card__icon-img']);
        $kpis = [];

        $kpis[] = [
            'key' => 'participants',
            'icon' => $icon('i/users'),
            'iconvariant' => 'primary',
            'label' => $summary->teamsubmission
                ? get_string('assignkpiteams', 'theme_saec')
                : get_string('assignkpiparticipants', 'theme_saec'),
            'value' => (string) $summary->participantcount,
            'hasvaluesuffix' => false,
            'valuesuffix' => null,
            'hasfootnote' => false,
            'footnote' => null,
            'isalert' => false,
        ];

        if ($summary->submissionsenabled) {
            if ($summary->submissiondraftsenabled) {
                $kpis[] = [
                    'key' => 'drafts',
                    'icon' => $icon('i/edit'),
                    'iconvariant' => 'neutral',
                    'label' => get_string('assignkpidrafts', 'theme_saec'),
                    'value' => (string) $summary->submissiondraftscount,
                    'hasvaluesuffix' => false,
                    'valuesuffix' => null,
                    'hasfootnote' => false,
                    'footnote' => null,
                    'isalert' => false,
                ];
            }

            $kpis[] = [
                'key' => 'submitted',
                'icon' => $icon('i/checkedcircle'),
                'iconvariant' => 'accent',
                'label' => get_string('assignkpisubmitted', 'theme_saec'),
                'value' => (string) $summary->submissionssubmittedcount,
                'hasvaluesuffix' => false,
                'valuesuffix' => null,
                'hasfootnote' => false,
                'footnote' => null,
                'isalert' => false,
            ];

            if (!$summary->teamsubmission) {
                $needsgrading = $summary->submissionsneedgradingcount;
                $kpis[] = [
                    'key' => 'needsgrading',
                    'icon' => $icon('i/warning'),
                    'iconvariant' => ($needsgrading > 0) ? 'danger' : 'neutral',
                    'label' => get_string('assignkpineedsgrading', 'theme_saec'),
                    'value' => (string) $needsgrading,
                    'hasvaluesuffix' => false,
                    'valuesuffix' => null,
                    'hasfootnote' => false,
                    'footnote' => null,
                    'isalert' => ($needsgrading > 0),
                ];
            }
        }

        return $kpis;
    }
}
