<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use completion_info;
use context_course;
use grade_grade;
use grade_item;
use moodle_url;
use stdClass;

/**
 * Backend data preparation for the "Course Inner View" SaaS overlay
 * (/course/view.php — Fase 17): a hero banner + sidebar widgets injected
 * ABOVE and BESIDE Moodle's own native section/activity rendering, which is
 * left completely untouched (no template/renderer override, no DOM
 * restructuring) so course-format editing, drag-drop and AJAX completion
 * toggles keep working exactly as core intended — only scss/saec/_course_view.scss
 * restyles that native markup in place.
 *
 * Scoped to students only (is_student()) and never while $PAGE->user_is_editing()
 * is true — teachers/admins, and anyone in edit mode, see the plain native
 * course page with zero theme interference.
 */
class course_view_page {

    /** @var int Max quick-links shown in the "Recursos Rápidos" 2x2 grid. */
    const MAX_QUICK_LINKS = 4;

    /** @var int Max recent teacher announcements shown. */
    const MAX_ANNOUNCEMENTS = 2;

    /** @var bool Guards the require_once calls in bootstrap() against repeated file includes. */
    private static bool $bootstrapped = false;

    private static function bootstrap(): void {
        if (self::$bootstrapped) {
            return;
        }
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/gradelib.php');
        self::$bootstrapped = true;
    }

    /**
     * Unified context for templates/components/course_view_header.mustache
     * and course_view_sidebar.mustache. Returns null whenever the overlay
     * doesn't apply (not a student, editing mode, guest, missing course).
     *
     * @param int $courseid
     * @param int $userid 0 for the current user.
     * @return array|null
     */
    public static function get_context(int $courseid, int $userid = 0): ?array {
        global $USER, $PAGE;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;

        if ($PAGE->user_is_editing() || !student_dashboard::is_student($userid)) {
            return null;
        }

        $course = get_course($courseid);
        $context = context_course::instance($courseid);

        return [
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            'coursecode' => $course->shortname,
        ] + self::get_hero($course, $userid) + [
            'sidebar' => self::get_sidebar($course, $userid),
        ];
    }

    /**
     * Hero banner data: instructor identity, real completion progress, and
     * real course-total grade.
     *
     * @param stdClass $course
     * @param int $userid
     * @return array
     */
    private static function get_hero(stdClass $course, int $userid): array {
        $context = context_course::instance($course->id);

        $instructor = self::resolve_instructor($course->id, $context);

        $completion = new completion_info($course);
        $hasprogress = false;
        $progresspercent = 0;
        if ($completion->is_enabled() && count($completion->get_activities()) > 0) {
            $hasprogress = true;
            $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
            $progresspercent = $percent !== null ? (int) round($percent) : 0;
        }

        $grade = self::compute_course_grade($course->id, $userid);

        return [
            'hasinstructor' => ($instructor !== null),
            'instructorname' => $instructor['name'] ?? null,
            'instructoravatarurl' => $instructor['avatarurl'] ?? null,
            'instructormessageurl' => $instructor['messageurl'] ?? null,
            'hasprogress' => $hasprogress,
            'progresspercent' => $progresspercent,
            'hasgrade' => ($grade !== null),
            'grade' => $grade !== null ? number_format($grade, 1) : null,
        ];
    }

    /**
     * First enrolled user with editing-teacher capability in this course.
     * Delegates to instructor_resolver so course_view_page and
     * assign_view_page always agree on who the teacher is.
     *
     * @param int $courseid
     * @param \context_course $context
     * @return array{name: string, avatarurl: string, messageurl: string}|null
     */
    private static function resolve_instructor(int $courseid, context_course $context): ?array {
        return instructor_resolver::resolve($courseid, $context);
    }

    /**
     * Course total grade normalised to a 0-10 scale — same normalisation
     * used across student_dashboard/courses_page/analytics_page, duplicated
     * here to keep this page's backend self-contained.
     *
     * @param int $courseid
     * @param int $userid
     * @return float|null
     */
    private static function compute_course_grade(int $courseid, int $userid): ?float {
        $courseitem = grade_item::fetch_course_item($courseid);
        if (!$courseitem) {
            return null;
        }
        $grade = grade_grade::fetch(['itemid' => $courseitem->id, 'userid' => $userid]);
        if (!$grade || $grade->finalgrade === null) {
            return null;
        }
        $min = (float) $courseitem->grademin;
        $max = (float) $courseitem->grademax;
        if ($max <= $min) {
            return null;
        }
        return (((float) $grade->finalgrade - $min) / ($max - $min)) * 10;
    }

