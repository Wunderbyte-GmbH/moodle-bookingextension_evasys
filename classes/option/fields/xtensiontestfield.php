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
 * Booking option field class.
 *
 * @package bookingextension_evasys
 * @copyright 2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Bernhard Fischer-Sengseis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace bookingextension_evasys\option\fields;

use mod_booking\booking_option_settings;
use mod_booking\option\fields_info;
use mod_booking\option\field_base;
use MoodleQuickForm;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/booking/bookingextension/evasys/lib.php');

/**
 * Booking option field class.
 *
 * @package bookingextension_evasys
 * @copyright 2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Bernhard Fischer-Sengseis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xtensiontestfield extends field_base {
    /**
     * This subplugin component.
     * @var string
     */
    public static $subplugin = 'bookingextension_evasys';

    /**
     * This ID is used for sorting execution.
     * @var int
     */
    public static $id = MOD_BOOKING_OPTION_FIELD_EVASYSTESTFIELD;

    /**
     * The header identifier.
     * @var string
     */
    public static $header = 'evasysheader';

    /**
     * The icon for the field's header icon.
     * @var string
     */
    public static $headericon = '<i class="fa fa-cubes" aria-hidden="true"></i>&nbsp;';

    /**
     * Some fields are saved with the booking option...
     * This is normal behaviour.
     * Some can be saved only post save (when they need the option id).
     * @var int
     */
    public static $save = MOD_BOOKING_EXECUTION_NORMAL;

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
     * This is an array of incompatible field ids.
     * @var array
     */
    public static $incompatiblefields = [];

    /**
     * This function interprets the value from the form and, if useful...
     * ... relays it to the new option class for saving or updating.
     * @param stdClass $formdata
     * @param stdClass $newoption
     * @param int $updateparam
     * @param ?mixed $returnvalue
     * @return string // If no warning, empty string.
     */
    public static function prepare_save_field(
        stdClass &$formdata,
        stdClass &$newoption,
        int $updateparam,
        $returnvalue = null
    ): array {
        $instance = new xtensiontestfield();
        $changes = [];
        $key = fields_info::get_class_name(static::class);
        $value = $formdata->{$key} ?? null;

        if (!empty($value)) {
            $newoption->{$key} = $value;
        } else {
            $newoption->{$key} = '';
        }
        $pollurlchanges = $instance->check_for_changes($formdata, $instance, null, $key, $value);
        if (!empty($pollurlchanges)) {
            $changes[$key] = $pollurlchanges;
        };
        // We also need to take care of pollurlteachers.
        $key = 'pollurlteachers';
        $value = $formdata->{$key} ?? null;

        if (!empty($value)) {
            $newoption->{$key} = $value;
        } else {
            $newoption->{$key} = '';
        }

        $puteacherschanges = $instance->check_for_changes($formdata, $instance, null, $key, $value);
        if (!empty($puteacherschanges)) {
            $puteacherschanges['changes']['fieldname'] = 'pollurlteachers';
            $changes[$key] = $puteacherschanges;
        };

        // We can return an warning message here.
        return ['changes' => $changes];
    }

    /**
     * Instance form definition
     * @param MoodleQuickForm $mform
     * @param array $formdata
     * @param array $optionformconfig
     * @param array $fieldstoinstanciate
     * @param bool $applyheader
     * @return void
     */
    public static function instance_form_definition(
        MoodleQuickForm &$mform,
        array &$formdata,
        array $optionformconfig,
        $fieldstoinstanciate = [],
        $applyheader = true
    ) {

        // Standardfunctionality to add a header to the mform (only if its not yet there).
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

        $mform->addElement('text', 'pollurl', get_string('bookingpollurl', 'mod_booking'), ['size' => '64']);
        $mform->setType('pollurl', PARAM_TEXT);
        $mform->addHelpButton('pollurl', 'feedbackurl', 'mod_booking');

        $mform->addElement(
            'text',
            'pollurlteachers',
            get_string('bookingpollurlteachers', 'mod_booking'),
            ['size' => '64']
        );
        $mform->setType('pollurlteachers', PARAM_TEXT);
        $mform->addHelpButton('pollurlteachers', 'feedbackurlteachers', 'mod_booking');
    }

    /**
     * This function adds error keys for form validation.
     * @param array $data
     * @param array $files
     * @param array $errors
     * @return array
     */
    public static function validation(array $data, array $files, array &$errors) {

        if (isset($data['pollurl']) && strlen($data['pollurl']) > 0) {
            if (!filter_var($data['pollurl'], FILTER_VALIDATE_URL)) {
                $errors['pollurl'] = get_string('entervalidurl', 'mod_booking');
            }
        }

        if (isset($data['pollurlteachers']) && strlen($data['pollurlteachers']) > 0) {
            if (!filter_var($data['pollurlteachers'], FILTER_VALIDATE_URL)) {
                $errors['pollurlteachers'] = get_string('entervalidurl', 'mod_booking');
            }
        }

        return $errors;
    }

    /**
     * Standard function to transfer stored value to form.
     * @param stdClass $data
     * @param booking_option_settings $settings
     * @return void
     * @throws dml_exception
     */
    public static function set_data(stdClass &$data, booking_option_settings $settings) {

        $key = fields_info::get_class_name(static::class);
        // Normally, we don't call set data after the first time loading.
        if (isset($data->{$key})) {
            return;
        }

        $value = $settings->{$key} ?? null;
        $data->{$key} = $value;

        $value = $settings->pollurlteachers ?? null;
        $data->pollurlteachers = $value;
    }
}
