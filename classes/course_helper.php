<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec;

use stdClass;

/**
 * Centralized course-image resolution — single source of truth reused by
 * every dashboard/catalog data provider (student, teacher, admin) so they
 * never disagree about which file is "the" course image or how its URL is
 * built. Also home to to_relative_url(), the shared fix for every other
 * image URL this theme emits (avatars, badges) — see that method's
 * docblock for why.
 */
class course_helper {
    /**
     * Resolves a course's overview image (the file uploaded via the
     * "overviewfiles" course summary field — see hook_callbacks for where
     * that upload is made mandatory) to a public pluginfile URL.
     *
     * Delegates to core's own \core_course\cache\course_image data source
     * (course/classes/cache/course_image.php, registered in
     * lib/db/caches.php) rather than re-walking get_course_overviewfiles()
     * here — it already builds this exact URL correctly (crucially, with a
     * null itemid: overviewfiles is a non-itemid pluginfile area, so a
     * literal 0 there produces a URL with a spurious extra path segment
     * that 404s — confirmed live during this round's QA) and is
     * automatically invalidated by core on every course update
     * (course/lib.php's update_course()), so this can never go stale.
     *
     * Returns null for a legacy course saved before that requirement
     * existed, or for a course whose overview file area holds only
     * non-image attachments — callers are expected to fall back to the
     * shared institutional placeholder (.saec-card-img__placeholder) rather
     * than fabricate a URL.
     *
     * The URL itself is stripped down to root-relative (see
     * to_relative_url()) before being returned, so this never needs
     * touching again by any of its many callers.
     *
     * @param stdClass $course
     * @return string|null
     */
    public static function get_course_image_url(stdClass $course): ?string {
        $url = \cache::make('core', 'course_image')->get($course->id);
        return self::to_relative_url($url !== false ? $url : null);
    }

    /**
     * Strips scheme+host from an absolute Moodle URL so the browser
     * re-requests it against whatever host is currently serving the page.
     *
     * Every moodle_url::out()/out(false) call bakes in $CFG->wwwroot's
     * static scheme+host. That is fine for the page's own navigation (the
     * browser is already there), but breaks every <img> the instant the
     * page is reached through a different host than $CFG->wwwroot names —
     * an ngrok/reverse-proxy tunnel, a LAN IP, a second domain — either as
     * a flat wrong-host 404/refused-connection, or as a mixed-content block
     * when $CFG->wwwroot is http:// but the tunnel serves https://. A
     * root-relative URL sidesteps this entirely: the browser resolves it
     * against its own current origin, which is always correct for a
     * same-server resource regardless of which hostname reached it.
     *
     * Deliberately scoped to image URLs only (course overview images,
     * avatars, badges — the call sites that route through this method),
     * not every moodle_url the theme emits: navigation/action links are
     * unaffected by this failure mode (the browser is already on the
     * current host when a link is clicked) and reworking those is outside
     * this fix's scope.
     *
     * @param string|null $url An absolute URL, typically from
     *                          moodle_url::out()/out(false). Null passes through unchanged.
     * @return string|null
     */
    public static function to_relative_url(?string $url): ?string {
        if ($url === null || $url === '') {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        $relative = $path;
        if (!empty($query)) {
            $relative .= '?' . $query;
        }
        if (!empty($fragment)) {
            $relative .= '#' . $fragment;
        }
        return $relative;
    }
}
