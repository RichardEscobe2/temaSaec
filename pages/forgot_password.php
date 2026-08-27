<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Institutional password-recovery assistance page.
 *
 * Replaces core's native /login/forgot_password.php search form, which on
 * this install throws an uncaught moodle_exception('cannotmailconfirm')
 * whenever a real username/email is submitted — send_password_change_
 * confirmation_email() fails because no local SMTP/sendmail is configured
 * in this environment, and that exception is thrown deep inside
 * login/lib.php (core_login_process_password_reset()) before theme_saec's
 * own layout/login.php ever gets a chance to render anything, so no
 * template override could intercept it.
 *
 * Wired in via $CFG->forgottenpasswordurl (Site administration > Security
 * > Site security settings > "Alternate forgotten password URL"), Moodle's
 * own officially-supported redirect point for institutions that handle
 * password recovery outside the built-in email flow — checked at the very
 * top of login/forgot_password.php, before any of the buggy logic runs.
 * Zero core files touched; see db upgrade note in this theme's install
 * step for how that config value gets set.
 *
 * Public page (no require_login()) — must remain reachable by anyone who
 * just failed to log in, same as the page it replaces.
 *
 * @package   theme_saec
 * @copyright 2026 SAEC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

// Same courtesy core's own forgot_password.php extends: a logged-in user
// (not guest) has no reason to be here.
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/my/'));
}

$PAGE->set_url(new moodle_url('/theme/saec/pages/forgot_password.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('passwordforgotten'));
$PAGE->set_heading(get_string('passwordforgotten'));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_saec/forgot_password_card', [
    'loginurl' => (new moodle_url('/login/index.php'))->out(false),
]);
echo $OUTPUT->footer();
