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
 * Tests for the request-path downloads.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

use block_download_certificates\local\source\issue_factory;
use block_download_certificates\local\source\tool_certificate;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/download_certificates/tests/fixtures/issue_factory.php');

/**
 * A request builds a ZIP only up to the synchronous limit, in its own request directory.
 *
 * Skipped, not green, where tool_certificate is not installed. The send_* methods end the request
 * and are not called here; the checks they rely on are tested in script_guards_test and request_test.
 *
 * Metadata stays in docblock form so the test files stay identical across the plugin's branches.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\local\download
 */
final class download_test extends \advanced_testcase {
    /** @var repository The repository over tool_certificate. */
    private repository $repository;

    /** @var int The owner of the issues. */
    private int $userid;

    /**
     * Skip without tool_certificate; reset the database after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!(new tool_certificate())->is_available()) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
        $this->resetAfterTest();
        $this->repository = new repository(['tool_certificate' => new tool_certificate()]);
        $this->userid = (int) $this->getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace'])->id;
    }

    /**
     * Insert issues of the owner from one template.
     *
     * @param int $count How many.
     * @param bool $withpage Whether the template renders a PDF.
     */
    private function issues(int $count, bool $withpage): void {
        $template = issue_factory::tool_certificate_template('Gold', $withpage)->get_id();
        for ($i = 1; $i <= $count; $i++) {
            issue_factory::tool_certificate_issue($template, $this->userid, 0, 1000 + $i, 'CODE' . $i);
        }
    }

    /**
     * Above the limit the request refuses, naming the count and the limit, before building anything.
     */
    public function test_build_zip_refuses_above_limit(): void {
        $this->issues(download::SYNC_LIMIT + 1, false);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string(
            'toomanyforsync',
            'block_download_certificates',
            (object) ['count' => download::SYNC_LIMIT + 1, 'limit' => download::SYNC_LIMIT]
        ));
        download::build_zip(scope::for_user($this->userid), new filter(), $this->repository);
    }

    /**
     * An empty selection is refused: the one issue predates the filter's first day.
     */
    public function test_build_zip_refuses_empty_selection(): void {
        $this->issues(1, false);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('nocertificates', 'block_download_certificates'));
        download::build_zip(scope::for_user($this->userid), filter::from_array(['datefrom' => 5000]), $this->repository);
    }

    /**
     * At the limit the request still builds: a template without page renders no PDF, so every issue
     * becomes an errors.txt line instead of a refusal.
     */
    public function test_build_zip_at_limit_is_not_refused(): void {
        $this->issues(download::SYNC_LIMIT, false);

        [$zippath, $errors] = download::build_zip(scope::for_user($this->userid), new filter(), $this->repository);

        $this->assertCount(download::SYNC_LIMIT, $errors);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zippath));
        $this->assertSame(1, $zip->numFiles);
        $this->assertSame(zip_builder::ERRORS_ENTRY, $zip->getNameIndex(0));
        $zip->close();
    }

    /**
     * The ZIP holds one PDF per issue, named after owner, template and code, oldest first.
     */
    public function test_build_zip_contents(): void {
        $this->issues(2, true);

        [$zippath, $errors] = download::build_zip(scope::for_user($this->userid), new filter(), $this->repository);

        $this->assertSame([], $errors);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zippath));
        $this->assertSame(2, $zip->numFiles);
        $this->assertSame('tool_certificate/' . clean_filename('Ada Lovelace_Gold_CODE1.pdf'), $zip->getNameIndex(0));
        $this->assertSame('tool_certificate/' . clean_filename('Ada Lovelace_Gold_CODE2.pdf'), $zip->getNameIndex(1));
        $this->assertStringStartsWith('%PDF', $zip->getFromIndex(0));
        $zip->close();
    }
}
