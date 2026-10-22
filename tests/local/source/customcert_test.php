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
 * Tests for the mod_customcert source.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local\source;

use block_download_certificates\local\filter;
use block_download_certificates\local\repository;
use block_download_certificates\local\scope;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/download_certificates/tests/fixtures/issue_factory.php');

/**
 * Issues of mod_customcert: row shape, the rendered PDF and the single-download check.
 *
 * Skipped, not green, where mod_customcert is not installed. The PDF test also proves that no
 * deprecated customcert API is called: an unexpected debugging() notice fails the test.
 *
 * Metadata stays in docblocks because the plugin still supports Moodle 4.5, whose moodle-cs cannot
 * read attributes and warns per method.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\local\source\customcert
 * @covers     \block_download_certificates\local\source\source
 * @covers     \block_download_certificates\local\repository
 */
final class customcert_test extends \advanced_testcase {
    /** @var customcert The source under test. */
    private customcert $source;

    /**
     * Skip without mod_customcert; reset the database after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->source = new customcert();
        if (!$this->source->is_available()) {
            $this->markTestSkipped('mod_customcert is not installed.');
        }
        $this->resetAfterTest();
    }

    /**
     * get_issue() returns the shared shape, with the activity name as template name.
     */
    public function test_get_issue_shape(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Physics']);
        $user = $this->getDataGenerator()->create_user();
        $instance = issue_factory::customcert_instance((int) $course->id, 'Completion award');
        $issueid = issue_factory::customcert_issue((int) $instance->id, (int) $user->id, 1234, 'XYZ789');

        $issue = $this->source->get_issue($issueid);

        $this->assertSame('cc_' . $issueid, $issue->uniqueid);
        $this->assertSame($issueid, $issue->issueid);
        $this->assertSame('customcert', $issue->type);
        $this->assertSame((int) $user->id, $issue->userid);
        $this->assertSame((int) $course->id, $issue->courseid);
        $this->assertSame('Physics', $issue->coursename);
        $this->assertSame('Completion award', $issue->templatename);
        $this->assertSame('XYZ789', $issue->code);
        $this->assertSame(1234, $issue->timecreated);
        $this->assertNull($this->source->get_issue($issueid + 1000));
    }

    /**
     * The content is the PDF the activity's template renders for the owner.
     */
    public function test_get_content_renders_pdf(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $instance = issue_factory::customcert_instance((int) $course->id, 'Award');
        $issueid = issue_factory::customcert_issue((int) $instance->id, (int) $user->id, 1000, 'PDF123');

        $content = $this->source->get_content($this->source->get_issue($issueid));

        $this->assertIsString($content);
        $this->assertStringStartsWith('%PDF', $content);
    }

    /**
     * A single download is refused outside the scope and served inside it.
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
        $ina = issue_factory::customcert_issue(
            (int) issue_factory::customcert_instance((int) $coursea->id, 'A')->id,
            (int) $student->id,
            1000,
            'INA001'
        );
        $inb = issue_factory::customcert_issue(
            (int) issue_factory::customcert_instance((int) $courseb->id, 'B')->id,
            (int) $student->id,
            1000,
            'INB001'
        );
        $repository = new repository(['customcert' => $this->source]);
        $scope = scope::for_user((int) $teacher->id);

        $issue = $repository->get_issue_for_download($scope, 'customcert', $ina);
        $this->assertSame($ina, $issue->issueid);
        $this->assertStringStartsWith('%PDF', $this->source->get_content($issue));

        try {
            $repository->get_issue_for_download($scope, 'customcert', $inb);
            $this->fail('No exception for an issue outside the scope.');
        } catch (\moodle_exception $e) {
            $this->assertSame('accessdenied', $e->errorcode);
        }
    }

    /**
     * A deleted user's customcert issue disappears from the counts, the search and the single read;
     * an active user's issue beside it stays.
     */
    public function test_deleted_users_excluded(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $manager = $gen->create_user();
        $gen->role_assign('manager', $manager->id);
        $active = $gen->create_user();
        $leaving = $gen->create_user();
        $instance = issue_factory::customcert_instance((int) $course->id, 'Award');
        $kept = issue_factory::customcert_issue((int) $instance->id, (int) $active->id, 1000, 'KEPT01');
        $gone = issue_factory::customcert_issue((int) $instance->id, (int) $leaving->id, 1001, 'GONE01');
        $repository = new repository(['customcert' => $this->source]);
        $scope = scope::for_user((int) $manager->id);
        $this->assertSame(2, $repository->count($scope, new filter()));
        $this->assertNotNull($this->source->get_issue($gone));

        delete_user(\core_user::get_user((int) $leaving->id));

        $this->assertSame(1, $repository->count($scope, new filter()));
        $rows = $repository->search($scope, new filter(), 'timecreated', 'ASC', 0, 0);
        $this->assertSame([$kept], array_map(fn($row) => (int) $row->issueid, $rows));
        $this->assertNull($this->source->get_issue($gone));
        $this->assertNotNull($this->source->get_issue($kept));
    }
}
