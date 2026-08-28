<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\output;

use theme_saec\dashboard\student_dashboard;
use theme_saec\dashboard\teacher_dashboard;

/**
 * theme_saec's core_renderer override. Loaded automatically by
 * $THEME->rendererfactory = 'theme_overridden_renderer_factory' (config.php)
 * — no explicit registration needed beyond this class existing at the
 * conventional theme_saec\output\core_renderer path.
 *
 * Root cause: /my/index.php always calls
 * $OUTPUT->custom_block_region('content') between header()/footer(),
 * regardless of theme (see core's own my/index.php — out of scope to
 * modify per this theme's project rules). That region carries the site's
 * default block_timeline + block_calendar_month instances for every user
 * (one pair per my-index subpage, confirmed in mdl_block_instances). For
 * any role theme_saec already gives a full custom PHP-rendered dashboard
 * (Student/Teacher/Admin — see layout/drawers.php's
 * showstudentdashboard/showteacherdashboard/showadmindashboard), those
 * native blocks still render underneath it, and block_timeline's own AMD
 * module (blocks/timeline/amd/src/view_dates.js →
 * calendar_events_repository.js) auto-initialises and independently calls
 * the core_calendar_get_action_events_by_timesort webservice — entirely
 * core code theme_saec has never called into (no amd/ directory exists in
 * this theme). Its "Must provide a timesort to and/or from value"
 * invalidparameter failure is a known Moodle core edge case in that
 * module's pagination/date-cursor handling, unrelated to theme_saec's own
 * (server-rendered, no-AJAX) "Próximas Entregas"/deadlines panels, which
 * already show the same information from real PHP data.
 *
 * Rather than patch block_timeline itself (core file, out of scope, and
 * the wrong fix regardless — the block is entirely redundant once a
 * custom dashboard already covers the same content), this override
 * suppresses ONLY the 'content' region on the my-index page, and ONLY for
 * the exact same users layout/drawers.php already routes to a custom
 * dashboard — the same is_student()/is_teacher()/is_siteadmin() checks,
 * so this can never disagree with which users see which experience. Every
 * other region (the Blocks drawer's side-pre, in particular) and every
 * other page are untouched: this does not call
 * $PAGE->blocks->show_only_fake_blocks(), which would blanket-suppress
 * every region for the whole request, not just this one.
 */
class core_renderer extends \theme_boost\output\core_renderer {

    public function custom_block_region($regionname) {
        if ($regionname === 'content'
                && $this->page->pagetype === 'my-index'
                && (student_dashboard::is_student() || teacher_dashboard::is_teacher() || is_siteadmin())) {
            return '';
        }

        return parent::custom_block_region($regionname);
    }
}
