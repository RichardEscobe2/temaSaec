<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use context_module;
use moodle_url;
use stdClass;

/**
 * Backend data preparation for the "Assignment View" SaaS overlay
 * (/mod/assign/view.php — Fase 19): a status/due-date header + a left
 * "Instrucciones + Rúbrica" workspace column injected ABOVE and BESIDE
 * Moodle's own native submission-status table, action buttons, and file
 * submission form (all left completely untouched — no DOM restructuring,
 * only reflowed into the right-hand column of a CSS grid by
 * scss/saec/_assign.scss).
 *
 * Scoped to students only — never for graders (mod/assign:grade) or while
 * $PAGE->user_is_editing() is true, so teachers/admins see the plain native
 * assignment page with zero theme interference, matching the precedent set
 * by course_view_page.
 */
class assign_view_page {

    /** @var bool Guards the require_once calls in bootstrap() against repeated file includes. */
    private static bool $bootstrapped = false;

    private static function bootstrap(): void {
        if (self::$bootstrapped) {
            return;
        }
        global $CFG;
        // mod_assign's \assign class is a legacy non-namespaced,
        // non-autoloaded class (same situation documented in renderers.php
        // for core_badges_renderer/core_course_renderer) — must be
        // require_once'd explicitly before it can be instantiated.
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        self::$bootstrapped = true;
    }

    /**
     * Unified context for templates/components/assign_header.mustache and
     * assign_workspace.mustache. Returns null whenever the overlay doesn't
     * apply (editing mode, grader, guest, missing/invalid course module).
     *
     * @param int $cmid
     * @param int $userid 0 for the current user.
     * @return array|null
     */
    public static function get_context(int $cmid, int $userid = 0): ?array {
        global $USER, $PAGE;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;

        if ($PAGE->user_is_editing() || isguestuser($userid)) {
            return null;
        }

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $context = context_module::instance($cm->id);

        if (has_capability('mod/assign:grade', $context, $userid)) {
            // Graders see the native grading-focused view untouched.
            return null;
        }

        $assign = new \assign($context, $cm, $course);
        $instance = $assign->get_instance($userid);

        return [
            'header' => self::get_header($assign, $instance, $context, $userid),
            // The 65/35 grid is now unconditional for every student
            // assignment view — get_workspace() always returns real
            // "Instrucciones" content (the teacher's own text, or an honest
            // fallback notice when none was written) so the left column is
            // never blank, and the Rúbrica card still only appears when a
            // real rubric is actually configured.
            'workspace' => self::get_workspace($assign, $context),
        ];
    }

    /**
     * Status pill (Pendiente/Entregado/Calificado) + due date + optional
     * "Material de Apoyo" link to the assignment's own intro attachments —
     * every value pulled from the same real APIs mod_assign's own status
     * table already uses (get_grading_status()/get_user_submission()),
     * never a fabricated label.
     *
     * @param \assign $assign
     * @param stdClass $instance
     * @param context_module $context
     * @param int $userid
     * @return array
     */
    private static function get_header(\assign $assign, stdClass $instance, context_module $context, int $userid): array {
        $submission = $assign->get_user_submission($userid, false);
        $hassubmitted = $submission && $submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $isgraded = ($assign->get_grading_status($userid) === ASSIGN_GRADING_STATUS_GRADED);

        if ($isgraded) {
            $statuskey = 'calificado';
        } else if ($hassubmitted) {
            $statuskey = 'entregado';
        } else {
            $statuskey = 'pendiente';
        }

        return [
            'statuskey' => $statuskey,
            'ispendiente' => ($statuskey === 'pendiente'),
            'isentregado' => ($statuskey === 'entregado'),
            'iscalificado' => ($statuskey === 'calificado'),
            'statuslabel' => get_string('assignstatus' . $statuskey, 'theme_saec'),
        ] + self::get_duedate($instance) + self::get_supportmaterial($context) + self::get_instructor($assign, $context);
    }

    /**
     * Same teacher identity shown in the course hero (course_view_page),
     * resolved via the shared instructor_resolver so both overlays always
     * agree on who the teacher is.
     *
     * @param \assign $assign
     * @param context_module $context
     * @return array
     */
    private static function get_instructor(\assign $assign, context_module $context): array {
        $courseid = (int) $assign->get_course()->id;
        $instructor = instructor_resolver::resolve($courseid, $context->get_course_context());

        return [
            'hasinstructor' => ($instructor !== null),
            'instructorname' => $instructor['name'] ?? null,
            'instructoravatarurl' => $instructor['avatarurl'] ?? null,
            'instructormessageurl' => $instructor['messageurl'] ?? null,
        ];
    }

