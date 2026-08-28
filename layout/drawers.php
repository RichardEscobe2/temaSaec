<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');

use theme_saec\dashboard\admin_courses_page;
use theme_saec\dashboard\admin_dashboard;
use theme_saec\dashboard\admin_reports_page;
use theme_saec\dashboard\analytics_page;
use theme_saec\dashboard\badges_page;
use theme_saec\dashboard\courses_page;
use theme_saec\dashboard\grader_hub_page;
use theme_saec\dashboard\student_dashboard;
use theme_saec\dashboard\teacher_course_view_page;
use theme_saec\dashboard\teacher_courses_page;
use theme_saec\dashboard\teacher_dashboard;
use theme_saec\dashboard\teacher_progress_page;

// NOTA CRÍTICA (Fase 18 — bug de "doctype() no llamado" en mod_assign):
// $PAGE y $OUTPUT NO se globalizan aquí a propósito. core_renderer::
// render_page_layout() (lib/outputrenderers.php) hace `include($layoutfile)`
// DESDE DENTRO de un método de instancia, y justo antes define variables
// LOCALES `$OUTPUT = $this;` / `$PAGE = $this->page;` / `$COURSE = ...` —
// con su propio comentario explícito: "this object may, or may not, be the
// same as the global $OUTPUT object". Casi siempre SÍ coinciden (por eso
// nunca se notó), pero cuando un renderer llama a $this->output->header()
// sobre una instancia de core_renderer DISTINTA a la global (el patrón
// exacto de mod_assign\output\renderer::render_assign_header(), que llama
// $this->output->header() en vez de que view.php llame $OUTPUT->header()
// directamente), declarar `global $OUTPUT;` aquí SOBRESCRIBE esa variable
// local con el $OUTPUT global equivocado — todo lo que este layout renderiza
// (incluido el <!DOCTYPE html> vía {{> theme_boost/head}}) termina en el
// objeto renderer INCORRECTO, y la instancia real nunca marca su
// contenttype, disparando el "The page layout file did not call
// $OUTPUT->doctype()" de core_renderer::header(). Usar las variables
// LOCALES que render_page_layout() ya deja listas es exactamente lo que
// pide el comentario de core ("people writing Moodle code expect the
// current renderer to be called $OUTPUT... so define a variable...").
global $USER, $DB, $CFG, $SITE;

// 1. DETECCIÓN DE ROLES SAEC
$is_admin = is_siteadmin($USER);
$is_teacher = false;
$my_courses = [];

if (!$is_admin && isloggedin() && !isguestuser()) {
    $my_courses = enrol_get_my_courses(['id']);
    foreach ($my_courses as $c) {
        $context = context_course::instance($c->id);
        if (has_capability('moodle/course:update', $context)) {
            $is_teacher = true;
            break;
        }
    }
}

// 1b. KPI DEL BANNER DE BIENVENIDA (/my/): cifras reales, no de ejemplo.
// Sólo se calcula en el layout de dashboard para no añadir consultas
// innecesarias al resto de páginas que también usan drawers.php.
$dashboardkpi = null;
if (isloggedin() && !isguestuser() && $PAGE->pagelayout === 'mydashboard') {
    $coursecount = $is_admin
        ? $DB->count_records_select('course', 'id <> :siteid', ['siteid' => SITEID])
        : count($my_courses);
    $dashboardkpi = [
        'label' => get_string('navmycourses', 'theme_saec'),
        'value' => $coursecount,
    ];
}

// 1c. PANEL DEL ALUMNO (/my/, Fase 2): construye el contexto completo de
// \theme_saec\dashboard\student_dashboard (Fase 1) y pre-renderiza
// theme_saec/student_dashboard sólo cuando el usuario logueado es Alumno.
// student_dashboard::get_dashboard_context() ya hace su propia
// comprobación de rol y devuelve null para Docente/Administrador, así que
// esos roles siguen viendo el contenido nativo de /my/ (bloques) sin
// cambios — ver el condicional showstudentdashboard en drawers.mustache.
$studentdashboardhtml = null;
if (isloggedin() && !isguestuser() && $PAGE->pagelayout === 'mydashboard') {
    $studentdashboardcontext = student_dashboard::get_dashboard_context();
    if ($studentdashboardcontext !== null) {
        $studentdashboardcontext['mycoursesurl'] = (new moodle_url('/my/courses.php'))->out(false);
        $studentdashboardcontext['calendarurl'] = (new moodle_url('/calendar/view.php'))->out(false);
        $studentdashboardhtml = $OUTPUT->render_from_template('theme_saec/student_dashboard', $studentdashboardcontext);

        // my/index.php already ran by this point and populated $PAGE->button
        // with its native "Customise this page" control (moodle/my:manageblocks
        // — on by default for every logged-in user, editing the block region
        // student_dashboard.mustache replaces entirely). Clear it before
        // full_header() reads $PAGE->button below, rather than hiding it with
        // CSS: there is no block region left for a student to customise here,
        // so the control has nothing to do and shouldn't exist in the DOM.
        $PAGE->set_button('');
    }
}

// 1c-bis. PANEL DEL DOCENTE (/my/, Sprint 1): mismo principio que el bloque
// 1c de arriba, pero para el rol Docente — construye el contexto completo
// de \theme_saec\dashboard\teacher_dashboard y pre-renderiza
// theme_saec/teacher_dashboard sólo cuando el usuario logueado es Docente.
// teacher_dashboard::get_dashboard_context() ya hace su propia
// comprobación de rol y devuelve null para Alumno/Administrador, así que
// esos roles no se ven afectados — ver el condicional showteacherdashboard
// en drawers.mustache.
$teacherdashboardhtml = null;
if (isloggedin() && !isguestuser() && $PAGE->pagelayout === 'mydashboard') {
    $teacherdashboardcontext = teacher_dashboard::get_dashboard_context();
    if ($teacherdashboardcontext !== null) {
        $teacherdashboardcontext['mycoursesurl'] = (new moodle_url('/my/courses.php'))->out(false);
        $teacherdashboardcontext['calendarurl'] = (new moodle_url('/calendar/view.php'))->out(false);
        $teacherdashboardhtml = $OUTPUT->render_from_template('theme_saec/teacher_dashboard', $teacherdashboardcontext);

        // Same rationale as the student dashboard block above: no block
        // region is left for a teacher to customise on this replaced view.
        $PAGE->set_button('');
    }
}

// 1c-ter. PANEL EJECUTIVO DEL ADMINISTRADOR (/my/, Admin Command Center):
// mismo principio que los bloques 1c/1c-bis de arriba, pero para el rol
// Administrador — antes, un Administrador en /my/ veía el dashboard nativo
// de Moodle sin ningún contenido propio de theme_saec. KPIs sitewide, barra
// de acciones rápidas y resúmenes de cursos/usuarios ahora viven aquí en vez
// de en theme/saec/pages/admin_hub.php, que ahora es sólo el índice de
// Administración del Sitio (categorías + búsqueda).
// admin_dashboard::get_dashboard_context() ya hace su propia comprobación de
// rol y devuelve null para Alumno/Docente.
$admindashboardhtml = null;
if (isloggedin() && !isguestuser() && $PAGE->pagelayout === 'mydashboard') {
    $admindashboardcontext = admin_dashboard::get_dashboard_context();
    if ($admindashboardcontext !== null) {
        $admindashboardhtml = $OUTPUT->render_from_template('theme_saec/admin_dashboard', $admindashboardcontext);

        // Same rationale as the student/teacher dashboard blocks above: no
        // block region is left for an admin to customise on this replaced view.
        $PAGE->set_button('');
    }
}

