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
 * Evasys Option Form field.
 *
 * @package     bookingextension_evasys
 * @copyright   2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      David Ala
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace bookingextension_evasys\option\fields;

 use bookingextension_evasys\task\evasys_send_to_api;
 use mod_booking\booking_option_settings;
 use bookingextension_evasys\local\evasys_handler;
 use mod_booking\option\field_base;
 use mod_booking\singleton_service;
 use MoodleQuickForm;
 use stdClass;

 defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/booking/bookingextension/evasys/lib.php');

 /**
  * EvaSys evaluation field for booking options.
  */
class evasys extends field_base {
    /**
     * This subplugin component.
     * @var string
     */
    public static $subplugin = 'bookingextension_evasys';

    /**
     * This ID is used for sorting execution.
     * @var int
     */
    public static $id = MOD_BOOKING_OPTION_FIELD_EVASYS;

    /**
     * Some fields are saved with the booking option...
     * This is normal behaviour.
     * Some can be saved only post save (when they need the option id).
     * @var int
     */
    public static $save = MOD_BOOKING_EXECUTION_POSTSAVE;

    /**
     * This identifies the header under which this particular field should be displayed.
     * @var string
     */
    public static $header = MOD_BOOKING_HEADER_EVASYS;

    /**
     * An int value to define if this field is standard or used in a different context.
     * @var array
     */
    public static $fieldcategories = [MOD_BOOKING_OPTION_FIELD_STANDARD];

    /**
     * Additionally to the classname, there might be others keys which should instantiate this class.
     * @var array
     */
    public static $alternativeimportidentifiers = [];
    /**
     * The icon for the field's header icon.
     * @var string
     */
    public static $headericon = '<i class="fa fa-cubes" aria-hidden="true"></i>&nbsp;';

    /**
     * This is an array of incompatible field ids.
     * @var array
     */
    public static $incompatiblefields = [];
    /**
     * List of Evasyskeys.
     *
     * @var array
     */
    public static $evasyskeys = [
        'evasys_form',
        'evasys_starttime',
        'evasys_endtime',
        'evasys_other_report_recipients',
        'evasysperiods',
        'evasys_notifyparticipants',
        'evasys_timemode',
        'evasys_durationbeforestart',
        'evasys_durationafterend',
        'evasys_qr',
        'evasys_surveyurl',
    ];

    /**
     * Relevant Keys to update survey to API.
     *
     * @var array
     */
    public static $relevantkeyssurvey = ['evasys_form', 'evasysperiods'];

    /**
     * Relevant Keys when a course need to be upgraded.
     *
     * @var array
     */
    public static $relevantkeyscourse = ['evasys_other_report_recipients'];

    /**
     * Prepare Savefield.
     *
     * @param stdClass $formdata
     * @param stdClass $newoption
     * @param int $updateparam
     * @param mixed $returnvalue
     *
     * @return array
     *
     */
    public static function prepare_save_field(
        stdClass &$formdata,
        stdClass &$newoption,
        int $updateparam,
        $returnvalue = null
    ): array {
        $instance = new evasys();
        $changes = [];
        if (empty($formdata->id)) {
            return $changes;
        } else {
            foreach (self::$evasyskeys as $key) {
                $value = $formdata->{$key} ?? null;
                $mockdata = (object)['optionid' => $formdata->id];
                $changeinkey = $instance->check_for_changes($formdata, $instance, $mockdata, $key, $value);
                if (!empty($changeinkey)) {
                    $changes['changes'][$key] = $changeinkey;
                }
            }
        }
        return $changes;
    }

    /**
     * This function adds error keys for form validation.
     * @param array $formdata
     * @param array $files
     * @param array $errors
     * @return array
     */
    public static function validation(array $formdata, array $files, array &$errors) {
        $settings = singleton_service::get_instance_of_booking_option_settings($formdata['id']);
        if (
            empty($formdata['evasys_form'])
            && !empty($settings->subpluginssettings['evasys']->formid)
            && empty($formdata['evasys_confirmdelete'])
        ) {
                $errors['evasys_confirmdelete'] = get_string('delete', 'bookingextension_evasys');
        }
        return $errors;
    }

