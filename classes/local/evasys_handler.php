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
 * Evasys Handler Class.
 *
 * @package bookingextension_evasys
 * @author David Ala
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookingextension_evasys\local;

use bookingextension_evasys\event\evasys_surveycreated;
use cache;
use context_course;
use bookingextension_evasys\local\evasys_helper_service;
use context_coursecat;
use context_module;
use core_course_category;
use mod_booking\singleton_service;
use stdClass;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . "/user/profile/lib.php");


/**
 * Class for handling logic of Evasys.
 */
class evasys_handler {
    /**
     * Saves option form.
     *
     * @param object $formdata
     * @param object $option
     *
     * @return void
     *
     */
    public function save_form(object &$formdata, object &$option) {
        global $DB;
        $helper = new evasys_helper_service();
        $insertdata = $helper->map_form_to_record($formdata, $option);
        if (empty($formdata->evasys_booking_id)) {
            $returnid = $DB->insert_record('bookingextension_evasys', $insertdata, true, false);
            // Returning ID so we can update record later for internal and external courseid.
            $formdata->evasys_booking_id = $returnid;
        } else {
            $DB->update_record('bookingextension_evasys', $insertdata);
        }
    }

    /**
     * Load for optionformfield.
     *
     * @param object $data
     *
     * @return void
     *
     */
    public function load_form(object &$data) {
        $helper = new evasys_helper_service();
        $settings = singleton_service::get_instance_of_booking_option_settings($data->id);
        if ((empty($settings->subpluginssettings['evasys']->id))) {
            return;
        }
        $helper->map_record_to_form($data, $settings->subpluginssettings['evasys']);
    }

    /**
     * Fetches periods and create array for Settings.
     *
     * @return array
     *
     */
    public function get_periods_for_settings() {
        $soap = new evasys_soap_service();
        $helper = new evasys_helper_service();
        $periods = $soap->fetch_periods();
        if (!isset($periods)) {
            return [];
        }
        $list = $periods->Periods;
        $periodoptions = $helper->transform_return_to_array($list, 'm_nPeriodId', 'm_sTitel');
        $encodedperiods = [];
        foreach ($periodoptions as $id => $label) {
            $encodedkey = $id . '-' . base64_encode($label);
            $encodedperiods[$encodedkey] = $label;
        }
        return $encodedperiods;
    }

    /**
     * Feteches periods for the query.
     *
     * @param string $query
     *
     * @return array
     *
     */
    public function get_periods_for_query(string $query) {
        $soap = new evasys_soap_service();
        $helper = new evasys_helper_service();
        $periods = $soap->fetch_periods();
        $listforarray = $periods->Periods;
        $periodoptions = $helper->transform_return_to_array($listforarray, 'm_nPeriodId', 'm_sTitel');
        $periodoptions = array_reverse($periodoptions, true);
        foreach ($periodoptions as $key => $value) {
            if (stripos($value, $query) !== false) {
                $list[$key] = $value;
            }
        }
        $formattedlist = [];
        foreach ($list as $id => $name) {
            $formattedlist[] = [
                'id' => $id . '-' . base64_encode($name),
                'name' => $name,
            ];
        }
        return [
                'warnings' => count($formattedlist) > 100 ? get_string('toomanyuserstoshow', 'core', '> 100') : '',
                'list' => count($formattedlist) > 100 ? [] : $formattedlist,
        ];
    }

    /**
     * Fetches all the forms for the query.
     *
     * @param string $query
     *
     * @return array
     *
     */
    public function get_forms_for_query(string $query) {
        $cache = $this->cached_forms();
        $forms = $cache[0];
        foreach ($forms as $key => $value) {
            if (
                empty($query)
                || stripos($value, $query) !== false
            ) {
                $list[$key] = $value;
            }
        }
        $formattedlist = [];
        foreach ($list as $id => $name) {
            $formattedlist[] = [
                'id' => $id . '-' . base64_encode($name),
                'name' => $name,
            ];
        }
        return [
                'warnings' => count($formattedlist) > 100 ? get_string('toomanyuserstoshow', 'core', '> 100') : '',
                'list' => count($formattedlist) > 100 ? [] : $formattedlist,
        ];
    }

