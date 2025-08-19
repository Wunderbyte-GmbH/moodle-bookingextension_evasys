<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Class evasys.
 *
 * @package     bookingextension_evasys
 * @copyright   2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      David Ala
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookingextension_evasys;

use admin_setting_configcheckbox;
use admin_setting_configpasswordunmask;
use admin_setting_configselect;
use admin_setting_configtext;
use admin_setting_description;
use admin_setting_heading;
use admin_settingpage;
use bookingextension_evasys\local\evasys_handler;
use context_module;
use mod_booking\customfield\booking_handler;
use mod_booking\plugininfo\bookingextension;
use mod_booking\plugininfo\bookingextension_interface;
use mod_booking\singleton_service;
use SoapFault;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/booking/bookingextension/evasys/lib.php');

/**
 * Class for the Evasys booking extension.
 */
class evasys extends bookingextension implements bookingextension_interface {
    /**
     * Get the plugin name.
     * @return string the plugin name
     */
    public function get_plugin_name(): string {
        return get_string('pluginname', 'bookingextension_evasys');
    }

    /**
     * Check if the booking extension contains new option fields.
     * @return bool True if the booking extension contains new option fields, false otherwise.
     */
    public function contains_option_fields(): bool {
        // Yes, this plugin contains new option fields.
        return true;
    }

    /**
     * If the extension adds new option fields this array contains the according information.
     * @return array
     */
    public function get_option_fields_info_array(): array {
        return [
            'evasys' => [
                'name' => 'evasys',
                'class' => 'bookingextension_evasys\option\fields\evasys',
                'id' => MOD_BOOKING_OPTION_FIELD_EVASYS,
            ],
            // We can add more fields here...
        ];
    }

    /**
     * Returns eventkeys that are allowed for the bookingrule.
     *
     * @return array
     *
     */
    public static function get_allowedruleeventkeys(): array {
        $allowedeventkeys = ['evasys_surveycreated'];
        return $allowedeventkeys;
    }

    /**
     * Sets the Data for Optionview for the Bookingoption Description.
     *
     * @param object $settings
     *
     * @return array
     *
     */
    public static function set_template_data_for_optionview(object $settings): array {
        global $USER;
        $modcontext = context_module::instance($settings->cmid);
        $templatedata = [];
        if (!isset($settings->subpluginssettings['evasys']->qrurl)) {
            return $templatedata;
        }
        if (empty(get_config('bookingextension_evasys', 'includeqrinoptionview'))) {
            return $templatedata;
        }
        $ba = singleton_service::get_instance_of_booking_answers($settings);
        if (
            has_capability('mod/booking:updatebooking', $modcontext)
            || isset($ba->usersonlist[$USER->id])
            || booking_check_if_teacher($settings->id)
        ) {
            $now = time();
            if ($now > $settings->subpluginssettings['evasys']->endtime) {
                return $templatedata;
            }
            $data = [
                'key' => 'evasys_qr',
                'value' => '<img src="' . s($settings->subpluginssettings['evasys']->qrurl)
                    . '" alt="' . get_string('evasysqrcode', 'bookingextension_evasys') . '" class="w-100">',
                'label' => 'evasys_qr_class',
                'description' => get_string('evasysqrcode', 'bookingextension_evasys'),
            ];
            $templatedata = [$data];
        }
        return $templatedata;
    }
    /**
     * Provides Data for the settings object.
     *
     * @param int $optionid
     *
     * @return object
     *
     */
    public static function load_data_for_settings_singleton(int $optionid): object {
        global $DB;
        return $DB->get_record('bookingextension_evasys', ['optionid' => $optionid], '*', IGNORE_MISSING) ?: (object)[];
    }