// 1d. PÁGINA "MIS CURSOS" (/my/courses.php, Fase 8): construye el contexto
// de \theme_saec\dashboard\courses_page y pre-renderiza
// theme_saec/my_courses_page sólo para Alumnos. block_myoverview sigue
// renderizándose (my/courses.php es core — ver el comentario grande en
// drawers.mustache sobre por qué su HTML no puede simplemente omitirse),
// pero scss/custom.scss lo oculta en esta página específica
// (body.page-mycourses .block-myoverview) y este catálogo lo sustituye
// visualmente en el mismo slot.
$coursespagehtml = null;
if (isloggedin() && !isguestuser() && $PAGE->pagelayout === 'mycourses' && student_dashboard::is_student()) {
    $coursespagecontext = courses_page::get_context();
    $coursespagehtml = $OUTPUT->render_from_template('theme_saec/my_courses_page', $coursespagecontext);
}

// 1d-bis. PÁGINA "MIS CURSOS" PARA DOCENTES (/my/courses.php, Sprint 1):
// mismo slot que el bloque 1d de arriba, pero para el rol Docente. Antes de
// esto, un Docente en esta página no obtenía NI el catálogo propio (guardado
// a is_student()) NI el bloque nativo block_myoverview (oculto de forma
// incondicional por la regla `body.page-mycourses .block_myoverview` de
// scss/custom.scss, sin importar el rol) — resultado: área de contenido en
// blanco. teacher_courses_page::get_context() ya hace su propia
// comprobación de rol y devuelve null para Alumno/Administrador.
$teachercoursespagehtml = null;
if (isloggedin() && !isguestuser() && $PAGE->pagelayout === 'mycourses') {
    $teachercoursespagecontext = teacher_courses_page::get_context();
    if ($teachercoursespagecontext !== null) {
        $teachercoursespagehtml = $OUTPUT->render_from_template('theme_saec/teacher_courses_page', $teachercoursespagecontext);
    }
}

// 1d-ter. CATÁLOGO GLOBAL DE CURSOS PARA ADMINISTRADOR (/my/courses.php):
// mismo slot que los bloques 1d/1d-bis de arriba, pero para el rol
// Administrador — antes, un Administrador en esta página no obtenía NI un
// catálogo propio NI el bloque nativo block_myoverview (oculto de forma
// incondicional por la regla `body.page-mycourses .block_myoverview` de
// scss/custom.scss, sin importar el rol), resultando en un área de
// contenido en blanco. A diferencia del resumen capado de admin_dashboard,
// éste es el catálogo completo de auditoría: todos los cursos del sistema,
// visibles y ocultos. admin_courses_page::get_context() ya hace su propia
// comprobación de rol y devuelve null para Alumno/Docente.
$admincoursespagehtml = null;
if (isloggedin() && !isguestuser() && $PAGE->pagelayout === 'mycourses') {
    $admincoursespagecontext = admin_courses_page::get_context();
    if ($admincoursespagecontext !== null) {
        $admincoursespagehtml = $OUTPUT->render_from_template('theme_saec/admin_courses_page', $admincoursespagecontext);
    }
}

// 1e. PÁGINA "MI MOCHILA DE INSIGNIAS" (/badges/mybadges.php, Fase 9):
// construye el contexto de \theme_saec\dashboard\badges_page y
// pre-renderiza theme_saec/badges_page sólo para Alumnos. El pagelayout de
// esta página ('standard') lo comparten decenas de páginas ajenas, así que
// se detecta por URL exacta en vez de por pagelayout. Moodle no añade una
// clase de body específica para esta página (a diferencia de
// 'page-mycourses', que my/courses.php sí agrega) y para cuando este layout
// se ejecuta — dentro de core_renderer::header(), ver el "output has
// already started" que dispara $PAGE->add_body_class() aquí — ya es
// demasiado tarde para registrar una en $PAGE. En su lugar se añade
// directamente al arreglo local $extraclasses de más abajo, que
// $OUTPUT->body_attributes() sí acepta como clases extra independientes del
// listado ya cerrado de $PAGE; así scss/custom.scss puede ocultar el
// contenido nativo del renderer de insignias con ese alcance
// (body.page-mybadges) sin afectar otras páginas bajo /badges/.
$badgespagehtml = null;
$ismybadgespage = ($PAGE->url->out_omit_querystring() === (new moodle_url('/badges/mybadges.php'))->out_omit_querystring());
if ($ismybadgespage && isloggedin() && !isguestuser() && student_dashboard::is_student()) {
    $badgespagecontext = badges_page::get_context();
    $badgespagehtml = $OUTPUT->render_from_template('theme_saec/badges_page', $badgespagecontext);
}

// 1f. PORTAL DE VERIFICACIÓN DE CREDENCIALES (/badges/badge.php, Fase 12):
// esta página es pública (verificadores externos sin sesión también la
// visitan), así que a diferencia de badges_page.php arriba NO se reemplaza
// su contenido — theme_saec_core_badges_renderer (renderers.php) ya
// restyla el output nativo vía un override de plantilla/renderer. Sólo se
// necesita una clase de body propia para ocultar el encabezado nativo
// duplicado (#page-header) sin afectar otras páginas con pagelayout 'base'.
$isbadgeverifypage = ($PAGE->url->out_omit_querystring() === (new moodle_url('/badges/badge.php'))->out_omit_querystring());

// 1g. PÁGINA "MI RENDIMIENTO" (/grade/report/overview/index.php, Fase 13):
// construye el contexto de \theme_saec\dashboard\analytics_page y
// pre-renderiza theme_saec/analytics_page sólo para Alumnos. Igual que
// badges_page.php: el reporte nativo de calificaciones sigue renderizándose
// (es core), pero scss/custom.scss lo oculta en esta página específica
// (body.page-analytics) y este panel lo sustituye visualmente en el mismo
// slot.
$analyticspagehtml = null;
$isanalyticspage = ($PAGE->url->out_omit_querystring()
    === (new moodle_url('/grade/report/overview/index.php'))->out_omit_querystring());
if ($isanalyticspage && isloggedin() && !isguestuser() && student_dashboard::is_student()) {
    $analyticspagecontext = analytics_page::get_context();
    $analyticspagehtml = $OUTPUT->render_from_template('theme_saec/analytics_page', $analyticspagecontext);
}

// 1g-bis. GRADEBOOK "COURSE SELECTION HUB" (/grade/report/overview/index.php,
// Sprint 4): teacher-facing counterpart to 1g above, same URL. Gated on
// $PAGE->course->id == SITEID rather than just $isanalyticspage, because
// $isanalyticspage matches this page for ANY querystring (out_omit_querystring
// ignores id/userid) — including ?id=<courseid>&userid=<studentid>, the URL a
// teacher actually lands on after picking a student from graded_users_selector.
// grade_report_overview::print_teacher_table() (what this overlay replaces)
// only ever runs in core when $courseid == SITEID (see index.php's branching);
// on a specific-course/specific-user request core instead builds #overview-grade
// (that student's per-course grades), which must render untouched — already
// styled by scss/saec/_grade_overview.scss and would be wrongly replaced by
// this generic "pick a course" hub without this guard.
// $analyticspagehtml === null keeps this mutually exclusive with 1g: a rare
// dual-role account (Student somewhere AND teaches elsewhere) deterministically
// keeps seeing the Student "Mi Rendimiento" overlay, matching 1g's own
// existing precedence.
$graderhubpagehtml = null;
if ($isanalyticspage && $analyticspagehtml === null && isloggedin() && !isguestuser()
        && $PAGE->course->id == SITEID) {
    $graderhubpagecontext = grader_hub_page::get_context();
    if ($graderhubpagecontext !== null) {
        $graderhubpagehtml = $OUTPUT->render_from_template('theme_saec/grader_hub_page', $graderhubpagecontext);
    }
}

