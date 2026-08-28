<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SAEC Corporativo';
$string['choosereadme'] = 'Custom theme for SAEC.';

// Frontpage course grid.
$string['coursesheading'] = 'Available courses';
$string['coursessubheading'] = 'Explore the programmes currently open for enrolment.';
$string['nocoursesavailable'] = 'There are no courses available right now.';
$string['placeholderinitial'] = 'SAEC';
$string['courseimagerequired'] = 'A course image is required. Please upload a JPG, PNG or WEBP image in "Course summary files" before saving.';

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
$string['navadmincoursemanagement'] = 'Course Management';
$string['navadmincredentials'] = 'Badges & Credentials';
$string['navuserdirectory'] = 'User Directory';
$string['navreports'] = 'Reports & Audit';

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
$string['usernameplaceholder'] = 'name@institution.edu';
$string['loginherotitle'] = 'Access Your Accredited Badges and Digital Certifications';
$string['loginherosubtitle'] = 'Your professional portfolio, validated by UPTex micro-credential technology.';
$string['loginfootercopyright'] = '© {$a} UPTex. Institutional Micro-credentials Platform.';
$string['legalprivacy'] = 'Privacy';
$string['legalterms'] = 'Terms';
$string['legalaccessibility'] = 'Accessibility';

// Password recovery — institutional assistance card (pages/forgot_password.php).
$string['forgotpasswordinstitutionaltitle'] = 'Institutional Password Reset';
$string['forgotpasswordinstitutionalmessage1'] = 'For institutional security policies and accreditation control, password recovery or reset is handled directly by the <strong>System Administrator / Academic Records Office (SAEC)</strong>.';
$string['forgotpasswordinstitutionalmessage2'] = 'Your password cannot be reset from this site. Request it directly from the relevant office listed below.';
$string['forgotpasswordsupportheading'] = 'Who should I contact?';
$string['forgotpasswordsupportstudentslabel'] = 'Students';
$string['forgotpasswordsupportstudentscontact'] = 'Go to the Academic Records Office or submit a ticket to your program coordination.';
$string['forgotpasswordsupportstafflabel'] = 'Teaching and Administrative Staff';
$string['forgotpasswordsupportstaffcontact'] = 'Contact the Information Technology (IT) Department to reset your account.';
$string['backtologin'] = 'Back to Login';

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

// Teacher Dashboard (/my/) — Sprint 1.
$string['teacherdashheading'] = 'Teacher Dashboard';
$string['teacherdashwelcome'] = 'Welcome back, {$a}. Here is a summary of your courses.';
$string['noupcomingclass'] = 'No upcoming classes scheduled.';
$string['kpipendingsubmissions'] = 'Pending Submissions';
$string['kpiactivecourses'] = 'Active Courses';

// Unified Hero Banner (/my/) — Student, Teacher, Admin all share the same
// structure (components/hero_banner.mustache); these 3 strings were the
// ones missing per role (Teacher already had its own above).
$string['studentdashheading'] = 'Student Dashboard';
$string['admindashheading'] = 'Admin Dashboard';
$string['adminsystemstatuspill'] = 'System Status: Operational · {$a} Active Courses';
$string['heroactionviewtasks'] = 'View Tasks';
$string['heroactionsubmittask'] = 'Submit Task';
$string['pendinggradingheading'] = 'Grading & Pending Submissions';
$string['pendinggradingempty'] = 'No submissions are waiting for grading. Great job!';
$string['colstudent'] = 'Student';
$string['colassignment'] = 'Assignment';
$string['colcourse'] = 'Course';
$string['colsubmitted'] = 'Submitted';
$string['colstatus'] = 'Status';
$string['gradesubmissionaction'] = 'Grade';
$string['statusontime'] = 'On time';
$string['statuslate'] = 'Late';
$string['institutionalannouncementsheading'] = 'Institutional Announcements';
$string['noinstitutionalannouncements'] = 'No announcements have been published yet.';
$string['nextclassinprogress'] = 'Class in progress: {$a}';
$string['nextclassupcoming'] = 'Next class:';
$string['takeattendanceaction'] = 'Take Attendance';
$string['gradingefficiencylabel'] = '{$a}% graded';
$string['viewstudentboleta'] = 'View student report card...';