    /**
     * Define Form.
     *
     * @param MoodleQuickForm $mform
     * @param array $formdata
     * @param array $optionformconfig
     * @param array $fieldstoinstanciate
     * @param bool $applyheader
     *
     * @return void
     *
     */
    public static function instance_form_definition(
        MoodleQuickForm &$mform,
        array &$formdata,
        array $optionformconfig,
        $fieldstoinstanciate = [],
        $applyheader = true
    ): void {

        if (empty(get_config('bookingextension_evasys', 'evasyssubunits'))) {
            return;
        }
        $evasys = new evasys_handler();
        $forms = [
            'tags' => false,
            'multiple' => false,
            'noselectionstring' => '',
            'ajax' => 'bookingextension_evasys/form_evasysforms_selector',
            'valuehtmlcallback' => function ($value) {
                if (empty($value)) {
                    return get_string('choose...', 'mod_booking');
                }
                $array = explode('-', $value);
                $name = end($array);
                $return = base64_decode($name);
                return $return;
            },
        ];

        $recipients = $evasys->get_recipients();
        $periodoptions = [
            'tags' => false,
            'multiple' => false,
            'noselectionstring' => '',
            'ajax' => 'bookingextension_evasys/form_evasysperiods_selector',
            'valuehtmlcallback' => function ($value) {
                if (empty($value)) {
                    return get_string('choose...', 'mod_booking');
                }
                $array = explode('-', $value);
                $name = end($array);
                $return = base64_decode($name);
                return $return;
            },
        ];

        if (empty(get_config('bookingextension_evasys', 'useevasys'))) {
            return;
        }

        if ($applyheader) {
            $elementexists = $mform->elementExists(self::$header);
            if (!$elementexists) {
                $mform->addElement(
                    'header',
                    self::$header,
                    self::$headericon . get_string(self::$header, self::$subplugin)
                );
            }
        }
        $mform->addElement('static', 'evasysdescription', get_string('evasysdescription', 'bookingextension_evasys'));
        $mform->addElement(
            'autocomplete',
            'evasys_form',
            get_string('questionaire', 'bookingextension_evasys'),
            [],
            $forms,
        );
        $mform->addHelpButton('evasys_form', 'questionaire', 'bookingextension_evasys');
        $mform->addElement(
            'advcheckbox',
            'evasys_confirmdelete',
            get_string('confirmdelete', 'bookingextension_evasys'),
            '',
            [],
            [0, 1],
        );

        $mform->hideIf('evasys_confirmdelete', 'evasys_delete', 'eq', 0);
        // Customer wants the option to potentially have this, but not now.
        $options = [
            0 => get_string('timemodeduration', 'bookingextension_evasys'),
            1 => get_string('timemodestart', 'bookingextension_evasys'),

        ];
        $mform->addElement(
            'hidden',
            'evasys_timemode',
            0
        );
        $mform->setType('evasys_timemode', PARAM_INT);
        $beforestartoptions = [
            -172800 => "48",
            - 86400 => "24",
            -7200 => "2",
            -3600 => "1",
            -1800 => "0,5",
        ];

        // Add date selectors.
        $mform->addElement(
            'select',
            'evasys_durationbeforestart',
            get_string('evaluationdurationbeforestart', 'bookingextension_evasys'),
            $beforestartoptions
        );
        $mform->setDefault('evasys_durationbeforestart', -7200);
        $afterendoptions = [
            7200 => "2",
            86400 => "24",
            172800 => "48",
            259200 => "72",
            345600 => "96",
            1209600 => "336",
        ];

        $mform->addElement(
            'select',
            'evasys_durationafterend',
            get_string('evaluationdurationafterend', 'bookingextension_evasys'),
            $afterendoptions,
        );
        $mform->setDefault('evasys_durationafterend', 7200);

        $mform->addElement(
            'date_time_selector',
            'evasys_starttime',
            get_string('evasysevaluationstarttime', 'bookingextension_evasys'),
            ['step' => 5],
        );
        $starttimestamp = self::prettytime(strtotime("now +1 day +1 hour"));
        $mform->setDefault('evasys_starttime', $starttimestamp);

        $mform->addElement(
            'date_time_selector',
            'evasys_endtime',
            get_string('evasysevaluationendtime', 'bookingextension_evasys'),
            ['step' => 5]
        );
        $endtimestamp = self::prettytime(strtotime("+2 days"));
        $mform->setDefault('evasys_endtime', $endtimestamp);

        // Hide date selectors unless "duration" (option 1) is selected.
        $mform->hideIf('evasys_starttime', 'evasys_timemode', 'noteq', 1);
        $mform->hideIf('evasys_endtime', 'evasys_timemode', 'noteq', 1);
        $mform->hideIf('evasys_durationafterend', 'evasys_timemode', 'noteq', 0);
        $mform->hideIf('evasys_durationbeforestart', 'evasys_timemode', 'noteq', 0);

        $mform->addElement(
            'autocomplete',
            'evasys_other_report_recipients',
            get_string('otherreportrecipients', 'bookingextension_evasys'),
            $recipients,
            ['multiple' => true],
        );

        $mform->addElement(
            'autocomplete',
            'evasysperiods',
            get_string('evasysperiods', 'bookingextension_evasys'),
            [],
            $periodoptions,
        );
        $mform->setDefault('evasysperiods', get_config('bookingextension_evasys', 'evasysperiods'));
        $mform->addElement(
            'advcheckbox',
            'evasys_notifyparticipants',
            get_string('notifyparticipants', 'bookingextension_evasys'),
            '',
            [],
            [0, 1],
        );
        // Hide Everything if no Questionaire was chosen.
        $mform->hideIf('evasys_timemode', 'evasys_form', 'eq', '');
        $mform->hideIf('evasys_other_report_recipients', 'evasys_form', 'eq', '');
        $mform->hideIf('evasysperiods', 'evasys_form', 'eq', '');
        $mform->hideIf('evasys_notifyparticipants', 'evasys_form', 'eq', '');
        $mform->hideIf('evasys_durationafterend', 'evasys_form', 'eq', '');
        $mform->hideIf('evasys_durationbeforestart', 'evasys_form', 'eq', '');

        $mform->addElement(
            'hidden',
            'evasys_booking_id',
            0
        );
        $mform->setType('evasys_booking_id', PARAM_INT);

        $mform->addElement(
            'hidden',
            'evasys_courseidexternal',
            ''
        );
        $mform->setType('evasys_courseidexternal', PARAM_TEXT);

        $mform->addElement(
            'hidden',
            'evasys_courseidinternal',
            ''
        );
        $mform->setType('evasys_courseidinternal', PARAM_INT);

        $mform->addElement('hidden', 'evasys_surveyid', 0);
        $mform->setType('evasys_surveyid', PARAM_INT);

        $mform->addElement('hidden', 'evasys_delete', 0);
        $mform->setDefault('evasys_delete', 0);
        $mform->setType('evasys_delete', PARAM_INT);

        $mform->addElement('hidden', 'evasys_qr', 0);
        $mform->setType('evasys_qr', PARAM_TEXT);

        $mform->addElement('hidden', 'evasys_surveyurl', 0);
        $mform->setType('evasys_surveyurl', PARAM_TEXT);
    }

