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
 * Test-only EvaSys SOAP mock service.
 *
 * @package bookingextension_evasys
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookingextension_evasys\tests\fixtures;

use InvalidArgumentException;
use stdClass;

/**
 * Lightweight mock used by tests instead of a network SOAP service.
 */
class evasys_soap_service_mock {
    /** @var string */
    private const NS = 'soapserver-v101.wsdl';

    /** @var string */
    private string $lastrequestxml = '';

    /** @var string */
    private string $lastresponsexml = '';

    /** @var int */
    private int $nextuserid = 1000;

    /** @var int */
    private int $nextcourseid = 2000;

    /** @var int */
    private int $nextsurveyid = 3000;

    /**
     * Last generated request XML envelope.
     *
     * @return string
     */
    public function get_last_request_xml(): string {
        return $this->lastrequestxml;
    }

    /**
     * Last generated response XML envelope.
     *
     * @return string
     */
    public function get_last_response_xml(): string {
        return $this->lastresponsexml;
    }

    /**
     * Fetches subunits from mock.
     *
     * @return object
     */
    public function fetch_subunits() {
        $response = (object) [
            'Units' => [
                (object) ['m_nId' => 10, 'm_sName' => 'Default Subunit'],
                (object) ['m_nId' => 11, 'm_sName' => 'Secondary Subunit'],
            ],
        ];
        $this->record_call('GetSubunits', [], $response);
        return $response;
    }

    /**
     * Fetches periods from mock.
     *
     * @return object
     */
    public function fetch_periods() {
        $response = (object) [
            'Periods' => [
                (object) ['m_nPeriodId' => 20261, 'm_sTitel' => 'SS 2026'],
                (object) ['m_nPeriodId' => 20262, 'm_sTitel' => 'WS 2026/27'],
            ],
        ];
        $this->record_call('GetAllPeriods', [], $response);
        return $response;
    }

    /**
     * Gets one period by ID.
     *
     * @param array $args
     * @return object
     */
    public function get_period(array $args) {
        $periodid = $args['PeriodId'] ?? ($args['nPeriodId'] ?? null);
        $this->assert_int($periodid, 'GetPeriod.PeriodId/nPeriodId');

        $response = (object) [
            'm_nPeriodId' => (int) $periodid,
            'm_sTitel' => 'Mock Period ' . (int) $periodid,
        ];
        $this->record_call('GetPeriod', $args, $response);
        return $response;
    }

    /**
     * Fetches forms with strict request validation.
     *
     * @param array $args
     * @return object
     */
    public function fetch_forms(array $args) {
        $this->assert_required_keys(
            $args,
            ['IncludeCustomReports', 'IncludeUsageRestrictions', 'UsageRestrictionList'],
            'GetAllForms'
        );
        $this->assert_bool($args['IncludeCustomReports'], 'GetAllForms.IncludeCustomReports');
        $this->assert_bool($args['IncludeUsageRestrictions'], 'GetAllForms.IncludeUsageRestrictions');
        if (!is_array($args['UsageRestrictionList'])) {
            throw new InvalidArgumentException('GetAllForms.UsageRestrictionList must be an array.');
        }

        $subunits = $args['UsageRestrictionList']['Subunits'] ?? null;
        if (!is_array($subunits) || !array_key_exists('ID', $subunits)) {
            throw new InvalidArgumentException('GetAllForms.UsageRestrictionList.Subunits.ID is required.');
        }
        $this->assert_int($subunits['ID'], 'GetAllForms.UsageRestrictionList.Subunits.ID');

        $response = (object) [
            'SimpleForms' => [
                (object) ['ID' => 510, 'Name' => 'Course Evaluation'],
                (object) ['ID' => 511, 'Name' => 'Teaching Feedback'],
            ],
        ];

        $this->record_call('GetAllForms', $args, $response);
        return $response;
    }

