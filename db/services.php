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
 * Web services for bookingextension_evasys.
 *
 * @package     bookingextension_evasys
 * @copyright   2025 Wunderbyte GmbH
 * @author      Bernhard Fischer-Sengseis
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

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
$services = [];
