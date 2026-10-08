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
 * Facade controller for block_download_certificates plugin.
 *
 * This class acts as the single entry point and delegates to:
 * - certificate_retriever: File content retrieval (storage + HTTP)
 * - certificate_query: SQL queries for certificate data
 * - certificate_packager: ZIP creation, filename generation, file sending
 *
 * @package   block_download_certificates
 * @copyright 2015 Manieer Chhettri  (Original Idea for Moodle 2.x and 3.x)
 * @copyright 2025 E-learning Touch' contact@elearningtouch.com (Maintainer)
 * @author    Thomas Clément 222384061+ClementThomasELT@users.noreply.github.com (Coder)
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');

require_once(__DIR__ . '/certificate_retriever.php');
require_once(__DIR__ . '/certificate_query.php');
require_once(__DIR__ . '/certificate_packager.php');

/**
 * Facade controller for certificate downloads.
 *
 * Delegates all heavy work to retriever, query, and packager classes.
 */
class block_download_certificates_controller {

    /** @var block_download_certificates_retriever */
    protected $retriever;

    /** @var block_download_certificates_query */
    protected $query;

    /** @var block_download_certificates_packager */
    protected $packager;

    /**
     * Constructor — initializes the three delegate classes.
     */
    public function __construct() {
        $this->retriever = new block_download_certificates_retriever();
        $this->query = new block_download_certificates_query();
        $this->packager = new block_download_certificates_packager($this->retriever, $this->query);
    }

    // =========================================================================
    // Data retrieval for display (index page, block, AJAX).
    // =========================================================================

    /**
     * Get certificates data for display.
     *
     * NOTE: No longer loads all certificate records. The table is populated
     * dynamically via AJAX (certificate_table.js + search_certificates.php).
     *
     * @return array Data for mustache template
     */
    public function get_certificates_data() {
        // Use optimized COUNT(*) queries — no need to load all records.
        $totalcount = $this->query->count_all_certificates();

        $cohorts = $this->query->get_cohorts_with_certificates();

        return [
            'total_count' => $totalcount,
            'download_all_url' => new moodle_url('/blocks/download_certificates/download.php', [
                'action' => 'download_all',
                'sesskey' => sesskey(),
            ]),
            'download_range_url' => new moodle_url('/blocks/download_certificates/download_range.php'),
            'download_course_url' => new moodle_url('/blocks/download_certificates/download_course.php'),
            'download_cohort_url' => new moodle_url('/blocks/download_certificates/download_cohort.php'),
            'download_user_url' => new moodle_url('/blocks/download_certificates/download_user.php'),
            'has_certificates' => ($totalcount > 0),
            'cohorts' => $cohorts,
            'has_cohorts' => !empty($cohorts),
            'sesskey' => sesskey(),
            'async_threshold' => 30,
            'pending_tasks' => $this->get_pending_tasks(),
        ];
    }

    // =========================================================================
    // Aggregation queries (delegated to query class).
    // =========================================================================

    /**
     * Get list of courses that have certificates.
     *
     * @return array Array of courses with certificate counts
     */
    public function get_courses_with_certificates() {
        return $this->query->get_courses_with_certificates();
    }

    /**
     * Get available cohorts with certificate counts.
     *
     * @return array Array of cohorts with certificate counts
     */
    public function get_cohorts_with_certificates() {
        return $this->query->get_cohorts_with_certificates();
    }

    /**
     * Get list of users that have certificates.
     *
     * @return array Array of users with certificate counts
     */
    public function get_users_with_certificates() {
        return $this->query->get_users_with_certificates();
    }

    /**
     * Count all certificates across all plugins.
     *
     * @return int Total certificate count
     */
    public function count_all_certificates() {
        return $this->query->count_all_certificates();
    }

    // =========================================================================
    // Batch download methods (delegated to packager class).
    // =========================================================================

    /**
     * Download all certificates as ZIP.
     */
    public function download_all_certificates() {
        $this->packager->download_all_certificates();
    }

    /**
     * Download certificates by date range as ZIP.
     *
     * @param int $startdate Start date timestamp
     * @param int $enddate End date timestamp
     */
    public function download_certificates_by_date_range($startdate, $enddate) {
        $this->packager->download_certificates_by_date_range($startdate, $enddate);
    }

    /**
     * Download all certificates for a specific course as ZIP.
     *
     * @param int $courseid Course ID
     */
    public function download_certificates_by_course($courseid) {
        $this->packager->download_certificates_by_course($courseid);
    }

    /**
     * Download all certificates for a specific user as ZIP.
     *
     * @param int $userid User ID
     */
    public function download_user_certificates($userid) {
        $this->packager->download_user_certificates($userid);
    }

    /**
     * Download certificates for all members of a cohort.
     *
     * @param int $cohortid Cohort ID
     */
    public function download_cohort_certificates($cohortid) {
        $this->packager->download_cohort_certificates($cohortid);
    }

    // =========================================================================
    // Individual download methods (delegated to packager class).
    // =========================================================================

    /**
     * Download a single tool_certificate file.
     *
     * @param int $timecreated Time created timestamp
     * @param string $code Certificate code
     */
    public function download_single_certificate($timecreated, $code) {
        $this->packager->download_single_certificate($timecreated, $code);
    }

    /**
     * Download a single customcert certificate file.
     *
     * @param int $userid User ID
     * @param int $certificateid Certificate ID
     */
    public function download_single_customcert($userid, $certificateid) {
        $this->packager->download_single_customcert($userid, $certificateid);
    }

    /**
     * Download a single mod_certificate file.
     *
     * @param int $userid User ID
     * @param int $certificateid Certificate ID
     */
    public function download_single_mod_certificate($userid, $certificateid) {
        $this->packager->download_single_mod_certificate($userid, $certificateid);
    }

    /**
     * Download a single simplecertificate file.
     *
     * @param int $userid User ID
     * @param int $certificateid Certificate ID
     * @param string $code Certificate code
     */
    public function download_single_simplecertificate($userid, $certificateid, $code) {
        $this->packager->download_single_simplecertificate($userid, $certificateid, $code);
    }

    /**
     * Download a single certificatebeautiful file.
     *
     * @param int $userid User ID
     * @param int $certificateid Certificate ID
     * @param string $code Certificate code
     */
    public function download_single_certificatebeautiful($userid, $certificateid, $code) {
        $this->packager->download_single_certificatebeautiful($userid, $certificateid, $code);
    }

    // =========================================================================
    // Async task management.
    // =========================================================================

    /**
     * Get pending/ready async tasks for the current user.
     *
     * @return array Array of task data for JS initialization
     */
    public function get_pending_tasks() {
        global $DB, $USER;

        $tasks = $DB->get_records_select(
            'block_download_certificates_tasks',
            "userid = ? AND status IN ('pending', 'processing', 'ready')",
            [$USER->id],
            'timecreated DESC'
        );

        $result = [];
        foreach ($tasks as $task) {
            $result[] = [
                'taskid' => (int) $task->id,
                'status' => $task->status,
                'progress' => (int) $task->progress,
                'total' => (int) $task->total,
                'type' => $task->type,
            ];
        }
        return $result;
    }
}