// Teacher dashboard quick-action bar (teacher_dashboard.mustache) + course-picker modals.
$string['quickactiongradesubmissions'] = 'Grade Submissions';
$string['quickactiontakeattendance'] = 'Take Attendance';
$string['quickactionnewtask'] = '+ New Assignment';
$string['quickactionnewnotice'] = '+ New Announcement';
$string['taskpickertitle'] = 'Select the course to create the assignment in';
$string['noticepickertitle'] = 'Select the course to post the announcement in';
$string['coursepickerempty'] = 'You have no courses available for this action.';

// Student dashboard quick-action bar (student_dashboard.mustache).
$string['quickactionmytasks'] = 'My Tasks';
$string['quickactionmyboleta'] = 'My Report Card';
$string['quickactionmybadges'] = 'My Badges';
$string['quickactionmycalendar'] = 'Calendar';

// Enrolled-courses Grid/List view switcher (student_dashboard.mustache).
$string['courseviewswitcherlabel'] = 'Course list display mode';
$string['courseviewgrid'] = 'Grid view';
$string['courseviewlist'] = 'List view';

// Teacher "My Courses" (/my/courses.php) — Sprint 2.
$string['teachercoursessearchplaceholder'] = 'Search by subject...';
$string['teachercoursesallperiods'] = 'All periods';
$string['teachercoursesnomatch'] = 'No course matches your search.';
$string['teachercoursesempty'] = 'You don\'t have any assigned courses yet.';
$string['teachercoursestudentcount'] = '{$a} Students';
$string['togglecoursevisibility'] = 'Show or hide this course from students';
$string['entercoursebutton'] = 'Enter Course';
$string['managementtoolsheading'] = 'Management Tools';
$string['managementtoolimport'] = 'Clone / Import Course';
$string['managementtoolquestionbank'] = 'Question Bank';
$string['managementtoolgradesettings'] = 'Grade Settings';
$string['managementtoolcompletionsettings'] = 'Configure Completion';
$string['managementtoolspickercourse'] = 'Course to configure';
$string['managementtoolspickerplaceholder'] = 'Select a course...';

// Teacher Course View (/course/view.php) — Sprint 3.
$string['teacherherostudentsheading'] = 'Enrolled Students';
$string['coursequicktoolsheading'] = 'Quick Course Tools';
$string['courseannouncementsheading'] = 'News & Announcements';
$string['coursepostannouncement'] = 'Post Announcement';

// Gradebook "Course Selection Hub" (/grade/report/overview/index.php) — Sprint 4.
$string['graderhubheading'] = 'Select a course to grade';
$string['graderhubsubheading'] = 'You have {$a} assigned courses';
$string['graderhubcta'] = 'View Gradebook';
$string['graderhubsearchplaceholder'] = 'Search by subject...';
$string['graderhubnomatch'] = 'No course matches your search.';

// Student "Boleta Digital" summary (/grade/report/user/index.php) — Sprint 6.
$string['boletaoverallgradelabel'] = 'Overall Grade';
$string['boletacompletedlabel'] = 'Completed Items';
$string['boletastatuslabel'] = 'Status';
$string['boletastatuspass'] = 'Passed';
$string['boletastatusfail'] = 'Failed';
$string['boletastatuspending'] = 'Under Review';

// Assignment View SaaS overlay, teacher branch (/mod/assign/view.php) — Sprint 7.
$string['assignkpiparticipants'] = 'Participants';
$string['assignkpiteams'] = 'Teams';
$string['assignkpidrafts'] = 'Drafts';
$string['assignkpisubmitted'] = 'Submitted';
$string['assignkpineedsgrading'] = 'Needs Grading';

