<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use badge;
use completion_info;
use context_course;
use moodle_url;
use stdClass;

/**
 * Backend data preparation for "Mi Mochila de Insignias" (/badges/mybadges.php
 * — Fase 9). Replaces core's badge management page output for students
 * (hidden via scss/custom.scss) with two real, non-fabricated lists:
 *
 * - "Todas": every badge already issued to the user (badges_get_user_badges).
 * - "En Proceso": active course badges (BADGE_TYPE_COURSE) tied to a course
 *   the student is enrolled in but hasn't finished yet, and hasn't already
 *   earned — i.e. the badge they'll receive on completing that course.
 *
 * The Credly/external-backpack tab and connection banner from the
 * stitch_uptex mockup are intentionally omitted per this phase's scope.
 */
class badges_page {

    /** @var bool Guards the require_once call in bootstrap() against repeated file includes. */
    private static bool $bootstrapped = false;

    private static function bootstrap(): void {
        if (self::$bootstrapped) {
            return;
        }
        global $CFG;
        require_once($CFG->libdir . '/badgeslib.php');
        require_once($CFG->libdir . '/completionlib.php');
        self::$bootstrapped = true;
    }

    /**
     * Unified context for templates/badges_page.mustache.
     *
     * @param int $userid 0 for the current user.
     * @return array
     */
    public static function get_context(int $userid = 0): array {
        global $USER, $CFG;
        self::bootstrap();
        $userid = $userid ?: (int) $USER->id;

        if (empty($CFG->enablebadges)) {
            return [
                'earnedcount' => 0, 'hasearned' => false, 'earnedbadges' => [],
                'inprogresscount' => 0, 'hasinprogress' => false, 'inprogressbadges' => [],
            ];
        }

        $earned = self::get_earned_badges($userid);
        $excludedids = array_column($earned, 'badgeid');
        $inprogress = self::get_inprogress_badges($userid, $excludedids);

        return [
            'earnedcount' => count($earned),
            'hasearned' => !empty($earned),
            'earnedbadges' => $earned,
            'inprogresscount' => count($inprogress),
            'hasinprogress' => !empty($inprogress),
            'inprogressbadges' => $inprogress,
        ];
    }

    /**
     * Every badge already issued to the user, newest first (native sort
     * order of badges_get_user_badges()).
     *
     * @param int $userid
     * @return array
     */
    private static function get_earned_badges(int $userid): array {
        $records = badges_get_user_badges($userid) ?: [];
        $categorycache = [];
        $dateformat = get_string('strftimedatefullshort', 'langconfig');

        $badges = [];
        foreach ($records as $record) {
            // A full \badge object (not just the badges_get_user_badges() row)
            // is needed for get_context()/print_badge_criteria() below — its
            // constructor also populates ->criteria, which the raw row lacks.
            $badgeobj = new badge((int) $record->id);
            $verifyurl = (new moodle_url('/badges/badge.php', ['hash' => $record->uniquehash]))->out(false);

            $badges[] = [
                'badgeid' => (int) $record->id,
                'title' => format_string($record->name),
                'imageurl' => self::resolve_badge_object_image_url($badgeobj)->out(false),
                'hasissued' => true,
                'subtitle' => self::resolve_earned_subtitle($record, $categorycache),
                'issuername' => format_string($badgeobj->issuername ?? ''),
                'date' => get_string('badgeissued', 'theme_saec', userdate($record->dateissued, $dateformat)),
                'hasexpiry' => !empty($record->dateexpire),
                'expirydate' => !empty($record->dateexpire)
                    ? get_string('badgeexpires', 'theme_saec', userdate($record->dateexpire, $dateformat))
                    : null,
                'uniquehash' => $record->uniquehash,
                'verifyurl' => $verifyurl,
                'isinprogress' => false,
                'targetcoursename' => null,
                'targetcourseurl' => null,
                'description' => self::resolve_description($badgeobj),
                'criteriahtml' => self::resolve_criteria_html($badgeobj),
                'downloadurl' => (new moodle_url('/badges/mybadges.php', [
                    'download' => $record->id,
                    'hash' => $record->uniquehash,
                    'sesskey' => sesskey(),
                ]))->out(false),
                'linkedinurl' => self::build_linkedin_url($badgeobj, $record, $verifyurl),
            ];
        }
        return $badges;
    }

