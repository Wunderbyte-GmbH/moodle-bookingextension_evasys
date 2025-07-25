<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace bookingextension_evasys\external;
use external_api;
use external_function_parameters;
use external_value;
use bookingextension_evasys\local\evasys_handler;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Provides the get_evasysperiods external function.
 *
 * @package     bookingextension_evasys
 * @category    external
 * @author David Ala
 * @copyright 2025 Wunderbyte GmbH
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_evasysperiods extends external_api {
    /**
     * Describes the external function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {

        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'The search query', VALUE_REQUIRED),
        ]);
    }

    /**
     * Finds entities with the name matching the given query.
     *
     * @param string $query The search request.
     * @return array
     */
    public static function execute(string $query): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
        ]);
        $evasys = new evasys_handler();
        return $evasys->get_periods_for_query($params['query']);
    }

    /**
     * Describes the external function result value.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {

        return new \external_single_structure([
            'list' => new \external_multiple_structure(
                new \external_single_structure([
                    'id' => new external_value(PARAM_TEXT, 'ID with Base64 encoded Name'),
                    'name' => new external_value(PARAM_TEXT, 'Name of the period'),
                ])
            ),
            'warnings' => new external_value(PARAM_TEXT, 'Warnings'),
        ]);
    }
}
