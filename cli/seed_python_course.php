<?php
// This file is part of Moodle - http://moodle.org/
//
// theme_saec is free software distributed as part of Moodle under the GPLv3.

/**
 * Idempotent seeder for the "Microcredencial en Programación y
 * Automatización con Python" (MPY-2026) demo course.
 *
 * Creates (or reuses, if already present) the Microcredenciales category,
 * the MPY-2026 course, 1 teacher + 10 students, the weighted 2-Parcial
 * gradebook, 14 assign activities, 32 mod_attendance sessions with
 * historical logs matching each student's target attendance %, and the
 * Python course badge, issued to the 3 students who meet its >=8.0
 * criterion.
 *
 * Every "creation" step first checks whether the entity already exists
 * (by idnumber/shortname/username/name) and reuses it instead of
 * duplicating — safe to run more than once, e.g. after fixing an error
 * partway through a previous run.
 *
 * Usage: docker exec saec_web php /var/www/html/theme/saec/cli/seed_python_course.php
 *
 * @package    theme_saec
 * @copyright  2026 Universidad Politécnica de Texcoco
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_category.php');
require_once($CFG->libdir . '/grade/grade_item.php');
require_once($CFG->libdir . '/badgeslib.php');

// The container has no MTA — enrol_try_internal_enrol() below triggers a
// course-welcome-message email per enrolment that would otherwise fail
// loudly (sendmail missing) without affecting the enrolment itself.
$CFG->noemailever = true;

// -----------------------------------------------------------------------
// Fixed seed data (Specification 2).
// -----------------------------------------------------------------------

$TEACHER = [
    'username' => 'docente_morales',
    'password' => 'Docente.2026*',
    'firstname' => 'Roberto',
    'lastname' => 'Morales Beltrán',
    'email' => 'morales.roberto@docente.uptex.edu.mx',
];

$STUDENTS = [
    // username, firstname, lastname, target avg (0-10), target attendance %.
    ['241750181', 'Andrea Sofía', 'Martínez Castro', 9.6, 95],
    ['241750182', 'Luis Fernando', 'Gómez Téllez', 9.1, 92],
    ['241750183', 'Mariana Ximena', 'Ruiz Peña', 8.4, 88],
    ['241750184', 'David Alejandro', 'Vargas Luna', 7.8, 85],
    ['241750185', 'Valeria Lizeth', 'Ortiz Reyes', 7.5, 82],
    ['241750186', 'Emiliano Josué', 'Cruz Romero', 7.2, 80],
    ['241750187', 'Camila Fernanda', 'Navarro Mora', 7.0, 81],
    ['241750188', 'Héctor Manuel', 'Bravo Soto', 8.5, 68],
    ['241750189', 'Karla Patricia', 'Méndez Silva', 5.8, 85],
    ['241750190', 'Rodrigo Gael', 'Pineda Duarte', 3.0, 40],
];
define('SAEC_BADGE_ELIGIBLE', ['241750181', '241750182', '241750183']);

$PARCIAL1_TASKS = [
    'Actividad 1.1: Sintaxis Básica, Variables y Operadores',
    'Actividad 1.2: Estructuras de Control de Flujo (if/match)',
    'Actividad 1.3: Bucles y Comprensión de Listas',
    'Actividad 1.4: Funciones Modulares y Manejo de Parámetros',
    'Actividad 1.5: Colecciones Avanzadas (Dicts, Sets, Tuplas)',
    'Actividad 1.6: Manejo Robusto de Excepciones y Errores',
    'Actividad 1.7: Evaluación Práctica: CLI Interactivo',
];
$PARCIAL2_TASKS = [
    'Actividad 2.1: Modelado de Clases, Constructores y Atributos',
    'Actividad 2.2: Herencia, Polimorfismo y Métodos Especiales',
    'Actividad 2.3: Persistencia de Archivos (TXT, CSV, JSON)',
    'Actividad 2.4: Consumo de APIs RESTful con requests',
    'Actividad 2.5: Procesamiento de Datos Tabulares con Pandas',
    'Actividad 2.6: Web Scraping y Automatización con BeautifulSoup',
    'Actividad 2.7: Proyecto Integrador: Pipeline de Automatización',
];

$COURSE_START = strtotime('2026-01-12 00:00:00'); // Monday.

// -----------------------------------------------------------------------
// Helpers.
// -----------------------------------------------------------------------

function saec_get_or_create_user(string $username, string $password, string $first, string $last, string $email): stdClass {
    global $DB, $CFG;
    $existing = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
    if ($existing) {
        mtrace("  · Usuario ya existe: {$username} (id={$existing->id})");
        return $existing;
    }
    $u = new stdClass();
    $u->username = $username;
    $u->password = $password;
    $u->firstname = $first;
    $u->lastname = $last;
    $u->email = $email;
    $u->auth = 'manual';
    $u->confirmed = 1;
    $u->mnethostid = $CFG->mnet_localhost_id;
    $u->policyagreed = 1;
    $u->lang = 'es';
    $u->city = 'Texcoco';
    $u->country = 'MX';
    $newid = user_create_user($u, true, false);
    $created = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
    mtrace("  · Usuario creado: {$username} (id={$newid})");
    return $created;
}

function saec_get_or_create_gradecat(int $courseid, int $parentid, string $fullname, int $aggregation): grade_category {
    global $DB;
    $existing = $DB->get_record('grade_categories', ['courseid' => $courseid, 'fullname' => $fullname, 'parent' => $parentid]);
    if ($existing) {
        mtrace("  · Categoría de calificación ya existe: {$fullname} (id={$existing->id})");
        return grade_category::fetch(['id' => $existing->id]);
    }
    $cat = new grade_category([
        'courseid' => $courseid,
        'parent' => $parentid,
        'fullname' => $fullname,
        'aggregation' => $aggregation,
    ], false);
    $cat->insert();
    mtrace("  · Categoría de calificación creada: {$fullname} (id={$cat->id})");
    return $cat;
}

function saec_get_or_create_assign(stdClass $course, int $sectionnum, string $name, string $intro, int $duedate, int $categoryid): stdClass {
    global $DB;
    $existing = $DB->get_record_sql(
        "SELECT cm.id AS cmid, a.id AS instanceid
           FROM {course_modules} cm
           JOIN {assign} a ON a.id = cm.instance
           JOIN {modules} m ON m.id = cm.module
          WHERE m.name = 'assign' AND cm.course = :courseid AND a.name = :name",
        ['courseid' => $course->id, 'name' => $name]
    );
    if ($existing) {
        mtrace("  · Actividad ya existe: {$name} (cmid={$existing->cmid})");
        return $existing;
    }

    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'assign';
    $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'assign']);
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $sectionnum;
    $moduleinfo->visible = 1;
    $moduleinfo->visibleoncoursepage = 1;
    $moduleinfo->name = $name;
    $moduleinfo->intro = $intro;
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->showdescription = 0;
    $moduleinfo->groupmode = 0;
    $moduleinfo->groupingid = 0;
    $moduleinfo->cmidnumber = '';
    $moduleinfo->availability = null;
    $moduleinfo->completion = 0;
    $moduleinfo->duedate = $duedate;
    $moduleinfo->allowsubmissionsfromdate = $duedate - (14 * DAYSECS);
    $moduleinfo->cutoffdate = 0;
    $moduleinfo->gradingduedate = 0;
    $moduleinfo->grade = 100;
    $moduleinfo->gradecat = $categoryid;
    $moduleinfo->assignsubmission_onlinetext_enabled = 1;
    $moduleinfo->assignsubmission_file_enabled = 0;
    $moduleinfo->assignfeedback_comments_enabled = 1;
    $moduleinfo->markingworkflow = 0;
    $moduleinfo->markingallocation = 0;
    $moduleinfo->teamsubmission = 0;
    $moduleinfo->requireallteammemberssubmit = 0;
    $moduleinfo->blindmarking = 0;
    $moduleinfo->submissiondrafts = 0;
    $moduleinfo->requiresubmissionstatement = 0;
    $moduleinfo->sendnotifications = 0;
    $moduleinfo->sendlatenotifications = 0;
    $moduleinfo->sendstudentnotifications = 0;

    $cm = add_moduleinfo($moduleinfo, $course);

    // Explicitly place the grade item in the right Parcial category —
    // don't rely on ->gradecat being honoured implicitly by add_moduleinfo().
    // Also set an explicit aggregationcoef: GRADE_AGGREGATE_WEIGHTED_MEAN
    // (unlike WEIGHTED_MEAN2) takes each item's weight directly from
    // aggregationcoef with NO grademax-based default — an item left at the
    // default aggregationcoef=0 is silently skipped from the category's
    // total entirely. All 7 items per Parcial get the same coef (1), i.e.
    // an equal-weighted mean across that Parcial's activities.
    $gi = grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $cm->instance, 'courseid' => $course->id]);
    if ($gi) {
        $gi->categoryid = $categoryid;
        $gi->aggregationcoef = 1;
        $gi->update();
    }

    mtrace("  · Actividad creada: {$name} (cmid={$cm->coursemodule})");
    return (object) ['cmid' => $cm->coursemodule, 'instanceid' => $cm->instance];
}

// -----------------------------------------------------------------------
// 1. Category (Specification 1).
// -----------------------------------------------------------------------

mtrace('=== SAEC Python Microcredential Seeder ===');
mtrace('');
mtrace('[1/7] Categoría "Microcredenciales"');

$category = $DB->get_record('course_categories', ['idnumber' => 'MICROCRED']);
if (!$category) {
    $catdata = new stdClass();
    $catdata->name = 'Microcredenciales';
    $catdata->idnumber = 'MICROCRED';
    $catdata->parent = 0;
    $category = core_course_category::create($catdata);
    mtrace("  · Categoría creada (id={$category->id})");
} else {
    mtrace("  · Categoría ya existe (id={$category->id})");
}

// -----------------------------------------------------------------------
// 2. Course.
// -----------------------------------------------------------------------

mtrace('');
mtrace('[2/7] Curso MPY-2026');

$course = $DB->get_record('course', ['shortname' => 'MPY-2026']);
if (!$course) {
    $coursedata = new stdClass();
    $coursedata->fullname = 'Microcredencial en Programación y Automatización con Python';
    $coursedata->shortname = 'MPY-2026';
    $coursedata->category = (int) $category->id;
    $coursedata->format = 'topics';
    $coursedata->summary = 'Curso oficial de certificación técnica en desarrollo, automatización y scripting con Python de la Universidad Politécnica de Texcoco.';
    $coursedata->summaryformat = FORMAT_HTML;
    $coursedata->visible = 1;
    $coursedata->startdate = $COURSE_START;
    $coursedata->enddate = $COURSE_START + (16 * WEEKSECS);
    $coursedata->enablecompletion = 1;
    $coursedata->numsections = 3;
    $course = create_course($coursedata);
    mtrace("  · Curso creado (id={$course->id})");
} else {
    mtrace("  · Curso ya existe (id={$course->id})");
}

course_create_sections_if_missing($course, [1, 2, 3]);
$sectionnames = [0 => 'General', 1 => 'Parcial 1', 2 => 'Parcial 2', 3 => 'Proyecto Integrador'];
foreach ($sectionnames as $num => $name) {
    $DB->set_field('course_sections', 'name', $name, ['course' => $course->id, 'section' => $num]);
}
rebuild_course_cache($course->id, true);
mtrace('  · Secciones: General, Parcial 1, Parcial 2, Proyecto Integrador');

// -----------------------------------------------------------------------
// 3. Users + enrolments.
// -----------------------------------------------------------------------

mtrace('');
mtrace('[3/7] Usuarios y matrícula');

$teacheruser = saec_get_or_create_user(
    $TEACHER['username'], $TEACHER['password'], $TEACHER['firstname'], $TEACHER['lastname'], $TEACHER['email']
);

$studentusers = []; // username => ['user' => stdClass, 'avg' => float, 'att' => int]
foreach ($STUDENTS as [$username, $first, $last, $avg, $att]) {
    $email = $username . '@alumno.uptex.edu.mx';
    $u = saec_get_or_create_user($username, 'Alumno.2026*', $first, $last, $email);
    $studentusers[$username] = ['user' => $u, 'avg' => $avg, 'att' => $att];
}

$teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

enrol_try_internal_enrol($course->id, $teacheruser->id, $teacherroleid);
foreach ($studentusers as $s) {
    enrol_try_internal_enrol($course->id, $s['user']->id, $studentroleid);
}
mtrace('  · 1 docente + 10 estudiantes matriculados en MPY-2026');

// -----------------------------------------------------------------------
// 4. Gradebook categories (Specification 3.1).
// -----------------------------------------------------------------------

mtrace('');
mtrace('[4/7] Libro de calificaciones (2 parciales, media ponderada 50/50)');

$coursecat = grade_category::fetch_course_category($course->id);
$coursecat->aggregation = GRADE_AGGREGATE_WEIGHTED_MEAN;
$coursecat->update('system');

$parcial1cat = saec_get_or_create_gradecat($course->id, $coursecat->id, 'Parcial 1: Fundamentos y Lógica Estructurada', GRADE_AGGREGATE_WEIGHTED_MEAN);
$parcial2cat = saec_get_or_create_gradecat($course->id, $coursecat->id, 'Parcial 2: POO, Datos y Automatización', GRADE_AGGREGATE_WEIGHTED_MEAN);

foreach ([$parcial1cat, $parcial2cat] as $cat) {
    $item = $cat->get_grade_item();
    $item->weightoverride = 1;
    $item->aggregationcoef = 50;
    $item->update();
}
mtrace('  · Parcial 1 y Parcial 2 ponderados 50.0 / 50.0 sobre el total del curso');

// -----------------------------------------------------------------------
// 5. Assignments (Specification 3.2).
// -----------------------------------------------------------------------

mtrace('');
mtrace('[5/7] 14 actividades (mod_assign)');

$assignments = []; // instanceid => ['name' => ..., 'parcial' => 1|2]

$week = 0;
foreach ($PARCIAL1_TASKS as $i => $taskname) {
    $duedate = $COURSE_START + (($i + 1) * WEEKSECS) + (3 * DAYSECS); // due each week, Thu.
    $intro = '<p>Entrega individual correspondiente a Parcial 1. Ponderación: 1/7 de la calificación de Parcial 1.</p>';
    $mod = saec_get_or_create_assign($course, 1, $taskname, $intro, $duedate, $parcial1cat->id);
    $assignments[$mod->instanceid] = ['name' => $taskname, 'parcial' => 1];
}
foreach ($PARCIAL2_TASKS as $i => $taskname) {
    $duedate = $COURSE_START + ((8 + $i + 1) * WEEKSECS) + (3 * DAYSECS);
    $intro = '<p>Entrega individual correspondiente a Parcial 2. Ponderación: 1/7 de la calificación de Parcial 2.</p>';
    $mod = saec_get_or_create_assign($course, 2, $taskname, $intro, $duedate, $parcial2cat->id);
    $assignments[$mod->instanceid] = ['name' => $taskname, 'parcial' => 2];
}
mtrace('  · 7 actividades en Parcial 1, 7 en Parcial 2');

// -----------------------------------------------------------------------
// 5b. Historical grades — uniform per student across all 14 activities so
// the weighted-mean course total lands exactly on each student's target
// average (grade on 0-100 scale = target avg * 10, matching how
// teacher_progress_page::get_student_grades() maps finalgrade back to a
// 0-10 scale: (finalgrade - min) / (max - min) * 10).
// -----------------------------------------------------------------------

mtrace('');
mtrace('  Calificando 14 actividades x 10 estudiantes...');

foreach ($studentusers as $username => $s) {
    $rawgrade = round($s['avg'] * 10, 1);
    foreach (array_keys($assignments) as $instanceid) {
        $gradeobj = new stdClass();
        $gradeobj->userid = $s['user']->id;
        $gradeobj->rawgrade = $rawgrade;
        $gradeobj->usermodified = $teacheruser->id;
        $gradeobj->dategraded = time();
        $gradeobj->datesubmitted = time();
        grade_update('mod/assign', $course->id, 'mod', 'assign', $instanceid, 0, $gradeobj);
    }
    mtrace("  · {$username}: {$rawgrade}/100 en las 14 actividades (promedio objetivo {$s['avg']})");
}

grade_regrade_final_grades($course->id);
mtrace('  · Recalculo de categorías y total del curso completado (grade_regrade_final_grades)');

// -----------------------------------------------------------------------
// 6. Attendance (Specification 3.3).
// -----------------------------------------------------------------------

mtrace('');
mtrace('[6/7] Asistencia (mod_attendance): 32 sesiones, 16 semanas, lunes y miércoles');

$attendmoduleid = $DB->get_field('modules', 'id', ['name' => 'attendance']);
if (!$attendmoduleid) {
    mtrace('  ! mod_attendance no está instalado — se omite este paso.');
} else {
    $attendanceinstance = $DB->get_record('attendance', ['course' => $course->id]);
    if (!$attendanceinstance) {
        $moduleinfo = new stdClass();
        $moduleinfo->modulename = 'attendance';
        $moduleinfo->module = $attendmoduleid;
        $moduleinfo->course = $course->id;
        $moduleinfo->section = 0;
        $moduleinfo->visible = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->name = 'Asistencia';
        $moduleinfo->intro = '<p>Control de asistencia del curso MPY-2026.</p>';
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->showdescription = 0;
        $moduleinfo->groupmode = 0;
        $moduleinfo->groupingid = 0;
        $moduleinfo->cmidnumber = '';
        $moduleinfo->availability = null;
        $moduleinfo->completion = 0;
        $moduleinfo->grade = 0;
        $cm = add_moduleinfo($moduleinfo, $course);
        $attendanceinstance = $DB->get_record('attendance', ['id' => $cm->instance], '*', MUST_EXIST);
        mtrace("  · Instancia de asistencia creada (id={$attendanceinstance->id})");
    } else {
        mtrace("  · Instancia de asistencia ya existe (id={$attendanceinstance->id})");
    }

    // Default statuses (Present/Absent/Late/Excused) are auto-created by
    // attendance_add_instance() — pick the highest-points one as "present"
    // and the lowest as "absent" rather than assuming exact default labels.
    $statuses = $DB->get_records('attendance_statuses', ['attendanceid' => $attendanceinstance->id, 'deleted' => 0], 'grade DESC');
    $statuses = array_values($statuses);
    $presentstatus = $statuses[0];
    $absentstatus = $statuses[count($statuses) - 1];
    mtrace("  · Estado 'presente' = {$presentstatus->description} ({$presentstatus->grade} pts), 'ausente' = {$absentstatus->description} ({$absentstatus->grade} pts)");

    $existingsessions = $DB->get_records('attendance_sessions', ['attendanceid' => $attendanceinstance->id], 'sessdate ASC');
    if (count($existingsessions) >= 32) {
        mtrace('  · 32 sesiones ya existen — se omite la generación.');
        $sessions = array_values($existingsessions);
    } else {
        // 16 weeks x (Monday, Wednesday) = 32 sessions.
        $sessiondates = [];
        for ($w = 0; $w < 16; $w++) {
            $monday = $COURSE_START + ($w * WEEKSECS) + (9 * HOURSECS); // 09:00.
            $wednesday = $monday + (2 * DAYSECS);
            $sessiondates[] = $monday;
            $sessiondates[] = $wednesday;
        }

        $sessions = [];
        foreach ($sessiondates as $sessdate) {
            $rec = new stdClass();
            $rec->attendanceid = $attendanceinstance->id;
            $rec->groupid = 0;
            $rec->sessdate = $sessdate;
            $rec->duration = HOURSECS;
            $rec->lasttaken = $sessdate;
            $rec->lasttakenby = $teacheruser->id;
            $rec->timemodified = time();
            $rec->description = '';
            $rec->descriptionformat = FORMAT_HTML;
            $rec->studentscanmark = 0;
            $rec->allowupdatestatus = 0;
            $rec->studentsearlyopentime = 0;
            $rec->autoassignstatus = 0;
            $rec->studentpassword = '';
            $rec->subnet = '';
            $rec->automark = 0;
            $rec->automarkcompleted = 0;
            $rec->statusset = 0;
            $rec->absenteereport = 1;
            $rec->preventsharedip = 0;
            $rec->caleventid = 0;
            $rec->calendarevent = 0;
            $rec->includeqrcode = 0;
            $rec->rotateqrcode = 0;
            $rec->automarkcmid = 0;
            $rec->id = $DB->insert_record('attendance_sessions', $rec);
            $sessions[] = $rec;
        }
        mtrace('  · 32 sesiones creadas (' . userdate($sessions[0]->sessdate, '%d/%m/%Y') . ' – ' . userdate(end($sessions)->sessdate, '%d/%m/%Y') . ')');
    }

    // Historical logs, idempotent per (session, student) pair.
    $existinglogcount = $DB->count_records_select(
        'attendance_log',
        'sessionid IN (SELECT id FROM {attendance_sessions} WHERE attendanceid = ?)',
        [$attendanceinstance->id]
    );
    $expectedlogcount = count($sessions) * count($studentusers);
    if ($existinglogcount >= $expectedlogcount) {
        mtrace('  · Registros de asistencia ya existen — se omite la generación.');
    } else {
        foreach ($studentusers as $username => $s) {
            $presentcount = (int) round(($s['att'] / 100) * count($sessions));
            mt_srand((int) $s['user']->id); // deterministic, idempotent shuffle.
            $order = range(0, count($sessions) - 1);
            shuffle($order);
            $presentindexes = array_flip(array_slice($order, 0, $presentcount));

            foreach ($sessions as $idx => $session) {
                $status = isset($presentindexes[$idx]) ? $presentstatus : $absentstatus;
                $log = new stdClass();
                $log->sessionid = $session->id;
                $log->studentid = $s['user']->id;
                $log->statusid = $status->id;
                $log->statusset = '0';
                $log->timetaken = $session->sessdate;
                $log->takenby = $teacheruser->id;
                $log->remarks = '';
                $log->ipaddress = '';
                $DB->insert_record('attendance_log', $log);
            }
            mtrace("  · {$username}: {$presentcount}/" . count($sessions) . ' sesiones presente (' . $s['att'] . '% objetivo)');
        }
    }
}

// -----------------------------------------------------------------------
// 7. Badge (Specification 4).
// -----------------------------------------------------------------------

mtrace('');
mtrace('[7/7] Insignia del curso');

$badgename = 'Microcredencial en Programación y Automatización con Python';
$badgerecord = $DB->get_record('badge', ['courseid' => $course->id, 'name' => $badgename]);

if (!$badgerecord) {
    $fordb = new stdClass();
    $fordb->name = $badgename;
    $fordb->description = 'Certificación oficial que acredita el dominio de fundamentos de programación, desarrollo orientado a objetos, integración de APIs y automatización de procesos con Python.';
    $fordb->timecreated = time();
    $fordb->timemodified = time();
    $fordb->usercreated = $teacheruser->id;
    $fordb->usermodified = $teacheruser->id;
    $fordb->issuername = 'Universidad Politécnica de Texcoco';
    $fordb->issuerurl = $CFG->wwwroot;
    $fordb->issuercontact = 'soporte@uptex.edu.mx';
    $fordb->expiredate = null;
    $fordb->expireperiod = null;
    $fordb->type = BADGE_TYPE_COURSE;
    $fordb->courseid = $course->id;
    $fordb->messagesubject = '¡Felicidades! Has obtenido la Microcredencial en Python';
    $fordb->message = 'Has acreditado el curso MPY-2026 con una calificación igual o superior a 8.0. Esta insignia certifica tu dominio de programación, POO y automatización con Python.';
    $fordb->attachment = 1;
    $fordb->notification = BADGE_MESSAGE_NEVER;
    $fordb->status = BADGE_STATUS_ACTIVE;
    $fordb->nextcron = 0;
    $fordb->version = '1.0';
    $fordb->language = 'es';
    $fordb->id = $DB->insert_record('badge', $fordb);
    $badgerecord = $fordb;
    mtrace("  · Insignia creada (id={$badgerecord->id})");

    // Criteria: overall wrapper (required by the badge system) + a course
    // criterion requiring course grade >= 80 (0-100 scale, matches
    // grade_get_course_grade()->grade used by award_criteria_course::review()).
    $overallcrit = new stdClass();
    $overallcrit->badgeid = $badgerecord->id;
    $overallcrit->criteriatype = BADGE_CRITERIA_TYPE_OVERALL;
    $overallcrit->method = BADGE_CRITERIA_AGGREGATION_ALL;
    $overallcrit->description = '';
    $overallcrit->descriptionformat = FORMAT_HTML;
    $DB->insert_record('badge_criteria', $overallcrit);

    $coursecrit = new stdClass();
    $coursecrit->badgeid = $badgerecord->id;
    $coursecrit->criteriatype = BADGE_CRITERIA_TYPE_COURSE;
    $coursecrit->method = BADGE_CRITERIA_AGGREGATION_ALL;
    $coursecrit->description = 'Calificación final del curso igual o superior a 8.0 (80%).';
    $coursecrit->descriptionformat = FORMAT_HTML;
    $critid = $DB->insert_record('badge_criteria', $coursecrit);

    $DB->insert_record('badge_criteria_param', (object) ['critid' => $critid, 'name' => "course_{$course->id}", 'value' => $course->id]);
    $DB->insert_record('badge_criteria_param', (object) ['critid' => $critid, 'name' => "grade_{$course->id}", 'value' => 80]);
    mtrace('  · Criterio configurado: calificación de curso >= 80');
} else {
    mtrace("  · Insignia ya existe (id={$badgerecord->id})");
}

$badge = new badge($badgerecord->id);

// Badge image — same real PNG asset the credential canvas overlay uses.
$fs = get_file_storage();
$context = $badge->get_context();
$existingimage = $fs->get_file($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1');
if (!$existingimage) {
    $imagepath = $CFG->dirroot . '/theme/saec/pix/badges/badge_python_template.png';
    if (file_exists($imagepath)) {
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'badges',
            'filearea' => 'badgeimage',
            'itemid' => $badge->id,
            'filepath' => '/',
            'filename' => 'f1',
            'timecreated' => time(),
            'timemodified' => time(),
            'userid' => $teacheruser->id,
        ];
        $fs->create_file_from_pathname($filerecord, $imagepath);
        mtrace('  · Imagen badge_python_template.png asociada a la insignia');
    } else {
        mtrace('  ! No se encontró pix/badges/badge_python_template.png — la insignia queda sin imagen.');
    }
} else {
    mtrace('  · Imagen de la insignia ya asociada');
}

// Award — direct issue() to the 3 students whose real, already-recorded
// course grade (set above) genuinely meets the >=8.0 criterion, rather than
// waiting for the badges cron task. nobake=true skips OpenBadges PNG baking
// (not needed — the verification page renders imageurl dynamically).
foreach (SAEC_BADGE_ELIGIBLE as $username) {
    $studentid = $studentusers[$username]['user']->id;
    if ($badge->is_issued($studentid)) {
        mtrace("  · {$username} ya tiene la insignia emitida");
        continue;
    }
    $badge->issue($studentid, true);
    mtrace("  · Insignia emitida a {$username} (promedio {$studentusers[$username]['avg']})");
}

mtrace('');
mtrace('=== Seeding completado ===');
