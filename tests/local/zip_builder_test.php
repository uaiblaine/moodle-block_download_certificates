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
 * Tests for the ZIP builder.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

use block_download_certificates\local\source\fake_source;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/download_certificates/tests/fixtures/fake_source.php');

/**
 * The builder names entries uniquely and survives failing issues; it runs on an in-memory source,
 * so no certificate plugin is needed.
 *
 * Metadata stays in docblocks because the plugin still supports Moodle 4.5, whose moodle-cs cannot
 * read attributes and warns per method.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\local\zip_builder
 */
final class zip_builder_test extends \advanced_testcase {
    /** @var fake_source The source under the builder. */
    private fake_source $source;

    /** @var string The archive path. */
    private string $zippath;

    /**
     * Fresh source and archive path for every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->source = new fake_source();
        $this->zippath = make_request_directory() . '/test.zip';
    }

    /**
     * The archive entries, name => content.
     *
     * @return array
     */
    private function entries(): array {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($this->zippath));
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $entries[$name] = $zip->getFromIndex($i);
        }
        $zip->close();
        ksort($entries);
        return $entries;
    }

    /**
     * Equal names get a counter, compared without case, also across two builds of one archive.
     */
    public function test_entry_names_are_deduplicated(): void {
        $this->source->add_issue(1, 'Ada Lovelace_Course_X.pdf');
        $this->source->add_issue(2, 'Ada Lovelace_Course_X.pdf');
        $this->source->add_issue(3, 'ada lovelace_course_x.pdf');
        $this->source->add_issue(4, 'Ada Lovelace_Course_X.pdf');
        $builder = new zip_builder(['fake' => $this->source]);

        $errors = $builder->build([['fake', 1], ['fake', 2], ['fake', 3]], $this->zippath);
        $this->assertSame([], $errors);
        $errors = $builder->build([['fake', 4]], $this->zippath);
        $this->assertSame([], $errors);

        $this->assertSame([
            'fake/Ada Lovelace_Course_X (2).pdf' => '%PDF-fake 2',
            'fake/Ada Lovelace_Course_X (4).pdf' => '%PDF-fake 4',
            'fake/Ada Lovelace_Course_X.pdf' => '%PDF-fake 1',
            'fake/ada lovelace_course_x (3).pdf' => '%PDF-fake 3',
        ], $this->entries());
    }

    /**
     * A failing issue leaves the others in the archive and one localised line in errors.txt, with no
     * text from foreign exceptions; an unknown issue and an unavailable source get a line each too,
     * and the progress callback counts every reference.
     */
    public function test_failing_issue_keeps_the_others(): void {
        $this->source->add_issue(1, 'one.pdf');
        $this->source->add_issue(2, 'two.pdf');
        $this->source->add_issue(3, 'three.pdf');
        $this->source->add_issue(4, 'four.pdf');
        $this->source->failures[2] = new \moodle_exception('nopdf', 'block_download_certificates');
        $this->source->failures[3] = new \dml_read_exception('secretdberror', 'SELECT secretcolumn FROM {secrettable}');
        $progress = [];

        $errors = (new zip_builder(['fake' => $this->source]))->build(
            [['fake', 1], ['fake', 2], ['fake', 3], ['fake', 99], ['other', 5], ['fake', 4]],
            $this->zippath,
            function (int $processed) use (&$progress): void {
                $progress[] = $processed;
            }
        );

        $line = fn(string $type, int $id, string $reason): string => get_string(
            'zip_error_line',
            'block_download_certificates',
            (object) ['type' => $type, 'issueid' => $id, 'reason' => $reason]
        );
        $expected = [
            $line('fake', 2, get_string('nopdf', 'block_download_certificates')),
            $line('fake', 3, get_string('zip_error_unexpected', 'block_download_certificates')),
            $line('fake', 99, get_string('certificatenotfound', 'block_download_certificates')),
            $line('other', 5, get_string('sourceunavailable', 'block_download_certificates', 'other')),
        ];
        $this->assertSame($expected, $errors);
        $this->assertSame([1, 2, 3, 4, 5, 6], $progress);

        $entries = $this->entries();
        $this->assertSame(['errors.txt', 'fake/four.pdf', 'fake/one.pdf'], array_keys($entries));
        $this->assertSame('%PDF-fake 1', $entries['fake/one.pdf']);
        $this->assertSame('%PDF-fake 4', $entries['fake/four.pdf']);
        $this->assertSame(implode("\n", $expected) . "\n", $entries['errors.txt']);
        $this->assertStringNotContainsString('secret', $entries['errors.txt']);
    }

    /**
     * errors.txt keeps the lines of earlier builds of the same archive.
     */
    public function test_errors_accumulate_across_builds(): void {
        $this->source->add_issue(1, 'one.pdf');
        $builder = new zip_builder(['fake' => $this->source]);

        $builder->build([['fake', 1], ['fake', 7]], $this->zippath);
        $builder->build([['fake', 8]], $this->zippath);

        $lines = explode("\n", rtrim($this->entries()['errors.txt'], "\n"));
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('7', $lines[0]);
        $this->assertStringContainsString('8', $lines[1]);
    }

    /**
     * A stored file goes into the archive through a temporary copy, removed once the archive closes.
     */
    public function test_stored_file_added_through_temporary_copy(): void {
        global $CFG;

        $fs = get_file_storage();
        $this->source->add_issue(1, 'stored.pdf');
        $this->source->stored[1] = $fs->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'block_download_certificates',
            'filearea' => 'test',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'stored.pdf',
        ], '%PDF-stored');

        $errors = (new zip_builder(['fake' => $this->source]))->build([['fake', 1]], $this->zippath);

        $this->assertSame([], $errors);
        $this->assertSame(['fake/stored.pdf' => '%PDF-stored'], $this->entries());
        $partsdir = $CFG->tempdir . '/block_download_certificates/parts';
        $this->assertSame([], is_dir($partsdir) ? array_values(array_diff(scandir($partsdir), ['.', '..'])) : []);
    }

    /**
     * Nothing is written outside an open archive.
     */
    public function test_add_requires_open_archive(): void {
        $issue = $this->source->add_issue(1, 'one.pdf');

        $this->expectException(\coding_exception::class);
        (new zip_builder(['fake' => $this->source]))->add($this->source, $issue);
    }
}
