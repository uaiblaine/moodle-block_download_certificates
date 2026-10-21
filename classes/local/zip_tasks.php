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
 * Background ZIP download requests.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

use block_download_certificates\task\generate_zip;

/**
 * The rows of `block_download_certificates_tasks` seen from the request side: creating one,
 * reporting its state to its owner, and handing over its archive once.
 *
 * A row stores the requester, the request type and the cleaned filter, never a scope: the task
 * derives the scope again when it runs. A failed row's `error` holds a string identifier of this
 * plugin, resolved in the reader's language when shown.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class zip_tasks {
    /** @var string The task table. */
    public const TABLE = 'block_download_certificates_tasks';

    /** @var string Queued, not started. */
    public const STATUS_PENDING = 'pending';

    /** @var string Between batches. */
    public const STATUS_PROCESSING = 'processing';

    /** @var string The archive is complete. */
    public const STATUS_READY = 'ready';

    /** @var string Stopped; `error` says why. */
    public const STATUS_FAILED = 'failed';

    /** @var string Downloaded or too old; the archive is gone. */
    public const STATUS_EXPIRED = 'expired';

    /**
     * Check a request, store it and queue its task.
     *
     * @param scope $scope Who is asking.
     * @param string $type One of {@see request::TYPES}.
     * @param filter $filter The request's filter.
     * @param repository|null $repository The repository to count with; null for the available sources.
     * @return \stdClass The new row.
     * @throws \moodle_exception As {@see request::check_zip()}, or `nocertificates`.
     */
    public static function create(scope $scope, string $type, filter $filter, ?repository $repository = null): \stdClass {
        global $DB;

        request::check_zip($scope, $type, $filter);
        $repository = $repository ?? new repository();
        $total = $repository->count($scope, $filter);
        if ($total === 0) {
            throw new \moodle_exception('nocertificates', 'block_download_certificates');
        }

        $now = time();
        $record = (object) [
            'userid' => $scope->get_user_id(),
            'type' => $type,
            'params' => json_encode($filter->to_array()),
            'status' => self::STATUS_PENDING,
            'progress' => 0,
            'total' => $total,
            'batchoffset' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = (int) $DB->insert_record(self::TABLE, $record);

        // No task user: core cancels, without running it, a task whose user is suspended or
        // deleted, which would leave the row pending; the task checks the requester itself.
        $task = new generate_zip();
        $task->set_custom_data((object) ['taskid' => $record->id]);
        \core\task\manager::queue_adhoc_task($task);

        return $record;
    }

    /**
     * A row of the given owner.
     *
     * @param int $taskid The row id.
     * @param int $userid The user asking.
     * @return \stdClass
     * @throws \moodle_exception `tasknotfound`, or `accessdenied` for another user's row.
     */
    public static function get_owned(int $taskid, int $userid): \stdClass {
        global $DB;

        $task = $DB->get_record(self::TABLE, ['id' => $taskid]);
        if (!$task) {
            throw new \moodle_exception('tasknotfound', 'block_download_certificates');
        }
        if ((int) $task->userid !== $userid) {
            throw new \moodle_exception('accessdenied', 'block_download_certificates');
        }
        return $task;
    }

    /**
     * The state of a row for its owner, as the status script answers it.
     *
     * @param int $taskid The row id.
     * @param int $userid The user asking.
     * @return array taskid, status, progress, total, and `download_url` when ready or
     *         `error_message` when failed.
     * @throws \moodle_exception As {@see self::get_owned()}.
     */
    public static function get_status(int $taskid, int $userid): array {
        $task = self::get_owned($taskid, $userid);
        $response = [
            'success' => true,
            'taskid' => (int) $task->id,
            'status' => (string) $task->status,
            'progress' => (int) $task->progress,
            'total' => (int) $task->total,
        ];
        if ($task->status === self::STATUS_READY) {
            $response['download_url'] = (new \moodle_url('/blocks/download_certificates/ajax/download_zip.php', [
                'taskid' => $task->id,
                'sesskey' => sesskey(),
            ]))->out(false);
        }
        if ($task->status === self::STATUS_FAILED) {
            $response['error_message'] = self::error_message((string) $task->error);
        }
        return $response;
    }

    /**
     * The owner's unfinished and ready rows, newest first, for the async script's start-up.
     *
     * @param int $userid The owner.
     * @return array List of taskid, status, progress, total, type.
     */
    public static function get_pending(int $userid): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(
            [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_READY],
            SQL_PARAMS_NAMED,
            'bdcstatus'
        );
        $params['bdcuserid'] = $userid;
        $tasks = $DB->get_records_select(
            self::TABLE,
            "userid = :bdcuserid AND status {$insql}",
            $params,
            'timecreated DESC, id DESC'
        );

        $result = [];
        foreach ($tasks as $task) {
            $result[] = [
                'taskid' => (int) $task->id,
                'status' => (string) $task->status,
                'progress' => (int) $task->progress,
                'total' => (int) $task->total,
                'type' => (string) $task->type,
            ];
        }
        return $result;
    }

    /**
     * The archive of a ready row of the given owner, about to be sent.
     *
     * @param int $taskid The row id.
     * @param int $userid The user asking.
     * @return array [string $zippath, string $filename]: the archive inside the row's own task
     *         directory, and its download name.
     * @throws \moodle_exception As {@see self::get_owned()}, `tasknotready`, or `filenotfound`
     *         (the row is then expired).
     */
    public static function prepare_download(int $taskid, int $userid): array {
        $task = self::get_owned($taskid, $userid);
        if ($task->status !== self::STATUS_READY) {
            throw new \moodle_exception('tasknotready', 'block_download_certificates');
        }
        // The path follows from the row id, never from the stored column.
        $zippath = task_storage::get_zip_path((int) $task->id);
        if (!is_file($zippath)) {
            self::expire((int) $task->id);
            throw new \moodle_exception('filenotfound', 'block_download_certificates');
        }
        $params = json_decode((string) $task->params, true);
        $filter = filter::from_array(is_array($params) ? $params : []);
        return [$zippath, request::zip_filename((string) $task->type, $filter)];
    }

    /**
     * Expire a row and remove its directory: an archive is handed over once.
     *
     * The download script registers this to run at shutdown, after the bytes went out.
     *
     * @param int $taskid The row id.
     */
    public static function expire(int $taskid): void {
        global $DB;

        $DB->update_record(self::TABLE, (object) [
            'id' => $taskid,
            'status' => self::STATUS_EXPIRED,
            'timemodified' => time(),
        ]);
        task_storage::delete($taskid);
    }

    /**
     * Fail a row and remove its directory.
     *
     * @param int $taskid The row id.
     * @param string $errorcode A string identifier of this plugin.
     */
    public static function fail(int $taskid, string $errorcode): void {
        global $DB;

        $DB->update_record(self::TABLE, (object) [
            'id' => $taskid,
            'status' => self::STATUS_FAILED,
            'error' => $errorcode,
            'timemodified' => time(),
        ]);
        task_storage::delete($taskid);
    }

    /**
     * The string identifier a failure is stored as.
     *
     * @param \Throwable $e The failure.
     * @return string The plugin's own error code, or `taskerror_unexpected` (also for a code whose
     *         message needs values, which a stored identifier cannot carry).
     */
    public static function error_code(\Throwable $e): string {
        if (
            $e instanceof \moodle_exception
            && $e->module === 'block_download_certificates'
            && $e->a === null
            && get_string_manager()->string_exists($e->errorcode, 'block_download_certificates')
        ) {
            return $e->errorcode;
        }
        return 'taskerror_unexpected';
    }

    /**
     * The message of a stored error, in the reader's language.
     *
     * Rows written before error codes held English text; any value that is not a string
     * identifier of this plugin reads as the generic failure.
     *
     * @param string $error The stored value.
     * @return string
     */
    public static function error_message(string $error): string {
        if (
            preg_match('/^[a-z0-9_]+$/', $error)
            && get_string_manager()->string_exists($error, 'block_download_certificates')
        ) {
            return get_string($error, 'block_download_certificates');
        }
        return get_string('taskerror_unexpected', 'block_download_certificates');
    }
}
