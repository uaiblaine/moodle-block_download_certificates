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
 * Tests for the group menu web service.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\external;

use core_external\external_api;

/**
 * The group menu lists the groups of the caller's own courses by the scope's group rule, and
 * refuses the guest and a course outside the caller's courses.
 *
 * Metadata stays in docblocks because the plugin still supports Moodle 4.5, whose moodle-cs cannot
 * read attributes and warns per method.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\external\get_groups
 */
final class get_groups_test extends \advanced_testcase {
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
     * @param int $courseid The course.
     * @return array The response: error, and data or exception.
     */
    private function call(int $courseid): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function('block_download_certificates_get_groups', ['courseid' => $courseid], true);
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
     * The guest account is refused; a manager is answered for the same course.
     */
    public function test_guest_is_refused(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->setGuestUser();
        $this->assertSame('noguest', $this->error_of($this->call((int) $course->id)));

        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id);
        $this->setUser($manager);
        $this->assertNull($this->error_of($this->call((int) $course->id)));
    }

    /**
     * A non-editing teacher under separate groups gets their own group only, its name stripped of
     * tags but not escaped; the course they are only a learner in is refused.
     */
    public function test_teacher_gets_their_groups_of_their_courses(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['groupmode' => SEPARATEGROUPS]);
        $other = $gen->create_course();
        $mine = (int) $gen->create_group(['courseid' => $course->id, 'name' => 'Q&A <b>1</b>'])->id;
        $gen->create_group(['courseid' => $course->id]);
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'teacher');
        $gen->enrol_user($teacher->id, $other->id, 'student');
        $gen->create_group_member(['groupid' => $mine, 'userid' => $teacher->id]);
        $this->setUser($teacher);

        $response = $this->call((int) $course->id);
        $this->assertNull($this->error_of($response));
        $this->assertSame([['id' => $mine, 'name' => 'Q&A 1']], $response['data']['groups']);

        $this->assertSame('accessdenied', $this->error_of($this->call((int) $other->id)));
    }
}
