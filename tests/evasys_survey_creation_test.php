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
 * Base tests for creating EvaSys surveys from booking options.
 *
 * @package bookingextension_evasys
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookingextension_evasys;

use advanced_testcase;
use bookingextension_evasys\local\evasys_handler;
use context_module;
use mod_booking\form\option_form;
use mod_booking\singleton_service;
use mod_booking_generator;
use stdClass;
use tool_mocktesttime\time_mock;

/**
 * Base tests for survey creation flow.
 */
final class evasys_survey_creation_test extends advanced_testcase {
    /**
     * Tests set up.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        time_mock::init();
        time_mock::set_mock_time(strtotime('now'));
        singleton_service::destroy_instance();
    }

    /**
     * Mandatory clean-up after each test.
     *
     * @return void
     */
    public function tearDown(): void {
        parent::tearDown();
        /** @var mod_booking_generator $plugingenerator */
        $plugingenerator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $plugingenerator->teardown();
    }

    /**
     * Creates a booking setup and a connected booking option.
     *
     * @return array
     */
    private function create_option_setup(): array {
        $this->setAdminUser();
        $basetime = time_mock::get_mock_time() ?: time();

        $teacher = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $booking = $this->getDataGenerator()->create_module('booking', [
            'name' => 'EvaSys Survey Base Case',
            'eventtype' => 'Base test',
            'bookedtext' => ['text' => 'booked'],
            'waitingtext' => ['text' => 'waiting'],
            'notifyemail' => ['text' => 'notify'],
            'statuschangetext' => ['text' => 'status'],
            'deletedtext' => ['text' => 'deleted'],
            'pollurltext' => ['text' => 'poll'],
            'pollurlteacherstext' => ['text' => 'pollteachers'],
            'notificationtext' => ['text' => 'notification'],
            'userleave' => ['text' => 'leave'],
            'tags' => '',
            'course' => $course->id,
            'bookingmanager' => $teacher->username,
            'showviews' => ['mybooking,myoptions,optionsiamresponsiblefor,showall,showactive,myinstitution'],
        ]);

        /** @var mod_booking_generator $plugingenerator */
        $plugingenerator = self::getDataGenerator()->get_plugin_generator('mod_booking');

        $record = new stdClass();
        $record->bookingid = $booking->id;
        $record->text = 'Option with survey';
        $record->importing = 1;
        $record->chooseorcreatecourse = 1;
        $record->courseid = $course->id;
        $record->description = 'Base survey creation test option';
        $record->coursestarttime = $basetime + (2 * DAYSECS);
        $record->courseendtime = $basetime + (3 * DAYSECS);
        $record->optiondateid_0 = '0';
        $record->daystonotify_0 = '0';
        $record->coursestarttime_0 = $basetime + (2 * DAYSECS);
        $record->courseendtime_0 = $basetime + (3 * DAYSECS);
        $record->teachersforoption = $teacher->username;

        $option = $plugingenerator->create_option($record);
        singleton_service::destroy_booking_option_singleton($option->id);

        return [$course, $booking, $option, $teacher];
    }

    /**
     * Base case: creating a survey from a booking option persists survey linkage.
     *
     * @covers \bookingextension_evasys\local\evasys_handler::create_survey
     * @return void
     */
    public function test_create_survey_for_booking_option_base_case(): void {
        global $DB;

        [$course, $booking, $option] = $this->create_option_setup();

        $evasysrow = (object) [
            'optionid' => $option->id,
            'formid' => '510',
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 0,
        ];
        $bookingid = $DB->insert_record('bookingextension_evasys', $evasysrow, true);

        $data = (object) [
            'evasys_form' => 510,
            'evasys_booking_id' => (int) $bookingid,
        ];

        $courseresponse = (object) [
            'm_nUserId' => 101,
            'm_nCourseId' => 201,
            'm_nPeriodId' => 20261,
        ];

        $handler = new evasys_handler();
        $survey = $handler->create_survey($courseresponse, $data, $option);

        $this->assertNotEmpty($survey);
        $this->assertObjectHasProperty('m_nSurveyId', $survey);
        $this->assertGreaterThan(0, $survey->m_nSurveyId);

        $stored = $DB->get_record('bookingextension_evasys', ['id' => $bookingid], '*', MUST_EXIST);
        $this->assertEquals($survey->m_nSurveyId, (int) $stored->surveyid);
        $this->assertNotEmpty($stored->surveyurl);
        $this->assertStringContainsString('/survey/' . $survey->m_nSurveyId, $stored->surveyurl);
        $this->assertNotEmpty($stored->qrurl);
        $this->assertStringContainsString('api.qrserver.com', $stored->qrurl);

        // Avoid unused variable warnings while keeping setup return explicit.
        $this->assertGreaterThan(0, $course->id);
        $this->assertGreaterThan(0, $booking->id);
    }

