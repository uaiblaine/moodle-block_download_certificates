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
 * Tests for the search web service.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\external;

use block_download_certificates\local\source\issue_factory;
use block_download_certificates\local\source\tool_certificate;
use core_external\external_api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/download_certificates/tests/fixtures/issue_factory.php');

/**
 * The search answers managers and teachers within their scope, through the AJAX entry point, and
 * refuses the guest and a user who opens no panel.
 *
 * The scope test skips, not passes, without tool_certificate.
 *
 * Metadata stays in docblock form so the test files stay identical across the plugin's branches.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\external\search_certificates
 */
final class search_certificates_test extends \advanced_testcase {
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
     * @param array $args The arguments.
     * @return array The response: error, and data or exception.
     */
    private function call(array $args = []): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function('block_download_certificates_search_certificates', $args, true);
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
     * A manager.
     *
     * @return \stdClass
     */
    private function manager(): \stdClass {
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id);
        return $manager;
    }

    /**
     * The guest account is refused; a manager is answered.
     */
    public function test_guest_is_refused(): void {
        $this->setGuestUser();
        $this->assertSame('noguest', $this->error_of($this->call()));

        $this->setUser($this->manager());
        $this->assertNull($this->error_of($this->call()));
    }

    /**
     * A learner, who teaches no course and holds no manager role, opens no panel and is refused; the
     * same user as a teacher of the course is answered.
     */
    public function test_learner_is_refused(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->assertSame('accessdenied', $this->error_of($this->call()));

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertNull($this->error_of($this->call()));
    }

    /**
     * A teacher gets the certificates of their course only, a manager those of every course; the
     * rows pass the return structure, names unescaped and the download link without a session key,
     * and a page size above 200 is cut to 200.
     */
    public function test_teacher_is_limited_to_their_scope(): void {
        if (!(new tool_certificate())->is_available()) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
        $gen = $this->getDataGenerator();
        $coursea = $gen->create_course(['fullname' => 'R&D <i>A</i>']);
        $courseb = $gen->create_course();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $coursea->id, 'editingteacher');
        $student = $gen->create_user(['firstname' => 'Ann & Co', 'lastname' => 'Smith']);
        $gen->enrol_user($student->id, $coursea->id, 'student');
        $stranger = $gen->create_user();
        $gen->enrol_user($stranger->id, $courseb->id, 'student');
        $template = issue_factory::tool_certificate_template('Gold')->get_id();
        issue_factory::tool_certificate_issue($template, (int) $student->id, (int) $coursea->id, 1000, 'CODEA');
        issue_factory::tool_certificate_issue($template, (int) $stranger->id, (int) $courseb->id, 2000, 'CODEB');

        $this->setUser($teacher);
        $response = $this->call(['perpage' => 5000]);
        $this->assertNull($this->error_of($response));
        $this->assertSame(1, $response['data']['total']);
        $this->assertSame(200, $response['data']['perpage']);
        $row = $response['data']['rows'][0];
        $this->assertSame('CODEA', $row['code']);
        $this->assertSame('Ann & Co Smith', $row['fullname']);
        $this->assertSame('R&D A', $row['coursename']);
        $this->assertStringNotContainsString('sesskey', $row['downloadurl']);

        // A filter narrows the teacher's scope and never widens it.
        $response = $this->call(['courseid' => (int) $courseb->id]);
        $this->assertSame(0, $response['data']['total']);

        $this->setUser($this->manager());
        $response = $this->call(['sort' => 'timecreated', 'order' => 'ASC']);
        $this->assertSame(2, $response['data']['total']);
        $this->assertSame(['CODEA', 'CODEB'], array_column($response['data']['rows'], 'code'));
        $response = $this->call(['courseid' => (int) $courseb->id]);
        $this->assertSame(['CODEB'], array_column($response['data']['rows'], 'code'));
    }
}
