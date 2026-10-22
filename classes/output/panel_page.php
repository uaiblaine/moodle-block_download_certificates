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
 * The contexts of the manager and teacher pages.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\output;

use block_download_certificates\local\download;
use block_download_certificates\local\filter;
use block_download_certificates\local\repository;
use block_download_certificates\local\scope;

/**
 * Builds the contexts of `main_page` (index.php) and `teacher_page` (index_teacher.php), which
 * share the partials `page_header`, `filter_bar`, `table_card` and `search_bar`.
 *
 * The menus list what the scope reaches; the ZIP pages and web services check the same scope again,
 * so a menu is a convenience, never the guard.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class panel_page {
    /** @var int[] Rows per page the table offers. */
    private const PER_PAGE = [25, 50, 100, 200];

    /** @var int The rows per page selected at first; certificate_table.js starts from the menu's value. */
    private const DEFAULT_PER_PAGE = 50;

    /**
     * The context of the manager page: every filter tab and "download all".
     *
     * @param scope $scope The manager's scope.
     * @param repository $repository The certificate repository.
     * @return array As {@see self::page()}.
     */
    public static function manager(scope $scope, repository $repository): array {
        $total = $repository->count($scope, new filter());

        $tabs = [self::range_tab($total)];
        $courses = [];
        foreach ($repository->get_courses($scope) as $course) {
            $courses[] = ['value' => $course->id, 'text' => $course->name, 'count' => $course->total];
        }
        if ($courses) {
            $tabs[] = self::course_tab($courses, false);
        }
        $cohorts = [];
        foreach ($repository->get_cohorts($scope) as $cohort) {
            $cohorts[] = ['value' => $cohort->id, 'text' => $cohort->name, 'count' => $cohort->total];
        }
        if ($cohorts) {
            $tabs[] = self::menu_tab(
                'cohort',
                'fa-users',
                'tab_cohort',
                'cohort_download_help',
                'download_cohort.php',
                'download_cohort',
                'cohort_select',
                'cohortid',
                get_string('cohort', 'core_cohort'),
                'choose_cohort',
                'download_cohort_certificates',
                $cohorts,
                false
            );
        }
        if ($usertab = self::user_tab($repository, $scope)) {
            $tabs[] = $usertab;
        }

        $downloadall = false;
        if ($total > 0) {
            $downloadall = zip_form::export(
                'download.php',
                ['action' => 'download_all'],
                'all',
                $total,
                get_string('download_all_certificates', 'block_download_certificates'),
                'btn-primary',
                get_string('confirm_download_all', 'block_download_certificates')
            );
        }

        return self::page(
            get_string('certificate_management', 'block_download_certificates'),
            get_string('total_certificates', 'block_download_certificates'),
            $total,
            $downloadall,
            $tabs,
            get_string('no_certificates_found', 'block_download_certificates'),
            get_string('no_certificates_description', 'block_download_certificates')
        );
    }

    /**
     * The context of the teacher page: the course tab, with its group menu, and the user tab.
     *
     * @param scope $scope The teacher's scope.
     * @param repository $repository The certificate repository.
     * @return array As {@see self::page()}.
     */
    public static function teacher(scope $scope, repository $repository): array {
        $total = $repository->count($scope, new filter());

        // The course menu offers the courses a course ZIP may be requested for, not every course
        // where the teacher holds a certificate of their own.
        $teachercourses = array_flip($scope->get_course_ids());
        $courses = [];
        foreach ($repository->get_courses($scope) as $course) {
            if (isset($teachercourses[$course->id])) {
                $courses[] = ['value' => $course->id, 'text' => $course->name, 'count' => $course->total];
            }
        }
        $tabs = [];
        if ($courses) {
            $tabs[] = self::course_tab($courses, true);
        }
        if ($usertab = self::user_tab($repository, $scope)) {
            $tabs[] = $usertab;
        }

        return self::page(
            get_string('teacher_panel', 'block_download_certificates'),
            get_string('teacher_certificates', 'block_download_certificates'),
            $total,
            false,
            $tabs,
            get_string('teacher_no_certificates', 'block_download_certificates'),
            ''
        );
    }

    /**
     * The shared shape of both pages.
     *
     * The first tab is the selected one; Bootstrap moves the selection from there.
     *
     * @param string $heading The page's own heading.
     * @param string $totallabel What the total counts.
     * @param int $total The certificates in scope.
     * @param array|false $downloadall A {@see zip_form} context, or false.
     * @param array $tabs The filter tabs, as {@see self::tab()}.
     * @param string $emptymessage The table's text when the scope holds no certificate.
     * @param string $emptydescription A second line under it, or ''.
     * @return array heading, totallabel, total, downloadall, hascertificates, hastabs, tabs,
     *         emptymessage, emptydescription, perpage (value, selected).
     */
    private static function page(
        string $heading,
        string $totallabel,
        int $total,
        array|false $downloadall,
        array $tabs,
        string $emptymessage,
        string $emptydescription
    ): array {
        if ($tabs) {
            $tabs[0]['active'] = true;
        }
        $perpage = [];
        foreach (self::PER_PAGE as $value) {
            $perpage[] = ['value' => $value, 'selected' => $value === self::DEFAULT_PER_PAGE];
        }
        return [
            'heading' => $heading,
            'totallabel' => $totallabel,
            'total' => $total,
            'downloadall' => $downloadall,
            'hascertificates' => $total > 0,
            'hastabs' => $total > 0 && !empty($tabs),
            'tabs' => $tabs,
            'emptymessage' => $emptymessage,
            'emptydescription' => $emptydescription,
            'perpage' => $perpage,
        ];
    }

    /**
     * A filter tab.
     *
     * @param string $id The tab's id suffix, unique on the page.
     * @param string $icon The Font Awesome class of its decorative icon.
     * @param string $label The lang key of its title.
     * @param string $help The lang key of the line above its form.
     * @return array id, icon, label, help, active (false), range (false), menu (false).
     */
    private static function tab(string $id, string $icon, string $label, string $help): array {
        return [
            'id' => $id,
            'icon' => $icon,
            'label' => get_string($label, 'block_download_certificates'),
            'help' => get_string($help, 'block_download_certificates'),
            'active' => false,
            'range' => false,
            'menu' => false,
        ];
    }

    /**
     * The date range tab; its request holds up to every certificate, so it is marked with the total.
     *
     * @param int $total The certificates in scope.
     * @return array As {@see self::tab()}, range: url, sesskey, count, threshold, buttonlabel.
     */
    private static function range_tab(int $total): array {
        $tab = self::tab('daterange', 'fa-calendar', 'tab_date_range', 'date_range_help');
        $tab['range'] = [
            'url' => (new \moodle_url('/blocks/download_certificates/download_range.php'))->out(false),
            'sesskey' => sesskey(),
            'count' => $total,
            'threshold' => download::SYNC_LIMIT,
            'buttonlabel' => get_string('download_range', 'block_download_certificates'),
        ];
        return $tab;
    }

    /**
     * The course tab.
     *
     * @param array $options The courses, as {@see self::menu_tab()} options.
     * @param bool $groups Whether a group menu follows the course menu (the teacher page's).
     * @return array As {@see self::menu_tab()}.
     */
    private static function course_tab(array $options, bool $groups): array {
        return self::menu_tab(
            'course',
            'fa-graduation-cap',
            'tab_course',
            'course_download_help',
            'download_course.php',
            'download_course',
            'course_select',
            'courseid',
            get_string('course'),
            'choose_course',
            'download_course_certificates',
            $options,
            $groups
        );
    }

    /**
     * The user tab, or null when the scope holds no certificate owner.
     *
     * @param repository $repository The certificate repository.
     * @param scope $scope The viewer's scope.
     * @return array|null As {@see self::menu_tab()}.
     */
    private static function user_tab(repository $repository, scope $scope): ?array {
        $users = [];
        foreach ($repository->get_users($scope) as $user) {
            $users[] = ['value' => $user->id, 'text' => $user->fullname, 'count' => $user->total];
        }
        if (!$users) {
            return null;
        }
        return self::menu_tab(
            'user',
            'fa-user',
            'tab_user',
            'user_download_help',
            'download_user.php',
            'download_user',
            'user_select',
            'userid',
            get_string('user'),
            'choose_user',
            'download_user_certificates',
            $users,
            false
        );
    }

    /**
     * A tab whose form picks one course, cohort or user from a menu.
     *
     * The form starts with no count: async_download.js copies the chosen option's count onto it.
     * The menu ids are the ones certificate_table.js enhances and watches.
     *
     * @param string $id The tab's id suffix, which is also the request type.
     * @param string $icon The Font Awesome class of its decorative icon.
     * @param string $label The lang key of its title.
     * @param string $help The lang key of the line above its form.
     * @param string $script The download page, in the plugin directory.
     * @param string $action The page's `action` value.
     * @param string $selectid The menu's id.
     * @param string $name The menu's field name.
     * @param string $selectlabel The menu's accessible name.
     * @param string $placeholder The lang key of the empty option.
     * @param string $buttonlabel The lang key of the submit button.
     * @param array $options List of value, text (formatted, not escaped), count.
     * @param bool $groups Whether a group menu follows.
     * @return array As {@see self::tab()}, menu: url, sesskey, threshold, type, action, selectid, name,
     *         selectlabel, placeholder, options, groups, buttonlabel.
     */
    private static function menu_tab(
        string $id,
        string $icon,
        string $label,
        string $help,
        string $script,
        string $action,
        string $selectid,
        string $name,
        string $selectlabel,
        string $placeholder,
        string $buttonlabel,
        array $options,
        bool $groups
    ): array {
        $tab = self::tab($id, $icon, $label, $help);
        $tab['menu'] = [
            'url' => (new \moodle_url('/blocks/download_certificates/' . $script))->out(false),
            'sesskey' => sesskey(),
            'threshold' => download::SYNC_LIMIT,
            'type' => $id,
            'action' => $action,
            'selectid' => $selectid,
            'name' => $name,
            'selectlabel' => $selectlabel,
            'placeholder' => get_string($placeholder, 'block_download_certificates'),
            'options' => $options,
            'groups' => $groups,
            'buttonlabel' => get_string($buttonlabel, 'block_download_certificates'),
        ];
        return $tab;
    }
}
