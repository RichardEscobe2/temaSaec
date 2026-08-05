<?php
// Mirrors lang/es/theme_saec.php verbatim. This site only has the "es_mx"
// (Español - México) core language pack installed, not bare "es", and
// es_mx's langconfig.php declares no parentlanguage — so with
// $CFG->lang = 'es_mx', string_manager falls back straight to English for
// any component with no es_mx-specific file, skipping lang/es/ entirely.
// Keep both files in sync when adding/editing theme_saec strings.
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SAEC Corporativo';
$string['choosereadme'] = 'Tema personalizado para el Sistema de Acreditación y Educación Continua (SAEC).';

// Carrusel de portada.
$string['slide1title'] = 'Bienvenido a SAEC';
$string['slide1subtitle'] = 'Educación continua y acreditación institucional.';
$string['slide2title'] = 'Aprende a tu propio ritmo';
$string['slide2subtitle'] = 'Programas flexibles diseñados para profesionales en activo.';
$string['slide3title'] = 'Calidad reconocida';
$string['slide3subtitle'] = 'Cursos acreditados respaldados por la institución SAEC.';

// Grid de cursos de portada.
$string['coursesheading'] = 'Cursos disponibles';
$string['coursessubheading'] = 'Explora los programas actualmente abiertos para inscripción.';
$string['nocoursesavailable'] = 'No hay cursos disponibles en este momento.';
$string['labelcourse'] = 'CURSO';
$string['labeldata'] = 'DATO';
$string['placeholderinitial'] = 'SAEC';

// Navbar.
$string['loginbutton'] = 'Iniciar sesión';
$string['opensidebar'] = 'Abrir menú principal';
$string['closesidebar'] = 'Cerrar menú principal';

// Sidebar / drawer principal — menú según rol (Alumno, Docente, Administrador).
$string['portaltag'] = 'Portal Académico';
$string['sidebarnav'] = 'Menú de navegación principal';
$string['navdashboard'] = 'Panel Principal';
$string['navmycourses'] = 'Mis Cursos';
$string['navgradebook'] = 'Calificador';
$string['navattendance'] = 'Control de Asistencia';
$string['navstudentprogress'] = 'Estudiantes y Progreso';
$string['navcredentials'] = 'Insignias';
$string['navanalytics'] = 'Mi Rendimiento';
$string['navsettings'] = 'Configuración';
$string['navsiteadmin'] = 'Administración del Sitio';

// Pie de página.
$string['footertagline'] = 'Sistema de Acreditación y Educación Continua';
$string['footercopyright'] = '© {$a} SAEC. Todos los derechos reservados.';

// Home page — hero, tarjetas de valor, cursos destacados, acreditación
// (Fase 9: auditoría i18n — extraído de templates/frontpage.mustache).
$string['herotitle'] = 'Potencia tu Futuro con Credenciales Digitales de Clase Mundial';
$string['herobtnexplore'] = 'Explorar Catálogo';
$string['herobtnvalidate'] = 'Validar Certificado';
$string['feature1title'] = 'Validación Global';
$string['feature1desc'] = 'Credenciales verificables en cualquier parte del mundo mediante tecnología blockchain.';
$string['feature2title'] = 'Enfoque Práctico';
$string['feature2desc'] = 'Currícula orientada a competencias reales, diseñada junto a la industria.';
$string['feature3title'] = 'Red Alumni';
$string['feature3desc'] = 'Conecta con una comunidad de profesionales certificados por UPTex.';
$string['academiceyebrow'] = 'Excelencia Académica';
$string['featuredcoursestitle'] = 'Cursos y Microcredenciales Destacadas';
$string['featuredcoursessubtitle'] = 'Adquiere habilidades críticas en ciclos cortos, con acompañamiento docente y certificación digital verificable.';
$string['viewfullcatalogue'] = 'Ver todo el catálogo';
$string['accredit1title'] = 'Open Badges v2.0';
$string['accredit1desc'] = 'Metadata estándar 100% compatible con Credly.';
$string['accredit2title'] = 'Modelo por Competencias';
$string['accredit2desc'] = 'Evaluaciones prácticas integradas en Moodle.';
$string['accredit3title'] = 'Acreditación Oficial';
$string['accredit3desc'] = 'Respaldo académico de la Universidad Politécnica de Texcoco.';
$string['accredit4title'] = 'Rutas Formativas';
$string['accredit4desc'] = 'Micro-aprendizaje estructurado y modular.';

