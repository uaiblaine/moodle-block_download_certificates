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
 * AJAX endpoint: one page of the certificates table, within the user's scope.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

use block_download_certificates\local\request;
use block_download_certificates\local\scope;

require_once(__DIR__ . '/../../../config.php');

require_login(null, false);
request::reject_guest();
require_sesskey();

$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$sort = optional_param('sort', 'timecreated', PARAM_ALPHANUMEXT);
$order = optional_param('order', 'DESC', PARAM_ALPHA);

$PAGE->set_context(context_system::instance());

try {
    $scope = scope::for_user((int) $USER->id);
    if (!request::can_open_panel($scope)) {
        throw new moodle_exception('accessdenied', 'block_download_certificates');
    }
    request::send_json(request::search_page($scope, $search, $page, $perpage, $sort, $order));
} catch (Throwable $e) {
    request::send_json(['success' => false, 'error' => request::error_message($e)]);
}
