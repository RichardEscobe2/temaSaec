<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/renderer.php');
require_once($CFG->dirroot . '/badges/renderer.php');

/**
 * theme_saec course renderer override.
 *
 * Wired automatically by Moodle's theme_overridden_renderer_factory (already
 * set as $THEME->rendererfactory in config.php): a legacy-named class
 * `theme_saec_core_course_renderer` extending `core_course_renderer`, loaded
 * from this file, transparently replaces the core renderer for every call to
 * $PAGE->get_renderer('core', 'course') while this theme is active.
 *
 * Overrides frontpage_available_courses()/frontpage_my_courses() — the two
 * entry points core_course_renderer::frontpage() dispatches to based on
 * $CFG->frontpage / $CFG->frontpageloggedin — to render the UPTex Stitch
 * course grid (theme_saec/core_course/frontpage_courses_grid) instead of the
 * legacy html_writer-built .coursebox markup. Course fetching, capability
 * checks (moodle/course:create fallback) and enrolment logic are reused
 * unchanged from core; only the final HTML assembly step is replaced.
 */
class theme_saec_core_course_renderer extends core_course_renderer {

    /** @var string[] Category keyword => .uptex-course-showcase-card__tag colour modifier (custom.scss). */
    private const CATEGORY_TAG_COLORS = [
        'web' => 'green', 'desarrollo' => 'green',
        'seguridad' => 'darkgreen', 'cyber' => 'darkgreen',
        'inteligencia' => 'red', 'datos' => 'red',
        'arquitectura' => 'gray', 'redes' => 'gray',
    ];

    /**
     * Returns HTML to print list of available courses for the frontpage.
     *
     * @return string
     */
    public function frontpage_available_courses() {
        global $CFG;

        $chelper = new coursecat_helper();
        $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_EXPANDED)->
                set_courses_display_options([
                    'recursive' => true,
                    'limit' => $CFG->frontpagecourselimit,
                ]);

        $courses = core_course_category::top()->get_courses($chelper->get_courses_display_options());
        $totalcount = core_course_category::top()->get_courses_count($chelper->get_courses_display_options());

        if (!$totalcount && !$this->page->user_is_editing()
                && has_capability('moodle/course:create', context_system::instance())) {
            // Preserva el comportamiento nativo: enlace para crear el primer curso.
            return $this->add_new_course_button();
        }

        return $this->uptex_render_course_grid($courses, $totalcount);
    }

    /**
     * Returns HTML to print list of courses the user is enrolled in, for the frontpage.
     *
     * @return string
     */
    public function frontpage_my_courses() {
        if (!isloggedin() || isguestuser()) {
            return '';
        }

        global $CFG;
        $courses = enrol_get_my_courses('summary, summaryformat, startdate, enddate, category');
        if (empty($courses)) {
            return '';
        }

        $totalcount = count($courses);
        if ($totalcount > $CFG->frontpagecourselimit) {
            $courses = array_slice($courses, 0, $CFG->frontpagecourselimit, true);
        }

        return $this->uptex_render_course_grid($courses, $totalcount);
    }

    /**
     * Builds the enriched Stitch-styled course grid shared by both frontpage entry points.
     *
     * Every field is real course data (title, link, category, teachers, computed duration);
     * the only non-database content is the Unsplash fallback image used when a course has no
     * uploaded overview image, and the static "Verificable en Credly" badge, which is design-
     * brief UI chrome rather than a real Credly integration check.
     *
     * @param array $courses stdClass or core_course_list_element records
     * @param int $totalcount total course count before any limit was applied
     * @return string
     */
    protected function uptex_render_course_grid($courses, int $totalcount): string {
        $carddata = [];
        foreach ($courses as $course) {
            $listcourse = ($course instanceof core_course_list_element)
                ? $course
                : new core_course_list_element($course);
            $carddata[] = $this->uptex_export_course_card($listcourse);
        }

        return $this->render_from_template('theme_saec/core_course/frontpage_courses_grid', [
            'hascourses' => !empty($carddata),
            'courses' => $carddata,
            'hasmore' => $totalcount > count($carddata),
            'catalogueurl' => (new moodle_url('/course/index.php'))->out(false),
        ]);
    }

    /**
     * Exports one course into the exact context theme_saec/core_course/frontpage_courses_grid expects.
     *
     * Image resolution is delegated to \theme_saec\course_helper (shared
     * with every authenticated dashboard/catalog card — see that class'
     * docblock for why it goes through core's own course_image cache
     * rather than building the pluginfile URL by hand). No login/enrolment
     * is required to read it: overviewfiles is served to guests as long as
     * $CFG->forcelogin is off (confirmed on this install), so the same
     * resolved URL that works for alumno_top1/maestro_b1/admin also works
     * unauthenticated. When a course has no image, hascourseimage is false
     * and the template falls back to the shared institutional placeholder
     * banner — never an external stock-photo URL.
     *
     * @param core_course_list_element $course
     * @return array
     */
    protected function uptex_export_course_card(core_course_list_element $course): array {
        $courseimage = \theme_saec\course_helper::get_course_image_url((object) ['id' => $course->id]);

        $category = core_course_category::get($course->category, IGNORE_MISSING);
        $categoryname = $category ? format_string($category->name) : '';
        $categorykey = $this->uptex_match_category_key($categoryname);
        $tagcolor = self::CATEGORY_TAG_COLORS[$categorykey] ?? 'green';

        // Duración: calculada de start/end date reales del curso; si no están
        // configurados, se omite el renglón de meta en vez de inventar un valor.
        $duration = null;
        if (!empty($course->startdate) && !empty($course->enddate) && $course->enddate > $course->startdate) {
            $weeks = max(1, (int) round(($course->enddate - $course->startdate) / WEEKSECS));
            $duration = get_string('courseduration', 'theme_saec', $weeks);
        }

        $teachers = [];
        foreach ($course->get_course_contacts() as $contact) {
            $teachers[] = $contact['username'];
        }
        $instructor = implode(', ', $teachers);

        return [
            'id' => $course->id,
            'fullname' => format_string($course->fullname, true, ['context' => context_course::instance($course->id)]),
            'viewurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'hascourseimage' => !empty($courseimage),
            'courseimage' => $courseimage,
            'categoryname' => $categoryname,
            'tagcolor' => $tagcolor,
            'hasduration' => !empty($duration),
            'duration' => $duration,
            'hasinstructor' => !empty($instructor),
            'instructor' => $instructor,
        ];
    }

    /**
     * Matches a category name against a small keyword table used for both the fallback image
     * and the tag colour. Falls back to the empty string (green tag, id-rotated image) when
     * nothing matches — never fabricates a category that is not really the course's own.
     *
     * @param string $categoryname
     * @return string
     */
    protected function uptex_match_category_key(string $categoryname): string {
        $name = core_text::strtolower($categoryname);
        foreach (array_keys(self::CATEGORY_TAG_COLORS) as $keyword) {
            if (strpos($name, $keyword) !== false) {
                return $keyword;
            }
        }
        return '';
    }
}

