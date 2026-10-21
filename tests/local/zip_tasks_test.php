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
 * Tests for the request side of background ZIP downloads.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

use block_download_certificates\local\source\issue_factory;
use block_download_certificates\local\source\tool_certificate;
use block_download_certificates\task\generate_zip;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/download_certificates/tests/fixtures/issue_factory.php');

/**
 * A task row is created only for a request the scope allows, stores the filter and never a scope,
 * and hands its archive over once, to its owner only.
 *
 * Only the creation test needs a certificate plugin; it skips, not passes, without tool_certificate.
 *
 * Metadata stays in docblocks because the plugin still supports Moodle 4.5, whose moodle-cs cannot
 * read attributes and warns per method.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\local\zip_tasks
 */
final class zip_tasks_test extends \advanced_testcase {
    /**
     * Reset the database after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Insert a task row.
     *
     * @param int $userid The owner.
     * @param string $status The status.
     * @param int $courseid The course of the row's `course` request.
     * @return int The row id.
     */
    private function add_task(int $userid, string $status, int $courseid = 5): int {
        global $DB;

        return (int) $DB->insert_record(zip_tasks::TABLE, (object) [
            'userid' => $userid,
            'type' => 'course',
            'params' => json_encode((new filter(courseid: $courseid))->to_array()),
            'status' => $status,
            'progress' => 0,
            'total' => 1,
            'batchoffset' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
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
     * A teacher's request for every certificate, a cohort or a date range is refused before any row
     * is written.
     */
    public function test_create_refuses_a_teachers_manager_types(): void {
        global $DB;

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $scope = scope::for_user((int) $teacher->id);
        $requests = [
            'all' => new filter(),
            'cohort' => new filter(cohortid: (int) $gen->create_cohort()->id),
            'range' => new filter(datefrom: 1000, dateto: 2000),
        ];

        foreach ($requests as $type => $filter) {
            $this->assertSame('accessdenied', $this->error_of(fn() => zip_tasks::create($scope, $type, $filter)), $type);
        }
        $this->assertSame(0, $DB->count_records(zip_tasks::TABLE));
        $this->assertCount(0, \core\task\manager::get_adhoc_tasks(generate_zip::class));
    }

    /**
     * A created row holds the requester, the type and the cleaned filter only, and its task carries
     * nothing but the row id.
     */
    public function test_create_stores_the_type_and_filter_only(): void {
        global $DB;

        if (!(new tool_certificate())->is_available()) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');
        $template = issue_factory::tool_certificate_template('Gold')->get_id();
        issue_factory::tool_certificate_issue($template, (int) $student->id, (int) $course->id, 1000, 'CODE1');
        $repository = new repository(['tool_certificate' => new tool_certificate()]);

        $filter = request::filter_for_type('course', ['courseid' => $course->id, 'userid' => $student->id, 'scope' => 'all']);
        $record = zip_tasks::create(scope::for_user((int) $teacher->id), 'course', $filter, $repository);

        $row = $DB->get_record(zip_tasks::TABLE, ['id' => $record->id]);
        $this->assertSame((int) $teacher->id, (int) $row->userid);
        $this->assertSame('course', $row->type);
        $this->assertSame(zip_tasks::STATUS_PENDING, $row->status);
        $this->assertSame(1, (int) $row->total);
        $this->assertSame((new filter(courseid: (int) $course->id))->to_array(), json_decode($row->params, true));
        $tasks = \core\task\manager::get_adhoc_tasks(generate_zip::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertEquals((object) ['taskid' => $record->id], $task->get_custom_data());
        $this->assertEmpty($task->get_userid());

        // A request with nothing to zip writes no row.
        $empty = new filter(courseid: (int) $course->id, datefrom: 5000);
        $this->assertSame('nocertificates', $this->error_of(fn() => zip_tasks::create(
            scope::for_user((int) $teacher->id),
            'course',
            $empty,
            $repository
        )));
        $this->assertSame(1, $DB->count_records(zip_tasks::TABLE));
    }

    /**
     * Another user's row is neither reported nor handed over; a row that is not ready is not handed
     * over; a ready row's archive comes from its own task directory.
     */
    public function test_status_and_download_are_for_the_owner_only(): void {
        $course = $this->getDataGenerator()->create_course();
        $owner = (int) $this->getDataGenerator()->create_user()->id;
        $this->getDataGenerator()->enrol_user($owner, $course->id, 'editingteacher');
        $other = (int) $this->getDataGenerator()->create_user()->id;
        $ready = $this->add_task($owner, zip_tasks::STATUS_READY, (int) $course->id);
        file_put_contents(task_storage::get_zip_path($ready), 'zip');
        $pending = $this->add_task($owner, zip_tasks::STATUS_PENDING, (int) $course->id);

        // Another user's row answers as a missing one, so ids cannot be probed.
        $this->assertSame('tasknotfound', $this->error_of(fn() => zip_tasks::get_status($ready, $other)));
        $this->assertSame('tasknotfound', $this->error_of(fn() => zip_tasks::prepare_download($ready, $other)));
        $this->assertSame('tasknotfound', $this->error_of(fn() => zip_tasks::prepare_download($ready + 1000, $owner)));
        $this->assertSame('tasknotready', $this->error_of(fn() => zip_tasks::prepare_download($pending, $owner)));

        [$zippath, $filename] = zip_tasks::prepare_download($ready, $owner);
        $this->assertSame(task_storage::get_zip_path($ready), $zippath);
        $this->assertSame('certificates_course_' . $course->id . '.zip', $filename);
        $status = zip_tasks::get_status($ready, $owner);
        $this->assertSame(zip_tasks::STATUS_READY, $status['status']);
        $this->assertSame('', $status['errormessage']);
        // The download script takes the task id and the session key in a POST body: the URL carries neither.
        $url = new \moodle_url($status['downloadurl']);
        $this->assertStringEndsWith('/blocks/download_certificates/ajax/download_zip.php', $url->get_path(false));
        $this->assertSame([], $url->params());
        $this->assertSame('', zip_tasks::get_status($pending, $owner)['downloadurl']);
    }

    /**
     * A ready archive is handed over only while its owner may still download it: a teacher who lost
     * `:downloadcourse` after the build is refused; with the capability the archive is handed over.
     */
    public function test_download_is_refused_to_an_owner_who_lost_access(): void {
        global $DB;

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = (int) $gen->create_user()->id;
        $gen->enrol_user($teacher, $course->id, 'editingteacher');
        $ready = $this->add_task($teacher, zip_tasks::STATUS_READY, (int) $course->id);
        file_put_contents(task_storage::get_zip_path($ready), 'zip');

        [$zippath] = zip_tasks::prepare_download($ready, $teacher);
        $this->assertSame(task_storage::get_zip_path($ready), $zippath);

        assign_capability(
            'block/download_certificates:downloadcourse',
            CAP_PROHIBIT,
            (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']),
            \context_course::instance($course->id)->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertSame('accessdenied', $this->error_of(fn() => zip_tasks::prepare_download($ready, $teacher)));
        $this->assertFileExists($zippath);
    }

    /**
     * A ready row whose archive is gone expires instead of pointing at nothing.
     */
    public function test_missing_archive_expires_the_row(): void {
        global $DB;

        $owner = (int) $this->getDataGenerator()->create_user()->id;
        $ready = $this->add_task($owner, zip_tasks::STATUS_READY);

        $this->assertSame('filenotfound', $this->error_of(fn() => zip_tasks::prepare_download($ready, $owner)));
        $this->assertSame(zip_tasks::STATUS_EXPIRED, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $ready]));
    }

    /**
     * A ready archive is claimed once: the first claim returns the path and expires the row, a
     * second claim is refused; another user's row and a row that is not ready are refused.
     */
    public function test_claim_download_hands_the_archive_over_once(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $owner = (int) $this->getDataGenerator()->create_user()->id;
        $this->getDataGenerator()->enrol_user($owner, $course->id, 'editingteacher');
        $other = (int) $this->getDataGenerator()->create_user()->id;
        $ready = $this->add_task($owner, zip_tasks::STATUS_READY, (int) $course->id);
        file_put_contents(task_storage::get_zip_path($ready), 'zip');
        $pending = $this->add_task($owner, zip_tasks::STATUS_PENDING, (int) $course->id);

        $this->assertSame('tasknotfound', $this->error_of(fn() => zip_tasks::claim_download($ready, $other)));
        $this->assertSame('tasknotready', $this->error_of(fn() => zip_tasks::claim_download($pending, $owner)));
        $this->assertSame(zip_tasks::STATUS_READY, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $ready]));

        [$zippath, $filename] = zip_tasks::claim_download($ready, $owner);
        $this->assertSame(task_storage::get_zip_path($ready), $zippath);
        $this->assertSame('certificates_course_' . $course->id . '.zip', $filename);
        $this->assertSame(zip_tasks::STATUS_EXPIRED, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $ready]));

        $this->assertSame('tasknotready', $this->error_of(fn() => zip_tasks::claim_download($ready, $owner)));
    }

