<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SAEC Corporativo';
$string['choosereadme'] = 'Custom theme for SAEC.';

// Frontpage hero carousel.
$string['slide1title'] = 'Welcome to SAEC';
$string['slide1subtitle'] = 'Continuing education and institutional accreditation.';
$string['slide2title'] = 'Learn at your own pace';
$string['slide2subtitle'] = 'Flexible programmes designed for working professionals.';
$string['slide3title'] = 'Recognised quality';
$string['slide3subtitle'] = 'Accredited courses backed by the SAEC institution.';

// Frontpage course grid.
$string['coursesheading'] = 'Available courses';
$string['coursessubheading'] = 'Explore the programmes currently open for enrolment.';
$string['nocoursesavailable'] = 'There are no courses available right now.';
$string['labelcourse'] = 'COURSE';
$string['labeldata'] = 'INFO';
$string['placeholderinitial'] = 'SAEC';

// Navbar.
$string['loginbutton'] = 'Log in';
$string['opensidebar'] = 'Open main menu';
$string['closesidebar'] = 'Close main menu';

// Sidebar / main drawer — role-based menu (Student, Teacher, Admin).
$string['portaltag'] = 'Academic Portal';
$string['sidebarnav'] = 'Main navigation menu';
$string['navdashboard'] = 'Dashboard';
$string['navmycourses'] = 'My Courses';
$string['navgradebook'] = 'Gradebook';
$string['navattendance'] = 'Attendance';
$string['navstudentprogress'] = 'Students & Progress';
$string['navcredentials'] = 'Credentials';
$string['navanalytics'] = 'My Progress';
$string['navsettings'] = 'Settings';
$string['navsiteadmin'] = 'Site Administration';

// Footer.
$string['footertagline'] = 'Continuing Education and Accreditation System';
$string['footercopyright'] = '© {$a} SAEC. All rights reserved.';

// Home page — hero, value cards, featured courses, accreditation banner
// (Phase 9: i18n audit — extracted from templates/frontpage.mustache).
$string['herotitle'] = 'Power Your Future with World-Class Digital Credentials';
$string['herobtnexplore'] = 'Explore Catalogue';
$string['herobtnvalidate'] = 'Validate Certificate';
$string['feature1title'] = 'Global Validation';
$string['feature1desc'] = 'Credentials verifiable anywhere in the world through blockchain technology.';
$string['feature2title'] = 'Practical Approach';
$string['feature2desc'] = 'Curricula focused on real-world competencies, designed together with industry.';
$string['feature3title'] = 'Alumni Network';
$string['feature3desc'] = 'Connect with a community of professionals certified by UPTex.';
$string['academiceyebrow'] = 'Academic Excellence';
$string['featuredcoursestitle'] = 'Featured Courses & Micro-credentials';
$string['featuredcoursessubtitle'] = 'Gain critical skills in short cycles, with teacher support and verifiable digital certification.';
$string['viewfullcatalogue'] = 'View full catalogue';
$string['accredit1title'] = 'Open Badges v2.0';
$string['accredit1desc'] = 'Metadata standard, 100% compatible with Credly.';
$string['accredit2title'] = 'Competency-Based Model';
$string['accredit2desc'] = 'Practical assessments integrated into Moodle.';
$string['accredit3title'] = 'Official Accreditation';
$string['accredit3desc'] = 'Academic backing from Universidad Politécnica de Texcoco.';
$string['accredit4title'] = 'Learning Pathways';
$string['accredit4desc'] = 'Structured, modular micro-learning.';

// Login page (extracted from login.mustache / core/loginform.mustache).
$string['loginwelcometitle'] = 'Welcome';
$string['loginwelcomesubtitle'] = 'Log in to manage your academic achievements.';
$string['ssogoogle'] = 'Continue with Google';
$string['ssoinstitutional'] = 'Institutional Portal (SSO)';
$string['ssodivider'] = 'Or use your email';
$string['usernameplaceholder'] = 'name@institution.edu';
$string['nosignupaccount'] = "Don't have an account?";
$string['loginherotitle'] = 'Access Your Accredited Badges and Digital Certifications';
$string['loginherosubtitle'] = 'Your professional portfolio, validated by UPTex micro-credential technology.';
$string['loginfootercopyright'] = '© {$a} UPTex. Institutional Micro-credentials Platform.';
$string['legalprivacy'] = 'Privacy';
$string['legalterms'] = 'Terms';
$string['legalaccessibility'] = 'Accessibility';

// Dashboard greeting (drawers.mustache).
$string['dashboardgreeting'] = 'Hi, {$a}!';

// Course / attendance components (components/*.mustache, core_course/*.mustache).
$string['statuspresent'] = 'P';
$string['statuslate'] = 'L';
$string['statusjustified'] = 'J';
$string['statusabsent'] = 'A';
$string['credlyverifiable'] = 'Verifiable on Credly';
$string['viewenrolbutton'] = 'View Details / Enrol';

// Dynamically computed course duration (renderers.php).
$string['courseduration'] = '{$a} weeks';
