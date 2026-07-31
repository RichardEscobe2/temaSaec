<?php
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