// 1g-quater. "ESTUDIANTES Y PROGRESO" — course-scoped teacher analytics
// (/grade/report/user/index.php, Fase 20). This exact URL serves two
// different native reports depending on the userid querystring param:
// userid=0 (or absent) shows the whole-course table this overlay replaces;
// a real userid=<studentid> shows core's own untouched per-student report
// (this overlay's own roster CTAs link straight into that second form,
// so they must never be intercepted here — hence the strict === 0 check,
// not just a truthiness check, matching /grade/report/user/index.php's
// own optional_param('userid', null, PARAM_INT) semantics where an
// explicit userid=0 means "show all").
$teacherprogresspagehtml = null;
$isteacherprogresspage = ($PAGE->url->out_omit_querystring()
    === (new moodle_url('/grade/report/user/index.php'))->out_omit_querystring());
if ($isteacherprogresspage && isloggedin() && !isguestuser()
        && optional_param('userid', 0, PARAM_INT) === 0) {
    $teacherprogresspagecontext = teacher_progress_page::get_context();
    if ($teacherprogresspagecontext !== null) {
        $teacherprogresspagehtml = $OUTPUT->render_from_template(
            'theme_saec/teacher_progress_page',
            $teacherprogresspagecontext
        );
    }
}

// 1g-ter. ATTENDANCE SESSION CARDS (/mod/attendance/manage.php, Sprint 9):
// unlike every overlay above, this does NOT hide or replace the native
// session table — its real per-row action links (take/edit/delete) carry
// a real sesskey the theme has no business reconstructing. A small JS
// pass (below) reads each row's own "take attendance" link to recover its
// real sessionid, then injects the real taken-status pill + attendee-count
// badge \theme_saec\dashboard\attendance_manage_page::get_session_badges()
// computes, keyed by sessionid, directly into that row — scss/saec/
// _attendance_manage.scss reflows the table into cards, the native <a>
// elements underneath are never touched.
$isattendancemanagepage = ($PAGE->url->out_omit_querystring()
    === (new moodle_url('/mod/attendance/manage.php'))->out_omit_querystring());
if ($isattendancemanagepage && isloggedin() && !isguestuser()) {
    $attendancecmid = optional_param('id', 0, PARAM_INT);
    if ($attendancecmid) {
        $attendancebadges = \theme_saec\dashboard\attendance_manage_page::get_session_badges($attendancecmid);
        if (!empty($attendancebadges)) {
            $attendancebadgesjson = json_encode($attendancebadges);
            $attendancetakenlabel = json_encode(get_string('attendancemanagetaken', 'theme_saec'));
            $attendancependinglabel = json_encode(get_string('attendancemanagepending', 'theme_saec'));
            $attendancepresentlabel = json_encode(get_string('attendancemanagepresent', 'theme_saec'));
            $PAGE->requires->js_init_code(<<<JS
require(['jquery'], function(\$) {
    var badges = {$attendancebadgesjson};
    var takenLabel = {$attendancetakenlabel};
    var pendingLabel = {$attendancependinglabel};
    var presentLabel = {$attendancepresentlabel};

    document.querySelectorAll('.attsessions_manage_table table.generaltable tbody tr').forEach(function(row) {
        if (row.children.length < 6) {
            return; // the bulk-actions footer row, not a real session row.
        }
        var takelink = row.querySelector('a');
        if (!takelink) {
            return;
        }
        var match = takelink.href.match(/sessionid=(\d+)/);
        if (!match || !badges[match[1]]) {
            return;
        }
        var info = badges[match[1]];
        var descCell = row.children[4];

        var statusPill = document.createElement('span');
        statusPill.className = 'saec-attendance-session-card__status ' +
            (info.taken ? 'saec-attendance-session-card__status--taken' : 'saec-attendance-session-card__status--pending');
        statusPill.textContent = info.taken ? takenLabel : pendingLabel;
        descCell.appendChild(statusPill);

        if (info.taken) {
            var countBadge = document.createElement('span');
            countBadge.className = 'saec-attendance-session-card__count';
            countBadge.textContent = info.present + '/' + info.total + ' ' + presentLabel;
            descCell.appendChild(countBadge);
        }
    });
});
JS);
        }
    }
}

// 1g-quater. "MARCAR TODOS COMO PRESENTES" (/mod/attendance/take.php,
// Sprint 9): mod_attendance's only native bulk-marking mechanism is the
// "Configurar estatus para" dropdown (pick a status, applies to the
// selected/all rows) — a real feature, but not the one-click utility this
// task asks for. Rather than reconstruct/duplicate that dropdown's own
// apply logic, this button directly checks every real radio in column c2
// (the "Presente" column — same fixed-position assumption documented in
// scss/saec/_attendance_take.scss) and dispatches a real change event, so
// anything else listening for it still fires. The teacher still has to
// press the native "Guardar" button themselves — this only pre-fills the
// form, it never submits on its own.
$isattendancetakepage = ($PAGE->url->out_omit_querystring()
    === (new moodle_url('/mod/attendance/take.php'))->out_omit_querystring());
if ($isattendancetakepage && isloggedin() && !isguestuser()) {
    $attendancemarkalllabel = json_encode(get_string('attendancemarkallpresent', 'theme_saec'));
    // P/L/E/A status pill labels (Sprint 10 — enterprise UI overhaul): the
    // native radios in columns c2..c5 render bare, with no wrapping <label>
    // and no visible text anywhere (verified live) — a plain grid of
    // identical, unlabelled circles until one is picked. CSS alone can't
    // add real content here (generated ::before/::after content does not
    // render on <input> — a replaced element, per spec — even with
    // appearance:none), so each radio is moved (not cloned, to preserve its
    // checked state/listeners) into a real <label> wrapping a visible
    // letter + an aria-label, turning the tiny dot into an accessible,
    // full-size clickable "pill button" — scss/saec/_attendance_take.scss's
    // .saec-attendance-pill classes do the rest.
    $attendancestatuslabels = json_encode([
        'c2' => ['letter' => 'P', 'variant' => 'p', 'label' => get_string('attendancestatuspresentlabel', 'theme_saec')],
        'c3' => ['letter' => 'L', 'variant' => 'l', 'label' => get_string('attendancestatuslatelabel', 'theme_saec')],
        'c4' => ['letter' => 'E', 'variant' => 'e', 'label' => get_string('attendancestatusexcusedlabel', 'theme_saec')],
        'c5' => ['letter' => 'A', 'variant' => 'a', 'label' => get_string('attendancestatusabsentlabel', 'theme_saec')],
    ]);
    $PAGE->requires->js_init_code(<<<JS
require(['jquery'], function(\$) {
    var table = document.querySelector('table.takelist');
    var submitbtn = document.querySelector('input[type="submit"].btn-primary');
    if (!table || !submitbtn) {
        return;
    }

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'saec-attendance-mark-all-present';
    button.textContent = {$attendancemarkalllabel};
    button.addEventListener('click', function() {
        table.querySelectorAll('td.cell.c2 input[type="radio"]').forEach(function(radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', {bubbles: true}));
        });
    });
    submitbtn.insertAdjacentElement('afterend', button);

    var statuslabels = {$attendancestatuslabels};
    table.querySelectorAll('tbody tr').forEach(function(row) {
        Object.keys(statuslabels).forEach(function(cellclass) {
            var td = row.querySelector('td.cell.' + cellclass);
            var radio = td ? td.querySelector('input[type="radio"]') : null;
            if (!radio) {
                return;
            }
            var info = statuslabels[cellclass];
            var label = document.createElement('label');
            label.className = 'saec-attendance-pill saec-attendance-pill--' + info.variant;
            var letter = document.createElement('span');
            letter.className = 'saec-attendance-pill__letter';
            letter.setAttribute('aria-hidden', 'true');
            letter.textContent = info.letter;
            radio.setAttribute('aria-label', info.label);
            td.insertBefore(label, radio);
            label.appendChild(radio);
            label.appendChild(letter);
        });
    });
});
JS);
}

