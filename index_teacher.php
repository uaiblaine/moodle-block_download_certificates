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

use block_download_certificates\local\bootstrap;
use block_download_certificates\local\repository;
use block_download_certificates\local\request;
use block_download_certificates\local\scope;
use block_download_certificates\local\zip_tasks;
use block_download_certificates\output\panel_page;

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
bootstrap::mark_page();

$data = panel_page::teacher($scope, new repository());

// The modules are the manager page's; the web services apply the teacher's scope.
$PAGE->requires->js_call_amd('block_download_certificates/certificate_table', 'init');
$PAGE->requires->js_call_amd('block_download_certificates/async_download', 'init', [[
    'userid' => (int) $USER->id,
    'pendingtasks' => zip_tasks::get_pending((int) $USER->id),
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_download_certificates/teacher_page', $data);
echo $OUTPUT->footer();