    /**
     * Sidebar widgets — every item below is real course/user data.
     *
     * @param stdClass $course
     * @param int $userid
     * @return array
     */
    private static function get_sidebar(stdClass $course, int $userid): array {
        $announcements = self::get_announcements($course->id);
        return [
            'nextdeadline' => self::get_next_deadline($course->id, $userid),
            'announcements' => $announcements,
            'hasannouncements' => !empty($announcements),
            'quicklinks' => self::get_quick_links($course->id),
            'resume' => self::get_resume_lesson($course->id, $userid),
        ];
    }

    /**
     * Nearest upcoming assignment/quiz due date in THIS course — same
     * calendar_get_events() source as student_dashboard's own deadline
     * widget, scoped to a single course.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    private static function get_next_deadline(int $courseid, int $userid): array {
        $now = time();
        $events = calendar_get_events($now, $now + (60 * DAYSECS), false, false, [$courseid], true, true);
        $filtered = array_values(array_filter($events, function (stdClass $event) use ($userid): bool {
            $modulename = $event->modulename ?? '';
            if (!in_array($modulename, ['assign', 'quiz'], true)) {
                return false;
            }
            // Skip activities the user has already submitted/attempted — a
            // due date in the future is not "upcoming work" once there's
            // nothing left for the student to do.
            return !self::has_user_completed_activity($modulename, (int) $event->instance, $userid);
        }));
        usort($filtered, fn (stdClass $a, stdClass $b) => $a->timestart <=> $b->timestart);

        if (empty($filtered)) {
            return ['hasdeadline' => false];
        }

        $event = $filtered[0];
        $daysleft = (int) floor(($event->timestart - $now) / DAYSECS);
        $daysleft = max(0, $daysleft);

        return [
            'hasdeadline' => true,
            'title' => format_string($event->name),
            'duedate' => userdate($event->timestart, get_string('strftimedatetimeshort', 'langconfig')),
            'daysleftnote' => ($daysleft === 0)
                ? get_string('coursedeadlinetoday', 'theme_saec')
                : get_string('deadlinedaysleft', 'theme_saec', $daysleft),
            'isurgent' => ($daysleft <= 2),
            'url' => (new moodle_url('/mod/' . $event->modulename . '/view.php', ['id' => $event->instance]))->out(false),
        ];
    }

    /**
     * Whether $userid has already submitted (assign) or finished an attempt
     * (quiz) for the given activity instance — used to keep already-handled
     * work out of the "next deadline" widget even when its due date still
     * lies in the future.
     *
     * @param string $modulename 'assign' or 'quiz'
     * @param int $instanceid The activity's own instance id (event->instance)
     * @param int $userid
     * @return bool
     */
    private static function has_user_completed_activity(string $modulename, int $instanceid, int $userid): bool {
        global $DB;

        if ($modulename === 'assign') {
            return $DB->record_exists('assign_submission', [
                'assignment' => $instanceid,
                'userid' => $userid,
                'status' => 'submitted',
                'latest' => 1,
            ]);
        }

        if ($modulename === 'quiz') {
            return $DB->record_exists('quiz_attempts', [
                'quiz' => $instanceid,
                'userid' => $userid,
                'state' => 'finished',
            ]);
        }

        return false;
    }

    /** @var array<int, array{forum: stdClass, cm: stdClass}|false> Per-request memo for get_news_forum(). */
    private static array $newsforumcache = [];

    /**
     * The course's "news"/announcements forum + its course module, shared by
     * get_announcements() and get_quick_links() — both used to run this
     * exact same lookup independently, firing the identical query twice on
     * every course_view_page request.
     *
     * @param int $courseid
     * @return array{forum: stdClass, cm: stdClass}|null
     */
    private static function get_news_forum(int $courseid): ?array {
        if (array_key_exists($courseid, self::$newsforumcache)) {
            return self::$newsforumcache[$courseid] ?: null;
        }

        global $DB;
        $result = null;
        $forum = $DB->get_record('forum', ['course' => $courseid, 'type' => 'news'], '*', IGNORE_MULTIPLE);
        if ($forum) {
            $cm = get_coursemodule_from_instance('forum', $forum->id, $courseid);
            if ($cm) {
                $result = ['forum' => $forum, 'cm' => $cm];
            }
        }
        self::$newsforumcache[$courseid] = $result ?: false;
        return $result;
    }

