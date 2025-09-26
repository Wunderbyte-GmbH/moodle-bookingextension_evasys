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
 * Evasys SOAP Service Class.
 *
 * @package bookingextension_evasys
 * @author David Ala
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookingextension_evasys\local;
use SoapClient;
use SoapFault;
use SoapHeader;
use mod_booking\event\booking_debug;
use context_system;


/**
 * Serviceclass to handle SOAP calls.
 */
class evasys_soap_service extends SoapClient {
    /**
     * URL of the Endpoint.
     *
     * @var string
     */
    private string $endpoint;
    /**
     * Username for Connection
     *
     * @var string
     */
    private string $username;
    /**
     * Password for Connection
     *
     * @var string
     */
    private string $password;

    /**
     * Wsdl Adress.
     *
     * @var string
     */
    private string $wsdl;

    /**
     * Constructor with parent constructor in it. Soapheader is used for authentication.
     *
     * @param string|null $endpoint
     * @param string|null $username
     * @param string|null $password
     * @param string|null $wsdl
     *
     */
    public function __construct(
        ?string $endpoint = null,
        ?string $username = null,
        ?string $password = null,
        ?string $wsdl = null
    ) {
        $this->endpoint = $endpoint ?? get_config('bookingextension_evasys', 'evasysbaseurl');
        $this->username = $username ?? get_config('bookingextension_evasys', 'evasysuser');
        $this->password = $password ?? get_config('bookingextension_evasys', 'evasyspassword');
        $this->wsdl = $wsdl ?? get_config('bookingextension_evasys', 'evasyswsdl');

        $options = [
            'trace'      => true,
            'exceptions' => true,
            'location'   => $this->endpoint,
        ];
        try {
            parent::__construct($this->wsdl, $options);
            $this->set_soap_header();
        } catch (SoapFault $e) {
            debugging('EvaSys SOAP connection failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Fetches subunits from EvaSys.
     *
     * @return object|null
     *
     */
    public function fetch_subunits() {
        try {
            $response = $this->__soapCall('GetSubunits', []);
            return $response;
        } catch (SoapFault $e) {
            return null;
        }
    }
    /**
     * Fetches periods from EvaSys.
     *
     * @return object|null
     *
     */
    public function fetch_periods() {
        try {
            $response = $this->__soapCall('GetAllPeriods', []);
            return $response;
        } catch (SoapFault $e) {
            if (get_config('booking', 'bookingdebugmode')) {
                // If debug mode is enabled, we create a debug message.
                $event = booking_debug::create([
                    'objectid' => $optionid ?? 0,
                    'context' => context_system::instance(),
                    'relateduserid' => $USER->id ?? 0,
                    'other' => [
                        'error' => $e,
                    ],
                ]);
                $event->trigger();
            }
            return null;
        }
    }

    /**
     * Get a Period by ID from Evasys.
     *
     * @param array $args
     *
     * @return object|null
     *
     */
    public function get_period(array $args) {
        try {
            $response = $this->__soapCall('GetPeriod', $args);
            return $response;
        } catch (SoapFault $e) {
            return null;
        }
    }

    /**
     * Fetches Forms from EvaSys.
     *
     * @param array $args
     *
     * @return object|null
     *
     */
    public function fetch_forms(array $args) {
        try {
            $response = $this->__soapCall('GetAllForms', $args);
            return $response;
        } catch (SoapFault $e) {
            return null;
        }
    }

    /**
     * Gets a Form by ID from EvaSys.
     *
     * @param array $args
     *
     * @return object|null
     *
     */
    public function get_form(array $args) {
        try {
            $response = $this->__soapCall('GetForm', $args);
            return $response;
        } catch (SoapFault $e) {
            if (get_config('booking', 'bookingdebugmode')) {
                // If debug mode is enabled, we create a debug message.
                $event = booking_debug::create([
                    'objectid' => $optionid ?? 0,
                    'context' => context_system::instance(),
                    'relateduserid' => $USER->id ?? 0,
                    'other' => [
                        'error' => $e,
                    ],
                ]);
                $event->trigger();
            }
            return null;
        }
    }

    /**
     *
     *
     * @param object $args
     *
     * @return object|null
     *
     */
    public function insert_user(object $args) {
        try {
            $response = $this->__soapCall('InsertUser', ['user' => $args]);
            return $response;
        } catch (SoapFault $e) {
            if (get_config('booking', 'bookingdebugmode')) {
                // If debug mode is enabled, we create a debug message.
                $event = booking_debug::create([
                    'objectid' => $optionid ?? 0,
                    'context' => context_system::instance(),
                    'relateduserid' => $USER->id ?? 0,
                    'other' => [
                        'error' => $e,
                    ],
                ]);
                $event->trigger();
            }
            return null;
        }
    }

    /**
     * Insert Course to EvaSys.
     *
     * @param array $args
     *
     * @return object|null
     *
     */
    public function insert_course(array $args) {
        try {
            $response = $this->__soapCall('InsertCourse', ['course' => $args]);
            return $response;
        } catch (SoapFault $e) {
            if (get_config('booking', 'bookingdebugmode')) {
                // If debug mode is enabled, we create a debug message.
                $event = booking_debug::create([
                    'objectid' => $optionid ?? 0,
                    'context' => context_system::instance(),
                    'relateduserid' => $USER->id ?? 0,
                    'other' => [
                        'error' => $e,
                    ],
                ]);
                $event->trigger();
            }
            return null;
        }
    }

    /**
     * Updates the Course to EvaSys.
     *
     * @param array $args
     *
     * @return object|null
     *
     */
    public function update_course(array $args) {
        try {
            $response = $this->__soapCall('UpdateCourse', ['course' => $args]);
            return $response;
        } catch (SoapFault $e) {
            if (get_config('booking', 'bookingdebugmode')) {
                // If debug mode is enabled, we create a debug message.
                $event = booking_debug::create([
                    'objectid' => $optionid ?? 0,
                    'context' => context_system::instance(),
                    'relateduserid' => $USER->id ?? 0,
                    'other' => [
                        'error' => $e,
                    ],
                ]);
                $event->trigger();
            }
            return null;
        }
    }

    /**
     * Deletes Course in EvaSys.
     *
     * @param array $args
     *
     * @return boolean
     *
     */
    public function delete_course(array $args) {
        try {
            $response = $this->__soapCall('DeleteCourse', $args);
            return $response;
        } catch (SoapFault $e) {
            if (get_config('booking', 'bookingdebugmode')) {
                // If debug mode is enabled, we create a debug message.
                $event = booking_debug::create([
                    'objectid' => $optionid ?? 0,
                    'context' => context_system::instance(),
                    'relateduserid' => $USER->id ?? 0,
                    'other' => [
                        'error' => $e,
                    ],
                ]);
                $event->trigger();
            }
            return false;
        }
    }

    /**
     * Insert Survey to EvaSys.
     *
     * @param array $args
     *
     * @return object|null
     *
     */
    public function insert_survey(array $args) {
        try {
            $response = $this->__soapCall('InsertCentralSurvey', $args);
            return $response;
        } catch (SoapFault $e) {
            if (get_config('booking', 'bookingdebugmode')) {
                // If debug mode is enabled, we create a debug message.
                $event = booking_debug::create([
                    'objectid' => $optionid ?? 0,
                    'context' => context_system::instance(),
                    'relateduserid' => $USER->id ?? 0,
                    'other' => [
                        'error' => $e,
                    ],
                ]);
                $event->trigger();
            }
            return null;
        }
    }

    /**
     * Delete the survey in EvaSys.
     *
     * @param array $args
     *
     * @return boolean
     *
     */
    public function delete_survey(array $args) {
        try {
            $response = $this->__soapCall('DeleteSurvey', $args);
            return $response;
        } catch (SoapFault $e) {
            if (get_config('booking', 'bookingdebugmode')) {
                // If debug mode is enabled, we create a debug message.
                $event = booking_debug::create([
                    'objectid' => $optionid ?? 0,
                    'context' => context_system::instance(),
                    'relateduserid' => $USER->id ?? 0,
                    'other' => [
                        'error' => $e,
                    ],
                ]);
                $event->trigger();
            }
            return false;
        }
    }

    /**
     * Get QR code from EvaSys.
     *
     * @param array $args
     *
     * @return string
     *
     */
    public function get_qr_code(array $args) {
        try {
            $response = $this->__soapCall('GetOnlineQRCode', $args);
            return $response;
        } catch (SoapFault $e) {
            if (get_config('booking', 'bookingdebugmode')) {
                // If debug mode is enabled, we create a debug message.
                $event = booking_debug::create([
                    'objectid' => $optionid ?? 0,
                    'context' => context_system::instance(),
                    'relateduserid' => $USER->id ?? 0,
                    'other' => [
                        'error' => $e,
                    ],
                ]);
                $event->trigger();
            }
            return "";
        }
    }

    /**
     * Opens Survey for Datacollection.
     *
     * @param array $args
     *
     * @return boolean
     *
     */
    public function open_survey(array $args) {
        try {
            $response = $this->__soapCall('OpenSurvey', $args);
            return $response;
        } catch (SoapFault $e) {
            if (get_config('booking', 'bookingdebugmode')) {
                // If debug mode is enabled, we create a debug message.
                $event = booking_debug::create([
                    'objectid' => $optionid ?? 0,
                    'context' => context_system::instance(),
                    'relateduserid' => $USER->id ?? 0,
                    'other' => [
                        'error' => $e,
                    ],
                ]);
                $event->trigger();
            }
            return false;
        }
    }
    /**
     * Closes the Datacollection for the survey.
     *
     * @param array $args
     *
     * @return boolean
     *
     */
    public function close_survey(array $args) {
        try {
            $response = $this->__soapCall('CloseSurvey', $args);
            return $response;
        } catch (SoapFault $e) {
            if (get_config('booking', 'bookingdebugmode')) {
                // If debug mode is enabled, we create a debug message.
                $event = booking_debug::create([
                    'objectid' => $optionid ?? 0,
                    'context' => context_system::instance(),
                    'relateduserid' => $USER->id ?? 0,
                    'other' => [
                        'error' => $e,
                    ],
                ]);
                $event->trigger();
            }
            return false;
        }
    }

    /**
     * This call is used to work around the soap API and get the surveyurl.
     *
     * @param array $args
     *
     * @return object|null
     *
     */
    public function get_surveyurl(array $args) {
        try {
            $response = $this->__soapCall('GetPswdsBySurvey', $args);
            return $response;
        } catch (SoapFault $e) {
            if (get_config('booking', 'bookingdebugmode')) {
                // If debug mode is enabled, we create a debug message.
                $event = booking_debug::create([
                    'objectid' => $optionid ?? 0,
                    'context' => context_system::instance(),
                    'relateduserid' => $USER->id ?? 0,
                    'other' => [
                        'error' => $e,
                    ],
                ]);
                $event->trigger();
            }
            return null;
        }
    }
    /**
     * Sets Soapheader for authentication.
     *
     * @return void
     *
     */
    private function set_soap_header() {
        $ns = 'soapserver-v91.wsdl';
        $headerbody = [
            'Ticket'   => '',
            'Login'    => $this->username,
            'Password' => $this->password,
        ];
        $header = new SoapHeader($ns, 'Header', $headerbody);
        $this->__setSoapHeaders($header);
    }
}
