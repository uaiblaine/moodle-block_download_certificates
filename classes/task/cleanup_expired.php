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
 * Scheduled task to clean up expired certificate download tasks.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch' contact@elearningtouch.com (Maintainer)
 * @author    Thomas Clément 222384061+ClementThomasELT@users.noreply.github.com (Coder)
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task that cleans up expired ZIP files and task records.
 *
 * Runs every hour. Deletes:
 * - Ready tasks older than 24 hours (and their ZIP files)
 * - Failed tasks older than 7 days
 * - Stuck processing tasks older than 2 hours
 */
class cleanup_expired extends \core\task\scheduled_task {

    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup_expired', 'block_download_certificates');
    }

    /**
     * Execute the cleanup task.
     */
    public function execute() {
        global $DB;

        $now = time();
        $cleaned = 0;

        // 1. Clean up ready tasks older than 24 hours.
        $expirytime = $now - (24 * 3600);
        $expiredtasks = $DB->get_records_select(
            'block_download_certificates_tasks',
            "status = 'ready' AND timecreated < ?",
            [$expirytime]
        );

        foreach ($expiredtasks as $task) {
            // Delete the ZIP file if it exists.
            if (!empty($task->filepath) && file_exists($task->filepath)) {
                unlink($task->filepath);
                mtrace("Deleted expired ZIP: {$task->filepath}");
            }
            $DB->delete_records('block_download_certificates_tasks', ['id' => $task->id]);
            $cleaned++;
        }

        // 2. Clean up failed tasks older than 7 days.
        $failedexpiry = $now - (7 * 24 * 3600);
        $failedcount = $DB->count_records_select(
            'block_download_certificates_tasks',
            "status = 'failed' AND timecreated < ?",
            [$failedexpiry]
        );
        if ($failedcount > 0) {
            $DB->delete_records_select(
                'block_download_certificates_tasks',
                "status = 'failed' AND timecreated < ?",
                [$failedexpiry]
            );
            $cleaned += $failedcount;
        }

        // 3. Clean up stuck processing tasks older than 2 hours.
        $stuckexpiry = $now - (2 * 3600);
        $stucktasks = $DB->get_records_select(
            'block_download_certificates_tasks',
            "status = 'processing' AND timemodified < ?",
            [$stuckexpiry]
        );

        foreach ($stucktasks as $task) {
            $DB->update_record('block_download_certificates_tasks', (object) [
                'id' => $task->id,
                'status' => 'failed',
                'error' => 'Task timed out (stuck for more than 2 hours)',
                'timemodified' => $now,
            ]);
            $cleaned++;
        }

        // 4. Clean up expired tasks.
        $expiredcount = $DB->count_records_select(
            'block_download_certificates_tasks',
            "status = 'expired'"
        );
        if ($expiredcount > 0) {
            // Delete ZIP files for expired tasks first.
            $expiredrecords = $DB->get_records_select(
                'block_download_certificates_tasks',
                "status = 'expired'"
            );
            foreach ($expiredrecords as $task) {
                if (!empty($task->filepath) && file_exists($task->filepath)) {
                    unlink($task->filepath);
                }
            }
            $DB->delete_records_select(
                'block_download_certificates_tasks',
                "status = 'expired'"
            );
            $cleaned += $expiredcount;
        }

        if ($cleaned > 0) {
            mtrace("Cleaned up {$cleaned} expired/failed certificate download tasks.");
        }
    }
}