    /**
     * Gets one form by ID with strict validation.
     *
     * @param array $args
     * @return object
     */
    public function get_form(array $args) {
        $this->assert_required_keys(
            $args,
            ['FormId', 'IdType', 'IncludeOnlyQuestions', 'SkipPoleLabelsInheritance'],
            'GetForm'
        );
        $this->assert_int($args['FormId'], 'GetForm.FormId');
        $this->assert_equals('INTERNAL', $args['IdType'], 'GetForm.IdType');
        $this->assert_bool($args['IncludeOnlyQuestions'], 'GetForm.IncludeOnlyQuestions');
        $this->assert_bool($args['SkipPoleLabelsInheritance'], 'GetForm.SkipPoleLabelsInheritance');

        $response = (object) [
            'FormId' => (int) $args['FormId'],
            'FormTitle' => 'Mock Form ' . (int) $args['FormId'],
        ];

        $this->record_call('GetForm', $args, $response);
        return $response;
    }

    /**
     * Inserts a user.
     *
     * @param object $args
     * @return object
     */
    public function insert_user(object $args) {
        $user = $this->to_array($args);
        $this->assert_required_keys(
            $user,
            ['m_sExternalId', 'm_sFirstName', 'm_sSurName', 'm_sEmail', 'm_nFbid'],
            'InsertUser.user'
        );
        $this->assert_string($user['m_sExternalId'], 'InsertUser.user.m_sExternalId');
        $this->assert_string($user['m_sFirstName'], 'InsertUser.user.m_sFirstName');
        $this->assert_string($user['m_sSurName'], 'InsertUser.user.m_sSurName');
        $this->assert_string($user['m_sEmail'], 'InsertUser.user.m_sEmail');
        $this->assert_int($user['m_nFbid'], 'InsertUser.user.m_nFbid');

        $response = (object) array_merge($user, ['m_nId' => $this->nextuserid++]);
        $this->record_call('InsertUser', ['user' => $user], $response);
        return $response;
    }

    /**
     * Inserts a course.
     *
     * @param array $args
     * @return object
     */
    public function insert_course(array $args) {
        $course = $args;
        $this->validate_course_payload($course, 'InsertCourse.course', false);

        $response = (object) array_merge($course, ['m_nCourseId' => $this->nextcourseid++]);
        $this->record_call('InsertCourse', ['course' => $course], $response);
        return $response;
    }

    /**
     * Updates a course.
     *
     * @param array $args
     * @return object
     */
    public function update_course(array $args) {
        $course = $args;
        $this->validate_course_payload($course, 'UpdateCourse.course', true);

        $response = (object) $course;
        $this->record_call('UpdateCourse', ['course' => $course], $response);
        return $response;
    }

    /**
     * Deletes a course.
     *
     * @param array $args
     * @return bool
     */
    public function delete_course(array $args) {
        $this->assert_required_keys($args, ['CourseId', 'IdType'], 'DeleteCourse');
        $this->assert_int($args['CourseId'], 'DeleteCourse.CourseId');
        $this->assert_equals('INTERNAL', $args['IdType'], 'DeleteCourse.IdType');

        $response = true;
        $this->record_call('DeleteCourse', $args, ['DeleteCourseResult' => true]);
        return $response;
    }

    /**
     * Inserts a central survey.
     *
     * @param array $args
     * @return object
     */
    public function insert_survey(array $args) {
        $this->assert_required_keys($args, ['nUserId', 'nCourseId', 'nFormId', 'nPeriodId', 'sSurveyType'], 'InsertCentralSurvey');
        $this->assert_int($args['nUserId'], 'InsertCentralSurvey.nUserId');
        $this->assert_int($args['nCourseId'], 'InsertCentralSurvey.nCourseId');
        $this->assert_int($args['nFormId'], 'InsertCentralSurvey.nFormId');
        $this->assert_int($args['nPeriodId'], 'InsertCentralSurvey.nPeriodId');
        $this->assert_equals('c', $args['sSurveyType'], 'InsertCentralSurvey.sSurveyType');

        $response = (object) ['m_nSurveyId' => $this->nextsurveyid++];
        $this->record_call('InsertCentralSurvey', $args, $response);
        return $response;
    }