    /**
     * Load Form data from DB.
     *
     * @param stdClass $data
     * @param booking_option_settings $settings
     *
     * @return void
     *
     */
    public static function set_data(&$data, booking_option_settings $settings) {
        $evasys = new evasys_handler();
        $evasys->load_form($data);
    }

    /**
     * Definition after data callback.
     *
     * @param MoodleQuickForm $mform
     * @param array $formdata
     *
     * @return void
     *
     */
    public static function definition_after_data(MoodleQuickForm &$mform, $formdata) {
        $freezetime = time();
        $evaluationstarttime = (int)($formdata['evasys_starttime'] ?? 0);
        if (empty($evaluationstarttime)) {
            return;
        }
        if ($evaluationstarttime < $freezetime) {
            $mform->freeze([
                'evasys_form',
                'evasys_timemode',
                'evasys_durationbeforestart',
                'evasys_durationafterend',
                'evasys_starttime',
                'evasys_endtime',
                'evasys_other_report_recipients',
                'evasysperiods',
                'evasys_notifyparticipants',
            ]);
        };
        $nosubmit = false;
        foreach ($mform->_noSubmitButtons as $key) {
            if (isset($formdata[$key])) {
                $nosubmit = true;
                break;
            }
        }
        if (
            !$nosubmit
            && isset($formdata['evasys_form'])
            && ($mform->_flagSubmitted ?? false)
            && empty($formdata['evasys_form'])
        ) {
            $validationelement = $mform->getElement('evasys_delete');
            $validationelement->setValue(1);
        }
    }

