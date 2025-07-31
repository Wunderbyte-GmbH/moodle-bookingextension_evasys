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
 * Adhoc Task for EvaSys to close survey for data collection.
 *
 * @package bookingextension_evasys
 * @copyright 2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @author David Ala
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookingextension_evasys\task;

use bookingextension_evasys\local\evasys_handler;


defined('MOODLE_INTERNAL') || die();

global $CFG;

use Exception;

require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Class to handle adhoc Task to send a mail by a rule at a certain time.
 */
class evasys_close_survey extends \core\task\adhoc_task {
    /**
     * Get task name.
     *
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('closesurvey', 'bookingextension_evasys');
    }

    /**
     * Execution function.
     *
     * {@inheritdoc}
     * @throws \coding_exception
     * @throws \dml_exception
     * @see \core\task\task_base::execute()
     */
    public function execute() {
        $taskdata = $this->get_custom_data();

        if ($taskdata != null) {
              mtrace($this->get_name() . ' executed.');

            try {
                // Check if all required keys exist in $taskdata.
                $requiredkeys = [
                    'surveyid',
                ];
                foreach ($requiredkeys as $key) {
                    if (!property_exists($taskdata, $key)) {
                        throw new Exception("Expected key ({$key}) not found in task data.");
                    }
                }
                $handler = new evasys_handler();
                $hasopened = $handler->close_survey_final($taskdata->surveyid);
                if (!$hasopened) {
                    throw new Exception('Survey could not be closed on Evasys');
                }
                mtrace($this->get_name() . ": Task done successfully.");
            } catch (\Throwable $e) {
                mtrace($this->get_name() . ": ERROR - " . $e->getMessage());
                throw $e;
            }
        } else {
             mtrace($this->get_name() . ': ERROR - missing taskdata.');
             throw new \coding_exception(
                 $this->get_name() . ': ERROR - missing taskdata.'
             );
        }
    }
}