    /**
     * Active course badges tied to a course the student is enrolled in but
     * hasn't completed, excluding badges already earned (dateissued set, or
     * present in $excludedbadgeids from the "Todas" list).
     *
     * @param int $userid
     * @param int[] $excludedbadgeids Badge ids already shown under "Todas".
     * @return array
     */
    private static function get_inprogress_badges(int $userid, array $excludedbadgeids): array {
        $courses = enrol_get_all_users_courses($userid, true, 'fullname, category, enablecompletion, visible');
        $seen = [];
        $badges = [];

        foreach ($courses as $course) {
            if (empty($course->visible) || self::is_course_completed($course, $userid)) {
                continue;
            }

            $coursebadges = badges_get_badges(BADGE_TYPE_COURSE, (int) $course->id, '', '', 0, 0, $userid);
            foreach ($coursebadges as $badge) {
                $badgeid = (int) $badge->id;
                if (!empty($badge->dateissued) || isset($seen[$badgeid]) || in_array($badgeid, $excludedbadgeids, true)) {
                    continue;
                }
                $seen[$badgeid] = true;

                $badges[] = [
                    'badgeid' => $badgeid,
                    'title' => format_string($badge->name),
                    'imageurl' => self::resolve_badge_object_image_url($badge)->out(false),
                    'hasissued' => false,
                    'subtitle' => null,
                    'date' => null,
                    'isinprogress' => true,
                    'targetcoursename' => format_string(
                        $course->fullname,
                        true,
                        ['context' => context_course::instance((int) $course->id)]
                    ),
                    'targetcourseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    'description' => self::resolve_description($badge),
                    'criteriahtml' => self::resolve_criteria_html($badge),
                    'downloadurl' => null,
                ];
            }
        }
        return $badges;
    }

    /**
     * Real completion percentage (not Moodle's date-based grouping) —
     * mirrors courses_page::export_enrolled_card's classification. Courses
     * without completion tracking enabled default to "not completed" (can't
     * prove otherwise), same convention used there.
     *
     * @param stdClass $course
     * @param int $userid
     * @return bool
     */
    private static function is_course_completed(stdClass $course, int $userid): bool {
        $completion = new completion_info($course);
        if (!$completion->is_enabled()) {
            return false;
        }
        if (count($completion->get_activities()) === 0) {
            return false;
        }
        $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
        return ($percent !== null && round($percent) >= 100);
    }

    /**
     * Course category name shown as the card's subtitle, for course badges
     * only — site badges have no category and leave this empty; the
     * institution/issuer name is now its own dedicated 'issuername' field
     * (populated for every badge, course or site), not folded into this one.
     *
     * @param stdClass $record Row from badges_get_user_badges() (badge.* + bi.* + u.email).
     * @param array $categorycache Passed by reference, shared across calls.
     * @return string
     */
    private static function resolve_earned_subtitle(stdClass $record, array &$categorycache): string {
        global $DB;
        if (!empty($record->courseid)) {
            $categoryid = $DB->get_field('course', 'category', ['id' => $record->courseid]);
            if ($categoryid !== false) {
                return self::resolve_category_name((int) $categoryid, $categorycache);
            }
        }
        return '';
    }

    /**
     * Resolves + caches a course category's display name (one query per
     * distinct category id per request, not per badge).
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

    /**
     * Resolves the public image URL for a \badge object.
     *
     * @param badge $badge
     * @return moodle_url
     */
    private static function resolve_badge_object_image_url(badge $badge): moodle_url {
        return moodle_url::make_pluginfile_url($badge->get_context()->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
    }

    /**
     * Formatted badge description, for the detail modal body.
     *
     * @param badge $badge
     * @return string
     */
    private static function resolve_description(badge $badge): string {
        if (empty($badge->description)) {
            return '';
        }
        return format_text($badge->description, FORMAT_HTML, ['context' => $badge->get_context()]);
    }

    /**
     * Full "how to earn this badge" HTML, reusing core's own badge renderer
     * so the wording/aggregation logic for every criteria type (course
     * completion, activity completion, profile field, cohort, manual...)
     * stays authoritative instead of being reimplemented here.
     *
     * @param badge $badge
     * @return string
     */
    private static function resolve_criteria_html(badge $badge): string {
        global $PAGE;
        $renderer = $PAGE->get_renderer('core', 'badges');
        return $renderer->print_badge_criteria($badge);
    }

    /**
     * LinkedIn's documented "Add to Profile" certification URL
     * (https://www.linkedin.com/help/linkedin/answer/a566473) — a plain
     * query-string deep link into LinkedIn's own profile-edit form, no API
     * key or OAuth needed. Every value comes straight from real badge data
     * (nothing fabricated): name/organizationName from the badge and its
     * issuer, issueYear/issueMonth parsed from the real issue timestamp,
     * expirationYear/Month only added when the badge actually has an expiry,
     * and certUrl/certId pointing at this badge's own public verification
     * page (badges/badge.php) and unique hash.
     *
     * @param badge $badge
     * @param stdClass $record Row from badges_get_user_badges() (badge.* + bi.* + u.email).
     * @param string $verifyurl Public verification URL already built for this badge.
     * @return string
     */
    private static function build_linkedin_url(badge $badge, stdClass $record, string $verifyurl): string {
        $params = [
            'startTask' => 'CERTIFICATION_NAME',
            'name' => format_string($badge->name),
            'organizationName' => format_string($badge->issuername ?? ''),
            'issueYear' => (int) date('Y', $record->dateissued),
            'issueMonth' => (int) date('n', $record->dateissued),
            'certUrl' => $verifyurl,
            'certId' => $record->uniquehash,
        ];
        if (!empty($record->dateexpire)) {
            $params['expirationYear'] = (int) date('Y', $record->dateexpire);
            $params['expirationMonth'] = (int) date('n', $record->dateexpire);
        }
        return (new moodle_url('https://www.linkedin.com/profile/add', $params))->out(false);
    }
}
