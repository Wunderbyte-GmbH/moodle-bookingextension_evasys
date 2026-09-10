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
 * Tests that deleting a booking option deletes its survey in EvaSys.
 *
 * @package bookingextension_evasys
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookingextension_evasys;

use advanced_testcase;
use bookingextension_evasys\tests\fixtures\evasys_soap_service_mock;
use context_module;
use mod_booking\booking_option;
use mod_booking\form\option_form;
use mod_booking\singleton_service;
use mod_booking_generator;
use stdClass;
use tool_mocktesttime\time_mock;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/booking/bookingextension/evasys/tests/fixtures/evasys_soap_service_mock.php');

/**
 * Tests that deleting a booking option deletes its survey in EvaSys.
 */
final class bookingoption_deleted_deletes_survey_test extends advanced_testcase {
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
            'name' => 'EvaSys Deletion Case',
            'eventtype' => 'Deletion test',
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
        $record->description = 'Deletion test option';
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
     * Creates a survey for the option via the edit option form, then deletes the
     * option and checks that survey and course are gone in the EvaSys API.
     *
     * @covers \bookingextension_evasys\observer::bookingoption_deleted
     * @covers \bookingextension_evasys\local\evasys_handler::delete_survey
     * @covers \bookingextension_evasys\local\evasys_handler::delete_course
     * @return void
     */
    public function test_bookingoption_deleted_deletes_survey_in_evasys(): void {
        global $DB;
        $this->preventResetByRollback();
        [$course, $booking, $option, $teacher] = $this->create_option_setup();

        // Simulate the edit option form submission that attaches an EvaSys survey.
        $settings = singleton_service::get_instance_of_booking_option_settings($option->id);
        $evasyssubplugin = $settings->subpluginssettings['evasys'] ?? (object) [];

        $postdata = (object) [
            'id' => $option->id,
            'cmid' => $option->cmid,
            'bookingid' => $booking->id,
            'text' => 'Option with survey',
            'description' => 'Deletion test option',
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
            'evasys_qr' => $evasyssubplugin->qrurl ?? 0,
            'qrurl' => $evasyssubplugin->qrurl ?? 0,
            'evasys_surveyurl' => $evasyssubplugin->surveyurl ?? 0,
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

        // The survey now exists in the DB and in the EvaSys API.
        $stored = $DB->get_record('bookingextension_evasys', ['optionid' => $option->id], '*', MUST_EXIST);
        $this->assertNotEmpty($stored->surveyid);
        $this->assertNotEmpty($stored->courseidinternal);
        $this->assertArrayHasKey((int) $stored->surveyid, evasys_soap_service_mock::get_surveys());
        $this->assertArrayHasKey((int) $stored->courseidinternal, evasys_soap_service_mock::get_courses());

        // Delete the booking option like the UI does; this triggers the
        // bookingoption_deleted event which the observer reacts to.
        $bookingoption = singleton_service::get_instance_of_booking_option($option->cmid, $option->id);
        $this->assertTrue($bookingoption->delete_booking_option());
        $this->assertFalse($DB->record_exists('booking_options', ['id' => $option->id]));

        // Survey and course are gone in the EvaSys API and the record is deleted.
        $this->assertArrayNotHasKey((int) $stored->surveyid, evasys_soap_service_mock::get_surveys());
        $this->assertArrayNotHasKey((int) $stored->courseidinternal, evasys_soap_service_mock::get_courses());
        $this->assertFalse($DB->record_exists('bookingextension_evasys', ['id' => $stored->id]));

        // Avoid unused variable warnings while keeping setup return explicit.
        $this->assertGreaterThan(0, $course->id);
    }
}
