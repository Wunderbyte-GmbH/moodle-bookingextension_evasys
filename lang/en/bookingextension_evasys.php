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
 * This file contains language strings for the subplugin.
 *
 * @package     bookingextension_evasys
 * @copyright   2025 Wunderbyte GmbH
 * @author      Bernhard Fischer-Sengseis
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['closesurvey'] = 'bookingextension_evays: close survey for data collection (adhoc task)';
$string['conditionselectorganizersinbo_desc'] = 'Select Additional report recipients from a Bookingoption';
$string['confirmdelete'] = 'Confirm deletion';
$string['datepast'] = 'The start time of the evaluation cannot be in the past.';
$string['delete'] = 'Do you want to delete all data for the evaluation in EvaSys?';
$string['evaluationdurationafterend'] = 'End of evaluation period (hours after event end)';
$string['evaluationdurationbeforestart'] = 'Start of evaluation period (hours before event start)';
$string['evasys'] = 'EvaSys';
$string['evasysbaseurl'] = 'Base URL for EvaSys connection';
$string['evasysbaseurl_desc'] = 'This URL is used to connect to EvaSys.';
$string['evasyscategoryfieldoption'] = 'Custom field for EvaSys course ID';
$string['evasyscategoryfieldoption_desc'] = 'Select the custom field where the EvaSys ID will be stored for each booking option.';
$string['evasyscategoryfielduser'] = 'User profile field for EvaSys ID';
$string['evasyscategoryfielduser_desc'] = 'Select the user profile field where the EvaSys ID is stored for each user.';
$string['evasyscustomfield1'] = 'EvaSys course customfield Slot 1';
$string['evasyscustomfield1_desc'] = 'Select a bookingoption customfield as evasys course customfield';
$string['evasyscustomfield2'] = 'EvaSys course customfield Slot 2';
$string['evasyscustomfield2_desc'] = 'Select a bookingoption customfield as evasys course customfield';
$string['evasyscustomfield3'] = 'EvaSys course customfield Slot 3';
$string['evasyscustomfield3_desc'] = 'Select a bookingoption customfield as evasys course customfield';
$string['evasyscustomfield4'] = 'EvaSys course customfield Slot 4';
$string['evasyscustomfield4_desc'] = 'Select a bookingoption customfield as evasys course customfield';
$string['evasyscustomfield5'] = 'EvaSys course customfield Slot 5';
$string['evasyscustomfield5_desc'] = 'Select the data of secondary teachers as an EvaSys course customfield';
$string['evasysdescription'] = 'Evaluations will only be created once trainers have been assigned and all fields have been filled in after selecting the questionnaire';
$string['evasysevaluationendtime'] = 'End of evaluation period';
$string['evasysevaluationstarttime'] = 'Start of evaluation period';
$string['evasysheader'] = 'Evaluation';
$string['evasyslinkforqr'] = 'Link to the EvaSys survey QR code';
$string['evasysnotreachable'] = 'The EvaSys server is not reachable.';
$string['evasyspassword'] = 'Password';
$string['evasyspassword_desc'] = 'Password for the EvaSys connection';
$string['evasysperiods'] = 'Periods';
$string['evasysperiods_desc'] = 'Select the periods used for EvaSys.';
$string['evasysqrcode'] = 'EvaSys Survey QR code';
$string['evasyssettings'] = 'EvaSys <span class="badge bg-success text-light"><i class="fa fa-cogs" aria-hidden="true"></i> PRO</span>';
$string['evasyssettings_desc'] = 'Settings for EvaSys';
$string['evasyssettingswarning'] = '<div class="alert alert-danger" role="alert">Connection to EvaSys could not be established. Please check your username and password.</div>';
$string['evasyssubunits'] = 'Subunits';
$string['evasyssubunits_desc'] = 'Select the subunits for the evaluation in EvaSys.';
$string['evasyssurveycreated'] = 'EvaSys survey created';
$string['evasyssurveycreated_desc'] = 'A survey has been generated for the booking option with the ID: {$a->optionid}';
$string['evasyssurveylink'] = 'Link to survey';
$string['evasyssurveyurl'] = 'EvaSys Survey';
$string['evasysuser'] = 'Username';
$string['evasysuser_desc'] = 'Username for the EvaSys connection';
$string['fieldsvalidation'] = 'Not all required fields have been selected.';
$string['includeqrinoptionview'] = 'Show evaluation QR code in booking description';
$string['includeqrinoptionview_desc'] = 'Displays the QR code for the EvaSys evaluation in the booking option description. Admins, teachers and booked users can see the code.';
$string['nothingtoselect'] = 'Nothing to select';
$string['notifyparticipants'] = 'Optional notifications to participants';
$string['opensurvey'] = 'bookingextension_evays: open survey for data collection (adhoc task)';
$string['otherreportrecipients'] = 'Additional report recipients';
$string['pluginname'] = 'Booking extension: EvaSys';
$string['questionaire'] = 'Select questionnaire';
$string['questionaire_help'] = 'For more information about the forms, please click <a href="https://www.qs.univie.ac.at/evaluationen/weitere-evaluationen/urise/" target="_blank">here</a>.';
$string['rolereportrecipients'] = 'Role restriction for additional report recipients';
$string['rolereportrecipients_desc'] = 'Define the role restriction for additional report recipients.';
$string['rulechoosemode'] = 'Activate on: ';
$string['ruleevasysevaluationtime'] = 'EvaSys Participants Rule';
$string['ruleevasysevaluationtime_desc'] = 'Choose whether the rule should be triggered at the start time or the end time of the evaluation.';
$string['selectorganizersinbo'] = 'Choose EvaSys additional report recipients from a Bookingoption';
$string['sendtoapi'] = 'bookingextension_evays: send to api (adhoc task)';
$string['setcourseendtime'] = 'When using this time mode, a course end time in the future must be set.';
$string['timemode'] = 'Time mode';
$string['timemodeduration'] = 'Define evaluation period based on course end time';
$string['timemodestart'] = 'Define evaluation period using fixed start and end dates';
$string['useevasys'] = 'Use EvaSys evaluation';
$string['useevasys_desc'] = 'Select the user profile field that stores the EvaSys ID for each user.';
