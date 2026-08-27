<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Site Administration Hub.
 *
 * A standalone theme_saec page, not a core/plugin override: a curated,
 * categorized, instantly searchable index of native Site Administration
 * destinations — /admin/search.php is the native equivalent, a flat,
 * unstyled list of every settings page. The Executive Command Center
 * (KPIs, quick actions, courses/users summaries) that used to live here too
 * now lives on /my/ instead (theme_saec/admin_dashboard, injected by
 * layout/drawers.php) — this page focuses on exactly one job: settings.
 * Same pattern as pages/attendance_hub.php: header/sidebar/footer still go
 * through the theme's own layout/drawers.php via the standard 'standard'
 * pagelayout, so navigation stays identical to every other page.
 *
 * @package   theme_saec
 * @copyright 2026 SAEC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use theme_saec\dashboard\admin_hub_page;

require_login(null, false);

if (!is_siteadmin()) {
    redirect(new moodle_url('/my/'));
}

$PAGE->set_url(new moodle_url('/theme/saec/pages/admin_hub.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('adminsectionsiteadmin', 'theme_saec'));
$PAGE->set_heading(get_string('adminsectionsiteadmin', 'theme_saec'));

$context = admin_hub_page::get_context();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_saec/admin_hub', $context ?? []);
echo $OUTPUT->footer();