// "Control de Asistencia" Course Selection Hub (theme/saec/pages/attendance_hub.php) — Sprint 9.
$string['attendancehubtitle'] = 'Attendance Control';
$string['attendancehubheading'] = 'Select an activity to manage attendance';
$string['attendancehubsubheading'] = 'You have {$a} attendance activities';
$string['attendancehubsessions'] = '{$a->total} sessions · {$a->taken} taken';
$string['attendancehubratelabel'] = 'Overall Rate';
$string['attendancehubnodata'] = 'No sessions taken yet';
$string['attendancehubcta'] = 'Manage Attendance';
$string['attendancehubempty'] = 'You have no attendance activities yet.';

// Session Management Panel cards (mod/attendance/manage.php) — Sprint 9.
$string['attendancemanagetaken'] = 'Completed';
$string['attendancemanagepending'] = 'Pending';
$string['attendancemanagepresent'] = 'Present';

// Quick Attendance Matrix (mod/attendance/take.php) — Sprint 9.
$string['attendancemarkallpresent'] = 'Mark all as Present';
$string['attendancestatuspresentlabel'] = 'Present';
$string['attendancestatuslatelabel'] = 'Late';
$string['attendancestatusexcusedlabel'] = 'Excused';
$string['attendancestatusabsentlabel'] = 'Absent';

// Admin SaaS Command Center (theme/saec/pages/admin_hub.php).
$string['adminhubgreeting'] = 'Welcome back, {$a}. Here is the site\'s executive summary.';
$string['kpiactivestudents'] = 'Active Students';
$string['kpienrolledteachers'] = 'Enrolled Teachers';
$string['kpiissuedbadges'] = 'Issued Badges';
$string['adminactioncreatecourse'] = '+ Create Course';
$string['adminactionnewuser'] = '+ New User';
$string['adminactionbulkupload'] = 'Bulk Upload (CSV)';
$string['adminactionbadges'] = 'Badge Management';
$string['adminactionpurgecache'] = 'Purge Cache';
$string['adminpurgecachesuccess'] = 'Cache purged successfully';
$string['adminpurgecacheerror'] = 'Error purging the cache';
$string['adminsearchplaceholder'] = 'Search settings, course, or user…';
$string['adminsectioncourses'] = 'Active Courses';
$string['adminsectionusers'] = 'User Directory';
$string['adminsectionsiteadmin'] = 'Site Administration';
$string['admincoursesempty'] = 'No active courses.';
$string['adminusersempty'] = 'No users to display.';
$string['admincoursecolname'] = 'Course';
$string['admincoursecolstudents'] = 'Students';
$string['admincoursecolactions'] = 'Actions';
$string['admincourseactionconfigure'] = 'Configure Course';
$string['admincourseactionparticipants'] = 'Participants';
$string['admincourseactiongrades'] = 'Gradebook';
$string['admincourseactionattendance'] = 'Attendance Control';
$string['adminviewallcourses'] = 'View all courses';
$string['adminusercolname'] = 'User';
$string['adminusercolrole'] = 'Role';
$string['adminusercolstatus'] = 'Status';
$string['adminuseractionedit'] = 'Edit';
$string['adminuseractionroles'] = 'Roles';
$string['adminuserstatusactive'] = 'Active';
$string['adminuserstatussuspended'] = 'Suspended';
$string['adminrolenone'] = 'No role';
$string['adminroleteacher'] = 'Teacher';
$string['adminrolestudent'] = 'Student';
$string['adminviewallusers'] = 'View all users';
$string['adminfiltercourses'] = 'Filter courses…';
$string['adminfilterusers'] = 'Filter users…';
$string['admincategoryappearance'] = 'Appearance & Themes';
$string['admincategoryusers'] = 'Users & Accounts';
$string['admincategorycourses'] = 'Courses & Categories';
$string['admincategorygrades'] = 'Grades & Badges';
$string['admincategoryplugins'] = 'Plugins & Extensions';
$string['admincategoryserver'] = 'Server, Security & Development';
$string['adminlinkthemeselector'] = 'Theme Selector';
$string['adminlinksaecsettings'] = 'SAEC Theme Settings';
$string['adminlinkadditionalhtml'] = 'Additional HTML';
$string['adminlinklogos'] = 'Logos & Branding';
$string['adminlinknavigation'] = 'Site Navigation';
$string['adminlinkuserlist'] = 'User List';
$string['adminlinkroles'] = 'Permissions & Role Definitions';
$string['adminlinkenrolmethods'] = 'Enrolment Methods';
$string['adminlinkcoursemanagement'] = 'Manage Courses and Categories';
$string['adminlinkbackup'] = 'Backup and Restore';
$string['adminlinkgradesettings'] = 'General Grade Settings';
$string['adminlinkbadgesettings'] = 'Badge Settings / Open Badges';
$string['adminlinkcompetencies'] = 'Competencies and Frameworks';
$string['adminlinkpluginsoverview'] = 'Plugins Overview';
$string['adminlinkinstallplugins'] = 'Install Plugins';
$string['adminlinkactivitymodules'] = 'Activity Modules';
$string['adminlinkenvironment'] = 'Environment and System Status';
$string['adminlinkscheduledtasks'] = 'Scheduled Tasks / Cron';
$string['adminlinksecurity'] = 'Security and Policies';
$string['adminlinkdebugging'] = 'Debugging Mode';
$string['adminlinklogs'] = 'Server Logs';
$string['adminlinkpurgecaches'] = 'Purge All Caches';
$string['adminhubnomatch'] = 'No results match your search.';

