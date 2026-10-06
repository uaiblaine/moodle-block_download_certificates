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
 * Download course certificates for block_download_certificates plugin.
 *
 * @package   block_download_certificates
 * @copyright 2015 Manieer Chhettri  (Original Idea for Moodle 2.x and 3.x)
 * @copyright 2025 E-learning Touch' contact@elearningtouch.com (Maintainer)
 * @author    Thomas Clément 222384061+ClementThomasELT@users.noreply.github.com (Coder)
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/blocks/download_certificates/lib.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/controller.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_access.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_retriever.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_query.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_packager.php');

require_login();

$context = context_system::instance();

// Vérification du niveau d'accès (gestionnaire ou enseignant).
$ismanager = has_capability('block/download_certificates:manage', $context);
$access = new block_download_certificates_access();
$isteacher = !$ismanager && $access->is_teacher($USER->id);

if (!$ismanager && !$isteacher) {
    require_capability('block/download_certificates:manage', $context);
}

// Get parameters.
$action   = required_param('action', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT); // 0 = tous les groupes.

// Verify sesskey.
require_sesskey();

if ($action !== 'download_course') {
    throw new moodle_exception('invalidaction', 'block_download_certificates');
}

if (empty($courseid)) {
    $returnurl = $ismanager
        ? new moodle_url('/blocks/download_certificates/index.php')
        : new moodle_url('/blocks/download_certificates/index_teacher.php');
    redirect($returnurl,
             get_string('coursenotselected', 'block_download_certificates'),
             null,
             \core\output\notification::NOTIFY_ERROR);
}

// Délégation selon le niveau d'accès.
if ($ismanager) {
    // Mode gestionnaire : téléchargement complet du cours.
    $controller = new block_download_certificates_controller();
    try {
        $controller->download_certificates_by_course($courseid);
    } catch (Exception $e) {
        redirect(new moodle_url('/blocks/download_certificates/index.php'),
                 $e->getMessage(),
                 null,
                 \core\output\notification::NOTIFY_ERROR);
    }
} else {
    // Mode enseignant : téléchargement filtré sur le périmètre (+ groupe optionnel).
    $retriever = new block_download_certificates_retriever();
    $query     = new block_download_certificates_query();
    $packager  = new block_download_certificates_packager($retriever, $query);
    try {
        $packager->download_certificates_by_course_for_teacher($courseid, $USER->id, $groupid ?: null);
    } catch (Exception $e) {
        redirect(new moodle_url('/blocks/download_certificates/index_teacher.php'),
                 $e->getMessage(),
                 null,
                 \core\output\notification::NOTIFY_ERROR);
    }
}
