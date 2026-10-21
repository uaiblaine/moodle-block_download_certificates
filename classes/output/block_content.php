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
 * The block's content.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\output;

use block_download_certificates\local\filter;
use block_download_certificates\local\repository;
use block_download_certificates\local\scope;

/**
 * What the block shows, decided by the viewer's scope narrowed to the page it stands on.
 *
 * - The dashboard, the front page and any other page: the site-wide summary. A manager gets the
 *   numbers, the panel link and "download all"; a user with courses of their own the teacher
 *   count and panel link; everybody their own certificates.
 * - A course page: that course only. The manager's numbers count it and "download all" asks for
 *   the course's ZIP; the user's own certificates of that course are listed one by one, because
 *   the own-certificates ZIP would hold every course.
 * - A user profile: that user's certificates within the viewer's scope, listed, with a ZIP of
 *   exactly those; nothing at all when the scope does not reach that user.
 *
 * Every number and list comes from the stage-1 scope, so what the block counts is what the ZIP
 * pages, which re-derive the scope, send.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_content implements \renderable, \templatable {
    /** @var string The site-wide summary: dashboard, front page and any other page. */
    public const PLACEMENT_SITE = 'site';

    /** @var string A course page. */
    public const PLACEMENT_COURSE = 'course';

    /** @var string A user profile. */
    public const PLACEMENT_PROFILE = 'profile';

    /** @var int Certificates listed one by one on a course page or a profile. */
    public const LIST_LIMIT = 5;

    /** @var scope The viewer's scope, narrowed to the page. */
    private scope $scope;

    /** @var string One of the PLACEMENT_* constants. */
    private string $placement;

    /** @var repository The certificate repository. */
    private repository $repository;

    /**
     * Constructor; use {@see self::for_page()} for a page.
     *
     * @param scope $scope The viewer's scope, narrowed to the page.
     * @param string $placement One of the PLACEMENT_* constants.
     * @param repository $repository The certificate repository.
     */
    public function __construct(scope $scope, string $placement, repository $repository) {
        $this->scope = $scope;
        $this->placement = $placement;
        $this->repository = $repository;
    }

    /**
     * The content of the block on a page, for a viewer.
     *
     * @param \moodle_page $page The page the block stands on.
     * @param int $userid The viewer.
     * @param repository|null $repository The repository to read from; null for the available sources.
     * @return self
     */
    public static function for_page(\moodle_page $page, int $userid, ?repository $repository = null): self {
        [$placement, $courseid, $profileuserid] = self::placement_of($page);
        return new self(
            scope::for_user($userid, $courseid, $profileuserid),
            $placement,
            $repository ?? new repository()
        );
    }

    /**
     * Where a page puts the block, and the course and certificate owner it narrows the scope to.
     *
     * The dashboard stands in the viewer's user context too: only a `user-*` page type is a profile.
     *
     * @param \moodle_page $page The page.
     * @return array [string $placement, int|null $courseid, int|null $profileuserid]
     */
    public static function placement_of(\moodle_page $page): array {
        $courseid = null;
        if (!empty($page->course->id) && (int) $page->course->id !== (int) SITEID) {
            $courseid = (int) $page->course->id;
        }
        $profileuserid = null;
        if ($page->context->contextlevel == CONTEXT_USER && str_starts_with($page->pagetype, 'user-')) {
            $profileuserid = (int) $page->context->instanceid;
        }

        if ($profileuserid !== null) {
            $placement = self::PLACEMENT_PROFILE;
        } else if ($courseid !== null) {
            $placement = self::PLACEMENT_COURSE;
        } else {
            $placement = self::PLACEMENT_SITE;
        }
        return [$placement, $courseid, $profileuserid];
    }

    /**
     * The context of `block_download_certificates/block_content`.
     *
     * @param \renderer_base $output The renderer.
     * @return array placement, error, manager, teacher, personal (each a section or false),
     *         hascontent (false: the block shows nothing), haszip (whether a ZIP form is present).
     */
    public function export_for_template(\renderer_base $output): array {
        $data = [
            'placement' => $this->placement,
            'error' => false,
            'manager' => false,
            'teacher' => false,
            'personal' => false,
        ];

        $profileuserid = $this->scope->get_profile_user_id();
        if ($this->placement === self::PLACEMENT_PROFILE) {
            // A profile is about its user alone, a manager's or a teacher's own included: their
            // summaries would count the narrowed scope under site-wide links.
            if ($profileuserid === $this->scope->get_user_id()) {
                $data['personal'] = $this->own_section(false);
            } else {
                $data['personal'] = $this->profile_section((int) $profileuserid);
            }
        } else {
            if ($this->scope->is_manager()) {
                $data['manager'] = $this->manager_section();
            } else if ($this->scope->get_course_ids()) {
                $data['teacher'] = $this->teacher_section();
            }
            $data['personal'] = $this->own_section($data['manager'] || $data['teacher']);
        }

        $data['hascontent'] = $data['manager'] || $data['teacher'] || $data['personal'];
        $data['haszip'] = !empty($data['manager']['zip']) || !empty($data['personal']['zip']);
        return $data;
    }

    /**
     * The manager's summary: numbers, the panel link and "download all", which on a course page asks
     * for that course's ZIP.
     *
     * @return array heading, stats (value, label), manageurl, zip (a {@see zip_form} context or false).
     */
    private function manager_section(): array {
        $total = $this->repository->count($this->scope, new filter());
        $recent = $this->repository->count($this->scope, new filter(datefrom: time() - 7 * DAYSECS));
        $courseid = $this->scope->get_course_id();

        $stats = [['value' => $total, 'label' => get_string('total', 'block_download_certificates')]];
        if ($courseid === null) {
            $stats[] = [
                'value' => count($this->repository->get_courses($this->scope)),
                'label' => get_string('courses', 'block_download_certificates'),
            ];
        }
        $stats[] = ['value' => $recent, 'label' => get_string('recent_7days', 'block_download_certificates')];

        $zip = false;
        if ($total > 0 && $courseid === null) {
            $zip = zip_form::export(
                'download.php',
                ['action' => 'download_all'],
                'all',
                $total,
                get_string('download_all_quick', 'block_download_certificates'),
                'btn-success btn-sm w-100',
                get_string('confirm_download_all', 'block_download_certificates')
            );
        } else if ($total > 0) {
            $zip = zip_form::export(
                'download_course.php',
                ['action' => 'download_course', 'courseid' => $courseid],
                'course',
                $total,
                get_string('download_course_certificates', 'block_download_certificates'),
                'btn-success btn-sm w-100'
            );
        }

        return [
            'heading' => get_string(
                $courseid === null ? 'certificate_summary' : 'course_certificate_summary',
                'block_download_certificates'
            ),
            'stats' => $stats,
            'manageurl' => (new \moodle_url('/blocks/download_certificates/index.php'))->out(false),
            'zip' => $zip,
        ];
    }

    /**
     * The certificates a user with courses of their own may download, and the link to their panel.
     *
     * @return array count, panelurl.
     */
    private function teacher_section(): array {
        return [
            'count' => $this->repository->count($this->scope, new filter()),
            'panelurl' => (new \moodle_url('/blocks/download_certificates/index_teacher.php'))->out(false),
        ];
    }

    /**
     * The viewer's own certificates: a count and the ZIP on site pages, the list on a course page or
     * the viewer's own profile.
     *
     * @param bool $afterothers Whether another section comes first, which gives this one a heading.
     * @return array As {@see self::personal_context()}.
     */
    private function own_section(bool $afterothers): array {
        $userid = $this->scope->get_user_id();
        $filter = new filter(userid: $userid);
        $count = $this->repository->count($this->scope, $filter);
        $oncourse = $this->placement === self::PLACEMENT_COURSE;

        $zip = false;
        if ($count > 0 && !$oncourse) {
            $zip = zip_form::export(
                'download_user.php',
                ['action' => 'download_my_certificates', 'userid' => $userid],
                'user',
                $count,
                get_string('download_my_certificates', 'block_download_certificates'),
                ($afterothers ? 'btn-secondary' : 'btn-primary') . ' btn-sm w-100'
            );
        }

        return $this->personal_context(
            $afterothers ? get_string('my_certificates', 'block_download_certificates') : '',
            $count,
            get_string($oncourse ? 'my_course_certificates_count' : 'my_certificates_count', 'block_download_certificates'),
            $this->placement === self::PLACEMENT_SITE ? [] : $this->list_certificates($filter, !$oncourse),
            $zip,
            get_string($oncourse ? 'no_certificates_course' : 'no_certificates_user', 'block_download_certificates')
        );
    }

    /**
     * Another user's certificates on their profile, within the viewer's scope.
     *
     * @param int $profileuserid The profile's user.
     * @return array|false As {@see self::personal_context()}; false when the viewer's scope reaches no
     *         certificate of that user and the viewer is not a manager, whose scope reaches everybody.
     */
    private function profile_section(int $profileuserid): array|false {
        $count = $this->repository->count($this->scope, new filter());
        if ($count === 0 && !$this->scope->is_manager()) {
            return false;
        }

        $zip = false;
        if ($count > 0) {
            // The ZIP page re-derives the scope without the profile narrowing; the user filter makes it
            // the same set this section counts.
            $zip = zip_form::export(
                'download_user.php',
                ['action' => 'download_user', 'userid' => $profileuserid],
                'user',
                $count,
                get_string('download_user_certificates', 'block_download_certificates'),
                'btn-primary btn-sm w-100'
            );
        }

        return $this->personal_context(
            '',
            $count,
            get_string('profile_certificates_count', 'block_download_certificates'),
            $this->list_certificates(new filter(), true),
            $zip,
            get_string('no_certificates_profile', 'block_download_certificates')
        );
    }

    /**
     * The context of `block_download_certificates/block_personal`.
     *
     * @param string $heading The section heading, or '' for none.
     * @param int $count The certificates of the section.
     * @param string $countlabel What the count counts.
     * @param array $certificates The listed certificates, as {@see self::list_certificates()}.
     * @param array|false $zip A {@see zip_form} context, or false.
     * @param string $empty The message shown when the count is 0.
     * @return array heading, count, countlabel, hascertificates, certificates, more, zip, empty.
     */
    private function personal_context(
        string $heading,
        int $count,
        string $countlabel,
        array $certificates,
        array|false $zip,
        string $empty
    ): array {
        $more = '';
        if ($certificates && $count > count($certificates)) {
            $more = get_string('more_certificates', 'block_download_certificates', $count - count($certificates));
        }
        return [
            'heading' => $heading,
            'count' => $count,
            'countlabel' => $countlabel,
            'hascertificates' => !empty($certificates),
            'certificates' => $certificates,
            'more' => $more,
            'zip' => $zip,
            'empty' => $count === 0 ? $empty : '',
        ];
    }

    /**
     * The newest certificates in scope, each with its single-download link.
     *
     * Names are formatted without escaping: the template escapes them once.
     *
     * @param filter $filter What to keep within the scope.
     * @param bool $withcourse Whether to name each certificate's course.
     * @return array List of name, course, date, url, downloadlabel.
     */
    private function list_certificates(filter $filter, bool $withcourse): array {
        $system = \context_system::instance();
        $unknown = get_string('unknown', 'block_download_certificates');
        $rows = $this->repository->search($this->scope, $filter, 'timecreated', 'DESC', 0, self::LIST_LIMIT);

        $list = [];
        foreach ($rows as $row) {
            $context = $row->courseid ? \context_course::instance($row->courseid, IGNORE_MISSING) : false;
            $options = ['context' => $context ?: $system, 'escape' => false];
            $name = format_string($row->templatename !== '' ? $row->templatename : $unknown, true, $options);
            $course = '';
            if ($withcourse && $row->coursename !== '') {
                $course = format_string($row->coursename, true, $options);
            }
            $url = new \moodle_url('/blocks/download_certificates/download_precise.php', [
                'type' => $row->type,
                'issueid' => $row->issueid,
            ]);
            $list[] = [
                'name' => $name,
                'course' => $course,
                'date' => userdate($row->timecreated, get_string('strftimedate', 'core_langconfig')),
                'url' => $url->out(false),
                'downloadlabel' => get_string('download_named', 'block_download_certificates', $name),
            ];
        }
        return $list;
    }
}
