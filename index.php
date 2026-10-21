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
 * Main controller for block_download_certificates plugin.
 *
 * @package   block_download_certificates
 * @copyright 2015 Manieer Chhettri
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/controller.php');

require_login();

$context = context_system::instance();
require_capability('block/download_certificates:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url('/blocks/download_certificates/index.php');
$PAGE->set_title(get_string('pluginname', 'block_download_certificates'));
$PAGE->set_heading(get_string('pluginname', 'block_download_certificates'));
$PAGE->set_pagelayout('admin');

// Initialize controller.
$controller = new block_download_certificates_controller();

// Get certificates data for display.
$data = $controller->get_certificates_data();

// Get courses with certificates for the dropdown.
$courses = $controller->get_courses_with_certificates();
$data['courses'] = $courses;
$data['has_courses'] = !empty($courses);

// Get users with certificates for the dropdown.
$users = $controller->get_users_with_certificates();
$data['users'] = $users;
$data['has_users'] = !empty($users);
$PAGE->requires->css('/blocks/download_certificates/styles.css');

// Initialize certificate table JS module (dynamic AJAX table).
$tableconfig = [
    'sesskey' => sesskey(),
    'total_count' => $data['total_count'],
];
$PAGE->requires->js_call_amd('block_download_certificates/certificate_table', 'init', [$tableconfig]);

// Initialize async download JS module.
$jsconfig = [
    'sesskey' => sesskey(),
    'pending_tasks' => $data['pending_tasks'] ?? [],
];
$PAGE->requires->js_call_amd('block_download_certificates/async_download', 'init', [$jsconfig]);

// Strings for JS modules.
$PAGE->requires->strings_for_js([
    // Async download strings.
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
    // Table strings.
    'certificates',
    'template',
    'date_created',
    'no_certificates_found',
    'no_search_results',
    'type',
    'download_certificate',
], 'block_download_certificates');

// Core strings used by certificate_table.js.
$PAGE->requires->strings_for_js([
    'user',
    'email',
    'course',
    'actions',
], 'core');

echo $OUTPUT->header();

// Render the main page using mustache template.
echo $OUTPUT->render_from_template('block_download_certificates/main_page', $data);

echo $OUTPUT->footer();
