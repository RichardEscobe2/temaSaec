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

// Student Dashboard (classes/dashboard/student_dashboard.php — Phase 1 backend).
$string['dashboardwelcomeback'] = 'Welcome back, {$a}';
$string['dashboardpendingsubmissions'] = 'You have {$a} pending submissions this week.';
$string['dashboardnopending'] = 'You have no pending submissions this week. Great job!';
$string['kpinodata'] = 'N/A';
$string['kpigpa'] = 'Overall GPA';
$string['kpiattendance'] = 'Attendance';
$string['kpistudyhours'] = 'Study Hours';
$string['kpistudyhoursvalue'] = '{$a}h';
$string['kpistudyhoursfootnote'] = 'This week';
$string['kpibadges'] = 'Badges';
$string['kpibadgesfootnote'] = 'Earned';
$string['courseprogresslabel'] = '{$a->completed} of {$a->total} modules';
$string['deadlinetoday'] = 'Today';
$string['deadlineclosingin'] = 'Closes in {$a} hours';
$string['badgeissued'] = 'Issued: {$a}';
$string['backpackexport'] = 'Export';
$string['backpackexportcredly'] = 'Export to Credly';

// templates/student_dashboard.mustache (Phase 2 — layout/drawers.php).
$string['coursesenrolledheading'] = 'My Enrolled Courses';
$string['viewallcourses'] = 'View all';
$string['nocoursesenrolled'] = 'You are not enrolled in any course yet.';
$string['academicsummaryheading'] = 'Academic Activity Summary';
$string['upcomingdeadlinesheading'] = 'Upcoming Deadlines';
$string['nodeadlines'] = 'You have no upcoming deadlines.';
$string['opencalendar'] = 'Open Moodle Calendar';
$string['mybackpackheading'] = 'My Backpack';
$string['nobadgesyet'] = "You haven't earned any badges yet.";
$string['courseprogresslabelheading'] = 'Course progress';
$string['continuecourse'] = 'Continue';

// "My Courses" page (/my/courses.php — classes/dashboard/courses_page.php,
// templates/my_courses_page.mustache, Phase 8).
$string['mycoursespagetitle'] = 'My Courses and Microcredentials';
$string['mycoursespagesubtitle'] = 'Manage your academic progress and institutional certifications.';
$string['mycoursessearchplaceholder'] = 'Search courses...';
$string['tabinprogress'] = 'In Progress';
$string['tabcompleted'] = 'Completed';
$string['tabavailable'] = 'Available';
$string['mycoursescontinuecourse'] = 'Continue Course';
$string['courseprogresspercent'] = '{$a}% complete';
$string['coursefinishedlabel'] = 'Course Finished';
$string['courseavailablenote'] = 'Available - Open Enrolment';
$string['viewbadge'] = 'View Badge';
$string['enrolnow'] = 'Enrol now';
$string['nocoursesintab'] = 'No courses in this category.';

// "My Badge Backpack" page (/badges/mybadges.php —
// classes/dashboard/badges_page.php, templates/badges_page.mustache, Phase 9).
$string['mybadgespagetitle'] = 'My Badge Backpack';
$string['mybadgespagesubtitle'] = 'Manage your academic achievements and institutional certifications.';
$string['tabbadgesall'] = 'All';
$string['tabbadgesinprogress'] = 'In Progress';
$string['badgestatusgranted'] = 'Granted';
$string['badgestatusinprogress'] = 'In Progress';
$string['badgetargetcourse'] = 'Target course: {$a}';
$string['nobadgesintab'] = 'No badges in this category.';
$string['badgecriteriaheading'] = 'Earning Criteria';
$string['downloadbadge'] = 'Download Badge';
$string['viewcoursecriteria'] = 'View Course Criteria';
$string['badgeexpires'] = 'Expires: {$a}';
$string['badgeissuedby'] = 'Issued by';
$string['badgeverify'] = 'Verify badge';
$string['badgehashlabel'] = 'Verification ID';
$string['addtolinkedin'] = 'Add to LinkedIn';
$string['credentialverifiedbanner'] = 'Officially Verified Credential';