// 1g-quinquies. CALIFICADOR GENERAL — CELDAS SIN CALIFICAR (Sprint 10,
// /grade/report/grader/index.php): the grid's "no grade yet" cells render
// as a literal "-" text node inside .gradevalue, with no distinct core
// class to select on (verified live: identical DOM/classes as a graded
// cell) — this tags them so scss/saec/_grader_report.scss can mute them.
$isgraderreportpage = ($PAGE->url->out_omit_querystring()
    === (new moodle_url('/grade/report/grader/index.php'))->out_omit_querystring());
if ($isgraderreportpage && isloggedin() && !isguestuser()) {
    $PAGE->requires->js_init_code(<<<'JS'
require(['jquery'], function($) {
    document.querySelectorAll('table.gradereport-grader-table .gradevalue').forEach(function(span) {
        if (span.textContent.trim() === '-') {
            span.classList.add('saec-grade-empty');
        }
    });
});
JS);
}

// 1h. PORTAL DE CONFIGURACIÓN DE CUENTA (/user/preferences.php, Fase 16):
// a diferencia de las páginas anteriores, ésta aplica a CUALQUIER usuario
// logueado (alumno, docente o administrador), no sólo a Alumnos — por eso
// no usa student_dashboard::is_student() como guarda. El grid nativo de
// categorías (Cuenta del usuario / Blogs / Insignias) sigue viniendo 100%
// de core_user::preferences_group() vía navigation_node — sólo su plantilla
// (core/preferences_groups) se sobrescribe (ver
// templates/core/preferences_groups.mustache) para convertirlo en pestañas;
// aquí sólo se construye la cabecera/hero que se inyecta ENCIMA de ese
// contenido nativo restilizado, igual que el resto de páginas de este tema.
$settingspagehtml = null;
$issettingspage = ($PAGE->url->out_omit_querystring() === (new moodle_url('/user/preferences.php'))->out_omit_querystring());
if ($issettingspage && isloggedin() && !isguestuser()) {
    $prefsrolelabel = $is_admin
        ? get_string('roleadmin', 'theme_saec')
        : ($is_teacher ? get_string('roleteacher', 'theme_saec') : get_string('rolestudent', 'theme_saec'));

    $prefsuserpicture = new user_picture($USER);
    $prefsuserpicture->size = 100;

    $settingspagecontext = [
        'avatarurl' => $prefsuserpicture->get_url($PAGE)->out(false),
        'fullname' => fullname($USER),
        'email' => $USER->email,
        'rolelabel' => $prefsrolelabel,
    ];
    $settingspagehtml = $OUTPUT->render_from_template('theme_saec/preferences_hero', $settingspagecontext);
}

// 1h-bis. "REPORTES Y AUDITORÍA" — platform-wide admin audit dashboard
// (/report/log/index.php, Fase 20). Unlike the teacher-facing "Estudiantes
// y Progreso" overlay above, this native report has no required 'id'
// param and no per-record single-item view to stay compatible with — the
// whole page is always the sitewide log table, so the guard is simply
// "is this the URL, is a real logged-in site admin viewing it."
$adminreportspagehtml = null;
$isadminreportspage = ($PAGE->url->out_omit_querystring()
    === (new moodle_url('/report/log/index.php'))->out_omit_querystring());
if ($isadminreportspage && isloggedin() && !isguestuser() && $is_admin) {
    $adminreportspagecontext = admin_reports_page::get_context();
    if ($adminreportspagecontext !== null) {
        $adminreportspagehtml = $OUTPUT->render_from_template('theme_saec/admin_reports_page', $adminreportspagecontext);
    }
}

// 1i. VISTA DE CURSO — SaaS OVERLAY (/course/view.php, Fase 17): a diferencia
// de todas las páginas anteriores, aquí NO se oculta ni se restila vía
// override de plantilla el contenido nativo de secciones/actividades —
// theme_saec_core_course_renderer (renderers.php) ya existe para el
// frontpage y no se toca aquí. Sólo se inyecta un hero + barra de pestañas
// ENCIMA, y un sidebar A UN LADO (ver el wrapper condicional en
// drawers.mustache), dejando 100% intacto el HTML/JS de edición de curso,
// drag-and-drop y togglers de finalización — course_view_page::get_context()
// ya se auto-desactiva (null) si $PAGE->user_is_editing() o si el usuario no
// es Alumno, así que un profesor editando ve el curso nativo sin ninguna
// interferencia de este tema.
$courseviewheaderhtml = null;
$courseviewsidebarhtml = null;
$iscourseviewpage = ($PAGE->url->out_omit_querystring() === (new moodle_url('/course/view.php'))->out_omit_querystring());
// NOTA: nunca uses empty()/!empty() sobre $PAGE->course. moodle_page define
// __get() (carga perezosa de course/cm/category/etc.) pero NO __isset() —
// sin __isset(), PHP trata CUALQUIER propiedad mágica como "no existente"
// para empty()/isset(), sin importar lo que __get() devolvería realmente
// (confirmado con un caso mínimo fuera de Moodle). $PAGE->course nunca es
// null (cae a $SITE si nunca se llamó set_course()), así que comparar su id
// directamente es both correcto y evita esta trampa por completo.
if ($iscourseviewpage && $PAGE->course->id != SITEID) {
    $courseviewcontext = \theme_saec\dashboard\course_view_page::get_context($PAGE->course->id);
    if ($courseviewcontext !== null) {
        $courseviewheaderhtml = $OUTPUT->render_from_template('theme_saec/components/course_view_header', $courseviewcontext);
        $courseviewsidebarhtml = $OUTPUT->render_from_template('theme_saec/components/course_view_sidebar', $courseviewcontext['sidebar']);
        // Hover/focus prefetch for the hero tab bar (Participantes/
        // Calificaciones/Competencias) — those are real navigations to
        // separate core pages, so this only shaves perceived latency on
        // click, it doesn't avoid the navigation itself.
        $PAGE->requires->js(new moodle_url('/theme/saec/javascript/course_tab_prefetch.js'), true);
    }
}

// 1i-bis. VISTA DE CURSO PARA DOCENTES — SaaS OVERLAY (/course/view.php,
// Sprint 3): mismo principio que el bloque 1i de arriba, pero para el rol
// Docente. A diferencia de la variante de Alumno, esta NO se desactiva
// durante $PAGE->user_is_editing() — para un Docente el modo edición es un
// estado cotidiano, no un caso excepcional, y el propio botón "Activar
// edición" vive dentro de este header (editbuttonhtml, reubicado vía
// $OUTPUT->page_heading_button() — la misma API pública que usan los layouts
// nativos, nunca reimplementado). El contenido nativo de secciones/
// actividades permanece 100% intacto tanto en modo edición como fuera de
// él — teacher_course_view_page::get_context() sólo se activa para Docentes
// reales (is_teacher()), nunca para Alumnos ni Administradores.
$teachercourseviewheaderhtml = null;
$teachercourseviewsidebarhtml = null;
if ($iscourseviewpage && $PAGE->course->id != SITEID) {
    $teachercourseviewcontext = teacher_course_view_page::get_context($PAGE->course->id);
    if ($teachercourseviewcontext !== null) {
        $teachercourseviewheaderhtml = $OUTPUT->render_from_template(
            'theme_saec/components/teacher_course_view_header',
            $teachercourseviewcontext
        );
        $teachercourseviewsidebarhtml = $OUTPUT->render_from_template(
            'theme_saec/components/teacher_course_view_sidebar',
            $teachercourseviewcontext['sidebar']
        );
    }
}

