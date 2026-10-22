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

use block_download_certificates\local\download;
use block_download_certificates\local\filter;
use block_download_certificates\local\repository;
use block_download_certificates\local\request;
use block_download_certificates\local\scope;
use block_download_certificates\local\source\issue_factory;
use block_download_certificates\local\source\tool_certificate;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/download_certificates/tests/fixtures/issue_factory.php');

/**
 * Every template renders through render_from_template() with the context the plugin really builds
 * for it, and the markup holds together: ids that labels and ARIA attributes point at exist, every
 * field has an accessible name, nothing carries an inline style or handler, and the values the
 * JavaScript reads are where it reads them. A key the template expects and the context lacks renders
 * as nothing, silently; the assertions on the rendered values are what catches it.
 *
 * Partials are rendered alone too, with the context their parent hands them, which is what the
 * Mustache lint does with each one's example.
 *
 * Metadata stays in docblocks because the plugin still supports Moodle 4.5, whose moodle-cs cannot
 * read attributes and warns per method.
 *
 * @package   block_download_certificates
 * @category  test
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \block_download_certificates\output\panel_page
 * @covers    \block_download_certificates\output\block_content
 * @covers    \block_download_certificates
 */
final class templates_test extends \advanced_testcase {
    /** @var string The session key the manager page was rendered with. */
    private string $mainsesskey = '';

    /** @var \stdClass Course A. */
    private \stdClass $coursea;

    /** @var \stdClass A student of course A with a certificate. */
    private \stdClass $ada;

    /** @var \stdClass The teacher of course A. */
    private \stdClass $teacher;

    /** @var \stdClass A site manager. */
    private \stdClass $manager;

    /** @var bool Whether the certificates exist, which needs tool_certificate. */
    private bool $withcertificates = false;

    /**
     * Reset the database after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A course with a teacher and a student; a cohort holding the student; with tool_certificate,
     * the student's certificate, so that every loop of the page templates has a row.
     */
    private function create_site(): void {
        $gen = $this->getDataGenerator();
        $this->coursea = $gen->create_course(['fullname' => 'Algebra & Geometry']);
        $this->ada = $gen->create_user(['firstname' => 'Ada', 'lastname' => "O'Brien"]);
        $this->teacher = $gen->create_user();
        $this->manager = $gen->create_user();
        $gen->enrol_user($this->ada->id, $this->coursea->id, 'student');
        $gen->enrol_user($this->teacher->id, $this->coursea->id, 'teacher');
        $gen->role_assign('manager', $this->manager->id);
        $cohort = $gen->create_cohort(['name' => 'Class of 2026']);
        cohort_add_member($cohort->id, $this->ada->id);

        $this->withcertificates = (new tool_certificate())->is_available();
        if ($this->withcertificates) {
            $template = issue_factory::tool_certificate_template('Gold')->get_id();
            issue_factory::tool_certificate_issue($template, (int) $this->ada->id, (int) $this->coursea->id, time(), 'A1');
        }
    }

    /**
     * Render a template.
     *
     * @param string $name The template name, without the component.
     * @param array $context The context.
     * @return string
     */
    private function render(string $name, array $context): string {
        global $OUTPUT;

        return $OUTPUT->render_from_template('block_download_certificates/' . $name, $context);
    }

