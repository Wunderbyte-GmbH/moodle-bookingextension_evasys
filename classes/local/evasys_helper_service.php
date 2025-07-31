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
 * Evasys Helper Class.
 *
 * @package bookingextension_evasys
 * @author David Ala
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookingextension_evasys\local;

use stdClass;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/user/lib.php');


/**
 * Helperclass for Evasys.
 */
class evasys_helper_service {
    /**
     * Sets the secondary Instructors for course insert or update.
     *
     * @param array $secondaryinstructors
     *
     * @return array
     *
     */
    public function set_secondaryinstructors_for_save(array $secondaryinstructors) {
        $userfieldshortname = get_config('bookingextension_evasys', 'evasyscategoryfielduser');
        $userlist = [];

        foreach ($secondaryinstructors as $instructor) {
            $parts = explode(',', $instructor->profile[$userfieldshortname]);
            $internalid = end($parts);
            $userobj = new stdClass();
            $userobj->m_nId = (int)$internalid;
            $userobj->m_nType = 1;
            $userobj->m_sLoginName = '';
            $userobj->m_sExternalId = "evasys_{$instructor->id}";
            $userobj->m_sTitle = '';
            $userobj->m_sFirstName = $instructor->firstname;
            $userobj->m_sSurName = $instructor->lastname;
            $userobj->m_sUnitName = '';
            $userobj->m_sAddress = $instructor->address ?? '';
            $userobj->m_sEmail = $instructor->email;
            $userobj->m_nFbid = (int)get_config('bookingextension_evasys', 'evasyssubunits');
            $userobj->m_nAddressId = 0;
            $userobj->m_sPassword = '';
            $userobj->m_sPhoneNumber = $instructor->phone1 ?? '';
            $userobj->m_bUseLDAP = null;
            $userobj->m_bActiveUser = null;
            $userobj->m_bTechnicalAdmin = null;
            $userobj->m_aCourses = null;
            $userlist[] = $userobj;
        }
        return $userlist;
    }

    /**
     * Maps optionform to db record.
     *
     * @param object $formdata
     * @param object $option
     *
     * @return object
     *
     */
    public function map_form_to_record(object $formdata, object $option) {
        global $USER;
        $insertdata = new stdClass();
        $now = time();
        $insertdata->optionid = $option->id;
        $insertdata->formid = $formdata->evasys_form;
        if (empty((int)$formdata->evasys_timemode)) {
            $insertdata->starttime = (int) $option->courseendtime + (int) $formdata->evasys_durationbeforestart;
            $insertdata->endtime = (int) $option->courseendtime + (int) $formdata->evasys_durationafterend;
            $insertdata->durationbeforestart = $formdata->evasys_durationbeforestart;
            $insertdata->durationafterend = $formdata->evasys_durationafterend;
        } else {
            $insertdata->starttime = $formdata->evasys_starttime;
            $insertdata->endtime = $formdata->evasys_endtime;
        }
        $insertdata->trainers = implode(',', ($formdata->teachersforoption ?? []));
        $insertdata->organizers = implode(',', ($formdata->evasys_other_report_recipients ?? []));
        $insertdata->notifyparticipants = $formdata->evasys_notifyparticipants;
        $insertdata->usermodified = $USER->id;
        $insertdata->periods = $formdata->evasysperiods;
        $insertdata->qr = $formdata->qrurl;
        $insertdata->timemode = $formdata->evasys_timemode;
        $insertdata->surveyurl = $formdata->evasys_surveyurl;
        if (empty($formdata->evasys_booking_id)) {
            $insertdata->timecreated = $now;
        } else {
            $insertdata->id = $formdata->evasys_booking_id;
            $insertdata->timemodified = $now;
        }
        return $insertdata;
    }

