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
 * Tests for the certificate scope.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

/**
 * The scope decides who sees which (owner, course) pair; can_see() runs the scope's own SQL, so
 * these tests exercise get_sql() without any certificate plugin installed.
 *
 * Metadata stays in docblock form so the test files stay identical across the plugin's branches.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\local\scope
 */
final class scope_test extends \advanced_testcase {
    /**
     * Reset the database after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a course with a group mode.
     *
     * @param int $groupmode NOGROUPS, SEPARATEGROUPS or VISIBLEGROUPS.
     * @return \stdClass
     */
    private function course(int $groupmode = NOGROUPS): \stdClass {
        return $this->getDataGenerator()->create_course(['groupmode' => $groupmode]);
    }

    /**
     * Create a user enrolled in a course with a role.
     *
     * @param \stdClass $course The course.
     * @param string $role The role shortname.
     * @param int $status ENROL_USER_ACTIVE or ENROL_USER_SUSPENDED.
     * @return \stdClass
     */
    private function enrolled(\stdClass $course, string $role, int $status = ENROL_USER_ACTIVE): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role, 'manual', 0, 0, $status);
        return $user;
    }

    /**
     * Put a user in a new or given group.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $user The user.
     * @param int $groupid The group, 0 for a new one.
     * @return int The group id.
     */
    private function in_group(\stdClass $course, \stdClass $user, int $groupid = 0): int {
        if (!$groupid) {
            $groupid = (int) $this->getDataGenerator()->create_group(['courseid' => $course->id])->id;
        }
        $this->getDataGenerator()->create_group_member(['groupid' => $groupid, 'userid' => $user->id]);
        return $groupid;
    }

    /**
     * Create a role allowing the given capabilities in the system context.
     *
     * @param string[] $capabilities The capabilities.
     * @return int The role id.
     */
    private function role_with(array $capabilities): int {
        $roleid = (int) $this->getDataGenerator()->create_role();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        }
        return $roleid;
    }

    /**
     * A manager sees every pair; the narrowing keeps one course or one owner.
     */
    public function test_manager_sees_everything(): void {
        $coursea = $this->course();
        $courseb = $this->course();
        $student = $this->enrolled($coursea, 'student');
        $stranger = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id);

        $scope = scope::for_user((int) $manager->id);
        $this->assertTrue($scope->is_manager());
        $this->assertTrue($scope->can_see_others());
        $this->assertSame([], $scope->get_course_ids());
        $this->assertTrue($scope->can_see((int) $student->id, (int) $coursea->id));
        $this->assertTrue($scope->can_see((int) $stranger->id, 0));
        $this->assertTrue($scope->can_see((int) $stranger->id, (int) $courseb->id));

        $narrowed = scope::for_user((int) $manager->id, (int) $coursea->id);
        $this->assertTrue($narrowed->can_see((int) $student->id, (int) $coursea->id));
        $this->assertFalse($narrowed->can_see((int) $student->id, (int) $courseb->id));

        $profile = scope::for_user((int) $manager->id, null, (int) $student->id);
        $this->assertTrue($profile->can_see((int) $student->id, (int) $courseb->id));
        $this->assertFalse($profile->can_see((int) $stranger->id, (int) $coursea->id));
    }

    /**
     * A user without any capability sees their own certificates and nothing else.
     */
    public function test_own_certificates_only_without_capability(): void {
        $course = $this->course();
        $student = $this->enrolled($course, 'student');
        $other = $this->enrolled($course, 'student');

        $scope = scope::for_user((int) $student->id);
        $this->assertFalse($scope->is_manager());
        $this->assertFalse($scope->can_see_others());
        $this->assertSame([], $scope->get_course_ids());
        $this->assertTrue($scope->can_see((int) $student->id, (int) $course->id));
        $this->assertTrue($scope->can_see((int) $student->id, 0));
        $this->assertFalse($scope->can_see((int) $other->id, (int) $course->id));
    }

    /**
     * A teacher in a course without groups sees its active participants, in that course only.
     */
    public function test_teacher_without_groups(): void {
        $coursea = $this->course();
        $courseb = $this->course();
        $teacher = $this->enrolled($coursea, 'teacher');
        $student = $this->enrolled($coursea, 'student');
        $this->getDataGenerator()->enrol_user($student->id, $courseb->id, 'student');
        $outsider = $this->enrolled($courseb, 'student');

        $scope = scope::for_user((int) $teacher->id);
        $this->assertSame([(int) $coursea->id], $scope->get_course_ids());
        $this->assertTrue($scope->can_see_others());
        $this->assertTrue($scope->can_see((int) $student->id, (int) $coursea->id));
        $this->assertFalse($scope->can_see((int) $student->id, (int) $courseb->id));
        $this->assertFalse($scope->can_see((int) $outsider->id, (int) $coursea->id));
        $this->assertFalse($scope->can_see((int) $outsider->id, (int) $courseb->id));
    }

    /**
     * Under separate groups a teacher without accessallgroups sees the members of their groups only.
     */
    public function test_separate_groups_without_accessallgroups(): void {
        $course = $this->course(SEPARATEGROUPS);
        $teacher = $this->enrolled($course, 'teacher');
        $mate = $this->enrolled($course, 'student');
        $other = $this->enrolled($course, 'student');
        $nogroup = $this->enrolled($course, 'student');
        $groupid = $this->in_group($course, $teacher);
        $this->in_group($course, $mate, $groupid);
        $this->in_group($course, $other);

        $scope = scope::for_user((int) $teacher->id);
        $this->assertTrue($scope->can_see((int) $mate->id, (int) $course->id));
        $this->assertFalse($scope->can_see((int) $other->id, (int) $course->id));
        $this->assertFalse($scope->can_see((int) $nogroup->id, (int) $course->id));
    }

    /**
     * Under separate groups accessallgroups (editing teacher) reaches every participant.
     */
    public function test_separate_groups_with_accessallgroups(): void {
        $course = $this->course(SEPARATEGROUPS);
        $teacher = $this->enrolled($course, 'editingteacher');
        $mate = $this->enrolled($course, 'student');
        $other = $this->enrolled($course, 'student');
        $nogroup = $this->enrolled($course, 'student');
        $groupid = $this->in_group($course, $teacher);
        $this->in_group($course, $mate, $groupid);
        $this->in_group($course, $other);

        $scope = scope::for_user((int) $teacher->id);
        $this->assertTrue($scope->can_see((int) $mate->id, (int) $course->id));
        $this->assertTrue($scope->can_see((int) $other->id, (int) $course->id));
        $this->assertTrue($scope->can_see((int) $nogroup->id, (int) $course->id));
    }

    /**
     * Visible groups do not separate: a teacher in one group sees every participant.
     */
    public function test_visible_groups(): void {
        $course = $this->course(VISIBLEGROUPS);
        $teacher = $this->enrolled($course, 'teacher');
        $other = $this->enrolled($course, 'student');
        $nogroup = $this->enrolled($course, 'student');
        $this->in_group($course, $teacher);
        $this->in_group($course, $other);

        $scope = scope::for_user((int) $teacher->id);
        $this->assertTrue($scope->can_see((int) $other->id, (int) $course->id));
        $this->assertTrue($scope->can_see((int) $nogroup->id, (int) $course->id));
    }

    /**
     * Under separate groups a teacher in no group sees nobody but themself, yet keeps the course.
     */
    public function test_separate_groups_teacher_in_no_group(): void {
        $course = $this->course(SEPARATEGROUPS);
        $teacher = $this->enrolled($course, 'teacher');
        $student = $this->enrolled($course, 'student');
        $this->in_group($course, $student);

        $scope = scope::for_user((int) $teacher->id);
        $this->assertSame([(int) $course->id], $scope->get_course_ids());
        $this->assertFalse($scope->can_see_others());
        $this->assertFalse($scope->can_see((int) $student->id, (int) $course->id));
        $this->assertTrue($scope->can_see((int) $teacher->id, (int) $course->id));
    }

    /**
     * Sharing a group in course A does not admit the same student's certificate of course B.
     */
    public function test_group_of_one_course_does_not_admit_another(): void {
        $coursea = $this->course(SEPARATEGROUPS);
        $courseb = $this->course(SEPARATEGROUPS);
        $teacher = $this->enrolled($coursea, 'teacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $courseb->id, 'teacher');
        $student = $this->enrolled($coursea, 'student');
        $this->getDataGenerator()->enrol_user($student->id, $courseb->id, 'student');

        $groupa = $this->in_group($coursea, $teacher);
        $this->in_group($coursea, $student, $groupa);
        $this->in_group($courseb, $teacher);
        $this->in_group($courseb, $student);

        $scope = scope::for_user((int) $teacher->id);
        $this->assertTrue($scope->can_see((int) $student->id, (int) $coursea->id));
        $this->assertFalse($scope->can_see((int) $student->id, (int) $courseb->id));
    }

    /**
     * Suspended enrolments, disabled instances and ended enrolments are not active participants.
     */
    public function test_inactive_student_enrolments_excluded(): void {
        global $DB;

        $course = $this->course();
        $teacher = $this->enrolled($course, 'teacher');
        $active = $this->enrolled($course, 'student');
        $suspended = $this->enrolled($course, 'student', ENROL_USER_SUSPENDED);
        $ended = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($ended->id, $course->id, 'student', 'manual', 0, time() - DAYSECS);

        $disabled = $this->getDataGenerator()->create_user();
        $selfplugin = enrol_get_plugin('self');
        $instanceid = $selfplugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $selfplugin->enrol_user($instance, $disabled->id, $DB->get_field('role', 'id', ['shortname' => 'student']));
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $instanceid]);

        $scope = scope::for_user((int) $teacher->id);
        $this->assertTrue($scope->can_see((int) $active->id, (int) $course->id));
        $this->assertFalse($scope->can_see((int) $suspended->id, (int) $course->id));
        $this->assertFalse($scope->can_see((int) $ended->id, (int) $course->id));
        $this->assertFalse($scope->can_see((int) $disabled->id, (int) $course->id));
    }

    /**
     * A hidden course is out of a teacher's scope unless they may view hidden courses there.
     *
     * The teacher holds a role of its own: both teacher archetypes may view hidden courses.
     */
    public function test_hidden_course_needs_viewhiddencourses(): void {
        $visible = $this->course();
        $hidden = $this->getDataGenerator()->create_course(['visible' => 0]);
        $roleid = $this->role_with(['block/download_certificates:downloadcourse']);
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->enrolled($visible, 'student');
        foreach ([$visible, $hidden] as $course) {
            $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $roleid);
        }
        $this->getDataGenerator()->enrol_user($student->id, $hidden->id, 'student');

        $scope = scope::for_user((int) $teacher->id);
        $this->assertTrue($scope->can_see((int) $student->id, (int) $visible->id));
        $this->assertFalse($scope->can_see((int) $student->id, (int) $hidden->id));
        $this->assertSame([(int) $visible->id], $scope->get_course_ids());

        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $scope = scope::for_user((int) $teacher->id);
        $this->assertTrue($scope->can_see((int) $student->id, (int) $hidden->id));
    }

    /**
     * A student enrolment that starts in the future is not an active participant; one that started
     * in the past is.
     */
    public function test_enrolment_not_started_is_excluded(): void {
        $course = $this->course();
        $teacher = $this->enrolled($course, 'teacher');
        $future = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($future->id, $course->id, 'student', 'manual', time() + DAYSECS);
        $past = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($past->id, $course->id, 'student', 'manual', time() - DAYSECS);

        $scope = scope::for_user((int) $teacher->id);
        $this->assertFalse($scope->can_see((int) $future->id, (int) $course->id));
        $this->assertTrue($scope->can_see((int) $past->id, (int) $course->id));
    }

    /**
     * The enrolment that counts is the one in the certificate's course: an active enrolment in
     * course B does not admit a certificate of course A where the student is suspended.
     */
    public function test_enrolment_is_tied_to_the_certificates_course(): void {
        $coursea = $this->course();
        $courseb = $this->course();
        $teacher = $this->enrolled($coursea, 'teacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $courseb->id, 'teacher');
        $student = $this->enrolled($coursea, 'student', ENROL_USER_SUSPENDED);
        $this->getDataGenerator()->enrol_user($student->id, $courseb->id, 'student');

        $scope = scope::for_user((int) $teacher->id);
        $this->assertFalse($scope->can_see((int) $student->id, (int) $coursea->id));
        $this->assertTrue($scope->can_see((int) $student->id, (int) $courseb->id));

        $this->getDataGenerator()->enrol_user($student->id, $coursea->id, 'student', 'manual', 0, 0, ENROL_USER_ACTIVE);
        $this->assertTrue(scope::for_user((int) $teacher->id)->can_see((int) $student->id, (int) $coursea->id));
    }

    /**
     * A teacher whose own enrolment is suspended can no longer enter, so loses the course.
     */
    public function test_suspended_teacher_loses_course(): void {
        $course = $this->course();
        $teacher = $this->enrolled($course, 'teacher', ENROL_USER_SUSPENDED);
        $student = $this->enrolled($course, 'student');

        $scope = scope::for_user((int) $teacher->id);
        $this->assertSame([], $scope->get_course_ids());
        $this->assertFalse($scope->can_see((int) $student->id, (int) $course->id));
    }

    /**
     * A role at category level keeps the course through moodle/course:view, without an enrolment;
     * without moodle/course:view the same role cannot enter.
     */
    public function test_category_role_needs_course_view(): void {
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $student = $this->enrolled($course, 'student');
        $categorycontext = \context_coursecat::instance($category->id);

        $viewer = $this->getDataGenerator()->create_user();
        $viewerrole = $this->role_with(['block/download_certificates:downloadcourse', 'moodle/course:view']);
        role_assign($viewerrole, $viewer->id, $categorycontext->id);

        $blind = $this->getDataGenerator()->create_user();
        $blindrole = $this->role_with(['block/download_certificates:downloadcourse']);
        role_assign($blindrole, $blind->id, $categorycontext->id);

        $scope = scope::for_user((int) $viewer->id);
        $this->assertSame([(int) $course->id], $scope->get_course_ids());
        $this->assertTrue($scope->can_see((int) $student->id, (int) $course->id));

        $blindscope = scope::for_user((int) $blind->id);
        $this->assertSame([], $blindscope->get_course_ids());
        $this->assertFalse($blindscope->can_see((int) $student->id, (int) $course->id));
    }

    /**
     * The site course is never in scope, though every user counts as enrolled on it.
     */
    public function test_site_course_never_in_scope(): void {
        $course = $this->course();
        $student = $this->enrolled($course, 'student');
        $sitewide = $this->getDataGenerator()->create_user();
        $role = $this->role_with(['block/download_certificates:downloadcourse', 'moodle/course:view']);
        role_assign($role, $sitewide->id, \context_system::instance()->id);

        $scope = scope::for_user((int) $sitewide->id);
        $this->assertContains((int) $course->id, $scope->get_course_ids());
        $this->assertNotContains((int) SITEID, $scope->get_course_ids());
        $this->assertTrue($scope->can_see((int) $student->id, (int) $course->id));
        $this->assertFalse($scope->can_see((int) $student->id, (int) SITEID));
    }

    /**
     * The course and profile narrowing apply to every rule, the user's own certificates included.
     */
    public function test_course_and_profile_narrowing(): void {
        $coursea = $this->course();
        $courseb = $this->course();
        $teacher = $this->enrolled($coursea, 'teacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $courseb->id, 'teacher');
        $both = $this->enrolled($coursea, 'student');
        $this->getDataGenerator()->enrol_user($both->id, $courseb->id, 'student');
        $onlya = $this->enrolled($coursea, 'student');

        $bycourse = scope::for_user((int) $teacher->id, (int) $coursea->id);
        $this->assertSame((int) $coursea->id, $bycourse->get_course_id());
        $this->assertSame([(int) $coursea->id], $bycourse->get_course_ids());
        $this->assertTrue($bycourse->can_see((int) $both->id, (int) $coursea->id));
        $this->assertFalse($bycourse->can_see((int) $both->id, (int) $courseb->id));
        $this->assertFalse($bycourse->can_see((int) $teacher->id, (int) $courseb->id));

        $byprofile = scope::for_user((int) $teacher->id, null, (int) $both->id);
        $this->assertSame((int) $both->id, $byprofile->get_profile_user_id());
        $this->assertTrue($byprofile->can_see((int) $both->id, (int) $courseb->id));
        $this->assertFalse($byprofile->can_see((int) $onlya->id, (int) $coursea->id));
        $this->assertFalse($byprofile->can_see((int) $teacher->id, (int) $coursea->id));

        $unnarrowed = scope::for_user((int) $teacher->id, 0, 0);
        $this->assertNull($unnarrowed->get_course_id());
        $this->assertNull($unnarrowed->get_profile_user_id());
        $this->assertTrue($unnarrowed->can_see((int) $onlya->id, (int) $coursea->id));
    }

    /**
     * Two get_sql() calls share no placeholder name and run together in one statement.
     */
    public function test_placeholders_unique_across_calls(): void {
        global $DB;

        $coursea = $this->course(SEPARATEGROUPS);
        $courseb = $this->course();
        $teacher = $this->enrolled($coursea, 'teacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $courseb->id, 'teacher');
        $student = $this->enrolled($coursea, 'student');
        $groupid = $this->in_group($coursea, $teacher);
        $this->in_group($coursea, $student, $groupid);

        $scope = scope::for_user((int) $teacher->id);
        [$where1, $params1] = $scope->get_sql('u.id', (string) $coursea->id);
        [$where2, $params2] = $scope->get_sql('u.id', (string) $coursea->id);
        [$where3, $params3] = scope::for_user((int) $student->id)->get_sql('u.id', (string) $coursea->id);

        $this->assertSame([], array_intersect_key($params1, $params2));
        $this->assertSame([], array_intersect_key($params1, $params3));
        $this->assertSame([], array_intersect_key($params2, $params3));

        $count = $DB->count_records_sql(
            "SELECT COUNT(1) FROM {user} u WHERE ({$where1}) AND ({$where2}) AND ({$where3})",
            $params1 + $params2 + $params3
        );
        // Only the student is in the teacher's scope and in their own.
        $this->assertSame(1, (int) $count);
    }
}