// 1j. VISTA DE TAREA — SaaS OVERLAY (/mod/assign/view.php, Fase 21): mismo
// principio que 1i — el formulario de envío nativo (filemanager, mform,
// botones Guardar cambios/Cancelar) NUNCA se toca ni se restructura en el
// DOM; sólo se inyecta un header de estado (píldora Pendiente/Entregado/
// Calificado + fecha de vencimiento) y una columna izquierda de workspace
// (Instrucciones — real o, si el docente no escribió ninguna, un aviso
// honesto de "sin instrucciones" — + Rúbrica real si el método de
// calificación activo es "rubric") que la rejilla CSS de drawers.mustache
// coloca junto a output.main_content (columna derecha, 65/35). La rejilla
// 65/35 es ahora INCONDICIONAL para toda vista de tarea de un alumno — ya
// no existe un caso de "columna izquierda vacía" desde que
// assign_view_page::get_workspace() siempre devuelve contenido real para
// Instrucciones (Fase 21). assign_view_page::get_context() sigue
// auto-desactivándose (null) si el usuario está editando o si tiene la
// capacidad mod/assign:grade (profesores ven la página nativa de
// calificación sin ninguna interferencia de este tema).
$assignheaderhtml = null;
$assignworkspacehtml = null;
$assignteachersummaryhtml = null;
$isassignviewpage = ($PAGE->url->out_omit_querystring() === (new moodle_url('/mod/assign/view.php'))->out_omit_querystring());
// mod/assign/view.php renders entirely different UI per ?action= (the
// submissions table for 'grading', the single-grading iframe for
// 'grader', the submission mform for 'editsubmission', ...) — the native
// "Sumario de calificaciones" block this teacher overlay replaces (1j-bis
// below) and assign_view_page's student overlay both only ever render on
// the DEFAULT landing action (empty or 'view'), so out_omit_querystring()
// alone (which strips ?action= too) isn't enough of a guard on its own.
$isassignlandingaction = in_array(optional_param('action', '', PARAM_ALPHA), ['', 'view'], true);
if ($isassignviewpage && $isassignlandingaction && $PAGE->cm && $PAGE->cm->modname === 'assign') {
    $assignviewcontext = \theme_saec\dashboard\assign_view_page::get_context($PAGE->cm->id);
    if ($assignviewcontext !== null) {
        $assignheaderhtml = $OUTPUT->render_from_template('theme_saec/components/assign_header', $assignviewcontext['header']);
        $assignworkspacehtml = $OUTPUT->render_from_template('theme_saec/components/assign_workspace', $assignviewcontext['workspace']);
    } else {
        // 1j-bis. VISTA DE TAREA — SaaS OVERLAY, RAMA DOCENTE (Sprint 7):
        // assign_view_page::get_context() returns null here for graders by
        // design (see its docblock) — this is exactly that gap. Native
        // "Sumario de calificaciones" table + its numbers are hidden via
        // CSS (body.saec-assign-teacher-summary-active) and replaced with
        // due-date pills + real KPI cards, all sourced from
        // \assign::get_assign_grading_summary_renderable() — never
        // reimplemented.
        $assignteachercontext = \theme_saec\dashboard\teacher_assign_view_page::get_context($PAGE->cm->id);
        if ($assignteachercontext !== null) {
            $assignteachersummaryhtml = $OUTPUT->render_from_template(
                'theme_saec/components/assign_teacher_summary',
                $assignteachercontext
            );
        }
    }
}

// 1k. STUDENT "BOLETA DIGITAL" SUMMARY (/grade/report/user/index.php,
// Sprint 6): additive only, same non-destructive pattern as 1j above — the
// native gradereport_user table is never touched (its rowspan/hidden-item/
// configurable-column logic is real core complexity, unsafe to
// reimplement), this only injects 3 real-data metric cards above it.
// Renders for whoever the table is actually showing (student viewing their
// own report, or a teacher/parent viewing a specific student's) — skipped
// for the teacher "zero state" (no student picked yet) and "view all"
// (userid=0, loops every student's table in sequence) branches of
// grade/report/user/index.php, where there's no single user to summarize.
$boletasummaryhtml = null;
$isuserreportpage = ($PAGE->url->out_omit_querystring() === (new moodle_url('/grade/report/user/index.php'))->out_omit_querystring());
if ($isuserreportpage) {
    // Registered unconditionally (not inside templates/components/
    // boleta_summary.mustache's own {{#js}}) so the mobile column-hiding
    // in scss/saec/_boleta.scss still works even when boleta_page::get_context()
    // returns null below (e.g. an empty gradebook) — that native table
    // still renders and still needs its columns tagged.
    $PAGE->requires->js_init_code(<<<'JS'
require(['jquery'], function($) {
    var table = document.querySelector('.path-grade-report-user table.user-grade');
    if (!table) {
        return;
    }
    var columnKeys = Array.from(table.querySelectorAll('thead th')).map(function(th) {
        var match = Array.from(th.classList).find(function(c) { return c.indexOf('column-') === 0; });
        return match ? match.replace('column-', '') : null;
    });
    table.querySelectorAll('tbody tr').forEach(function(row) {
        var cells = Array.from(row.children);
        var anchor = cells.findIndex(function(c) { return c.tagName === 'TH'; });
        if (anchor === -1) {
            return;
        }
        columnKeys.forEach(function(key, i) {
            var cell = cells[anchor + i];
            if (cell && key) {
                cell.setAttribute('data-column', key);
            }
        });
    });
});
JS);
}
if ($isuserreportpage && isloggedin() && !isguestuser() && $PAGE->course->id) {
    $boletacourseid = $PAGE->course->id;
    $boletarequesteduserid = optional_param('userid', null, PARAM_INT);
    if (has_capability('moodle/grade:viewall', context_course::instance($boletacourseid))) {
        $boletauserid = (empty($boletarequesteduserid)) ? null : $boletarequesteduserid;
    } else {
        $boletauserid = $boletarequesteduserid ?: $USER->id;
    }
    if ($boletauserid) {
        $boletacontext = \theme_saec\dashboard\boleta_page::get_context($boletacourseid, $boletauserid);
        if ($boletacontext !== null) {
            $boletasummaryhtml = $OUTPUT->render_from_template('theme_saec/components/boleta_summary', $boletacontext);
        }
    }
}

// 2. CONFIGURACIÓN DE NAVEGACIÓN NATIVA DE MOODLE
$addblockbutton = $OUTPUT->addblockbutton();
$extraclasses = [];
if ($PAGE->user_is_editing()) {
    $extraclasses[] = 'edithread';
}
if ($badgespagehtml !== null) {
    $extraclasses[] = 'page-mybadges';
}
if ($isbadgeverifypage) {
    $extraclasses[] = 'page-badge-verify';
}
if ($analyticspagehtml !== null) {
    $extraclasses[] = 'page-analytics';
}
if ($graderhubpagehtml !== null) {
    $extraclasses[] = 'page-grader-hub';
}
if ($teacherprogresspagehtml !== null) {
    $extraclasses[] = 'page-teacher-progress';
}
if ($adminreportspagehtml !== null) {
    $extraclasses[] = 'page-admin-reports';
}
if ($settingspagehtml !== null) {
    $extraclasses[] = 'page-settings';
}
if ($courseviewheaderhtml !== null) {
    $extraclasses[] = 'saec-course-view-active';
}
if ($teachercourseviewheaderhtml !== null) {
    $extraclasses[] = 'saec-teacher-course-view-active';
}
if ($assignheaderhtml !== null) {
    $extraclasses[] = 'saec-assign-view-active';
}
if ($assignteachersummaryhtml !== null) {
    $extraclasses[] = 'saec-assign-teacher-summary-active';
}
$bodyattributes = $OUTPUT->body_attributes($extraclasses);

