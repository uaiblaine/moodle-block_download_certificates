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
 * Web service: the state of a background ZIP download.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\external;

use block_download_certificates\local\request;
use block_download_certificates\local\zip_tasks;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * The state of one of the caller's own background downloads, as {@see zip_tasks::get_status()}
 * reports it.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_task_status extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'taskid' => new external_value(PARAM_INT, 'Task id'),
        ]);
    }

    /**
     * The state.
     *
     * @param int $taskid The task.
     * @return array As {@see self::execute_returns()}.
     * @throws \moodle_exception `noguest`, `tasknotfound`, or `accessdenied` for another user's task.
     */
    public static function execute(int $taskid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['taskid' => $taskid]);
        self::validate_context(\context_system::instance());
        request::reject_guest();

        return zip_tasks::get_status($params['taskid'], (int) $USER->id);
    }

    /**
     * The result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'taskid' => new external_value(PARAM_INT, 'Task id'),
            'type' => new external_value(PARAM_ALPHA, 'Request type: all, course, user, cohort or range'),
            'status' => new external_value(PARAM_ALPHA, 'pending, processing, ready, failed or expired'),
            'progress' => new external_value(PARAM_INT, 'Certificates added so far'),
            'total' => new external_value(PARAM_INT, 'Certificates to add'),
            'downloadurl' => new external_value(PARAM_URL, 'Download script when ready; it takes taskid and sesskey by POST'),
            'errormessage' => new external_value(PARAM_TEXT, 'Localised reason when failed; else empty'),
        ]);
    }
}
