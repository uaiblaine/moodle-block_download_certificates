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

namespace block_download_certificates\output;

use block_download_certificates\local\source\issue_factory;
use block_download_certificates\local\source\tool_certificate;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/download_certificates/tests/fixtures/issue_factory.php');

/**
 * The block's context on each page it can stand on: the dashboard and the front page show the
 * site-wide summary, a course page that course (its "download all" asks for the course ZIP), a
 * profile that user's certificates within the viewer's scope, and nothing when the scope does not
 * reach that user.
 *
 * The tests with certificates skip, not pass, without tool_certificate.
 *
 * Metadata stays in docblock form so the test files stay identical across the plugin's branches.
 *
 * @package   block_download_certificates
 * @category  test
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \block_download_certificates\output\block_content
 * @covers    \block_download_certificates\output\zip_form
 */
final class block_content_test extends \advanced_testcase {
    /** @var \stdClass Course A. */
    private \stdClass $coursea;

    /** @var \stdClass Course B. */
    private \stdClass $courseb;

    /** @var \stdClass A student of courses A and B, with a certificate in each. */
    private \stdClass $ada;

    /** @var \stdClass A student of course B, with a certificate in B. */
    private \stdClass $bob;

    /** @var \stdClass The teacher of course A. */
    private \stdClass $teacher;

    /** @var \stdClass A site manager. */
    private \stdClass $manager;

    /** @var int[] Issue ids: a1 (Ada, A), b1 (Ada, B), b2 (Bob, B). */
    private array $issues = [];

    /**
     * Reset the database after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Two courses, a teacher of A, two students and a manager; with tool_certificate, three
     * certificates.
     *
     * @param bool $withcertificates Whether to issue the certificates, which needs tool_certificate.
     */
    private function create_site(bool $withcertificates): void {
        $gen = $this->getDataGenerator();
        $this->coursea = $gen->create_course(['fullname' => 'Algebra']);
        $this->courseb = $gen->create_course(['fullname' => 'Botany']);
        $this->ada = $gen->create_user();
        $this->bob = $gen->create_user();
        $this->teacher = $gen->create_user();
        $this->manager = $gen->create_user();
        $gen->enrol_user($this->ada->id, $this->coursea->id, 'student');
        $gen->enrol_user($this->ada->id, $this->courseb->id, 'student');
        $gen->enrol_user($this->bob->id, $this->courseb->id, 'student');
        $gen->enrol_user($this->teacher->id, $this->coursea->id, 'teacher');
        $gen->role_assign('manager', $this->manager->id);

        if (!$withcertificates) {
            return;
        }
        if (!(new tool_certificate())->is_available()) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
        $template = issue_factory::tool_certificate_template('Gold')->get_id();
        $now = time();
        $ada = (int) $this->ada->id;
        $bob = (int) $this->bob->id;
        $this->issues = [
            'a1' => issue_factory::tool_certificate_issue($template, $ada, (int) $this->coursea->id, $now - 100, 'A1'),
            'b1' => issue_factory::tool_certificate_issue($template, $ada, (int) $this->courseb->id, $now - 50, 'B1'),
            'b2' => issue_factory::tool_certificate_issue($template, $bob, (int) $this->courseb->id, $now, 'B2'),
        ];
    }

    /**
     * The block's context for the current user on a page.
     *
     * @param \moodle_page $page The page.
     * @return array
     */
    private function export(\moodle_page $page): array {
        global $USER;

        return block_content::for_page($page, (int) $USER->id)->export_for_template($page->get_renderer('core'));
    }

    /**
     * The dashboard of the current user.
     *
     * @return \moodle_page
     */
    private function dashboard(): \moodle_page {
        global $USER;

        $page = new \moodle_page();
        $page->set_context(\context_user::instance($USER->id));
        $page->set_pagetype('my-index');
        $page->set_url('/my/');
        return $page;
    }

