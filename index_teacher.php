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
 * Page principale pour les enseignants — interface filtrée sur leur périmètre.
 *
 * Seuls les enseignants (editingteacher / teacher dans au moins un cours) peuvent
 * accéder à cette page. Les gestionnaires sont redirigés vers index.php.
 * Les apprenants sont redirigés vers leur tableau de bord.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch' contact@elearningtouch.com (Maintainer)
 * @author    Thomas Clément 222384061+ClementThomasELT@users.noreply.github.com (Coder)
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_access.php');
require_once($CFG->dirroot . '/blocks/download_certificates/classes/certificate_query.php');
require_once($CFG->dirroot . '/blocks/download_certificates/lib.php');

require_login();

$context = context_system::instance();

// Les gestionnaires sont redirigés vers la page complète.
if (has_capability('block/download_certificates:manage', $context)) {
    redirect(new moodle_url('/blocks/download_certificates/index.php'));
}

// Vérification du rôle enseignant.
$access = new block_download_certificates_access();
if (!$access->is_teacher($USER->id)) {
    // Pas enseignant → accès refusé, retour au tableau de bord.
    redirect(
        new moodle_url('/my/'),
        get_string('accessdenied', 'block_download_certificates'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Récupération du périmètre de l'enseignant.
$scope        = $access->get_teacher_visible_user_ids($USER->id);
$courseids    = array_keys($scope);
$alluserids   = $access->get_teacher_all_visible_user_ids($USER->id);

$PAGE->set_context($context);
$PAGE->set_url('/blocks/download_certificates/index_teacher.php');
$PAGE->set_title(get_string('teacher_panel', 'block_download_certificates'));
$PAGE->set_heading(get_string('teacher_panel', 'block_download_certificates'));
$PAGE->set_pagelayout('standard');
$PAGE->requires->css('/blocks/download_certificates/styles.css');

// Récupération des données filtrées pour l'interface.
$query = new block_download_certificates_query();

$totalcount = empty($scope) ? 0 : $query->count_search_results_for_teacher($scope);
$courses    = $query->get_courses_with_certificates_for_teacher($scope);
$users      = $query->get_users_with_certificates_for_teacher($alluserids);

// Construction des données pour le template Mustache.
// On réutilise le même template que la page gestionnaire (main_page.mustache) mais
// avec les données filtrées et des URL pointant vers des handlers sécurisés.
$data = [
    'total_count'         => $totalcount,
    'has_certificates'    => ($totalcount > 0),
    'courses'             => $courses,
    'has_courses'         => !empty($courses),
    'users'               => $users,
    'has_users'           => !empty($users),
    'cohorts'             => [],
    'has_cohorts'         => false,
    'sesskey'             => sesskey(),
    'async_threshold'     => 30,
    'pending_tasks'       => [],
    // URL de téléchargement par cours (passera par download_course.php avec vérification enseignant).
    'download_course_url' => new moodle_url('/blocks/download_certificates/download_course.php'),
    // URL de téléchargement par utilisateur (passera par download_user.php).
    'download_user_url'   => new moodle_url('/blocks/download_certificates/download_user.php'),
    // URL de recherche AJAX → passera par search_certificates.php (bifurqué manager/enseignant).
    'is_teacher_view'     => true,
];

// Initialisation du module JS pour le tableau (même module que la page gestionnaire).
$tableconfig = [
    'sesskey'      => sesskey(),
    'total_count'  => $totalcount,
    'is_teacher'   => true,
];
$PAGE->requires->js_call_amd('block_download_certificates/certificate_table', 'init', [$tableconfig]);

// Chaînes pour les modules JS.
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

// Bandeau d'information sur le périmètre de l'enseignant.
$coursenames = [];
foreach ($courses as $course) {
    $coursenames[] = $course['fullname'];
}

if (empty($courseids)) {
    echo $OUTPUT->notification(
        get_string('no_teacher_courses', 'block_download_certificates'),
        'warning'
    );
} else {
    // Afficher les informations sur les groupes si applicable.
    $groupinfo = [];
    foreach ($courseids as $courseid) {
        $groups = $access->get_teacher_groups_in_course($USER->id, $courseid);
        if (!empty($groups)) {
            $groupnames = array_map(fn($g) => format_string($g->name), $groups);
            $coursename = '';
            foreach ($courses as $c) {
                if ($c['id'] == $courseid) {
                    $coursename = $c['fullname'];
                    break;
                }
            }
            $groupinfo[] = $coursename . ' (' .
                           get_string('teacher_scope_groups', 'block_download_certificates',
                                      implode(', ', $groupnames)) . ')';
        }
    }

    // Rendu du template de la page enseignant.
    echo $OUTPUT->render_from_template('block_download_certificates/teacher_page', $data);
}

echo $OUTPUT->footer();