/**
 * theme_saec badges renderer override (Fase 12 — Institutional Credential
 * Verification Portal, /badges/badge.php).
 *
 * Same theme_overridden_renderer_factory mechanism as
 * theme_saec_core_course_renderer above: this legacy-named class,
 * `theme_saec_core_badges_renderer extends core_badges_renderer`, transparently
 * replaces the core renderer for every call to $PAGE->get_renderer('core',
 * 'badges') — used by both badges/badge.php (public verification) and the
 * badge-class preview — with zero core file changes.
 *
 * Only render_issued_badge() is overridden, and only to ADD fields
 * (verifyurl, hasexpiry, linkedinurl) to the same context
 * issued_badge::export_for_template() already builds; every existing field is
 * passed through untouched to templates/core_badges/issued_badge.mustache
 * (this theme's override of that template).
 */
class theme_saec_core_badges_renderer extends core_badges_renderer {

    /**
     * @param \core_badges\output\issued_badge $ibadge
     * @return string
     */
    protected function render_issued_badge(\core_badges\output\issued_badge $ibadge) {
        $data = $ibadge->export_for_template($this);

        $verifyurl = (new moodle_url('/badges/badge.php', ['hash' => $ibadge->hash]))->out(false);
        $data->verifyurl = $verifyurl;
        $data->hasexpiry = !empty($data->expiredate) || !empty($data->expireddate);

        // Plain aliases for theme_saec/components/badge_credential_canvas
        // (shared with the "Mi Mochila" detail modal, see badges_page::
        // get_earned_badges()) — recipientname is already native on $data,
        // no alias needed for that one field.
        $data->imageurl = $data->badgeimage;
        $data->credentialtitle = $data->badgename;

        // LinkedIn's "Add to Profile" only makes sense for the badge's own
        // recipient — downloadurl is already gated on that same condition
        // by issued_badge::export_for_template(), so reusing its presence
        // avoids re-deriving the $USER->id == recipient->id check here.
        if (!empty($data->downloadurl)) {
            $linkedinparams = [
                'startTask' => 'CERTIFICATION_NAME',
                'name' => $data->badgename,
                'organizationName' => $data->issuedby,
                'issueYear' => (int) date('Y', $data->badgeissuedon),
                'issueMonth' => (int) date('n', $data->badgeissuedon),
                'certUrl' => $verifyurl,
                'certId' => $ibadge->hash,
            ];
            if (!empty($data->expiredate)) {
                $linkedinparams['expirationYear'] = (int) date('Y', $data->expiredate);
                $linkedinparams['expirationMonth'] = (int) date('n', $data->expiredate);
            }
            $data->linkedinurl = (new moodle_url('https://www.linkedin.com/profile/add', $linkedinparams))->out(false);
        }

        return $this->render_from_template('core_badges/issued_badge', $data);
    }
}
