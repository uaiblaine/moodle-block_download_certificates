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
 * Tests for the privacy provider.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\privacy;

use block_download_certificates\local\task_storage;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * The block stores one row per ZIP download a user requested, in that user's own context.
 *
 * Attributes are not used for the metadata below because the plugin still supports Moodle 4.5,
 * whose moodle-cs cannot read them and warns per method.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var string The table the provider covers. */
    private const TABLE = 'block_download_certificates_tasks';

    /**
     * Insert a task row, optionally with a real file behind its filepath.
     *
     * @param int $userid The requesting user.
     * @param string $filepath The stored path.
     * @return int The new row id.
     */
    private function add_task(int $userid, string $filepath = ''): int {
        global $DB;

        return (int) $DB->insert_record(self::TABLE, (object) [
            'userid' => $userid,
            'type' => 'course',
            'params' => '{"courseid":2}',
            'status' => 'ready',
            'progress' => 3,
            'total' => 3,
            'filepath' => $filepath,
            'timecreated' => 1700000000,
            'timemodified' => 1700000100,
            'error' => null,
            'batchoffset' => 0,
        ]);
    }

    /**
     * Create a ZIP stand-in in a task's own directory and point the row's filepath at it.
     *
     * @param int $taskid The task row.
     * @return string The absolute path.
     */
    private function add_file(int $taskid): string {
        global $DB;

        $path = task_storage::get_zip_path($taskid);
        file_put_contents($path, 'zip');
        $DB->set_field(self::TABLE, 'filepath', $path, ['id' => $taskid]);
        return $path;
    }

    /**
     * Core's compliance test sweeps every component but is not in the plugin's testsuite, so it is repeated here.
     *
     * @return void
     */
    public function test_the_component_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('block_download_certificates'));
    }

    /**
     * The metadata names the one table and the eight user-facing fields.
     *
     * @return void
     */
    public function test_the_metadata_declares_the_tasks_table(): void {
        $items = provider::get_metadata(new collection('block_download_certificates'))->get_collection();

        $this->assertCount(1, $items);
        $this->assertSame(self::TABLE, $items[0]->get_name());
        $this->assertEqualsCanonicalizing(
            ['userid', 'type', 'params', 'status', 'filepath', 'timecreated', 'timemodified', 'error'],
            array_keys($items[0]->get_privacy_fields())
        );
    }

    /**
     * Only the requesting user's context is listed; a user with no task has none.
     *
     * @return void
     */
    public function test_contexts_are_the_requesters_user_context(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->add_task((int) $user->id);

        $this->assertSame(
            [\context_user::instance($user->id)->id],
            array_map('intval', provider::get_contexts_for_userid((int) $user->id)->get_contextids())
        );
        $this->assertCount(0, provider::get_contexts_for_userid((int) $other->id)->get_contextids());
    }

    /**
     * The user context lists its owner, and a user without tasks is not listed.
     *
     * @return void
     */
    public function test_users_in_context(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->add_task((int) $user->id);

        $list = new userlist(\context_user::instance($user->id), 'block_download_certificates');
        provider::get_users_in_context($list);
        $this->assertSame([(int) $user->id], array_map('intval', $list->get_userids()));

        $empty = new userlist(\context_user::instance($other->id), 'block_download_certificates');
        provider::get_users_in_context($empty);
        $this->assertCount(0, $empty->get_userids());
    }

    /**
     * One export per task, with formatted times and without the ZIP itself.
     *
     * @return void
     */
    public function test_export_writes_one_entry_per_task(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $first = $this->add_task((int) $user->id);
        $second = $this->add_task((int) $user->id);
        $this->add_task((int) $other->id);
        $context = \context_user::instance($user->id);

        provider::export_user_data(new approved_contextlist($user, 'block_download_certificates', [$context->id]));

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $name = get_string('privacy:path:tasks', 'block_download_certificates');
        $one = $writer->get_data([$name, (string) $first]);
        $two = $writer->get_data([$name, (string) $second]);
        $this->assertSame('course', $one->type);
        $this->assertSame('{"courseid":2}', $one->params);
        $this->assertSame(\core_privacy\local\request\transform::datetime(1700000000), $one->timecreated);
        $this->assertSame(\core_privacy\local\request\transform::datetime(1700000100), $two->timemodified);
        $this->assertEmpty(writer::with_context(\context_user::instance($other->id))->get_data([$name, (string) $first]));
    }

    /**
     * Deleting for all users in a context removes the owner's rows and ZIP, not another user's.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $mine = $this->add_file($this->add_task((int) $user->id));
        $theirs = $this->add_file($this->add_task((int) $other->id));

        provider::delete_data_for_all_users_in_context(\context_user::instance($user->id));

        $this->assertFalse($DB->record_exists(self::TABLE, ['userid' => $user->id]));
        $this->assertFileDoesNotExist($mine);
        $this->assertTrue($DB->record_exists(self::TABLE, ['userid' => $other->id]));
        $this->assertFileExists($theirs);
    }

    /**
     * Deleting for one user removes their rows and ZIP, not another user's.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $mine = $this->add_file($this->add_task((int) $user->id));
        $theirs = $this->add_file($this->add_task((int) $other->id));
        $context = \context_user::instance($user->id);

        provider::delete_data_for_user(new approved_contextlist($user, 'block_download_certificates', [$context->id]));

        $this->assertFalse($DB->record_exists(self::TABLE, ['userid' => $user->id]));
        $this->assertFileDoesNotExist($mine);
        $this->assertTrue($DB->record_exists(self::TABLE, ['userid' => $other->id]));
        $this->assertFileExists($theirs);
    }

    /**
     * Deleting for a user list removes the owner's rows and ZIP, not another user's.
     *
     * @return void
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $mine = $this->add_file($this->add_task((int) $user->id));
        $theirs = $this->add_file($this->add_task((int) $other->id));
        $context = \context_user::instance($user->id);

        provider::delete_data_for_users(new approved_userlist($context, 'block_download_certificates', [(int) $user->id]));

        $this->assertFalse($DB->record_exists(self::TABLE, ['userid' => $user->id]));
        $this->assertFileDoesNotExist($mine);
        $this->assertTrue($DB->record_exists(self::TABLE, ['userid' => $other->id]));
        $this->assertFileExists($theirs);
    }

    /**
     * A stored path outside the task directories is never unlinked, even inside the plugin's own temp
     * directory; the row and its task directory still go.
     *
     * @return void
     */
    public function test_a_path_outside_the_task_directories_is_not_unlinked(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $outside = make_temp_directory('block_download_certificates/async') . '/outside.zip';
        file_put_contents($outside, 'zip');
        $taskid = $this->add_task((int) $user->id, $outside);
        $directory = task_storage::make_directory($taskid);

        provider::delete_data_for_all_users_in_context(\context_user::instance($user->id));

        $this->assertFalse($DB->record_exists(self::TABLE, ['userid' => $user->id]));
        $this->assertDirectoryDoesNotExist($directory);
        $this->assertFileExists($outside);
        unlink($outside);
    }
}
