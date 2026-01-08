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
 * Adhoc Task for EvaSys Create and Update Logic.
 *
 * @package bookingextension_evasys
 * @copyright 2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @author David Ala
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookingextension_evasys\task;

use bookingextension_evasys\local\evasys_handler;
use mod_booking\booking_option;
use mod_booking\option\fields\courseid;


defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Class to handle adhoc Task for communication with the SOAP Server.
 */
class evasys_send_to_api extends \core\task\adhoc_task {
    /**
     * Get task name.
     *
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('sendtoapi', 'bookingextension_evasys');
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
                // Check if all required keys exits in $taskdata.
                $requiredkeys = [
                    'teacherchanges',
                    'namechanges',
                    'relevantchanges',
                    'relevantkeyssurvey',
                    'relevantkeyscourse',
                    'recipients',
                    'data',
                    'courseid',
                ];
                foreach ($requiredkeys as $key) {
                    if (!property_exists($taskdata, $key)) {
                        mtrace($this->get_name() . "Excepted key ({$key}) not found in task data.");
                        return;
                    }
                }
                $data = $taskdata->data;
                $newoption = $taskdata->newoption;
                $relevantkeyssurvey = $taskdata->relevantkeyssurvey;
                $relevankeyscourse = $taskdata->relevantkeyscourse;
                $teacherchanges = $taskdata->teacherchanges;
                $namechanges = $taskdata->namechanges;
                $relevantchanges = $taskdata->relevantchanges;
                $changetasks = $taskdata->changetasks;
                // Check if teachers exist otherwise skip the task.
                if (empty($data->teachersforoption)) {
                    mtrace($this->get_name() . ': Skipping task - no teachers assigned.');
                    return;
                }
                $evasys = new evasys_handler();
                if (empty($data->evasys_courseidexternal) && !empty($data->evasys_form)) {
                    $course = $evasys->create_course($data, $newoption, $taskdata->courseid);
                    if (empty($course)) {
                          mtrace($this->get_name() . ': On course creation there was no connection to EvaSys');
                          return;
                    }
                    $survey = $evasys->create_survey($course, $data, $newoption);
                    if (empty($survey)) {
                        mtrace($this->get_name() . ': On survey creation there was no connection to EvaSys');
                        return;
                    }
                    $taskopen = new evasys_open_survey();
                    $taskclose = new evasys_close_survey();
                    $taskdata = [
                        'surveyid' => $survey->m_nSurveyId,
                        'optionid' => $newoption->id,
                     ];
                      $taskopen->set_custom_data($taskdata);
                      $taskclose->set_custom_data($taskdata);
                      $taskopen->set_next_run_time($data->evasys_starttime);
                      $taskclose->set_next_run_time($data->evasys_endtime);
                      \core\task\manager::queue_adhoc_task($taskopen);
                      \core\task\manager::queue_adhoc_task($taskclose);
                } else {
                    if (!empty($data->evasys_confirmdelete)) {
                            // Delete the Survey.
                            $evasys->delete_survey($data->evasys_surveyid);
                            // Afterwards the course.
                            $evasys->delete_course($data->evasys_courseidinternal, $data->evasys_booking_id);
                            booking_option::purge_cache_for_option($newoption->id);
                            return;
                    }
                    $updatesurvey = false;
                    $updatecourse = false;
                    // Checks if teachers or option name changed. If it changed we already know we need to update course and survey.
                    if (
                        !empty($teacherchanges)
                        || !empty($namechanges)
                    ) {
                        $updatesurvey = true;
                    }
                    // Checks if the survey and therefore the course needs to be updated.
                    if (!$updatesurvey) {
                        foreach ($relevantchanges as $key => $value) {
                            if (in_array($key, $relevantkeyssurvey, true)) {
                                $updatesurvey = true;
                            }
                        }
                        // Checks for the only key where just the course needs to be updated.
                        if (
                            !$updatesurvey
                            && isset($courserelevantchanges->$relevankeyscourse)
                        ) {
                            $updatecourse = true;
                        }
                    }
                    if ($updatesurvey) {
                        $surveyid = $data->evasys_surveyid;
                        $newsurvey = $evasys->update_survey($surveyid, $data, $newoption, $taskdata->courseid);
                        $taskopen = new evasys_open_survey();
                        $taskclose = new evasys_close_survey();
                        $taskdata = [
                        'surveyid' => $newsurvey->m_nSurveyId,
                        'optionid' => $newoption->id,
                        ];
                        $taskopen->set_custom_data($taskdata);
                        $taskclose->set_custom_data($taskdata);
                        $taskopen->set_next_run_time($data->evasys_starttime);
                        $taskclose->set_next_run_time($data->evasys_endtime);
                        \core\task\manager::reschedule_or_queue_adhoc_task($taskopen);
                        \core\task\manager::reschedule_or_queue_adhoc_task($taskclose);
                    }
                    if ($updatecourse) {
                        $evasys->update_course($data, $newoption, $data->evasys_booking_id, $taskdata->courseid);
                    }
                }
                if ($taskdata->changetasks && !$updatesurvey && !empty($data->evasys_courseidexternal)) {
                    $taskopen = new evasys_open_survey();
                    $taskclose = new evasys_close_survey();
                    $taskdata = [
                        'surveyid' => $data->evasys_surveyid,
                        'optionid' => $newoption->id,
                     ];
                      $taskopen->set_custom_data($taskdata);
                      $taskclose->set_custom_data($taskdata);
                      $taskopen->set_next_run_time($data->evasys_starttime);
                      $taskclose->set_next_run_time($data->evasys_endtime);
                      \core\task\manager::queue_adhoc_task($taskopen);
                      \core\task\manager::queue_adhoc_task($taskclose);
                }
                booking_option::purge_cache_for_option($newoption->id);
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
