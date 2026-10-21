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
 * Download one certificate after the scope check.
 *
 * A read like pluginfile.php: a GET link without a session key, refused outside the scope.
 *
 * @package   block_download_certificates
 * @copyright 2015 Manieer Chhettri
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_download_certificates\local\download;
use block_download_certificates\local\request;
use block_download_certificates\local\scope;

require_once(__DIR__ . '/../../config.php');

require_login(null, false);
request::reject_guest();

$type = optional_param('type', 'tool_certificate', PARAM_ALPHANUMEXT);
$issueid = required_param('issueid', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/blocks/download_certificates/download_precise.php');

$scope = scope::for_user((int) $USER->id);
try {
    download::send_single($scope, $type, $issueid);
} catch (moodle_exception $e) {
    request::redirect_on_error($scope, $e);
}