// Student Dashboard enrichment — Tasks KPI, course card teacher/boleta,
// deadline submit shortcut.
$string['kpitasks'] = 'Tasks';
$string['kpitasksvalue'] = '{$a->completed}/{$a->total}';
$string['kpitaskscompletedlabel'] = '{$a}% completed';
$string['entercoursebutton'] = 'Enter Course';
$string['viewboletabutton'] = 'View Report Card';
$string['submitassignmentbutton'] = 'Submit Assignment';

// Admin Global Course Catalog (/my/courses.php, admin branch).
$string['admincoursecatalogheading'] = 'Course Catalog';
$string['admincoursecatalogsubheading'] = 'Every course in the system, for auditing and quick access.';
$string['adminfiltercatalog'] = 'Search the catalog…';
$string['adminvisibilityvisible'] = 'Visible';
$string['adminvisibilityhidden'] = 'Hidden';

// Admin Course Catalog / Academic Operations Center enrichment.
$string['admincoursecatalogcreatebutton'] = '+ Create New Course';
$string['admincoursecatalogcsvbutton'] = 'Bulk Upload (CSV)';
$string['admincoursecatalogcategoriesbutton'] = 'Manage Categories';
$string['adminfilterall'] = 'All';
$string['adminfiltervisible'] = 'Visible';
$string['adminfilterhidden'] = 'Hidden / In Progress';
$string['admincoursecolteacher'] = 'Lead Teacher';
$string['adminnoteacherassigned'] = 'No Teacher Assigned';
$string['admincourseactionmore'] = 'More ⋯';
$string['admincourseactionduplicate'] = 'Duplicate';
$string['admincourseactionbackup'] = 'Backup (.mbz)';

