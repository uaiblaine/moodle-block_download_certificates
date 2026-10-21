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
 * Adhoc task to generate a certificates ZIP file asynchronously.
 *
 * Uses a batched approach: each execution processes a limited number of
 * certificates (BATCH_SIZE) and re-schedules itself for the next batch.
 * This prevents memory exhaustion and timeout issues on large volumes.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_retriever.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_query.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_packager.php');

/**
 * Adhoc task that generates a ZIP archive of certificates in the background.
 *
 * Processes certificates in batches of BATCH_SIZE and re-schedules itself
 * for subsequent batches until all certificates have been processed.
 */
class generate_zip extends \core\task\adhoc_task {
    /** @var int Number of certificates to process per batch. */
    const BATCH_SIZE = 500;

    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_generate_zip', 'block_download_certificates');
    }

    /**
     * Execute the task.
     *
     * Processes one batch of certificates and re-schedules itself if needed.
     */
    public function execute() {
        global $DB;

        // Raise memory limit for PDF generation.
        raise_memory_limit(MEMORY_EXTRA);

        $data = $this->get_custom_data();
        $taskid = $data->taskid;
        $offset = isset($data->offset) ? (int) $data->offset : 0;

        // Fetch the task record.
        $task = $DB->get_record('block_download_certificates_tasks', ['id' => $taskid]);
        if (!$task) {
            mtrace("Task {$taskid} not found, skipping.");
            return;
        }

        // Skip if already completed, failed, or expired.
        if (!in_array($task->status, ['pending', 'processing'])) {
            mtrace("Task {$taskid} status is '{$task->status}', skipping.");
            return;
        }

        // Update status to processing (only on first batch).
        if ($task->status === 'pending') {
            $DB->update_record('block_download_certificates_tasks', (object) [
                'id' => $taskid,
                'status' => 'processing',
                'timemodified' => time(),
            ]);
        }

        try {
            $retriever = new \block_download_certificates_retriever();
            $query = new \block_download_certificates_query();
            $packager = new \block_download_certificates_packager($retriever, $query);

            $params = !empty($task->params) ? json_decode($task->params, true) : [];

            // On first batch, count total certificates and save.
            if ($offset === 0) {
                $total = $packager->count_certificates_for_type($task->type, $params);
                $DB->update_record('block_download_certificates_tasks', (object) [
                    'id' => $taskid,
                    'total' => $total,
                    'timemodified' => time(),
                ]);
            } else {
                $total = (int) $task->total;
            }

            // Get existing ZIP path (for subsequent batches).
            $existingzippath = !empty($task->filepath) ? $task->filepath : null;

            // Progress callback — update the task record every 10 certificates.
            $lastupdate = 0;
            $progresscallback = function ($processed) use ($DB, $taskid, &$lastupdate) {
                if ($processed - $lastupdate >= 10 || $processed === 0) {
                    $DB->update_record('block_download_certificates_tasks', (object) [
                        'id' => $taskid,
                        'progress' => $processed,
                        'timemodified' => time(),
                    ]);
                    $lastupdate = $processed;
                }
            };

            // Process one batch.
            mtrace("Task {$taskid}: Processing batch at offset {$offset} (batch size: " . self::BATCH_SIZE . ")");

            $result = $packager->generate_zip_batch(
                $task->type,
                $params,
                $offset,
                self::BATCH_SIZE,
                $existingzippath,
                $progresscallback
            );

            $newoffset = $offset + $result['processed'];

            // Log any errors from this batch.
            if (!empty($result['errors'])) {
                mtrace("Task {$taskid}: " . count($result['errors']) . " errors in this batch.");
                foreach (array_slice($result['errors'], 0, 5) as $error) {
                    mtrace("  - " . $error);
                }
            }

            // Save the ZIP path and update progress.
            $DB->update_record('block_download_certificates_tasks', (object) [
                'id' => $taskid,
                'progress' => $newoffset,
                'filepath' => $result['zippath'],
                'batchoffset' => $newoffset,
                'timemodified' => time(),
            ]);

            // Check if we need to process more batches.
            if ($newoffset < $result['total']) {
                // Re-schedule for the next batch.
                mtrace("Task {$taskid}: Batch complete ({$newoffset}/{$result['total']}). Scheduling next batch.");

                $nexttask = new self();
                $nexttask->set_custom_data((object) [
                    'taskid' => $taskid,
                    'offset' => $newoffset,
                ]);
                $nexttask->set_userid($this->get_userid());
                \core\task\manager::queue_adhoc_task($nexttask);
            } else {
                // All batches complete — add error log if needed and finalize.
                if (!empty($result['zippath']) && file_exists($result['zippath'])) {
                    // Reopen ZIP to add error log if any errors accumulated.
                    $allerrors = $this->collect_batch_errors($result);
                    if (!empty($allerrors)) {
                        $zip = new \ZipArchive();
                        if ($zip->open($result['zippath']) === true) {
                            $zip->addFromString(
                                'download_errors.txt',
                                "Download errors:\n" . implode("\n", $allerrors)
                            );
                            $zip->close();
                        }
                    }
                }

                // Mark as ready.
                $DB->update_record('block_download_certificates_tasks', (object) [
                    'id' => $taskid,
                    'status' => 'ready',
                    'progress' => $result['total'],
                    'filepath' => $result['zippath'],
                    'batchoffset' => $result['total'],
                    'timemodified' => time(),
                ]);

                mtrace("Task {$taskid}: ZIP generated successfully at {$result['zippath']}");
            }
        } catch (\Exception $e) {
            // Mark task as failed.
            $DB->update_record('block_download_certificates_tasks', (object) [
                'id' => $taskid,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'timemodified' => time(),
            ]);

            mtrace("Task {$taskid} failed: " . $e->getMessage());
        }
    }

    /**
     * Collect errors from a batch result.
     *
     * @param array $result Batch result from generate_zip_batch
     * @return array Error messages
     */
    private function collect_batch_errors($result) {
        return !empty($result['errors']) ? $result['errors'] : [];
    }
}