    /**
     * The site front page.
     *
     * @return \moodle_page
     */
    private function front_page(): \moodle_page {
        $page = new \moodle_page();
        $page->set_course(get_site());
        $page->set_pagetype('site-index');
        $page->set_url('/');
        return $page;
    }

    /**
     * A course page.
     *
     * @param \stdClass $course The course.
     * @return \moodle_page
     */
    private function course_page(\stdClass $course): \moodle_page {
        $page = new \moodle_page();
        $page->set_course($course);
        $page->set_pagetype('course-view-topics');
        $page->set_url('/course/view.php', ['id' => $course->id]);
        return $page;
    }

    /**
     * A user's profile.
     *
     * @param \stdClass $user The profile's user.
     * @return \moodle_page
     */
    private function profile(\stdClass $user): \moodle_page {
        $page = new \moodle_page();
        $page->set_context(\context_user::instance($user->id));
        $page->set_pagetype('user-profile');
        $page->set_url('/user/profile.php', ['id' => $user->id]);
        return $page;
    }

    /**
     * The hidden fields of a ZIP form context, as name => value.
     *
     * @param array $zip The context.
     * @return array
     */
    private function fields(array $zip): array {
        return array_column($zip['fields'], 'value', 'name');
    }

    /**
     * The value of one manager stat by its label's key.
     *
     * @param array $manager The manager section.
     * @param string $key The lang key of the label.
     * @return int|null Null when the stat is absent.
     */
    private function stat(array $manager, string $key): ?int {
        $label = get_string($key, 'block_download_certificates');
        foreach ($manager['stats'] as $stat) {
            if ($stat['label'] === $label) {
                return (int) $stat['value'];
            }
        }
        return null;
    }

    /**
     * The dashboard and the front page are site-wide; a course page and a profile narrow.
     */
    public function test_placement_of_each_page(): void {
        $this->create_site(false);
        $this->setUser($this->ada);

        $this->assertSame([block_content::PLACEMENT_SITE, null, null], block_content::placement_of($this->dashboard()));
        $this->assertSame([block_content::PLACEMENT_SITE, null, null], block_content::placement_of($this->front_page()));
        $this->assertSame(
            [block_content::PLACEMENT_COURSE, (int) $this->coursea->id, null],
            block_content::placement_of($this->course_page($this->coursea))
        );
        $this->assertSame(
            [block_content::PLACEMENT_PROFILE, null, (int) $this->bob->id],
            block_content::placement_of($this->profile($this->bob))
        );
        // The control: the user context alone does not make a profile; the dashboard stands in it too.
        $this->assertSame(\CONTEXT_USER, (int) $this->dashboard()->context->contextlevel);
    }

    /**
     * On the dashboard and the front page a manager's "download all" asks for every certificate,
     * with a confirmation, and the summary counts the whole site.
     */
    public function test_manager_on_site_pages_downloads_everything(): void {
        $this->create_site(true);
        $this->setUser($this->manager);

        foreach (['dashboard' => $this->dashboard(), 'front page' => $this->front_page()] as $name => $page) {
            $data = $this->export($page);
            $this->assertSame(block_content::PLACEMENT_SITE, $data['placement'], $name);
            $this->assertSame(3, $this->stat($data['manager'], 'total'), $name);
            $this->assertSame(2, $this->stat($data['manager'], 'courses'), $name);
            $this->assertSame('all', $data['manager']['zip']['type'], $name);
            $this->assertSame(3, $data['manager']['zip']['count'], $name);
            $this->assertStringEndsWith('/blocks/download_certificates/download.php', $data['manager']['zip']['url'], $name);
            $this->assertSame(['action' => 'download_all'], $this->fields($data['manager']['zip']), $name);
            $confirm = get_string('confirm_download_all', 'block_download_certificates');
            $this->assertSame($confirm, $data['manager']['zip']['confirm'], $name);
            $this->assertTrue($data['haszip'], $name);
            // The manager's own section follows the summary, under its heading.
            $this->assertNotSame('', $data['personal']['heading'], $name);
            $this->assertFalse($data['teacher'], $name);
        }
    }

