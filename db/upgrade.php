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
 * Bookingextension Evasys database upgrade script
 *
 * @package     bookingextension_evasys
 * @copyright   2025 Wunderbyte GmbH
 * @author      David Ala
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Xmldb booking upgrade
 *
 * @param string $oldversion
 *
 * @return bool
 *
 */
function xmldb_bookingextension_evasys_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025061300) {
        // Define table bookingextension_evasys to be created.
        $table = new xmldb_table('bookingextension_evasys');
        // Adding fields to table bookingextension_evasys.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('optionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('formid', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('qrurl', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('surveyurl', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timemode', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('starttime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('endtime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('durationbeforestart', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('durationafterend', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('organizers', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('notifyparticipants', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('surveyid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('courseidexternal', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('courseidinternal', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('periods', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        // Adding keys to table bookingextension_evasys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for bookingextension_evasys.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // Booking savepoint reached.
        upgrade_plugin_savepoint(true, 2025061300, 'bookingextension', 'evasys');
    }
    return true;
}
