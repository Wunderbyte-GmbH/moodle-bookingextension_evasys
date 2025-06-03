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
 * @author      Bernhard Fischer-Sengseis
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookingextension_evasys;

use admin_settingpage;
use mod_booking\plugininfo\bookingextension;
use mod_booking\plugininfo\bookingextension_interface;

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
            'xtensiontestfield' => [
                'name' => 'xtensiontestfield',
                'class' => 'bookingextension_evasys\option\fields\xtensiontestfield',
                'id' => MOD_BOOKING_OPTION_FIELD_EVASYSTESTFIELD,
            ],
            // We can add more fields here...
        ];
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

        $adminroot->add('modbookingfolder', $evasyssettings);
    }
}
