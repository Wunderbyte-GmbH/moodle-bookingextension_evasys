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
 * Tests that changed dates of a booking option replace survey and course in EvaSys.
 *
 * @package bookingextension_evasys
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookingextension_evasys;

use advanced_testcase;
use bookingextension_evasys\task\evasys_close_survey;
use bookingextension_evasys\task\evasys_open_survey;
use bookingextension_evasys\task\evasys_send_to_api;
use bookingextension_evasys\tests\fixtures\evasys_soap_service_mock;
use context_module;
use mod_booking\form\option_form;
use mod_booking\singleton_service;
use mod_booking_generator;
use stdClass;
use tool_mocktesttime\time_mock;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/booking/bookingextension/evasys/tests/fixtures/evasys_soap_service_mock.php');

/**
 * Tests the replacement of survey and course when the dates of the booking option change.
 */
final class evasys_survey_update_on_datechange_test extends advanced_testcase {
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
        evasys_soap_service_mock::reset_mock_state();
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
     * Creates a booking setup and a booking option with one date.
     *
     * @param int $start start of the date
     * @param int $end end of the date
     * @return array
     */
    private function create_option_setup(int $start, int $end): array {
        $this->setAdminUser();

        $teacher = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $booking = $this->getDataGenerator()->create_module('booking', [
            'name' => 'EvaSys Date Change Case',
            'eventtype' => 'Date change test',
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
        $record->description = 'Date change test option';
        $record->coursestarttime = $start;
        $record->courseendtime = $end;
        $record->optiondateid_0 = '0';
        $record->daystonotify_0 = '0';
        $record->coursestarttime_0 = $start;
        $record->courseendtime_0 = $end;
        $record->teachersforoption = $teacher->username;
        $option = $plugingenerator->create_option($record);
        singleton_service::destroy_booking_option_singleton($option->id);

        return [$booking, $option, $teacher];
    }

    /**
     * Saves the option the way the option form does, with the given start and end of its only date.
     * All EvaSys values are the stored ones, so only the date can be a change.
     *
     * @param stdClass $booking
     * @param stdClass $option
     * @param stdClass $teacher
     * @param int $start start of the date
     * @param int $end end of the date
     * @return void
     */
    private function submit_option_form(stdClass $booking, stdClass $option, stdClass $teacher, int $start, int $end): void {
        global $DB;
        singleton_service::destroy_booking_option_singleton($option->id);
        $settings = singleton_service::get_instance_of_booking_option_settings($option->id);
        $evasyssubplugin = $settings->subpluginssettings['evasys'] ?? (object) [];
        $optiondates = $DB->get_records('booking_optiondates', ['optionid' => $option->id], 'id ASC');
        $optiondate = reset($optiondates);

        $postdata = (object) [
            'id' => $option->id,
            'cmid' => $option->cmid,
            'bookingid' => $booking->id,
            'text' => 'Option with survey',
            'description' => 'Date change test option',
            'optiondateid_1' => (int) $optiondate->id,
            'coursestarttime_1' => $start,
            'courseendtime_1' => $end,
            'daystonotify_1' => 0,
            'evasys_form' => 510,
            'evasys_durationbeforestart' => (int) ($evasyssubplugin->durationbeforestart ?? -7200),
            'evasys_durationafterend' => (int) ($evasyssubplugin->durationafterend ?? 7200),
            'evasys_starttime' => (int) ($evasyssubplugin->starttime ?? 0),
            'evasys_endtime' => (int) ($evasyssubplugin->endtime ?? 0),
            'evasys_other_report_recipients' => [],
            'evasys_notifyparticipants' => (int) ($evasyssubplugin->notifyparticipants ?? 0),
            'evasysperiods' => $evasyssubplugin->periods ?? get_config('bookingextension_evasys', 'evasysperiods') ?? '',
            'evasys_timemode' => (int) ($evasyssubplugin->timemode ?? 0),
            'evasys_confirmdelete' => 0,
            'evasys_delete' => 0,
            'evasys_booking_id' => (int) ($evasyssubplugin->id ?? 0),
            'evasys_courseidexternal' => (string) ($evasyssubplugin->courseidexternal ?? ''),
            'evasys_courseidinternal' => (int) ($evasyssubplugin->courseidinternal ?? 0),
            'evasys_surveyid' => (int) ($evasyssubplugin->surveyid ?? 0),
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
    }

    /**
     * Runs the queued adhoc tasks. They write mtrace output, which is buffered to avoid risky test warnings.
     *
     * @return void
     */
    private function run_tasks(): void {
        ob_start();
        $this->runAdhocTasks();
        ob_end_clean();
    }

    /**
     * Returns the queued api tasks of the option.
     *
     * @param int $optionid
     * @return array
     */
    private function get_api_tasks(int $optionid): array {
        $tasks = \core\task\manager::get_adhoc_tasks(evasys_send_to_api::class);
        return array_values(array_filter($tasks, fn($task) => (int) $task->get_custom_data()->newoption->id === $optionid));
    }

    /**
     * Moving the date of an option with a survey which has not started yet replaces survey and course
     * and schedules the opening and closing of the new survey for the new evaluation window.
     *
     * @covers \bookingextension_evasys\option\fields\evasys::changes_collected_action
     * @covers \bookingextension_evasys\task\evasys_send_to_api::execute
     * @return void
     */
    public function test_date_change_replaces_survey_and_course(): void {
        global $DB;
        $this->preventResetByRollback();
        $basetime = time_mock::get_mock_time() ?: time();
        $start = $basetime + (2 * DAYSECS);
        $end = $basetime + (3 * DAYSECS);
        [$booking, $option, $teacher] = $this->create_option_setup($start, $end);

        // Attach the survey.
        $this->submit_option_form($booking, $option, $teacher, $start, $end);
        $this->run_tasks();
        $original = $DB->get_record('bookingextension_evasys', ['optionid' => $option->id], '*', MUST_EXIST);
        $this->assertNotEmpty($original->surveyid);
        $this->assertNotEmpty($original->courseidinternal);

        // Saving again without any change leaves survey and course alone.
        $this->submit_option_form($booking, $option, $teacher, $start, $end);
        foreach ($this->get_api_tasks((int) $option->id) as $task) {
            $this->assertEmpty($task->get_custom_data()->datechanges);
        }
        $this->run_tasks();
        $unchanged = $DB->get_record('bookingextension_evasys', ['id' => $original->id], '*', MUST_EXIST);
        $this->assertEquals($original->surveyid, $unchanged->surveyid);
        $this->assertEquals($original->courseidinternal, $unchanged->courseidinternal);

        // Now the date is moved by a week.
        evasys_soap_service_mock::reset_calls();
        $this->submit_option_form($booking, $option, $teacher, $start + WEEKSECS, $end + WEEKSECS);
        $apitasks = $this->get_api_tasks((int) $option->id);
        $this->assertCount(1, $apitasks);
        $this->assertTrue($apitasks[0]->get_custom_data()->datechanges);
        $this->run_tasks();

        // Survey and course were replaced, the old ones are gone in EvaSys.
        $replaced = $DB->get_record('bookingextension_evasys', ['id' => $original->id], '*', MUST_EXIST);
        $this->assertNotEquals($original->surveyid, $replaced->surveyid);
        $this->assertNotEquals($original->courseidinternal, $replaced->courseidinternal);
        $this->assertEquals([(int) $replaced->surveyid], array_keys(evasys_soap_service_mock::get_surveys()));
        $this->assertEquals([(int) $replaced->courseidinternal], array_keys(evasys_soap_service_mock::get_courses()));
        $this->assertStringContainsString('/survey/' . $replaced->surveyid, $replaced->surveyurl);

        // The survey had not started, so there was no report to send before it was deleted.
        $this->assertNotContains('SaveTasks', evasys_soap_service_mock::get_calls());

        // The evaluation window moved with the date.
        $this->assertEquals($end + WEEKSECS + (int) $replaced->durationbeforestart, (int) $replaced->starttime);
        $this->assertEquals($end + WEEKSECS + (int) $replaced->durationafterend, (int) $replaced->endtime);

        // The new survey is opened and closed in the new evaluation window.
        $opentimes = [];
        foreach (\core\task\manager::get_adhoc_tasks(evasys_open_survey::class) as $task) {
            if ((int) $task->get_custom_data()->surveyid === (int) $replaced->surveyid) {
                $opentimes[] = (int) $task->get_next_run_time();
            }
        }
        $closetimes = [];
        foreach (\core\task\manager::get_adhoc_tasks(evasys_close_survey::class) as $task) {
            if ((int) $task->get_custom_data()->surveyid === (int) $replaced->surveyid) {
                $closetimes[] = (int) $task->get_next_run_time();
            }
        }
        $this->assertEquals([(int) $replaced->starttime], $opentimes);
        $this->assertEquals([(int) $replaced->endtime], $closetimes);
    }

    /**
     * A survey which is already running is closed and its report is sent before it is replaced,
     * even though the new date moves the start of the evaluation into the future.
     *
     * @covers \bookingextension_evasys\option\fields\evasys::changes_collected_action
     * @covers \bookingextension_evasys\local\evasys_handler::update_survey
     * @return void
     */
    public function test_date_change_of_running_survey_sends_report_first(): void {
        global $DB;
        $this->preventResetByRollback();
        $basetime = time_mock::get_mock_time() ?: time();
        // The evaluation starts two hours before the end of the date, so it is running already.
        $start = $basetime - DAYSECS;
        $end = $basetime + HOURSECS;
        [$booking, $option, $teacher] = $this->create_option_setup($start, $end);

        $this->submit_option_form($booking, $option, $teacher, $start, $end);
        $this->run_tasks();
        $original = $DB->get_record('bookingextension_evasys', ['optionid' => $option->id], '*', MUST_EXIST);
        $this->assertNotEmpty($original->surveyid);
        $this->assertLessThan($basetime, (int) $original->starttime);

        evasys_soap_service_mock::reset_calls();
        $this->submit_option_form($booking, $option, $teacher, $start + WEEKSECS, $end + WEEKSECS);
        $this->run_tasks();

        $replaced = $DB->get_record('bookingextension_evasys', ['id' => $original->id], '*', MUST_EXIST);
        $this->assertNotEquals($original->surveyid, $replaced->surveyid);
        $this->assertGreaterThan($basetime, (int) $replaced->starttime);
        $this->assertEquals([(int) $replaced->surveyid], array_keys(evasys_soap_service_mock::get_surveys()));

        // Close and report of the old survey come before its deletion.
        $calls = evasys_soap_service_mock::get_calls();
        $close = array_search('CloseSurvey', $calls, true);
        $report = array_search('SaveTasks', $calls, true);
        $delete = array_search('DeleteSurvey', $calls, true);
        $this->assertNotFalse($close);
        $this->assertNotFalse($report);
        $this->assertNotFalse($delete);
        $this->assertLessThan($report, $close);
        $this->assertLessThan($delete, $report);
    }
}
