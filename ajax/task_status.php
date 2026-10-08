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
 * AJAX endpoint to check the status of an async download task.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch' contact@elearningtouch.com (Maintainer)
 * @author    Thomas Clément 222384061+ClementThomasELT@users.noreply.github.com (Coder)
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

require_login();

$taskid = required_param('taskid', PARAM_INT);

$task = $DB->get_record('block_download_certificates_tasks', ['id' => $taskid]);

if (!$task) {
    echo json_encode(['success' => false, 'error' => 'Task not found']);
    die();
}

// Verify ownership.
if ((int)$task->userid !== (int)$USER->id) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    die();
}

$response = [
    'success' => true,
    'taskid' => (int) $task->id,
    'status' => $task->status,
    'progress' => (int) $task->progress,
    'total' => (int) $task->total,
];

// Include download URL if ready.
if ($task->status === 'ready') {
    $response['download_url'] = (new moodle_url('/blocks/download_certificates/ajax/download_zip.php', [
        'taskid' => $task->id,
        'sesskey' => sesskey(),
    ]))->out(false);
}

// Include error message if failed.
if ($task->status === 'failed' && !empty($task->error)) {
    $response['error_message'] = $task->error;
}

echo json_encode($response);
