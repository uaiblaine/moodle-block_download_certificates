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

use block_download_certificates\local\download;
use block_download_certificates\local\filter;
use block_download_certificates\local\repository;
use block_download_certificates\local\request;
use block_download_certificates\local\scope;
use block_download_certificates\local\zip_tasks;

require_once(__DIR__ . '/../../config.php');

require_login(null, false);
request::reject_guest();

$context = context_system::instance();
require_capability('block/download_certificates:manage', $context);
$scope = scope::for_user((int) $USER->id);

$PAGE->set_context($context);
$PAGE->set_url('/blocks/download_certificates/index.php');
$PAGE->set_title(get_string('pluginname', 'block_download_certificates'));
$PAGE->set_heading(get_string('pluginname', 'block_download_certificates'));
$PAGE->set_pagelayout('admin');

$repository = new repository();
$totalcount = $repository->count($scope, new filter());

$courses = [];
foreach ($repository->get_courses($scope) as $course) {
    $courses[] = ['id' => $course->id, 'fullname' => $course->name, 'certificate_count' => $course->total];
}
$users = [];
foreach ($repository->get_users($scope) as $user) {
    $users[] = ['id' => $user->id, 'fullname' => $user->fullname, 'certificate_count' => $user->total];
}
$cohorts = [];
foreach ($repository->get_cohorts($scope) as $cohort) {
    $cohorts[] = ['id' => $cohort->id, 'name' => $cohort->name, 'certificate_count' => $cohort->total];
}

$data = [
    'total_count' => $totalcount,
    'download_all_url' => new moodle_url('/blocks/download_certificates/download.php'),
    'download_range_url' => new moodle_url('/blocks/download_certificates/download_range.php'),
    'download_course_url' => new moodle_url('/blocks/download_certificates/download_course.php'),
    'download_cohort_url' => new moodle_url('/blocks/download_certificates/download_cohort.php'),
    'download_user_url' => new moodle_url('/blocks/download_certificates/download_user.php'),
    'has_certificates' => $totalcount > 0,
    'courses' => $courses,
    'has_courses' => !empty($courses),
    'users' => $users,
    'has_users' => !empty($users),
    'cohorts' => $cohorts,
    'has_cohorts' => !empty($cohorts),
    'sesskey' => sesskey(),
    'async_threshold' => download::SYNC_LIMIT,
    'pending_tasks' => zip_tasks::get_pending((int) $USER->id),
];
$PAGE->requires->css('/blocks/download_certificates/styles.css');

$PAGE->requires->js_call_amd('block_download_certificates/certificate_table', 'init');
$PAGE->requires->js_call_amd('block_download_certificates/async_download', 'init', [[
    'userid' => (int) $USER->id,
    'pendingtasks' => $data['pending_tasks'],
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_download_certificates/main_page', $data);

echo $OUTPUT->footer();
