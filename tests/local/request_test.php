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
 * Tests for the entry points' request helpers.
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
 * The guards every page and script applies before the library runs: which ZIP a scope may ask for,
 * how request values become a filter, and what a client is told on failure.
 *
 * Only the search page test needs a certificate plugin; it skips, not passes, without
 * tool_certificate.
 *
 * Metadata stays in docblocks because the plugin still supports Moodle 4.5, whose moodle-cs cannot
 * read attributes and warns per method.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\local\request
 */
final class request_test extends \advanced_testcase {
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
     * A user enrolled in a course with a role.
     *
     * @param \stdClass $course The course.
     * @param string $role The role archetype's default shortname.
     * @return \stdClass
     */
    private function enrolled(\stdClass $course, string $role): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role);
        return $user;
    }

    /**
     * A user holding the given system capabilities through a role of their own.
     *
     * @param string[] $capabilities The capabilities.
     * @return \stdClass
     */
    private function user_with(array $capabilities): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = (int) $this->getDataGenerator()->create_role();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        }
        $this->getDataGenerator()->role_assign($roleid, $user->id);
        return $user;
    }

    /**
     * A range covers the user's calendar days: from the first midnight in their zone up to, not
     * including, the midnight after the last day.
     */
    public function test_date_range_is_half_open_in_the_users_zone(): void {
        $saopaulo = new \DateTimeZone('America/Sao_Paulo');
        $this->assertSame(
            [gmmktime(3, 0, 0, 3, 10, 2026), gmmktime(3, 0, 0, 3, 12, 2026)],
            request::date_range('2026-03-10', '2026-03-11', $saopaulo)
        );

        // Without a zone, the current user's applies.
        $this->setUser($this->getDataGenerator()->create_user(['timezone' => 'Asia/Tokyo']));
        $this->assertSame(
            [gmmktime(15, 0, 0, 3, 9, 2026), gmmktime(15, 0, 0, 3, 10, 2026)],
            request::date_range('2026-03-10', '2026-03-10')
        );
    }

    /**
     * Days that do not exist, malformed text and a last day before the first are refused.
     */
    public function test_date_range_refuses_bad_days(): void {
        $utc = new \DateTimeZone('UTC');
        foreach ([['2026-02-31', '2026-03-01'], ['tomorrow', '2026-03-01'], ['', ''], ['2026-03-02', '2026-03-01']] as $days) {
            $this->assertSame(
                'invalidaterange',
                $this->error_of(fn() => request::date_range($days[0], $days[1], $utc)),
                implode(' / ', $days)
            );
        }
        // The control: a one-day range is accepted.
        $this->assertNull($this->error_of(fn() => request::date_range('2026-03-01', '2026-03-01', $utc)));
    }

    /**
     * A request keeps only the keys its type means; a range's `Y-m-d` days become the user's
     * calendar days.
     */
    public function test_filter_for_type_keeps_the_types_keys(): void {
        $posted = ['courseid' => '7', 'groupid' => 3, 'userid' => 9, 'cohortid' => 4, 'scope' => 'everything'];

        $this->assertSame(
            ['courseid' => 7, 'userid' => 0, 'cohortid' => 0, 'groupid' => 3, 'datefrom' => 0, 'dateto' => 0, 'search' => ''],
            request::filter_for_type('course', $posted)->to_array()
        );
        $this->assertSame(9, request::filter_for_type('user', $posted)->userid);
        $this->assertSame(0, request::filter_for_type('user', $posted)->courseid);
        $this->assertSame(4, request::filter_for_type('cohort', $posted)->cohortid);
        $this->assertSame((new filter())->to_array(), request::filter_for_type('all', $posted)->to_array());

        $range = request::filter_for_type('range', [
            'startdate' => '2026-03-10',
            'enddate' => '2026-03-11',
            'datefrom' => 1,
        ], new \DateTimeZone('America/Sao_Paulo'));
        $this->assertSame(gmmktime(3, 0, 0, 3, 10, 2026), $range->datefrom);
        $this->assertSame(gmmktime(3, 0, 0, 3, 12, 2026), $range->dateto);

        $this->assertSame('invalidtype', $this->error_of(fn() => request::filter_for_type('everything', $posted)));
        $this->assertSame('invalidaterange', $this->error_of(fn() => request::filter_for_type('range', [])));
        $this->assertSame(
            'invalidaterange',
            $this->error_of(fn() => request::filter_for_type('range', ['startdate' => '2026-03-10', 'enddate' => ['x']]))
        );
    }

    /**
     * A teacher may ask for their own courses and the users they reach, never for every
     * certificate, a cohort or a date range; a manager may.
     */
    public function test_check_zip_keeps_a_teacher_to_their_courses(): void {
        $gen = $this->getDataGenerator();
        $coursea = $gen->create_course();
        $courseb = $gen->create_course();
        $teacher = $this->enrolled($coursea, 'editingteacher');
        $student = $this->enrolled($coursea, 'student');
        $cohort = (int) $gen->create_cohort()->id;
        $teacherscope = scope::for_user((int) $teacher->id);
        $manager = $gen->create_user();
        $gen->role_assign('manager', $manager->id);
        $managerscope = scope::for_user((int) $manager->id);
        $range = new filter(datefrom: 1000, dateto: 2000);

        // The teacher page opens for a course of the teacher's own, without a role name.
        $this->assertTrue(request::can_open_panel($teacherscope));
        $this->assertTrue(request::can_open_panel($managerscope));

        $requests = [
            ['all', new filter()],
            ['cohort', new filter(cohortid: $cohort)],
            ['range', $range],
        ];
        foreach ($requests as [$type, $filter]) {
            $this->assertSame('accessdenied', $this->error_of(fn() => request::check_zip($teacherscope, $type, $filter)), $type);
            $this->assertNull($this->error_of(fn() => request::check_zip($managerscope, $type, $filter)), $type);
        }

        $this->assertNull($this->error_of(fn() => request::check_zip($teacherscope, 'course', new filter(courseid: $coursea->id))));
        $this->assertSame(
            'accessdenied',
            $this->error_of(fn() => request::check_zip($teacherscope, 'course', new filter(courseid: $courseb->id)))
        );
        $this->assertNull($this->error_of(fn() => request::check_zip($managerscope, 'course', new filter(courseid: $courseb->id))));
        $this->assertNull($this->error_of(fn() => request::check_zip($teacherscope, 'user', new filter(userid: $student->id))));
        $this->assertSame('coursenotselected', $this->error_of(fn() => request::check_zip($teacherscope, 'course', new filter())));
        $this->assertSame('invalidtype', $this->error_of(fn() => request::check_zip($managerscope, 'everything', new filter())));
    }

    /**
     * A range that ends at or before its start, and a user request without a user, are refused;
     * the same requests with valid values pass.
     */
    public function test_check_zip_refuses_a_bad_range_and_a_user_request_without_a_user(): void {
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id);
        $student = $this->getDataGenerator()->create_user();
        $scope = scope::for_user((int) $manager->id);

        $this->assertNull($this->error_of(fn() => request::check_zip($scope, 'range', new filter(datefrom: 1000, dateto: 2000))));
        $this->assertSame(
            'invalidaterange',
            $this->error_of(fn() => request::check_zip($scope, 'range', new filter(datefrom: 2000, dateto: 2000)))
        );
        $this->assertSame(
            'invalidaterange',
            $this->error_of(fn() => request::check_zip($scope, 'range', new filter(datefrom: 2000, dateto: 1000)))
        );

        $this->assertNull($this->error_of(fn() => request::check_zip($scope, 'user', new filter(userid: $student->id))));
        $this->assertSame('invalidparameters', $this->error_of(fn() => request::check_zip($scope, 'user', new filter())));
    }

    /**
     * A user who reaches nobody else may ask for their own certificates only.
     */
    public function test_check_zip_keeps_a_learner_to_their_own(): void {
        $course = $this->getDataGenerator()->create_course();
        $learner = $this->enrolled($course, 'student');
        $other = $this->enrolled($course, 'student');
        $scope = scope::for_user((int) $learner->id);

        $this->assertFalse(request::can_open_panel($scope));
        $this->assertNull($this->error_of(fn() => request::check_zip($scope, 'user', new filter(userid: $learner->id))));
        $this->assertSame(
            'accessdenied',
            $this->error_of(fn() => request::check_zip($scope, 'user', new filter(userid: $other->id)))
        );
        $this->assertSame(
            'accessdenied',
            $this->error_of(fn() => request::check_zip($scope, 'course', new filter(courseid: $course->id)))
        );
    }

    /**
     * A cohort must be one the user may list: visible, or `moodle/cohort:view` in its context.
     */
    public function test_check_zip_cohort_must_be_listable(): void {
        $gen = $this->getDataGenerator();
        $visible = (int) $gen->create_cohort()->id;
        $hidden = (int) $gen->create_cohort(['visible' => 0])->id;
        $plain = scope::for_user((int) $this->user_with(['block/download_certificates:manage'])->id);
        $viewer = scope::for_user((int) $this->user_with(['block/download_certificates:manage', 'moodle/cohort:view'])->id);

        $this->assertNull($this->error_of(fn() => request::check_zip($plain, 'cohort', new filter(cohortid: $visible))));
        $this->assertSame(
            'accessdenied',
            $this->error_of(fn() => request::check_zip($plain, 'cohort', new filter(cohortid: $hidden)))
        );
        $this->assertNull($this->error_of(fn() => request::check_zip($viewer, 'cohort', new filter(cohortid: $hidden))));
        $this->assertSame(
            'accessdenied',
            $this->error_of(fn() => request::check_zip($viewer, 'cohort', new filter(cohortid: $hidden + 1000)))
        );
    }

    /**
     * The group menu follows the scope's group rule: a teacher under separate groups gets their own
     * groups, a manager and a teacher with `moodle/site:accessallgroups` every group; a course outside
     * the user's courses is refused.
     */
    public function test_course_groups_follow_the_group_rule(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['groupmode' => SEPARATEGROUPS]);
        $other = $gen->create_course();
        $mine = (int) $gen->create_group(['courseid' => $course->id])->id;
        $theirs = (int) $gen->create_group(['courseid' => $course->id])->id;
        $teacher = $this->enrolled($course, 'teacher');
        $gen->create_group_member(['groupid' => $mine, 'userid' => $teacher->id]);
        $editing = $this->enrolled($course, 'editingteacher');
        $manager = $gen->create_user();
        $gen->role_assign('manager', $manager->id);

        $ids = static fn(array $groups): array => array_column($groups, 'id');
        $this->assertSame([$mine], $ids(request::course_groups(scope::for_user((int) $teacher->id), (int) $course->id)));
        $this->assertEqualsCanonicalizing(
            [$mine, $theirs],
            $ids(request::course_groups(scope::for_user((int) $editing->id), (int) $course->id))
        );
        $this->assertEqualsCanonicalizing(
            [$mine, $theirs],
            $ids(request::course_groups(scope::for_user((int) $manager->id), (int) $course->id))
        );
        $this->assertSame(
            'accessdenied',
            $this->error_of(fn() => request::course_groups(scope::for_user((int) $teacher->id), (int) $other->id))
        );
    }

    /**
     * A client sees the plugin's own reason, and a generic one for anything else.
     */
    public function test_error_message_hides_other_components_text(): void {
        $this->assertSame(
            get_string('accessdenied', 'block_download_certificates'),
            request::error_message(new \moodle_exception('accessdenied', 'block_download_certificates'))
        );
        $generic = get_string('requestfailed', 'block_download_certificates');
        $this->assertSame($generic, request::error_message(new \dml_read_exception('SELECT secret FROM x')));
        $this->assertSame($generic, request::error_message(new \coding_exception('internal detail')));
        $this->assertSame($generic, request::error_message(new \Error('Call to undefined method')));
    }

    /**
     * The table's sort key is mapped at the boundary, and ZIP names carry ids, never names.
     */
    public function test_sort_key_and_zip_filename(): void {
        $this->assertSame('type', request::sort_key('cert_type'));
        $this->assertSame('lastname', request::sort_key('lastname'));
        $this->assertSame('certificates.zip', request::zip_filename('all', new filter()));
        $this->assertSame('certificates_course_12.zip', request::zip_filename('course', new filter(courseid: 12, userid: 3)));
        $this->assertSame('certificates_range.zip', request::zip_filename('range', new filter(datefrom: 1, dateto: 2)));
    }

    /**
     * The search page maps the library's rows to the web service's shape: names formatted but not
     * escaped, the source's name, a single download by type and issue id without a session key, the
     * page size kept in bounds; the filter narrows.
     */
    public function test_search_page_shape(): void {
        if (!(new tool_certificate())->is_available()) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
        $owner = $this->getDataGenerator()->create_user(['firstname' => 'Ada & Co', 'lastname' => 'Lovelace']);
        $this->setUser($owner);
        $template = issue_factory::tool_certificate_template('Gold & "Silver"')->get_id();
        $issueid = issue_factory::tool_certificate_issue($template, (int) $owner->id, 0, 1000, 'CODE1');
        $repository = new repository(['tool_certificate' => new tool_certificate()]);
        $scope = scope::for_user((int) $owner->id);

        $page = request::search_page($scope, new filter(), 0, 5000, 'cert_type', 'ASC', $repository);

        $this->assertSame(1, $page['total']);
        $this->assertSame(200, $page['perpage']);
        $this->assertSame(1, $page['pages']);
        $row = $page['rows'][0];
        $this->assertSame('tool_certificate', $row['type']);
        $this->assertSame((new tool_certificate())->get_name(), $row['typename']);
        $this->assertSame('Ada & Co Lovelace', $row['fullname']);
        $this->assertSame('Gold & "Silver"', $row['templatename']);
        $this->assertSame('CODE1', $row['code']);
        $this->assertSame(1000, $row['timecreated']);
        $url = new \moodle_url($row['downloadurl']);
        $this->assertStringEndsWith('/blocks/download_certificates/download_precise.php', $url->get_path(false));
        $this->assertSame(['type' => 'tool_certificate', 'issueid' => (string) $issueid], $url->params());

        // The filter narrows: a search that matches nothing leaves no row.
        $none = request::search_page($scope, new filter(search: 'no such text'), 0, 50, 'timecreated', 'DESC', $repository);
        $this->assertSame(0, $none['total']);
        $this->assertSame([], $none['rows']);
    }

    /**
     * E-mail follows core's identity fields: a manager's page shows it while `showuseridentity`
     * lists it and the manager may see identities site-wide; a teacher's only while every course of
     * theirs allows it; never for a learner, nor for a scope that is not the current user's.
     */
    public function test_email_follows_the_identity_fields(): void {
        global $DB;

        $gen = $this->getDataGenerator();
        $manager = $gen->create_user();
        $gen->role_assign('manager', $manager->id);
        $this->setUser($manager);
        $this->assertTrue(request::email_visible(scope::for_user((int) $manager->id)));

        set_config('showuseridentity', 'idnumber');
        $this->assertFalse(request::email_visible(scope::for_user((int) $manager->id)));
        set_config('showuseridentity', 'email');

        $coursea = $gen->create_course();
        $courseb = $gen->create_course();
        $teacher = $this->enrolled($coursea, 'editingteacher');
        $gen->enrol_user($teacher->id, $courseb->id, 'editingteacher');
        $this->setUser($teacher);
        $scope = scope::for_user((int) $teacher->id);
        $this->assertEqualsCanonicalizing([(int) $coursea->id, (int) $courseb->id], $scope->get_course_ids());
        $this->assertTrue(request::email_visible($scope));

        // Somebody else's scope is refused, since core's helper reads the current user.
        $this->setUser($manager);
        $this->assertFalse(request::email_visible($scope));
        $this->setUser($teacher);

        // One course that withholds identities hides the column on the whole page.
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability(
            'moodle/site:viewuseridentity',
            CAP_PROHIBIT,
            $roleid,
            \context_course::instance($courseb->id)->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(request::email_visible(scope::for_user((int) $teacher->id)));
        $this->assertTrue(request::email_visible(scope::for_user((int) $teacher->id, (int) $coursea->id)));

        // A learner opens no panel and sees no e-mail.
        $learner = $this->enrolled($coursea, 'student');
        $this->setUser($learner);
        $this->assertFalse(request::email_visible(scope::for_user((int) $learner->id)));
    }

    /**
     * Where the e-mail is hidden, the search page leaves it empty and neither matches nor sorts by
     * it; where it is shown (the control), it does all three.
     */
    public function test_search_page_hides_email_where_core_does(): void {
        if (!(new tool_certificate())->is_available()) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $this->enrolled($course, 'editingteacher');
        $zed = $gen->create_user(['firstname' => 'Ada', 'lastname' => 'One', 'email' => 'zed@example.com']);
        $abe = $gen->create_user(['firstname' => 'Ada', 'lastname' => 'Two', 'email' => 'abe@example.com']);
        $gen->enrol_user($zed->id, $course->id, 'student');
        $gen->enrol_user($abe->id, $course->id, 'student');
        $template = issue_factory::tool_certificate_template('Gold')->get_id();
        issue_factory::tool_certificate_issue($template, (int) $zed->id, (int) $course->id, 1000, 'ZZZ1');
        issue_factory::tool_certificate_issue($template, (int) $abe->id, (int) $course->id, 2000, 'AAA2');
        $repository = new repository(['tool_certificate' => new tool_certificate()]);
        $this->setUser($teacher);
        $owners = static fn(array $page): array => array_column($page['rows'], 'userid');

        $shown = request::search_page(scope::for_user((int) $teacher->id), new filter(), 0, 50, 'email', 'ASC', $repository);
        $this->assertTrue($shown['showemail']);
        $this->assertSame([(int) $abe->id, (int) $zed->id], $owners($shown));
        $this->assertSame(['abe@example.com', 'zed@example.com'], array_column($shown['rows'], 'email'));
        $found = request::search_page(
            scope::for_user((int) $teacher->id),
            new filter(search: 'zed@example'),
            0,
            50,
            'timecreated',
            'DESC',
            $repository
        );
        $this->assertSame(1, $found['total']);

        set_config('showuseridentity', 'idnumber');
        $hidden = request::search_page(scope::for_user((int) $teacher->id), new filter(), 0, 50, 'email', 'ASC', $repository);
        $this->assertFalse($hidden['showemail']);
        $this->assertSame([(int) $zed->id, (int) $abe->id], $owners($hidden));
        $this->assertSame(['', ''], array_column($hidden['rows'], 'email'));
        $none = request::search_page(
            scope::for_user((int) $teacher->id),
            new filter(search: 'zed@example'),
            0,
            50,
            'timecreated',
            'DESC',
            $repository
        );
        $this->assertSame(0, $none['total']);
        $byname = request::search_page(
            scope::for_user((int) $teacher->id),
            new filter(search: 'Ada One'),
            0,
            50,
            'timecreated',
            'DESC',
            $repository
        );
        $this->assertSame(1, $byname['total']);
    }

    /**
     * The ZIP pages accept a session key from a POST body only: one in the URL, a wrong one or none
     * is refused.
     */
    public function test_require_post_sesskey(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $refused = fn() => $this->error_of(fn() => request::require_post_sesskey());

        $_POST = [];
        $_GET = ['sesskey' => sesskey()];
        $this->assertSame('invalidsesskey', $refused());

        $_POST = ['action' => 'download_all'];
        $this->assertSame('invalidsesskey', $refused());

        $_POST = ['sesskey' => 'wrong'];
        $this->assertSame('invalidsesskey', $refused());

        $_POST = ['sesskey' => ['array']];
        $this->assertSame('invalidsesskey', $refused());

        // The control: the key in the body passes.
        $_GET = [];
        $_POST = ['sesskey' => sesskey()];
        $this->assertNull($refused());
    }
}