    /**
     * Recent posts from the course's own "news"/announcements forum — real
     * discussion titles/snippets, not fabricated feedback text.
     *
     * @param int $courseid
     * @return array
     */
    private static function get_announcements(int $courseid): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $newsforum = self::get_news_forum($courseid);
        if (!$newsforum) {
            return [];
        }
        $forum = $newsforum['forum'];

        $sql = "SELECT d.id, d.name, p.message, p.modified
                  FROM {forum_discussions} d
                  JOIN {forum_posts} p ON p.discussion = d.id AND p.parent = 0
                 WHERE d.forum = :forumid
              ORDER BY p.modified DESC";
        $records = $DB->get_records_sql($sql, ['forumid' => $forum->id], 0, self::MAX_ANNOUNCEMENTS);

        $dateformat = get_string('strftimedatetimeshort', 'langconfig');
        $items = [];
        foreach ($records as $record) {
            $snippet = trim(strip_tags($record->message));
            if (\core_text::strlen($snippet) > 140) {
                $snippet = \core_text::substr($snippet, 0, 140) . '…';
            }
            $items[] = [
                'title' => format_string($record->name),
                'snippet' => $snippet,
                'date' => userdate((int) $record->modified, $dateformat),
                'url' => (new moodle_url('/mod/forum/discuss.php', ['d' => $record->id]))->out(false),
            ];
        }
        return $items;
    }

    /**
     * Four always-real, always-available quick links for this course —
     * grades/participants/course-info are native Moodle pages that exist
     * for every course; the announcements forum link only appears when the
     * course actually has one (never a fabricated "Rubric"/"Base Code").
     *
     * @param int $courseid
     * @return array
     */
    private static function get_quick_links(int $courseid): array {
        global $OUTPUT;
        $icon = fn (string $pix) => $OUTPUT->pix_icon($pix, '', 'moodle', ['class' => 'saec-quicklinks-grid__icon-img']);
        $links = [];

        $newsforum = self::get_news_forum($courseid);
        if ($newsforum) {
            $links[] = [
                'label' => get_string('quicklinkforum', 'theme_saec'),
                'iconhtml' => $icon('t/message'),
                'url' => (new moodle_url('/mod/forum/view.php', ['id' => $newsforum['cm']->id]))->out(false),
            ];
        }

        $links[] = [
            'label' => get_string('quicklinkgrades', 'theme_saec'),
            'iconhtml' => $icon('i/grades'),
            'url' => (new moodle_url('/grade/report/user/index.php', ['id' => $courseid]))->out(false),
        ];
        $links[] = [
            'label' => get_string('quicklinkparticipants', 'theme_saec'),
            'iconhtml' => $icon('i/users'),
            'url' => (new moodle_url('/user/index.php', ['id' => $courseid]))->out(false),
        ];
        $links[] = [
            'label' => get_string('quicklinksyllabus', 'theme_saec'),
            'iconhtml' => $icon('i/info'),
            'url' => (new moodle_url('/course/info.php', ['id' => $courseid]))->out(false),
        ];

        return array_slice($links, 0, self::MAX_QUICK_LINKS);
    }

    /**
     * Most recently viewed course module for this user in this course, from
     * logstore_standard_log — a genuine "resume where you left off" link,
     * not a guess.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    private static function get_resume_lesson(int $courseid, int $userid): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            return ['hasresume' => false];
        }

        $sql = "SELECT l.id, l.contextid, l.timecreated
                  FROM {logstore_standard_log} l
                 WHERE l.userid = :userid AND l.courseid = :courseid
                       AND l.contextlevel = :contextmodule AND l.crud = 'r'
              ORDER BY l.timecreated DESC";
        $rows = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
            'contextmodule' => CONTEXT_MODULE,
        ], 0, 20);

        $modinfo = get_fast_modinfo($courseid, $userid);
        foreach ($rows as $row) {
            try {
                $modulecontext = \context::instance_by_id($row->contextid, IGNORE_MISSING);
            } catch (\dml_exception $e) {
                continue;
            }
            if (!$modulecontext || $modulecontext->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $cms = $modinfo->get_cms();
            if (!isset($cms[$modulecontext->instanceid])) {
                // Module since deleted — a stale log row, not a valid resume target.
                continue;
            }
            $cm = $cms[$modulecontext->instanceid];
            if (!$cm->uservisible) {
                continue;
            }
            return [
                'hasresume' => true,
                'name' => $cm->get_formatted_name(),
                'url' => $cm->url ? $cm->url->out(false) : null,
            ];
        }
        return ['hasresume' => false];
    }
}
