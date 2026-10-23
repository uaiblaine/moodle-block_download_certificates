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
 * Tests for the plugin's uninstall.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates;

use block_download_certificates\local\task_storage;

/**
 * The uninstall removes every task directory, with or without a row, and nothing outside them.
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
final class uninstall_test extends \advanced_testcase {
    /**
     * Reset the temporary files after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * The task directories and their base go; another temp directory stays.
     */
    public function test_uninstall_removes_the_task_directories(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/download_certificates/db/uninstall.php');

        $one = task_storage::make_directory(7);
        $two = task_storage::make_directory(8);
        file_put_contents(task_storage::get_zip_path(7), 'zip');
        $other = make_temp_directory('block_download_certificates_test_other');
        file_put_contents($other . '/keep.txt', 'keep');
        $this->assertFileExists($one . '/certificates.zip');
        $this->assertDirectoryExists($two);

        $this->assertTrue(xmldb_block_download_certificates_uninstall());

        $this->assertDirectoryDoesNotExist($one);
        $this->assertDirectoryDoesNotExist($two);
        $this->assertDirectoryDoesNotExist($CFG->tempdir . '/' . task_storage::BASEDIR);
        $this->assertFileExists($other . '/keep.txt');

        // Nothing to remove is not an error.
        $this->assertTrue(xmldb_block_download_certificates_uninstall());
    }
}
