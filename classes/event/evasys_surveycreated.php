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
 * The evasys_surveycreated event.
 *
 * @package bookingextension_evasys
 * @copyright 2024 Wunderbyte
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookingextension_evasys\event;

use stdClass;


/**
 * The evasys_surveycreated event class.
 * @package bookingextension_evasys
 * @author David Ala
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evasys_surveycreated extends \core\event\base {
    /**
     * Init
     *
     * @return void
     *
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'bookingextension_evasys';
    }

    /**
     * Get name
     *
     * @return string
     *
     */
    public static function get_name() {
        return get_string('evasyssurveycreated', 'bookingextension_evasys');
    }

    /**
     * Get description
     *
     * @return string
     *
     */
    public function get_description() {
        $data = (object) [
            'context' => $this->context,
            'objectid' => $this->objectid,
        ];
        $a = new stdClass();
        $a->optionid = $data->objectid;
        return get_string('evasyssurveycreated_desc', 'bookingextension_evasys', $a);
    }
}
