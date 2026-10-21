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

require_once(__DIR__ . '/../../../config.php');

require_login();
require_sesskey();

$taskid = required_param('taskid', PARAM_INT);

$task = $DB->get_record('block_download_certificates_tasks', ['id' => $taskid]);

if (!$task) {
    throw new moodle_exception('tasknotfound', 'block_download_certificates');
}

// Verify ownership.
if ((int)$task->userid !== (int)$USER->id) {
    throw new moodle_exception('accessdenied', 'block_download_certificates');
}

// Verify status is ready.
if ($task->status !== 'ready') {
    throw new moodle_exception('tasknotready', 'block_download_certificates');
}

// Verify the file exists.
if (empty($task->filepath) || !file_exists($task->filepath)) {
    // Mark as expired if file is missing.
    $DB->update_record('block_download_certificates_tasks', (object) [
        'id' => $task->id,
        'status' => 'expired',
        'timemodified' => time(),
    ]);
    throw new moodle_exception('filenotfound', 'block_download_certificates');
}

// Determine a nice filename.
$filename = basename($task->filepath);

// Mark task as expired (downloaded once = consumed).
$DB->update_record('block_download_certificates_tasks', (object) [
    'id' => $task->id,
    'status' => 'expired',
    'timemodified' => time(),
]);

// Send the file.
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($task->filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

readfile($task->filepath);

// Clean up the file after sending.
unlink($task->filepath);

exit;
