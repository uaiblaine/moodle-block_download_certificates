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
 * Tests for the web service that queues background ZIP downloads.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\external;

use block_download_certificates\event\zip_requested;
use block_download_certificates\local\source\issue_factory;
use block_download_certificates\local\source\tool_certificate;
use block_download_certificates\local\zip_tasks;
use core_external\external_api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/download_certificates/tests/fixtures/issue_factory.php');

/**
 * A request is queued only when the caller's scope allows it, and each queued request, and only
 * those, is logged with the task row as its object and the requester's user context.
 *
 * The tests that queue a task skip, not pass, without tool_certificate: an empty selection is
 * refused before a row is written.
 *
 * Metadata stays in docblocks because the plugin still supports Moodle 4.5, whose moodle-cs cannot
 * read attributes and warns per method.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\external\create_task
 */
final class create_task_test extends \advanced_testcase {
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
    private function call(array $args): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function('block_download_certificates_create_task', $args, true);
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
     * Skip without tool_certificate.
     */
    private function require_tool_certificate(): void {
        if (!(new tool_certificate())->is_available()) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
    }

    /**
     * The zip_requested events of a call.
     *
     * @param array $args The arguments.
     * @return array [array $response, zip_requested[] $events]
     */
    private function call_with_events(array $args): array {
        $sink = $this->redirectEvents();
        $response = $this->call($args);
        $events = array_values(array_filter($sink->get_events(), fn($event) => $event instanceof zip_requested));
        $sink->close();
        return [$response, $events];
    }

    /**
     * The guest account is refused before its request is checked or anything is written; a manager's
     * request of the same type gets past that guard.
     */
    public function test_guest_is_refused(): void {
        global $DB;

        $this->setGuestUser();
        [$response, $events] = $this->call_with_events(['type' => 'all']);
        $this->assertSame('noguest', $this->error_of($response));
        $this->assertSame([], $events);
        $this->assertSame(0, $DB->count_records(zip_tasks::TABLE));

        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id);
        $this->setUser($manager);
        $this->assertNotSame('noguest', $this->error_of($this->call(['type' => 'all'])));
    }

    /**
     * A teacher's course request is queued and logged once, with the task row as its object and the
     * teacher's user context; a request for every certificate is refused and logs nothing.
     */
    public function test_teacher_request_is_queued_and_logged(): void {
        global $DB;

        $this->require_tool_certificate();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $template = issue_factory::tool_certificate_template('Gold')->get_id();
        issue_factory::tool_certificate_issue($template, (int) $student->id, (int) $course->id, 1000, 'CODE1');
        $this->setUser($teacher);

        [$response, $events] = $this->call_with_events(['type' => 'all']);
        $this->assertSame('accessdenied', $this->error_of($response));
        $this->assertSame([], $events);
        $this->assertSame(0, $DB->count_records(zip_tasks::TABLE));

        [$response, $events] = $this->call_with_events(['type' => 'course', 'courseid' => (int) $course->id]);
        $this->assertNull($this->error_of($response));
        $this->assertSame(1, $response['data']['total']);
        $taskid = $response['data']['taskid'];
        $row = $DB->get_record(zip_tasks::TABLE, ['id' => $taskid], '*', MUST_EXIST);
        $this->assertSame((int) $teacher->id, (int) $row->userid);
        $this->assertSame('course', $row->type);

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame($taskid, (int) $event->objectid);
        $this->assertSame(zip_tasks::TABLE, $event->objecttable);
        $this->assertSame(\context_user::instance((int) $teacher->id)->id, $event->get_context()->id);
        $this->assertSame((int) $teacher->id, (int) $event->userid);
        $this->assertSame('course', $event->other['type']);
        // A course request names the course, and no related user.
        $this->assertSame((int) $course->id, (int) $event->other['courseid']);
        $this->assertArrayNotHasKey('groupid', $event->other);
        $this->assertArrayNotHasKey('startdate', $event->other);
        $this->assertEmpty($event->relateduserid);
    }

    /**
     * A user request relates the event to the owner and a range request carries its days; the
     * controls above and here show a course request does neither.
     */
    public function test_event_names_the_user_and_the_days(): void {
        $this->require_tool_certificate();
        $manager = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);
        $this->getDataGenerator()->role_assign('manager', $manager->id);
        $owner = $this->getDataGenerator()->create_user();
        $template = issue_factory::tool_certificate_template('Gold')->get_id();
        issue_factory::tool_certificate_issue($template, (int) $owner->id, 0, gmmktime(12, 0, 0, 3, 10, 2026), 'IN');
        $this->setUser($manager);

        [$response, $events] = $this->call_with_events(['type' => 'user', 'userid' => (int) $owner->id]);
        $this->assertNull($this->error_of($response));
        $this->assertCount(1, $events);
        $this->assertSame((int) $owner->id, (int) $events[0]->relateduserid);
        $this->assertSame('user', $events[0]->other['type']);
        $this->assertArrayNotHasKey('startdate', $events[0]->other);

        [$response, $events] = $this->call_with_events([
            'type' => 'range',
            'startdate' => '2026-03-10',
            'enddate' => '2026-03-11',
        ]);
        $this->assertNull($this->error_of($response));
        $this->assertCount(1, $events);
        $this->assertSame('2026-03-10', $events[0]->other['startdate']);
        $this->assertSame('2026-03-11', $events[0]->other['enddate']);
        $this->assertEmpty($events[0]->relateduserid);
    }

    /**
     * A learner may queue their own certificates and nobody else's.
     */
    public function test_learner_is_kept_to_their_own(): void {
        $this->require_tool_certificate();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $learner = $gen->create_user();
        $other = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id, 'student');
        $gen->enrol_user($other->id, $course->id, 'student');
        $template = issue_factory::tool_certificate_template('Gold')->get_id();
        issue_factory::tool_certificate_issue($template, (int) $learner->id, (int) $course->id, 1000, 'MINE');
        issue_factory::tool_certificate_issue($template, (int) $other->id, (int) $course->id, 1000, 'THEIRS');
        $this->setUser($learner);

        $this->assertSame('accessdenied', $this->error_of($this->call(['type' => 'user', 'userid' => (int) $other->id])));
        $this->assertSame('accessdenied', $this->error_of($this->call(['type' => 'course', 'courseid' => (int) $course->id])));
        $response = $this->call(['type' => 'user', 'userid' => (int) $learner->id]);
        $this->assertNull($this->error_of($response));
        $this->assertSame(1, $response['data']['total']);
    }

    /**
     * A range request carries the days the browser picked; a malformed day is refused.
     */
    public function test_range_days(): void {
        $this->require_tool_certificate();
        $manager = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);
        $this->getDataGenerator()->role_assign('manager', $manager->id);
        $owner = $this->getDataGenerator()->create_user();
        $template = issue_factory::tool_certificate_template('Gold')->get_id();
        issue_factory::tool_certificate_issue($template, (int) $owner->id, 0, gmmktime(12, 0, 0, 3, 10, 2026), 'IN');
        issue_factory::tool_certificate_issue($template, (int) $owner->id, 0, gmmktime(12, 0, 0, 3, 12, 2026), 'OUT');
        $this->setUser($manager);

        $this->assertSame(
            'invalidaterange',
            $this->error_of($this->call(['type' => 'range', 'startdate' => '2026-02-31', 'enddate' => '2026-03-10']))
        );
        $response = $this->call(['type' => 'range', 'startdate' => '2026-03-10', 'enddate' => '2026-03-11']);
        $this->assertNull($this->error_of($response));
        $this->assertSame(1, $response['data']['total']);
    }
}
