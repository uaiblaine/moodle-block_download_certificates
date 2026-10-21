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
 * Teacher page: the certificates of the courses where the user holds `:downloadcourse`.
 *
 * Reached by a user whose scope has courses of their own; managers go to index.php.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_download_certificates\local\download;
use block_download_certificates\local\filter;
use block_download_certificates\local\repository;
use block_download_certificates\local\request;
use block_download_certificates\local\scope;

require_once(__DIR__ . '/../../config.php');

require_login(null, false);
request::reject_guest();

$context = context_system::instance();
$scope = scope::for_user((int) $USER->id);

if ($scope->is_manager()) {
    redirect(new moodle_url('/blocks/download_certificates/index.php'));
}
if (!$scope->get_course_ids()) {
    redirect(
        new moodle_url('/my/'),
        get_string('accessdenied', 'block_download_certificates'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$PAGE->set_context($context);
$PAGE->set_url('/blocks/download_certificates/index_teacher.php');
$PAGE->set_title(get_string('teacher_panel', 'block_download_certificates'));
$PAGE->set_heading(get_string('teacher_panel', 'block_download_certificates'));
$PAGE->set_pagelayout('standard');
$PAGE->requires->css('/blocks/download_certificates/styles.css');

$repository = new repository();
$totalcount = $repository->count($scope, new filter());

// The course menu offers the courses a course ZIP may be requested for, not every course where the
// teacher holds a certificate of their own.
$teachercourses = array_flip($scope->get_course_ids());
$courses = [];
foreach ($repository->get_courses($scope) as $course) {
    if (isset($teachercourses[$course->id])) {
        $courses[] = ['id' => $course->id, 'fullname' => $course->name, 'certificate_count' => $course->total];
    }
}
$users = [];
foreach ($repository->get_users($scope) as $user) {
    $users[] = ['id' => $user->id, 'fullname' => $user->fullname, 'certificate_count' => $user->total];
}

$data = [
    'total_count' => $totalcount,
    'has_certificates' => $totalcount > 0,
    'courses' => $courses,
    'has_courses' => !empty($courses),
    'users' => $users,
    'has_users' => !empty($users),
    'cohorts' => [],
    'has_cohorts' => false,
    'sesskey' => sesskey(),
    'async_threshold' => download::SYNC_LIMIT,
    'pending_tasks' => [],
    'download_course_url' => new moodle_url('/blocks/download_certificates/download_course.php'),
    'download_user_url' => new moodle_url('/blocks/download_certificates/download_user.php'),
    'is_teacher_view' => true,
];

// The table module is the manager page's; the search script applies the teacher's scope.
$tableconfig = [
    'sesskey'      => sesskey(),
    'total_count'  => $totalcount,
    'is_teacher'   => true,
];
$PAGE->requires->js_call_amd('block_download_certificates/certificate_table', 'init', [$tableconfig]);

// Strings for the JS modules.
$PAGE->requires->strings_for_js([
    'certificates',
    'template',
    'date_created',
    'no_certificates_found',
    'no_search_results',
    'type',
    'download_certificate',
    'all_groups',
], 'block_download_certificates');

$PAGE->requires->strings_for_js([
    'user',
    'email',
    'course',
    'actions',
], 'core');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_download_certificates/teacher_page', $data);
echo $OUTPUT->footer();
