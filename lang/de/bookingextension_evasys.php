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
$string['conditionselectorganizersinbo_desc'] = 'Wählen Sie zusätzliche Berichtsempfänger:innen aus einer Buchungsoption aus.';
$string['confirmdelete'] = 'Löschen bestätigen';
$string['datepast'] = 'Der Startzeitpunkt der Evaluation darf nicht in der Vergangenheit liegen.';
$string['delete'] = 'Wollen Sie alle Daten für die Evaluation in EvaSys löschen?';
$string['evaluationdurationafterend'] = 'Ende (Stunden nach Kursendzeit)';
$string['evaluationdurationbeforestart'] = 'Beginn (Stunden vor Kursendzeit)';
$string['evasys'] = 'EvaSys';
$string['evasysbaseurl'] = 'Basis-URL für EvaSys-Verbindung';
$string['evasysbaseurl_desc'] = 'Die URL wird für die Verbindung zu EvaSys verwendet.';
$string['evasyscategoryfieldoption'] = 'Customfield für die EvaSys-Kurs-ID';
$string['evasyscategoryfieldoption_desc'] = 'Wählen Sie ein Customfield aus, in dem für jede Buchungsoption die EvaSys-ID gespeichert wird.';
$string['evasyscategoryfielduser'] = 'Nutzerprofilfeld für die EvaSys-ID';
$string['evasyscategoryfielduser_desc'] = 'Wählen Sie ein Nutzerprofilfeld aus, in dem für jede:n Nutzer:in die EvaSys-ID gespeichert wird.';
$string['evasyscustomfield1'] = 'EvaSys Kurs benutzerdefiniertes Feld Slot 1';
$string['evasyscustomfield1_desc'] = 'Benutzerdefiniertes Buchungsoptionsfeld auswählen als EvaSys Kurs benutzerdefiniertes Feld';
$string['evasyscustomfield2'] = 'EvaSys Kurs benutzerdefiniertes Feld Slot 2';
$string['evasyscustomfield2_desc'] = 'Benutzerdefiniertes Buchungsoptionsfeld auswählen als EvaSys Kurs benutzerdefiniertes Feld';
$string['evasyscustomfield3'] = 'EvaSys Kurs benutzerdefiniertes Feld Slot 3';
$string['evasyscustomfield3_desc'] = 'Benutzerdefiniertes Buchungsoptionsfeld auswählen als EvaSys Kurs benutzerdefiniertes Feld';
$string['evasyscustomfield4'] = 'EvaSys Kurs benutzerdefiniertes Feld Slot 4';
$string['evasyscustomfield4_desc'] = 'Benutzerdefiniertes Buchungsoptionsfeld auswählen als EvaSys Kurs benutzerdefiniertes Feld';
$string['evasyscustomfield5'] = 'EvaSys Kurs benutzerdefiniertes Feld Slot 5';
$string['evasyscustomfield5_desc'] = 'Daten der zusätzliche Trainer:innen auswählen als EvaSys Kurs benutzerdefiniertes Feld';
$string['evasysevaluationendtime'] = 'Ende des Evaluierungszeitraums';
$string['evasysevaluationstarttime'] = 'Beginn des Evaluierungszeitraums';
$string['evasysheader'] = 'EvaSys';
$string['evasyslinkforqr'] = 'Link zum QR-Code der EvaSys Umfrage';
$string['evasysnotreachable'] = 'Der EvaSys-Server ist nicht erreichbar.';
$string['evasyspassword'] = 'Passwort';
$string['evasyspassword_desc'] = 'Passwort für die EvaSys-Verbindung';
$string['evasysperiods'] = 'Semester';
$string['evasysperiods_desc'] = 'Wählen Sie Semester für EvaSys aus.';
$string['evasysqrcode'] = 'QR-Code der EvaSys Umfrage';
$string['evasyssettings'] = 'EvaSys <span class="badge bg-success text-light"><i class="fa fa-cogs" aria-hidden="true"></i> PRO</span>';
$string['evasyssettings_desc'] = 'Einstellungen für EvaSys';
$string['evasyssettingswarning'] = '<div class="alert alert-danger" role="alert">Verbindung zu EvaSys konnte nicht hergestellt werden. Überprüfen Sie Benutzername und Passwort.</div>';
$string['evasyssubunits'] = 'Untereinheiten';
$string['evasyssubunits_desc'] = 'Wählen Sie die Untereinheiten für die Evaluation aus.';
$string['evasyssurveycreated'] = 'EvaSys Umfrage erstellt';
$string['evasyssurveycreated_desc'] = 'Eine Umfrage für die Buchungsoption mit der ID: {$a->optionid} wurde erstellt';
$string['evasyssurveylink'] = 'Link zur Umfrage';
$string['evasyssurveyurl'] = 'EvaSys Umfrage';
$string['evasysuser'] = 'Benutzername';
$string['evasysuser_desc'] = 'Benutzername für die EvaSys-Verbindung';
$string['fieldsvalidation'] = 'Nicht alle Pflichtfelder wurden ausgewählt.';
$string['includeqrinoptionview'] = 'QR-Code der Evaluation in Buchungsbeschreibung anzeigen';
$string['includeqrinoptionview_desc'] = 'Zeigt den QR-Code zur EvaSys-Evaluation in der Buchungsbeschreibung an. „Admins, Trainer:innen und gebuchte Benutzer:innen können den Code sehen.“';
$string['nothingtoselect'] = 'Keine Auswahl möglich';
$string['notifyparticipants'] = 'Optionale Benachrichtigungen an Teilnehmer:innen';
$string['opensurvey'] = 'bookingextension_evays: open survey for data collection (adhoc task)';
$string['otherreportrecipients'] = 'Weitere Berichtsempfänger:innen (Sekundärdozent:innen in EvaSys)';
$string['pluginname'] = 'Booking-Erweiterung: EvaSys';
$string['questionaire'] = 'Auswahl des Fragebogens';
$string['questionaire_help'] = 'Mehr Infos zu den Formularen finden Sie <a href="https://www.qs.univie.ac.at/evaluationen/weitere-evaluationen/urise/" target="_blank">hier</a>.';
$string['rolereportrecipients'] = 'Rolleneinschränkung für weitere Berichtsempfänger:innen';
$string['rolereportrecipients_desc'] = 'Definieren Sie die Rolleneinschränkung für weitere Berichtsempfänger:innen.';
$string['rulechoosemode'] = 'Aktivierungszeitpunkt: ';
$string['ruleevasysevaluationtime'] = 'EvaSys Regel für Teilnehmer:innen';
$string['ruleevasysevaluationtime_desc'] = 'Wählen Sie, ob die Regel bei der Startzeit oder der Endzeit der Evaluation ausgelöst werden soll.';
$string['selectorganizersinbo'] = 'Wähle EvaSys weitere Berichtsempfänger:innen';
$string['sendtoapi'] = 'bookingextension_evays: send to api (adhoc task)';
$string['setcourseendtime'] = 'Bei Auswahl dieses Zeitmodus muss ein Kursende festgelegt werden, das in der Zukunft liegt.';
$string['timemode'] = 'Auswahl des Evaluierungszeitraums';
$string['timemodeduration'] = 'Evaluierungszeitraum anhand der Kursendzeit definieren';
$string['timemodestart'] = 'Evaluierungszeitraum mit Start- und Enddatum definieren';
$string['useevasys'] = 'EvaSys Evaluation verwenden';
$string['useevasys_desc'] = 'EvaSys Evaluation kann verwendet werden. ';
