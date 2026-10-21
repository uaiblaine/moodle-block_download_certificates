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
 * Download user's own certificates for block_download_certificates plugin.
 *
 * @package   block_download_certificates
 * @copyright 2015 Manieer Chhettri
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/controller.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_access.php');

require_login();

// Get parameters.
$action = required_param('action', PARAM_ALPHANUMEXT);
$userid = optional_param('userid', 0, PARAM_INT);

// Validate sesskey for security.
require_sesskey();

// Check context and permissions.
$context = context_system::instance();

$ismanager = has_capability('block/download_certificates:manage', $context);
$access    = new block_download_certificates_access();
$isteacher = !$ismanager && $access->is_teacher($USER->id);

if ($action === 'download_my_certificates') {
    // User downloading their own certificates.
    require_capability('block/download_certificates:view', $context);
    $userid = $USER->id;
} else if ($action === 'download_user') {
    // Téléchargement pour un utilisateur spécifique.
    if (!$ismanager && !$isteacher) {
        // Accès refusé si ni gestionnaire ni enseignant.
        require_capability('block/download_certificates:manage', $context);
    }
    if (!$userid) {
        throw new moodle_exception('missingparam', 'error', '', 'userid');
    }
    // Vérification que l'enseignant peut accéder à cet utilisateur.
    if ($isteacher) {
        $alluserids = $access->get_teacher_all_visible_user_ids($USER->id);
        if (!in_array($userid, $alluserids, true)) {
            throw new moodle_exception('accessdenied', 'block_download_certificates');
        }
    }
} else {
    throw new moodle_exception('invalidaction', 'block_download_certificates');
}

try {
    // Create controller instance.
    $controller = new block_download_certificates_controller();

    // Download user's certificates.
    $controller->download_user_certificates($userid);
} catch (Exception $e) {
    // Show error page.
    $PAGE->set_context($context);
    $PAGE->set_url('/blocks/download_certificates/download_user.php');
    $PAGE->set_title(get_string('error'));

    echo $OUTPUT->header();
    echo $OUTPUT->notification($e->getMessage(), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/my/'));
    echo $OUTPUT->footer();
}