    /**
     * Feteches forms and creates array for Settings.
     *
     * @return array
     *
     */
    public function get_allforms() {
        $soap = new evasys_soap_service();
        $helper = new evasys_helper_service();
        $subunitconfig = get_config('bookingextension_evasys', 'evasyssubunits');
        $subunitid = reset(explode('-', $subunitconfig));
        $args = $helper->set_args_fetch_forms($subunitid);
        $forms = $soap->fetch_forms($args);
        if (!isset($forms)) {
            return [];
        }
        $list = $forms->SimpleForms;
        $formoptions = [];
        foreach ($list as $element) {
            $formoptions[$element->ID] = $element->Name;
        }
        return $formoptions;
    }

    /**
     * Fetches all users with manager role from DB.
     *
     * @return array
     *
     */
    public function get_recipients() {
        global $DB;
        $roleid = get_config('bookingextension_evasys', 'rolereportrecipients');
        if (empty($roleid)) {
            return [];
        }
        $sql = "SELECT DISTINCT u.*
          FROM {role_assignments} ra
          JOIN {user} u ON u.id = ra.userid
         WHERE ra.roleid = :roleid";

        $params = ['roleid' => $roleid];
        $users = $DB->get_records_sql($sql, $params);
        foreach ($users as $user) {
            $useroptions[$user->id] = "$user->firstname $user->lastname (ID: $user->id) | $user->email";
        }
        return $useroptions;
    }

    /**
     * Fetches subunits and creates array for Settings.
     *
     * @return array
     *
     */
    public function get_subunits() {
        $soap = new evasys_soap_service();
        $helper = new evasys_helper_service();
        $subunits = $soap->fetch_subunits();
        if (!isset($subunits)) {
            return [];
        }
        $list = $subunits->Units;
        $subunitoptions = $helper->transform_return_to_array($list, 'm_nId', 'm_sName');
        $encodedsubunitoptions = [];
        foreach ($subunitoptions as $id => $label) {
            $encodedkey = $id . '-' . base64_encode($label);
            $encodedsubunitoptions[$encodedkey] = $label;
        }
        return $encodedsubunitoptions;
    }

    /**
     * Saves user in Evasys.
     *
     * @param object $user
     * @return void
     *
     */
    public function save_user(object $user) {
        global $CFG;
        $helper = new evasys_helper_service();
        $userdata = $helper->set_args_insert_user(
            $user->id,
            $user->firstname,
            $user->lastname,
            $user->adress ?? "",
            $user->email,
            (int) $user->phone1
        );
        $soap = new evasys_soap_service();
        $response = $soap->insert_user($userdata);
        if (isset($response)) {
            $value = [$response->m_sExternalId, $response->m_nId];
            $insert = implode(',', $value);
            $fieldshortname = get_config('bookingextension_evasys', 'evasyscategoryfielduser');
            profile_save_custom_fields($user->id, [$fieldshortname => $insert]);
        }
    }

    /**
     * Saves survey in Evasys and Surveyid to DB.
     *
     * @param array $args
     * @param int $id
     *
     * @return object
     *
     */
    public function save_survey(array $args, int $id) {
        global $DB;
        $soap = new evasys_soap_service();
        $response = $soap->insert_survey($args);
        if (isset($response)) {
            $data = [
                'id' => $id,
                'surveyid' => $response->m_nSurveyId,
            ];
            $DB->update_record('bookingextension_evasys', $data);
        }
        return $response;
    }

    /**
     * Deletes Survey in Evasys and DB.
     *
     * @param int $surveyid
     *
     * @return boolean
     *
     */
    public function delete_survey(int $surveyid) {
        $helper = new evasys_helper_service();
        $args = $helper->set_args_delete_survey($surveyid);
        $soap = new evasys_soap_service();
        $response = $soap->delete_survey($args);
        return $response;
    }

    /**
     * Opens Survey for Data collection.
     *
     * @param int $surveyid
     *
     * @return boolean
     *
     */
    public function open_survey(int $surveyid) {
        $helper = new evasys_helper_service();
        $args = $helper->set_args_open_survey($surveyid);
        $soap = new evasys_soap_service();
        $response = $soap->open_survey($args);
        return $response;
    }

