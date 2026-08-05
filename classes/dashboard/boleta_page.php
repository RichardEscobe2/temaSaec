<?php
// PHP requires `namespace` to be the file's first statement, so the usual
// defined('MOODLE_INTERNAL') || die(); guard cannot appear here — this file
// is only ever reached through the autoloader, never requested directly.

namespace theme_saec\dashboard;

use context_course;
use grade_grade;
use grade_item;

/**
 * Backend data preparation for the Student "Boleta Digital" summary
 * (/grade/report/user/index.php). Unlike grader_hub_page/analytics_page,
 * this does NOT replace gradereport_user\report\user's native table — that
 * table's rowspan-nested categories, per-item hidden/locked/extra-credit
 * rules and configurable column set (weight/range/percentage/feedback/... —
 * each independently toggleable in course settings, see grade/report/user/lib.php)
 * are exactly the kind of real edge-case logic that's unsafe to
 * reimplement from scratch. This only ADDS three real-data summary cards
 * above it (same non-destructive injection pattern as the Assignment view
 * page's header+workspace, layout/drawers.php's $isassignviewpage branch) —
 * the native table stays, gets CSS-only cosmetic polish instead
 * (scss/saec/_boleta.scss).
 *
 * "Overall grade" is the course total grade_item's real final grade as a
 * percentage — the same number the native table's own course-total row
 * already shows, not an invented GPA concept this data model doesn't have.
 */
class boleta_page {

    /**
     * Unified context for templates/components/boleta_summary.mustache.
     * Returns null when there's no real course total to show (no course
     * grade item, or the student has no grades at all yet).
     *
     * @param int $courseid
     * @param int $userid The user whose report is being displayed — may be
     *                     the viewer themselves or, for a teacher/parent,
     *                     someone else's.
     * @return array|null
     */
    public static function get_context(int $courseid, int $userid): ?array {
        global $OUTPUT;

        $courseitem = grade_item::fetch_course_item($courseid);
        if (!$courseitem) {
            return null;
        }

        $coursegrade = new grade_grade(['itemid' => $courseitem->id, 'userid' => $userid]);
        $coursegrade->grade_item =& $courseitem;
        $finalgrade = $coursegrade->finalgrade;

        $overallpercent = null;
        if (!is_null($finalgrade) && $courseitem->grademax > $courseitem->grademin) {
            $overallpercent = ($finalgrade - $courseitem->grademin) / ($courseitem->grademax - $courseitem->grademin) * 100;
        }

        $context = context_course::instance($courseid);
        $canviewhidden = has_capability('moodle/grade:viewhidden', $context, $userid);

        [$completed, $pending] = self::count_items($courseid, $userid, $canviewhidden);
        if ($overallpercent === null && $completed === 0 && $pending === 0) {
            // Nothing real to summarize (e.g. an empty gradebook) — an
            // empty-looking metrics row would be worse than none at all.
            return null;
        }

        $ispassing = $coursegrade->is_passed($courseitem);
        if ($completed === 0) {
            $statuskey = 'pending';
        } else if ($ispassing === false) {
            $statuskey = 'fail';
        } else if ($ispassing === true) {
            $statuskey = 'pass';
        } else {
            // No pass/fail boundary configured on the course total — the
            // only honest status left is "still has ungraded work" or not.
            $statuskey = ($pending > 0) ? 'pending' : 'pass';
        }
        $statusvariant = ['pass' => 'primary', 'fail' => 'danger', 'pending' => 'neutral'][$statuskey];

        $icon = fn (string $pix) => $OUTPUT->pix_icon($pix, '', 'moodle', ['class' => 'saec-kpi-card__icon-img']);

        return [
            'cards' => [
                [
                    'key' => 'overallgrade',
                    'icon' => $icon('i/grades'),
                    'iconvariant' => 'primary',
                    'label' => get_string('boletaoverallgradelabel', 'theme_saec'),
                    'value' => ($overallpercent !== null) ? format_float($overallpercent, 1) : '—',
                    'hasvaluesuffix' => ($overallpercent !== null),
                    'valuesuffix' => '%',
                    'hasfootnote' => false,
                    'footnote' => null,
                ],
                [
                    'key' => 'completeditems',
                    'icon' => $icon('i/checkpermissions'),
                    'iconvariant' => 'accent',
                    'label' => get_string('boletacompletedlabel', 'theme_saec'),
                    'value' => (string) $completed,
                    'hasvaluesuffix' => true,
                    'valuesuffix' => '/ ' . ($completed + $pending),
                    'hasfootnote' => false,
                    'footnote' => null,
                ],
                [
                    'key' => 'status',
                    'icon' => $icon('i/grading'),
                    'iconvariant' => $statusvariant,
                    'label' => get_string('boletastatuslabel', 'theme_saec'),
                    'value' => get_string('boletastatus' . $statuskey, 'theme_saec'),
                    'hasvaluesuffix' => false,
                    'valuesuffix' => null,
                    'hasfootnote' => false,
                    'footnote' => null,
                ],
            ],
        ];
    }

    /**
     * Real completed-vs-pending item counts: every gradable item in the
     * course (excluding course/category aggregation rows, which aren't
     * "items" a student completes), split by whether this user has a
     * recorded final grade. Hidden items the viewer can't see are skipped
     * entirely rather than counted as pending, matching the native
     * report's own hide-hidden-items default.
     *
     * @param int $courseid
     * @param int $userid
     * @param bool $canviewhidden
     * @return array{0: int, 1: int} [completed, pending]
     */
    private static function count_items(int $courseid, int $userid, bool $canviewhidden): array {
        $items = grade_item::fetch_all(['courseid' => $courseid]);
        if (!$items) {
            return [0, 0];
        }

        $completed = 0;
        $pending = 0;
        foreach ($items as $item) {
            if (in_array($item->itemtype, ['course', 'category'], true)) {
                continue;
            }
            if ($item->is_hidden() && !$canviewhidden) {
                continue;
            }

            $grade = new grade_grade(['itemid' => $item->id, 'userid' => $userid]);
            $grade->grade_item =& $item;

            if ($grade->is_hidden() && !$canviewhidden) {
                continue;
            }

            if (!is_null($grade->finalgrade)) {
                $completed++;
            } else {
                $pending++;
            }
        }

        return [$completed, $pending];
    }
}
