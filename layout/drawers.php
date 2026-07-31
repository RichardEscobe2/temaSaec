<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');

global $USER, $DB, $CFG, $PAGE, $OUTPUT, $SITE;

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

// 2. CONFIGURACIÓN DE NAVEGACIÓN NATIVA DE MOODLE
$addblockbutton = $OUTPUT->addblockbutton();
$extraclasses = [];
if ($PAGE->user_is_editing()) {
    $extraclasses[] = 'edithread';
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
    'langmenu' => $OUTPUT->lang_menu(),
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