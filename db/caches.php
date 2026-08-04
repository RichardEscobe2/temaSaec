<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

/**
 * MUC definitions for theme_saec.
 *
 * "instructor" backs instructor_resolver::resolve() — the first
 * editing-teacher lookup shown in both the course hero and the assignment
 * header. Teacher assignment for a course changes rarely (a re-enrolment,
 * not a per-request event), so a short TTL is enough to cut a
 * get_enrolled_users() query off nearly every student page load without
 * risking meaningfully stale data.
 */
$definitions = [
    'instructor' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 3600,
    ],
];