// Página de inicio de sesión (extraído de login.mustache / core/loginform.mustache).
$string['loginwelcometitle'] = 'Bienvenido';
$string['loginwelcomesubtitle'] = 'Inicia sesión para gestionar tus logros académicos.';
$string['ssogoogle'] = 'Continuar con Google';
$string['ssoinstitutional'] = 'Portal Institucional (SSO)';
$string['ssodivider'] = 'O usa tu correo';
$string['usernameplaceholder'] = 'nombre@institucion.edu';
$string['nosignupaccount'] = '¿No tienes una cuenta?';
$string['loginherotitle'] = 'Accede a tus Insignias Acreditadas y Certificaciones Digitales';
$string['loginherosubtitle'] = 'Tu portafolio profesional, validado por la tecnología de micro-credenciales de UPTex.';
$string['loginfootercopyright'] = '© {$a} UPTex. Plataforma de Micro-credenciales Institucionales.';
$string['legalprivacy'] = 'Privacidad';
$string['legalterms'] = 'Términos';
$string['legalaccessibility'] = 'Accesibilidad';

// Saludo del dashboard (drawers.mustache).
$string['dashboardgreeting'] = '¡Hola, {$a}!';

// Componentes de curso / asistencia (components/*.mustache, core_course/*.mustache).
$string['statuspresent'] = 'P';
$string['statuslate'] = 'R';
$string['statusjustified'] = 'J';
$string['statusabsent'] = 'F';
$string['credlyverifiable'] = 'Verificable en Credly';
$string['viewenrolbutton'] = 'Ver Detalles / Inscribirse';

// Duración de curso calculada dinámicamente (renderers.php).
$string['courseduration'] = '{$a} semanas';

// Panel del Alumno (classes/dashboard/student_dashboard.php — Fase 1 backend).
$string['dashboardwelcomeback'] = 'Bienvenido de nuevo, {$a}';
$string['dashboardpendingsubmissions'] = 'Tienes {$a} entregas pendientes para esta semana.';
$string['dashboardnopending'] = 'No tienes entregas pendientes esta semana. ¡Buen trabajo!';
$string['kpinodata'] = 'N/D';
$string['kpigpa'] = 'Promedio General';
$string['kpiattendance'] = 'Asistencia';
$string['kpistudyhours'] = 'Horas de Estudio';
$string['kpistudyhoursvalue'] = '{$a}h';
$string['kpistudyhoursfootnote'] = 'Esta semana';
$string['kpibadges'] = 'Insignias';
$string['kpibadgesfootnote'] = 'Logradas';
$string['courseprogresslabel'] = '{$a->completed} de {$a->total} módulos';
$string['deadlinetoday'] = 'Hoy';
$string['deadlineclosingin'] = 'Cierra en {$a} horas';
$string['badgeissued'] = 'Emitido: {$a}';
$string['backpackexport'] = 'Exportar';
$string['backpackexportcredly'] = 'Exportar a Credly';

// Plantilla templates/student_dashboard.mustache (Fase 2 — layout/drawers.php).
$string['coursesenrolledheading'] = 'Mis Cursos Inscritos';
$string['viewallcourses'] = 'Ver todo';
$string['nocoursesenrolled'] = 'Aún no estás inscrito en ningún curso.';
$string['academicsummaryheading'] = 'Resumen de Actividad Académica';
$string['upcomingdeadlinesheading'] = 'Próximas Entregas';
$string['nodeadlines'] = 'No tienes entregas próximas.';
$string['opencalendar'] = 'Abrir Calendario Moodle';
$string['mybackpackheading'] = 'Mi Mochila';
$string['nobadgesyet'] = 'Aún no has ganado insignias.';
$string['courseprogresslabelheading'] = 'Progreso del curso';
$string['continuecourse'] = 'Continuar';

// Página "Mis Cursos" (/my/courses.php — classes/dashboard/courses_page.php,
// templates/my_courses_page.mustache, Fase 8).
$string['mycoursespagetitle'] = 'Mis Cursos y Microcredenciales';
$string['mycoursespagesubtitle'] = 'Gestiona tu progreso académico y certificaciones institucionales.';
$string['mycoursessearchplaceholder'] = 'Buscar cursos...';
$string['tabinprogress'] = 'En Curso';
$string['tabcompleted'] = 'Completados';
$string['tabavailable'] = 'Disponibles';
$string['mycoursescontinuecourse'] = 'Continuar Curso';
$string['courseprogresspercent'] = '{$a}% completado';
$string['coursefinishedlabel'] = 'Curso Finalizado';
$string['courseavailablenote'] = 'Disponible - Matrícula Abierta';
$string['viewbadge'] = 'Ver Insignia';
$string['enrolnow'] = 'Inscribirse ahora';
$string['nocoursesintab'] = 'No hay cursos en esta categoría.';

