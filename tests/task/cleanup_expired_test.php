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
 * Tests for the cleanup of background ZIP downloads.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\task;

use block_download_certificates\local\task_storage;
use block_download_certificates\local\zip_tasks;

/**
 * Every row the cleanup fails, expires or deletes loses its own task directory and nothing else.
 *
 * Metadata stays in docblocks because the plugin still supports Moodle 4.5, whose moodle-cs cannot
 * read attributes and warns per method.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\task\cleanup_expired
 */
final class cleanup_expired_test extends \advanced_testcase {
    /** @var int The owner of the rows. */
    private int $userid;

    /**
     * Reset the database after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->userid = (int) $this->getDataGenerator()->create_user()->id;
    }

    /**
     * Make every row lock available again.
     */
    protected function tearDown(): void {
        zip_tasks::set_unavailable_locks([]);
        parent::tearDown();
    }

    /**
     * Insert a row with an archive in its task directory.
     *
     * @param string $status The status.
     * @param int $age Seconds since the row was created.
     * @param int $idle Seconds since the row was last modified.
     * @return int The row id.
     */
    private function add_task(string $status, int $age, int $idle): int {
        global $DB;

        $now = time();
        $taskid = (int) $DB->insert_record(zip_tasks::TABLE, (object) [
            'userid' => $this->userid,
            'type' => 'all',
            'params' => '{}',
            'status' => $status,
            'progress' => 0,
            'total' => 1,
            'batchoffset' => 0,
            'timecreated' => $now - $age,
            'timemodified' => $now - $idle,
        ]);
        $zippath = task_storage::get_zip_path($taskid);
        file_put_contents($zippath, 'zip');
        $DB->set_field(zip_tasks::TABLE, 'filepath', $zippath, ['id' => $taskid]);
        return $taskid;
    }

    /**
     * Run the cleanup, capturing its trace output.
     *
     * @return string What the cleanup printed.
     */
    private function run_cleanup(): string {
        ob_start();
        try {
            (new cleanup_expired())->execute();
        } finally {
            $output = ob_get_clean();
        }
        return $output;
    }