    /**
     * Close Survey for Data collection and send report to recipients.
     *
     * @param int $surveyid
     *
     * @return boolean
     *
     */
    public function close_survey_final(int $surveyid) {
        $helper = new evasys_helper_service();
        $args = $helper->set_args_close_survey_final($surveyid);
        $soap = new evasys_soap_service();
        $response = $soap->close_survey($args);
        return $response;
    }

    /**
     * Close Survey temporary for Data collection.
     *
     * @param int $surveyid
     *
     * @return boolean
     *
     */
    public function close_survey_temporary(int $surveyid) {
        $helper = new evasys_helper_service();
        $args = $helper->set_args_close_survey_temporary($surveyid);
        $soap = new evasys_soap_service();
        $response = $soap->close_survey($args);
        return $response;
    }

    /**
     * Update Logic for the Survey in Evasys.
     *
     * @param int $surveyid
     * @param object $data
     * @param object $option
     * @param int $moodlecourseid
     *
     * @return object | null
     *
     */
    public function update_survey(int $surveyid, object $data, object $option, int $moodlecourseid) {
        global $DB;
        $soap = new evasys_soap_service();
        $helper = new evasys_helper_service();
        $coursedata = self::aggregate_data_for_course_save($data, $option, $moodlecourseid, $data->evasys_courseidinternal);
        $course = $soap->update_course($coursedata);
        if (empty($course)) {
            return;
        }
        $argsdelete = $helper->set_args_delete_survey($surveyid);
        $isdeleted = $soap->delete_survey($argsdelete);
        if (!$isdeleted) {
            return;
        }
        $argsnewsurvey = $helper->set_args_insert_survey(
            $course->m_nUserId,
            $course->m_nCourseId,
            (int) $data->evasys_form,
            $course->m_nPeriodId
        );
        $survey = $this->save_survey($argsnewsurvey, $data->evasys_booking_id);
        if (empty($survey)) {
            return $survey;
        }
        $this->close_survey_temporary($survey->m_nSurveyId);
        $qrcode = $this->get_qrcode($data->evasys_booking_id, $survey->m_nSurveyId);
        $surveyurl = $this->get_surveyurl($data->evasys_booking_id, $survey->m_nSurveyId);
        $settings = singleton_service::get_instance_of_booking_option_settings($option->id);
        $context = context_module::instance($settings->cmid);
        $event = evasys_surveycreated::create([
            'objectid' => $option->id,
            'context' => $context,
        ]);
        $event->trigger();
        return $survey;
    }

    /**
     * Creates the survey, surveyurl and QR-code for EvaSys.
     *
     * @param object $courseresponse
     * @param object $data
     * @param object $option
     *
     * @return object|null
     *
     */
    public function create_survey(object $courseresponse, object $data, object $option) {
        $helper = new evasys_helper_service();
        $argssurvey = $helper->set_args_insert_survey(
            $courseresponse->m_nUserId,
            $courseresponse->m_nCourseId,
            (int)$data->evasys_form,
            $courseresponse->m_nPeriodId,
        );

        $id = $data->evasys_booking_id;
        $survey = $this->save_survey($argssurvey, $id);
        if (empty($survey)) {
            return $survey;
        }
        $this->close_survey_temporary($survey->m_nSurveyId);
        $qrcode = $this->get_qrcode($id, $survey->m_nSurveyId);
        $surveyurl = $this->get_surveyurl($id, $survey->m_nSurveyId);
        $settings = singleton_service::get_instance_of_booking_option_settings($option->id);
        $context = context_module::instance($settings->cmid);
        $event = evasys_surveycreated::create([
            'objectid' => $option->id,
            'context' => $context,
        ]);
        $event->trigger();
        return $survey;
    }

