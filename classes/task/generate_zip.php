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
 * Adhoc task that builds a background certificate ZIP in batches.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\task;

use block_download_certificates\local\filter;
use block_download_certificates\local\repository;
use block_download_certificates\local\request;
use block_download_certificates\local\scope;
use block_download_certificates\local\task_storage;
use block_download_certificates\local\zip_builder;
use block_download_certificates\local\zip_tasks;

/**
 * Builds the ZIP of one task row, one batch per run, re-queuing itself until the archive is complete.
 *
 * Every run derives the requester's scope again and checks the request against it: the request was
 * authorised by a scope that may have changed since. The first run freezes the list of issues
 * into the task directory; later runs read that list by `batchoffset`, so a certificate issued
 * between two runs neither shifts nor duplicates a batch.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_zip extends \core\task\adhoc_task {
    /** @var int Certificates added per run. */
    public const BATCH_SIZE = 500;

    /** @var int Progress is written every this many certificates. */
    private const PROGRESS_STEP = 10;

    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_generate_zip', 'block_download_certificates');
    }

    /**
     * Run one batch of the row named by the custom data; a failure fails the row, never the task.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $taskid = (int) ($this->get_custom_data()->taskid ?? 0);
        $task = $taskid > 0 ? $DB->get_record(zip_tasks::TABLE, ['id' => $taskid]) : false;
        if (!$task) {
            mtrace("Certificate ZIP task {$taskid} not found, skipping.");
            return;
        }
        if (!in_array($task->status, [zip_tasks::STATUS_PENDING, zip_tasks::STATUS_PROCESSING], true)) {
            mtrace("Certificate ZIP task {$taskid} is {$task->status}, skipping.");
            return;
        }

        raise_memory_limit(MEMORY_EXTRA);
        try {
            $this->run_batch($task);
        } catch (\Throwable $e) {
            // A row the cleanup failed meanwhile keeps its own reason.
            zip_tasks::fail_if_status(
                $taskid,
                [zip_tasks::STATUS_PENDING, zip_tasks::STATUS_PROCESSING],
                zip_tasks::error_code($e)
            );
            mtrace("Certificate ZIP task {$taskid} failed: " . get_class($e) . ': ' . $e->getMessage());
        }
    }

    /**
     * The number of certificates one run adds.
     *
     * @return int
     */
    protected function get_batch_size(): int {
        return self::BATCH_SIZE;
    }

    /**
     * Freeze the list on the first run, then add the next batch and either re-queue or finish.
     *
     * @param \stdClass $task The row.
     * @throws \moodle_exception When the request is no longer allowed, empty, or cannot be written.
     */
    private function run_batch(\stdClass $task): void {
        $taskid = (int) $task->id;
        [$scope, $filter] = $this->get_checked_request($task);
        $repository = new repository();
        $zippath = task_storage::get_zip_path($taskid);

        $offset = (int) $task->batchoffset;
        if ($task->status === zip_tasks::STATUS_PENDING) {
            $refs = $repository->list_issue_refs($scope, $filter);
            if (!$refs) {
                throw new \moodle_exception('nocertificates', 'block_download_certificates');
            }
            task_storage::write_refs($taskid, $refs);
            $offset = 0;
            $moved = zip_tasks::update_if_status($taskid, zip_tasks::STATUS_PENDING, [
                'status' => zip_tasks::STATUS_PROCESSING,
                'total' => count($refs),
                'progress' => 0,
                'batchoffset' => 0,
                'timemodified' => time(),
            ]);
            if ($this->lost_write($taskid, $moved)) {
                return;
            }
        }

        $refs = task_storage::read_refs($taskid);
        $total = count($refs);
        $batch = array_slice($refs, $offset, max(1, $this->get_batch_size()));

        $progress = static function (int $processed) use ($taskid, $offset): void {
            if ($processed % self::PROGRESS_STEP === 0) {
                zip_tasks::update_if_status($taskid, zip_tasks::STATUS_PROCESSING, [
                    'progress' => $offset + $processed,
                    'timemodified' => time(),
                ]);
            }
        };
        // The scope is checked again for each issue: the frozen list may be older than a membership change.
        $errors = (new zip_builder($repository->get_sources()))->build($batch, $zippath, $progress, $scope);
        if ($errors) {
            mtrace("Certificate ZIP task {$taskid}: " . count($errors) . ' certificates could not be added.');
        }

        $offset += count($batch);
        if ($offset < $total) {
            $moved = zip_tasks::update_if_status($taskid, zip_tasks::STATUS_PROCESSING, [
                'progress' => $offset,
                'batchoffset' => $offset,
                'timemodified' => time(),
            ]);
            if ($this->lost_write($taskid, $moved)) {
                return;
            }
            $this->queue($taskid);
            return;
        }

        if (!is_file($zippath)) {
            throw new \moodle_exception('cannotcreatezipfile', 'block_download_certificates');
        }
        $moved = zip_tasks::update_if_status($taskid, zip_tasks::STATUS_PROCESSING, [
            'status' => zip_tasks::STATUS_READY,
            'progress' => $total,
            'batchoffset' => $total,
            'timemodified' => time(),
        ]);
        $this->lost_write($taskid, $moved);
    }

    /**
     * Deal with a conditional status write that did not happen.
     *
     * A write that lost to another writer (the cleanup failed the row) ends the run and drops what it
     * built. A write that never ran because the row's lock was not had in time says nothing about the
     * row: the run is queued again for the same row, and its directory stays.
     *
     * @param int $taskid The row id.
     * @param bool|null $moved The result of {@see zip_tasks::update_if_status()}.
     * @return bool Whether the run must stop here.
     */
    private function lost_write(int $taskid, ?bool $moved): bool {
        if ($moved === true) {
            return false;
        }
        if ($moved === null) {
            $this->queue($taskid);
            mtrace("Certificate ZIP task {$taskid} could not lock its row, queued again.");
        } else {
            $this->abandon($taskid);
        }
        return true;
    }

    /**
     * Queue the next run for a row.
     *
     * @param int $taskid The row id.
     */
    private function queue(int $taskid): void {
        $next = new self();
        $next->set_custom_data((object) ['taskid' => $taskid]);
        \core\task\manager::queue_adhoc_task($next);
    }

    /**
     * Stop working on a row that something else (the cleanup) moved on while this run was building.
     *
     * The row keeps its status and reason; what this run wrote into the task directory goes.
     *
     * @param int $taskid The row id.
     */
    private function abandon(int $taskid): void {
        task_storage::delete($taskid);
        mtrace("Certificate ZIP task {$taskid} changed status while it ran, dropping the run.");
    }

    /**
     * The requester's scope as it is now, and the stored filter, after the request check.
     *
     * @param \stdClass $task The row.
     * @return array [scope $scope, filter $filter]
     * @throws \moodle_exception `taskerror_inactiveuser` for a deleted, suspended or unusable
     *         account, `taskerror_noaccess` when the scope no longer allows the request.
     */
    private function get_checked_request(\stdClass $task): array {
        $user = \core_user::get_user((int) $task->userid);
        try {
            if (!$user) {
                throw new \moodle_exception('invaliduser');
            }
            \core_user::require_active_user($user, true, true);
        } catch (\moodle_exception $e) {
            throw new \moodle_exception('taskerror_inactiveuser', 'block_download_certificates');
        }

        $params = json_decode((string) $task->params, true);
        $filter = filter::from_array(is_array($params) ? $params : []);
        $scope = scope::for_user((int) $user->id);
        try {
            request::check_zip($scope, (string) $task->type, $filter);
        } catch (\moodle_exception $e) {
            if ($e->errorcode !== 'accessdenied') {
                throw $e;
            }
            throw new \moodle_exception('taskerror_noaccess', 'block_download_certificates');
        }
        return [$scope, $filter];
    }
}
