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
 * AJAX endpoint to download the generated ZIP file.
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
require_sesskey();

$taskid = required_param('taskid', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/blocks/download_certificates/ajax/download_zip.php', ['taskid' => $taskid]);

[$zippath, $filename] = zip_tasks::prepare_download($taskid, (int) $USER->id);

// Expired once the bytes went out: shutdown functions run after send_temp_file() ends the request.
// An interrupted transfer expires the task too, and the user requests the archive again.
\core\shutdown_manager::register_function([zip_tasks::class, 'expire'], [$taskid]);
send_temp_file($zippath, $filename);