    /**
     * Every template, rendered with its real context; the keys are the template names.
     *
     * @return array Template name => HTML.
     */
    private function render_everything(): array {
        global $CFG, $OUTPUT, $USER;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $this->create_site();
        $repository = new repository();
        $html = [];

        $this->setUser($this->manager);
        $main = panel_page::manager(scope::for_user((int) $USER->id), $repository);
        $html['main_page'] = $this->render('main_page', $main);
        // Switching user below gives the session a new key: keep the one the page was built with.
        $this->mainsesskey = sesskey();
        $html['page_header'] = $this->render('page_header', $main);
        $html['filter_bar'] = $this->render('filter_bar', $main);
        $html['table_card'] = $this->render('table_card', $main);
        $html['search_bar'] = $this->render('search_bar', $main);
        $html['zip_form'] = $this->render('zip_form', $main['downloadall'] ?: zip_form::export(
            'download.php',
            ['action' => 'download_all'],
            'all',
            0,
            get_string('download_all_certificates', 'block_download_certificates'),
            'btn-primary',
            get_string('confirm_download_all', 'block_download_certificates')
        ));

        $page = new \moodle_page();
        $page->set_context(\context_user::instance($USER->id));
        $page->set_pagetype('my-index');
        $block = block_content::for_page($page, (int) $USER->id, $repository)
            ->export_for_template($page->get_renderer('core'));
        $html['block_content'] = $this->render('block_content', $block);
        $html['block_manager'] = $this->render('block_manager', $block['manager']);

        // A list and a ZIP: the student's own profile.
        $this->setUser($this->ada);
        $page = new \moodle_page();
        $page->set_context(\context_user::instance($USER->id));
        $page->set_pagetype('user-profile');
        $profile = block_content::for_page($page, (int) $USER->id, $repository)
            ->export_for_template($page->get_renderer('core'));
        $html['block_personal'] = $this->render('block_personal', $profile['personal']);
        $this->setUser($this->manager);

        // The certificates table, as certificate_table.js builds its context from the web service.
        $result = request::search_page(
            scope::for_user((int) $USER->id),
            new filter(),
            0,
            10,
            'timecreated',
            'DESC',
            $repository
        );
        $columns = [];
        foreach (['firstname', 'email', 'coursename', 'templatename', 'type', 'timecreated'] as $key) {
            $columns[$key] = ['ariasort' => $key === 'timecreated' ? 'descending' : 'none', 'icon' => 'fa-sort'];
        }
        $html['certificate_rows'] = $this->render('certificate_rows', [
            'hasrows' => !empty($result['rows']),
            'emptymessage' => get_string('no_certificates_found', 'block_download_certificates'),
            'columns' => $columns,
            'rows' => $result['rows'],
            'pagination' => [
                'haspages' => true,
                'previous' => ['page' => 0, 'disabled' => true],
                'next' => ['page' => 1, 'disabled' => false],
                'pages' => [['page' => 0, 'label' => 1, 'active' => true, 'ellipsis' => false]],
            ],
        ]);

        // The progress, as async_download.js builds its context for a banner.
        $html['task_progress'] = $this->render('task_progress', [
            'taskid' => 12,
            'banner' => true,
            'showhint' => false,
            'message' => get_string('async_processing', 'block_download_certificates'),
            'percent' => 40,
            'progress' => 20,
            'total' => 50,
            'ready' => false,
        ]);

        $this->setUser($this->teacher);
        $teacher = panel_page::teacher(scope::for_user((int) $USER->id), $repository);
        $html['teacher_page'] = $this->render('teacher_page', $teacher);
        $page = new \moodle_page();
        $page->set_course($this->coursea);
        $page->set_pagetype('course-view-topics');
        $teacherblock = block_content::for_page($page, (int) $USER->id, $repository)
            ->export_for_template($page->get_renderer('core'));
        $html['block_teacher'] = $this->render('block_teacher', $teacherblock['teacher']);

        $html['@teacher_block'] = $this->render('block_content', $teacherblock);
        $html['@teacher_page'] = $html['teacher_page'];
        return $html;
    }

    /**
     * Parse rendered HTML.
     *
     * @param string $html The markup.
     * @return \DOMXPath
     */
    private function dom(string $html): \DOMXPath {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="test-root">' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new \DOMXPath($dom);
    }

    /**
     * Every template file is rendered here, so a template added later cannot skip this test.
     */
    public function test_every_template_is_rendered(): void {
        $rendered = array_filter(array_keys($this->render_everything()), static fn(string $name): bool => $name[0] !== '@');
        $files = array_map(
            static fn(string $path): string => basename($path, '.mustache'),
            glob(dirname(__DIR__, 2) . '/templates/*.mustache') ?: []
        );
        sort($rendered);
        sort($files);
        $this->assertCount(13, $files);
        $this->assertSame($files, $rendered);
    }

