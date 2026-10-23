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
 * Tests for the guards the download scripts delegate to.
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
 * `download_precise.php` is a read without a session key: the scope alone decides, through
 * {@see repository::get_issue_for_download()}. `download_user.php` serves a user's own certificates
 * without any block capability, through {@see request::check_zip()}. The ZIP pages'
 * POST-only key is {@see request::require_post_sesskey()}, tested in request_test.
 *
 * The single download test skips, not passes, without tool_certificate.
 *
 * Metadata stays in docblock form so the test files stay identical across the plugin's branches.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\local\repository
 * @covers     \block_download_certificates\local\request
 */
final class script_guards_test extends \advanced_testcase {
    /**
     * Reset the database after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * The error code a callable throws, or null when it throws nothing.
     *
     * @param callable $callable The code under test.
     * @return string|null
     */
    private function error_of(callable $callable): ?string {
        try {
            $callable();
        } catch (\moodle_exception $e) {
            return $e->errorcode;
        }
        return null;
    }

    /**
     * A teacher's single download reaches a certificate of their course and not one of another
     * course; the owner reaches their own, and an unknown issue id is reported as not found.
     */
    public function test_single_download_follows_the_scope(): void {
        if (!(new tool_certificate())->is_available()) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
        $gen = $this->getDataGenerator();
        $coursea = $gen->create_course();
        $courseb = $gen->create_course();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $coursea->id, 'editingteacher');
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $coursea->id, 'student');
        $gen->enrol_user($student->id, $courseb->id, 'student');
        $template = issue_factory::tool_certificate_template('Gold')->get_id();
        $ina = issue_factory::tool_certificate_issue($template, (int) $student->id, (int) $coursea->id, 1000, 'A');
        $inb = issue_factory::tool_certificate_issue($template, (int) $student->id, (int) $courseb->id, 1000, 'B');
        $repository = new repository(['tool_certificate' => new tool_certificate()]);
        $teacherscope = scope::for_user((int) $teacher->id);

        $this->assertSame($ina, $repository->get_issue_for_download($teacherscope, 'tool_certificate', $ina)->issueid);
        $this->assertSame(
            'accessdenied',
            $this->error_of(fn() => $repository->get_issue_for_download($teacherscope, 'tool_certificate', $inb))
        );
        $this->assertSame(
            $inb,
            $repository->get_issue_for_download(scope::for_user((int) $student->id), 'tool_certificate', $inb)->issueid
        );
        $this->assertSame(
            'certificatenotfound',
            $this->error_of(fn() => $repository->get_issue_for_download($teacherscope, 'tool_certificate', $inb + 1000))
        );
    }

    /**
     * A user holding no block capability downloads their own certificates, and still not another
     * user's.
     */
    public function test_own_certificates_need_no_block_capability(): void {
        $user = (int) $this->getDataGenerator()->create_user()->id;
        $other = (int) $this->getDataGenerator()->create_user()->id;
        $scope = scope::for_user($user);

        $this->assertNull($this->error_of(fn() => request::check_zip($scope, 'user', new filter(userid: $user))));
        $this->assertSame('accessdenied', $this->error_of(fn() => request::check_zip($scope, 'user', new filter(userid: $other))));
    }
}
