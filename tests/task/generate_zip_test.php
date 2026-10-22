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
 * Tests for the background ZIP task.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\task;

use block_download_certificates\local\filter;
use block_download_certificates\local\scope;
use block_download_certificates\local\source\issue_factory;
use block_download_certificates\local\source\tool_certificate;
use block_download_certificates\local\task_storage;
use block_download_certificates\local\zip_builder;
use block_download_certificates\local\zip_tasks;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/download_certificates/tests/fixtures/issue_factory.php');

/**
 * The task decides on the requester's scope as it is when it runs, builds from the list it froze on
 * its first run, and writes into its own directory.
 *
 * Skipped, not green, where tool_certificate is not installed.
 *
 * Metadata stays in docblock form so the test files stay identical across the plugin's branches.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\task\generate_zip
 */
final class generate_zip_test extends \advanced_testcase {
    /** @var int A tool_certificate template without pages: its issues become errors.txt lines. */
    private int $blanktemplate;

    /**
     * Skip without tool_certificate; reset the database after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!(new tool_certificate())->is_available()) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
        $this->resetAfterTest();
        $this->blanktemplate = issue_factory::tool_certificate_template('Blank')->get_id();
    }

    /**
     * Make every row lock available again.
     */
    protected function tearDown(): void {
        zip_tasks::set_unavailable_locks([]);
        parent::tearDown();
    }

    /**
     * Run one batch of a row, with a batch size of two, without its trace output.
     *
     * @param int $taskid The row id.
     * @param callable|null $midrun Called once the run has read its batch size, that is after the
     *        row went to processing and before the batch is built.
     * @return \stdClass The row afterwards.
     */
    private function run_batch(int $taskid, ?callable $midrun = null): \stdClass {
        global $DB;

        $task = new class extends generate_zip {
            /** @var callable|null Called when the batch size is read. */
            public $midrun = null;

            /**
             * Two certificates per run, so that three need two runs.
             *
             * @return int
             */
            protected function get_batch_size(): int {
                if ($this->midrun) {
                    ($this->midrun)();
                }
                return 2;
            }
        };
        $task->midrun = $midrun;
        $task->set_custom_data((object) ['taskid' => $taskid]);
        ob_start();
        try {
            $task->execute();
        } finally {
            ob_end_clean();
        }
        return $DB->get_record(zip_tasks::TABLE, ['id' => $taskid]);
    }

