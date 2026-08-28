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
 * Direct CSV/Excel export for the Admin "Reportes y Auditoría" page
 * (templates/admin_reports_page.mustache's "Exportar CSV"/"Exportar Excel"
 * buttons).
 *
 * Originally those buttons linked straight at core's own
 * /report/log/index.php?download=csv|excel — that native report gates its
 * entire table-setup-and-export flow behind an ADDITIONAL chooselog=1
 * postback param (see report/log/index.php's own "if (!empty($chooselog))"
 * branch), so a plain ?download=csv link silently produced nothing. Rather
 * than reverse-engineer that report's full param contract, this is a
 * small, standalone raw-output endpoint — same pattern as
 * ajax/purge_caches.php (require_login + sesskey + capability, no $PAGE/
 * layout, exits before any HTML would print) — that reuses the exact same
 * query \theme_saec\dashboard\admin_reports_page's own "Bitácora de
 * Auditoría Reciente" widget already runs, just uncapped to 500 rows
 * instead of 15.
 *
 * @package    theme_saec
 * @copyright  2026 SAEC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

// This export mirrors the Admin Reports dashboard 1:1 (same query, same
// data) — access is deliberately kept identical to that page's own guard
// (\theme_saec\dashboard\admin_reports_page::get_context()'s
// is_siteadmin() check), not the broader report/log:view capability a
// non-admin manager role could also hold, so nobody can reach this export
// from a link the dashboard itself wouldn't have shown them.
if (!is_siteadmin()) {
    throw new \required_capability_exception(
        context_system::instance(),
        'moodle/site:config',
        'nopermissions',
        ''
    );
}

require_sesskey();

$format = optional_param('format', 'csv', PARAM_ALPHA);

// Row cap — generous for a "recent audit trail" export without risking an
// unbounded download.
$export = \theme_saec\dashboard\admin_reports_page::get_export_rows(500);

$filename = 'saec_auditoria_' . date('Y-m-d');

// No $PAGE->set_url()/header()/footer() anywhere below — this endpoint
// only ever emits the download stream, then exits, exactly like
// ajax/purge_caches.php's own raw-response pattern.
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    // A minimal HTML table is what "application/vnd.ms-excel" actually
    // means in practice — both Excel and LibreOffice Calc open this
    // directly as a spreadsheet, no external library needed. The UTF-8
    // <meta charset> tag is what makes Excel render accented characters
    // correctly (it has no BOM-sniffing for this content type the way it
    // does for CSV).
    echo "<html><head><meta charset=\"utf-8\"></head><body><table border=\"1\">\n";
    echo "<tr>";
    foreach ($export['header'] as $heading) {
        echo '<th>' . s($heading) . '</th>';
    }
    echo "</tr>\n";
    foreach ($export['rows'] as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . s((string) $cell) . '</td>';
        }
        echo "</tr>\n";
    }
    echo "</table></body></html>";
} else {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    // UTF-8 BOM — without it, Microsoft Excel (unlike LibreOffice/Google
    // Sheets) guesses the wrong legacy codepage and mangles accented
    // characters (á, é, í, ó, ú, ñ) in every fullname/event-name column.
    echo "\xEF\xBB\xBF";

    $handle = fopen('php://output', 'w');
    fputcsv($handle, $export['header']);
    foreach ($export['rows'] as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);
}

exit;