$buildregionmainsettings = $PAGE->include_region_main_settings_in_header_actions();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

// Navegación primaria (Pestañas superiores)
$primary = new \core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);

$primarymoremenu = $primarymenu['moremenu'] ?? $primarymenu;

// EXTRAER EL MENÚ DE USUARIO ESTRUCTURADO
$usermenudata = $primarymenu['user'] ?? null;
if (empty($usermenudata)) {
    $usermenuobj = new \core\navigation\output\user_menu($PAGE);
    $usermenudata = $usermenuobj->export_for_template($renderer);
}

// Navegación secundaria (Pestañas internas de curso/admin)
$buildsecondarymenu = $PAGE->has_secondary_navigation();
$secondaryinitial = false;
$overflow = '';

if ($buildsecondarymenu) {
    $secondarynavigation = $PAGE->secondarynav;
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($secondarynavigation, 'nav-tabs', true, $tablistnav);
    $secondaryinitial = $moremenu->export_for_template($renderer);
    if (!empty($secondaryinitial)) {
        $overflow = (object) [
            'servicenode' => $secondarynavigation->find_active_node(),
        ];
    }
}

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

// 3. ESTADO DE DRAWERS NATIVOS
// NOTA: se reutiliza la preferencia 'drawer-open-index' (antes ligada al
// árbol de secciones del curso) porque el drawer izquierdo ahora aloja el
// menú principal UPTex (theme_saec/nav-drawer) en el mismo slot/posición.
$sidebaropen = false;
if (isloggedin() && !isguestuser()) {
    $sidebaropen = get_user_preferences('drawer-open-index', true);
}

// Igual que el drawer principal (ver $sidebaropen arriba): el drawer de
// bloques nunca se ofrece a sesiones anónimas/invitado. Sin esta guarda,
// theme_boost/drawer.js igual inicializa ese drawer para cualquier
// visitante (blocks_for_region() no distingue por sesión), registrando su
// listener global de "resize" y, si el visitante abre/cierra el drawer,
// intentando persistir la preferencia vía set_user_preference() — una
// llamada AJAX a core_user_set_user_preferences que una sesión invitada no
// puede completar con éxito. Cortarlo aquí evita que el drawer se renderice
// del todo para esos visitantes, no solo que falle la llamada.
$hasblocks = (isloggedin() && !isguestuser()) && $OUTPUT->blocks_for_region('side-pre');
$blockdraweropen = false;
if ($hasblocks) {
    $blockdraweropen = get_user_preferences('drawer-open-block', true);
}

// 3b. NAVBAR: identidad de marca y sesión (requerido por theme_saec/navbar)
$logourl = $OUTPUT->image_url('logo1', 'theme_saec')->out(false);
$isloggedin = isloggedin() && !isguestuser();
$loginurl = (new moodle_url('/login/index.php'))->out(false);

