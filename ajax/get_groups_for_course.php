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
 * Endpoint AJAX — retourne les groupes de l'enseignant dans un cours donné.
 *
 * Réponse JSON :
 * {
 *   "success": true,
 *   "groups": [{"id": 12, "name": "Groupe A"}, ...]
 * }
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch' contact@elearningtouch.com
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_access.php');

require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    require_sesskey();

    $courseid = required_param('courseid', PARAM_INT);
    $context  = context_system::instance();

    // Seuls les enseignants ou gestionnaires peuvent appeler cet endpoint.
    $ismanager = has_capability('block/download_certificates:manage', $context);
    $access    = new block_download_certificates_access();
    $isteacher = !$ismanager && $access->is_teacher($USER->id);

    if (!$ismanager && !$isteacher) {
        throw new moodle_exception('accessdenied', 'block_download_certificates');
    }

    // Récupérer les groupes de l'enseignant dans ce cours.
    $groups = [];
    if ($isteacher) {
        // Enseignant : uniquement ses groupes dans ce cours.
        $teachergroups = $access->get_teacher_groups_in_course($USER->id, $courseid);
        foreach ($teachergroups as $g) {
            $groups[] = ['id' => (int)$g->id, 'name' => format_string($g->name)];
        }
    } else {
        // Gestionnaire : tous les groupes du cours.
        $allgroups = groups_get_all_groups($courseid);
        foreach ($allgroups as $g) {
            $groups[] = ['id' => (int)$g->id, 'name' => format_string($g->name)];
        }
    }

    echo json_encode([
        'success' => true,
        'groups'  => $groups,
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
