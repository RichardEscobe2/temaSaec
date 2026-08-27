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