    /**
     * Adds Downloadbutton for EvasysQrCode to Option.
     *
     * @param object $settings
     * @param mixed $context
     *
     * @return string
     *
     */
    public static function add_options_to_col_actions(object $settings, mixed $context): string {
        $option = "";
        if (
            (
            has_capability('mod/booking:updatebooking', $context)
            || ((has_capability('mod/booking:addeditownoption', $context)))
            )
            && isset($settings->subpluginssettings['evasys']->qrurl)
        ) {
            $now = time();
            if ($now > $settings->subpluginssettings['evasys']->endtime) {
                return $option;
            }
            // First Option show link to qr-code.
            $url = $settings->subpluginssettings['evasys']->qrurl;
            $option = '<a href="' . $url . '" target="_blank" class="dropdown-item d-flex align-items-center">
            <i class="icon fa fa-qrcode fa-fw mr-2" aria-hidden="true""
                aria-label="' . get_string('evasysqrcode', 'bookingextension_evasys') . '"
                title="' . get_string('evasysqrcode', 'bookingextension_evasys') . '">
            </i>
            ' . get_string('evasysqrcode', 'bookingextension_evasys') . '
        </a>';
            // Second Option show the survey.
            $url = $settings->subpluginssettings['evasys']->surveyurl;
            $option .= '<a href="' . $url . '" target="_blank" " class="dropdown-item d-flex align-items-center">
            <i class="icon fas fa-file-alt fa-fw mr-2" aria-hidden="true"
                aria-label="' . get_string('evasyssurveyurl', 'bookingextension_evasys') . '"
                title="' . get_string('evasyssurveyurl', 'bookingextension_evasys') . '">
            </i>
            ' . get_string('evasyssurveyurl', 'bookingextension_evasys') . '
        </a>';
        }
        return $option;
    }

