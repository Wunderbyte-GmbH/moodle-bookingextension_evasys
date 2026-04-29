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
 * Manually send EvaSys reports for all expired surveys.
 *
 * @package bookingextension_evasys
 * @copyright 2026 Wunderbyte GmbH
 * @author David Ala
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

use bookingextension_evasys\local\evasys_handler;

require_login();
require_sesskey();
require_capability('moodle/site:config', \context_system::instance());

$now = time();
$records = $DB->get_records_select(
    'bookingextension_evasys',
    'surveyid IS NOT NULL AND endtime IS NOT NULL AND endtime > 0 AND endtime < :now',
    ['now' => $now]
);

$handler = new evasys_handler();
$processed = 0;
$failed = 0;

foreach ($records as $record) {
    try {
        $response = $handler->send_report((int)$record->surveyid);
        if (!empty($response)) {
            $processed++;
        } else {
            $failed++;
        }
    } catch (Throwable $e) {
        $failed++;
    }
}

$returnurl = new moodle_url('/admin/settings.php', ['section' => 'bookingextension_evasys_settings']);
$message = get_string('sendreportforpastsurveys_result', 'bookingextension_evasys', (object)[
    'total' => count($records),
    'sent' => $processed,
    'failed' => $failed,
]);

$messagetype = $failed > 0 ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS;
redirect($returnurl, $message, null, $messagetype);