// Página "Mi Mochila de Insignias" (/badges/mybadges.php —
// classes/dashboard/badges_page.php, templates/badges_page.mustache, Fase 9).
$string['mybadgespagetitle'] = 'Mi Mochila de Insignias';
$string['mybadgespagesubtitle'] = 'Gestiona tus logros académicos y certificaciones institucionales.';
$string['tabbadgesall'] = 'Todas';
$string['tabbadgesinprogress'] = 'En Proceso';
$string['badgestatusgranted'] = 'Otorgado';
$string['badgestatusinprogress'] = 'En Proceso';
$string['badgetargetcourse'] = 'Curso objetivo: {$a}';
$string['nobadgesintab'] = 'No hay insignias en esta categoría.';
$string['badgecriteriaheading'] = 'Criterios de Obtención';
$string['downloadbadge'] = 'Descargar Insignia';
$string['viewcoursecriteria'] = 'Ver Criterios del Curso';
$string['badgeexpires'] = 'Expira: {$a}';
$string['badgeissuedby'] = 'Emitido por';
$string['badgeverify'] = 'Verificar insignia';
$string['badgehashlabel'] = 'ID de verificación';
$string['addtolinkedin'] = 'Añadir a LinkedIn';
$string['credentialverifiedbanner'] = 'Credencial Oficialmente Verificada';

// Página "Mi Rendimiento" (/grade/report/overview/index.php —
// classes/dashboard/analytics_page.php, templates/analytics_page.mustache, Fase 13).
$string['analyticspagetitle'] = 'Rendimiento Académico';
$string['analyticspagesubtitle'] = 'Seguimiento detallado de tu progreso y logros en UPTex.';
$string['kpicompletionrate'] = 'Tasa de Completado';
$string['kpibadgesearned'] = 'Insignias Ganadas';
$string['matrizheading'] = 'Matriz de Materias';
$string['colsubject'] = 'Asignatura';
$string['colgrade'] = 'Calificación';
$string['colactivities'] = 'Actividades (%)';
$string['colbadge'] = 'Insignia';
$string['badgestatusobtained'] = 'Obtenida';
$string['badgestatuseligible'] = 'Eligible';
$string['badgestatuspending'] = 'Pendiente';
$string['timelineheading'] = 'Línea de Actividad';
$string['timelinefeedback'] = 'Feedback de Profesor';
$string['timelinegrade'] = 'Calificación: {$a}';
$string['timelinesubmission'] = 'Entrega de Tarea';
$string['notimelineitems'] = 'Aún no hay actividad académica reciente.';
$string['milestoneheading'] = 'Próximo Hito';
$string['milestoneremaining'] = 'Te faltan {$a} actividades para completar este curso.';
$string['nomilestone'] = 'Has completado todos tus cursos activos. ¡Sin hitos pendientes!';
$string['trendheading'] = 'Tendencia';
$string['trendup'] = 'Tu rendimiento ha subido un {$a}% este mes.';
$string['trenddown'] = 'Tu rendimiento ha bajado un {$a}% respecto al mes anterior.';
$string['trendneutral'] = 'Mantienes estabilidad respecto al mes anterior.';
$string['notrenddata'] = 'Aún no hay suficientes datos históricos para calcular una tendencia.';
$string['statusheading'] = 'Estatus de Alumno';
$string['statusnodata'] = 'Sin datos suficientes';
$string['statusoutstanding'] = 'Estatus Sobresaliente';
$string['statusgood'] = 'Estatus Satisfactorio';
$string['statuspassing'] = 'Estatus Aprobatorio';
$string['statusatrisk'] = 'Estatus en Riesgo';
$string['statuscompletionnote'] = '{$a}% de finalización general de cursos.';
$string['kpigpafootnote'] = 'En cursos activos';
$string['kpistudyhourstotalfootnote'] = 'Total acumulado';
$string['kpicompletionratefootnote'] = 'Avance global de actividades';

// Portal "Configuración de Cuenta" (/user/preferences.php —
// templates/preferences_hero.mustache, templates/core/preferences_groups.mustache, Fase 16).
$string['settingspagetitle'] = 'Configuración de Cuenta';
$string['settingspagesubtitle'] = 'Administra tu información personal, preferencias del sistema y credenciales.';
$string['rolestudent'] = 'Alumno';
$string['roleteacher'] = 'Docente';
$string['roleadmin'] = 'Administrador';

