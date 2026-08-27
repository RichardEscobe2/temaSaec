<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use context_course;
use moodle_url;
use user_picture;

/**
 * Single source of truth for "who is the teacher of this course" — used by
 * both course_view_page (hero banner) and assign_view_page (assignment
 * header) so the two SaaS overlays never resolve a different teacher for
 * the same course.
 */
final class instructor_resolver {

    /**
     * First enrolled user with editing-teacher capability in the course —
     * same detection convention already used by layout/drawers.php's own
     * sidebar role check. Cached in MUC (see db/caches.php) since this is
     * looked up on every course_view_page/assign_view_page request and
     * teacher assignment changes far less often than that — a short TTL
     * trades a little staleness tolerance for skipping get_enrolled_users()
     * on nearly every student page load.
     *
     * @param int $courseid
     * @param context_course $context
     * @return array{name: string, avatarurl: string, messageurl: string}|null
     */
    public static function resolve(int $courseid, context_course $context): ?array {
        $cache = \cache::make('theme_saec', 'instructor');
        $cachekey = (string) $courseid;
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached ?: null;
        }

        $instructor = self::resolve_uncached($courseid, $context);
        // cache_store can't distinguish a cached `false` from a cache miss,
        // so a null result (no teacher enrolled) is stored as an empty
        // array rather than `false`.
        $cache->set($cachekey, $instructor ?? []);
        return $instructor;
    }

    /**
     * @param int $courseid
     * @param context_course $context
     * @return array{name: string, avatarurl: string, messageurl: string}|null
     */
    private static function resolve_uncached(int $courseid, context_course $context): ?array {
        global $PAGE;
        $enrolled = get_enrolled_users($context, 'moodle/course:update');
        $teacher = reset($enrolled);
        if (!$teacher) {
            return null;
        }

        $picture = new user_picture($teacher);
        $picture->size = 100;

        return [
            'name' => fullname($teacher),
            'avatarurl' => \theme_saec\course_helper::to_relative_url($picture->get_url($PAGE)->out(false)),
            'messageurl' => (new moodle_url('/message/index.php', ['id' => $teacher->id]))->out(false),
        ];
    }
}
