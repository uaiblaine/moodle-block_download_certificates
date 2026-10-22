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
 * Scheduled task that removes old background ZIP downloads.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\task;

use block_download_certificates\local\task_storage;
use block_download_certificates\local\zip_tasks;

/**
 * Removes old background ZIP downloads, hourly.
 *
 * - A pending or processing row untouched for {@see self::STUCK_AFTER} fails.
 * - A ready row untouched for {@see self::READY_FOR} expires.
 * - A row older than {@see self::KEEP_FOR} is deleted.
 * Every row it fails, expires or deletes loses its task directory, and so does any failed or expired
 * row that still has one. Files are only ever removed through {@see task_storage}, which knows no
 * path outside the plugin's task directories; the rows' `filepath` column is never read.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_expired extends \core\task\scheduled_task {
    /** @var int Seconds after which an unfinished row counts as stuck. */
    public const STUCK_AFTER = 2 * HOURSECS;

    /** @var int Seconds a ready archive stays downloadable. */
    public const READY_FOR = DAYSECS;

    /** @var int Seconds a row is kept. */
    public const KEEP_FOR = 7 * DAYSECS;

    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup_expired', 'block_download_certificates');
    }

    /**
     * Fail stuck rows, expire old archives, delete old rows, each with its directory.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $now = time();
        $table = zip_tasks::TABLE;

        $stuck = $DB->get_fieldset_select(
            $table,
            'id',
            'status IN (:pending, :processing) AND timemodified < :before',
            [
                'pending' => zip_tasks::STATUS_PENDING,
                'processing' => zip_tasks::STATUS_PROCESSING,
                'before' => $now - self::STUCK_AFTER,
            ]
        );
        // A row whose lock is not had in time stays as it is for the next run.
        $failed = 0;
        foreach ($stuck as $taskid) {
            $failed += zip_tasks::fail((int) $taskid, 'taskerror_timeout') ? 1 : 0;
        }

        $old = $DB->get_fieldset_select(
            $table,
            'id',
            'status = :ready AND timemodified < :before',
            ['ready' => zip_tasks::STATUS_READY, 'before' => $now - self::READY_FOR]
        );
        $expired = 0;
        foreach ($old as $taskid) {
            $expired += zip_tasks::expire((int) $taskid) ? 1 : 0;
        }

        $finished = $DB->get_fieldset_select(
            $table,
            'id',
            'status IN (:failed, :expired)',
            ['failed' => zip_tasks::STATUS_FAILED, 'expired' => zip_tasks::STATUS_EXPIRED]
        );
        foreach ($finished as $taskid) {
            task_storage::delete((int) $taskid);
        }

        $deleted = $DB->get_fieldset_select($table, 'id', 'timecreated < :before', ['before' => $now - self::KEEP_FOR]);
        foreach ($deleted as $taskid) {
            task_storage::delete((int) $taskid);
            $DB->delete_records($table, ['id' => $taskid]);
        }

        if ($failed + $expired + count($deleted) > 0) {
            mtrace("Certificate ZIP downloads: {$failed} failed, {$expired} expired, " . count($deleted) . ' deleted.');
        }
    }
}
