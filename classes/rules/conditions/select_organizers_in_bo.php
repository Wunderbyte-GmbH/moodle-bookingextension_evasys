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

namespace bookingextension_evasys\rules\conditions;

use mod_booking\booking_rules\booking_rule_condition;
use MoodleQuickForm;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Condition how to identify organizers in from Bookingoption.
 *
 * @package bookingextension_evasys
 * @author David Ala
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class select_organizers_in_bo implements booking_rule_condition {
    /** @var string $rulename */
    public $conditionname = 'select_organizers_in_bo';

    /** @var string $conditionnamestringid Id of localized string for name of rule condition*/
    protected $conditionnamestringid = 'selectorganizersinbo';

    /** @var string $rulejson a json string for a booking rule */
    public $rulejson = '';

    /**
     * Function to tell if a condition can be combined with a certain booking rule type.
     * @param string $bookingruletype e.g. "rule_daysbefore" or "rule_react_on_event"
     * @return bool true if it can be combined
     */
    public function can_be_combined_with_bookingruletype(string $bookingruletype): bool {
        // This condition can currently be combined with any rule.
        return true;
    }

    /**
     * Load json data from DB into the object.
     * @param stdClass $record a rule condition record from DB
     */
    public function set_conditiondata(stdClass $record) {
        $this->set_conditiondata_from_json($record->rulejson);
    }

    /**
     * Load data directly from JSON.
     * @param string $json a json string for a booking rule
     */
    public function set_conditiondata_from_json(string $json) {
        $this->rulejson = $json;
    }

    /**
     * Only customizable functions need to return their necessary form elements.
     *
     * @param MoodleQuickForm $mform
     * @param ?array $ajaxformdata
     * @return void
     */
    public function add_condition_to_mform(MoodleQuickForm &$mform, ?array &$ajaxformdata = null) {
        $mform->addElement(
            'static',
            'condition_select_organizers_in_bo',
            '',
            get_string('conditionselectorganizersinbo_desc', 'bookingextension_evasys')
        );
    }

    /**
     * Get the name of the rule.
     *
     * @param bool $localized
     * @return string the name of the rule
     */
    public function get_name_of_condition($localized = true) {
        return $localized ? get_string($this->conditionnamestringid, 'bookingextension_evasys') : $this->conditionname;
    }

    /**
     * Save the JSON for all sendmail_daysbefore rules defined in form.
     * @param stdClass $data form data reference
     */
    public function save_condition(stdClass &$data): void {
        global $DB;

        if (!isset($data->rulejson)) {
            $jsonobject = new stdClass();
        } else {
            $jsonobject = json_decode($data->rulejson);
        }

        $jsonobject->conditionname = $this->conditionname;
        $jsonobject->conditioncomponent = 'bookingextension_evasys';

        $data->rulejson = json_encode($jsonobject);
    }

    /**
     * Sets the rule defaults when loading the form.
     * @param stdClass $data reference to the default values
     * @param stdClass $record a record from booking_rules
     */
    public function set_defaults(stdClass &$data, stdClass $record) {

        $data->bookingruleconditiontype = $this->conditionname;
    }

    /**
     * Execute the condition.
     * We receive an array of stdclasses with the keys optinid & cmid.
     * @param stdClass $sql
     * @param array $params
     */
    public function execute(stdClass &$sql, array &$params): void {

        global $DB;
        $sql->select = $sql->select . ",boz.optionid, u.id";
        $sql->from .= " JOIN {bookingextension_evasys} boz ON boz.optionid = bo.id";
        $sql->from .= " JOIN m_user u on CONCAT(',', boz.organizers, ',') LIKE CONCAT('%,', u.id, ',%')";
        $concat = $DB->sql_concat("bo.id", "'-'", "u.id");
        $sql->select = " $concat AS uniqueid, " . $sql->select;
        $sql->select .= ", u.id::int AS userid";
    }
}