    /**
     * On a course page the manager's numbers count that course and "download all" asks for the
     * course's ZIP, never the site's.
     */
    public function test_manager_on_course_page_downloads_that_course(): void {
        $this->create_site(true);
        $this->setUser($this->manager);

        $ona = $this->export($this->course_page($this->coursea));
        $this->assertSame(block_content::PLACEMENT_COURSE, $ona['placement']);
        $this->assertSame(1, $this->stat($ona['manager'], 'total'));
        $this->assertNull($this->stat($ona['manager'], 'courses'));
        $this->assertSame('course', $ona['manager']['zip']['type']);
        $this->assertSame(1, $ona['manager']['zip']['count']);
        $this->assertStringEndsWith('/blocks/download_certificates/download_course.php', $ona['manager']['zip']['url']);
        $this->assertSame(
            ['action' => 'download_course', 'courseid' => (string) $this->coursea->id],
            $this->fields($ona['manager']['zip'])
        );
        $this->assertFalse($ona['manager']['zip']['confirm']);

        // The control: the other course's page counts and targets that course.
        $onb = $this->export($this->course_page($this->courseb));
        $this->assertSame(2, $this->stat($onb['manager'], 'total'));
        $this->assertSame((string) $this->courseb->id, $this->fields($onb['manager']['zip'])['courseid']);
    }

    /**
     * "Download all" needs a certificate to download; the summary still shows.
     */
    public function test_manager_without_certificates_gets_no_zip(): void {
        $this->create_site(false);
        $this->setUser($this->manager);

        $data = $this->export($this->dashboard());
        $this->assertSame(0, $this->stat($data['manager'], 'total'));
        $this->assertFalse($data['manager']['zip']);
        $this->assertFalse($data['personal']['zip']);
        $this->assertFalse($data['haszip']);
        $this->assertNotSame('', $data['personal']['empty']);
    }

    /**
     * A teacher gets the teacher section where their scope has a course, and on a course page only
     * when it is one of theirs.
     */
    public function test_teacher_section_follows_the_course_page(): void {
        $this->create_site(false);
        $this->setUser($this->teacher);

        $dashboard = $this->export($this->dashboard());
        $this->assertStringEndsWith('/blocks/download_certificates/index_teacher.php', $dashboard['teacher']['panelurl']);
        $this->assertFalse($dashboard['manager']);
        $this->assertNotFalse($this->export($this->course_page($this->coursea))['teacher']);
        // The control: a course of somebody else's narrows the scope to no course of the teacher's.
        $onb = $this->export($this->course_page($this->courseb));
        $this->assertFalse($onb['teacher']);
        $this->assertSame('', $onb['personal']['heading']);
    }

    /**
     * A learner's dashboard counts every own certificate behind one ZIP; a course page lists only
     * that course's, one link each, with no ZIP, because the own-certificates ZIP holds every course.
     */
    public function test_learner_on_course_page_lists_that_course(): void {
        $this->create_site(true);
        $this->setUser($this->ada);

        $dashboard = $this->export($this->dashboard());
        $this->assertSame(2, $dashboard['personal']['count']);
        $this->assertSame([], $dashboard['personal']['certificates']);
        $this->assertSame(
            ['action' => 'download_my_certificates', 'userid' => (string) $this->ada->id],
            $this->fields($dashboard['personal']['zip'])
        );

        $ona = $this->export($this->course_page($this->coursea));
        $this->assertSame(1, $ona['personal']['count']);
        $this->assertFalse($ona['personal']['zip']);
        $this->assertFalse($ona['haszip']);
        $this->assertCount(1, $ona['personal']['certificates']);
        $this->assertStringContainsString('issueid=' . $this->issues['a1'], $ona['personal']['certificates'][0]['url']);
        $this->assertStringContainsString('download_precise.php', $ona['personal']['certificates'][0]['url']);
        $this->assertSame('Gold', $ona['personal']['certificates'][0]['name']);
    }

