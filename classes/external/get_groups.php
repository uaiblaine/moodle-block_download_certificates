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
 * Web service: the groups a course download may be narrowed to.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\external;

use block_download_certificates\local\request;
use block_download_certificates\local\scope;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * The groups of one of the caller's courses, as {@see request::course_groups()} lists them.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_groups extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * The groups.
     *
     * @param int $courseid The course.
     * @return array As {@see self::execute_returns()}.
     * @throws \moodle_exception `noguest`, or `accessdenied` for a course outside the caller's courses.
     */
    public static function execute(int $courseid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        self::validate_context(\context_system::instance());
        request::reject_guest();

        return ['groups' => request::course_groups(scope::for_user((int) $USER->id), $params['courseid'])];
    }

    /**
     * The result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'groups' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Group id'),
                    'name' => new external_value(PARAM_TEXT, 'Group name, not escaped'),
                ])
            ),
        ]);
    }
}
