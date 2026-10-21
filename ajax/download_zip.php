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
 * Hand over a ready background ZIP to its owner; the task id and the session key come in a POST body.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_download_certificates\local\request;
use block_download_certificates\local\zip_tasks;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login(null, false);
request::reject_guest();
request::require_post_sesskey();

$taskid = required_param('taskid', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/blocks/download_certificates/ajax/download_zip.php');

// The row is claimed (ready to expired) before sending, so a second request for it is refused.
// send_temp_file() deletes the file and ends the request, so nothing can run after it: the expired
// row's task directory is removed by the cleanup_expired task, which deletes the directory of every
// expired row. An interrupted transfer costs the archive; the user requests it again.
[$zippath, $filename] = zip_tasks::claim_download($taskid, (int) $USER->id);
send_temp_file($zippath, $filename);
