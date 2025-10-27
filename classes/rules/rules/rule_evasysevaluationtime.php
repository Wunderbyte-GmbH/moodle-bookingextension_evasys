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

namespace bookingextension_evasys\rules\rules;

use context;
use mod_booking\booking_rules\actions_info;
use mod_booking\booking_rules\booking_rule;
use mod_booking\booking_rules\conditions_info;
use mod_booking\option\fields\applybookingrules;
use MoodleQuickForm;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Rule to do something on the EvaSys evaluation start- or endtime.
 *
 * @package bookingextension_evasys
 * @author David Ala
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_evasysevaluationtime implements booking_rule {
    /** @var string $rulename */
    protected $rulename = 'rule_evasysevaluationtime';

    /** @var string $rulenamestringid ID of localized string for name of rule */
    protected $rulenamestringid = 'ruleevasysevaluationtime';

    /** @var int $contextid */
    public $contextid = 1;

    /** @var string $name */
    public $name = null;

    /** @var string $rulejson */
    public $rulejson = null;

    /** @var int $ruleid from database! */
    public $ruleid = null;

    /** @var string $mode */
    public $datefield = null;

    /** @var bool $ruleisactive */
    public $ruleisactive = true;


    /**
     * Load json data from DB into the object.
     * @param stdClass $record a rule record from DB
     */
    public function set_ruledata(stdClass $record) {
        $this->ruleid = $record->id ?? 0;
        $this->contextid = $record->contextid ?? 1;
        $this->ruleisactive = $record->isactive;
        $this->set_ruledata_from_json($record->rulejson);
    }

    /**
     * Load data directly from JSON.
     * @param string $json a json string for a booking rule
     */
    public function set_ruledata_from_json(string $json) {
        $this->rulejson = $json;
        $ruleobj = json_decode($json);
        $this->name = $ruleobj->name;
        $this->datefield = $ruleobj->ruledata->datefield;
    }

    /**
     * Only customizable functions need to return their necessary form elements.
     *
     * @param MoodleQuickForm $mform
     * @param array $repeateloptions
     * @param array $ajaxformdata
     * @return void
     */
    public function add_rule_to_mform(MoodleQuickForm &$mform, array &$repeateloptions, array $ajaxformdata = []) {
        global $DB;

        $mform->addElement(
            'static',
            'rule_evasysevaluationtime_desc',
            '',
            get_string('ruleevasysevaluationtime_desc', 'bookingextension_evasys')
        );

        $mform->addElement(
            'select',
            'datefield',
            get_string('rulechoosemode', 'bookingextension_evasys'),
            [
                'starttime' => get_string('evasysevaluationstarttime', 'bookingextension_evasys'),
                'endtime' => get_string('evasysevaluationendtime', 'bookingextension_evasys'),
                'courseendtime' => get_string('ruleoptionfieldcourseendtime', 'mod_booking'),
            ],
        );
    }

    /**
     * Get the name of the rule.
     * @param bool $localized
     * @return string the name of the rule
     */
    public function get_name_of_rule(bool $localized = true): string {
        return $localized ? get_string($this->rulenamestringid, 'bookingextension_evasys') : $this->rulename;
    }

    /**
     * Save the JSON for daysbefore rule defined in form.
     * The role has to determine the handler for condtion and action and get the right json object.
     * @param stdClass $data form data reference
     */
    public function save_rule(stdClass &$data) {
        global $DB;

        $record = new stdClass();

        if (!isset($data->rulejson)) {
            $jsonobject = new stdClass();
        } else {
            $jsonobject = json_decode($data->rulejson);
        }

        $jsonobject->name = $data->rule_name;
        $jsonobject->rulename = $this->rulename;
        $jsonobject->ruledata = new stdClass();
        $jsonobject->ruledata->datefield = $data->datefield;
        // We need to pass on the component of this rule, to correctly resolve strings.
        $jsonobject->ruledata->component = 'bookingextension_evasys';
        $record->rulejson = json_encode($jsonobject);
        $record->rulename = $this->rulename;
        $record->contextid = $data->contextid ?? 1;
        $record->isactive = $data->ruleisactive;

        // If we can update, we add the id here.
        if ($data->id ?? false) {
            $record->id = $data->id;
            $DB->update_record('booking_rules', $record);
        } else {
            $ruleid = $DB->insert_record('booking_rules', $record);
            $this->ruleid = $ruleid;
        }
    }

    /**
     * Sets the rule defaults when loading the form.
     * @param stdClass $data reference to the default values
     * @param stdClass $record a record from booking_rules
     */
    public function set_defaults(stdClass &$data, stdClass $record) {

        $data->bookingruletype = $this->rulename;

        $jsonobject = json_decode($record->rulejson);
        $ruledata = $jsonobject->ruledata;

        $data->rule_name = $jsonobject->name;
        $data->ruleisactive = $record->isactive;
        $data->rule_evasysevaluation = $ruledata->datefield;
    }

    /**
     * Execute the rule.
     * @param int $optionid optional
     * @param int $userid optional
     */
    public function execute(int $optionid = 0, int $userid = 0) {
        $jsonobject = json_decode($this->rulejson);

        if (!applybookingrules::apply_rule($optionid, $this->ruleid)) {
            return;
        }

        // We reuse this code when we check for validity, therefore we use a separate function.
        $records = $this->get_records_for_execution($optionid, $userid);

        // Now we finally execution the action, where we pass on every record.
        $action = actions_info::get_action($jsonobject->actionname);
        $action->set_actiondata_from_json($this->rulejson);
        // For the execution, we need a rule id, otherwise we can't test for consistency.
        $action->ruleid = $this->ruleid;

        foreach ($records as $record) {
            // The override happens within the SQL of get_records_for_execution.
            // So $record->daystonotify will have the correct value.
            // Set the time of when the task should run.
            $nextruntime = (int) $record->datefield;
            $record->rulename = $this->rulename;
            $record->nextruntime = $nextruntime;
            $action->execute($record);
        }
    }

    /**
     * This function is called on execution of adhoc tasks,
     * so we can see if the rule still applies and the adhoc task
     * shall really be executed.
     *
     * @param int $optionid
     * @param int $userid
     * @param int $nextruntime
     * @return bool true if the rule still applies, false if not
     */
    public function check_if_rule_still_applies(int $optionid, int $userid, int $nextruntime): bool {

        if (empty($this->ruleisactive)) {
            return false;
        }

        $rulestillapplies = true;

        if (!applybookingrules::apply_rule($optionid, $this->ruleid)) {
            return false;
        }

        // We retrieve the same sql we also use in the execute function.
        $records = $this->get_records_for_execution($optionid, $userid, true);

        if (empty($records)) {
            $rulestillapplies = false;
        }

        foreach ($records as $record) {
            $oldnextruntime = (int) $record->datefield;

            if ($oldnextruntime != $nextruntime) {
                $rulestillapplies = false;
                break;
            }
        }

        return $rulestillapplies;
    }

    /**
     * This helperfunction builds the sql with the help of the condition and returns the records.
     * Testmode means that we don't limit by now timestamp.
     *
     * @param int $optionid
     * @param int $userid
     * @param bool $testmode
     * @param int $nextruntime
     * @return array
     */
    public function get_records_for_execution(
        int $optionid = 0,
        int $userid = 0,
        bool $testmode = false,
        int $nextruntime = 0
    ) {
        global $DB;

        // Execution of a rule is a complex action.
        // Going from rule to condition to action...
        // ... we need to go into actions with an array of records...
        // ... which has the keys cmid, optionid & userid.

        $jsonobject = json_decode($this->rulejson);
        $ruledata = $jsonobject->ruledata;

        $andoptionid = "";
        $anduserid = "";

        $params = [
            'nowparam' => time(),
        ];

        if (!empty($optionid)) {
            $andoptionid = " AND bo.id = :optionid ";
            $params['optionid'] = $optionid;
        }

        // When we want to restrict the userid, we just pass on the param to the condition like this.
        if (!empty($userid)) {
            $params['userid'] = $userid;
        }

        // A rule might apply from the start only to a specific context. To check this, sql needs to take care of this.

        $context = context::instance_by_id($this->contextid);
        $path = $context->path;

        $params['path'] = "$path%";

        $sql = new stdClass();

        $sql->where = " c.path LIKE :path ";
        $sql->where .= " $andoptionid $anduserid ";

        // Initialize optiondates join.

        if ($ruledata->datefield == 'courseendtime') {
             $sql->select = "bo.id optionid, cm.id cmid, bo." . $ruledata->datefield . " datefield";
             $sql->where .= " AND bo." . $ruledata->datefield;
        } else {
            $sql->select = "bo.id optionid, cm.id cmid, bee." . $ruledata->datefield . " datefield";
            $sql->where .= " AND bee." . $ruledata->datefield;
        }
        // In testmode we don't check the timestamp. Add one hour of tolerance.
        $sql->where .= !$testmode ? " >= ( :nowparam - 3600)" : " IS NOT NULL ";

        // Make sure, cancelled options aren't fetched.
        $sql->where .= " AND bo.status < 1 AND bee.notifyparticipants > 0";

        $sql->from = "{booking_options} bo
                    JOIN {course_modules} cm
                    ON cm.instance = bo.bookingid
                    JOIN {modules} m
                    ON m.name = 'booking' AND m.id = cm.module
                    JOIN {context} c
                    ON c.instanceid = cm.id
                    JOIN {bookingextension_evasys} bee
                    ON bee.optionid = bo.id";

        // Now that we know the ids of the booking options concerend, we will determine the users concerned.
        // The condition execution will add their own code to the sql.

        $condition = conditions_info::get_condition($jsonobject->conditionname);

        $condition->set_conditiondata_from_json($this->rulejson);

        $condition->execute($sql, $params, $testmode, $nextruntime);

        $sql->select = " DISTINCT " . $sql->select; // Required to eliminate potential duplication in case inoptimal query.
        $sqlstring = "SELECT $sql->select FROM $sql->from WHERE $sql->where";

        $records = $DB->get_records_sql($sqlstring, $params);

        return $records;
    }
}