    /**
     * Deletes a survey.
     *
     * @param array $args
     * @return bool
     */
    public function delete_survey(array $args) {
        $this->assert_required_keys($args, ['SurveyId', 'IgnoreTwoStepDelete'], 'DeleteSurvey');
        $this->assert_int($args['SurveyId'], 'DeleteSurvey.SurveyId');
        $this->assert_bool($args['IgnoreTwoStepDelete'], 'DeleteSurvey.IgnoreTwoStepDelete');

        $this->record_call('DeleteSurvey', $args, ['DeleteSurveyResult' => true]);
        return true;
    }

    /**
     * Opens a survey.
     *
     * @param array $args
     * @return bool
     */
    public function open_survey(array $args) {
        $this->assert_required_keys($args, ['nSurveyId'], 'OpenSurvey');
        $this->assert_int($args['nSurveyId'], 'OpenSurvey.nSurveyId');

        $this->record_call('OpenSurvey', $args, ['OpenSurveyResult' => true]);
        return true;
    }

    /**
     * Closes a survey.
     *
     * @param array $args
     * @return bool
     */
    public function close_survey(array $args) {
        $this->assert_required_keys($args, ['nSurveyId', 'bSendReportToInstructor'], 'CloseSurvey');
        $this->assert_int($args['nSurveyId'], 'CloseSurvey.nSurveyId');
        $this->assert_bool($args['bSendReportToInstructor'], 'CloseSurvey.bSendReportToInstructor');

        $this->record_call('CloseSurvey', $args, ['CloseSurveyResult' => true]);
        return true;
    }

    /**
     * Returns direct online survey URL.
     *
     * @param array $args
     * @return object
     */
    public function get_surveyurl(array $args) {
        $this->assert_required_keys(
            $args,
            [
                'nSurveyId',
                'nPswdCount',
                'nCodeTypes',
                'bForceNewPasswordGeneration',
                'bSetPswdsToSent',
                'bGetFiveDigitOnlineCode',
            ],
            'GetPswdsBySurvey'
        );
        $this->assert_int($args['nSurveyId'], 'GetPswdsBySurvey.nSurveyId');
        $this->assert_int($args['nPswdCount'], 'GetPswdsBySurvey.nPswdCount');
        $this->assert_int($args['nCodeTypes'], 'GetPswdsBySurvey.nCodeTypes');
        $this->assert_bool($args['bForceNewPasswordGeneration'], 'GetPswdsBySurvey.bForceNewPasswordGeneration');
        $this->assert_bool($args['bSetPswdsToSent'], 'GetPswdsBySurvey.bSetPswdsToSent');
        $this->assert_bool($args['bGetFiveDigitOnlineCode'], 'GetPswdsBySurvey.bGetFiveDigitOnlineCode');

        $response = (object) [
            'OnlineCodes' => (object) [
                'm_sDirectOnlineLink' => 'https://mock.evasys.local/survey/' . (int) $args['nSurveyId'],
            ],
        ];

        $this->record_call('GetPswdsBySurvey', $args, $response);
        return $response;
    }