    /**
     * Closer-to-reality flow: simulate editoption form submission and then create a survey.
     *
     * @covers \mod_booking\form\option_form::process_dynamic_submission
     * @covers \bookingextension_evasys\local\evasys_handler::save_form
     * @covers \bookingextension_evasys\task\send_to_api::execute
     * @return void
     */
    public function test_editoption_dynamic_submission_then_create_survey_with_mocktime(): void {
        global $DB;
        $this->preventResetByRollback();
        [$course, $booking, $option, $teacher] = $this->create_option_setup();

        $mocktime = strtotime('2026-05-26 10:00:00');
        time_mock::set_mock_time($mocktime);

        // Force real field changes so change keys are present in changes_collected_action.
        $settings = singleton_service::get_instance_of_booking_option_settings($option->id);
        $evasyssubplugin = $settings->subpluginssettings['evasys'] ?? (object) [];
        $defaultperiods = $evasyssubplugin->periods ?? get_config('bookingextension_evasys', 'evasysperiods') ?? '';
        $defaulttimemode = (int) ($evasyssubplugin->timemode ?? 0);
        $defaultbeforestart = (int) ($evasyssubplugin->durationbeforestart ?? -7200);
        $defaultafterend = (int) ($evasyssubplugin->durationafterend ?? 7200);
        $defaultstarttime = (int) ($evasyssubplugin->starttime ?? 0);
        $defaultendtime = (int) ($evasyssubplugin->endtime ?? 0);
        $defaultnotifyparticipants = (int) ($evasyssubplugin->notifyparticipants ?? 0);
        $defaultbookingid = (int) ($evasyssubplugin->id ?? 0);
        $defaultcourseidexternal = (string) ($evasyssubplugin->courseidexternal ?? '');
        $defaultcourseidinternal = (int) ($evasyssubplugin->courseidinternal ?? 0);
        $defaultsurveyid = (int) ($evasyssubplugin->surveyid ?? 0);
        $defaultqr = $evasyssubplugin->qrurl ?? 0;
        $defaultsurveyurl = $evasyssubplugin->surveyurl ?? 0;

        $postdata = (object) [
            'id' => $option->id,
            'cmid' => $option->cmid,
            'bookingid' => $booking->id,
            'text' => 'Option with survey updated',
            'description' => 'Form submit simulation for EvaSys',
            'evasys_form' => 510,
            'evasys_durationbeforestart' => $defaultbeforestart,
            'evasys_durationafterend' => $defaultafterend,
            'evasys_starttime' => $defaultstarttime,
            'evasys_endtime' => $defaultendtime,
            'evasys_other_report_recipients' => [],
            'evasys_notifyparticipants' => $defaultnotifyparticipants,
            'evasysperiods' => $defaultperiods,
            'evasys_timemode' => $defaulttimemode,
            'evasys_confirmdelete' => 0,
            'evasys_delete' => 0,
            'evasys_booking_id' => $defaultbookingid,
            'evasys_courseidexternal' => $defaultcourseidexternal,
            'evasys_courseidinternal' => $defaultcourseidinternal,
            'evasys_surveyid' => $defaultsurveyid,
            'evasys_qr' => $defaultqr,
            'qrurl' => $defaultqr,
            'evasys_surveyurl' => $defaultsurveyurl,
            'teachersforoption' => [$teacher->id],
            'optionid' => $option->id,
        ];

        $context = context_module::instance($option->cmid);
        $form = $this->getMockBuilder(option_form::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_data', 'get_context_for_dynamic_submission'])
            ->getMock();

        $form->method('get_data')->willReturn($postdata);
        $form->method('get_context_for_dynamic_submission')->willReturn($context);
        $form->process_dynamic_submission();

        // Adhoc task execution writes mtrace output; buffer it to avoid PHPUnit risky output warnings.
        ob_start();
        $this->runAdhocTasks();
        ob_end_clean();
        $saved = $DB->get_record('bookingextension_evasys', ['optionid' => $option->id], '*', MUST_EXIST);
        $this->assertEquals(510, (int) $saved->formid);
        $this->assertEquals($mocktime, (int) $saved->timecreated);
        $stored = $DB->get_record('bookingextension_evasys', ['id' => $saved->id], '*', MUST_EXIST);
        $this->assertNotEmpty($stored->surveyid);
        $this->assertNotEmpty($stored->surveyurl);
        $this->assertStringContainsString('/survey/' . $stored->surveyid, $stored->surveyurl);
        $this->assertNotEmpty($stored->qrurl);
        $this->assertStringContainsString('api.qrserver.com', $stored->qrurl);
        $this->assertNotEmpty($stored->courseidexternal);
    }
}
