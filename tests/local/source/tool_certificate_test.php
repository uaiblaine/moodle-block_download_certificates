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
 * Tests for the tool_certificate source.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local\source;

use block_download_certificates\local\repository;
use block_download_certificates\local\scope;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/download_certificates/tests/fixtures/issue_factory.php');

/**
 * Issues of tool_certificate: row shape, the stored PDF, file names and the single-download check.
 *
 * Skipped, not green, where tool_certificate is not installed.
 *
 * Metadata stays in docblock form so the test files stay identical across the plugin's branches.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\local\source\tool_certificate
 * @covers     \block_download_certificates\local\source\source
 * @covers     \block_download_certificates\local\source\registry
 * @covers     \block_download_certificates\local\repository
 */
final class tool_certificate_test extends \advanced_testcase {
    /** @var tool_certificate The source under test. */
    private tool_certificate $source;

    /**
     * Skip without tool_certificate; reset the database after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->source = new tool_certificate();
        if (!$this->source->is_available()) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
        $this->resetAfterTest();
    }

    /**
     * Issue a certificate through the plugin's generator, PDF included.
     *
     * @param \tool_certificate\template $template The template.
     * @param int $userid The owner.
     * @param int|null $courseid The course.
     * @return \stdClass The tool_certificate_issues record.
     */
    private function issue(\tool_certificate\template $template, int $userid, ?int $courseid = null): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('tool_certificate')
            ->issue($template, $userid, null, [], 'tool_certificate', $courseid);
    }

    /**
     * The registry lists the source when the plugin is installed.
     */
    public function test_registry_lists_source(): void {
        $this->assertInstanceOf(tool_certificate::class, registry::get('tool_certificate'));
        $this->assertArrayHasKey('tool_certificate', registry::get_available());
        $this->assertNull(registry::get('unknown'));
    }

    /**
     * get_issue() returns the shared shape; an issue without course has course 0 and no name.
     */
    public function test_get_issue_shape(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Chemistry']);
        $user = $this->getDataGenerator()->create_user();
        $template = issue_factory::tool_certificate_template('Gold');
        $withcourse = $this->issue($template, (int) $user->id, (int) $course->id);
        $without = $this->issue($template, (int) $user->id);

        $issue = $this->source->get_issue((int) $withcourse->id);
        $this->assertSame('tc_' . $withcourse->id, $issue->uniqueid);
        $this->assertSame((int) $withcourse->id, $issue->issueid);
        $this->assertSame('tool_certificate', $issue->type);
        $this->assertSame((int) $user->id, $issue->userid);
        $this->assertSame((int) $course->id, $issue->courseid);
        $this->assertSame('Chemistry', $issue->coursename);
        $this->assertSame('Gold', $issue->templatename);
        $this->assertSame($withcourse->code, $issue->code);
        $this->assertSame((int) $withcourse->timecreated, $issue->timecreated);

        $issue = $this->source->get_issue((int) $without->id);
        $this->assertSame(0, $issue->courseid);
        $this->assertSame('', $issue->coursename);

        $this->assertNull($this->source->get_issue((int) $without->id + 1000));
    }

    /**
     * The content is the plugin's stored issue file; a missing file is recreated by the plugin.
     */
    public function test_get_content_returns_stored_pdf(): void {
        $user = $this->getDataGenerator()->create_user();
        $template = issue_factory::tool_certificate_template('Silver', true);
        $record = $this->issue($template, (int) $user->id);
        $issue = $this->source->get_issue((int) $record->id);

        $file = $this->source->get_content($issue);
        $this->assertInstanceOf(\stored_file::class, $file);
        $this->assertSame('tool_certificate', $file->get_component());
        $this->assertSame('issues', $file->get_filearea());
        $this->assertSame((int) $record->id, (int) $file->get_itemid());
        $this->assertSame($record->code . '.pdf', $file->get_filename());
        $this->assertStringStartsWith('%PDF', $file->get_content());

        $file->delete();
        $recreated = $this->source->get_content($issue);
        $this->assertStringStartsWith('%PDF', $recreated->get_content());
    }

    /**
     * An issue without code has no file name to look up and yields no PDF.
     */
    public function test_get_content_without_code(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $template = issue_factory::tool_certificate_template('Bronze', true);
        $record = $this->issue($template, (int) $user->id);
        $DB->set_field('tool_certificate_issues', 'code', '', ['id' => $record->id]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('nopdf', 'block_download_certificates'));
        $this->source->get_content($this->source->get_issue((int) $record->id));
    }

    /**
     * The file name joins the owner's full name, the template and the code.
     */
    public function test_get_filename(): void {
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace']);
        $template = issue_factory::tool_certificate_template('Gold: level/2');
        $issueid = issue_factory::tool_certificate_issue($template->get_id(), (int) $user->id, 0, 1000, 'ABC123');

        $filename = $this->source->get_filename($this->source->get_issue($issueid));

        $this->assertSame(clean_filename('Ada Lovelace_Gold: level/2_ABC123.pdf'), $filename);
        $this->assertStringNotContainsString('/', $filename);
    }

    /**
     * A single download is refused outside the scope and served inside it; an unknown id, or an id
     * asked of the wrong source, is reported as not found.
     */
    public function test_single_download_scope_check(): void {
        $gen = $this->getDataGenerator();
        $coursea = $gen->create_course();
        $courseb = $gen->create_course();
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $coursea->id, 'teacher');
        $gen->enrol_user($student->id, $coursea->id, 'student');
        $gen->enrol_user($student->id, $courseb->id, 'student');
        $template = issue_factory::tool_certificate_template('Gold', true);
        $ina = $this->issue($template, (int) $student->id, (int) $coursea->id);
        $inb = $this->issue($template, (int) $student->id, (int) $courseb->id);
        $repository = new repository(['tool_certificate' => $this->source]);
        $scope = scope::for_user((int) $teacher->id);

        $issue = $repository->get_issue_for_download($scope, 'tool_certificate', (int) $ina->id);
        $this->assertSame((int) $ina->id, $issue->issueid);
        $this->assertStringStartsWith('%PDF', $this->source->get_content($issue)->get_content());

        $this->assert_refused($repository, $scope, 'tool_certificate', (int) $inb->id, 'accessdenied');
        $this->assert_refused($repository, $scope, 'tool_certificate', (int) $inb->id + 1000, 'certificatenotfound');
        $this->assert_refused($repository, $scope, 'customcert', (int) $ina->id, 'certificatenotfound');
    }

    /**
     * Assert that a single download is refused with an error code.
     *
     * @param repository $repository The repository.
     * @param scope $scope The scope.
     * @param string $type The source type.
     * @param int $issueid The issue id.
     * @param string $errorcode The expected error code.
     */
    private function assert_refused(repository $repository, scope $scope, string $type, int $issueid, string $errorcode): void {
        try {
            $repository->get_issue_for_download($scope, $type, $issueid);
            $this->fail("No exception for {$type} {$issueid}");
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
    }
}