    /**
     * A request that lost the claim to another one between its checks and its write is refused: the
     * row was expired by someone else while this one still held the ready row it read.
     */
    public function test_claim_loses_to_a_concurrent_claim(): void {
        $owner = (int) $this->getDataGenerator()->create_user()->id;
        $ready = $this->add_task($owner, zip_tasks::STATUS_READY);

        $this->assertTrue(zip_tasks::update_if_status($ready, zip_tasks::STATUS_READY, [
            'status' => zip_tasks::STATUS_EXPIRED,
            'timemodified' => time(),
        ]));
        // The conditional write of a second claimant finds the row no longer ready.
        $this->assertFalse(zip_tasks::update_if_status($ready, zip_tasks::STATUS_READY, [
            'status' => zip_tasks::STATUS_EXPIRED,
            'timemodified' => time(),
        ]));
    }

    /**
     * Expiring a row removes its directory and nobody else's.
     */
    public function test_expire_removes_the_rows_directory_only(): void {
        global $DB;

        $owner = (int) $this->getDataGenerator()->create_user()->id;
        $one = $this->add_task($owner, zip_tasks::STATUS_READY);
        $two = $this->add_task($owner, zip_tasks::STATUS_READY);
        file_put_contents(task_storage::get_zip_path($one), 'zip');
        file_put_contents(task_storage::get_zip_path($two), 'zip');

        zip_tasks::expire($one);

        $this->assertSame(zip_tasks::STATUS_EXPIRED, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $one]));
        $this->assertDirectoryDoesNotExist(task_storage::get_directory($one));
        $this->assertFileExists(task_storage::get_directory($two) . '/certificates.zip');
        $this->assertSame(zip_tasks::STATUS_READY, $DB->get_field(zip_tasks::TABLE, 'status', ['id' => $two]));
    }

    /**
     * A failure is stored as a string identifier and read in the reader's language; stored text that
     * is not one of the plugin's identifiers reads as the generic failure.
     */
    public function test_failed_rows_report_a_localised_reason(): void {
        global $DB;

        $owner = (int) $this->getDataGenerator()->create_user()->id;
        $taskid = $this->add_task($owner, zip_tasks::STATUS_PROCESSING);
        task_storage::write_refs($taskid, [['tool_certificate', 1]]);

        zip_tasks::fail($taskid, 'taskerror_noaccess');

        $this->assertDirectoryDoesNotExist(task_storage::get_directory($taskid));
        $status = zip_tasks::get_status($taskid, $owner);
        $this->assertSame(zip_tasks::STATUS_FAILED, $status['status']);
        $this->assertSame(get_string('taskerror_noaccess', 'block_download_certificates'), $status['errormessage']);

        $DB->set_field(zip_tasks::TABLE, 'error', 'SQLSTATE[42P01]: relation "x" does not exist', ['id' => $taskid]);
        $this->assertSame(
            get_string('taskerror_unexpected', 'block_download_certificates'),
            zip_tasks::get_status($taskid, $owner)['errormessage']
        );
    }

    /**
     * Only the plugin's own error codes are stored; anything else is the generic failure.
     */
    public function test_error_code(): void {
        $this->assertSame(
            'nocertificates',
            zip_tasks::error_code(new \moodle_exception('nocertificates', 'block_download_certificates'))
        );
        $this->assertSame('taskerror_unexpected', zip_tasks::error_code(new \dml_read_exception('boom')));
        $this->assertSame('taskerror_unexpected', zip_tasks::error_code(new \moodle_exception('nopermissions', 'error')));
        $witha = new \moodle_exception('toomanyforsync', 'block_download_certificates', '', (object) ['count' => 1]);
        $this->assertSame('taskerror_unexpected', zip_tasks::error_code($witha));
    }

    /**
     * The start-up list holds the owner's unfinished and ready rows only.
     */
    public function test_get_pending(): void {
        $owner = (int) $this->getDataGenerator()->create_user()->id;
        $other = (int) $this->getDataGenerator()->create_user()->id;
        $pending = $this->add_task($owner, zip_tasks::STATUS_PENDING);
        $ready = $this->add_task($owner, zip_tasks::STATUS_READY);
        $this->add_task($owner, zip_tasks::STATUS_FAILED);
        $this->add_task($other, zip_tasks::STATUS_PENDING);

        $this->assertEqualsCanonicalizing([$pending, $ready], array_column(zip_tasks::get_pending($owner), 'taskid'));
    }
}
