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
 * Tests for the certificate repository.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

use block_download_certificates\local\source\customcert;
use block_download_certificates\local\source\issue_factory;
use block_download_certificates\local\source\tool_certificate;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/download_certificates/tests/fixtures/issue_factory.php');

/**
 * Search, counts, ZIP lists and menus over the union of the sources, scoped and filtered.
 *
 * The data set lives in tool_certificate; the test class is skipped, not green, where that plugin
 * is not installed. The union of both sources is checked when mod_customcert is installed too.
 *
 * Metadata stays in docblocks because the plugin still supports Moodle 4.5, whose moodle-cs cannot
 * read attributes and warns per method.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\local\repository
 * @covers     \block_download_certificates\local\source\tool_certificate
 * @covers     \block_download_certificates\local\source\customcert
 */
final class repository_test extends \advanced_testcase {
    /** @var \stdClass Ids of the data set built by {@see self::build_data_set()}. */
    private \stdClass $ids;

    /**
     * Skip without tool_certificate; reset the database after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!(new tool_certificate())->is_available()) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
        $this->resetAfterTest();
    }

    /**
     * Two courses, three owners, a cohort, a group and five issues.
     *
     * Issues: alice in A at 1000 (Alpha, AAA111); bob in A at 2000 (Alpha, BBB222); carol in B at
     * 3000 (Beta, CCC333); alice with no course at 3000 (Beta, DDD444); bob in B at 4000 (Beta, EEE555).
     */
    private function build_data_set(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $gen = $this->getDataGenerator();
        $ids = new \stdClass();

        $ids->coursea = (int) $gen->create_course(['fullname' => 'Algebra'])->id;
        $ids->courseb = (int) $gen->create_course(['fullname' => 'Biology'])->id;
        $ids->alice = (int) $gen->create_user([
            'firstname' => 'Alice', 'lastname' => 'Zephyr', 'email' => 'alice@example.com',
        ])->id;
        $ids->bob = (int) $gen->create_user(['firstname' => 'Bob', 'lastname' => 'Young', 'email' => 'bob@example.com'])->id;
        $ids->carol = (int) $gen->create_user([
            'firstname' => 'Carol', 'lastname' => 'Xavier', 'email' => 'carol@example.com',
        ])->id;

        $gen->enrol_user($ids->alice, $ids->coursea, 'student');
        $gen->enrol_user($ids->bob, $ids->coursea, 'student');
        $ids->group = (int) $gen->create_group(['courseid' => $ids->coursea])->id;
        $gen->create_group_member(['groupid' => $ids->group, 'userid' => $ids->alice]);
        $ids->cohort = (int) $gen->create_cohort()->id;
        cohort_add_member($ids->cohort, $ids->carol);

        $alpha = issue_factory::tool_certificate_template('Alpha template')->get_id();
        $beta = issue_factory::tool_certificate_template('Beta template')->get_id();
        $ids->issues = [
            issue_factory::tool_certificate_issue($alpha, $ids->alice, $ids->coursea, 1000, 'AAA111'),
            issue_factory::tool_certificate_issue($alpha, $ids->bob, $ids->coursea, 2000, 'BBB222'),
            issue_factory::tool_certificate_issue($beta, $ids->carol, $ids->courseb, 3000, 'CCC333'),
            issue_factory::tool_certificate_issue($beta, $ids->alice, 0, 3000, 'DDD444'),
            issue_factory::tool_certificate_issue($beta, $ids->bob, $ids->courseb, 4000, 'EEE555'),
        ];
        $this->ids = $ids;
    }