    /**
     * Saves reporting task.
     *
     * @param array $args
     * @return object
     */
    public function send_report(array $args) {
        if (!isset($args['TasksToSave']) || !is_array($args['TasksToSave'])) {
            throw new InvalidArgumentException('SaveTasks.TasksToSave must be an array.');
        }
        $task = $args['TasksToSave']['SendResultsToInstructorsTask'] ?? null;
        if (!is_array($task)) {
            throw new InvalidArgumentException('SaveTasks.TasksToSave.SendResultsToInstructorsTask must be an array.');
        }

        $this->assert_required_keys(
            $task,
            [
                'SurveyId',
                'SendEmail',
                'SendDefaultReport',
                'SendReportDefinitions',
                'StartTime',
                'Recipients',
            ],
            'SaveTasks.TasksToSave.SendResultsToInstructorsTask'
        );

        $this->assert_int($task['SurveyId'], 'SaveTasks.SurveyId');
        $this->assert_bool($task['SendEmail'], 'SaveTasks.SendEmail');
        $this->assert_bool($task['SendDefaultReport'], 'SaveTasks.SendDefaultReport');
        $this->assert_bool($task['SendReportDefinitions'], 'SaveTasks.SendReportDefinitions');
        $this->assert_iso8601($task['StartTime'], 'SaveTasks.StartTime');

        if (!is_array($task['Recipients'])) {
            throw new InvalidArgumentException('SaveTasks.Recipients must be an array.');
        }
        $placeholder = $task['Recipients']['Placeholder'] ?? null;
        if (!is_array($placeholder) || empty($placeholder)) {
            throw new InvalidArgumentException('SaveTasks.Recipients.Placeholder must be a non-empty array.');
        }
        foreach ($placeholder as $index => $value) {
            $this->assert_int($value, 'SaveTasks.Recipients.Placeholder[' . $index . ']');
        }

        $response = (object) ['m_bSaved' => true, 'm_nTaskCount' => 1];
        $this->record_call('SaveTasks', $args, $response);
        return $response;
    }

    /**
     * Validates a course payload.
     *
     * @param array $course
     * @param string $path
     * @param bool $requirecourseid
     * @return void
     */
    private function validate_course_payload(array $course, string $path, bool $requirecourseid): void {
        $required = [
            'm_sCourseTitle',
            'm_sPubCourseId',
            'm_sExternalId',
            'm_sCustomFieldsJSON',
            'm_nUserId',
            'm_nFbid',
            'm_nPeriodId',
            'm_aoSecondaryInstructors',
        ];
        if ($requirecourseid) {
            $required[] = 'm_nCourseId';
        }

        $this->assert_required_keys($course, $required, $path);
        if ($requirecourseid) {
            $this->assert_int($course['m_nCourseId'], $path . '.m_nCourseId');
        }
        $this->assert_string($course['m_sCourseTitle'], $path . '.m_sCourseTitle');
        $this->assert_string($course['m_sPubCourseId'], $path . '.m_sPubCourseId');
        $this->assert_string($course['m_sExternalId'], $path . '.m_sExternalId');
        $this->assert_string($course['m_sCustomFieldsJSON'], $path . '.m_sCustomFieldsJSON');
        $this->assert_json($course['m_sCustomFieldsJSON'], $path . '.m_sCustomFieldsJSON');
        $this->assert_int($course['m_nUserId'], $path . '.m_nUserId');
        $this->assert_int($course['m_nFbid'], $path . '.m_nFbid');
        $this->assert_int($course['m_nPeriodId'], $path . '.m_nPeriodId');
        if (!is_array($course['m_aoSecondaryInstructors'])) {
            throw new InvalidArgumentException($path . '.m_aoSecondaryInstructors must be an array.');
        }
    }

    /**
     * Records request and response payload XML.
     *
     * @param string $operation
     * @param mixed $request
     * @param mixed $response
     * @return void
     */
    private function record_call(string $operation, $request, $response): void {
        $requestpayload = [$operation => $this->to_array($request)];
        $responsepayload = [$operation . 'Response' => $this->to_array($response)];
        $this->lastrequestxml = $this->build_envelope($requestpayload);
        $this->lastresponsexml = $this->build_envelope($responsepayload);
    }

    /**
     * Creates a SOAP-like envelope for assertions.
     *
     * @param array $payload
     * @return string
     */
    private function build_envelope(array $payload): string {
        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns="' . self::NS . '">';
        $xml .= '<soap:Body>';
        $xml .= $this->build_xml_nodes($payload, 'ns');
        $xml .= '</soap:Body></soap:Envelope>';
        return $xml;
    }

