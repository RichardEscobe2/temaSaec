<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Cache-purge action for the Admin Command Center's "Purgar Caché" quick
 * action (templates/admin_hub.mustache). POST-only, sesskey- and
 * capability-guarded — calls core's own purge_all_caches(), the exact same
 * function /admin/purgecaches.php uses, so no theme-specific cache logic
 * exists here beyond wiring the request through.
 *
 * @package    theme_saec
 * @copyright  2026 SAEC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die;
}

require_sesskey();
require_capability('moodle/site:config', context_system::instance());

purge_all_caches();

header('Content-Type: application/json');
echo json_encode(['purged' => true]);