    /**
     * Maps db record to optionform.
     *
     * @param object $data
     * @param object $record
     *
     * @return void
     *
     */
    public function map_record_to_form(object &$data, object $record) {
        $data->evasys_form = $record->formid;
        $data->evasys_starttime = $record->starttime;
        $data->evasys_endtime = $record->endtime;
        $data->evasys_qr = $record->qrurl;
        $data->evasys_other_report_recipients = explode(',', $record->organizers);
        $data->evasys_notifyparticipants = $record->notifyparticipants;
        $data->evasys_booking_id = $record->id;
        $data->evasys_timecreated = $record->timecreated;
        $data->evasysperiods = $record->periods;
        $data->evasys_surveyid = $record->surveyid;
        $data->evasys_courseidinternal = $record->courseidinternal;
        $data->evasys_courseidexternal = $record->courseidexternal;
        $data->evasys_timemode = $record->timemode;
        $data->evasys_durationbeforestart = $record->durationbeforestart;
        $data->evasys_durationafterend = $record->durationafterend;
        $data->evasys_timemode = $record->timemode;
        $data->evasys_surveyurl = $record->surveyurl;
    }

    /**
     * Transforms Array of objects to an associative array for the settings.
     *
     * @param array $list
     * @param string $key
     * @param string $value
     *
     * @return array
     *
     */
    public function transform_return_to_array(array $list, string $key, string $value) {
        $array = [];
        foreach ($list as $element) {
            $array[$element->$key] = $element->$value;
        }
        return $array;
    }


    /**
     * Helperfunction to set args for insert course to EvaSys.
     *
     * @param string $title
     * @param int $optionid
     * @param int $internalid
     * @param int $periodid
     * @param array $secondaryinstructors
     * @param string $customfield
     * @param mixed $courseid
     *
     * @return object
     *
     */
    public function set_args_insert_course(
        string $title,
        int $optionid,
        int $internalid,
        int $periodid,
        array $secondaryinstructors,
        string $customfield,
        $courseid = null
    ) {
        $subunitencoded = get_config('bookingextension_evasys', 'evasyssubunits');
        $array = explode('-', $subunitencoded);
        $subunitname = base64_decode(end($array));
        $subunitid = reset($array);
        $coursedata = (object) [
            'm_nCourseId' => $courseid,
            'm_sProgramOfStudy' => (string)$subunitname,
            'm_sCourseTitle' => "$title",
            'm_sRoom' => '',
            'm_nCourseType' => 5,
            'm_sPubCourseId' => "urise_$optionid",
            'm_sExternalId' => "urise_$optionid",
            'm_nCountStud' => null,
            'm_sCustomFieldsJSON' => $customfield,
            'm_nUserId' => $internalid,
            'm_nFbid' => (int) $subunitid,
            'm_nPeriodId' => (int)$periodid,
            'currentPosition' => null,
            'hasAnonymousParticipants' => false,
            'isModuleCourse' => null,
            'm_aoParticipants' => [],
            'm_aoSecondaryInstructors' => $secondaryinstructors,
            'm_oSurveyHolder' => null,
        ];
        return $coursedata;
    }

    /**
     * Helperfunction to set args for user to insert into evasys.
     *
     * @param int $userid
     * @param string $firstname
     * @param string $lastname
     * @param string $adress
     * @param string $email
     * @param int|null $phone
     *
     * @return object
     *
     */
    public function set_args_insert_user(
        int $userid,
        string $firstname,
        string $lastname,
        string $adress,
        string $email,
        ?int $phone
    ) {
        $subunitencoded = get_config('bookingextension_evasys', 'evasyssubunits');
        $array = explode('-', $subunitencoded);
        $subunitname = base64_decode(end($array));
        $subunitid = reset($array);
         $user = (object) [
            'm_nId' => null,
            'm_nType' => null,
            'm_sLoginName' => '',
            'm_sExternalId' => "evasys_$userid",
            'm_sTitle' => '',
            'm_sFirstName' => $firstname,
            'm_sSurName' => $lastname,
            'm_sUnitName' => (string) $subunitname,
            'm_sAddress' => $adress ?? '',
            'm_sEmail' => $email,
            'm_nFbid' => (int) $subunitid,
            'm_nAddressId' => 0,
            'm_sPassword' => '',
            'm_sPhoneNumber' => $phone,
            'm_bUseLDAP' => null,
            'm_bActiveUser' => null,
            'm_aCourses' => null,
         ];
         return $user;
    }

