<?php
defined('MOODLE_INTERNAL') || die();

global $USER, $DB, $CFG, $PAGE, $OUTPUT, $SITE;

// 1. NAVEGACIÓN PRIMARIA (idéntica a drawers.php para no perder el contexto core)
$primary = new \core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$primarymoremenu = $primarymenu['moremenu'] ?? $primarymenu;

$usermenudata = $primarymenu['user'] ?? null;
if (empty($usermenudata)) {
    $usermenuobj = new \core\navigation\output\user_menu($PAGE);
    $usermenudata = $usermenuobj->export_for_template($renderer);
}

$bodyattributes = $OUTPUT->body_attributes();

// 1b. ESTADO DE SESIÓN (para el botón "Iniciar sesión" del navbar)
$isloggedin = isloggedin() && !isguestuser();
$loginurl = (new moodle_url('/login/index.php'))->out(false);

// 1c. MENÚ DE IDIOMA (navbar): ver la nota equivalente en layout/drawers.php
// — $OUTPUT->lang_menu() da HTML pre-renderizado con la forma de
// core/single_select, no el {title, items} que theme_boost/language_menu.mustache
// espera (navbar.mustache incluye ese partial), así que el dropdown salía
// vacío. \core\output\language_menu::export_for_template() da la forma
// correcta directamente.
$languagemenu = new \core\output\language_menu($PAGE);
$langmenudata = $languagemenu->export_for_template($renderer);

// 2. IMÁGENES DEL TEMA (pix/) — resueltas vía image_url para no romper si cambia la extensión.
$logourl = $OUTPUT->image_url('logo1', 'theme_saec')->out(false);
$logofooterurl = $OUTPUT->image_url('logo2', 'theme_saec')->out(false);
$heroimage = $OUTPUT->image_url('img1', 'theme_saec')->out(false);

// 3. CONTEXTO COMPLETO PARA MUSTACHE
// NOTA: ya no se precalculan $slides/$courses aquí — la portada ahora
// delega el listado de cursos a {{{ output.main_content }}} (ver
// templates/frontpage.mustache), así que esa consulta ya no es necesaria
// en cada carga de la portada.
$templatecontext = [
    'sitename' => format_string($SITE->fullname),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'primarymoremenu' => $primarymoremenu,
    'mobileprimarynav' => $primarymoremenu,
    'usermenu' => $usermenudata,
    'isloggedin' => $isloggedin,
    'loginurl' => $loginurl,
    'langmenu' => $langmenudata,
    'baseUrl' => $CFG->wwwroot,
    'logourl' => $logourl,
    'logofooterurl' => $logofooterurl,
    'heroimage' => $heroimage,
    'currentyear' => date('Y'),
];

echo $OUTPUT->render_from_template('theme_saec/frontpage', $templatecontext);