    /**
     * A manager's scope.
     *
     * @return scope
     */
    private function manager_scope(): scope {
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id);
        return scope::for_user((int) $manager->id);
    }

    /**
     * Search, count and the ZIP list agree for every filter, and each filter keeps what it says.
     */
    public function test_search_count_and_refs_agree_for_every_filter(): void {
        $this->build_data_set();
        $ids = $this->ids;
        $repository = new repository(['tool_certificate' => new tool_certificate()]);
        $scope = $this->manager_scope();

        $cases = [
            'none' => [[], 5],
            'course A' => [['courseid' => $ids->coursea], 2],
            'course B' => [['courseid' => $ids->courseb], 2],
            'user' => [['userid' => $ids->alice], 2],
            'cohort' => [['cohortid' => $ids->cohort], 1],
            'group' => [['groupid' => $ids->group], 1],
            'from' => [['datefrom' => 2000], 4],
            'to' => [['dateto' => 3000], 2],
            'from and to' => [['datefrom' => 2000, 'dateto' => 4000], 3],
            'full name' => [['search' => 'ICE ZEP'], 2],
            'e-mail' => [['search' => 'bob@example'], 2],
            'course name' => [['search' => 'biolo'], 2],
            'template' => [['search' => 'beta'], 3],
            'code' => [['search' => 'ccc3'], 1],
            'no match' => [['search' => 'nothing like this'], 0],
            'like wildcard' => [['search' => '%'], 0],
            'combined' => [['courseid' => $ids->coursea, 'search' => 'alice'], 1],
        ];
        foreach ($cases as $name => [$values, $expected]) {
            $filter = filter::from_array($values);
            $this->assertSame($expected, $repository->count($scope, $filter), $name);
            $this->assertCount($expected, $repository->search($scope, $filter, 'timecreated', 'DESC', 0, 0), $name);
            $this->assertCount($expected, $repository->list_issue_refs($scope, $filter), $name);
        }
    }

    /**
     * Rows come back in the shared shape, with the owner's name and e-mail.
     */
    public function test_search_row_shape(): void {
        $this->build_data_set();
        $repository = new repository(['tool_certificate' => new tool_certificate()]);

        $rows = $repository->search($this->manager_scope(), new filter(0, 0, 0, 0, 0, 0, 'AAA111'), 'code', 'ASC', 0, 10);

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame('tc_' . $this->ids->issues[0], $row->uniqueid);
        $this->assertSame($this->ids->issues[0], $row->issueid);
        $this->assertSame('tool_certificate', $row->type);
        $this->assertSame($this->ids->alice, $row->userid);
        $this->assertSame($this->ids->coursea, $row->courseid);
        $this->assertSame('Algebra', $row->coursename);
        $this->assertSame('Alpha template', $row->templatename);
        $this->assertSame(1000, $row->timecreated);
        $this->assertSame('Alice', $row->firstname);
        $this->assertSame('alice@example.com', $row->email);
    }

    /**
     * Equal sort values are ordered by the unique id, in both directions, so pages never overlap.
     */
    public function test_sort_tie_breaker(): void {
        $this->build_data_set();
        $repository = new repository(['tool_certificate' => new tool_certificate()]);
        $scope = $this->manager_scope();
        $template = issue_factory::tool_certificate_template('Tie')->get_id();
        $tied = [];
        foreach (['T1', 'T2', 'T3'] as $code) {
            $tied[] = 'tc_' . issue_factory::tool_certificate_issue($template, $this->ids->carol, 0, 3000, $code);
        }
        $tied[] = 'tc_' . $this->ids->issues[2];
        $tied[] = 'tc_' . $this->ids->issues[3];
        sort($tied, SORT_STRING);

        foreach (['ASC', 'DESC'] as $order) {
            $all = array_column($repository->search($scope, new filter(), 'timecreated', $order, 0, 0), 'uniqueid');
            $this->assertSame($tied, array_values(array_intersect($all, $tied)), $order);

            $paged = [];
            for ($offset = 0; $offset < count($all); $offset++) {
                $page = $repository->search($scope, new filter(), 'timecreated', $order, $offset, 1);
                $paged[] = $page[0]->uniqueid;
            }
            $this->assertSame($all, $paged, $order);
        }

        // An unknown sort key falls back to the date.
        $fallback = $repository->search($scope, new filter(), 'u.password', 'ASC', 0, 0);
        $this->assertSame(1000, $fallback[0]->timecreated);
    }

    /**
     * The date range includes its start and excludes its end.
     */
    public function test_date_range_is_half_open(): void {
        $this->build_data_set();
        $repository = new repository(['tool_certificate' => new tool_certificate()]);
        $scope = $this->manager_scope();

        $refs = $repository->list_issue_refs($scope, filter::from_array(['datefrom' => 2000, 'dateto' => 3000]));
        $this->assertSame([['tool_certificate', $this->ids->issues[1]]], $refs);

        $refs = $repository->list_issue_refs($scope, filter::from_array(['datefrom' => 1000, 'dateto' => 2000]));
        $this->assertSame([['tool_certificate', $this->ids->issues[0]]], $refs);
    }

    /**
     * The ZIP list is ordered by issue time, then unique id.
     */
    public function test_list_issue_refs_order(): void {
        $this->build_data_set();
        $repository = new repository(['tool_certificate' => new tool_certificate()]);

        $refs = $repository->list_issue_refs($this->manager_scope(), new filter());

        $tied = [$this->ids->issues[2], $this->ids->issues[3]];
        usort($tied, fn(int $a, int $b): int => strcmp('tc_' . $a, 'tc_' . $b));
        $expected = [$this->ids->issues[0], $this->ids->issues[1], $tied[0], $tied[1], $this->ids->issues[4]];
        $this->assertSame($expected, array_column($refs, 1));
    }

    /**
     * A deleted user's certificates disappear from every read.
     */
    public function test_deleted_users_excluded(): void {
        $this->build_data_set();
        $repository = new repository(['tool_certificate' => new tool_certificate()]);
        $scope = $this->manager_scope();
        $this->assertSame(5, $repository->count($scope, new filter()));

        delete_user(\core_user::get_user($this->ids->bob));

        $this->assertSame(3, $repository->count($scope, new filter()));
        $this->assertCount(3, $repository->search($scope, new filter(), 'timecreated', 'ASC', 0, 0));
        $this->assertArrayNotHasKey($this->ids->bob, $repository->get_users($scope));
        $this->assertNull((new tool_certificate())->get_issue($this->ids->issues[1]));
    }

    /**
     * Menus count per course, owner and cohort; the site course stays out of the course menu, and a
     * hidden cohort is listed to a manager but not to a teacher without moodle/cohort:view.
     */
    public function test_menus(): void {
        $this->build_data_set();
        $ids = $this->ids;
        $repository = new repository(['tool_certificate' => new tool_certificate()]);
        $template = issue_factory::tool_certificate_template('Site')->get_id();
        issue_factory::tool_certificate_issue($template, $ids->carol, (int) SITEID, 5000, 'SITE1');
        $hidden = (int) $this->getDataGenerator()->create_cohort(['visible' => 0])->id;
        cohort_add_member($hidden, $ids->bob);
        $manager = $this->manager_scope();

        $courses = $repository->get_courses($manager);
        $this->assertSame([$ids->coursea, $ids->courseb], array_keys($courses));
        $this->assertSame(2, $courses[$ids->coursea]->total);
        $this->assertSame('Algebra', $courses[$ids->coursea]->name);

        $users = $repository->get_users($manager);
        $this->assertSame(2, $users[$ids->alice]->total);
        $this->assertSame(2, $users[$ids->bob]->total);
        $this->assertSame(2, $users[$ids->carol]->total);
        $this->assertSame('Alice Zephyr', $users[$ids->alice]->fullname);

        $cohorts = $repository->get_cohorts($manager);
        $this->assertSame(2, $cohorts[$ids->cohort]->total);
        $this->assertSame(2, $cohorts[$hidden]->total);

        // A teacher of course A without moodle/cohort:view sees the visible cohort of a participant
        // only; carol is not in course A, so her cohort holds nothing in scope.
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $ids->coursea, 'teacher');
        $visible = (int) $this->getDataGenerator()->create_cohort()->id;
        cohort_add_member($visible, $ids->bob);
        $teacherscope = scope::for_user((int) $teacher->id);

        $this->assertSame([$ids->coursea], array_keys($repository->get_courses($teacherscope)));
        $this->assertSame([$visible], array_keys($repository->get_cohorts($teacherscope)));
    }

    /**
     * A teacher under separate groups lists, counts and zips their group's certificates only.
     */
    public function test_teacher_scope_applies_to_every_read(): void {
        global $DB;

        $this->build_data_set();
        $ids = $this->ids;
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $ids->coursea]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $ids->coursea, 'teacher');
        $this->getDataGenerator()->create_group_member(['groupid' => $ids->group, 'userid' => $teacher->id]);
        $repository = new repository(['tool_certificate' => new tool_certificate()]);
        $scope = scope::for_user((int) $teacher->id);

        $this->assertSame(1, $repository->count($scope, new filter()));
        $this->assertSame([['tool_certificate', $ids->issues[0]]], $repository->list_issue_refs($scope, new filter()));
        $this->assertSame([$ids->alice], array_keys($repository->get_users($scope)));

        // The manager sees bob's course A certificate the teacher does not.
        $this->assertSame(2, $repository->count($this->manager_scope(), filter::from_array(['courseid' => $ids->coursea])));
    }

    /**
     * A frozen list keeps its references when a certificate is issued afterwards.
     */
    public function test_frozen_refs_ignore_later_issues(): void {
        $this->build_data_set();
        $repository = new repository(['tool_certificate' => new tool_certificate()]);
        $scope = $this->manager_scope();

        $frozen = $repository->list_issue_refs($scope, new filter());
        task_storage::write_refs(77, $frozen);
        $template = issue_factory::tool_certificate_template('Later')->get_id();
        issue_factory::tool_certificate_issue($template, $this->ids->alice, $this->ids->coursea, 1500, 'LATER1');

        $this->assertCount(6, $repository->list_issue_refs($scope, new filter()));
        $this->assertSame($frozen, task_storage::read_refs(77));
    }

    /**
     * The union of both sources lists both types with their prefixes.
     */
    public function test_union_of_both_sources(): void {
        if (!(new customcert())->is_available()) {
            $this->markTestSkipped('mod_customcert is not installed.');
        }
        $this->build_data_set();
        $instance = issue_factory::customcert_instance($this->ids->courseb, 'Gamma certificate');
        $issueid = issue_factory::customcert_issue((int) $instance->id, $this->ids->carol, 3500, 'CUSTOM1');
        $repository = new repository();
        $scope = $this->manager_scope();

        $this->assertSame(6, $repository->count($scope, new filter()));
        $rows = $repository->search($scope, filter::from_array(['search' => 'gamma']), 'type', 'ASC', 0, 0);
        $this->assertCount(1, $rows);
        $this->assertSame('cc_' . $issueid, $rows[0]->uniqueid);
        $this->assertSame('customcert', $rows[0]->type);
        $this->assertSame($this->ids->courseb, $rows[0]->courseid);
        $this->assertSame('Biology', $rows[0]->coursename);
        $this->assertSame(3, $repository->count($scope, filter::from_array(['courseid' => $this->ids->courseb])));
    }

    /**
     * Without any available source every read is empty.
     */
    public function test_no_source(): void {
        $this->build_data_set();
        $repository = new repository([]);
        $scope = $this->manager_scope();

        $this->assertSame(0, $repository->count($scope, new filter()));
        $this->assertSame([], $repository->search($scope, new filter(), 'timecreated', 'DESC', 0, 10));
        $this->assertSame([], $repository->list_issue_refs($scope, new filter()));
        $this->assertSame([], $repository->get_courses($scope));
    }
}