    /**
     * The markup holds together: referenced ids exist, fields are named, no inline style or handler,
     * no Mustache left over.
     *
     * The control: the same checks fail on a label pointing at a missing id with an unnamed select,
     * and on an inline style with a handler.
     */
    public function test_rendered_markup_holds_together(): void {
        foreach ($this->render_everything() as $name => $html) {
            $this->assertNotSame('', trim($html), $name);
            $this->assertSame([], $this->markup_problems($html), $name);
        }
        $this->assertNotSame([], $this->markup_problems('<label for="nothing">X</label><select name="a"></select>'));
        $this->assertNotSame([], $this->markup_problems('<p style="color: red" onclick="go()">X</p>'));
    }

    /**
     * What is wrong with a piece of markup.
     *
     * @param string $html The markup.
     * @return string[]
     */
    private function markup_problems(string $html): array {
        $xpath = $this->dom($html);
        $problems = [];
        if (str_contains($html, '{{') || str_contains($html, '[[')) {
            $problems[] = 'unrendered Mustache or a missing string';
        }
        foreach ($xpath->query('//*[@style or @onclick or @onchange or @onsubmit]') as $node) {
            $problems[] = 'inline style or handler on ' . $node->nodeName;
        }
        foreach (['for', 'aria-controls', 'aria-labelledby', 'aria-describedby'] as $attribute) {
            foreach ($xpath->query('//*[@' . $attribute . ']') as $node) {
                foreach (preg_split('/\s+/', trim($node->getAttribute($attribute))) as $id) {
                    if (!$xpath->query('//*[@id="' . $id . '"]')->length) {
                        $problems[] = $attribute . '="' . $id . '" points at nothing';
                    }
                }
            }
        }
        $fields = $xpath->query('//select | //textarea | //input[not(@type="hidden")]');
        foreach ($fields as $node) {
            $id = $node->getAttribute('id');
            $named = $node->hasAttribute('aria-label') || $node->hasAttribute('aria-labelledby')
                || ($id !== '' && $xpath->query('//label[@for="' . $id . '"]')->length);
            if (!$named) {
                $problems[] = $node->nodeName . ' ' . $id . ' has no label';
            }
        }
        foreach ($xpath->query('//button | //a') as $node) {
            if (trim($node->textContent) === '' && !$node->hasAttribute('aria-label')) {
                $problems[] = $node->nodeName . ' without an accessible name';
            }
        }
        foreach ($xpath->query('//i[contains(concat(" ", @class, " "), " fa ")]') as $node) {
            if ($node->getAttribute('aria-hidden') !== 'true') {
                $problems[] = 'icon ' . $node->getAttribute('class') . ' not hidden from assistive technology';
            }
        }
        return $problems;
    }

