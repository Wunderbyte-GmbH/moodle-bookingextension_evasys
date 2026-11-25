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
 * Tests that the period chosen in the booking option is the period of course and survey in EvaSys.
 *
 * @package bookingextension_evasys
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookingextension_evasys;

use advanced_testcase;
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
 * Tests the period of course and survey in EvaSys on creation and after a change of the period.
 */
final class evasys_survey_period_test extends advanced_testcase {
    /** @var int Id of the default period of the plugin settings. */
    private const DEFAULTPERIODID = 20261;

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
        set_config('evasysperiods', self::encode_period(self::DEFAULTPERIODID, 'SS 2026'), 'bookingextension_evasys');
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
     * Returns a period the way the period selector of the option form submits it.
     *
     * @param int $periodid
     * @param string $name
     * @return string
     */
    private static function encode_period(int $periodid, string $name): string {
        return $periodid . '-' . base64_encode($name);
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
            'name' => 'EvaSys Period Case',
            'eventtype' => 'Period test',
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
        $record->description = 'Period test option';
        $record->coursestarttime = $start;
        $record->courseendtime = $end;
        $record->optiondateid_0 = '0';
        $record->daystonotify_0 = '0';
        $record->coursestarttime_0 = $start;
        $record->courseendtime_0 = $end;
        $record->teachersforoption = $teacher->username;
        $option = $plugingenerator->create_option($record);
        singleton_service::destroy_booking_option_singleton($option->id);

        return [$booking, $option, $teacher, $start, $end];
    }

    /**
     * Saves the option the way the option form does, with the given period.
     * All other EvaSys values are the stored ones, so only the period can be a change.
     *
     * @param stdClass $booking
     * @param stdClass $option
     * @param stdClass $teacher
     * @param int $start start of the date
     * @param int $end end of the date
     * @param string $period the period as submitted by the period selector
     * @return void
     */
    private function submit_option_form(
        stdClass $booking,
        stdClass $option,
        stdClass $teacher,
        int $start,
        int $end,
        string $period
    ): void {
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
            'description' => 'Period test option',
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
            'evasysperiods' => $period,
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
     * Asserts that the option has exactly one course and one survey in EvaSys and both are in the given period.
     *
     * @param stdClass $record the bookingextension_evasys record of the option
     * @param int $periodid
     * @return void
     */
    private function assert_period_in_evasys(stdClass $record, int $periodid): void {
        $courses = evasys_soap_service_mock::get_courses();
        $surveys = evasys_soap_service_mock::get_surveys();
        $this->assertEquals([(int) $record->courseidinternal], array_keys($courses));
        $this->assertEquals([(int) $record->surveyid], array_keys($surveys));
        $this->assertSame($periodid, $courses[(int) $record->courseidinternal]['m_nPeriodId']);
        $this->assertSame($periodid, $surveys[(int) $record->surveyid]['nPeriodId']);
    }

    /**
     * A period chosen in the option which is not the default period of the settings
     * is the period of the course and the survey created in EvaSys.
     *
     * @covers \bookingextension_evasys\option\fields\evasys::changes_collected_action
     * @covers \bookingextension_evasys\local\evasys_handler::aggregate_data_for_course_save
     * @return void
     */
    public function test_chosen_period_is_used_on_creation(): void {
        global $DB;
        $this->preventResetByRollback();
        $basetime = time_mock::get_mock_time() ?: time();
        [$booking, $option, $teacher, $start, $end] = $this->create_option_setup(
            $basetime + (2 * DAYSECS),
            $basetime + (3 * DAYSECS)
        );

        $period = self::encode_period(20262, 'WS 2026/27');
        $this->submit_option_form($booking, $option, $teacher, $start, $end, $period);
        $this->run_tasks();

        $record = $DB->get_record('bookingextension_evasys', ['optionid' => $option->id], '*', MUST_EXIST);
        $this->assertEquals($period, $record->periods);
        $this->assertNotEmpty($record->surveyid);
        $this->assert_period_in_evasys($record, 20262);
    }

    /**
     * Changing nothing but the period replaces survey and course in EvaSys, the new ones are in the new period.
     *
     * @covers \bookingextension_evasys\option\fields\evasys::changes_collected_action
     * @covers \bookingextension_evasys\task\evasys_send_to_api::execute
     * @covers \bookingextension_evasys\local\evasys_handler::update_survey
     * @return void
     */
    public function test_period_change_replaces_survey_in_new_period(): void {
        global $DB;
        $this->preventResetByRollback();
        $basetime = time_mock::get_mock_time() ?: time();
        [$booking, $option, $teacher, $start, $end] = $this->create_option_setup(
            $basetime + (2 * DAYSECS),
            $basetime + (3 * DAYSECS)
        );

        // Attach the survey in a period which is not the default one.
        $this->submit_option_form($booking, $option, $teacher, $start, $end, self::encode_period(20262, 'WS 2026/27'));
        $this->run_tasks();
        $original = $DB->get_record('bookingextension_evasys', ['optionid' => $option->id], '*', MUST_EXIST);
        $this->assertNotEmpty($original->surveyid);
        $this->assert_period_in_evasys($original, 20262);

        // Now only the period changes.
        $newperiod = self::encode_period(20271, 'SS 2027');
        $this->submit_option_form($booking, $option, $teacher, $start, $end, $newperiod);
        $apitasks = $this->get_api_tasks((int) $option->id);
        $this->assertCount(1, $apitasks);
        $taskdata = $apitasks[0]->get_custom_data();
        $this->assertEquals(['evasysperiods'], array_keys((array) $taskdata->relevantchanges));
        $this->assertEmpty($taskdata->datechanges);
        $this->assertEquals($newperiod, $taskdata->data->evasysperiods);
        $this->run_tasks();

        // Survey and course were replaced by new ones in the new period.
        $replaced = $DB->get_record('bookingextension_evasys', ['id' => $original->id], '*', MUST_EXIST);
        $this->assertEquals($newperiod, $replaced->periods);
        $this->assertNotEquals($original->surveyid, $replaced->surveyid);
        $this->assertNotEquals($original->courseidinternal, $replaced->courseidinternal);
        $this->assert_period_in_evasys($replaced, 20271);

        // The evaluation window did not move.
        $this->assertEquals($original->starttime, $replaced->starttime);
        $this->assertEquals($original->endtime, $replaced->endtime);
    }
}
