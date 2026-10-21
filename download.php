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
 * Certificate download handler for block_download_certificates plugin.
 *
 * @package   block_download_certificates
 * @copyright 2015 Manieer Chhettri
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_download_certificates\local\download;
use block_download_certificates\local\filter;
use block_download_certificates\local\request;
use block_download_certificates\local\scope;

require_once(__DIR__ . '/../../config.php');

require_login(null, false);
request::reject_guest();
require_sesskey();

$action = optional_param('action', '', PARAM_ALPHANUMEXT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/blocks/download_certificates/download.php');

$scope = scope::for_user((int) $USER->id);
if (!in_array($action, ['download_all', 'downloadall', 'download_range'], true)) {
    redirect(request::panel_url($scope));
}

try {
    if ($action === 'download_range') {
        $type = 'range';
        [$datefrom, $dateto] = request::date_range(
            optional_param('start_date', '', PARAM_ALPHANUMEXT),
            optional_param('end_date', '', PARAM_ALPHANUMEXT)
        );
        $filter = filter::from_array(['datefrom' => $datefrom, 'dateto' => $dateto]);
    } else {
        $type = 'all';
        $filter = new filter();
    }
    request::check_zip($scope, $type, $filter);
    download::send_zip($scope, $filter, request::zip_filename($type, $filter));
} catch (moodle_exception $e) {
    request::redirect_on_error($scope, $e);
}