// 3c. SIDEBAR UPTEX: menú según rol (Alumno, Docente, Administrador). Sólo
// se construye para usuarios autenticados no-invitados; para el resto el
// drawer principal no se ofrece. Los roles ($is_admin/$is_teacher) ya se
// calcularon en el bloque 1 con capacidades nativas de Moodle
// (is_siteadmin()/has_capability('moodle/course:update', ...)).
$showmainnav = $isloggedin;
$navitems = [];
if ($showmainnav) {
    $currentpath = $PAGE->url->get_path();
    // NOT !empty($PAGE->course): moodle_page defines __get() (delegating to
    // magic_get_course()) but no __isset() — per PHP's overloading rules,
    // empty()/isset() on a property that only has __get() always evaluate
    // to "not set", regardless of what __get() would actually return
    // (verified: $PAGE->course->id resolves the real course correctly even
    // while !empty($PAGE->course) is false). That silently broke $courseid
    // (and therefore the Calificador/Asistencia/Progreso sidebar links)
    // wherever $PAGE->course->id != SITEID no longer had to be reached
    // through a truthiness check on the object itself — e.g. every
    // mod/*/view.php page. magic_get_course() always returns a real course
    // object (defaults to $SITE, never null), so there's nothing to guard
    // against here beyond the actual SITEID comparison.
    $incourse = ($PAGE->course->id != SITEID);
    $courseid = $incourse ? $PAGE->course->id : null;
    $isstudentrole = !$is_teacher && !$is_admin;

    $icon = function (string $pix, string $component = 'moodle') use ($OUTPUT) {
        return $OUTPUT->pix_icon($pix, '', $component, ['class' => 'saec-sidebar__icon-img']);
    };

    // "Configuración" (/user/preferences.php) is also the active nav item
    // while inside the native profile-edit pages it links out to (Editar
    // perfil / Cambiar contraseña), not just on the preferences grid itself
    // — an admin/teacher/student mid-edit shouldn't lose the sidebar's
    // sense of "where am I".
    $isaccountsettingspath = function (string $path) use ($PAGE, $USER): bool {
        if (strpos($path, '/user/preferences.php') !== false
                || strpos($path, '/user/profile.php') !== false
                || strpos($path, '/user/edit.php') !== false) {
            return true;
        }

        // Admins' own "Editar perfil" link (from the preferences hero) routes
        // through the advanced editor (moodle/user:editprofile lets them edit
        // fields edit.php doesn't expose) instead of the plain self-service
        // edit.php a teacher/student's own profile edit uses. Only treat it
        // as "Configuración" when the id being edited is the admin's own —
        // this exact URL is also how the Admin Command Center's "+ Nuevo
        // Usuario" action and the Course Catalog's user-directory "Editar"
        // action reach OTHER users' profiles, which is Administración del
        // Sitio work, not account settings.
        if (strpos($path, '/user/editadvanced.php') !== false) {
            return ((int) $PAGE->url->get_param('id')) === (int) $USER->id;
        }

        return false;
    };

    $dashboarditem = [
        'label' => get_string('navdashboard', 'theme_saec'),
        'url' => (new moodle_url('/my/'))->out(false),
        'icon' => $icon('i/dashboard'),
        'isactive' => ($PAGE->pagelayout === 'mydashboard'),
    ];
    $mycoursesitem = [
        'label' => get_string('navmycourses', 'theme_saec'),
        'url' => (new moodle_url('/my/courses.php'))->out(false),
        'icon' => $icon('i/course'),
        'isactive' => (strpos($currentpath, '/my/courses.php') !== false || $PAGE->pagelayout === 'coursecategory'),
    ];

    if ($is_admin) {
        // ---- ADMINISTRADOR --------------------------------------------------
        // Sprint (Admin Command Center): was a direct link to the native
        // /admin/search.php flat settings list. Now points at the theme's
        // own Admin SaaS Command Center (theme/saec/pages/admin_hub.php),
        // which itself links out to /admin/search.php and every other
        // native admin destination — same "theme hub replaces a bare native
        // landing page" strategy pages/attendance_hub.php already uses.
        // Enterprise SaaS information architecture, two groups: daily
        // operational workspaces first (Panel, Gestión de Cursos, Directorio
        // de Usuarios, Insignias y Certificación, Reportes y Auditoría), then
        // governance/system administration (Administración del Sitio,
        // Configuración) toward the bottom, separated by a subtle divider.
        // Labels below are admin-specific overrides of the generic
        // navmycourses/navcredentials strings Teacher/Student also use
        // (get_string('navmycourses')="Mis Cursos", ('navcredentials')=
        // "Insignias") — built as standalone array items here rather than
        // relabeling $mycoursesitem itself, since that shared object/string
        // must stay untouched for the Teacher/Student branches below.
        $navitems = [
            $dashboarditem,
            [
                'label' => get_string('navadmincoursemanagement', 'theme_saec'),
                'url' => $mycoursesitem['url'],
                'icon' => $icon('i/course'),
                'isactive' => $mycoursesitem['isactive'],
            ],
            [
                'label' => get_string('navuserdirectory', 'theme_saec'),
                'url' => (new moodle_url('/admin/user.php'))->out(false),
                'icon' => $icon('i/users'),
                'isactive' => (strpos($currentpath, '/admin/user.php') !== false),
            ],
            [
                'label' => get_string('navadmincredentials', 'theme_saec'),
                // 1 = BADGE_TYPE_SITE (see the same literal-vs-constant note
                // on admin_dashboard's own badges quick action) — site-level
                // badge management, the admin-only counterpart to a
                // student's "Mi Mochila".
                'url' => (new moodle_url('/badges/index.php', ['type' => 1]))->out(false),
                'icon' => $icon('i/badge'),
                'isactive' => (strpos($currentpath, '/badges/') !== false),
                'disabled' => empty($CFG->enablebadges),
            ],
            [
                'label' => get_string('navreports', 'theme_saec'),
                'url' => (new moodle_url('/report/log/index.php'))->out(false),
                'icon' => $icon('i/report'),
                'isactive' => (strpos($currentpath, '/report/log/') !== false),
            ],
            ['isdivider' => true],
            [
                'label' => get_string('navsiteadmin', 'theme_saec'),
                'url' => (new moodle_url('/theme/saec/pages/admin_hub.php'))->out(false),
                'icon' => $icon('i/settings'),
                // Excludes /admin/user.php specifically — that subpath now
                // has its own dedicated "Directorio de Usuarios" item above,
                // and /admin/ is otherwise a prefix of it, which would
                // double-highlight both items at once without this carve-out.
                'isactive' => ((strpos($currentpath, '/admin/') !== false
                        && strpos($currentpath, '/admin/user.php') === false)
                    || strpos($currentpath, '/theme/saec/pages/admin_hub.php') !== false),
            ],
            [
                'label' => get_string('navsettings', 'theme_saec'),
                'url' => (new moodle_url('/user/preferences.php'))->out(false),
                'icon' => $icon('i/settings'),
                'isactive' => $isaccountsettingspath($currentpath),
            ],
        ];
    } else if ($is_teacher) {
        // ---- DOCENTE ----------------------------------------------------------
        $gradeurl = $courseid
            ? new moodle_url('/grade/report/index.php', ['id' => $courseid])
            : new moodle_url('/grade/report/overview/index.php');

        // Sprint 9: was a per-course link (mod/attendance/index.php?id=courseid,
        // a bare 2-column table with no metrics — real, but not a "hub").
        // Now points at the theme's own cross-course Course Selection Hub
        // (theme/saec/pages/attendance_hub.php) instead — mod_attendance has
        // no cross-course landing page of its own to link to.
        $attendanceinstalled = file_exists($CFG->dirroot . '/mod/attendance/version.php');
        $attendanceurl = $attendanceinstalled
            ? new moodle_url('/theme/saec/pages/attendance_hub.php')
            : new moodle_url('/my/courses.php');

        // /grade/report/user/index.php's own 'id' param is required_param()
        // — unlike Calificador/Asistencia above, there's no "id-less"
        // landing to fall back to, so outside a course context this
        // resolves a real course id from the teacher's own manageable
        // courses (teacher_progress_page::get_context() applies this exact
        // same "first manageable course" fallback again once landed, so
        // the two never disagree). userid=0 is explicit (not omitted) so
        // this always deterministically lands on the "all students" roster
        // rather than whatever course/student the PHP session happened to
        // have last cached for grade/report/user's own native controller.
        $progresscourseid = $courseid ?: (teacher_progress_page::get_manageable_courseids((int) $USER->id)[0] ?? 0);
        $progressurl = $progresscourseid
            ? new moodle_url('/grade/report/user/index.php', ['id' => $progresscourseid, 'userid' => 0])
            : new moodle_url('/my/courses.php');

        // Course-scoped badge management (2 = BADGE_TYPE_COURSE — see the
        // literal-vs-constant note on admin_dashboard's own badges action).
        // Unlike Calificador/Progreso above, there's no sensible cross-course
        // fallback to fall back to here — a teacher's site-level
        // moodle/badges:manageownbadges is normally off (that's an admin-only
        // capability), so this is disabled outside a specific course rather
        // than guessing which of their courses to land on.
        $badgeurl = $courseid
            ? new moodle_url('/badges/index.php', ['type' => 2, 'id' => $courseid])
            : new moodle_url('/my/courses.php');

        $navitems = [
            $dashboarditem,
            $mycoursesitem,
            [
                'label' => get_string('navgradebook', 'theme_saec'),
                'url' => $gradeurl->out(false),
                'icon' => $icon('i/grades'),
                'isactive' => (strpos($currentpath, '/grade/') !== false),
            ],
            [
                'label' => get_string('navcredentials', 'theme_saec'),
                'url' => $badgeurl->out(false),
                'icon' => $icon('i/badge'),
                'isactive' => (strpos($currentpath, '/badges/') !== false),
                'disabled' => (!$courseid || empty($CFG->enablebadges)),
            ],
            [
                'label' => get_string('navattendance', 'theme_saec'),
                'url' => $attendanceurl->out(false),
                'icon' => $attendanceinstalled ? $icon('monologo', 'mod_attendance') : $icon('i/calendar'),
                'isactive' => (strpos($currentpath, '/mod/attendance/') !== false
                    || strpos($currentpath, '/theme/saec/pages/attendance_hub.php') !== false),
                'disabled' => !$attendanceinstalled,
            ],
            [
                'label' => get_string('navstudentprogress', 'theme_saec'),
                'url' => $progressurl->out(false),
                'icon' => $icon('i/report'),
                'isactive' => (strpos($currentpath, '/grade/report/user/') !== false),
                'disabled' => !$courseid,
            ],
            [
                'label' => get_string('navsettings', 'theme_saec'),
                'url' => (new moodle_url('/user/preferences.php'))->out(false),
                'icon' => $icon('i/settings'),
                'isactive' => $isaccountsettingspath($currentpath),
            ],
        ];
    } else if ($isstudentrole) {
        // ---- ALUMNO -------------------------------------------------------
        $navitems = [
            $dashboarditem,
            $mycoursesitem,
            [
                'label' => get_string('navstudenttasks', 'theme_saec'),
                'url' => (new moodle_url('/theme/saec/pages/student_tasks.php'))->out(false),
                'icon' => $icon('i/checkedcircle'),
                'isactive' => (strpos($currentpath, '/theme/saec/pages/student_tasks.php') !== false),
            ],
            [
                'label' => get_string('navcredentials', 'theme_saec'),
                'url' => (new moodle_url('/badges/mybadges.php'))->out(false),
                'icon' => $icon('i/badge'),
                'isactive' => (strpos($currentpath, '/badges/') !== false),
                'disabled' => empty($CFG->enablebadges),
            ],
            [
                'label' => get_string('navanalytics', 'theme_saec'),
                'url' => (new moodle_url('/grade/report/overview/index.php'))->out(false),
                'icon' => $icon('i/report'),
                'isactive' => (strpos($currentpath, '/grade/report/overview/') !== false),
            ],
            [
                'label' => get_string('navsettings', 'theme_saec'),
                'url' => (new moodle_url('/user/preferences.php'))->out(false),
                'icon' => $icon('i/settings'),
                'isactive' => $isaccountsettingspath($currentpath),
            ],
        ];
    }
}

