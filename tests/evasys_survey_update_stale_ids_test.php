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
 * Tests that two queued survey updates for the same option both succeed.
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
 * Tests the update of a survey by two tasks which were queued with the same survey and course ids.
 */
final class evasys_survey_update_stale_ids_test extends advanced_testcase {
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
     * Builds the task for a teacher change the way the option form queues it.
     *
     * @param stdClass $option
     * @param stdClass $snapshot the bookingextension_evasys record at the time the option was saved
     * @param array $oldteachers
     * @param array $newteachers
     * @param int $categoryid
     * @return evasys_send_to_api
     */
    private function build_teacher_change_task(
        stdClass $option,
        stdClass $snapshot,
        array $oldteachers,
        array $newteachers,
        int $categoryid
    ): evasys_send_to_api {
        $task = new evasys_send_to_api();
        $task->set_custom_data([
            'teacherchanges' => [
                'changes' => [
                    'fieldname' => 'teachers',
                    'oldvalue' => $oldteachers,
                    'newvalue' => $newteachers,
                    'formkey' => 'teachersforoption',
                ],
            ],
            'namechanges' => null,
            'relevantchanges' => [],
            'newoption' => [
                'id' => $option->id,
                'text' => $option->text,
                'selflearningcourse' => 0,
            ],
            'relevantkeyssurvey' => ['evasys_form', 'evasysperiods'],
            'relevantkeyscourse' => ['evasys_other_report_recipients'],
            'recipients' => [],
            'data' => [
                'evasys_form' => $snapshot->formid,
                'evasys_surveyid' => $snapshot->surveyid,
                'evasys_courseidexternal' => $snapshot->courseidexternal,
                'evasys_courseidinternal' => $snapshot->courseidinternal,
                'evasys_booking_id' => $snapshot->id,
                'teachersforoption' => $newteachers,
                'evasys_other_report_recipients' => [],
                // The survey is already running, so the report has to be sent before it is replaced.
                'evasys_starttime' => time() - HOURSECS,
                'evasys_endtime' => time() + DAYSECS,
                'evasys_confirmdelete' => 0,
            ],
            'courseid' => $categoryid,
            'changetasks' => false,
        ]);
        return $task;
    }

    /**
     * Two teacher changes are saved shortly after each other while the survey is running.
     * Both tasks are queued with the same survey and course ids. The first task replaces
     * survey and course, the second one has to work with the ids the first one stored.
     *
     * @covers \bookingextension_evasys\task\evasys_send_to_api::execute
     * @covers \bookingextension_evasys\local\evasys_handler::update_survey
     * @return void
     */
    public function test_second_update_task_uses_current_ids(): void {
        global $DB;
        $this->preventResetByRollback();
        [$course, $booking, $option, $teacher] = $this->create_option_setup();
        $teacher2 = $this->getDataGenerator()->create_user();
        $teacher3 = $this->getDataGenerator()->create_user();

        // Simulate the edit option form submission that attaches an EvaSys survey.
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

        // Adhoc task execution writes mtrace output; buffer it to avoid PHPUnit risky output warnings.
        ob_start();
        $this->runAdhocTasks();
        ob_end_clean();

        // The first survey exists in the DB and in the EvaSys API.
        $original = $DB->get_record('bookingextension_evasys', ['optionid' => $option->id], '*', MUST_EXIST);
        $this->assertNotEmpty($original->surveyid);
        $this->assertNotEmpty($original->courseidinternal);
        $this->assertArrayHasKey((int) $original->surveyid, evasys_soap_service_mock::get_surveys());
        $this->assertArrayHasKey((int) $original->courseidinternal, evasys_soap_service_mock::get_courses());

        // Two teacher changes are saved before any task ran, so both tasks hold the ids of the first survey.
        $firsttask = $this->build_teacher_change_task(
            $option,
            $original,
            [$teacher->id],
            [$teacher->id, $teacher2->id],
            (int) $course->category
        );
        $secondtask = $this->build_teacher_change_task(
            $option,
            $original,
            [$teacher->id, $teacher2->id],
            [$teacher->id, $teacher2->id, $teacher3->id],
            (int) $course->category
        );

        // The first task replaces survey and course.
        ob_start();
        $firsttask->execute();
        ob_end_clean();

        $afterfirst = $DB->get_record('bookingextension_evasys', ['id' => $original->id], '*', MUST_EXIST);
        $this->assertNotEquals($original->surveyid, $afterfirst->surveyid);
        $this->assertNotEquals($original->courseidinternal, $afterfirst->courseidinternal);
        $this->assertArrayNotHasKey((int) $original->surveyid, evasys_soap_service_mock::get_surveys());
        $this->assertArrayHasKey((int) $afterfirst->surveyid, evasys_soap_service_mock::get_surveys());

        // The second task still holds the ids of the deleted survey and has to succeed nevertheless.
        ob_start();
        $secondtask->execute();
        $output = ob_get_clean();
        $this->assertStringContainsString('Task done successfully', $output);

        // It replaced the survey of the first task and not the one from its own task data.
        $aftersecond = $DB->get_record('bookingextension_evasys', ['id' => $original->id], '*', MUST_EXIST);
        $this->assertNotEquals($afterfirst->surveyid, $aftersecond->surveyid);
        $this->assertNotEquals($afterfirst->courseidinternal, $aftersecond->courseidinternal);
        $this->assertStringContainsString('/survey/' . $aftersecond->surveyid, $aftersecond->surveyurl);

        // Exactly the current survey and course are left in EvaSys, nothing is orphaned.
        $surveys = evasys_soap_service_mock::get_surveys();
        $courses = evasys_soap_service_mock::get_courses();
        $this->assertEquals([(int) $aftersecond->surveyid], array_keys($surveys));
        $this->assertEquals([(int) $aftersecond->courseidinternal], array_keys($courses));

        // The teacher change of the second task arrived in EvaSys: one main and two secondary instructors.
        $this->assertCount(2, $courses[(int) $aftersecond->courseidinternal]['m_aoSecondaryInstructors']);
    }
}