// "My Performance" page (/grade/report/overview/index.php —
// classes/dashboard/analytics_page.php, templates/analytics_page.mustache, Phase 13).
$string['analyticspagetitle'] = 'Academic Performance';
$string['analyticspagesubtitle'] = 'Detailed tracking of your progress and achievements at UPTex.';
$string['kpicompletionrate'] = 'Completion Rate';
$string['kpibadgesearned'] = 'Badges Earned';
$string['matrizheading'] = 'Subject Matrix';
$string['colsubject'] = 'Subject';
$string['colgrade'] = 'Grade';
$string['colactivities'] = 'Activities (%)';
$string['colbadge'] = 'Badge';
$string['badgestatusobtained'] = 'Obtained';
$string['badgestatuseligible'] = 'Eligible';
$string['badgestatuspending'] = 'Pending';
$string['timelineheading'] = 'Activity Timeline';
$string['timelinefeedback'] = 'Teacher Feedback';
$string['timelinegrade'] = 'Grade: {$a}';
$string['timelinesubmission'] = 'Assignment Submission';
$string['notimelineitems'] = 'No recent academic activity yet.';
$string['milestoneheading'] = 'Next Milestone';
$string['milestoneremaining'] = '{$a} activities left to complete this course.';
$string['nomilestone'] = "You've completed all your active courses. No pending milestones!";
$string['trendheading'] = 'Trend';
$string['trendup'] = 'Your performance has risen {$a}% this month.';
$string['trenddown'] = 'Your performance has dropped {$a}% compared to last month.';
$string['trendneutral'] = "You're holding steady compared to last month.";
$string['notrenddata'] = 'Not enough historical data yet to calculate a trend.';
$string['statusheading'] = 'Student Status';
$string['statusnodata'] = 'Not enough data';
$string['statusoutstanding'] = 'Outstanding Status';
$string['statusgood'] = 'Good Standing';
$string['statuspassing'] = 'Passing Status';
$string['statusatrisk'] = 'At-Risk Status';
$string['statuscompletionnote'] = '{$a}% overall course completion.';
$string['kpigpafootnote'] = 'Across active courses';
$string['kpistudyhourstotalfootnote'] = 'Total accumulated';
$string['kpicompletionratefootnote'] = 'Overall activity progress';

// "Account Settings" portal (/user/preferences.php —
// templates/preferences_hero.mustache, templates/core/preferences_groups.mustache, Phase 16).
$string['settingspagetitle'] = 'Account Settings';
$string['settingspagesubtitle'] = 'Manage your personal information, system preferences and credentials.';
$string['rolestudent'] = 'Student';
$string['roleteacher'] = 'Teacher';
$string['roleadmin'] = 'Administrator';

// "Course" SaaS overlay (/course/view.php — classes/dashboard/course_view_page.php,
// templates/components/course_view_*.mustache, Phase 17).
$string['coursetabcourse'] = 'Course';
$string['coursetabparticipants'] = 'Participants';
$string['coursetabgrades'] = 'Grades';
$string['coursetabcompetencies'] = 'Competencies';
$string['teacherrolelabel'] = 'Teacher:';
$string['sendmessage'] = 'Send Message';
$string['courseprogressheading'] = 'Course progress';
$string['coursegradeheading'] = 'Current grade';
$string['sidebarnextdeadline'] = 'Next Deadline';
$string['nonextdeadline'] = "You have no upcoming submissions in this course.";
$string['deadlinedaysleft'] = '{$a} days left';
$string['coursedeadlinetoday'] = 'Due today';
$string['viewdeadline'] = 'View details';
$string['sidebarannouncements'] = "Teacher's Announcements";
$string['noannouncements'] = 'No recent announcements.';
$string['sidebarquicklinks'] = 'Quick Resources';
$string['quicklinkforum'] = 'General Forum / Announcements';
$string['quicklinkgrades'] = 'Gradebook';
$string['quicklinkparticipants'] = 'Participants Directory';
$string['quicklinksyllabus'] = 'Guide / Course Syllabus';
$string['sidebarresume'] = 'Resume Last Lesson';
$string['resumelesson'] = 'Continue';
$string['noresume'] = "You haven't visited any activity in this course yet.";
$string['assignstatuspendiente'] = 'Pending';
$string['assignstatusentregado'] = 'Submitted';
$string['assignstatuscalificado'] = 'Graded';
$string['assignduelabel'] = 'Due:';
$string['assignduedateformat'] = '%d %B, %H:%M';
$string['assignsupportmaterial'] = 'Support Material';
$string['assigninstructions'] = 'Instructions';
$string['assignnointro'] = 'No additional written instructions for this assignment. Check the guidance your instructor gave in class, or review the attached files if there are any.';
$string['assignrubric'] = 'Grading Rubric';
$string['assignrubriccriterio'] = 'Criterion';
$string['assignrubricpeso'] = 'Weight';
$string['assignrubricnivel'] = 'Achievement Level';
