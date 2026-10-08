## [2026100700] - 2026-10-07

### Changed

- **Renommage de la table de suivi des taches** : `block_download_cert_tasks` devient `block_download_certificates_tasks` pour respecter le prefixe frankenstyle exige par le validateur de plugins (`In db/install.xml, failed to find table prefixes`). Etape de mise a niveau `2026100700` dans `db/upgrade.php` qui renomme la table existante, donnees conservees.
- **Compatibilite** : Moodle 4.3 minimum (`requires = 2023100900`, `supported = [403, 503]`). Le nouveau nom (33 caracteres) depasse la limite de 28 caracteres des noms de table de Moodle 4.1 et 4.2.

## [2026052800] - 2026-05-28

### Changed

- **Gestion de collation uniforme dans certificate_query.php** : Ajout de la récupération de collation via `$DB->get_dbcollation()` dans les méthodes `build_union_subqueries()` et `build_union_subqueries_for_teacher_scope()`. Application systématique de `COLLATE {$collation}` sur toutes les colonnes texte des requêtes UNION (`cert_type`, `coursename`, `templatename`, `code`). Cast explicite des littéraux avec `CAST('tool_certificate' AS CHAR(50)) COLLATE ...` pour tous les types de certificats (tool_certificate, customcert, mod_certificate, mod_simplecertificate, mod_certificatebeautiful).

- **Prévention des erreurs "Illegal mix of collations"** : Cette modification résout les incompatibilités de collation entre tables de certificats (ex: utf8mb3 vs utf8mb4) fréquentes lors de migrations de base de données ou d'installations hétérogènes de plugins de certification.

## [2026052800] - 2026-05-28

### Changed

- **Gestion d'erreurs SQL renforcée** : Ajout de blocs `try/catch` autour de toutes les requêtes SQL dans `classes/certificate_packager.php` (méthodes : `fetch_certificates_for_course()`, `fetch_certificates_for_user()`, `fetch_certificates_for_cohort()`, `fetch_certificates_for_range()`, `download_certificates_by_date_range()`, `download_certificates_by_course()`, `download_user_certificates()`, `download_cohort_certificates()`, `download_single_certificate()`, `fetch_certificates_for_course_teacher()`). Chaque type de certificat (tool_certificate, customcert, mod_certificate, simplecertificate, certificatebeautiful) est traité indépendamment : si une requête échoue, les autres continuent.

- **Résilience des requêtes dans certificate_query.php** : Les méthodes `get_tool_certificate_issues()`, `get_customcert_issues()`, `get_mod_certificate_issues()`, `get_simplecertificate_issues()`, `get_certificatebeautiful_issues()` retournent désormais un tableau vide `[]` en cas d'erreur SQL au lieu de propager l'exception. Toutes les erreurs sont loguées via `debugging()` avec un message explicite identifiant la méthode et la cause.

- **Debugging amélioré** : Chaque échec de requête génère un message de debug spécifique (ex: `fetch_certificates_for_course: tool_certificate query failed: ...`) permettant un diagnostic précis sans bloquer le traitement global.

## [2026052800] - 2026-05-28

### Changed

- **Numéro de version** : Mise à jour du build number dans `version.php` de `2026033100` à `2026052800`. Aucun changement fonctionnel ou technique associé à cette version.

## [2026033102] - 2026-03-31

### Changed

- **Refactoring `get_courses_with_certificates_for_teacher()`** : La méthode dans `classes/certificate_query.php` accepte maintenant un paramètre `$scope` (tableau associatif cours => userids) au lieu de `$courseids`. Le comptage des certificats respecte désormais strictement le cloisonnement par groupes : seuls les certificats des utilisateurs visibles par l'enseignant dans chaque cours sont comptabilisés. Simplification du format de retour (un seul `certificate_count` au lieu de compteurs par type de certificat).

