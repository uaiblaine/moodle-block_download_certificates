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
 * AJAX endpoint: the groups of a course the user may filter a course download by.
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

$courseid = required_param('courseid', PARAM_INT);

try {
    $groups = request::course_groups(scope::for_user((int) $USER->id), $courseid);
    request::send_json(['success' => true, 'groups' => $groups]);
} catch (Throwable $e) {
    request::send_json(['success' => false, 'error' => request::error_message($e)]);
}
