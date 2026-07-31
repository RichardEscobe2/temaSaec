<?php
defined('MOODLE_INTERNAL') || die();

/**
 * UPTex/SAEC login page layout.
 *
 * Mirrors theme_boost/layout/login.php's context exactly (sitename, output,
 * bodyattributes is genuinely all it computes — the real form data:
 * username/password/logintoken/error/etc. is injected later via
 * output.main_content, produced by core/loginform.mustache, which theme_saec
 * overrides — see templates/core/loginform.mustache), plus the extra fields
 * our own theme_saec/login page-shell template needs for the hero panel.
 */

global $SITE, $OUTPUT, $CFG;

$bodyattributes = $OUTPUT->body_attributes();

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), 'escape' => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'logourl' => $OUTPUT->image_url('logo1', 'theme_saec')->out(false),
    // Curated Unsplash tech/laptop mockup for the hero panel — verified
    // reachable (HTTP 200) before wiring it in.
    'heroimage' => 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?auto=format&fit=crop&w=1000&q=80',
    'currentyear' => date('Y'),
];

echo $OUTPUT->render_from_template('theme_saec/login', $templatecontext);
