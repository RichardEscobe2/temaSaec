<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec;

use context_user;
use core_course\hook\after_form_definition;
use core_course\hook\after_form_validation;

/**
 * Listeners for core_course's Hook API (course/classes/hook/*.php),
 * registered in db/hooks.php. This is the zero-core-hack way to make the
 * course overview image mandatory: Moodle 4.4's course_edit_form no longer
 * exposes a "form_alter" callback (that API doesn't exist in this version),
 * but it dispatches these two hooks itself in definition() and validation()
 * — the officially supported extension point.
 */
class hook_callbacks {
    /** @var string[] Extensions this theme accepts for a course image, independent of the site-wide "web_image" group (which also allows gif). */
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Re-adds the course "overviewfiles" filemanager with this theme's
     * tighter constraints (image-only extensions, exactly 1 file) in place
     * of the site-wide default (course_overviewfiles_options(), which
     * follows $CFG->courseoverviewfilesext/courseoverviewfileslimit and may
     * be broader). Re-added at the same position via insertElementBefore()
     * — a plain removeElement()+addElement() would silently relocate it to
     * the bottom of the whole form, after every other section.
     *
     * A client-side 'required' rule is also attached, matching the same
     * pattern core itself uses for mod/workshop's attachment filemanager —
     * it will not reliably block an empty submission on its own (a
     * filemanager's hidden input always holds a truthy draft item id, empty
     * or not), so the real gate is validate_course_image()'s server-side
     * check below. It is kept anyway for the "required" asterisk and the
     * cases it does catch, not relied upon as the sole guard.
     *
     * @param after_form_definition $hook
     */
    public static function tighten_course_image_element(after_form_definition $hook): void {
        global $CFG;

        $mform = $hook->mform;
        if (!$mform->elementExists('overviewfiles_filemanager')) {
            // Site has course overview files disabled entirely
            // ($CFG->courseoverviewfileslimit = 0) — nothing to tighten.
            return;
        }

        $mform->removeElement('overviewfiles_filemanager');

        $options = [
            'maxfiles' => 1,
            'maxbytes' => $CFG->maxbytes,
            'subdirs' => 0,
            'accepted_types' => array_map(fn($ext) => '.' . $ext, self::ALLOWED_EXTENSIONS),
            'context' => $hook->formwrapper->get_context(),
        ];

        $element = $mform->createElement(
            'filemanager',
            'overviewfiles_filemanager',
            get_string('courseoverviewfiles'),
            null,
            $options
        );

        if ($mform->elementExists('courseformathdr')) {
            $mform->insertElementBefore($element, 'courseformathdr');
        } else {
            $mform->addElement($element);
        }

        $mform->addHelpButton('overviewfiles_filemanager', 'courseoverviewfiles');
        $mform->addRule('overviewfiles_filemanager', get_string('required'), 'required', null, 'client');
    }

    /**
     * Server-side gate: rejects the submission unless the draft area behind
     * "overviewfiles_filemanager" actually holds at least one file that is
     * both a valid image and has an allowed extension. This is what
     * actually blocks course creation/edit without an image — see the
     * docblock above for why the client-side rule alone cannot be trusted.
     *
     * @param after_form_validation $hook
     */
    public static function validate_course_image(after_form_validation $hook): void {
        $data = $hook->get_data();
        if (!array_key_exists('overviewfiles_filemanager', $data)) {
            // Field wasn't part of this form submission (disabled site-wide).
            return;
        }

        $draftitemid = (int) $data['overviewfiles_filemanager'];
        if ($draftitemid <= 0 || !self::draft_area_has_valid_image($draftitemid)) {
            $hook->add_errors([
                'overviewfiles_filemanager' => get_string('courseimagerequired', 'theme_saec'),
            ]);
        }
    }

    /**
     * @param int $draftitemid
     * @return bool
     */
    private static function draft_area_has_valid_image(int $draftitemid): bool {
        global $USER;

        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);

        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if (in_array($ext, self::ALLOWED_EXTENSIONS, true) && $file->is_valid_image()) {
                return true;
            }
        }
        return false;
    }
}
