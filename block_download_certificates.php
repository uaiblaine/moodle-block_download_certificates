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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/download_certificates/classes/controller.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_access.php');

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
        global $OUTPUT, $USER;

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

        // Use system context for capability checks.
        $context = context_system::instance();

        try {
            // Vérification du niveau d'accès (gestionnaire > enseignant > apprenant).
            $ismanager = has_capability('block/download_certificates:manage', $context);

            if ($ismanager) {
                // Interface complète pour les gestionnaires/administrateurs.
                $this->content->text = $this->get_manager_content();
            } else if ($this->user_is_teacher($USER->id)) {
                // Interface filtrée pour les enseignants.
                $this->content->text = $this->get_teacher_content();
            } else {
                // Interface simplifiée pour les apprenants.
                $this->content->text = $this->get_learner_content();
            }
        } catch (Exception $e) {
            // Error handling.
            $this->content->text = html_writer::div(
                get_string('error_loading_block', 'block_download_certificates'),
                'alert alert-danger'
            );
        }

        return $this->content;
    }

    /**
     * Get content for managers (admin interface).
     *
     * @return string
     */
    private function get_manager_content() {
        try {
            // Initialize controller.
            $controller = new block_download_certificates_controller();

            // Get quick stats for the block.
            $stats = $this->get_certificate_stats($controller);

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
                    'pending_tasks' => $controller->get_pending_tasks(),
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
            $content .= $this->get_manager_personal_section();

            return $content;
        } catch (Exception $e) {
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
     * @return string
     */
    private function get_learner_content() {
        global $USER;

        try {
            // Initialize controller.
            $controller = new block_download_certificates_controller();

            // Get user's certificate count.
            $usercertcount = $this->get_user_certificate_count($USER->id);

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
        } catch (Exception $e) {
            // Error handling.
            return html_writer::div(
                get_string('error_loading_block', 'block_download_certificates'),
                'alert alert-danger'
            );
        }
    }

    /**
     * Get the number of certificates for a specific user.
     *
     * @param int $userid User ID
     * @return int Number of certificates
     */
    private function get_user_certificate_count($userid) {
        global $DB;

        try {
            // Count tool_certificate certificates.
            $toolcertcount = 0;
            $dbman = $DB->get_manager();
            if ($dbman->table_exists('tool_certificate_issues')) {
                $toolcertcount = $DB->count_records('tool_certificate_issues', ['userid' => $userid]);
            }

            // Count customcert certificates.
            $customcertcount = 0;
            if ($dbman->table_exists('customcert_issues')) {
                $customcertcount = $DB->count_records('customcert_issues', ['userid' => $userid]);
            }

            // Count mod_certificate certificates.
            $modcertcount = 0;
            if ($dbman->table_exists('certificate_issues')) {
                $modcertcount = $DB->count_records('certificate_issues', ['userid' => $userid]);
            }

            // Count mod_simplecertificate certificates.
            $simplecertcount = 0;
            if ($dbman->table_exists('simplecertificate_issues')) {
                $simplecertcount = $DB->count_records_select(
                    'simplecertificate_issues',
                    'userid = ? AND (timedeleted IS NULL OR timedeleted = 0)',
                    [$userid]
                );
            }

            // Count mod_certificatebeautiful certificates.
            $certificatebeautifulcount = 0;
            if ($dbman->table_exists('certificatebeautiful_issue')) {
                $certificatebeautifulcount = $DB->count_records('certificatebeautiful_issue', ['userid' => $userid]);
            }

            return $toolcertcount + $customcertcount + $modcertcount + $simplecertcount + $certificatebeautifulcount;
        } catch (Exception $e) {
            debugging('Error getting user certificate count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get certificate statistics for the block display.
     *
     * @param block_download_certificates_controller $controller
     * @return array
     */
    private function get_certificate_stats($controller) {
        global $DB;

        $stats = [
            'total' => 0,
            'courses' => 0,
            'recent' => 0,
        ];

        try {
            $dbman = $DB->get_manager();

            // Total certificates from all sources.
            $toolcerttotal = 0;
            $customcerttotal = 0;
            $modcerttotal = 0;
            $simplecerttotal = 0;
            $certificatebeautifultotal = 0;

            if ($dbman->table_exists('tool_certificate_issues')) {
                $toolcerttotal = $DB->count_records('tool_certificate_issues');
            }

            if ($dbman->table_exists('customcert_issues')) {
                $customcerttotal = $DB->count_records('customcert_issues');
            }

            if ($dbman->table_exists('certificate_issues')) {
                $modcerttotal = $DB->count_records('certificate_issues');
            }

            if ($dbman->table_exists('simplecertificate_issues')) {
                $simplecerttotal = $DB->count_records_select(
                    'simplecertificate_issues',
                    'timedeleted IS NULL OR timedeleted = 0'
                );
            }

            if ($dbman->table_exists('certificatebeautiful_issue')) {
                $certificatebeautifultotal = $DB->count_records('certificatebeautiful_issue');
            }

            $stats['total'] = $toolcerttotal + $customcerttotal + $modcerttotal + $simplecerttotal + $certificatebeautifultotal;

            // Number of unique courses with certificates from all sources.
            // Use UNION to get all unique course IDs that have any type of certificate.
            $courseids = [];

            if ($dbman->table_exists('tool_certificate_issues')) {
                $sql1 = "SELECT DISTINCT courseid as course_id FROM {tool_certificate_issues} WHERE courseid > 1";
                $toolcourseids = $DB->get_fieldset_sql($sql1);
                $courseids = array_merge($courseids, $toolcourseids);
            }

            if ($dbman->table_exists('customcert_issues') && $dbman->table_exists('customcert')) {
                $sql2 = "SELECT DISTINCT c.course as course_id FROM {customcert_issues} ci
                         JOIN {customcert} c ON c.id = ci.customcertid WHERE c.course > 1";
                $customcourseids = $DB->get_fieldset_sql($sql2);
                $courseids = array_merge($courseids, $customcourseids);
            }

            if ($dbman->table_exists('certificate_issues') && $dbman->table_exists('certificate')) {
                $sql3 = "SELECT DISTINCT c.course as course_id FROM {certificate_issues} ci
                         JOIN {certificate} c ON c.id = ci.certificateid WHERE c.course > 1";
                $modcourseids = $DB->get_fieldset_sql($sql3);
                $courseids = array_merge($courseids, $modcourseids);
            }

            if ($dbman->table_exists('simplecertificate_issues')) {
                $sql4 = "SELECT DISTINCT c.id as course_id FROM {simplecertificate_issues} si
                         JOIN {course} c ON (c.shortname = si.coursename OR c.fullname = si.coursename)
                         WHERE c.id > 1 AND (si.timedeleted IS NULL OR si.timedeleted = 0)";
                $simplecourseids = $DB->get_fieldset_sql($sql4);
                $courseids = array_merge($courseids, $simplecourseids);
            }

            if ($dbman->table_exists('certificatebeautiful_issue') && $dbman->table_exists('course_modules')) {
                $sql5 = "SELECT DISTINCT c.id as course_id FROM {certificatebeautiful_issue} cbi
                         JOIN {course_modules} cm ON cm.id = cbi.cmid
                         JOIN {course} c ON c.id = cm.course
                         WHERE c.id > 1";
                $certificatebeautifulcourseids = $DB->get_fieldset_sql($sql5);
                $courseids = array_merge($courseids, $certificatebeautifulcourseids);
            }

            // Count unique courses.
            $stats['courses'] = count(array_unique($courseids));

            // Recent certificates (last 7 days) from all sources.
            $weekago = time() - (7 * 24 * 60 * 60);
            $toolrecent = 0;
            $customrecent = 0;
            $modrecent = 0;
            $simplerecent = 0;
            $certificatebeautifulrecent = 0;

            if ($dbman->table_exists('tool_certificate_issues')) {
                $toolrecent = $DB->count_records_select('tool_certificate_issues', 'timecreated >= ?', [$weekago]);
            }

            if ($dbman->table_exists('customcert_issues')) {
                $customrecent = $DB->count_records_select('customcert_issues', 'timecreated >= ?', [$weekago]);
            }

            if ($dbman->table_exists('certificate_issues')) {
                $modrecent = $DB->count_records_select('certificate_issues', 'timecreated >= ?', [$weekago]);
            }

            if ($dbman->table_exists('simplecertificate_issues')) {
                $simplerecent = $DB->count_records_select(
                    'simplecertificate_issues',
                    'timecreated >= ? AND (timedeleted IS NULL OR timedeleted = 0)',
                    [$weekago]
                );
            }

            if ($dbman->table_exists('certificatebeautiful_issue')) {
                $certificatebeautifulrecent = $DB->count_records_select(
                    'certificatebeautiful_issue',
                    'timecreated >= ?',
                    [$weekago]
                );
            }

            $stats['recent'] = $toolrecent + $customrecent + $modrecent + $simplerecent + $certificatebeautifulrecent;
        } catch (Exception $e) {
            // If there's an error, return zeros.
            debugging('Error getting certificate stats: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get personal certificates section for managers.
     *
     * @return string
     */
    private function get_manager_personal_section() {
        global $USER;

        try {
            // Get user's certificate count.
            $usercertcount = $this->get_user_certificate_count($USER->id);

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
        } catch (Exception $e) {
            // Error handling - return empty string on error.
            debugging('Error loading manager personal section: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Détermine si un utilisateur est enseignant dans au moins un cours.
     *
     * Délègue à block_download_certificates_access::is_teacher().
     *
     * @param int $userid ID de l'utilisateur
     * @return bool
     */
    private function user_is_teacher(int $userid): bool {
        $access = new block_download_certificates_access();
        return $access->is_teacher($userid);
    }

    /**
     * Contenu du bloc pour les enseignants.
     *
     * Affiche le nombre de certificats dans le périmètre de l'enseignant
     * et un lien vers la page enseignant (index_teacher.php).
     *
     * @return string HTML du contenu du bloc
     */
    private function get_teacher_content(): string {
        global $USER;

        try {
            $access = new block_download_certificates_access();
            $scope = $access->get_teacher_visible_user_ids($USER->id);
            $courseids = array_keys($scope);

            // Compter les certificats dans le périmètre de l'enseignant.
            require_once($GLOBALS['CFG']->dirroot . '/blocks/download_certificates/classes/certificate_query.php');
            $query = new block_download_certificates_query();
            $totalcount = $query->count_search_results_for_teacher($scope);

            $content = '';

            // Affichage du nombre de certificats.
            $content .= html_writer::start_div('certificate-stats mb-3 text-center');
            $content .= html_writer::tag('div', $totalcount, ['class' => 'h4 mb-0 text-primary']);
            $content .= html_writer::tag(
                'small',
                get_string('teacher_certificates', 'block_download_certificates'),
                ['class' => 'text-muted d-block']
            );
            $content .= html_writer::end_div();

            // Lien vers la page enseignant.
            $manageurl = new moodle_url('/blocks/download_certificates/index_teacher.php');
            $content .= html_writer::link(
                $manageurl,
                get_string('teacher_panel', 'block_download_certificates'),
                ['class' => 'btn btn-primary btn-sm d-block mb-2']
            );

            // Section certificats personnels (comme pour le gestionnaire).
            $content .= $this->get_manager_personal_section();

            return $content;
        } catch (Exception $e) {
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
