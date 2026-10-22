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
 * Tests for the background download status web service.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\external;

use block_download_certificates\local\filter;
use block_download_certificates\local\zip_tasks;
use core_external\external_api;

/**
 * A task's state is reported to its owner only, with the download script's address once ready
 * and a localised reason once failed.
 *
 * Metadata stays in docblocks because the plugin still supports Moodle 4.5, whose moodle-cs cannot
 * read attributes and warns per method.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\external\get_task_status
 */
final class get_task_status_test extends \advanced_testcase {
    /**
     * Reset the database after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Call the function as the AJAX layer does, as the current user.
     *
     * @param int $taskid The task.
     * @return array The response: error, and data or exception.
     */
    private function call(int $taskid): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function('block_download_certificates_get_task_status', ['taskid' => $taskid], true);
    }

    /**
     * The error code of a refused response, or null for an answered one.
     *
     * @param array $response The response.
     * @return string|null
     */
    private function error_of(array $response): ?string {
        return $response['error'] ? $response['exception']->errorcode : null;
    }

    /**
     * Insert a task row.
     *
     * @param int $userid The owner.
     * @param string $status The status.
     * @param string|null $error The stored error code.
     * @return int The row id.
     */
    private function add_task(int $userid, string $status, ?string $error = null): int {
        global $DB;

        return (int) $DB->insert_record(zip_tasks::TABLE, (object) [
            'userid' => $userid,
            'type' => 'course',
            'params' => json_encode((new filter(courseid: 5))->to_array()),
            'status' => $status,
            'progress' => 3,
            'total' => 7,
            'batchoffset' => 0,
            'error' => $error,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * The owner gets the state; another user and the guest are refused for the same task.
     */
    public function test_status_is_for_the_owner_only(): void {
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $taskid = $this->add_task((int) $owner->id, zip_tasks::STATUS_PROCESSING);

        // Another user's row answers as a missing one: task ids cannot be probed.
        $this->setUser($other);
        $this->assertSame('tasknotfound', $this->error_of($this->call($taskid)));
        $this->assertSame($this->error_of($this->call($taskid + 1000)), $this->error_of($this->call($taskid)));
        $this->setGuestUser();
        $this->assertSame('noguest', $this->error_of($this->call($taskid)));

        $this->setUser($owner);
        $response = $this->call($taskid);
        $this->assertNull($this->error_of($response));
        $this->assertSame([
            'taskid' => $taskid,
            'type' => 'course',
            'status' => zip_tasks::STATUS_PROCESSING,
            'progress' => 3,
            'total' => 7,
            'downloadurl' => '',
            'errormessage' => '',
        ], $response['data']);

        $this->assertSame('tasknotfound', $this->error_of($this->call($taskid + 1000)));
    }

    /**
     * A ready task points at the download script with no session key in the address; a failed one
     * carries its reason in the reader's language.
     */
    public function test_ready_and_failed_states(): void {
        $owner = $this->getDataGenerator()->create_user();
        $ready = $this->add_task((int) $owner->id, zip_tasks::STATUS_READY);
        $failed = $this->add_task((int) $owner->id, zip_tasks::STATUS_FAILED, 'taskerror_timeout');
        $this->setUser($owner);

        $data = $this->call($ready)['data'];
        $url = new \moodle_url($data['downloadurl']);
        $this->assertStringEndsWith('/blocks/download_certificates/ajax/download_zip.php', $url->get_path(false));
        $this->assertSame([], $url->params());
        $this->assertSame('', $data['errormessage']);

        $data = $this->call($failed)['data'];
        $this->assertSame(get_string('taskerror_timeout', 'block_download_certificates'), $data['errormessage']);
        $this->assertSame('', $data['downloadurl']);
    }
}
