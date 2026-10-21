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
 * AJAX endpoint to create an async certificate download task.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_retriever.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_query.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_packager.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_access.php');

require_login();
require_sesskey();

$context = context_system::instance();

// Vérification du niveau d'accès (gestionnaire ou enseignant).
$ismanager = has_capability('block/download_certificates:manage', $context);
$access    = new block_download_certificates_access();
$isteacher = !$ismanager && $access->is_teacher($USER->id);

if (!$ismanager && !$isteacher) {
    echo json_encode(['success' => false, 'error' => get_string('accessdenied', 'block_download_certificates')]);
    die();
}

$type = required_param('type', PARAM_ALPHANUMEXT);
$paramsraw = optional_param('params', '{}', PARAM_RAW);
$params = json_decode($paramsraw, true);

if (!is_array($params)) {
    $params = [];
}

// Validate type selon le niveau d'accès.
// Les enseignants ne peuvent créer que des tâches de type 'course' ou 'user'.
// Ils ne peuvent pas créer de tâches 'all', 'cohort' ou 'range'.
$validtypes = ['all', 'course', 'user', 'cohort', 'range'];
$teachertypes = ['course', 'user'];

if ($isteacher && !in_array($type, $teachertypes)) {
    echo json_encode(['success' => false, 'error' => get_string('accessdenied', 'block_download_certificates')]);
    die();
}

if (!in_array($type, $validtypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid download type']);
    die();
}

// Vérification du périmètre de l'enseignant pour les paramètres spécifiques.
if ($isteacher) {
    if ($type === 'course' && !empty($params['courseid'])) {
        $courseid = (int) $params['courseid'];
        if (!in_array($courseid, $access->get_teacher_course_ids($USER->id))) {
            echo json_encode(['success' => false, 'error' => get_string('accessdenied', 'block_download_certificates')]);
            die();
        }
    } else if ($type === 'user' && !empty($params['userid'])) {
        $targetuserid = (int) $params['userid'];
        $alluserids = $access->get_teacher_all_visible_user_ids($USER->id);
        if (!in_array($targetuserid, $alluserids, true)) {
            echo json_encode(['success' => false, 'error' => get_string('accessdenied', 'block_download_certificates')]);
            die();
        }
    }
}

try {
    // Count certificates to set the total.
    $retriever = new block_download_certificates_retriever();
    $query = new block_download_certificates_query();
    $packager = new block_download_certificates_packager($retriever, $query);
    $total = $packager->count_certificates_for_type($type, $params);

    if ($total === 0) {
        echo json_encode(['success' => false, 'error' => get_string('nocertificates', 'block_download_certificates')]);
        die();
    }

    // Create task record.
    $taskrecord = new stdClass();
    $taskrecord->userid = $USER->id;
    $taskrecord->type = $type;
    $taskrecord->params = json_encode($params);
    $taskrecord->status = 'pending';
    $taskrecord->progress = 0;
    $taskrecord->total = $total;
    $taskrecord->batchoffset = 0;
    $taskrecord->timecreated = time();
    $taskrecord->timemodified = time();

    $taskid = $DB->insert_record('block_download_certificates_tasks', $taskrecord);

    // Schedule the adhoc task.
    $adhoctask = new \block_download_certificates\task\generate_zip();
    $adhoctask->set_custom_data((object) ['taskid' => $taskid]);
    $adhoctask->set_userid($USER->id);
    \core\task\manager::queue_adhoc_task($adhoctask);

    echo json_encode([
        'success' => true,
        'taskid' => $taskid,
        'total' => $total,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
