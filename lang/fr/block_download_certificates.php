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
 * French language strings for block_download_certificates plugin.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accessdenied'] = 'Accès refusé.';
$string['all_groups'] = 'Tous les groupes';
$string['async_can_close'] = 'Vous pouvez fermer cette fenêtre et continuer à naviguer. L\'archive continuera à se générer et sera prête à votre retour sur cette page.';
$string['async_download'] = 'Télécharger';
$string['async_expired'] = 'Ce téléchargement a expiré. Demandez-le à nouveau.';
$string['async_generating'] = 'Génération de l\'archive ZIP';
$string['async_polling_stopped'] = 'La progression de ce téléchargement ne peut plus être suivie ici ; l\'archive continue d\'être préparée. Rechargez la page plus tard pour la retrouver.';
$string['async_preparing'] = 'Préparation de votre téléchargement...';
$string['async_processing'] = 'Traitement des certificats en cours...';
$string['async_ready'] = 'Votre archive ZIP est prête ! Le téléchargement va démarrer automatiquement.';
$string['async_zip_ready'] = 'Une archive ZIP de certificats est prête au téléchargement.';
$string['async_zip_ready_label_all'] = 'Votre archive ZIP de tous les certificats est prête au téléchargement.';
$string['async_zip_ready_label_cohort'] = 'Votre archive ZIP des certificats de la cohorte est prête au téléchargement.';
$string['async_zip_ready_label_course'] = 'Votre archive ZIP des certificats du cours est prête au téléchargement.';
$string['async_zip_ready_label_range'] = 'Votre archive ZIP des certificats (plage de dates) est prête au téléchargement.';
$string['async_zip_ready_label_user'] = 'Votre archive ZIP des certificats de l\'utilisateur est prête au téléchargement.';
$string['cannotcreatezipfile'] = 'Impossible de créer le fichier ZIP.';
$string['certificate_list'] = 'Certificats';
$string['certificate_management'] = 'Gestion des certificats';
$string['certificate_summary'] = 'Résumé des certificats';
$string['certificatenotfound'] = 'Certificat non trouvé.';
$string['certificates'] = 'certificats';
$string['choose_cohort'] = 'Choisir une cohorte...';
$string['choose_course'] = '-- Choisir un cours --';
$string['choose_group'] = 'Choisir un groupe';
$string['choose_user'] = 'Choisir un utilisateur...';
$string['clear_search'] = 'Effacer la recherche';
$string['cohort_download_help'] = 'Sélectionnez une cohorte pour télécharger tous les certificats de ses membres.';
$string['cohortnotselected'] = 'Aucune cohorte sélectionnée.';
$string['confirm_download_all'] = 'Êtes-vous sûr de vouloir télécharger tous les certificats ? Cela peut prendre un certain temps.';
$string['course_certificate_summary'] = 'Certificats de ce cours';
$string['course_download_help'] = 'Sélectionnez un cours pour télécharger tous les certificats émis pour ce cours.';
$string['coursenotselected'] = 'Veuillez sélectionner un cours.';
$string['courses'] = 'Cours';
$string['date_created'] = 'Date de création';
$string['date_range_help'] = 'Sélectionnez une plage de dates pour télécharger tous les certificats émis durant cette période.';
$string['download_all_certificates'] = 'Télécharger tous les certificats';
$string['download_all_quick'] = 'Tout télécharger';
$string['download_certificate'] = 'Télécharger le certificat';
$string['download_certificates:addinstance'] = 'Ajouter un nouveau bloc de téléchargement de certificats';
$string['download_certificates:downloadcourse'] = 'Télécharger les certificats des participants du cours';
$string['download_certificates:manage'] = 'Gérer le téléchargement de certificats';
$string['download_certificates:myaddinstance'] = 'Ajouter un nouveau bloc de téléchargement de certificats au tableau de bord';
$string['download_certificates:view'] = 'Voir le bloc de téléchargement de certificats';
$string['download_cohort_certificates'] = 'Télécharger les certificats de la cohorte';
$string['download_course_certificates'] = 'Télécharger les certificats du cours';
$string['download_my_certificates'] = 'Télécharger mes certificats';
$string['download_named'] = 'Télécharger {$a}';
$string['download_range'] = 'Télécharger la plage';
$string['download_user_certificates'] = 'Télécharger les certificats';
$string['end_date'] = 'Date de fin';
$string['error_loading_block'] = 'Erreur lors du chargement des données de certificats';
$string['event_zip_requested'] = 'Téléchargement ZIP de certificats demandé';
$string['filenotfound'] = 'Fichier non trouvé.';
$string['invalidaction'] = 'Action non valide.';
$string['invalidaterange'] = 'Plage de dates invalide. Assurez-vous que la date de début est antérieure à la date de fin.';
$string['invalidparameters'] = 'Paramètres non valides.';
$string['invalidtype'] = 'Type de téléchargement invalide.';
$string['manage_certificates'] = 'Gérer les certificats';
$string['more_certificates'] = 'Et {$a} de plus';
$string['my_certificates'] = 'Mes certificats';
$string['my_certificates_count'] = 'Mes certificats';
$string['my_course_certificates_count'] = 'Mes certificats dans ce cours';
$string['no_certificates_course'] = 'Vous n\'avez pas encore de certificats dans ce cours.';
$string['no_certificates_description'] = 'Aucun certificat n\'est disponible au téléchargement pour le moment.';
$string['no_certificates_found'] = 'Aucun certificat trouvé';
$string['no_certificates_profile'] = 'Cet utilisateur n\'a pas encore de certificats.';
$string['no_certificates_user'] = 'Vous n\'avez pas encore de certificats.';
$string['no_search_results'] = 'Aucun certificat trouvé correspondant à votre recherche.';
$string['nocertificates'] = 'Aucun certificat trouvé à télécharger.';
$string['nopdf'] = 'Ce certificat n\'a pas de fichier PDF.';
$string['pluginname'] = 'Téléchargement de certificats';
$string['privacy:metadata:block_download_certificates_tasks'] = 'Les demandes de téléchargement d\'archives ZIP de certificats effectuées par un utilisateur.';
$string['privacy:metadata:block_download_certificates_tasks:error'] = 'Le message d\'erreur enregistré lorsque le téléchargement a échoué.';
$string['privacy:metadata:block_download_certificates_tasks:filepath'] = 'Une colonne des versions upstream, l\'emplacement sur le serveur d\'une archive ZIP générée ; ce plugin la laisse vide et la mise à niveau la vide.';
$string['privacy:metadata:block_download_certificates_tasks:params'] = 'Le filtre choisi pour le téléchargement, comme le cours, l\'utilisateur, la cohorte ou la période.';
$string['privacy:metadata:block_download_certificates_tasks:status'] = 'L\'état de la demande de téléchargement.';
$string['privacy:metadata:block_download_certificates_tasks:timecreated'] = 'La date de la demande de téléchargement.';
$string['privacy:metadata:block_download_certificates_tasks:timemodified'] = 'La date de la dernière mise à jour de la demande de téléchargement.';
$string['privacy:metadata:block_download_certificates_tasks:type'] = 'Le type de téléchargement demandé (tout, cours, utilisateur, cohorte ou période).';
$string['privacy:metadata:block_download_certificates_tasks:userid'] = 'L\'identifiant de l\'utilisateur qui a demandé le téléchargement.';
$string['privacy:path:tasks'] = 'Demandes de téléchargement de certificats';
$string['profile_certificates_count'] = 'Certificats de cet utilisateur';
$string['recent_7days'] = '7 derniers jours';
$string['requestfailed'] = 'La requête n\'a pas pu aboutir. Veuillez réessayer plus tard.';
$string['result_count'] = '{$a->first}-{$a->last} sur {$a->total} certificats';
$string['rows_per_page'] = 'Certificats par page';
$string['search_certificates'] = 'Rechercher des certificats';
$string['sourceunavailable'] = 'Le plugin de certificats {$a} n\'est pas disponible.';
$string['start_date'] = 'Date de début';
$string['tab_cohort'] = 'Par cohorte';
$string['tab_course'] = 'Par cours';
$string['tab_date_range'] = 'Par dates';
$string['tab_user'] = 'Par utilisateur';
$string['task_cleanup_expired'] = 'Nettoyage des tâches de téléchargement de certificats expirées';
$string['task_generate_zip'] = 'Génération de l\'archive ZIP des certificats';
$string['taskerror_inactiveuser'] = 'Le compte qui a demandé ce téléchargement est supprimé ou suspendu.';
$string['taskerror_noaccess'] = 'Les certificats de ce téléchargement ne sont plus accessibles au compte qui l\'a demandé.';
$string['taskerror_timeout'] = 'La préparation du téléchargement a pris trop de temps et a été interrompue.';
$string['taskerror_unexpected'] = 'Le téléchargement n\'a pas pu être préparé à cause d\'une erreur inattendue.';
$string['tasknotfound'] = 'Tâche de téléchargement non trouvée.';
$string['tasknotready'] = 'La tâche de téléchargement n\'est pas encore prête.';
$string['taskrefsunreadable'] = 'La liste des certificats de ce téléchargement n\'a pas pu être lue.';
$string['teacher_certificates'] = 'Certificats de vos étudiants';
$string['teacher_no_certificates'] = 'Aucun certificat trouvé dans votre périmètre.';
$string['teacher_panel'] = 'Panneau Enseignant';
$string['template'] = 'Modèle';
$string['toomanyforsync'] = 'Cette sélection compte {$a->count} certificats, plus que les {$a->limit} téléchargeables en une seule fois. Utilisez le téléchargement en arrière-plan.';
$string['total'] = 'Total';
$string['total_certificates'] = 'Total des certificats';
$string['type'] = 'Type';
$string['unknown'] = 'Inconnu';
$string['user_download_help'] = 'Sélectionnez un utilisateur pour télécharger tous ses certificats sous forme de fichier ZIP.';
$string['zip_error_line'] = 'Certificat {$a->type} {$a->issueid} : {$a->reason}';
$string['zip_error_outofscope'] = 'n\'est plus accessible au compte qui a demandé le téléchargement';
$string['zip_error_unexpected'] = 'erreur inattendue lors de l\'ajout du certificat';
