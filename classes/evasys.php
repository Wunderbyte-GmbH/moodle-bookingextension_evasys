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
use mod_booking\plugininfo\bookingextension;
use mod_booking\plugininfo\bookingextension_interface;
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
     * Adds webservice functions to the service.
     *
     * This function usually includes settings.php file in plugins folder.
     * Alternatively it can create a link to some settings page (instance of admin_externalpage)
     *
     * @param array $functions
     * Reference to the array of functions to be registered with the web service.
     */
    public function register_booking_webservice_functions(array &$functions): void {
        $functions = [
            'bookingextension_evasys_get_evasysperiods' => [
                'classname'   => 'bookingextension_evasys\external\get_evasysperiods',
                'description' => 'Fetch list of Evasys periods based on search query.',
                'type' => 'read',
                'capabilities' => '',
                'ajax'        => 1,
            ],
            'bookingextension_evasys_get_evasysforms' => [
                'classname'   => 'bookingextension_evasys\external\get_evasysforms',
                'methodname'  => 'execute',
                'description' => 'Fetch list of Evasys questionaires based on search query.',
                'type'        => 'read',
                'capabilities' => '',
                'ajax'        => 1,
                ],
        ];
    }

    /**
     * [Description for load_data_for_settings_singleton]
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

        $evasyssettings->add(
            new admin_setting_configcheckbox(
                'booking/useevasys',
                get_string('useevasys', 'bookingextension_evasys'),
                get_string('useevasys_desc', 'bookingextension_evasys'),
                0
            )
        );

        $evasyssettings->add(
            new admin_setting_configtext(
                'booking/evasysbaseurl',
                get_string('evasysbaseurl', 'bookingextension_evasys'),
                get_string('evasysbaseurl_desc', 'bookingextension_evasys'),
                ''
            )
        );

        $evasyssettings->add(
            new admin_setting_configtext(
                'booking/evasysuser',
                get_string('evasysuser', 'bookingextension_evasys'),
                get_string('evasysuser_desc', 'bookingextension_evasys'),
                ''
            )
        );
        $evasyssettings->add(
            new admin_setting_configpasswordunmask(
                'booking/evasyspassword',
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
                'booking/evasyscategoryfielduser',
                get_string('evasyscategoryfielduser', 'bookingextension_evasys'),
                get_string('evasyscategoryfielduser_desc', 'bookingextension_evasys'),
                'evasysid',
                $userprofilefieldsarray
            )
        );
        $roles = $roles = $DB->get_records('role', [], 'id', 'id, name');
        $rolesarray = [];
        foreach ($roles as $role) {
            $rolesarray[$role->id] = $role->name;
        }
         $evasyssettings->add(
             new admin_setting_configselect(
                 'booking/rolereportrecipients',
                 get_string('rolereportrecipients', 'bookingextension_evasys'),
                 get_string('rolereportrecipients_desc', 'bookingextension_evasys'),
                 '',
                 $rolesarray
             )
         );
        try {
            $evasys = new evasys_handler();
            $subunitoptions = $evasys->get_subunits();
            $periodoptions = $evasys->get_periods_for_settings();
        } catch (SoapFault $e) {
                $subunitoptions = [0 => get_string('evasysnotreachable', 'bookingextension_evasys')];
        }
        if (
            empty($subunitoptions)
            && empty(get_config('booking', 'evasysuser'))
            && empty(get_config('booking', 'evasyspassword'))
        ) {
            $evasyssettings->add(
                new admin_setting_description(
                    'booking/evasyssettingswarning',
                    '',
                    get_string('evasyssettingswarning', 'bookingextension_evasys')
                )
            );
        } else {
            $evasyssettings->add(
                new admin_setting_configselect(
                    'booking/evasyssubunits',
                    get_string('evasyssubunits', 'bookingextension_evasys'),
                    get_string('evasyssubunits_desc', 'bookingextension_evasys'),
                    0,
                    $subunitoptions
                )
            );
            $evasyssettings->add(
                new admin_setting_configselect(
                    'booking/evasysperiods',
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