    /**
     * Aggregates Data for Course.
     *
     * @param object $data
     * @param object $option
     * @param int $moodlecourseid
     * @param int $evasyscourseid
     *
     * @return array
     *
     */
    public function aggregate_data_for_course_save($data, $option, $moodlecourseid, $evasyscourseid = null) {
        $userfieldshortname = get_config('bookingextension_evasys', 'evasyscategoryfielduser');
        $helper = new evasys_helper_service();
        // Gets all the Teachers and looks if they already exist in Evasys.
        foreach ($data->teachersforoption as $teacherid) {
            $teacher = singleton_service::get_instance_of_user($teacherid, true);
            $teachers[$teacherid] = $teacher;
            if (empty($teacher->profile[$userfieldshortname])) {
                $this->save_user($teacher);
                singleton_service::destroy_user($teacherid);
                $teacher = singleton_service::get_instance_of_user($teacherid, true);
                $teachers[$teacherid] = $teacher;
            } else {
                continue;
            }
        }
        // Gets all the other report recipients and looks if they already exist in Evasys.
        foreach ($data->evasys_other_report_recipients as $recipientid) {
            $recipient = singleton_service::get_instance_of_user($recipientid, true);
            $recipients[$recipientid] = $recipient;
            if (empty($recipient->profile[$userfieldshortname])) {
                $this->save_user($recipient);
                singleton_service::destroy_user($recipientid);
                $recipient = singleton_service::get_instance_of_user($recipientid, true);
                $recipients[$recipientid] = $recipient;
            } else {
                continue;
            }
        }
        // Sort Teachers alphabetically.
        usort($teachers, function ($a, $b) {
                $lastnamecomparison = strcmp($a->lastname, $b->lastname);
                // Fallback if both have the same Lastname.
            if ($lastnamecomparison !== 0) {
                return $lastnamecomparison;
            }
            return strcmp($a->firstname, $b->firstname);
        });

        $userfieldvalue = array_shift($teachers)->profile[$userfieldshortname];
        // Set User ID for Course insert to Evasys.
        $parts = explode(',', $userfieldvalue);
        if (!empty($parts)) {
            $internalid = end($parts);
        } else {
            $internalid = (int) $userfieldvalue;
        }
        // Make JSON for Customfields. 1-4 are bookingoption customfields, 5 is secondary teachers details.
        $count = 1;
        $customfieldvaluescollected = [];
        $aggregatedcustomfields = [
            get_config('bookingextension_evasys', 'evasyscustomfield1'),
            get_config('bookingextension_evasys', 'evasyscustomfield2'),
         ];
        $settings = singleton_service::get_instance_of_booking_option_settings($option->id);
        foreach ($aggregatedcustomfields as $customfield) {
            $customfieldvalues = $settings->customfields[$customfield] ?? [];
            $valuescollected = [];
            foreach ($customfieldvalues as $value) {
                $valuescollected[] = $value;
            }
            $customfieldvaluescollected[$count] = implode(',', $valuescollected);
            $count++;
        }
        $category = core_course_category::get((int) $moodlecourseid, IGNORE_MISSING);
        switch (get_config('bookingextension_evasys', 'evasyscustomfield5')) {
            case 'fullname':
                 $teachernames = [];
                foreach ($teachers as $teacher) {
                    $names = $teacher->firstname . ' ' . $teacher->lastname;
                    $teachernames[] = $names;
                }
                $customfield5 = implode(',', $teachernames);
                if(!empty($customfield5)) {
                    $customfield5 = ", " . $customfield5;
                }
                break;
            default:
                $customfield5 = "";
        }
        $coursecustomfield = [
                '1' => $category->id ?? '',
                '2' => $category->get_formatted_name() ?? "",
                '3' => $customfieldvaluescollected[1],
                '4' => $customfieldvaluescollected[2],
                '5' => $customfield5
         ];
         $customfields = json_encode($coursecustomfield, JSON_UNESCAPED_UNICODE);

         // Merge the rest of the teachers with recipients so they get an Evasys Report.
         $secondaryinstructors = array_merge($teachers ?? [], $recipients ?? []);
         $secondaryinstructorsinsert = $helper->set_secondaryinstructors_for_save($secondaryinstructors);
        if (!empty($data->evasysperiods)) {
             $perioddata = explode('-', $data->evasysperiods);
             $periodid = reset($perioddata);
        } else {
             $periodid = get_config('bookingextension_evasys', 'evasysperiods');
        }
         $coursedata = $helper->set_args_insert_course(
             $option->text,
             (int) $option->id,
             (int) $internalid,
             (int) $periodid,
             $secondaryinstructorsinsert,
             $customfields,
             $evasyscourseid,
         );
        return $coursedata;
    }

