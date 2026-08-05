<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use context_course;
use moodle_url;
use stdClass;

/**
 * Backend data preparation for the "Teacher Course View" SaaS overlay
 * (/course/view.php — Sprint 3): the teacher-role counterpart of
 * course_view_page.php. Same non-negotiable principle as that class — the
 * native section/activity content, drag-and-drop reordering, AJAX
 * completion toggles and inline editing are NEVER touched or restyled
 * beyond paint; only a hero header + sidebar are injected above/beside
 * output.main_content (see the wrapper in templates/drawers.mustache).
 *
 * Deliberately does NOT auto-disable during $PAGE->user_is_editing() the
 * way course_view_page.php does for students — for a teacher, edit mode is
 * an everyday state, not an edge case, and the header carries the real
 * "Activar edición" control itself (via $OUTPUT->page_heading_button(), the
 * same public API core's own layouts use — never reimplemented), so hiding
 * the whole overlay the moment editing turns on would make that very
 * button disappear.
 */
class teacher_course_view_page {

    /** @var int Max recent course announcements shown in the sidebar. */
    const MAX_ANNOUNCEMENTS = 3;

    /**
     * Unified context for templates/components/teacher_course_view_header.mustache
     * and teacher_course_view_sidebar.mustache. Returns null whenever the
     * overlay doesn't apply (not a teacher, guest, missing/site course).
     *
     * @param int $courseid
     * @param int $userid 0 for the current user.
     * @return array|null
     */
    public static function get_context(int $courseid, int $userid = 0): ?array {
        global $USER, $OUTPUT;
        $userid = $userid ?: (int) $USER->id;

        if (!teacher_dashboard::is_teacher($userid)) {
            return null;
        }

        $course = get_course($courseid);
        $context = context_course::instance($courseid);
        $categorycache = [];

        return [
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            'coursecode' => $course->shortname,
            'categoryname' => self::resolve_category_name((int) $course->category, $categorycache),
            // Core's own edit-mode toggle, relocated (not rebuilt) into our
            // hero — see the class docblock. $PAGE->button is already
            // populated by course/view.php by the time this layout runs.
            'editbuttonhtml' => $OUTPUT->page_heading_button(),
            'studentcount' => self::count_students($courseid, $context),
            'pendinggradingcount' => count(self::fetch_pending_grading_for_course($courseid)),
            'sidebar' => self::get_sidebar($course, $courseid),
        ];
    }

    /**
     * Active (status=0) student-role enrolment count for a single course —
     * same batched-query shape as teacher_courses_page::fetch_student_counts()
     * reduced to one course id; kept as its own copy since that method is
     * private to its own class (same duplication convention already used
     * for resolve_category_name() across this page family).
     *
     * @param int $courseid
     * @param context_course $context
     * @return int
     */
    private static function count_students(int $courseid, context_course $context): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT ue.userid)
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND e.courseid = :courseid
                  JOIN {role_assignments} ra ON ra.userid = ue.userid AND ra.contextid = :contextid
                  JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                 WHERE ue.status = 0";

        return (int) $DB->count_records_sql($sql, ['courseid' => $courseid, 'contextid' => $context->id]);
    }

    /**
     * Submissions awaiting grading in THIS course only — same "needs
     * grading" definition as teacher_dashboard::fetch_pending_grading(),
     * scoped to a single course rather than every course a teacher teaches.
     *
     * @param int $courseid
     * @return stdClass[]
     */
    private static function fetch_pending_grading_for_course(int $courseid): array {
        global $DB;

        $sql = "SELECT s.id
                  FROM {assign_submission} s
                  JOIN {assign} a ON a.id = s.assignment AND a.course = :courseid
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
             LEFT JOIN {assign_grades} g ON g.assignment = a.id AND g.userid = s.userid
                        AND g.attemptnumber = s.attemptnumber
                 WHERE cm.visible = 1
                       AND s.latest = 1
                       AND s.status = 'submitted'
                       AND (g.id IS NULL OR g.timemodified < s.timemodified)";

        return array_values($DB->get_records_sql($sql, ['courseid' => $courseid]));
    }

    /**
     * Sidebar widgets: quick tool links bound to THIS course id (no
     * cross-course picker needed — we're already inside one course's
     * context) and this course's own announcements.
     *
     * @param stdClass $course
     * @param int $courseid
     * @return array
     */
    private static function get_sidebar(stdClass $course, int $courseid): array {
        global $CFG;
        $attendanceinstalled = file_exists($CFG->dirroot . '/mod/attendance/version.php');
        $announcements = self::get_announcements($courseid);

        return [
            'gradebookurl' => (new moodle_url('/grade/report/index.php', ['id' => $courseid]))->out(false),
            'hasattendance' => $attendanceinstalled,
            'attendanceurl' => $attendanceinstalled
                ? (new moodle_url('/mod/attendance/index.php', ['id' => $courseid]))->out(false)
                : null,
            'questionbankurl' => (new moodle_url('/question/edit.php', ['courseid' => $courseid]))->out(false),
            'completionsettingsurl' => (new moodle_url('/course/completion.php', ['id' => $courseid]))->out(false),
            'hasannouncements' => !empty($announcements),
            'announcements' => $announcements,
            'announcementsforumurl' => self::get_announcements_forum_url($courseid),
        ];
    }

    /** @var array<int, array{forum: stdClass, cm: stdClass}|false> Per-request memo for get_news_forum(). */
    private static array $newsforumcache = [];

    /**
     * The course's "news"/announcements forum + its course module — same
     * lookup shape as course_view_page::get_news_forum(), duplicated for
     * the same reason as resolve_category_name() above.
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
     * Recent posts from the course's own announcements forum.
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
     * Link to the course's announcements forum itself (to post a new one),
     * null when the course has none.
     *
     * @param int $courseid
     * @return string|null
     */
    private static function get_announcements_forum_url(int $courseid): ?string {
        $newsforum = self::get_news_forum($courseid);
        if (!$newsforum) {
            return null;
        }
        return (new moodle_url('/mod/forum/view.php', ['id' => $newsforum['cm']->id]))->out(false);
    }

    /**
     * Resolves + caches a course category's display name — same per-request
     * pattern as courses_page::resolve_category_name(), duplicated per this
     * page family's established convention.
     *
     * @param int $categoryid
     * @param array $cache Passed by reference, shared across calls.
     * @return string
     */
    private static function resolve_category_name(int $categoryid, array &$cache): string {
        global $DB;
        if (!array_key_exists($categoryid, $cache)) {
            $name = $DB->get_field('course_categories', 'name', ['id' => $categoryid]);
            $cache[$categoryid] = ($name !== false) ? format_string($name) : '';
        }
        return $cache[$categoryid];
    }
}
