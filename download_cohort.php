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
 * Download certificates by cohort for block_download_certificates plugin.
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

// Get parameters.
$action = required_param('action', PARAM_ALPHANUMEXT);
$cohortid = required_param('cohortid', PARAM_INT);

// Verify sesskey.
require_sesskey();

if ($action !== 'download_cohort') {
    throw new moodle_exception('invalidaction', 'block_download_certificates');
}

if (empty($cohortid)) {
    redirect(
        new moodle_url('/blocks/download_certificates/index.php'),
        get_string('cohortnotselected', 'block_download_certificates'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

try {
    // Create controller instance.
    $controller = new block_download_certificates_controller();

    // Download certificates for cohort members.
    $controller->download_cohort_certificates($cohortid);
} catch (Exception $e) {
    // Log the error.
    debugging('Cohort certificate download error: ' . $e->getMessage());

    // Show error page.
    $PAGE->set_context($context);
    $PAGE->set_url('/blocks/download_certificates/download_cohort.php');
    $PAGE->set_title(get_string('error'));

    echo $OUTPUT->header();
    echo $OUTPUT->notification($e->getMessage(), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/blocks/download_certificates/index.php'));
    echo $OUTPUT->footer();
}
