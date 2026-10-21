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
 * Tests for the background download storage.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

/**
 * Each task owns one directory named after its id; the frozen reference list lives there.
 *
 * Metadata stays in docblocks because the plugin still supports Moodle 4.5, whose moodle-cs cannot
 * read attributes and warns per method.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\local\task_storage
 */
final class task_storage_test extends \advanced_testcase {
    /**
     * Reset the temporary files after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Two tasks get two directories under the plugin's task base, and the archive lives in its own.
     */
    public function test_directory_per_task(): void {
        global $CFG;

        $one = task_storage::make_directory(101);
        $two = task_storage::make_directory(102);

        $this->assertNotSame($one, $two);
        $this->assertDirectoryExists($one);
        $this->assertDirectoryExists($two);
        $this->assertSame($CFG->tempdir . '/' . task_storage::BASEDIR . '/101', $one);
        $this->assertSame($one, task_storage::get_directory(101));
        $this->assertSame($one . '/certificates.zip', task_storage::get_zip_path(101));
        $this->assertSame($two, dirname(task_storage::get_zip_path(102)));
    }

    /**
     * The frozen list reads back as written, ids cast.
     */
    public function test_refs_round_trip(): void {
        $this->assertFalse(task_storage::has_refs(201));

        task_storage::write_refs(201, [['tool_certificate', '7'], ['customcert', 8]]);

        $this->assertTrue(task_storage::has_refs(201));
        $this->assertSame([['tool_certificate', 7], ['customcert', 8]], task_storage::read_refs(201));

        task_storage::write_refs(202, []);
        $this->assertSame([], task_storage::read_refs(202));
    }

    /**
     * A missing list fails with the localised error.
     */
    public function test_read_refs_missing(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('taskrefsunreadable', 'block_download_certificates'));
        task_storage::read_refs(301);
    }

    /**
     * A malformed list fails with the localised error, whatever its shape.
     */
    public function test_read_refs_malformed(): void {
        $directory = task_storage::make_directory(302);
        $bodies = ['not json', '{"a":1}', '[["customcert"]]', '[["customcert","8"]]', '[[3,8]]', '[1]'];

        foreach ($bodies as $body) {
            file_put_contents($directory . '/refs.json', $body);
            try {
                task_storage::read_refs(302);
                $this->fail('No exception for ' . $body);
            } catch (\moodle_exception $e) {
                $this->assertSame('taskrefsunreadable', $e->errorcode, $body);
            }
        }
    }

    /**
     * Deleting a task's directory leaves another task's untouched; deleting one that was never made
     * is harmless.
     */
    public function test_delete_leaves_other_task(): void {
        task_storage::write_refs(401, [['customcert', 1]]);
        task_storage::write_refs(402, [['customcert', 2]]);
        file_put_contents(task_storage::get_zip_path(402), 'zip');

        task_storage::delete(401);
        task_storage::delete(403);

        $this->assertDirectoryDoesNotExist(task_storage::get_directory(401));
        $this->assertSame([['customcert', 2]], task_storage::read_refs(402));
        $this->assertFileExists(task_storage::get_zip_path(402));
    }

    /**
     * Only existing paths inside the task base belong to the plugin's tasks.
     */
    public function test_owns_path(): void {
        global $CFG;

        $zippath = task_storage::get_zip_path(501);
        file_put_contents($zippath, 'zip');
        $outside = make_temp_directory('block_download_certificates') . '/elsewhere.zip';
        file_put_contents($outside, 'zip');

        $this->assertTrue(task_storage::owns_path($zippath));
        $this->assertFalse(task_storage::owns_path($outside));
        $this->assertFalse(task_storage::owns_path(task_storage::get_directory(501) . '/../../elsewhere.zip'));
        $this->assertFalse(task_storage::owns_path($CFG->tempdir . '/' . task_storage::BASEDIR));
        $this->assertFalse(task_storage::owns_path(task_storage::get_directory(501) . '/missing.zip'));
        $this->assertFalse(task_storage::owns_path(''));
    }

    /**
     * A task id that cannot be a row id is refused before any path is built.
     */
    public function test_invalid_task_id(): void {
        $this->expectException(\coding_exception::class);
        task_storage::get_directory(0);
    }
}