    /**
     * @param stdClass $instance
     * @return array
     */
    private static function get_duedate(stdClass $instance): array {
        if (empty($instance->duedate)) {
            return ['hasduedate' => false];
        }
        return [
            'hasduedate' => true,
            'duedate' => userdate((int) $instance->duedate, get_string('assignduedateformat', 'theme_saec')),
        ];
    }

    /**
     * "Material de Apoyo" CTA — only appears when the assignment actually
     * has real intro attachment files (ASSIGN_INTROATTACHMENT_FILEAREA);
     * links to the first one rather than fabricating a resources list.
     *
     * @param context_module $context
     * @return array
     */
    private static function get_supportmaterial(context_module $context): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_assign',
            ASSIGN_INTROATTACHMENT_FILEAREA,
            0,
            'sortorder',
            false
        );
        if (empty($files)) {
            return ['hassupportmaterial' => false];
        }
        $file = reset($files);
        $url = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
        return [
            'hassupportmaterial' => true,
            'supportmaterialurl' => $url->out(false),
        ];
    }

    /**
     * Left-column workspace: real assignment instructions when the teacher
     * actually wrote any (already filter/format-safe via
     * format_module_intro), or an honest "no instructions" notice when they
     * didn't — the Instrucciones card itself is always rendered either way,
     * so the 65% column is never blank. The Rúbrica card is still only
     * included when the assignment's active grading method is genuinely
     * "rubric" — never a fabricated table when the course uses simple
     * direct grading (the common case on this site).
     *
     * @param \assign $assign
     * @param context_module $context
     * @return array
     */
    private static function get_workspace(\assign $assign, context_module $context): array {
        $intro = format_module_intro('assign', $assign->get_instance(), $assign->get_course_module()->id);
        $hasrealintro = (trim(strip_tags($intro)) !== '');
        return [
            'hasrealintro' => $hasrealintro,
            'introhtml' => $hasrealintro ? $intro : null,
            'nointrofallback' => !$hasrealintro,
            'rubric' => self::get_rubric($context),
        ];
    }

    /**
     * Real rubric criteria pulled from the active advanced grading
     * definition for this assignment's "submissions" grading area — "Peso"
     * is genuinely derived (each criterion's own max achievable score as a
     * percentage of the rubric's total max score), not invented.
     *
     * @param context_module $context
     * @return array
     */
    private static function get_rubric(context_module $context): array {
        $manager = \get_grading_manager($context, 'mod_assign', 'submissions');
        $method = $manager->get_active_method();
        if ($method !== 'rubric') {
            return ['hasrubric' => false];
        }

        $controller = $manager->get_controller('rubric');
        if (!$controller->is_form_defined()) {
            return ['hasrubric' => false];
        }

        $definition = $controller->get_definition();
        $criteria = $definition->rubric_criteria ?? [];
        if (empty($criteria)) {
            return ['hasrubric' => false];
        }

        $totalmax = 0.0;
        foreach ($criteria as $criterion) {
            $totalmax += self::criterion_max_score($criterion);
        }
        if ($totalmax <= 0) {
            return ['hasrubric' => false];
        }

        $rows = [];
        foreach ($criteria as $criterion) {
            $maxscore = self::criterion_max_score($criterion);
            $toplevel = self::criterion_top_level($criterion);
            $rows[] = [
                'criterio' => format_string($criterion['description'], true, ['context' => $context]),
                'peso' => (int) round(($maxscore / $totalmax) * 100),
                'nivellogro' => $toplevel !== null ? format_string($toplevel['definition'], true, ['context' => $context]) : '',
            ];
        }

        return [
            'hasrubric' => true,
            'rubricname' => format_string($definition->name ?? '', true, ['context' => $context]),
            'criteria' => $rows,
        ];
    }

    /**
     * @param array $criterion
     * @return float
     */
    private static function criterion_max_score(array $criterion): float {
        $max = 0.0;
        foreach (($criterion['levels'] ?? []) as $level) {
            $max = max($max, (float) $level['score']);
        }
        return $max;
    }

    /**
     * @param array $criterion
     * @return array|null
     */
    private static function criterion_top_level(array $criterion): ?array {
        $top = null;
        foreach (($criterion['levels'] ?? []) as $level) {
            if ($top === null || (float) $level['score'] > (float) $top['score']) {
                $top = $level;
            }
        }
        return $top;
    }
}
