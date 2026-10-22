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
 * Web service: queue a background ZIP download.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\external;

use block_download_certificates\event\zip_requested;
use block_download_certificates\local\request;
use block_download_certificates\local\scope;
use block_download_certificates\local\zip_tasks;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Check a ZIP request against the caller's scope, store it and queue its task, as
 * {@see zip_tasks::create()} does; the request names a type and a filter, never a scope.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_task extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHA, 'all, course, user, cohort or range'),
            'courseid' => new external_value(PARAM_INT, 'Course of a course request', VALUE_DEFAULT, 0),
            'groupid' => new external_value(PARAM_INT, 'Group narrowing a course request; 0 for none', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'Owner of a user request', VALUE_DEFAULT, 0),
            'cohortid' => new external_value(PARAM_INT, 'Cohort of a cohort request', VALUE_DEFAULT, 0),
            'startdate' => new external_value(PARAM_ALPHANUMEXT, 'First day of a range request, Y-m-d', VALUE_DEFAULT, ''),
            'enddate' => new external_value(PARAM_ALPHANUMEXT, 'Last day of a range request, Y-m-d', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Queue the download.
     *
     * @param string $type The request type.
     * @param int $courseid The course of a course request.
     * @param int $groupid The group narrowing a course request.
     * @param int $userid The owner of a user request.
     * @param int $cohortid The cohort of a cohort request.
     * @param string $startdate The first day of a range request.
     * @param string $enddate The last day of a range request.
     * @return array As {@see self::execute_returns()}.
     * @throws \moodle_exception `noguest`, as {@see request::filter_for_type()} or as {@see zip_tasks::create()}.
     */
    public static function execute(
        string $type,
        int $courseid = 0,
        int $groupid = 0,
        int $userid = 0,
        int $cohortid = 0,
        string $startdate = '',
        string $enddate = ''
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'type' => $type,
            'courseid' => $courseid,
            'groupid' => $groupid,
            'userid' => $userid,
            'cohortid' => $cohortid,
            'startdate' => $startdate,
            'enddate' => $enddate,
        ]);
        self::validate_context(\context_system::instance());
        request::reject_guest();

        $filter = request::filter_for_type($params['type'], $params);
        $task = zip_tasks::create(scope::for_user((int) $USER->id), $params['type'], $filter);

        // The event says what was requested: the cleaned filter's ids, and the days of a range.
        $other = array_filter([
            'courseid' => $filter->courseid,
            'groupid' => $filter->groupid,
            'cohortid' => $filter->cohortid,
            'startdate' => $params['type'] === 'range' ? $params['startdate'] : '',
            'enddate' => $params['type'] === 'range' ? $params['enddate'] : '',
        ]);
        zip_requested::create([
            'objectid' => $task->id,
            'context' => \context_user::instance((int) $USER->id),
            'relateduserid' => $params['type'] === 'user' ? $filter->userid : null,
            'other' => ['type' => $params['type']] + $other,
        ])->trigger();

        return ['taskid' => (int) $task->id, 'total' => (int) $task->total];
    }

    /**
     * The result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'taskid' => new external_value(PARAM_INT, 'Task id'),
            'total' => new external_value(PARAM_INT, 'Certificates the archive will hold'),
        ]);
    }
}