    /**
     * On their own profile, a user sees every own certificate, listed, with the own ZIP.
     */
    public function test_own_profile_lists_own_certificates(): void {
        $this->create_site(true);
        $this->setUser($this->ada);

        $data = $this->export($this->profile($this->ada));
        $this->assertSame(block_content::PLACEMENT_PROFILE, $data['placement']);
        $this->assertFalse($data['manager']);
        $this->assertFalse($data['teacher']);
        $this->assertSame(2, $data['personal']['count']);
        $this->assertCount(2, $data['personal']['certificates']);
        // Newest first; the course is named on a profile.
        $this->assertStringContainsString('issueid=' . $this->issues['b1'], $data['personal']['certificates'][0]['url']);
        $this->assertSame('Botany', $data['personal']['certificates'][0]['course']);
        $this->assertSame('download_my_certificates', $this->fields($data['personal']['zip'])['action']);
    }

    /**
     * A teacher on a student's profile sees that student's certificates of the teacher's courses
     * only, and the ZIP asks for exactly those: the user request the ZIP page re-scopes.
     */
    public function test_other_profile_within_scope(): void {
        $this->create_site(true);
        $this->setUser($this->teacher);

        $data = $this->export($this->profile($this->ada));
        $this->assertTrue($data['hascontent']);
        $this->assertSame(1, $data['personal']['count']);
        $this->assertCount(1, $data['personal']['certificates']);
        $this->assertStringContainsString('issueid=' . $this->issues['a1'], $data['personal']['certificates'][0]['url']);
        $this->assertSame('user', $data['personal']['zip']['type']);
        $this->assertSame(
            ['action' => 'download_user', 'userid' => (string) $this->ada->id],
            $this->fields($data['personal']['zip'])
        );
        $this->assertSame(1, $data['personal']['zip']['count']);
    }

    /**
     * The block shows nothing on the profile of a user the viewer's scope does not reach; a manager's
     * scope reaches everybody.
     */
    public function test_other_profile_without_scope_shows_nothing(): void {
        $this->create_site(true);

        // Bob studies only in course B, which the teacher does not teach.
        $this->setUser($this->teacher);
        $this->assertFalse($this->export($this->profile($this->bob))['hascontent']);
        $this->setUser($this->ada);
        $this->assertFalse($this->export($this->profile($this->bob))['hascontent']);

        // The control: the same profile, seen by a manager.
        $this->setUser($this->manager);
        $data = $this->export($this->profile($this->bob));
        $this->assertTrue($data['hascontent']);
        $this->assertSame(1, $data['personal']['count']);
        $this->assertFalse($data['manager']);
    }

    /**
     * A manager on the profile of a user without certificates says so; the ZIP needs certificates.
     * On their own profile a manager or a teacher gets their own certificates only, never the
     * summary, whose links are site-wide.
     */
    public function test_manager_on_profile_without_certificates(): void {
        $this->create_site(false);
        $this->setUser($this->manager);

        $own = $this->export($this->profile($this->manager));
        $this->assertFalse($own['manager']);
        $this->assertSame('', $own['personal']['heading']);
        // The control: the same manager's dashboard has the summary.
        $this->assertNotFalse($this->export($this->dashboard())['manager']);
        $this->setUser($this->teacher);
        $this->assertFalse($this->export($this->profile($this->teacher))['teacher']);
        $this->setUser($this->manager);

        $data = $this->export($this->profile($this->bob));
        $this->assertTrue($data['hascontent']);
        $this->assertSame(0, $data['personal']['count']);
        $this->assertFalse($data['personal']['zip']);
        $this->assertSame(get_string('no_certificates_profile', 'block_download_certificates'), $data['personal']['empty']);
    }
}
