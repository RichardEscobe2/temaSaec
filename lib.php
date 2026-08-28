<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Global Bootstrap/Boost variable overrides — registered via
 * $THEME->prescsscallback (config.php). lib/outputlib.php's
 * theme_config::get_pre_scss_code() calls this and PREPENDS its return
 * value to the compiled stylesheet, before $THEME->scss's own content
 * (theme_boost_get_main_scss_content(), the Boost preset that @imports
 * Bootstrap's _variables.scss). This is the only point in the whole
 * compile where a plain `$primary: ...;` assignment here can win against
 * Bootstrap's own `$primary: ... !default;` — see scss/pre.scss for the
 * full explanation and the variable map itself.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_saec_get_pre_scss($theme) {
    global $CFG;
    $prefile = $CFG->dirroot . '/theme/saec/scss/pre.scss';
    if (file_exists($prefile)) {
        return file_get_contents($prefile);
    }
    return '';
}

function theme_saec_get_extra_scss($theme) {
    global $CFG;
    $postfile = $CFG->dirroot . '/theme/saec/scss/post.scss';
    if (file_exists($postfile)) {
        return file_get_contents($postfile);
    }
    return '';
}

/**
 * Unconditionally forces $CFG->forgottenpasswordurl to this theme's own
 * institutional support card, resolved dynamically against the current
 * request's $CFG->wwwroot — every request, regardless of what (if
 * anything) is stored in mdl_config.forgottenpasswordurl.
 *
 * pages/forgot_password.php exists specifically because core's own
 * /login/forgot_password.php search form throws an uncaught exception on
 * this install (no local SMTP configured) the moment a real username/email
 * is submitted — send_password_change_confirmation_email() fails deep
 * inside login/lib.php, before theme_saec's own layout ever gets a chance
 * to render anything. There is no safe "let core handle it" fallback here:
 * core's native flow is not just cosmetically wrong on this install, it is
 * broken. So unlike a normal site override (which should respect an admin
 * clearing the setting to opt back into core behaviour), this one is
 * deliberately NOT conditional — this theme has no working native
 * password-reset path to fall back to, so nothing may ever leave
 * $CFG->forgottenpasswordurl empty or pointing anywhere else, including a
 * stale absolute URL typed into the site setting by hand (confirmed: this
 * install's own stored value was still "http://localhost/moodle/...", not
 * the current "http://localhost:8080" wwwroot) or a future admin blanking
 * the field via Administration > Security > Site security settings.
 *
 * get_plugins_with_function('after_config', 'lib.php') (lib/setup.php,
 * called unconditionally near the end of every bootstrap, before any page
 * controller runs) is Moodle's own supported hook for exactly this shape
 * of problem: adjust a $CFG value early, on every request, without a
 * permanent write to mdl_config and without touching core. By the time
 * this runs, $CFG->wwwroot is already correct for the current request
 * (config.php resolves it from $_SERVER before including setup.php) and
 * lib/weblib.php (moodle_url) is already loaded (required a few hundred
 * lines above the after_config dispatch in setup.php), so both are safe
 * to use here.
 */
function theme_saec_after_config() {
    global $CFG;

    $CFG->forgottenpasswordurl = (new moodle_url('/theme/saec/pages/forgot_password.php'))->out(false);
}