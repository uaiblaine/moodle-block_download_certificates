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
 * Block download_certificates definition.
 *
 * @package   block_download_certificates
 * @copyright 2015 Manieer Chhettri
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_download_certificates\local\filter;
use block_download_certificates\local\repository;
use block_download_certificates\local\scope;
use block_download_certificates\local\zip_tasks;

/**
 * Block download_certificates class.
 *
 * This class defines the download certificates block functionality.
 *
 * @package   block_download_certificates
 * @copyright 2015 Manieer Chhettri
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_download_certificates extends block_base {
    /**
     * Initialize block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_download_certificates');
    }

    /**
     * Defines where the block can be used.
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'admin' => true,
            'site-index' => true,
            'course-view' => true,
            'mod' => false,
            'my' => true,
            'user-profile' => true,
        ];
    }

    /**
     * Controls whether multiple instances of the block are allowed.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Controls whether the block has a settings.php file.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }

    /**
     * Get block content.
     *
     * @return stdClass
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Don't show for guests or non-logged in users.
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        try {
            $scope = $this->get_scope();
            $repository = new repository();
            if ($scope->is_manager()) {
                $this->content->text = $this->get_manager_content($scope, $repository);
            } else if ($scope->get_course_ids()) {
                $this->content->text = $this->get_teacher_content($scope, $repository);
            } else {
                $this->content->text = $this->get_learner_content($scope, $repository);
            }
        } catch (\Throwable $e) {
            debugging('Download certificates block: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $this->content->text = html_writer::div(
                get_string('error_loading_block', 'block_download_certificates'),
                'alert alert-danger'
            );
        }

        return $this->content;
    }

    /**
     * The viewer's scope, narrowed to the page the block stands on: a course page keeps that course,
     * a user profile keeps that user.
     *
     * @return scope
     */
    private function get_scope(): scope {
        global $USER;

        $courseid = null;
        if (!empty($this->page->course->id) && (int) $this->page->course->id !== (int) SITEID) {
            $courseid = (int) $this->page->course->id;
        }
        // The dashboard stands in the viewer's user context too; only a profile page narrows.
        $profileuserid = null;
        if ($this->page->context->contextlevel == CONTEXT_USER && str_starts_with($this->page->pagetype, 'user-')) {
            $profileuserid = (int) $this->page->context->instanceid;
        }
        return scope::for_user((int) $USER->id, $courseid, $profileuserid);
    }

    /**
     * Get content for managers (admin interface).
     *
     * @param scope $scope The viewer's scope.
     * @param repository $repository The certificate repository.
     * @return string
     */
    private function get_manager_content(scope $scope, repository $repository) {
        try {
            $stats = $this->get_certificate_stats($scope, $repository);

            // Create content.
            $content = '';

            // Stats display.
            $content .= html_writer::start_div('certificate-stats mb-3');
            $content .= html_writer::tag(
                'h6',
                get_string('certificate_summary', 'block_download_certificates'),
                ['class' => 'mb-2']
            );

            $content .= html_writer::start_div('row text-center');

            $content .= html_writer::start_div('col-4');
            $content .= html_writer::tag('div', $stats['total'], ['class' => 'h5 mb-0 text-primary']);
            $content .= html_writer::tag(
                'small',
                get_string('total', 'block_download_certificates'),
                ['class' => 'text-muted']
            );
            $content .= html_writer::end_div();

            $content .= html_writer::start_div('col-4');
            $content .= html_writer::tag('div', $stats['courses'], ['class' => 'h5 mb-0 text-success']);
            $content .= html_writer::tag(
                'small',
                get_string('courses', 'block_download_certificates'),
                ['class' => 'text-muted']
            );
            $content .= html_writer::end_div();

            $content .= html_writer::start_div('col-4');
            $content .= html_writer::tag('div', $stats['recent'], ['class' => 'h5 mb-0 text-info']);
            $content .= html_writer::tag(
                'small',
                get_string('recent_7days', 'block_download_certificates'),
                ['class' => 'text-muted']
            );
            $content .= html_writer::end_div();

            $content .= html_writer::end_div(); // Row.
            $content .= html_writer::end_div(); // Stats.

            // Quick actions.
            $content .= html_writer::start_div('quick-actions');

            // Main management link.
            $manageurl = new moodle_url('/blocks/download_certificates/index.php');
            $content .= html_writer::link(
                $manageurl,
                get_string('manage_certificates', 'block_download_certificates'),
                ['class' => 'btn btn-primary btn-sm d-block mb-2']
            );

            // Quick download all button (async).
            if ($stats['total'] > 0) {
                $downloadurl = new moodle_url(
                    '/blocks/download_certificates/download.php',
                    ['action' => 'download_all', 'sesskey' => sesskey()]
                );
                $content .= html_writer::link(
                    $downloadurl,
                    get_string('download_all_quick', 'block_download_certificates'),
                    [
                        'class' => 'btn btn-success btn-sm d-block',
                        'data-download-all' => '1',
                        'data-cert-count' => $stats['total'],
                    ]
                );

                // Load async download JS module for the block.
                $jsconfig = [
                    'sesskey' => sesskey(),
                    'pending_tasks' => zip_tasks::get_pending($scope->get_user_id()),
                ];
                $this->page->requires->js_call_amd('block_download_certificates/async_download', 'init', [$jsconfig]);

                // Required JS strings for the async download module.
                $this->page->requires->strings_for_js([
                    'async_generating',
                    'async_preparing',
                    'async_processing',
                    'async_ready',
                    'async_zip_ready',
                    'async_close',
                    'async_download',
                    'async_can_close',
                    'async_zip_ready_label_all',
                    'async_zip_ready_label_course',
                    'async_zip_ready_label_user',
                    'async_zip_ready_label_cohort',
                    'async_zip_ready_label_range',
                    'certificates',
                ], 'block_download_certificates');
            }

            $content .= html_writer::end_div(); // Actions.

            // Add personal certificates section for managers.
            $content .= $this->get_manager_personal_section($scope, $repository);

            return $content;
        } catch (\Throwable $e) {
            // Error handling.
            return html_writer::div(
                get_string('error_loading_block', 'block_download_certificates'),
                'alert alert-danger'
            );
        }
    }

    /**
     * Get content for learners (simplified interface).
     *
     * @param scope $scope The viewer's scope.
     * @param repository $repository The certificate repository.
     * @return string
     */
    private function get_learner_content(scope $scope, repository $repository) {
        try {
            $usercertcount = $this->get_own_count($scope, $repository);

            // Create content.
            $content = '';

            // Certificate count display.
            $content .= html_writer::start_div('user-certificates mb-3 text-center');
            $content .= html_writer::tag('div', $usercertcount, ['class' => 'h4 mb-1 text-primary']);
            $content .= html_writer::tag(
                'small',
                get_string('my_certificates_count', 'block_download_certificates'),
                ['class' => 'text-muted d-block']
            );
            $content .= html_writer::end_div();

            // Download button (only if user has certificates).
            if ($usercertcount > 0) {
                $downloadurl = new moodle_url(
                    '/blocks/download_certificates/download_user.php',
                    ['action' => 'download_my_certificates', 'sesskey' => sesskey()]
                );
                $content .= html_writer::link(
                    $downloadurl,
                    get_string('download_my_certificates', 'block_download_certificates'),
                    ['class' => 'btn btn-primary btn-sm d-block']
                );
            } else {
                $content .= html_writer::div(
                    get_string('no_certificates_user', 'block_download_certificates'),
                    'alert alert-info text-center'
                );
            }

            return $content;
        } catch (\Throwable $e) {
            // Error handling.
            return html_writer::div(
                get_string('error_loading_block', 'block_download_certificates'),
                'alert alert-danger'
            );
        }
    }

    /**
     * The viewer's own certificates within the scope.
     *
     * @param scope $scope The viewer's scope.
     * @param repository $repository The certificate repository.
     * @return int
     */
    private function get_own_count(scope $scope, repository $repository): int {
        return $repository->count($scope, new filter(userid: $scope->get_user_id()));
    }

    /**
     * The manager summary: certificates, courses holding them, certificates of the last 7 days.
     *
     * @param scope $scope The viewer's scope.
     * @param repository $repository The certificate repository.
     * @return array total, courses, recent.
     */
    private function get_certificate_stats(scope $scope, repository $repository): array {
        return [
            'total' => $repository->count($scope, new filter()),
            'courses' => count($repository->get_courses($scope)),
            'recent' => $repository->count($scope, new filter(datefrom: time() - 7 * DAYSECS)),
        ];
    }

    /**
     * Get personal certificates section for managers.
     *
     * @param scope $scope The viewer's scope.
     * @param repository $repository The certificate repository.
     * @return string
     */
    private function get_manager_personal_section(scope $scope, repository $repository) {
        try {
            $usercertcount = $this->get_own_count($scope, $repository);

            // Create personal section.
            $content = '';

            // Add separator.
            $content .= html_writer::tag('hr', '', ['class' => 'my-3']);

            // Personal certificates section.
            $content .= html_writer::start_div('manager-personal-certificates');
            $content .= html_writer::tag(
                'h6',
                get_string('my_certificates', 'block_download_certificates'),
                ['class' => 'mb-2 text-secondary']
            );

            // Certificate count display.
            $content .= html_writer::start_div('user-certificates mb-2 text-center');
            $content .= html_writer::tag('div', $usercertcount, ['class' => 'h5 mb-1 text-primary']);
            $content .= html_writer::tag(
                'small',
                get_string('my_certificates_count', 'block_download_certificates'),
                ['class' => 'text-muted d-block']
            );
            $content .= html_writer::end_div();

            // Download button (only if user has certificates).
            if ($usercertcount > 0) {
                $downloadurl = new moodle_url(
                    '/blocks/download_certificates/download_user.php',
                    ['action' => 'download_my_certificates', 'sesskey' => sesskey()]
                );
                $content .= html_writer::link(
                    $downloadurl,
                    get_string('download_my_certificates', 'block_download_certificates'),
                    ['class' => 'btn btn-outline-primary btn-sm d-block']
                );
            } else {
                $content .= html_writer::div(
                    get_string('no_certificates_user', 'block_download_certificates'),
                    'alert alert-light text-center small'
                );
            }

            $content .= html_writer::end_div(); // Manager-personal-certificates.

            return $content;
        } catch (\Throwable $e) {
            // Error handling - return empty string on error.
            debugging('Error loading manager personal section: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Content for a user with courses of their own: the certificates in scope and a link to the
     * teacher page.
     *
     * @param scope $scope The viewer's scope.
     * @param repository $repository The certificate repository.
     * @return string HTML of the block content
     */
    private function get_teacher_content(scope $scope, repository $repository): string {
        try {
            $totalcount = $repository->count($scope, new filter());

            $content = '';

            // Certificate count.
            $content .= html_writer::start_div('certificate-stats mb-3 text-center');
            $content .= html_writer::tag('div', $totalcount, ['class' => 'h4 mb-0 text-primary']);
            $content .= html_writer::tag(
                'small',
                get_string('teacher_certificates', 'block_download_certificates'),
                ['class' => 'text-muted d-block']
            );
            $content .= html_writer::end_div();

            // Link to the teacher page.
            $manageurl = new moodle_url('/blocks/download_certificates/index_teacher.php');
            $content .= html_writer::link(
                $manageurl,
                get_string('teacher_panel', 'block_download_certificates'),
                ['class' => 'btn btn-primary btn-sm d-block mb-2']
            );

            // The personal section, as for managers.
            $content .= $this->get_manager_personal_section($scope, $repository);

            return $content;
        } catch (\Throwable $e) {
            return html_writer::div(
                get_string('error_loading_block', 'block_download_certificates'),
                'alert alert-danger'
            );
        }
    }

    /**
     * Copy any block-specific data when copying to a new block instance.
     * Since this block does not use instance-specific custom tables,
     * we simply return true to allow the dashboard duplication to proceed.
     *
     * @param int $fromid original block instance id
     * @return boolean
     */
    public function instance_copy($fromid) {
        return true;
    }
}
