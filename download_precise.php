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
 * Download specific certificate.
 *
 * @package   block_download_certificates
 * @copyright 2015 Manieer Chhettri
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/controller.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_access.php');

require_login();

// Vérification des permissions (gestionnaire ou enseignant).
$context = context_system::instance();
$ismanager = has_capability('block/download_certificates:manage', $context);
$access = new block_download_certificates_access();
$isteacher = !$ismanager && $access->is_teacher($USER->id);

if (!$ismanager && !$isteacher) {
    // Ni gestionnaire ni enseignant : accès refusé (fail-close).
    require_capability('block/download_certificates:manage', $context);
}

// Get parameters.
$type = optional_param('type', 'tool_certificate', PARAM_ALPHANUMEXT);
$sesskey = required_param('sesskey', PARAM_RAW);

// Confirm sesskey.
confirm_sesskey($sesskey);

$PAGE->set_context($context);
$PAGE->set_url('/blocks/download_certificates/download_precise.php');

try {
    $controller = new block_download_certificates_controller();

    if ($type === 'customcert') {
        // For customcert, download PDF via URL and serve it.
        $userid        = required_param('userid', PARAM_INT);
        $certificateid = required_param('certificateid', PARAM_INT);

        // Vérification périmètre enseignant (tient compte des groupes par cours).
        if ($isteacher) {
            global $DB;
            $issue = $DB->get_record_sql(
                "SELECT cc.course FROM {customcert_issues} ci
                  JOIN {customcert} cc ON cc.id = ci.customcertid
                  WHERE ci.userid = :uid AND ci.customcertid = :cid",
                ['uid' => $userid, 'cid' => $certificateid],
                IGNORE_MISSING
            );
            $courseid = $issue ? (int) $issue->course : 0;
            $access->require_teacher_access_to_certificate($userid, $courseid);
        }

        $controller->download_single_customcert($userid, $certificateid);
    } else if ($type === 'mod_certificate') {
        // For mod_certificate, use the new method.
        $userid        = required_param('userid', PARAM_INT);
        $certificateid = required_param('certificateid', PARAM_INT);

        // Vérification périmètre enseignant (tient compte des groupes par cours).
        if ($isteacher) {
            global $DB;
            $issue = $DB->get_record_sql(
                "SELECT cert.course FROM {certificate_issues} ci
                  JOIN {certificate} cert ON cert.id = ci.certificateid
                  WHERE ci.userid = :uid AND ci.certificateid = :cid",
                ['uid' => $userid, 'cid' => $certificateid],
                IGNORE_MISSING
            );
            $courseid = $issue ? (int) $issue->course : 0;
            $access->require_teacher_access_to_certificate($userid, $courseid);
        }

        $controller->download_single_mod_certificate($userid, $certificateid);
    } else if ($type === 'mod_simplecertificate') {
        // For mod_simplecertificate, use the API method.
        $userid        = required_param('userid', PARAM_INT);
        $certificateid = required_param('certificateid', PARAM_INT);
        $code          = required_param('code', PARAM_ALPHANUMEXT);

        // Vérification périmètre enseignant (pas de courseid direct, on vérifie la liste plate).
        if ($isteacher) {
            $alluserids = $access->get_teacher_all_visible_user_ids($USER->id);
            if (!in_array($userid, $alluserids, true)) {
                throw new moodle_exception('accessdenied', 'block_download_certificates');
            }
        }

        $controller->download_single_simplecertificate($userid, $certificateid, $code);
    } else if ($type === 'mod_certificatebeautiful') {
        // For mod_certificatebeautiful, use the API method.
        $userid = required_param('userid', PARAM_INT);
        $certificateid = required_param('certificateid', PARAM_INT);
        $code = required_param('code', PARAM_ALPHANUMEXT);

        // Vérification périmètre enseignant.
        if ($isteacher) {
            $alluserids = $access->get_teacher_all_visible_user_ids($USER->id);
            if (!in_array($userid, $alluserids, true)) {
                throw new moodle_exception('accessdenied', 'block_download_certificates');
            }
        }

        $controller->download_single_certificatebeautiful($userid, $certificateid, $code);
    } else {
        // For tool_certificate, use the existing method.
        $timecreated = required_param('timecreated', PARAM_INT);
        $code = required_param('code', PARAM_ALPHANUMEXT);

        // Vérification périmètre enseignant via le code du certificat.
        if ($isteacher) {
            global $DB;
            $dbman = $DB->get_manager();
            if ($dbman->table_exists('tool_certificate_issues')) {
                $issue = $DB->get_record(
                    'tool_certificate_issues',
                    ['timecreated' => $timecreated, 'code' => $code],
                    'userid,courseid',
                    IGNORE_MISSING
                );
                if (!$issue) {
                    throw new moodle_exception('certificatenotfound', 'block_download_certificates');
                }
                $access->require_teacher_access_to_certificate((int)$issue->userid, (int)$issue->courseid);
            }
        }

        $controller->download_single_certificate($timecreated, $code);
    }
} catch (Exception $e) {
    debugging('Certificate download error: ' . $e->getMessage());

    // Redirection vers la bonne page selon le rôle.
    $returnurl = $isteacher
        ? new moodle_url('/blocks/download_certificates/index_teacher.php')
        : new moodle_url('/blocks/download_certificates/index.php');
    redirect(
        $returnurl,
        get_string('cannotdownloadcertificate', 'block_download_certificates'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
