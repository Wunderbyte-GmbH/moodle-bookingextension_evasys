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
 * Bookingextension Evasys database install script.
 *
 * @package     bookingextension_evasys
 * @copyright   2025 Wunderbyte GmbH
 * @author      David Ala
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * XMLDB Booking install function.
 * @return void
 */
function xmldb_bookingextension_evasys_install() {
    global $DB;

    require_once(__DIR__ . '/../classes/services/evasysuser_profile_field_initializer.php');

    \bookingextension_evasys\services\evasysuser_profile_field_initializer::ensure_evasyscustomfield_exists();
}