// Portal "Curso" SaaS overlay (/course/view.php — classes/dashboard/course_view_page.php,
// templates/components/course_view_*.mustache, Fase 17).
$string['coursetabcourse'] = 'Curso';
$string['coursetabparticipants'] = 'Participantes';
$string['coursetabgrades'] = 'Calificaciones';
$string['coursetabcompetencies'] = 'Competencias';
$string['teacherrolelabel'] = 'Docente:';
$string['sendmessage'] = 'Enviar Mensaje';
$string['courseprogressheading'] = 'Progreso del curso';
$string['coursegradeheading'] = 'Calificación actual';
$string['sidebarnextdeadline'] = 'Próximo Vencimiento';
$string['nonextdeadline'] = 'No tienes entregas próximas en este curso.';
$string['deadlinedaysleft'] = '{$a} días restantes';
$string['coursedeadlinetoday'] = 'Vence hoy';
$string['viewdeadline'] = 'Ver detalles';
$string['sidebarannouncements'] = 'Avisos del Profesor';
$string['noannouncements'] = 'Sin avisos recientes.';
$string['sidebarquicklinks'] = 'Recursos Rápidos';
$string['quicklinkforum'] = 'Foro General / Avisos';
$string['quicklinkgrades'] = 'Libro de Calificaciones';
$string['quicklinkparticipants'] = 'Directorio de Participantes';
$string['quicklinksyllabus'] = 'Guía / Programa del Curso';
$string['sidebarresume'] = 'Reanudar Última Lección';
$string['resumelesson'] = 'Continuar';
$string['noresume'] = 'Aún no has visitado ninguna actividad de este curso.';
$string['assignstatuspendiente'] = 'Pendiente';
$string['assignstatusentregado'] = 'Entregado';
$string['assignstatuscalificado'] = 'Calificado';
$string['assignduelabel'] = 'Vence:';
$string['assignduedateformat'] = '%d de %B, %H:%M';
$string['assignsupportmaterial'] = 'Material de Apoyo';
$string['assigninstructions'] = 'Instrucciones';
$string['assignnointro'] = 'No hay instrucciones escritas adicionales para esta tarea. Consulta las indicaciones expresadas por tu docente en clase o revisa los archivos adjuntos si los hay.';
$string['assignrubric'] = 'Rúbrica de Evaluación';
$string['assignrubriccriterio'] = 'Criterio';
$string['assignrubricpeso'] = 'Peso';
$string['assignrubricnivel'] = 'Nivel de Logro';

// Panel del Docente (/my/) — Sprint 1.
$string['teacherdashheading'] = 'Panel del Docente';
$string['teacherdashwelcome'] = 'Bienvenido de nuevo, {$a}. Aquí está el resumen de sus cursos.';
$string['kpipendingsubmissions'] = 'Entregas Pendientes';
$string['kpiactivecourses'] = 'Cursos Activos';
$string['pendinggradingheading'] = 'Calificador y Entregas Pendientes';
$string['pendinggradingempty'] = 'No hay entregas pendientes por calificar. ¡Buen trabajo!';
$string['colstudent'] = 'Estudiante';
$string['colassignment'] = 'Tarea';
$string['colcourse'] = 'Curso';
$string['colsubmitted'] = 'Entregado';
$string['colstatus'] = 'Estado';
$string['gradesubmissionaction'] = 'Calificar';
$string['statusontime'] = 'A tiempo';
$string['statuslate'] = 'Con retraso';
$string['institutionalannouncementsheading'] = 'Anuncios Institucionales';
$string['noinstitutionalannouncements'] = 'Aún no hay anuncios publicados.';
$string['nextclassinprogress'] = 'Clase en curso: {$a}';
$string['nextclassupcoming'] = 'Próxima clase:';
$string['takeattendanceaction'] = 'Iniciar Pase de Lista';
$string['gradingefficiencylabel'] = '{$a}% calificado';

// Mis Cursos del Docente (/my/courses.php) — Sprint 2.
$string['teachercoursessearchplaceholder'] = 'Buscar por materia...';
$string['teachercoursesallperiods'] = 'Todos los periodos';
$string['teachercoursesnomatch'] = 'Ningún curso coincide con tu búsqueda.';
$string['teachercoursesempty'] = 'Aún no tienes cursos asignados.';
$string['teachercoursestudentcount'] = '{$a} Alumnos';
$string['togglecoursevisibility'] = 'Mostrar u ocultar este curso a los estudiantes';
$string['entercoursebutton'] = 'Entrar';
$string['managementtoolsheading'] = 'Herramientas de Gestión';
$string['managementtoolimport'] = 'Clonar / Importar Curso';
$string['managementtoolquestionbank'] = 'Banco de Preguntas';
$string['managementtoolgradesettings'] = 'Configuración de Calificaciones';
$string['managementtoolcompletionsettings'] = 'Configurar Finalización';
$string['managementtoolspickercourse'] = 'Curso a configurar';
$string['managementtoolspickerplaceholder'] = 'Selecciona un curso...';

// Vista de Curso del Docente (/course/view.php) — Sprint 3.
$string['teacherherostudentsheading'] = 'Alumnos Inscritos';
$string['coursequicktoolsheading'] = 'Herramientas Rápidas del Curso';
$string['courseannouncementsheading'] = 'Avisos y Novedades';
$string['coursepostannouncement'] = 'Publicar Anuncio';