- **Ajout de `composer.json`** : Fichier de configuration Composer ajouté à la racine du plugin. Autoload PSR-4 configuré pour le namespace `block_download_certificates\` pointant vers le dossier `classes/`. Permet l'intégration avec des outils externes compatibles Composer.

### Fixed

- **Comptage des certificats enseignants** : Correction du comptage qui incluait précédemment tous les certificats d'un cours enseigné, même ceux d'apprenants hors du périmètre de groupes de l'enseignant. Le nouveau calcul itère cours par cours et filtre par les userids du scope, garantissant que seuls les certificats accessibles sont comptés.

## [2026033101] - 2025-06-09

### Removed

- **Affichage du périmètre enseignant dans le bloc** : Suppression du code dans `block_download_certificates.php` (lignes 580-595) qui affichait la liste des cours enseignés dans le bloc tableau de bord. La requête DB pour récupérer les noms de cours et l'affichage HTML associé ont été retirés.

- **Bandeau de périmètre dans l'interface enseignant** : Suppression du bandeau d'information dans `templates/teacher_page.mustache` (lignes 34-40) qui affichait le badge "teacher_scope_courses". L'élément HTML contenant le badge badge-info a été retiré du template.

### Changed

- **Simplification de l'interface enseignant** : Le périmètre de visibilité des enseignants est toujours appliqué automatiquement par `classes/certificate_access.php`, mais n'est plus affiché explicitement dans l'interface. Les listes déroulantes de cours et d'utilisateurs restent filtrées selon le périmètre réel de l'enseignant.

# Changelog

All notable changes to the `block_download_certificates` plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Plugin versions follow the Moodle convention `YYYYMMDDXX`.

## [2026033100] - 2026-03-31

### Added

- **Interface de gestion complète** : Page principale (`index.php`) avec tableau dynamique chargé via AJAX, recherche en temps réel, tri par colonne et pagination configurable. Template Mustache `main_page.mustache` pour le rendu.

- **Traitement asynchrone des téléchargements** : Module JS `amd/src/async_download.js` qui intercepte les formulaires lorsque le nombre de certificats >= 30. Création de tâches via `ajax/create_task.php`, polling du statut via `ajax/task_status.php`, téléchargement via `ajax/download_zip.php`.

- **Table de suivi des tâches** : Table `block_download_cert_tasks` dans `db/install.xml` avec champs : userid, type, params (JSON), status, progress, total, filepath, timecreated, timemodified, error, batchoffset. Index sur status et userid+status.

- **Tâche planifiée de nettoyage** : Classe `block_download_certificates\task\cleanup_expired` exécutée toutes les heures. Supprime les tâches ready > 24h, failed > 7j, processing bloquées > 2h. Déclarée dans `db/tasks.php`.

- **Tâche adhoc de génération ZIP** : Classe `block_download_certificates\task\generate_zip` avec traitement par lots de 500 certificats (BATCH_SIZE). Ré-ordonnancement automatique via `core\task\manager::queue_adhoc_task()`. Ajout d'un fichier `download_errors.txt` dans le ZIP en cas d'erreurs.

- **Interface enseignant filtrée** : Page `index_teacher.php` avec périmètre de visibilité calculé par `classes/certificate_access.php`. Filtrage par cours enseignés et par groupes d'appartenance. Template `teacher_page.mustache`.

- **Support multi-plugins de certificats** : Requêtes SQL dans `classes/certificate_query.php` pour 5 types : tool_certificate_issues, customcert_issues, certificate_issues, simplecertificate_issues, certificatebeautiful_issue.

- **Récupération optimisée des PDF** : Classe `classes/certificate_retriever.php` avec 3 niveaux de priorité : pathnamehash direct (~1ms), recherche filestorage (~10ms), téléchargement HTTP fallback (~200-500ms).

- **Empaquetage ZIP standardisé** : Classe `classes/certificate_packager.php` avec génération de noms de fichiers : `Prénom_Nom_NomCertificat_NomCours.pdf`. Gestion des collisions avec suffixe numérique.

- **Bloc tableau de bord** : Fichier `block_download_certificates.php` avec 3 vues : gestionnaire (stats + actions), enseignant (périmètre filtré), apprenant (ses certificats uniquement).

- **Endpoints AJAX** : 5 endpoints dans `ajax/` : create_task.php, task_status.php, download_zip.php, search_certificates.php, get_groups_for_course.php.

- **Support bilingue** : Fichiers de langue `lang/en/block_download_certificates.php` et `lang/fr/block_download_certificates.php` avec 100+ chaînes traduites.

- **Scripts CLI de test** : 5 scripts dans `cli/` : create_test_groups.php, generate_test_certificates.php, generate_test_certificates_customcert.php, purge_all_certificates.php, test_customcert_pdf.php.

### Changed

- **Architecture modulaire** : Refactoring complet avec pattern Facade. Controller (`classes/controller.php`) délègue à Query, Retriever, Packager et Access.

- **Tableau dynamique AJAX** : Remplacement du chargement serveur par un module JS `amd/src/certificate_table.js` avec recherche debounce (300ms), tri client, pagination et autocomplétion Moodle.

- **Gestion des permissions enseignant** : Logique centralisée dans `classes/certificate_access.php` avec cache statique (`$teachercoursecache`, `$teacherscopecache`) pour éviter les requêtes répétées.

- **Seuil asynchrone** : Constante `ASYNC_THRESHOLD = 30` dans `async_download.js`. Configurable via la donnée passée depuis PHP.

### Fixed

- **Fuites de mémoire** : Appel à `gc_collect_cycles()` tous les 50 certificats dans `classes/certificate_packager.php` pendant la création ZIP.

- **Validation des sessions** : Ajout de `require_sesskey()` sur tous les endpoints de téléchargement et de création de tâche.

- **Accès enseignant aux groupes** : Endpoint `ajax/get_groups_for_course.php` qui retourne uniquement les groupes de l'enseignant dans le cours (pas tous les groupes du cours).

- **Expiration des archives** : Marquage des tâches comme 'expired' après téléchargement dans `ajax/download_zip.php` avec suppression physique du fichier ZIP.

### Removed

- **Aucune suppression** : Version initiale du plugin, aucune fonctionnalité retirée.