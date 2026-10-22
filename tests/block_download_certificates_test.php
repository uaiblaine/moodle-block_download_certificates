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
 * Tests for the block's section choice and counts.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates;

use block_download_certificates\local\source\issue_factory;
use block_download_certificates\local\source\tool_certificate;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/download_certificates/tests/fixtures/issue_factory.php');

/**
 * The block decides its section from the viewer's scope, never a role name, and narrows the scope
 * to the page it stands on: a course page keeps that course, a profile keeps that user.
 *
 * The counting test skips, not passes, without tool_certificate.
 *
 * Metadata stays in docblocks because the plugin still supports Moodle 4.5, whose moodle-cs cannot
 * read attributes and warns per method.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates
 */
final class block_download_certificates_test extends \advanced_testcase {
    /**
     * Reset the database after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * The block's text for the current user on a page.
     *
     * @param \moodle_page $page The page the block stands on.
     * @return string
     */
    private function render(\moodle_page $page): string {
        $record = $this->getDataGenerator()->create_block('download_certificates', [
            'parentcontextid' => $page->context->id,
            'pagetypepattern' => '*',
        ]);
        $block = block_instance('download_certificates', $record, $page);
        return (string) $block->get_content()->text;
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
     * The dashboard.
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
     * A manager gets the manager section, a teacher the teacher section, anybody else the personal
     * one; on a course page the teacher section needs that course to be one of the teacher's.
     */
    public function test_section_follows_the_scope_and_the_course_page(): void {
        $gen = $this->getDataGenerator();
        $coursea = $gen->create_course();
        $courseb = $gen->create_course();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $coursea->id, 'teacher');
        $gen->enrol_user($teacher->id, $courseb->id, 'student');
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $coursea->id, 'student');
        $manager = $gen->create_user();
        $gen->role_assign('manager', $manager->id);
        $managerlink = '/blocks/download_certificates/index.php';
        $teacherlink = '/blocks/download_certificates/index_teacher.php';
        $mine = get_string('my_certificates_count', 'block_download_certificates');

        $this->setUser($manager);
        $this->assertStringContainsString($managerlink, $this->render($this->course_page($coursea)));

        $this->setUser($teacher);
        $this->assertStringContainsString($teacherlink, $this->render($this->dashboard()));
        $this->assertStringContainsString($teacherlink, $this->render($this->course_page($coursea)));
        $onb = $this->render($this->course_page($courseb));
        $this->assertStringNotContainsString($teacherlink, $onb);
        $this->assertStringContainsString($mine, $onb);

        $this->setUser($learner);
        $own = $this->render($this->dashboard());
        $this->assertStringNotContainsString($teacherlink, $own);
        $this->assertStringNotContainsString($managerlink, $own);
        $this->assertStringContainsString($mine, $own);
    }

    /**
     * For a manager, the block's first number counts the site, the course of a course page, or the
     * profile's user (the profile section replaces the manager's on a profile).
     */
    public function test_counts_are_narrowed_to_the_page(): void {
        if (!(new tool_certificate())->is_available()) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
        $gen = $this->getDataGenerator();
        $coursea = $gen->create_course();
        $courseb = $gen->create_course();
        $ada = $gen->create_user();
        $bob = $gen->create_user();
        $template = issue_factory::tool_certificate_template('Gold')->get_id();
        issue_factory::tool_certificate_issue($template, (int) $ada->id, (int) $coursea->id, 1000, 'A1');
        issue_factory::tool_certificate_issue($template, (int) $ada->id, (int) $courseb->id, 1001, 'B1');
        issue_factory::tool_certificate_issue($template, (int) $bob->id, (int) $courseb->id, 1002, 'B2');
        $manager = $gen->create_user();
        $gen->role_assign('manager', $manager->id);
        $this->setUser($manager);
        // The first number of the block: the manager's total, or the profile section's count.
        $total = static function (string $html): string {
            return preg_match('~block-download-certificates-number">(\d+)<~', $html, $m) ? $m[1] : '';
        };

        $this->assertSame('3', $total($this->render($this->dashboard())));
        $this->assertSame('1', $total($this->render($this->course_page($coursea))));
        $this->assertSame('2', $total($this->render($this->course_page($courseb))));

        $profile = new \moodle_page();
        $profile->set_context(\context_user::instance($bob->id));
        $profile->set_pagetype('user-profile');
        $profile->set_url('/user/profile.php', ['id' => $bob->id]);
        $this->assertSame('1', $total($this->render($profile)));
    }
}