    /**
     * Loads plugin settings to the settings tree.
     *
     * @param \part_of_admin_tree $adminroot
     * @param string $parentnodename
     * @param bool $hassiteconfig whether the current user has moodle/site:config capability
     */
    public function load_settings(\part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig): void {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // In case settings.php wants to refer to them.
        // $ADMIN = $adminroot; // May be used in settings.php.
        // $plugininfo = $this; // Also can be used inside settings.php.

        $evasyssettings = new admin_settingpage(
            'bookingextension_evasys_settings',
            get_string('pluginname', 'bookingextension_evasys'),
            'moodle/site:config',
            $this->is_enabled() === false
        );
        $evasyssettings->add(
            new admin_setting_heading(
                'evasyssettings',
                get_string('evasyssettings', 'bookingextension_evasys'),
                get_string('evasyssettings_desc', 'bookingextension_evasys')
            )
        );

        set_config('evasyswsdl', 'https://evasys3.qs.univie.ac.at/evasys/services/soapserver-v91.wsdl', 'bookingextension_evasys');

        $evasyssettings->add(
            new admin_setting_configcheckbox(
                'bookingextension_evasys/useevasys',
                get_string('useevasys', 'bookingextension_evasys'),
                get_string('useevasys_desc', 'bookingextension_evasys'),
                0
            )
        );

        $evasyssettings->add(
            new admin_setting_configtext(
                'bookingextension_evasys/evasysbaseurl',
                get_string('evasysbaseurl', 'bookingextension_evasys'),
                get_string('evasysbaseurl_desc', 'bookingextension_evasys'),
                ''
            )
        );

        $evasyssettings->add(
            new admin_setting_configtext(
                'bookingextension_evasys/evasysuser',
                get_string('evasysuser', 'bookingextension_evasys'),
                get_string('evasysuser_desc', 'bookingextension_evasys'),
                ''
            )
        );
        $evasyssettings->add(
            new admin_setting_configpasswordunmask(
                'bookingextension_evasys/evasyspassword',
                get_string('evasyspassword', 'bookingextension_evasys'),
                get_string('evasyspassword_desc', 'bookingextension_evasys'),
                ''
            )
        );
        $userprofilefieldsarray[0] = get_string('userprofilefieldoff', 'mod_booking');
        $userprofilefields = profile_get_custom_fields();
        if (!empty($userprofilefields)) {
            $userprofilefieldsarray = [];
            // Create an array of key => value pairs for the dropdown.
            foreach ($userprofilefields as $userprofilefield) {
                $userprofilefieldsarray[$userprofilefield->shortname] = $userprofilefield->name;
            }
        }
        $evasyssettings->add(
            new admin_setting_configselect(
                'bookingextension_evasys/evasyscategoryfielduser',
                get_string('evasyscategoryfielduser', 'bookingextension_evasys'),
                get_string('evasyscategoryfielduser_desc', 'bookingextension_evasys'),
                'evasysid',
                $userprofilefieldsarray
            )
        );
        $roles = $DB->get_records('role', [], 'id', 'id, name');
        $rolesarray = [];
        foreach ($roles as $role) {
            $rolesarray[$role->id] = $role->name;
        }
        if (empty($rolesarray)) {
            $rolesarray[0] = get_string('nothingtoselect', 'bookingextension_evasys');
        }
        $evasyssettings->add(
            new admin_setting_configselect(
                'bookingextension_evasys/rolereportrecipients',
                get_string('rolereportrecipients', 'bookingextension_evasys'),
                get_string('rolereportrecipients_desc', 'bookingextension_evasys'),
                '',
                $rolesarray
            )
        );
        $evasyssettings->add(
            new admin_setting_configcheckbox(
                'bookingextension_evasys/includeqrinoptionview',
                get_string('includeqrinoptionview', 'bookingextension_evasys'),
                get_string('includeqrinoptionview_desc', 'bookingextension_evasys'),
                0,
            )
        );
        $customfields = booking_handler::get_customfields();
        $customfieldshortnames = ['' => ''];
        if (!empty($customfields)) {
            foreach ($customfields as $cf) {
                $customfieldshortnames[$cf->shortname] = format_string("$cf->name ($cf->shortname)");
            }
        }
        $evasyssettings->add(
            new admin_setting_configselect(
                'bookingextension_evasys/evasyscustomfield1',
                get_string('evasyscustomfield1', 'bookingextension_evasys'),
                get_string('evasyscustomfield1_desc', 'bookingextension_evasys'),
                '',
                $customfieldshortnames
            )
        );
         $evasyssettings->add(
             new admin_setting_configselect(
                 'bookingextension_evasys/evasyscustomfield2',
                 get_string('evasyscustomfield2', 'bookingextension_evasys'),
                 get_string('evasyscustomfield2_desc', 'bookingextension_evasys'),
                 '',
                 $customfieldshortnames
             )
         );
        $customoptions = [
            '' => '',
            'fullname' => get_string('fullname', 'mod_booking'),
        ];

        $evasyssettings->add(
            new admin_setting_configselect(
                'bookingextension_evasys/evasyscustomfield5',
                get_string('evasyscustomfield5', 'bookingextension_evasys'),
                get_string('evasyscustomfield5_desc', 'bookingextension_evasys'),
                '',
                $customoptions
            )
        );

        $evasys = new evasys_handler();
        $subunitoptions = $evasys->get_subunits();
        $periodoptions = $evasys->get_periods_for_settings();
        if (
            empty($subunitoptions)
        ) {
            $evasyssettings->add(
                new admin_setting_description(
                    'bookingextension_evasys/evasyssettingswarning',
                    '',
                    get_string('evasyssettingswarning', 'bookingextension_evasys')
                )
            );
        } else {
            if (empty($periodoptions)) {
                $periodoptions = [0 => get_string('nothingtoselect', 'bookingextension_evasys')];
            }
            $evasyssettings->add(
                new admin_setting_configselect(
                    'bookingextension_evasys/evasyssubunits',
                    get_string('evasyssubunits', 'bookingextension_evasys'),
                    get_string('evasyssubunits_desc', 'bookingextension_evasys'),
                    0,
                    $subunitoptions
                )
            );
            $evasyssettings->add(
                new admin_setting_configselect(
                    'bookingextension_evasys/evasysperiods',
                    get_string('evasysperiods', 'bookingextension_evasys'),
                    get_string('evasysperiods_desc', 'bookingextension_evasys'),
                    0,
                    $periodoptions
                )
            );
        }
        $adminroot->add('modbookingfolder', $evasyssettings);
    }
}
