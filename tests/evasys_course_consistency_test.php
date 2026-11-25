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
 * Tests that course and survey of an option stay unique and reachable in EvaSys.
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
 * Tests the guards against duplicate courses and the update of a course in EvaSys.
 */
final class evasys_course_consistency_test extends advanced_testcase {
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
            'name' => 'EvaSys Update Case',
            'eventtype' => 'Update test',
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
        $record->description = 'Update test option';
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
     * Submits the option form with an EvaSys form selected, like a user saving the option.
     *
     * @param stdClass $option
     * @param stdClass $booking
     * @param stdClass $teacher
     * @return void
     */
    private function submit_option_form(stdClass $option, stdClass $booking, stdClass $teacher): void {
        $settings = singleton_service::get_instance_of_booking_option_settings($option->id);
        $evasyssubplugin = $settings->subpluginssettings['evasys'] ?? (object) [];

        $postdata = (object) [
            'id' => $option->id,
            'cmid' => $option->cmid,
            'bookingid' => $booking->id,
            'text' => 'Option with survey',
            'description' => 'Update test option',
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
     * Builds a task the way the option form queues it. The survey has not started yet.
     *
     * @param stdClass $option
     * @param stdClass $snapshot the bookingextension_evasys record at the time the option was saved
     * @param array $teachers the teachers of the option after the save
     * @param array $recipients the additional report recipients after the save
     * @param array $changes keys teacherchanges and relevantchanges
     * @param int $categoryid
     * @return evasys_send_to_api
     */
    private function build_task(
        stdClass $option,
        stdClass $snapshot,
        array $teachers,
        array $recipients,
        array $changes,
        int $categoryid
    ): evasys_send_to_api {
        $task = new evasys_send_to_api();
        $task->set_custom_data([
            'teacherchanges' => $changes['teacherchanges'] ?? [],
            'namechanges' => null,
            'relevantchanges' => $changes['relevantchanges'] ?? [],
            'newoption' => [
                'id' => $option->id,
                'text' => $option->text,
                'selflearningcourse' => 0,
            ],
            'relevantkeyssurvey' => ['evasys_form', 'evasysperiods'],
            'relevantkeyscourse' => ['evasys_other_report_recipients'],
            'recipients' => $recipients,
            'data' => [
                'evasys_form' => $snapshot->formid,
                'evasys_surveyid' => $snapshot->surveyid,
                'evasys_courseidexternal' => $snapshot->courseidexternal,
                'evasys_courseidinternal' => $snapshot->courseidinternal,
                'evasys_booking_id' => $snapshot->id,
                'teachersforoption' => $teachers,
                'evasys_other_report_recipients' => $recipients,
                'evasys_starttime' => time() + DAYSECS,
                'evasys_endtime' => time() + (2 * DAYSECS),
                'evasys_confirmdelete' => 0,
            ],
            'courseid' => $categoryid,
            'changetasks' => false,
        ]);
        return $task;
    }

    /**
     * The teacher changes of a task, in the format of the option form.
     *
     * @param array $oldteachers
     * @param array $newteachers
     * @return array
     */
    private function teacher_changes(array $oldteachers, array $newteachers): array {
        return [
            'teacherchanges' => [
                'changes' => [
                    'fieldname' => 'teachers',
                    'oldvalue' => $oldteachers,
                    'newvalue' => $newteachers,
                    'formkey' => 'teachersforoption',
                ],
            ],
        ];
    }

    /**
     * An option is saved twice before any task ran, the second time with an additional teacher.
     * Both tasks were queued without a course id. EvaSys accepts a second course with the same
     * external id for another main instructor, so the second task must replace the course of
     * the first one instead of creating another one.
     *
     * @covers \bookingextension_evasys\task\evasys_send_to_api::execute
     * @return void
     */
    public function test_second_create_task_does_not_create_second_course(): void {
        global $DB;
        $this->preventResetByRollback();
        [$course, $booking, $option, $teacher] = $this->create_option_setup();
        $teacher2 = $this->getDataGenerator()->create_user();

        $this->submit_option_form($option, $booking, $teacher);
        // The record at the time of the save: no course and no survey yet.
        $snapshot = $DB->get_record('bookingextension_evasys', ['optionid' => $option->id], '*', MUST_EXIST);
        $this->assertEmpty($snapshot->courseidinternal);
        $this->assertEmpty($snapshot->courseidexternal);

        // The task of the first save creates course and survey.
        ob_start();
        $this->runAdhocTasks();
        ob_end_clean();
        $afterfirst = $DB->get_record('bookingextension_evasys', ['id' => $snapshot->id], '*', MUST_EXIST);
        $this->assertNotEmpty($afterfirst->courseidinternal);
        $this->assertNotEmpty($afterfirst->surveyid);
        $this->assertCount(1, evasys_soap_service_mock::get_courses());

        // The task of the second save still holds the empty ids.
        $secondtask = $this->build_task(
            $option,
            $snapshot,
            [$teacher->id, $teacher2->id],
            [],
            $this->teacher_changes([$teacher->id], [$teacher->id, $teacher2->id]),
            (int) $course->category
        );
        ob_start();
        $secondtask->execute();
        $output = ob_get_clean();
        $this->assertStringContainsString('Task done successfully', $output);

        // Exactly one course and one survey are left in EvaSys, nothing is orphaned.
        $aftersecond = $DB->get_record('bookingextension_evasys', ['id' => $snapshot->id], '*', MUST_EXIST);
        $courses = evasys_soap_service_mock::get_courses();
        $this->assertEquals([(int) $aftersecond->courseidinternal], array_keys($courses));
        $this->assertEquals([(int) $aftersecond->surveyid], array_keys(evasys_soap_service_mock::get_surveys()));
        $this->assertNotEquals($afterfirst->courseidinternal, $aftersecond->courseidinternal);
        // The teacher change of the second save arrived in EvaSys.
        $this->assertCount(1, $courses[(int) $aftersecond->courseidinternal]['m_aoSecondaryInstructors']);
    }

    /**
     * If the old course can not be deleted in EvaSys, no new course and no new survey are created.
     *
     * @covers \bookingextension_evasys\local\evasys_handler::update_survey
     * @return void
     */
    public function test_failed_course_deletion_creates_no_new_course(): void {
        global $DB;
        $this->preventResetByRollback();
        [$course, $booking, $option, $teacher] = $this->create_option_setup();
        $teacher2 = $this->getDataGenerator()->create_user();

        $this->submit_option_form($option, $booking, $teacher);
        ob_start();
        $this->runAdhocTasks();
        ob_end_clean();
        $original = $DB->get_record('bookingextension_evasys', ['optionid' => $option->id], '*', MUST_EXIST);
        $this->assertNotEmpty($original->courseidinternal);

        evasys_soap_service_mock::set_fail_delete_course(true);
        evasys_soap_service_mock::reset_calls();
        $task = $this->build_task(
            $option,
            $original,
            [$teacher->id, $teacher2->id],
            [],
            $this->teacher_changes([$teacher->id], [$teacher->id, $teacher2->id]),
            (int) $course->category
        );
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('could not be deleted', $output);
        $calls = evasys_soap_service_mock::get_calls();
        $this->assertContains('DeleteCourse', $calls);
        $this->assertNotContains('InsertCourse', $calls);
        $this->assertNotContains('InsertCentralSurvey', $calls);
        // The old course is still the only one, and our record still points to it.
        $this->assertEquals([(int) $original->courseidinternal], array_keys(evasys_soap_service_mock::get_courses()));
        $after = $DB->get_record('bookingextension_evasys', ['id' => $original->id], '*', MUST_EXIST);
        $this->assertEquals($original->courseidinternal, $after->courseidinternal);
    }

    /**
     * A change of only the additional report recipients updates the course in EvaSys.
     * EvaSys identifies the course by its own internal id, not by the id of our record.
     *
     * @covers \bookingextension_evasys\task\evasys_send_to_api::execute
     * @covers \bookingextension_evasys\local\evasys_handler::update_course
     * @return void
     */
    public function test_recipient_change_updates_course_with_evasys_course_id(): void {
        global $DB;
        $this->preventResetByRollback();
        [$course, $booking, $option, $teacher] = $this->create_option_setup();
        $recipient = $this->getDataGenerator()->create_user();

        $this->submit_option_form($option, $booking, $teacher);
        ob_start();
        $this->runAdhocTasks();
        ob_end_clean();
        $original = $DB->get_record('bookingextension_evasys', ['optionid' => $option->id], '*', MUST_EXIST);
        $this->assertNotEmpty($original->courseidinternal);
        // Record id and EvaSys course id differ, otherwise the test would prove nothing.
        $this->assertNotEquals((int) $original->id, (int) $original->courseidinternal);
        $courses = evasys_soap_service_mock::get_courses();
        $this->assertCount(0, $courses[(int) $original->courseidinternal]['m_aoSecondaryInstructors']);

        evasys_soap_service_mock::reset_calls();
        $task = $this->build_task(
            $option,
            $original,
            [$teacher->id],
            [$recipient->id],
            ['relevantchanges' => ['evasys_other_report_recipients' => ['oldvalue' => [], 'newvalue' => [$recipient->id]]]],
            (int) $course->category
        );
        ob_start();
        $task->execute();
        $output = ob_get_clean();
        $this->assertStringContainsString('Task done successfully', $output);

        // Only the course was updated, survey and course were not replaced.
        $calls = evasys_soap_service_mock::get_calls();
        $this->assertContains('UpdateCourse', $calls);
        $this->assertNotContains('DeleteCourse', $calls);
        $this->assertNotContains('InsertCourse', $calls);
        $courses = evasys_soap_service_mock::get_courses();
        $this->assertEquals([(int) $original->courseidinternal], array_keys($courses));
        // The recipient arrived as secondary instructor of the right course.
        $this->assertCount(1, $courses[(int) $original->courseidinternal]['m_aoSecondaryInstructors']);
    }
}