// Student Tasks Hub ("Mis Tareas", theme/saec/pages/student_tasks.php).
$string['navstudenttasks'] = 'My Tasks';
$string['studenttaskspagetitle'] = 'My Tasks';
$string['studenttaskssubheading'] = 'Every assignment, from every course, in one place.';
$string['studenttaskskpipending'] = 'Pending';
$string['studenttaskskpisubmitted'] = 'Under Review';
$string['studenttaskskpigraded'] = 'Graded';
$string['studenttasksfilterall'] = 'All';
$string['studenttasksfilterpending'] = 'Pending / To Submit';
$string['studenttasksfiltersubmitted'] = 'Submitted';
$string['studenttasksfiltergraded'] = 'Graded';
$string['studenttasksstatuspendiente'] = 'Pending';
$string['studenttasksstatusentregada'] = 'Submitted';
$string['studenttasksstatuscalificada'] = 'Graded';
$string['studenttasksstatuscerrada'] = 'Closed / No Submission';
$string['studenttasksurgencyurgente'] = 'Urgent';
$string['studenttasksurgencyproximo'] = 'Upcoming';
$string['studenttasksurgencycontiempo'] = 'On Time';
$string['studenttasksnoduedate'] = 'No due date';
$string['studenttasksgradevalue'] = '{$a->grade}/{$a->max}';
$string['studenttasksactionsubmit'] = 'Submit Assignment';
$string['studenttasksactionview'] = 'View Submission';
$string['studenttasksactionfeedback'] = 'View Feedback';
$string['studenttasksempty'] = "You don't have any assignments yet.";
$string['studenttasksnomatch'] = 'No tasks match this filter.';

// "Students & Progress" (/grade/report/user/index.php, teacher — Phase 20).
$string['teacherprogresstitle'] = 'Students & Progress';
$string['teacherprogresscourseselector'] = 'Select course';
$string['teacherprogresskpienrolled'] = 'Enrolled Students';
$string['teacherprogresskpiavggrade'] = 'Course Average';
$string['teacherprogresskpisubmissionrate'] = 'Submission Rate';
$string['teacherprogresskpiattendance'] = 'Average Attendance';
$string['teacherprogressattendanceunavailable'] = 'Not available';
$string['teacherprogressatriskheading'] = 'At-Risk Students';
$string['teacherprogressgradeabbrev'] = 'Avg.';
$string['teacherprogressattendanceabbrev'] = 'Attend.';
$string['teacherprogressrosterheading'] = 'Student Roster';
$string['teacherprogresscolstudent'] = 'Student';
$string['teacherprogresscolprogress'] = 'Progress';
$string['teacherprogresscolgrade'] = 'Grade';
$string['teacherprogresscolattendance'] = 'Attendance';
$string['teacherprogresscolstatus'] = 'Status';
$string['teacherprogresscolactions'] = 'Actions';
$string['teacherprogressviewreport'] = 'View Report Card';
$string['teacherprogressnostudents'] = 'This course has no enrolled students yet.';
$string['teacherprogressstatusacreditado'] = 'Accredited';
$string['teacherprogressstatusregular'] = 'Regular';
$string['teacherprogressstatusenriesgo'] = 'At Risk';

// "Reports & Audit" (/report/log/index.php, admin — Phase 20).
$string['adminreportstitle'] = 'Reports & Audit';
$string['adminreportssubtitle'] = 'Real-time system activity and health.';
$string['adminreportsexportcsv'] = 'Export CSV';
$string['adminreportsexportexcel'] = 'Export Excel';
$string['adminreportskpiactiveusers'] = 'Active Users (7 days)';
$string['adminreportskpiglobalaverage'] = 'Institutional Average';
$string['adminreportskpibadges'] = 'Issued Badges';
$string['adminreportskpitodayevents'] = "Today's Events";
$string['adminreportstrendheading'] = 'Activity Trend (7 days)';
$string['adminreportsnotrend'] = 'No activity recorded in this period yet.';
$string['adminreportsauditheading'] = 'Recent Audit Trail';
$string['adminreportscoltimestamp'] = 'Timestamp';
$string['adminreportscoluser'] = 'User';
$string['adminreportscolevent'] = 'Event';
$string['adminreportscolip'] = 'IP Address';
$string['adminreportscolcontext'] = 'Context';
$string['adminreportsnoaudit'] = 'No events recorded yet.';
$string['adminreportsactoranonymous'] = 'Anonymous';
$string['adminreportsactorsystem'] = 'System';
