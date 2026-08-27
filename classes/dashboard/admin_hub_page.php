<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use moodle_url;

/**
 * Backend data preparation for the Site Administration Hub
 * (theme/saec/pages/admin_hub.php) — a curated (not auto-generated from
 * core_admin's settings tree), categorized index of native Site
 * Administration destinations with an instant client-side search bar.
 *
 * This page used to also carry the Executive Command Center (KPIs, quick
 * actions, courses/users summaries) that now lives on /my/ instead — see
 * admin_dashboard.php, injected by layout/drawers.php exactly like
 * teacher_dashboard/student_dashboard already are for their roles. Keeping
 * "run the school" and "configure the site" on separate pages mirrors how
 * every other role already separates its dashboard from its management
 * pages in this theme.
 */
class admin_hub_page {

    /**
     * Unified context for templates/admin_hub.mustache. Returns null when
     * the logged-in user is not a site admin (mirrors every other
     * *_page::get_context()'s role guard).
     *
     * @param int $userid 0 for the current user.
     * @return array|null
     */
    public static function get_context(int $userid = 0): ?array {
        global $USER;
        $userid = $userid ?: (int) $USER->id;

        if (!is_siteadmin($userid)) {
            return null;
        }

        return [
            'searchplaceholder' => get_string('adminsearchplaceholder', 'theme_saec'),
            'categories' => self::get_site_admin_categories(),
        ];
    }

    /**
     * The Site Administration Hub: a curated (not auto-generated from
     * core_admin's settings tree) categorized list of native destinations,
     * exactly as specified for this Command Center — 6 categories, each
     * with a handful of sub-links. Every link's label backs the client-side
     * instant search in templates/admin_hub.mustache's inline JS.
     *
     * @return array[]
     */
    private static function get_site_admin_categories(): array {
        global $OUTPUT;

        $url = fn (string $path, array $params = []) => (new moodle_url($path, $params))->out(false);
        $icon = fn (string $pix) => $OUTPUT->pix_icon($pix, '', 'moodle', ['class' => 'uptex-category-card__icon-img']);

        return [
            [
                'key' => 'appearance',
                'title' => get_string('admincategoryappearance', 'theme_saec'),
                'icon' => $icon('i/theme'),
                'links' => [
                    // 'themesettings' isn't a registered admin section on this Moodle
                    // version (throws sectionerror) — the real native theme-selector
                    // destination is the dedicated /admin/themeselector.php page
                    // (registered as the 'themeselector' external page in
                    // admin/settings/appearance.php), not a settings.php section.
                    ['label' => get_string('adminlinkthemeselector', 'theme_saec'),
                        'url' => $url('/admin/themeselector.php')],
                    ['label' => get_string('adminlinksaecsettings', 'theme_saec'),
                        // theme_saec has no settings.php of its own (extends theme_boost
                        // without overriding it) — 'themesettingcustomsaec' doesn't exist
                        // as a registered admin section and throws sectionerror. The only
                        // real "active theme settings" page is theme_boost's own, still
                        // the one governing this site's active theme.
                        'url' => $url('/admin/settings.php', ['section' => 'themesettingboost'])],
                    ['label' => get_string('adminlinkadditionalhtml', 'theme_saec'),
                        'url' => $url('/admin/settings.php', ['section' => 'additionalhtml'])],
                    ['label' => get_string('adminlinklogos', 'theme_saec'),
                        'url' => $url('/admin/settings.php', ['section' => 'logos'])],
                    ['label' => get_string('adminlinknavigation', 'theme_saec'),
                        'url' => $url('/admin/settings.php', ['section' => 'navigation'])],
                ],
            ],
            [
                'key' => 'users',
                'title' => get_string('admincategoryusers', 'theme_saec'),
                'icon' => $icon('i/user'),
                'links' => [
                    ['label' => get_string('adminlinkuserlist', 'theme_saec'), 'url' => $url('/admin/user.php')],
                    ['label' => get_string('adminlinkroles', 'theme_saec'), 'url' => $url('/admin/roles/manage.php')],
                    ['label' => get_string('adminlinkenrolmethods', 'theme_saec'),
                        'url' => $url('/admin/settings.php', ['section' => 'manageenrols'])],
                ],
            ],
            [
                'key' => 'courses',
                'title' => get_string('admincategorycourses', 'theme_saec'),
                'icon' => $icon('i/course'),
                'links' => [
                    ['label' => get_string('adminlinkcoursemanagement', 'theme_saec'), 'url' => $url('/course/management.php')],
                    ['label' => get_string('adminlinkbackup', 'theme_saec'),
                        'url' => $url('/admin/settings.php', ['section' => 'backupgeneralsettings'])],
                ],
            ],
            [
                'key' => 'grades',
                'title' => get_string('admincategorygrades', 'theme_saec'),
                'icon' => $icon('i/grades'),
                'links' => [
                    ['label' => get_string('adminlinkgradesettings', 'theme_saec'),
                        'url' => $url('/admin/settings.php', ['section' => 'gradecategorysettings'])],
                    ['label' => get_string('adminlinkbadgesettings', 'theme_saec'),
                        'url' => $url('/admin/settings.php', ['section' => 'badgesettings'])],
                    ['label' => get_string('adminlinkcompetencies', 'theme_saec'), 'url' => $url('/admin/competencies/index.php')],
                ],
            ],
            [
                'key' => 'plugins',
                'title' => get_string('admincategoryplugins', 'theme_saec'),
                'icon' => $icon('i/settings'),
                'links' => [
                    ['label' => get_string('adminlinkpluginsoverview', 'theme_saec'), 'url' => $url('/admin/plugins.php')],
                    ['label' => get_string('adminlinkinstallplugins', 'theme_saec'), 'url' => $url('/admin/tool/installaddon/index.php')],
                    ['label' => get_string('adminlinkactivitymodules', 'theme_saec'), 'url' => $url('/admin/modules.php')],
                ],
            ],
            [
                'key' => 'server',
                'title' => get_string('admincategoryserver', 'theme_saec'),
                'icon' => $icon('i/reload'),
                'links' => [
                    ['label' => get_string('adminlinkenvironment', 'theme_saec'), 'url' => $url('/admin/environment.php')],
                    ['label' => get_string('adminlinkscheduledtasks', 'theme_saec'), 'url' => $url('/admin/tool/task/scheduledtasks.php')],
                    ['label' => get_string('adminlinksecurity', 'theme_saec'),
                        'url' => $url('/admin/settings.php', ['section' => 'sitepolicies'])],
                    ['label' => get_string('adminlinkdebugging', 'theme_saec'),
                        'url' => $url('/admin/settings.php', ['section' => 'debugging'])],
                    ['label' => get_string('adminlinklogs', 'theme_saec'), 'url' => $url('/report/log/index.php')],
                    ['label' => get_string('adminlinkpurgecaches', 'theme_saec'), 'url' => $url('/admin/purgecaches.php')],
                ],
            ],
        ];
    }
}