    /**
     * A user holding the manager role in the system context.
     *
     * @return \stdClass
     */
    private function manager(): \stdClass {
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id);
        return $manager;
    }

    /**
     * The entry names of an archive.
     *
     * @param string $zippath The archive.
     * @return string[]
     */
    private function entries(string $zippath): array {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zippath));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        return $names;
    }

    /**
     * The first run freezes the list; a certificate issued before the second run is not in the
     * archive, and nothing is skipped or repeated.
     */
    public function test_frozen_list_survives_a_certificate_issued_between_batches(): void {
        $manager = $this->manager();
        $owner = $this->getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace']);
        $template = issue_factory::tool_certificate_template('Gold', true)->get_id();
        for ($i = 1; $i <= 3; $i++) {
            issue_factory::tool_certificate_issue($template, (int) $owner->id, 0, 1000 + $i, 'CODE' . $i);
        }
        $taskid = (int) zip_tasks::create(scope::for_user((int) $manager->id), 'all', new filter())->id;

        $row = $this->run_batch($taskid);
        $this->assertSame(zip_tasks::STATUS_PROCESSING, $row->status);
        $this->assertSame(3, (int) $row->total);
        $this->assertSame(2, (int) $row->batchoffset);
        $this->assertFileExists(task_storage::get_directory($taskid) . '/refs.json');
        $this->assertCount(2, \core\task\manager::get_adhoc_tasks(generate_zip::class));

        // Issued between the runs, and older than every frozen one: a list read again by offset
        // would shift it into the second batch.
        issue_factory::tool_certificate_issue($template, (int) $owner->id, 0, 500, 'LATE');

        $row = $this->run_batch($taskid);
        $this->assertSame(zip_tasks::STATUS_READY, $row->status);
        $this->assertSame(3, (int) $row->progress);
        $this->assertNull($row->filepath);
        $expected = [];
        for ($i = 1; $i <= 3; $i++) {
            $expected[] = 'tool_certificate/' . clean_filename('Ada Lovelace_Gold_CODE' . $i . '.pdf');
        }
        $this->assertSame($expected, $this->entries(task_storage::get_zip_path($taskid)));
    }

    /**
     * Two requests for the same selection build two archives, each in its own task directory.
     */
    public function test_each_task_builds_in_its_own_directory(): void {
        $owner = $this->getDataGenerator()->create_user();
        issue_factory::tool_certificate_issue($this->blanktemplate, (int) $owner->id, 0, 1000, 'CODE1');
        $one = (int) zip_tasks::create(scope::for_user((int) $this->manager()->id), 'all', new filter())->id;
        $two = (int) zip_tasks::create(scope::for_user((int) $this->manager()->id), 'all', new filter())->id;

        $this->assertSame(zip_tasks::STATUS_READY, $this->run_batch($one)->status);
        $this->assertSame(zip_tasks::STATUS_READY, $this->run_batch($two)->status);
        $this->assertNotSame(task_storage::get_zip_path($one), task_storage::get_zip_path($two));

        zip_tasks::expire($one);

        $this->assertFileDoesNotExist(task_storage::get_zip_path($one));
        $this->assertContains(zip_builder::ERRORS_ENTRY, $this->entries(task_storage::get_zip_path($two)));
    }

    /**
     * A teacher who loses `:downloadcourse` between the request and the run gets a failed row with
     * a reason, and no archive; the same request run before the change completes.
     */
    public function test_teacher_who_lost_the_capability_gets_a_failed_task(): void {
        global $DB;

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');
        issue_factory::tool_certificate_issue($this->blanktemplate, (int) $student->id, (int) $course->id, 1000, 'CODE1');
        $filter = new filter(courseid: (int) $course->id);
        $control = (int) zip_tasks::create(scope::for_user((int) $teacher->id), 'course', $filter)->id;
        $taskid = (int) zip_tasks::create(scope::for_user((int) $teacher->id), 'course', $filter)->id;

        $this->assertSame(zip_tasks::STATUS_READY, $this->run_batch($control)->status);

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability(
            'block/download_certificates:downloadcourse',
            CAP_PROHIBIT,
            $roleid,
            \context_course::instance($course->id)->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();

        $row = $this->run_batch($taskid);
        $this->assertSame(zip_tasks::STATUS_FAILED, $row->status);
        $this->assertSame('taskerror_noaccess', $row->error);
        $this->assertDirectoryDoesNotExist(task_storage::get_directory($taskid));
    }

    /**
     * A row whose requester was suspended or deleted after the request fails with a reason.
     */
    public function test_inactive_requester_fails(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        issue_factory::tool_certificate_issue($this->blanktemplate, (int) $owner->id, 0, 1000, 'CODE1');
        $active = $this->manager();
        $suspended = $this->manager();
        $deleted = $this->manager();
        $tasks = [];
        foreach (['active' => $active, 'suspended' => $suspended, 'deleted' => $deleted] as $key => $user) {
            $tasks[$key] = (int) zip_tasks::create(scope::for_user((int) $user->id), 'all', new filter())->id;
        }
        $DB->set_field('user', 'suspended', 1, ['id' => $suspended->id]);
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);

        $this->assertSame(zip_tasks::STATUS_READY, $this->run_batch($tasks['active'])->status);
        foreach (['suspended', 'deleted'] as $key) {
            $row = $this->run_batch($tasks[$key]);
            $this->assertSame(zip_tasks::STATUS_FAILED, $row->status, $key);
            $this->assertSame('taskerror_inactiveuser', $row->error, $key);
        }
    }

    /**
     * A row that is no longer pending or processing is left alone.
     */
    public function test_finished_rows_are_skipped(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        issue_factory::tool_certificate_issue($this->blanktemplate, (int) $owner->id, 0, 1000, 'CODE1');
        $taskid = (int) zip_tasks::create(scope::for_user((int) $this->manager()->id), 'all', new filter())->id;
        $DB->set_field(zip_tasks::TABLE, 'status', zip_tasks::STATUS_EXPIRED, ['id' => $taskid]);

        $row = $this->run_batch($taskid);

        $this->assertSame(zip_tasks::STATUS_EXPIRED, $row->status);
        $this->assertDirectoryDoesNotExist(task_storage::get_directory($taskid));
    }

    /**
     * A group membership removed between two batches takes that user's certificates out of the
     * archive, with a line in errors.txt; the same request without the removal has all of them.
     */
    public function test_later_batches_are_checked_against_the_current_scope(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['groupmode' => SEPARATEGROUPS]);
        $teacher = $gen->create_user();
        $ada = $gen->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace']);
        $grace = $gen->create_user(['firstname' => 'Grace', 'lastname' => 'Hopper']);
        $gen->enrol_user($teacher->id, $course->id, 'teacher');
        $gen->enrol_user($ada->id, $course->id, 'student');
        $gen->enrol_user($grace->id, $course->id, 'student');
        $group = $gen->create_group(['courseid' => $course->id]);
        foreach ([$teacher, $ada, $grace] as $member) {
            $gen->create_group_member(['groupid' => $group->id, 'userid' => $member->id]);
        }
        $template = issue_factory::tool_certificate_template('Gold', true)->get_id();
        issue_factory::tool_certificate_issue($template, (int) $ada->id, (int) $course->id, 1001, 'ADA1');
        issue_factory::tool_certificate_issue($template, (int) $ada->id, (int) $course->id, 1002, 'ADA2');
        issue_factory::tool_certificate_issue($template, (int) $grace->id, (int) $course->id, 1003, 'GRACE1');
        $filter = new filter(courseid: (int) $course->id);
        $control = (int) zip_tasks::create(scope::for_user((int) $teacher->id), 'course', $filter)->id;
        $taskid = (int) zip_tasks::create(scope::for_user((int) $teacher->id), 'course', $filter)->id;
        $gracefile = 'tool_certificate/' . clean_filename('Grace Hopper_Gold_GRACE1.pdf');

        $this->assertSame(zip_tasks::STATUS_PROCESSING, $this->run_batch($control)->status);
        $this->assertSame(zip_tasks::STATUS_READY, $this->run_batch($control)->status);
        $entries = $this->entries(task_storage::get_zip_path($control));
        $this->assertCount(3, $entries);
        $this->assertContains($gracefile, $entries);
        $this->assertNotContains(zip_builder::ERRORS_ENTRY, $entries);

        $this->assertSame(zip_tasks::STATUS_PROCESSING, $this->run_batch($taskid)->status);
        groups_remove_member($group->id, $grace->id);
        $row = $this->run_batch($taskid);

        $this->assertSame(zip_tasks::STATUS_READY, $row->status);
        $zippath = task_storage::get_zip_path($taskid);
        $entries = $this->entries($zippath);
        $this->assertNotContains($gracefile, $entries);
        $this->assertContains(zip_builder::ERRORS_ENTRY, $entries);
        $this->assertCount(3, $entries);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zippath));
        $errors = $zip->getFromName(zip_builder::ERRORS_ENTRY);
        $zip->close();
        $this->assertStringContainsString(get_string('zip_error_outofscope', 'block_download_certificates'), $errors);
        $this->assertStringNotContainsString('Hopper', $errors);
    }

    /**
     * A row that something else failed while the task was running stays failed, with its reason: no
     * next batch is queued, no archive is left; an untouched row reaches ready.
     */
    public function test_status_writes_do_not_overwrite_a_row_failed_meanwhile(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $template = issue_factory::tool_certificate_template('Gold', true)->get_id();
        for ($i = 1; $i <= 3; $i++) {
            issue_factory::tool_certificate_issue($template, (int) $owner->id, 0, 1000 + $i, 'CODE' . $i);
        }
        $manager = $this->manager();
        $failrow = function (int $taskid) use ($DB): void {
            $DB->set_field(zip_tasks::TABLE, 'status', zip_tasks::STATUS_FAILED, ['id' => $taskid]);
            $DB->set_field(zip_tasks::TABLE, 'error', 'taskerror_timeout', ['id' => $taskid]);
        };

        // Three certificates, two batches: the run would queue the next batch.
        $multi = (int) zip_tasks::create(scope::for_user((int) $manager->id), 'all', new filter())->id;
        $queued = count(\core\task\manager::get_adhoc_tasks(generate_zip::class));
        $row = $this->run_batch($multi, fn() => $failrow($multi));
        $this->assertSame(zip_tasks::STATUS_FAILED, $row->status);
        $this->assertSame('taskerror_timeout', $row->error);
        $this->assertCount($queued, \core\task\manager::get_adhoc_tasks(generate_zip::class));
        $this->assertDirectoryDoesNotExist(task_storage::get_directory($multi));

        // One batch: the run would mark the row ready.
        $solo = $this->getDataGenerator()->create_user();
        issue_factory::tool_certificate_issue($template, (int) $solo->id, 0, 2000, 'SOLO');
        $single = (int) zip_tasks::create(
            scope::for_user((int) $manager->id),
            'user',
            new filter(userid: (int) $solo->id)
        )->id;
        $row = $this->run_batch($single, fn() => $failrow($single));
        $this->assertSame(zip_tasks::STATUS_FAILED, $row->status);
        $this->assertSame('taskerror_timeout', $row->error);
        $this->assertDirectoryDoesNotExist(task_storage::get_directory($single));

        // Control: the same request, untouched, finishes.
        $control = (int) zip_tasks::create(scope::for_user((int) $manager->id), 'all', new filter())->id;
        $this->assertSame(zip_tasks::STATUS_PROCESSING, $this->run_batch($control)->status);
        $this->assertSame(zip_tasks::STATUS_READY, $this->run_batch($control)->status);
        $this->assertFileExists(task_storage::get_zip_path($control));
    }

    /**
     * A run that cannot lock its row to move it on leaves the row and its files alone and queues the
     * same row again; the same run with the lock free moves the row on.
     */
    public function test_a_lock_not_had_queues_the_row_again(): void {
        $owner = $this->getDataGenerator()->create_user();
        $template = issue_factory::tool_certificate_template('Gold', true)->get_id();
        for ($i = 1; $i <= 3; $i++) {
            issue_factory::tool_certificate_issue($template, (int) $owner->id, 0, 1000 + $i, 'CODE' . $i);
        }
        $manager = $this->manager();
        $taskid = (int) zip_tasks::create(scope::for_user((int) $manager->id), 'all', new filter())->id;
        $queued = count(\core\task\manager::get_adhoc_tasks(generate_zip::class));

        zip_tasks::set_unavailable_locks([$taskid]);
        $row = $this->run_batch($taskid);
        zip_tasks::set_unavailable_locks([]);

        $this->assertSame(zip_tasks::STATUS_PENDING, $row->status);
        $this->assertFileExists(task_storage::get_directory($taskid) . '/refs.json');
        $this->assertCount($queued + 1, \core\task\manager::get_adhoc_tasks(generate_zip::class));
        $this->assertSame(
            [$taskid],
            array_values(array_unique(array_map(
                fn($task) => (int) $task->get_custom_data()->taskid,
                \core\task\manager::get_adhoc_tasks(generate_zip::class)
            )))
        );

        // Control: with the lock free the run moves the row on and queues its next batch.
        $row = $this->run_batch($taskid);
        $this->assertSame(zip_tasks::STATUS_PROCESSING, $row->status);
        $this->assertSame(2, (int) $row->batchoffset);
        $this->assertCount($queued + 2, \core\task\manager::get_adhoc_tasks(generate_zip::class));
    }

    /**
     * An error after the cleanup already failed the row keeps the cleanup's reason; the same error on
     * a row that is still running takes its own reason.
     */
    public function test_an_error_does_not_overwrite_a_reason_the_cleanup_wrote(): void {
        global $DB;

        $manager = $this->manager();
        $owner = $this->getDataGenerator()->create_user();
        issue_factory::tool_certificate_issue($this->blanktemplate, (int) $owner->id, 0, 1000, 'CODE1');
        $cleaned = (int) zip_tasks::create(scope::for_user((int) $manager->id), 'all', new filter())->id;
        $running = (int) zip_tasks::create(scope::for_user((int) $manager->id), 'all', new filter())->id;

        $row = $this->run_batch($cleaned, function () use ($DB, $cleaned): void {
            $DB->set_field(zip_tasks::TABLE, 'status', zip_tasks::STATUS_FAILED, ['id' => $cleaned]);
            $DB->set_field(zip_tasks::TABLE, 'error', 'taskerror_timeout', ['id' => $cleaned]);
            throw new \RuntimeException('boom');
        });
        $this->assertSame(zip_tasks::STATUS_FAILED, $row->status);
        $this->assertSame('taskerror_timeout', $row->error);

        $row = $this->run_batch($running, function (): void {
            throw new \RuntimeException('boom');
        });
        $this->assertSame(zip_tasks::STATUS_FAILED, $row->status);
        $this->assertSame('taskerror_unexpected', $row->error);
        $this->assertDirectoryDoesNotExist(task_storage::get_directory($running));
    }
}