    /**
     * A row older than the keep period goes with its directory; a recent row keeps its archive.
     */
    public function test_old_rows_are_deleted_with_their_directory_only(): void {
        global $DB;

        $old = $this->add_task(zip_tasks::STATUS_READY, cleanup_expired::KEEP_FOR + HOURSECS, HOURSECS);
        $recent = $this->add_task(zip_tasks::STATUS_READY, HOURSECS, HOURSECS);

        $this->run_cleanup();

        $this->assertFalse($DB->record_exists(zip_tasks::TABLE, ['id' => $old]));
        $this->assertDirectoryDoesNotExist(task_storage::get_directory($old));
        $this->assertSame(zip_tasks::STATUS_READY, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $recent]));
        $this->assertFileExists(task_storage::get_directory($recent) . '/certificates.zip');
    }

    /**
     * Pending and processing rows idle past the stuck limit fail and lose their directory; a
     * processing row that moved recently is left running.
     */
    public function test_stuck_tasks_fail_and_lose_their_directory(): void {
        global $DB;

        $stuckidle = cleanup_expired::STUCK_AFTER + MINSECS;
        $pending = $this->add_task(zip_tasks::STATUS_PENDING, $stuckidle, $stuckidle);
        $processing = $this->add_task(zip_tasks::STATUS_PROCESSING, DAYSECS, $stuckidle);
        $running = $this->add_task(zip_tasks::STATUS_PROCESSING, DAYSECS, 10 * MINSECS);

        $this->run_cleanup();

        foreach ([$pending, $processing] as $taskid) {
            $row = $DB->get_record(zip_tasks::TABLE, ['id' => $taskid]);
            $this->assertSame(zip_tasks::STATUS_FAILED, $row->status);
            $this->assertSame('taskerror_timeout', $row->error);
            $this->assertDirectoryDoesNotExist(task_storage::get_directory($taskid));
        }
        $this->assertSame(zip_tasks::STATUS_PROCESSING, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $running]));
        $this->assertFileExists(task_storage::get_directory($running) . '/certificates.zip');
    }

    /**
     * A ready archive left past its download period expires and is removed; a fresh one stays.
     */
    public function test_old_ready_archives_expire(): void {
        global $DB;

        $old = cleanup_expired::READY_FOR + HOURSECS;
        $stale = $this->add_task(zip_tasks::STATUS_READY, $old, $old);
        $fresh = $this->add_task(zip_tasks::STATUS_READY, HOURSECS, HOURSECS);

        $this->run_cleanup();

        $this->assertSame(zip_tasks::STATUS_EXPIRED, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $stale]));
        $this->assertDirectoryDoesNotExist(task_storage::get_directory($stale));
        $this->assertSame(zip_tasks::STATUS_READY, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $fresh]));
        $this->assertFileExists(task_storage::get_directory($fresh) . '/certificates.zip');
    }

    /**
     * A failed or expired row that still has a directory loses it.
     */
    public function test_finished_rows_lose_a_leftover_directory(): void {
        $failed = $this->add_task(zip_tasks::STATUS_FAILED, HOURSECS, HOURSECS);
        $expired = $this->add_task(zip_tasks::STATUS_EXPIRED, HOURSECS, HOURSECS);

        $this->run_cleanup();

        $this->assertDirectoryDoesNotExist(task_storage::get_directory($failed));
        $this->assertDirectoryDoesNotExist(task_storage::get_directory($expired));
    }

    /**
     * A stored path outside the task directories is never deleted, even inside the plugin's own temp
     * directory, while its row goes.
     */
    public function test_nothing_outside_the_task_directories_is_touched(): void {
        global $DB;

        $outside = make_temp_directory('block_download_certificates/async') . '/legacy.zip';
        file_put_contents($outside, 'zip');
        $taskid = $this->add_task(zip_tasks::STATUS_EXPIRED, cleanup_expired::KEEP_FOR + HOURSECS, HOURSECS);
        $DB->set_field(zip_tasks::TABLE, 'filepath', $outside, ['id' => $taskid]);

        $this->run_cleanup();

        $this->assertFalse($DB->record_exists(zip_tasks::TABLE, ['id' => $taskid]));
        $this->assertFileExists($outside);
        unlink($outside);
    }

    /**
     * A row whose lock the cleanup cannot take keeps its status and its directory until the next
     * run; a row beside it, unlocked, is handled at once.
     */
    public function test_a_locked_row_is_left_for_the_next_run(): void {
        global $DB;

        $stuckidle = cleanup_expired::STUCK_AFTER + MINSECS;
        $old = cleanup_expired::READY_FOR + HOURSECS;
        $stuck = $this->add_task(zip_tasks::STATUS_PROCESSING, DAYSECS, $stuckidle);
        $stale = $this->add_task(zip_tasks::STATUS_READY, $old, $old);
        $freestuck = $this->add_task(zip_tasks::STATUS_PROCESSING, DAYSECS, $stuckidle);
        $freestale = $this->add_task(zip_tasks::STATUS_READY, $old, $old);
        zip_tasks::set_unavailable_locks([$stuck, $stale]);

        $output = $this->run_cleanup();

        $this->assertStringContainsString('1 failed, 1 expired, 0 deleted', $output);
        $this->assertSame(zip_tasks::STATUS_PROCESSING, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $stuck]));
        $this->assertSame(zip_tasks::STATUS_READY, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $stale]));
        $this->assertFileExists(task_storage::get_directory($stuck) . '/certificates.zip');
        $this->assertFileExists(task_storage::get_directory($stale) . '/certificates.zip');
        $this->assertSame(zip_tasks::STATUS_FAILED, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $freestuck]));
        $this->assertSame(zip_tasks::STATUS_EXPIRED, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $freestale]));

        zip_tasks::set_unavailable_locks([]);
        $this->run_cleanup();

        $this->assertSame(zip_tasks::STATUS_FAILED, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $stuck]));
        $this->assertSame(zip_tasks::STATUS_EXPIRED, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $stale]));
        $this->assertDirectoryDoesNotExist(task_storage::get_directory($stuck));
        $this->assertDirectoryDoesNotExist(task_storage::get_directory($stale));
    }
}