    /**
     * Helperfunction to set args for inserting a survey to EvaSys.
     *
     * @param int $userid
     * @param int $internalcourseid
     * @param int $formid
     * @param int $periodid
     *
     * @return array
     *
     */
    public function set_args_insert_survey(int $userid, int $internalcourseid, int $formid, int $periodid) {
        $survey = [
            'nUserId' => $userid,
            'nCourseId' => $internalcourseid,
            'nFormId' => $formid,
            'nPeriodId' => $periodid,
            'sSurveyType' => "c",
        ];
        return $survey;
    }
    /**
     * Helperfunction to set args for deleting a survey in EvaSys.
     *
     * @param int $surveyid
     *
     * @return array
     *
     */
    public function set_args_delete_survey(int $surveyid) {
        $survey = [
            'SurveyId' => $surveyid,
            'IgnoreTwoStepDelete' => false,
        ];
        return $survey;
    }

    /**
     * Helperfunction to set args for deleting a course in EvaSys.
     *
     * @param int $internalcourseid
     *
     * @return array
     *
     */
    public function set_args_delete_course(int $internalcourseid) {
        $course = [
            'CourseId' => $internalcourseid,
            'IdType' => 'INTERNAL',
        ];
        return $course;
    }
    /**
     * Helperfunction to set args for getting QR-Code.
     *
     * @param int $surveyid
     *
     * @return array
     *
     */
    public function set_args_get_qrcode(int $surveyid) {
        $survey = [
            'SurveyId' => $surveyid,
        ];
        return $survey;
    }

    /**
     * Helperfunction to set args for getting a Form.
     *
     * @param int $internalid
     *
     * @return array
     *
     */
    public function set_args_get_form(int $internalid) {
        $form = [
            'FormId' => (int)$internalid,
            'IdType' => 'INTERNAL',
            'IncludeOnlyQuestions' => true,
            'SkipPoleLabelsInheritance' => true,
        ];
        return $form;
    }

    /**
     * Helperfunction to set args for getting all Forms.
     *
     * @param int $subunitid
     *
     * @return array
     *
     */
    public function set_args_fetch_forms(int $subunitid) {
        $args = [
                'IncludeCustomReports' => true,
                'IncludeUsageRestrictions' => true,
                'UsageRestrictionList' => [
                        'Subunits' =>
                [
                    'ID' => (int) $subunitid,
                        ],
                ],
                    ];
                return $args;
    }
    /**
     * Helperfunction to set args for openeing survey.
     *
     * @param int $surveyid
     *
     * @return array
     *
     */
    public function set_args_open_survey(int $surveyid) {
        $args = [
            'nSurveyId' => $surveyid,
        ];
        return $args;
    }
    /**
     * Helperfunction to set args for closing survey.
     *
     * @param int $surveyid
     *
     * @return array
     *
     */
    public function set_args_close_survey_final(int $surveyid) {
        $args = [
            'nSurveyId' => $surveyid,
            'bSendReportToInstructor' => true,
        ];
        return $args;
    }
    /**
     * Helperfunction to set args for closing survey.
     *
     * @param int $surveyid
     *
     * @return array
     *
     */
    public function set_args_close_survey_temporary(int $surveyid) {
        $args = [
            'nSurveyId' => $surveyid,
            'bSendReportToInstructor' => false,
        ];
        return $args;
    }

    /**
     * Helperfunction to set Args for getting the surveyurl.
     *
     * @param int $surveyid
     *
     * @return array
     *
     */
    public function set_args_get_surveyurl(int $surveyid) {
        $args = [
            'nSurveyId' => $surveyid,
            'nPswdCount' => 0,
            'nCodeTypes' => 0,
            'bForceNewPasswordGeneration' => false,
            'bSetPswdsToSent' => false,
            'bGetFiveDigitOnlineCode' => false,
        ];
        return $args;
    }
}
