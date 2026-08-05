<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');

use theme_saec\dashboard\analytics_page;
use theme_saec\dashboard\badges_page;
use theme_saec\dashboard\courses_page;
use theme_saec\dashboard\student_dashboard;
use theme_saec\dashboard\teacher_course_view_page;
use theme_saec\dashboard\teacher_courses_page;
use theme_saec\dashboard\teacher_dashboard;

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
$isassignviewpage = ($PAGE->url->out_omit_querystring() === (new moodle_url('/mod/assign/view.php'))->out_omit_querystring());
if ($isassignviewpage && $PAGE->cm && $PAGE->cm->modname === 'assign') {
    $assignviewcontext = \theme_saec\dashboard\assign_view_page::get_context($PAGE->cm->id);
    if ($assignviewcontext !== null) {
        $assignheaderhtml = $OUTPUT->render_from_template('theme_saec/components/assign_header', $assignviewcontext['header']);
        $assignworkspacehtml = $OUTPUT->render_from_template('theme_saec/components/assign_workspace', $assignviewcontext['workspace']);
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

$hasblocks = $OUTPUT->blocks_for_region('side-pre');
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
    $incourse = (!empty($PAGE->course) && $PAGE->course->id != SITEID);
    $courseid = $incourse ? $PAGE->course->id : null;
    $isstudentrole = !$is_teacher && !$is_admin;

    $icon = function (string $pix, string $component = 'moodle') use ($OUTPUT) {
        return $OUTPUT->pix_icon($pix, '', $component, ['class' => 'saec-sidebar__icon-img']);
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
        $navitems = [
            $dashboarditem,
            [
                'label' => get_string('navsiteadmin', 'theme_saec'),
                'url' => (new moodle_url('/admin/search.php'))->out(false),
                'icon' => $icon('i/settings'),
                'isactive' => (strpos($currentpath, '/admin/') !== false),
            ],
            $mycoursesitem,
        ];
    } else if ($is_teacher) {
        // ---- DOCENTE ----------------------------------------------------------
        $gradeurl = $courseid
            ? new moodle_url('/grade/report/index.php', ['id' => $courseid])
            : new moodle_url('/grade/report/overview/index.php');

        $attendanceinstalled = file_exists($CFG->dirroot . '/mod/attendance/version.php');
        $attendanceurl = ($courseid && $attendanceinstalled)
            ? new moodle_url('/mod/attendance/index.php', ['id' => $courseid])
            : new moodle_url('/my/courses.php');

        $progressurl = $courseid
            ? new moodle_url('/report/progress/index.php', ['course' => $courseid])
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
                'label' => get_string('navattendance', 'theme_saec'),
                'url' => $attendanceurl->out(false),
                'icon' => $attendanceinstalled ? $icon('monologo', 'mod_attendance') : $icon('i/calendar'),
                'isactive' => (strpos($currentpath, '/mod/attendance/') !== false),
                'disabled' => !($courseid && $attendanceinstalled),
            ],
            [
                'label' => get_string('navstudentprogress', 'theme_saec'),
                'url' => $progressurl->out(false),
                'icon' => $icon('i/report'),
                'isactive' => (strpos($currentpath, '/report/progress/') !== false),
                'disabled' => !$courseid,
            ],
            [
                'label' => get_string('navsettings', 'theme_saec'),
                'url' => (new moodle_url('/user/preferences.php'))->out(false),
                'icon' => $icon('i/settings'),
                'isactive' => (strpos($currentpath, '/user/preferences.php') !== false),
            ],
        ];
    } else if ($isstudentrole) {
        // ---- ALUMNO -------------------------------------------------------
        $navitems = [
            $dashboarditem,
            $mycoursesitem,
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
                'isactive' => (strpos($currentpath, '/user/preferences.php') !== false),
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

// 4. CONTEXTO COMPLETO PARA MUSTACHE
$templatecontext = [
    'sitename' => format_string($SITE->fullname),
    'output' => $OUTPUT,
    'config' => ['wwwroot' => $CFG->wwwroot, 'homeurl' => (new moodle_url('/'))->out(false)],
    'sidepreblocks' => $OUTPUT->blocks('side-pre'),
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'primarymoremenu' => $primarymoremenu,
    'secondarymoremenu' => $secondaryinitial,
    'mobileprimarynav' => $primarymoremenu,
    'usermenu' => $usermenudata, // Pasa los datos que la plantilla core/user_menu necesita
    'langmenu' => $langmenudata,
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
    'showcoursespage' => ($coursespagehtml !== null),
    'coursespagehtml' => $coursespagehtml,
    'showteachercoursespage' => ($teachercoursespagehtml !== null),
    'teachercoursespagehtml' => $teachercoursespagehtml,
    'showbadgespage' => ($badgespagehtml !== null),
    'badgespagehtml' => $badgespagehtml,
    'showanalyticspage' => ($analyticspagehtml !== null),
    'analyticspagehtml' => $analyticspagehtml,
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