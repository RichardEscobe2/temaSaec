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
 * Forces $CFG->forgottenpasswordurl to always resolve dynamically against
 * the current request's $CFG->wwwroot, instead of trusting whatever
 * absolute URL happens to be frozen in mdl_config.
 *
 * pages/forgot_password.php exists specifically because core's own
 * /login/forgot_password.php throws an uncaught exception on this install
 * (no local SMTP configured) — the "Alternate forgotten password URL" site
 * setting (Administration > Security > Site security settings) is
 * Moodle's own officially-supported redirect point for that, and an admin
 * pointed it at this page at some point by typing an absolute URL into
 * that settings field. That URL is a plain DB string
 * (mdl_config.forgottenpasswordurl): it never re-resolves itself when the
 * site's real host/port changes (dev -> staging -> production, or even a
 * docker port remap), so it can silently go stale and start pointing at a
 * host nobody can reach (confirmed: this install's own stored value was
 * still "http://localhost/moodle/...", not the current
 * "http://localhost:8080" wwwroot).
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
 *
 * Only touches the value when the site has one configured at all — an
 * admin explicitly clearing this setting (falling back to core's native
 * email flow) is a real choice this function must not override.
 */
function theme_saec_after_config() {
    global $CFG;

    if (empty($CFG->forgottenpasswordurl)) {
        return;
    }

    $CFG->forgottenpasswordurl = (new moodle_url('/theme/saec/pages/forgot_password.php'))->out(false);
}