$hashelp = !empty($CFG->supportpage);
$helpurl = $hashelp ? $CFG->supportpage : null;
$logouturl = (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false);

// 3d. MENÚ DE IDIOMA (navbar): $OUTPUT->lang_menu() devuelve HTML ya
// renderizado contra la plantilla core/single_select — no el {title, items}
// que theme_boost/language_menu.mustache espera — así que navbar.mustache
// ({{#langmenu}}{{> theme_boost/language_menu}}{{/langmenu}}) terminaba
// renderizando ese partial con un contexto vacío (dropdown sin opciones).
// \core\output\language_menu::export_for_template() da directamente la
// forma correcta, y a diferencia de \core\navigation\output\primary (que
// sólo expone su propio 'lang' para invitados/deslogueados) no depende del
// estado de sesión — sólo de $CFG->langmenu + 2+ idiomas instalados — así
// que el selector también aparece ya logueado, como pide este panel.
$languagemenu = new \core\output\language_menu($PAGE);
$langmenudata = $languagemenu->export_for_template($renderer);

// 3e. MESSAGE DRAWER (chat): the navbar's chat toggle
// (core_message/message_popover, rendered above via output.navbar_plugin_output)
// only ever publishes a TOGGLE_VISIBILITY PubSub event — it renders no
// panel of its own. The panel itself (core_message/message_drawer, a
// {{< core/drawer}} instance carrying its own {{#js}} init block) has to
// be explicitly rendered into the page by the theme; core_message::
// render_messaging_widget() is the official API for that (it already
// bails out to '' for guests/logged-out/messaging-disabled, so no extra
// guard is needed here). Neither theme_boost's own layouts call this
// either — messaging drawers are opt-in per theme, not automatic.
$messagedrawerhtml = \core_message\helper::render_messaging_widget(true);

// 4. CONTEXTO COMPLETO PARA MUSTACHE
$templatecontext = [
    'sitename' => format_string($SITE->fullname),
    'output' => $OUTPUT,
    'config' => ['wwwroot' => $CFG->wwwroot, 'homeurl' => (new moodle_url('/'))->out(false)],
    'sidepreblocks' => $OUTPUT->blocks('side-pre'),
    'hasblocks' => $hasblocks,
    // templates/drawers.mustache's blocks-drawer override of theme_boost/drawer's
    // "forceopen" block reads this key ({{#forceblockdraweropen}}1{{/forceblockdraweropen}})
    // — it was never actually supplied here, so Mustache silently rendered
    // that section as empty (data-forceopen="") instead of the parent
    // template's own real default ("0"). Harmless in practice (core's own
    // drawers.js only ever compares dataset.forceopen == 1 with loose
    // equality, and "" == 1 is false exactly like "0" == 1), but a real gap
    // between the template and its own context all the same — this drawer
    // is never meant to force itself open regardless of the stored
    // preference, so the correct value is a real, explicit false, not an
    // accidentally-undefined key.
    'forceblockdraweropen' => false,
    'bodyattributes' => $bodyattributes,
    'primarymoremenu' => $primarymoremenu,
    'secondarymoremenu' => $secondaryinitial,
    'mobileprimarynav' => $primarymoremenu,
    'usermenu' => $usermenudata, // Pasa los datos que la plantilla core/user_menu necesita
    'langmenu' => $langmenudata,
    'messagedrawerhtml' => $messagedrawerhtml,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'headercontent' => $headercontent,
    'overflow' => $overflow,
    'addblockbutton' => $addblockbutton,
    'sidebaropen' => $sidebaropen,
    'blockdraweropen' => $blockdraweropen,
    'isTeacher' => $is_teacher,
    'isAdmin' => $is_admin,
    'isStudent' => (!$is_teacher && !$is_admin),
    'studentName' => fullname($USER),
    'firstName' => $isloggedin ? $USER->firstname : '',
    'dashboardkpi' => $dashboardkpi,
    'showstudentdashboard' => ($studentdashboardhtml !== null),
    'studentdashboardhtml' => $studentdashboardhtml,
    'showteacherdashboard' => ($teacherdashboardhtml !== null),
    'teacherdashboardhtml' => $teacherdashboardhtml,
    'showadmindashboard' => ($admindashboardhtml !== null),
    'admindashboardhtml' => $admindashboardhtml,
    'showcoursespage' => ($coursespagehtml !== null),
    'coursespagehtml' => $coursespagehtml,
    'showteachercoursespage' => ($teachercoursespagehtml !== null),
    'teachercoursespagehtml' => $teachercoursespagehtml,
    'showadmincoursespage' => ($admincoursespagehtml !== null),
    'admincoursespagehtml' => $admincoursespagehtml,
    'showbadgespage' => ($badgespagehtml !== null),
    'badgespagehtml' => $badgespagehtml,
    'showanalyticspage' => ($analyticspagehtml !== null),
    'analyticspagehtml' => $analyticspagehtml,
    'showgraderhubpage' => ($graderhubpagehtml !== null),
    'graderhubpagehtml' => $graderhubpagehtml,
    'showteacherprogresspage' => ($teacherprogresspagehtml !== null),
    'teacherprogresspagehtml' => $teacherprogresspagehtml,
    'showadminreportspage' => ($adminreportspagehtml !== null),
    'adminreportspagehtml' => $adminreportspagehtml,
    'showsettingspage' => ($settingspagehtml !== null),
    'settingspagehtml' => $settingspagehtml,
    'showcourseviewpage' => ($courseviewheaderhtml !== null),
    'courseviewheaderhtml' => $courseviewheaderhtml,
    'courseviewsidebarhtml' => $courseviewsidebarhtml,
    'showteachercourseviewpage' => ($teachercourseviewheaderhtml !== null),
    'teachercourseviewheaderhtml' => $teachercourseviewheaderhtml,
    'teachercourseviewsidebarhtml' => $teachercourseviewsidebarhtml,
    'showassignviewpage' => ($assignheaderhtml !== null),
    'assignheaderhtml' => $assignheaderhtml,
    'assignworkspacehtml' => $assignworkspacehtml,
    'showassignteachersummary' => ($assignteachersummaryhtml !== null),
    'assignteachersummaryhtml' => $assignteachersummaryhtml,
    'showboletasummary' => ($boletasummaryhtml !== null),
    'boletasummaryhtml' => $boletasummaryhtml,
    'baseUrl' => $CFG->wwwroot,
    'isDashboard' => ($PAGE->pagelayout === 'mydashboard'),
    'isFrontpage' => ($PAGE->pagelayout === 'frontpage'),
    // Navbar (theme_saec/navbar).
    'logourl' => $logourl,
    'isloggedin' => $isloggedin,
    'loginurl' => $loginurl,
    // Sidebar / main nav drawer (theme_saec/nav-drawer).
    'showmainnav' => $showmainnav,
    'navitems' => $navitems,
    'hashelp' => $hashelp,
    'helpurl' => $helpurl,
    'logouturl' => $logouturl,
];

echo $OUTPUT->render_from_template('theme_saec/drawers', $templatecontext);