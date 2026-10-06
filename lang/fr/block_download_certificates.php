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
 * @copyright 2025 E-learning Touch' contact@elearningtouch.com (Maintainer)
 * @author    Thomas Clément 222384061+ClementThomasELT@users.noreply.github.com (Coder)
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Téléchargement de certificats';
$string['download_certificates:view'] = 'Voir le bloc de téléchargement de certificats';
$string['download_certificates:manage'] = 'Gérer le téléchargement de certificats';
$string['download_certificates:addinstance'] = 'Ajouter un nouveau bloc de téléchargement de certificats';
$string['download_certificates:myaddinstance'] = 'Ajouter un nouveau bloc de téléchargement de certificats au tableau de bord';
$string['downloadcertificate'] = 'Télécharger le certificat';

// Block specific strings.
$string['certificate_summary'] = 'Résumé des certificats';
$string['total'] = 'Total';
$string['courses'] = 'Cours';
$string['recent_7days'] = '7 derniers jours';
$string['manage_certificates'] = 'Gérer les certificats';
$string['download_all_quick'] = 'Tout télécharger';
$string['error_loading_block'] = 'Erreur lors du chargement des données de certificats';

// Main page strings.
$string['certificate_management'] = 'Gestion des certificats';
$string['download_all_certificates'] = 'Télécharger tous les certificats';
$string['confirm_download_all'] = 'Êtes-vous sûr de vouloir télécharger tous les certificats ? Cela peut prendre un certain temps.';
$string['total_certificates'] = 'Total des certificats';
$string['template'] = 'Modèle';
$string['code'] = 'Code';
$string['date_created'] = 'Date de création';
$string['download_certificate'] = 'Télécharger le certificat';
$string['no_certificates_found'] = 'Aucun certificat trouvé';
$string['no_certificates_description'] = 'Aucun certificat n\'est disponible au téléchargement pour le moment.';
$string['unknown'] = 'Inconnu';

// Error messages.
$string['nocertificates'] = 'Aucun certificat trouvé à télécharger.';
$string['cannotcreatezipfile'] = 'Impossible de créer le fichier ZIP.';
$string['novalidcertificates'] = 'Aucun certificat valide n\'a pu être téléchargé.';
$string['filenotfound'] = 'Fichier non trouvé.';
$string['certificatenotfound'] = 'Certificat non trouvé.';

// User-specific certificate strings.
$string['my_certificates'] = 'Mes certificats';
$string['my_certificates_count'] = 'Mes certificats';
$string['download_my_certificates'] = 'Télécharger mes certificats';
$string['no_certificates_user'] = 'Vous n\'avez pas encore de certificats.';
$string['nocertificatesuser'] = 'Aucun certificat trouvé pour cet utilisateur.';
$string['novalidcertificatesuser'] = 'Aucun certificat valide n\'a pu être téléchargé pour cet utilisateur.';

// Settings strings.
$string['enable'] = 'Activer le plugin';
$string['enable_desc'] = 'Activer ou désactiver la fonctionnalité de téléchargement de certificats.';
$string['max_certificates_display'] = 'Nombre maximum de certificats à afficher';
$string['max_certificates_display_desc'] = 'Nombre maximum de certificats à afficher dans le résumé du bloc.';
$string['filename_format'] = 'Format du nom de fichier';
$string['filename_format_desc'] = 'Format pour les noms de fichiers de certificats. Paramètres disponibles : {fullname}, {course}, {date}, {userid}.';
$string['managecertificates'] = 'Gérer les certificats';

// Legacy strings.
$string['certificate'] = 'Certificat';
$string['nocertificate'] = 'Aucun certificat disponible';
$string['certificategenerated'] = 'Certificat généré avec succès';
$string['certificateerror'] = 'Erreur lors de la génération du certificat';

// Privacy.
$string['privacy:metadata'] = 'Le plugin Téléchargement de certificats ne stocke aucune donnée personnelle.';

// Date range download strings.
$string['download_by_date_range'] = 'Télécharger par plage de dates';
$string['date_range_help'] = 'Sélectionnez une plage de dates pour télécharger tous les certificats émis durant cette période.';
$string['start_date'] = 'Date de début';
$string['end_date'] = 'Date de fin';
$string['download_range'] = 'Télécharger la plage';
$string['novalidcertificatesinrange'] = 'Aucun certificat valide trouvé dans la plage de dates spécifiée.';

// Course download strings.
$string['download_by_course'] = 'Télécharger par cours';
$string['course_download_help'] = 'Sélectionnez un cours pour télécharger tous les certificats émis pour ce cours.';
$string['select_course'] = 'Sélectionner un cours';
$string['choose_course'] = '-- Choisir un cours --';
$string['download_course_certificates'] = 'Télécharger les certificats du cours';
$string['no_courses_with_certificates'] = 'Aucun cours avec des certificats trouvé.';
$string['certificates'] = 'certificats';
$string['coursenotfound'] = 'Cours non trouvé.';
$string['nocertificatesforcourse'] = 'Aucun certificat trouvé pour ce cours.';
$string['novalidcertificatesforcourse'] = 'Aucun certificat valide n\'a pu être téléchargé pour ce cours.';
$string['coursenotselected'] = 'Veuillez sélectionner un cours.';

// User download strings.
$string['download_by_user'] = 'Télécharger par utilisateur';
$string['user_download_help'] = 'Sélectionnez un utilisateur pour télécharger tous ses certificats sous forme de fichier ZIP.';
$string['select_user'] = 'Sélectionner un utilisateur';
$string['choose_user'] = 'Choisir un utilisateur...';
$string['download_user_certificates'] = 'Télécharger les certificats';
$string['no_users_with_certificates'] = 'Aucun utilisateur avec des certificats trouvé.';
$string['cannotdownloadusercertificates'] = 'Impossible de télécharger les certificats de l\'utilisateur.';