    /**
     * The manager page carries what its modules read: the forms' targets and marks, the tabs and
     * their panels, the menus by id with their counts, the confirmation of "download all".
     */
    public function test_main_page_carries_what_the_modules_read(): void {
        $html = $this->render_everything()['main_page'];
        $xpath = $this->dom($html);

        $this->assertSame(1, $xpath->query('//div[contains(@class, "block-download-certificates-page")]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="cert-table-container"][@aria-live="polite"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="cert-result-count"]')->length);
        $this->assertSame(1, $xpath->query('//select[@id="cert-perpage"]/option[@selected][@value="50"]')->length);
        $this->assertSame(1, $xpath->query('//input[@id="cert-search-input"]')->length);
        $this->assertSame(1, $xpath->query('//button[@id="cert-search-clear"][@aria-label]')->length);
        if (!$this->withcertificates) {
            // Without certificates the page has no tabs and no "download all"; the table says so.
            $this->assertSame(0, $xpath->query('//*[@role="tablist"]')->length);
            $this->assertStringContainsString(get_string('no_certificates_found', 'block_download_certificates'), $html);
            return;
        }

        $form = $xpath->query('//form[@data-download-type="all"]')->item(0);
        $this->assertNotNull($form);
        $this->assertStringEndsWith('/blocks/download_certificates/download.php', $form->getAttribute('action'));
        $this->assertSame('1', $form->getAttribute('data-cert-count'));
        $this->assertSame((string) download::SYNC_LIMIT, $form->getAttribute('data-async-threshold'));
        $this->assertSame(get_string('confirm_download_all', 'block_download_certificates'), $form->getAttribute('data-confirm'));
        $this->assertSame($this->mainsesskey, $xpath->query('.//input[@name="sesskey"]', $form)->item(0)->getAttribute('value'));
        $this->assertSame('download_all', $xpath->query('.//input[@name="action"]', $form)->item(0)->getAttribute('value'));

        // Tabs: the first selected, each controlling its panel, the panel labelled by its tab.
        $tabs = $xpath->query('//*[@role="tablist"]//*[@role="tab"]');
        $this->assertSame(4, $tabs->length);
        $this->assertSame('true', $tabs->item(0)->getAttribute('aria-selected'));
        $this->assertSame('false', $tabs->item(1)->getAttribute('aria-selected'));
        foreach ($tabs as $tab) {
            $this->assertSame('tab', $tab->getAttribute('data-bs-toggle'));
            $this->assertSame('tab', $tab->getAttribute('data-toggle'));
            $panel = $xpath->query('//*[@id="' . $tab->getAttribute('aria-controls') . '"]')->item(0);
            $this->assertSame('tabpanel', $panel->getAttribute('role'));
            $this->assertSame($tab->getAttribute('id'), $panel->getAttribute('aria-labelledby'));
        }

        // The menus, by the ids the modules use, each option carrying its count; names escaped once.
        $course = $xpath->query('//select[@id="course_select"]/option[@value="' . $this->coursea->id . '"]')->item(0);
        $this->assertSame('1', $course->getAttribute('data-count'));
        $this->assertStringContainsString('Algebra & Geometry', $course->textContent);
        // Escaped once, by the template.
        $this->assertStringContainsString('Algebra &amp; Geometry', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertSame(1, $xpath->query('//select[@id="cohort_select"]/option[@data-count="1"]')->length);
        $this->assertSame(1, $xpath->query('//select[@id="user_select"]/option[@value="' . $this->ada->id . '"]')->length);
        $this->assertSame(0, $xpath->query('//select[@id="group_select"]')->length);
        $this->assertSame(1, $xpath->query('//form[@data-download-type="range"]//input[@id="start_date"][@required]')->length);
    }

    /**
     * The teacher page: no "download all", no date range or cohort, the course form with its hidden
     * group menu.
     */
    public function test_teacher_page_carries_the_group_menu(): void {
        $html = $this->render_everything()['@teacher_page'];
        $xpath = $this->dom($html);

        $this->assertSame(0, $xpath->query('//form[@data-download-type="all"]')->length);
        $this->assertSame(0, $xpath->query('//form[@data-download-type="range"]')->length);
        $this->assertSame(0, $xpath->query('//select[@id="cohort_select"]')->length);
        $this->assertStringContainsString(get_string('teacher_panel', 'block_download_certificates'), $html);
        if (!$this->withcertificates) {
            $this->assertStringContainsString(get_string('teacher_no_certificates', 'block_download_certificates'), $html);
            return;
        }
        $wrapper = $xpath->query('//*[@id="group-select-wrapper"]')->item(0);
        $this->assertNotNull($wrapper);
        $this->assertTrue($wrapper->hasAttribute('hidden'));
        $form = $xpath->query('//form[@data-download-type="course"]')->item(0);
        $this->assertStringEndsWith('/blocks/download_certificates/download_course.php', $form->getAttribute('action'));
        $this->assertSame(1, $xpath->query('.//select[@id="group_select"][@name="groupid"]', $form)->length);
        $this->assertSame('true', $xpath->query('//*[@role="tab"]')->item(0)->getAttribute('aria-selected'));
    }

    /**
     * The block renders through the block itself, as a page shows it, with its sections' links and
     * forms.
     */
    public function test_block_renders_through_the_block(): void {
        $this->create_site();
        $this->setUser($this->manager);
        $page = new \moodle_page();
        $page->set_course($this->coursea);
        $page->set_pagetype('course-view-topics');
        $page->set_url('/course/view.php', ['id' => $this->coursea->id]);
        $record = $this->getDataGenerator()->create_block('download_certificates', [
            'parentcontextid' => $page->context->id,
            'pagetypepattern' => '*',
        ]);
        $html = (string) block_instance('download_certificates', $record, $page)->get_content()->text;
        $xpath = $this->dom($html);

        $this->assertSame([], $this->markup_problems($html));
        $this->assertSame(1, $xpath->query('//div[contains(@class, "block-download-certificates-content")]')->length);
        $this->assertSame(1, $xpath->query('//a[contains(@href, "/blocks/download_certificates/index.php")]')->length);
        $this->assertSame(0, $xpath->query('//form[@data-download-type="all"]')->length);
        if ($this->withcertificates) {
            $form = $xpath->query('//form[@data-download-type="course"]')->item(0);
            $this->assertNotNull($form);
            $this->assertFalse($form->hasAttribute('data-confirm'));
            $this->assertSame(
                (string) $this->coursea->id,
                $xpath->query('.//input[@name="courseid"]', $form)->item(0)->getAttribute('value')
            );
        }
    }

    /**
     * A template, course and cohort name with `&` and `"` reach the block's list, its link label,
     * the manager page's menus and the table rows escaped exactly once: the markup holds the entity
     * the character needs, never an entity of an entity, and the parsed text is the name as typed.
     *
     * The control: the plain-named certificate of the same user renders unchanged beside it.
     */
    public function test_names_are_escaped_exactly_once(): void {
        global $CFG, $OUTPUT, $USER;
        require_once($CFG->dirroot . '/cohort/lib.php');

        if (!(new tool_certificate())->is_available()) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
        $tricky = 'Tom & Jerry "Gold"';
        // Format_string() strips anything that reads as a tag, so the course carries the characters
        // it keeps: an ampersand and double quotes.
        $course = 'Algebra & "Co"';
        $cohortname = 'Cohort "Tom" & Co';

        $gen = $this->getDataGenerator();
        $coursea = $gen->create_course(['fullname' => $course]);
        $courseb = $gen->create_course(['fullname' => 'Botany']);
        $ada = $gen->create_user();
        $manager = $gen->create_user();
        $gen->enrol_user($ada->id, $coursea->id, 'student');
        $gen->enrol_user($ada->id, $courseb->id, 'student');
        $gen->role_assign('manager', $manager->id);
        $cohort = $gen->create_cohort(['name' => $cohortname]);
        cohort_add_member($cohort->id, $ada->id);

        $now = time();
        $trickyid = issue_factory::tool_certificate_template($tricky)->get_id();
        $plainid = issue_factory::tool_certificate_template('Silver')->get_id();
        issue_factory::tool_certificate_issue($trickyid, (int) $ada->id, (int) $coursea->id, $now, 'T1');
        issue_factory::tool_certificate_issue($plainid, (int) $ada->id, (int) $courseb->id, $now - 100, 'S1');
        $repository = new repository();

        // The block's list, on the learner's own profile: the link, its label, the course line.
        $this->setUser($ada);
        $page = new \moodle_page();
        $page->set_context(\context_user::instance($USER->id));
        $page->set_pagetype('user-profile');
        $block = block_content::for_page($page, (int) $USER->id, $repository)
            ->export_for_template($page->get_renderer('core'));
        $personal = $this->render('block_personal', $block['personal']);
        $xpath = $this->dom($personal);
        $link = $xpath->query('//li[.//span[contains(., "Algebra")]]/a')->item(0);
        $this->assertNotNull($link);
        $this->assertSame('Download ' . $tricky, $link->getAttribute('aria-label'));
        $this->assertSame($tricky, trim($link->textContent));
        $this->assertSame($course, trim($xpath->query('//li[.//span[contains(., "Algebra")]]/span[1]')->item(0)->textContent));
        $this->assert_escaped_once($personal);
        $this->assertStringContainsString('aria-label="Download Tom &amp; Jerry &quot;Gold&quot;"', $personal);

        // The control: the plain certificate of the same list is untouched.
        $silver = $xpath->query('//li[.//span[contains(., "Botany")]]/a')->item(0);
        $this->assertSame('Download Silver', $silver->getAttribute('aria-label'));
        $this->assertSame('Silver', trim($silver->textContent));

        // The manager page's menus.
        $this->setUser($manager);
        $main = panel_page::manager(scope::for_user((int) $USER->id), $repository);
        $page = $this->render('main_page', $main);
        $xpath = $this->dom($page);
        $courseoption = $xpath->query('//select[@id="course_select"]/option[@value="' . $coursea->id . '"]')->item(0);
        $this->assertStringStartsWith($course, trim($courseoption->textContent));
        $cohortoption = $xpath->query('//select[@id="cohort_select"]/option[@value="' . $cohort->id . '"]')->item(0);
        $this->assertStringStartsWith($cohortname, trim($cohortoption->textContent));
        $this->assertStringContainsString('Algebra &amp; &quot;Co&quot;', $page);
        $this->assertStringContainsString('Cohort &quot;Tom&quot; &amp; Co', $page);
        $this->assert_escaped_once($page);
        $botany = $xpath->query('//select[@id="course_select"]/option[@value="' . $courseb->id . '"]')->item(0);
        $this->assertStringStartsWith('Botany (', trim($botany->textContent));

        // The table rows, as the search web service hands them to certificate_table.js.
        $result = request::search_page(
            scope::for_user((int) $USER->id),
            new filter(),
            0,
            10,
            'timecreated',
            'DESC',
            $repository
        );
        $columns = [];
        foreach (['firstname', 'email', 'coursename', 'templatename', 'type', 'timecreated'] as $key) {
            $columns[$key] = ['ariasort' => 'none', 'icon' => 'fa-sort'];
        }
        $rows = $this->render('certificate_rows', [
            'hasrows' => true,
            'emptymessage' => '',
            'columns' => $columns,
            'rows' => $result['rows'],
            'pagination' => ['haspages' => false],
        ]);
        $cells = array_map(
            static fn(\DOMNode $node): string => trim($node->textContent),
            iterator_to_array($this->dom($rows)->query('//td'))
        );
        $this->assertContains($tricky, $cells);
        $this->assertContains($course, $cells);
        $this->assertContains('Silver', $cells);
        $this->assertStringContainsString('Tom &amp; Jerry &quot;Gold&quot;', $rows);
        $this->assert_escaped_once($rows);
    }

    /**
     * What escaping the names of {@see self::test_names_are_escaped_exactly_once()} twice, or not at
     * all, would leave in the markup.
     *
     * @param string $html The markup.
     */
    private function assert_escaped_once(string $html): void {
        foreach (['&amp;amp;', '&amp;quot;', '&amp;lt;', '&amp;gt;', '&amp;#'] as $wrong) {
            $this->assertStringNotContainsString($wrong, $html);
        }
    }

    /**
     * The block of a learner on another learner's profile renders nothing at all, so the block hides.
     */
    public function test_block_hides_on_a_profile_out_of_scope(): void {
        $this->create_site();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($other);
        $page = new \moodle_page();
        $page->set_context(\context_user::instance($this->ada->id));
        $page->set_pagetype('user-profile');
        $page->set_url('/user/profile.php', ['id' => $this->ada->id]);
        $record = $this->getDataGenerator()->create_block('download_certificates', [
            'parentcontextid' => \context_user::instance($this->ada->id)->id,
            'pagetypepattern' => '*',
        ]);
        $this->assertSame('', (string) block_instance('download_certificates', $record, $page)->get_content()->text);

        // The control: on their own profile the same user gets the block.
        $page = new \moodle_page();
        $page->set_context(\context_user::instance($other->id));
        $page->set_pagetype('user-profile');
        $page->set_url('/user/profile.php', ['id' => $other->id]);
        $this->assertStringContainsString(
            'block-download-certificates-content',
            (string) block_instance('download_certificates', $record, $page)->get_content()->text
        );
    }
}
