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

namespace bookingextension_evasys;

use bookingextension_evasys\local\evasys_handler;
use mod_booking\singleton_service;


/**
 * Event observers.
 *
 * @package   bookingextension_evasys
 * @copyright 2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @author    David Ala
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Function that listens on survey created event.
     *
     * @return void
     *
     */
    public static function survey_created() {
        return;
    }

    /**
     * [Description for bookingoption_created]
     *
     * @param \mod_booking\event\bookingoption_cancelled $event
     *
     * @return void
     *
     */
    public static function bookingoption_cancelled(\mod_booking\event\bookingoption_cancelled $event) {
        $optionid = $event->objectid;
        $settings = singleton_service::get_instance_of_booking_option_settings($optionid);
        if (!isset($settings->subpluginssettings['evasys']->surveyid)) {
            return;
        }
        $surveyid = $settings->subpluginssettings['evasys']->surveyid;
        $id = $settings->subpluginssettings['evasys']->id;
        $internalid = $settings->subpluginssettings['evasys']->courseidinternal;
        $handler = new evasys_handler();
        $handler->delete_survey($surveyid);
        $handler->delete_course($internalid, $id);
    }

    /**
     * Deletes survey and course in EvaSys when the booking option is deleted.
     *
     * The option record is already gone when this event fires, so we read the
     * EvaSys record directly from the DB instead of the settings singleton.
     *
     * @param \mod_booking\event\bookingoption_deleted $event
     *
     * @return void
     *
     */
    public static function bookingoption_deleted(\mod_booking\event\bookingoption_deleted $event) {
        global $DB;
        $optionid = $event->objectid;
        $record = $DB->get_record('bookingextension_evasys', ['optionid' => $optionid]);
        if (empty($record) || empty($record->surveyid)) {
            return;
        }
        $handler = new evasys_handler();
        $handler->delete_survey((int) $record->surveyid);
        $handler->delete_course((int) $record->courseidinternal, (int) $record->id);
    }
}