// Precise download strings.
$string['download_precise'] = 'Téléchargement précis';
$string['precise_download_help'] = 'Téléchargez des certificats spécifiques en sélectionnant des éléments individuels.';
$string['select_certificates'] = 'Sélectionner des certificats';
$string['download_selected'] = 'Télécharger la sélection';
$string['nocertificatesselected'] = 'Aucun certificat sélectionné.';
$string['novalidcertificatesselected'] = 'Aucun certificat valide n\'a pu être téléchargé à partir de la sélection.';

// Date range validation and progress strings.
$string['invalidaterange'] = 'Plage de dates invalide. Assurez-vous que la date de début est antérieure à la date de fin.';
$string['downloadinprogress'] = 'Téléchargement en cours...';

// Cohort download strings.
$string['download_by_cohort'] = 'Télécharger par cohorte';
$string['cohort_download_help'] = 'Sélectionnez une cohorte pour télécharger tous les certificats de ses membres.';
$string['select_cohort'] = 'Sélectionner une cohorte';
$string['choose_cohort'] = 'Choisir une cohorte...';
$string['members'] = 'membres';
$string['download_cohort_certificates'] = 'Télécharger les certificats de la cohorte';
$string['no_cohorts_with_certificates'] = 'Aucune cohorte avec des certificats trouvée.';
$string['cohortnotselected'] = 'Aucune cohorte sélectionnée.';
$string['nocohortmembers'] = 'Aucun membre trouvé dans cette cohorte.';
$string['nocertificatescohort'] = 'Aucun certificat trouvé pour les membres de cette cohorte.';
$string['novalidcertificatescohort'] = 'Aucun certificat valide n\'a pu être téléchargé pour cette cohorte.';

// Customcert specific strings.
$string['customcert_certificate'] = 'Certificat personnalisé';
$string['customcert_not_available'] = 'Le plugin Certificat personnalisé n\'est pas disponible.';

$string['cannotdownloadcertificate'] = 'Impossible de télécharger le certificat. Veuillez réessayer plus tard ou contacter un administrateur.';

// Async download strings.
$string['task_generate_zip'] = 'Génération de l\'archive ZIP des certificats';
$string['task_cleanup_expired'] = 'Nettoyage des tâches de téléchargement de certificats expirées';
$string['async_generating'] = 'Génération de l\'archive ZIP';
$string['async_preparing'] = 'Préparation de votre téléchargement...';
$string['async_processing'] = 'Traitement des certificats en cours...';
$string['async_ready'] = 'Votre archive ZIP est prête ! Le téléchargement va démarrer automatiquement.';
$string['async_zip_ready'] = 'Une archive ZIP de certificats est prête au téléchargement.';
$string['async_download_started'] = 'Votre téléchargement est en cours de préparation en arrière-plan. Vous pouvez continuer à naviguer.';
$string['tasknotfound'] = 'Tâche de téléchargement non trouvée.';
$string['tasknotready'] = 'La tâche de téléchargement n\'est pas encore prête.';
$string['accessdenied'] = 'Accès refusé.';
$string['invalidtype'] = 'Type de téléchargement invalide.';
$string['nocertificatesinrange'] = 'Aucun certificat trouvé dans la plage de dates spécifiée.';
$string['async_close'] = 'Fermer';
$string['async_download'] = 'Télécharger';
$string['async_can_close'] = 'Vous pouvez fermer cette fenêtre et continuer à naviguer. L\'archive continuera à se générer et sera prête à votre retour sur cette page.';
$string['async_zip_ready_label_all'] = 'Votre archive ZIP de tous les certificats est prête au téléchargement.';
$string['async_zip_ready_label_course'] = 'Votre archive ZIP des certificats du cours est prête au téléchargement.';
$string['async_zip_ready_label_user'] = 'Votre archive ZIP des certificats de l\'utilisateur est prête au téléchargement.';
$string['async_zip_ready_label_cohort'] = 'Votre archive ZIP des certificats de la cohorte est prête au téléchargement.';
$string['async_zip_ready_label_range'] = 'Votre archive ZIP des certificats (plage de dates) est prête au téléchargement.';

// Table and search strings.
$string['type'] = 'Type';
$string['no_search_results'] = 'Aucun certificat trouvé correspondant à votre recherche.';
$string['search_placeholder'] = 'Rechercher par nom, email, cours, modèle...';
$string['per_page'] = 'Par page';
$string['showing_results'] = 'Affichage {$a->start}-{$a->end} sur {$a->total}';

// Tab labels (short).
$string['tab_date_range'] = 'Par dates';
$string['tab_course'] = 'Par cours';
$string['tab_cohort'] = 'Par cohorte';
$string['tab_user'] = 'Par utilisateur';

// Chaînes pour l'interface enseignant.
$string['teacher_panel'] = 'Panneau Enseignant';
$string['teacher_scope_courses'] = 'Périmètre : vos cours';
$string['teacher_scope_groups'] = 'Périmètre : vos groupes ({$a})';
$string['no_teacher_courses'] = 'Vous n\'avez aucun cours en tant qu\'enseignant.';
$string['teacher_certificates'] = 'Certificats de vos étudiants';
$string['access_denied_certificate'] = 'Accès refusé : ce certificat n\'est pas dans votre périmètre.';
$string['teacher_no_certificates'] = 'Aucun certificat trouvé dans votre périmètre.';
$string['all_groups'] = 'Tous les groupes';
$string['choose_group'] = 'Choisir un groupe';
$string['invalidgroup'] = 'Groupe invalide ou inaccessible.';

