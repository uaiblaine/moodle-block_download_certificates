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
 * Certificate download handler for block_download_certificates plugin.
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
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$timecreated = optional_param('timecreated', 0, PARAM_INT);
$code = optional_param('code', '', PARAM_ALPHANUMEXT);
$startdate = optional_param('start_date', '', PARAM_TEXT);
$enddate = optional_param('end_date', '', PARAM_TEXT);

// Validate sesskey for security.
require_sesskey();

// Initialize controller.
$controller = new block_download_certificates_controller();

if ($action === 'download_all' || $action === 'downloadall') {
    $controller->download_all_certificates();
    exit;
}

if ($action === 'download_range') {
    if (empty($startdate) || empty($enddate)) {
        throw new moodle_exception('invalidparameters', 'block_download_certificates');
    }

    // Convert date strings to timestamps.
    $starttime = strtotime($startdate . ' 00:00:00');
    $endtime = strtotime($enddate . ' 23:59:59');

    if ($starttime === false || $endtime === false) {
        throw new moodle_exception('invalidaterange', 'block_download_certificates');
    }

    $controller->download_certificates_by_date_range($starttime, $endtime);
    exit;
}

if (($action === 'download_single' || $action === 'downloadsingle') && !empty($timecreated) && !empty($code)) {
    try {
        $controller->download_single_certificate($timecreated, $code);
    } catch (Exception $e) {
        throw $e;
    }
    exit;
}

// If we get here, redirect back to main page.
redirect(new moodle_url('/blocks/download_certificates/index.php'));
