<?php
defined('MOODLE_INTERNAL') || die();

function theme_saec_get_extra_scss($theme) {
    global $CFG;
    $postfile = $CFG->dirroot . '/theme/saec/scss/post.scss';
    if (file_exists($postfile)) {
        return file_get_contents($postfile);
    }
    return '';
}