    /**
     * Recursively serializes payload values to XML nodes.
     *
     * @param mixed $value
     * @param string $prefix
     * @param string|null $tag
     * @return string
     */
    private function build_xml_nodes($value, string $prefix = 'ns', ?string $tag = null): string {
        if (is_array($value)) {
            if ($tag !== null) {
                $xml = '<' . $tag . '>';
            } else {
                $xml = '';
            }

            $islist = array_keys($value) === range(0, count($value) - 1);
            if ($islist) {
                foreach ($value as $item) {
                    $childtag = $tag ?? ($prefix . ':Item');
                    $xml .= $this->build_xml_nodes($item, $prefix, $childtag);
                }
            } else {
                foreach ($value as $key => $item) {
                    $childtag = $prefix . ':' . $key;
                    $xml .= $this->build_xml_nodes($item, $prefix, $childtag);
                }
            }

            if ($tag !== null) {
                $xml .= '</' . $tag . '>';
            }
            return $xml;
        }

        if (is_object($value)) {
            return $this->build_xml_nodes($this->to_array($value), $prefix, $tag);
        }

        if ($tag === null) {
            return '';
        }

        $scalar = $this->to_scalar_xml($value);
        return '<' . $tag . '>' . $scalar . '</' . $tag . '>';
    }

    /**
     * Converts scalar values to XML-safe text.
     *
     * @param mixed $value
     * @return string
     */
    private function to_scalar_xml($value): string {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1);
    }

    /**
     * Converts objects recursively to arrays.
     *
     * @param mixed $value
     * @return mixed
     */
    private function to_array($value) {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = $this->to_array($item);
        }
        return $out;
    }

    /**
     * Asserts that required keys exist in a payload array.
     *
     * @param array $payload
     * @param array $keys
     * @param string $path
     * @return void
     */
    private function assert_required_keys(array $payload, array $keys, string $path): void {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new InvalidArgumentException($path . '.' . $key . ' is required.');
            }
        }
    }

    /**
     * Asserts that a value is an integer.
     *
     * @param mixed $value
     * @param string $path
     * @return void
     */
    private function assert_int($value, string $path): void {
        if (!is_int($value)) {
            throw new InvalidArgumentException($path . ' must be int.');
        }
    }

    /**
     * Asserts that a value is a boolean.
     *
     * @param mixed $value
     * @param string $path
     * @return void
     */
    private function assert_bool($value, string $path): void {
        if (!is_bool($value)) {
            throw new InvalidArgumentException($path . ' must be bool.');
        }
    }

    /**
     * Asserts that a value is a string.
     *
     * @param mixed $value
     * @param string $path
     * @return void
     */
    private function assert_string($value, string $path): void {
        if (!is_string($value)) {
            throw new InvalidArgumentException($path . ' must be string.');
        }
    }

    /**
     * Asserts strict equality with a clear path-aware message.
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param string $path
     * @return void
     */
    private function assert_equals($expected, $actual, string $path): void {
        if ($expected !== $actual) {
            throw new InvalidArgumentException($path . ' must be ' . var_export($expected, true) . '.');
        }
    }

    /**
     * Asserts that a string contains valid JSON.
     *
     * @param mixed $value
     * @param string $path
     * @return void
     */
    private function assert_json($value, string $path): void {
        if (!is_string($value)) {
            throw new InvalidArgumentException($path . ' must be valid JSON string.');
        }
        json_decode($value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException($path . ' must be valid JSON string.');
        }
    }

    /**
     * Asserts that a value looks like an ISO-8601 datetime string.
     *
     * @param mixed $value
     * @param string $path
     * @return void
     */
    private function assert_iso8601($value, string $path): void {
        if (!is_string($value) || strtotime($value) === false) {
            throw new InvalidArgumentException($path . ' must be ISO-8601 datetime string.');
        }
    }
}