    /**
     * Save Form data
     * @param object $formdata
     * @param object $option
     * @return void
     * @throws \dml_exception
     */
    public static function save_data(object &$formdata, object &$option) {
        if (empty($formdata->evasys_form)) {
            return;
        }
        $evasys = new evasys_handler();
        $evasys->save_form($formdata, $option);
        if (empty($formdata->teachersforoption)) {
            return;
        }
    }

    /**
     * Once all changes are collected, also those triggered in save data, this is a possible hook for the fields.
     *
     * @param array $changes
     * @param object $data
     * @param object $newoption
     * @param object $originaloption
     *
     * @return void
     */
    public static function changes_collected_action(
        array $changes,
        object $data,
        object $newoption,
        object $originaloption
    ) {
        global $COURSE;
        // Get just the relevant data for the logic of the task.
        $settings = singleton_service::get_instance_of_booking_option_settings($newoption->id);
        if (!isset($settings->subpluginssettings['evasys']->id)) {
            return;
        }
        $relevantdata = new stdClass();
        $relevantdata->evasys_form = $settings->subpluginssettings['evasys']->formid;
        $relevantdata->evasys_surveyid = $settings->subpluginssettings['evasys']->surveyid;
        $relevantdata->evasys_courseidexternal = $settings->subpluginssettings['evasys']->courseidexternal;
        $relevantdata->evasys_courseidinternal = $settings->subpluginssettings['evasys']->courseidinternal;
        $relevantdata->evasys_booking_id = $settings->subpluginssettings['evasys']->id;
        $relevantdata->teachersforoption = $data->teachersforoption;
        $relevantdata->evasys_other_report_recipients = $data->evasys_other_report_recipients;
        $relevantdata->evasys_starttime = $settings->subpluginssettings['evasys']->starttime;
        $relevantdata->evasys_endtime = $settings->subpluginssettings['evasys']->endtime;
        $relevantdata->evasys_confirmdelete = $data->evasys_confirmdelete;
        $relevantoptiondata = new stdClass();
        $relevantoptiondata->id = $newoption->id;
        $relevantoptiondata->text = $newoption->text;

        $task = new evasys_send_to_api();
        $taskdata = [
            'teacherchanges' => $changes["mod_booking\\option\\fields\\teachers"],
            'namechanges' => $changes["mod_booking\\option\\fields\\text"],
            'relevantchanges' => $changes["bookingextension_evasys\\option\\fields\\evasys"]['changes'],
            'newoption' => $relevantoptiondata,
            'relevantkeyssurvey' => self::$relevantkeyssurvey,
            'relevantkeyscourse' => self::$relevantkeyscourse,
            'recipients' => $data->evasys_other_report_recipients,
            'data' => $relevantdata,
            'courseid' => $COURSE->category,
        ];
        $task->set_custom_data($taskdata);
        // Now queue the task or reschedule it if it already exists (with matching data).
        \core\task\manager::reschedule_or_queue_adhoc_task($task);
    }
    /**
     * Makes the minutes always to be zero.
     *
     * @param int $timestamp
     *
     * @return int
     *
     */
    private static function prettytime(int $timestamp) {
        $prettytimestamp = make_timestamp(
            (int)date('Y', $timestamp),
            (int)date('n', $timestamp),
            (int)date('j', $timestamp),
            (int)date('H', $timestamp),
            0,
        );
        return $prettytimestamp;
    }
}
