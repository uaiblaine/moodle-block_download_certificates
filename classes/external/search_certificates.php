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
 * Web service: one page of the certificates table.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\external;

use block_download_certificates\local\filter;
use block_download_certificates\local\request;
use block_download_certificates\local\scope;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * One page of the certificates the caller's scope reaches, for the manager and teacher pages.
 *
 * The filter fields only narrow: the scope built from the caller decides what may be seen. A cohort
 * is not among them, because a cohort narrows by membership the caller may not be allowed to list.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_certificates extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Text matched against owner, course, template and code', VALUE_DEFAULT, ''),
            'courseid' => new external_value(PARAM_INT, 'Keep this course only; 0 for any', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'Keep this owner only; 0 for any', VALUE_DEFAULT, 0),
            'groupid' => new external_value(PARAM_INT, 'Keep the members of this group only; 0 for any', VALUE_DEFAULT, 0),
            'datefrom' => new external_value(PARAM_INT, 'Issued at or after this time; 0 for open', VALUE_DEFAULT, 0),
            'dateto' => new external_value(PARAM_INT, 'Issued before this time; 0 for open', VALUE_DEFAULT, 0),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Sort key', VALUE_DEFAULT, 'timecreated'),
            'order' => new external_value(PARAM_ALPHA, 'ASC or DESC', VALUE_DEFAULT, 'DESC'),
            'page' => new external_value(PARAM_INT, 'Page, from 0', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per page, kept between 10 and 200', VALUE_DEFAULT, 50),
        ]);
    }

    /**
     * One page of certificates.
     *
     * @param string $search Text matched against owner, course, template and code.
     * @param int $courseid Keep this course only.
     * @param int $userid Keep this owner only.
     * @param int $groupid Keep the members of this group only.
     * @param int $datefrom Issued at or after this time.
     * @param int $dateto Issued before this time.
     * @param string $sort The sort key.
     * @param string $order `ASC` or `DESC`.
     * @param int $page The page, from 0.
     * @param int $perpage Rows per page.
     * @return array As {@see self::execute_returns()}.
     * @throws \moodle_exception `noguest`, or `accessdenied` for a caller who opens no panel.
     */
    public static function execute(
        string $search = '',
        int $courseid = 0,
        int $userid = 0,
        int $groupid = 0,
        int $datefrom = 0,
        int $dateto = 0,
        string $sort = 'timecreated',
        string $order = 'DESC',
        int $page = 0,
        int $perpage = 50
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'courseid' => $courseid,
            'userid' => $userid,
            'groupid' => $groupid,
            'datefrom' => $datefrom,
            'dateto' => $dateto,
            'sort' => $sort,
            'order' => $order,
            'page' => $page,
            'perpage' => $perpage,
        ]);
        self::validate_context(\context_system::instance());
        request::reject_guest();

        $scope = scope::for_user((int) $USER->id);
        if (!request::can_open_panel($scope)) {
            throw new \moodle_exception('accessdenied', 'block_download_certificates');
        }
        $filter = new filter(
            courseid: $params['courseid'],
            userid: $params['userid'],
            groupid: $params['groupid'],
            datefrom: $params['datefrom'],
            dateto: $params['dateto'],
            search: $params['search']
        );
        return request::search_page(
            $scope,
            $filter,
            $params['page'],
            $params['perpage'],
            $params['sort'],
            $params['order']
        );
    }

    /**
     * The result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'rows' => new external_multiple_structure(
                new external_single_structure([
                    'uniqueid' => new external_value(PARAM_ALPHANUMEXT, 'Row id, unique across sources'),
                    'type' => new external_value(PARAM_ALPHANUMEXT, 'Certificate source type'),
                    'typename' => new external_value(PARAM_TEXT, 'Certificate source name'),
                    'userid' => new external_value(PARAM_INT, 'Owner id'),
                    'fullname' => new external_value(PARAM_TEXT, 'Owner full name, not escaped'),
                    'email' => new external_value(PARAM_TEXT, 'Owner e-mail'),
                    'coursename' => new external_value(PARAM_TEXT, 'Course name, not escaped'),
                    'templatename' => new external_value(PARAM_TEXT, 'Certificate name, not escaped'),
                    'code' => new external_value(PARAM_TEXT, 'Certificate code'),
                    'timecreated' => new external_value(PARAM_INT, 'Issue time'),
                    'datecreated' => new external_value(PARAM_TEXT, 'Issue time, formatted for the user'),
                    'downloadurl' => new external_value(PARAM_URL, 'Single download, without session key'),
                ])
            ),
            'total' => new external_value(PARAM_INT, 'Rows matching, across pages'),
            'page' => new external_value(PARAM_INT, 'The page returned, from 0'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page, as applied'),
            'pages' => new external_value(PARAM_INT, 'Number of pages'),
        ]);
    }
}