    /**
     * Creates a Course in EvaSys.
     *
     * @param object $data
     * @param object $option
     *
     * @return object|null
     *
     */
    public function create_course(object $data, object $option, $moodlecourseid) {
        global $DB;
        $coursedata = $this->aggregate_data_for_course_save($data, $option, $moodlecourseid);
        $soap = new evasys_soap_service();
        $response = $soap->insert_course($coursedata);
        if (!empty($response)) {
            $dataobject = (object)[
                'id' => $data->evasys_booking_id,
                'courseidinternal' => $response->m_nCourseId,
                'courseidexternal' => $response->m_sExternalId,
            ];
            $DB->update_record('bookingextension_evasys', $dataobject);
        }
        return $response;
    }

    /**
     * Deletes a Course in EvaSys.
     *
     * @param int $internalid
     * @param int $tableid
     *
     * @return boolean
     *
     */
    public function delete_course(int $internalid, int $tableid) {
        global $DB;
        $helper = new evasys_helper_service();
        $argscourse = $helper->set_args_delete_course($internalid);
        $soap = new evasys_soap_service();
        $response = $soap->delete_course($argscourse);
        if (!empty($response)) {
            $DB->delete_records('bookingextension_evasys', ['id' => $tableid]);
        }
        return $response;
    }

    /**
     * Updates Course in EvaSys.
     *
     * @param object $data
     * @param object $newoption
     * @param int $tableid
     * @param int $moodlecourseid
     *
     * @return object|null
     *
     */
    public function update_course(object $data, object $newoption, int $tableid, int $moodlecourseid) {
        $coursedata = $this->aggregate_data_for_course_save($data, $newoption, $moodlecourseid, $tableid);
        $soap = new evasys_soap_service();
        $response = $soap->update_course($coursedata);
        return $response;
    }

    /**
     * Gets the QR Code for Survey and saves it to DB.
     *
     * @param int $id
     * @param int $surveyid
     *
     * @return string
     *
     */
    public function get_qrcode(int $id, int $surveyid) {
        global $DB;
        $helper = new evasys_helper_service();
        $args = $helper->set_args_get_qrcode($surveyid);
        $soap = new evasys_soap_service();
        $response = $soap->get_qr_code($args);
        if (!empty($response)) {
            $dataobject = (object) [
            'id' => $id,
            'qrurl' => $response,
            ];
            $DB->update_record('bookingextension_evasys', $dataobject);
        }
        return $response;
    }

    /**
     * Gets the survey and saves it to DB.
     *
     * @param int $id
     * @param int $surveyid
     *
     * @return object|null
     *
     */
    public function get_surveyurl(int $id, int $surveyid) {
        global $DB;
        $helper = new evasys_helper_service();
        $soap = new evasys_soap_service();
        $args = $helper->set_args_get_surveyurl($surveyid);
        $response = $soap->get_surveyurl($args);
        if (!empty($response)) {
            $dataobject = (object) [
            'id' => $id,
            'surveyurl' => $response->OnlineCodes->m_sDirectOnlineLink,
            ];
            $DB->update_record('bookingextension_evasys', $dataobject);
        }
        return $response;
    }

    /**
     * Get's cached Forms.
     *
     * @return array
     *
     */
    public function cached_forms() {
        $cache = cache::make('bookingextension_evasys', 'evasysforms');
        $cachedforms = $cache->get('cachedforms');

        if (empty($cachedforms)) {
            $allforms = $this->get_allforms();
            $soap = new evasys_soap_service();
            $helper = new evasys_helper_service();
            $formswithtitle = [];
            foreach ($allforms as $key => $value) {
                $args = $helper->set_args_get_form($key);
                $response = $soap->get_form($args);
                $formswithtitle[$response->FormId] = $response->FormTitle;
            }
            $timecreated = time();
            $cachedata = [$formswithtitle, $timecreated];
            $cache->set('cachedforms', $cachedata);
            $cachedforms = $cachedata;
        }
        return $cachedforms;
    }
}
