# Documentation Technique - block_download_certificates

## Architecture du code

### Structure des fichiers et dossiers

```
blocks/download_certificates/
├── amd/src/
│   ├── async_download.js      # Module JS pour le traitement asynchrone
│   └── certificate_table.js   # Module JS pour le tableau dynamique
├── classes/
│   ├── certificate_access.php     # Gestion des permissions et périmètres
│   ├── certificate_packager.php   # Création ZIP et envoi de fichiers
│   ├── certificate_query.php      # Requêtes SQL pour les certificats
│   ├── certificate_retriever.php  # Récupération du contenu des fichiers PDF
│   ├── controller.php             # Contrôleur principal (Facade)
│   ├── observer.php               # Observateur d'événements Moodle
│   ├── privacy/provider.php       # Sous-système de confidentialité
│   └── task/
│       ├── cleanup_expired.php    # Tâche planifiée de nettoyage
│       └── generate_zip.php       # Tâche adhoc de génération ZIP
├── db/
│   ├── access.php             # Définition des capacités
│   ├── events.php             # Observateurs d'événements
│   ├── install.php            # Post-installation
│   ├── install.xml            # Schéma de base de données
│   ├── tasks.php              # Tâches planifiées
│   └── upgrade.php            # Migrations de version
├── lang/
│   ├── en/                    # Chaînes de langue anglaises
│   └── fr/                    # Chaînes de langue françaises
├── templates/
│   ├── main_page.mustache     # Template page gestionnaire
│   └── teacher_page.mustache  # Template page enseignant
├── ajax/
│   ├── create_task.php            # Création de tâche asynchrone
│   ├── download_zip.php           # Téléchargement de l'archive ZIP
│   ├── get_groups_for_course.php  # Récupération des groupes d'un cours
│   ├── search_certificates.php    # Recherche et pagination AJAX
│   └── task_status.php            # Statut d'une tâche
├── cli/
│   ├── create_test_groups.php           # Création de groupes de test
│   ├── generate_test_certificates.php   # Génération de certificats de test
│   ├── generate_test_certificates_customcert.php
│   ├── purge_all_certificates.php       # Purge complète des certificats
│   └── test_customcert_pdf.php          # Test PDF customcert
├── block_download_certificates.php  # Définition du bloc
├── index.php                      # Page principale gestionnaire
├── index_teacher.php              # Page principale enseignant
├── lib.php                        # Fonctions bibliothèque
├── download.php                   # Handler de téléchargement principal
├── download_course.php            # Téléchargement par cours
├── download_cohort.php            # Téléchargement par cohorte
├── download_user.php              # Téléchargement par utilisateur
├── download_range.php             # Téléchargement par plage de dates
├── download_precise.php           # Téléchargement individuel
├── ajax.php                       # Endpoint AJAX générique
├── styles.css                     # Feuilles de style
└── composer.json                  # Configuration Composer/PSR-4
```

### Pattern architectural

Le plugin suit un pattern **Facade** avec séparation des responsabilités :

- **Controller** (`classes/controller.php`) : Point d'entrée unique qui délègue aux classes spécialisées
- **Query** (`classes/certificate_query.php`) : Toutes les requêtes SQL vers les tables de certificats
- **Retriever** (`classes/certificate_retriever.php`) : Récupération du contenu PDF (storage + HTTP fallback)
- **Packager** (`classes/certificate_packager.php`) : Création des archives ZIP et envoi des fichiers
- **Access** (`classes/certificate_access.php`) : Gestion des permissions et calcul du périmètre enseignant

## Compilation JS/CSS

### Modules AMD

Le plugin utilise deux modules JavaScript AMD :

**async_download.js** :
- Intercepte les soumissions de formulaire pour les téléchargements par lots
- Crée des tâches asynchrones via AJAX lorsque le seuil de 30 certificats est dépassé
- Affiche une modale de progression initiale
- Maintient une bannière persistante en haut de page
- Polling toutes les 3 secondes pour vérifier l'état de la tâche
- Gestion du localStorage pour mémoriser les bannières fermées

**certificate_table.js** :
- Charge dynamiquement les certificats via AJAX
- Gère la recherche avec debounce (300ms)
- Implémente le tri par colonne
- Gère la pagination
- Initialise l'autocomplétion Moodle sur les sélecteurs
- Affiche des badges colorés par type de certificat

### Commandes de build

Aucune compilation n'est requise. Les fichiers JavaScript sont en vanilla JS compatible avec le requirejs de Moodle. Pour la minification en production :

```bash
# Grunt (si configuré dans Moodle)
grunt amd

# Ou manuellement avec uglify-js
uglifyjs amd/src/async_download.js -o amd/build/async_download.min.js
uglifyjs amd/src/certificate_table.js -o amd/build/certificate_table.min.js
```

### CSS

Le fichier `styles.css` contient tous les styles nécessaires. Aucune pré-compilation (Sass/Less) n'est configurée. Pour la production, utilisez le système de cache CSS de Moodle :

```bash
php admin/cli/purge_caches.php
```

## Services Web (API)

Le plugin n'expose pas de services web externes. Toutes les interactions se font via :
- Pages PHP traditionnelles
- Endpoints AJAX internes (`/blocks/download_certificates/ajax/*.php`)

### Endpoints AJAX

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `ajax/create_task.php` | POST | Crée une tâche de téléchargement asynchrone |
| `ajax/task_status.php` | GET | Récupère le statut d'une tâche |
| `ajax/download_zip.php` | GET | Télécharge l'archive ZIP générée |
| `ajax/search_certificates.php` | GET | Recherche et pagination des certificats |
| `ajax/get_groups_for_course.php` | GET | Récupère les groupes d'un cours pour un enseignant |

## Événements Moodle

### Observateurs configurés (`db/events.php`)

```php
$observers = [
    [
        'eventname' => '\core\event\course_completed',
        'callback' => 'block_download_certificates_observer::course_completed',
    ],
];
```

L'observateur `course_completed` est défini mais actuellement minimal. Il peut être étendu pour déclencher des actions lors de la complétion d'un cours.

## Tâches programmées

### Tâche planifiée : cleanup_expired

**Classe** : `block_download_certificates\task\cleanup_expired`

**Fréquence** : Toutes les heures (minute 0, heure *)

**Fonction** :
- Supprime les tâches "ready" de plus de 24 heures (et leurs fichiers ZIP)
- Supprime les tâches "failed" de plus de 7 jours
- Marque comme "failed" les tâches "processing" bloquées depuis plus de 2 heures
- Supprime les tâches "expired"

**Fichier** : `classes/task/cleanup_expired.php`

### Tâche adhoc : generate_zip

**Classe** : `block_download_certificates\task\generate_zip`

**Déclenchement** : Manuel via `core\task\manager::queue_adhoc_task()`

**Fonction** :
- Traite les certificats par lots de 500 unités (BATCH_SIZE)
- Ré-ordonnance automatiquement la tâche suivante si des certificats restent à traiter
- Met à jour la progression dans la table `block_download_cert_tasks`
- Ajoute un fichier `download_errors.txt` dans le ZIP en cas d'erreurs

**Fichier** : `classes/task/generate_zip.php`

## Structure de la base de données

### Table `{prefix}_block_download_cert_tasks`

Cette table suit les tâches de téléchargement asynchrone de certificats.

| Colonne | Type | Null | Défaut | Description |
|---------|------|------|--------|-------------|
| id | BIGINT(10) | NOT NULL | AUTO_INCREMENT | Clé primaire |
| userid | INT(10) | NOT NULL | | Utilisateur ayant demandé le téléchargement |
| type | VARCHAR(50) | NOT NULL | | Type de téléchargement : all, course, user, cohort, range |
| params | TEXT | NULL | | Paramètres JSON du filtre de téléchargement |
| status | VARCHAR(20) | NOT NULL | 'pending' | Statut : pending, processing, ready, failed, expired |
| progress | INT(10) | NOT NULL | 0 | Nombre de certificats traités |
| total | INT(10) | NOT NULL | 0 | Nombre total de certificats à traiter |
| filepath | VARCHAR(255) | NULL | | Chemin vers le fichier ZIP généré |
| timecreated | INT(10) | NOT NULL | 0 | Timestamp de création |
| timemodified | INT(10) | NOT NULL | 0 | Timestamp de dernière modification |
| error | TEXT | NULL | | Message d'erreur si la tâche a échoué |
| batchoffset | INT(10) | NOT NULL | 0 | Offset actuel pour le traitement par lots |

<details>
<summary>Requête SQL CREATE TABLE</summary>

```sql
CREATE TABLE {prefix}_block_download_cert_tasks (
    id BIGINT(10) NOT NULL AUTO_INCREMENT,
    userid INT(10) NOT NULL,
    type VARCHAR(50) NOT NULL,
    params TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    progress INT(10) NOT NULL DEFAULT 0,
    total INT(10) NOT NULL DEFAULT 0,
    filepath VARCHAR(255) NULL,
    timecreated INT(10) NOT NULL DEFAULT 0,
    timemodified INT(10) NOT NULL DEFAULT 0,
    error TEXT NULL,
    batchoffset INT(10) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY userid (userid),
    KEY status_idx (status),
    KEY userid_status_idx (userid, status),
    CONSTRAINT {prefix}_block_download_cert_tasks_userid FOREIGN KEY (userid) REFERENCES {prefix}_user (id)
);
```

</details>

### Index définis

- `status_idx` : Index sur la colonne `status` pour les requêtes de filtrage par statut
- `userid_status_idx` : Index composite sur `userid` et `status` pour les requêtes de l'utilisateur courant

## Dépendances techniques

### Plugins Moodle requis (au moins un)

Le plugin nécessite qu'au moins l'un des plugins de certification suivants soit installé :

| Plugin | Table(s) | Composant |
|--------|----------|-----------|
| tool_certificate | `tool_certificate_issues`, `tool_certificate_templates` | core |
| mod_customcert | `customcert_issues`, `customcert`, `customcert_templates` | mod_customcert |
| mod_certificate | `certificate_issues`, `certificate` | mod_certificate |
| mod_simplecertificate | `simplecertificate_issues` | mod_simplecertificate |
| mod_certificatebeautiful | `certificatebeautiful_issue`, `certificatebeautiful` | mod_certificatebeautiful |

### Extensions PHP requises

- **ZipArchive** : Requis pour la création des archives ZIP (activé par défaut)
- **JSON** : Requis pour l'encodage/décodage des paramètres (activé par défaut)

### Versionnement

Informations extraites de `version.php` :

```php
$plugin->component = 'block_download_certificates';
$plugin->version = 2026052800;        // Build YYYYMMDDXX
$plugin->requires = 2022112800;       // Moodle 4.1 minimum
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.5.0';
```

### Capacités (db/access.php)

```php
$capabilities = [
    'block/download_certificates:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'guest' => CAP_PREVENT,
            'user' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    'block/download_certificates:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'block/download_certificates:addinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    'block/download_certificates:myaddinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],
];
```

## Classes principales

### block_download_certificates_access

Gère le périmètre d'accès des enseignants :
- `is_teacher($userid)` : Vérifie si un utilisateur est enseignant
- `get_teacher_course_ids($userid)` : Retourne les IDs des cours enseignés
- `get_teacher_visible_user_ids($userid)` : Calcule le périmètre de visibilité (cours + groupes)
- `require_teacher_access_to_certificate($certuserid, $courseid)` : Vérifie l'accès à un certificat

### block_download_certificates_query

Centralise toutes les requêtes SQL avec gestion d'erreurs :
- `get_tool_certificate_issues()` : Certificats tool_certificate
- `get_customcert_issues()` : Certificats customcert
- `get_mod_certificate_issues()` : Certificats mod_certificate
- `get_simplecertificate_issues()` : Certificats simplecertificate
- `get_certificatebeautiful_issues()` : Certificats certificatebeautiful
- `get_courses_with_certificates()` : Cours avec certificats
- `get_courses_with_certificates_for_teacher($scope)` : Cours avec certificats pour enseignant (filtrage par scope utilisateur)
- `get_cohorts_with_certificates()` : Cohortes avec certificats
- `get_users_with_certificates()` : Utilisateurs avec certificats

**Gestion d'erreurs** : Toutes les méthodes de requête SQL sont enveloppées dans des blocs `try/catch`. En cas d'échec de requête, la méthode retourne un tableau vide `[]` et logue l'erreur via `debugging()` avec un message explicite identifiant la méthode et la cause.

**Gestion de collation** : Depuis la version 2026052800, les méthodes `build_union_subqueries()` et `build_union_subqueries_for_teacher_scope()` appliquent une collation uniforme sur toutes les colonnes texte des requêtes UNION. Cette fonctionnalité récupère la collation de la base de données via `$DB->get_dbcollation()` et l'applique systématiquement sur :
- Les colonnes `cert_type` (cast explicite avec `CAST(... AS CHAR(50)) COLLATE ...`)
- Les colonnes `coursename`, `templatename`, `code`

Cette approche prévient les erreurs SQL "Illegal mix of collations" lorsque les tables de certificats ont des collations différentes (ex: utf8mb3 vs utf8mb4), un problème fréquent lors de migrations de base de données ou d'installations hétérogènes.

**Note sur `get_courses_with_certificates_for_teacher()`** : Cette méthode accepte désormais un tableau `$scope` où les clés sont les IDs de cours et les valeurs sont les tableaux d'IDs d'utilisateurs visibles par l'enseignant dans chaque cours. Elle retourne uniquement les cours ayant au moins un certificat accessible, avec un comptage total respectant le cloisonnement par groupes.

### block_download_certificates_retriever

Récupère le contenu des fichiers PDF :
- Priorité 1 : Accès direct au file storage via pathnamehash (~1ms)
- Priorité 2 : Recherche dans le file storage par component/filearea (~10ms)
- Priorité 3 : Téléchargement HTTP en dernier recours (~200-500ms)

### block_download_certificates_packager

Gère l'empaquetage et l'envoi avec gestion d'erreurs renforcée :
- Génération des noms de fichiers standardisés
- Création des archives ZIP
- Envoi des fichiers avec headers HTTP appropriés
- Gestion des collisions de noms de fichiers

**Gestion d'erreurs SQL** : Toutes les méthodes de récupération de certificats (`fetch_certificates_for_course()`, `fetch_certificates_for_user()`, `fetch_certificates_for_cohort()`, `fetch_certificates_for_range()`, `download_certificates_by_date_range()`, `download_certificates_by_course()`, `download_user_certificates()`, `download_cohort_certificates()`, `download_single_certificate()`, `fetch_certificates_for_course_teacher()`) enveloppent chaque requête SQL dans un bloc `try/catch`. En cas d'échec, l'erreur est loguée via `debugging()` avec un message identifiant la méthode et le type de certificat concerné, permettant au traitement de continuer avec les autres sources de certificats.

### block_download_certificates_controller

Facade principale :
- Initialise les trois classes déléguées (retriever, query, packager)
- Fournit une API unifiée pour les pages PHP
- Gère les téléchargements individuels et par lots

## Flux de traitement asynchrone

```
1. Utilisateur soumet un formulaire de téléchargement
2. async_download.js intercepte si count >= 30
3. AJAX vers ajax/create_task.php
4. Création d'un enregistrement dans block_download_cert_tasks
5. Queue d'une tâche adhoc generate_zip
6. La tâche traite par lots de 500 certificats
7. Chaque lot met à jour progress et batchoffset
8. Si des certificats restent, ré-queue de la tâche
9. Quand tout est traité, status = 'ready'
10. async_download.js poll via ajax/task_status.php
11. Quand ready, téléchargement via ajax/download_zip.php
12. Le fichier ZIP est supprimé après téléchargement
```

## Sécurité

- **sesskey** : Toutes les opérations de téléchargement requièrent une validation de sesskey
- **Capability checks** : Vérification systématique des permissions avant chaque opération
- **Fail-close** : En cas de doute sur les permissions, l'accès est refusé
- **Paramétrisation SQL** : Toutes les requêtes utilisent des requêtes paramétrées
- **Validation des inputs** : Tous les paramètres sont validés avec `required_param()` et `optional_param()`
- **Ownership check** : Les utilisateurs ne peuvent télécharger que leurs propres tâches

## Gestion d'erreurs et résilience

### Stratégie de gestion d'erreurs SQL

Depuis la version 2026052800, toutes les requêtes SQL du plugin sont protégées par des blocs `try/catch` pour garantir la résilience du système :

**Dans `classes/certificate_query.php`** :
- Chaque méthode de requête (`get_tool_certificate_issues()`, `get_customcert_issues()`, etc.) capture les exceptions SQL
- En cas d'erreur, la méthode retourne un tableau vide `[]` au lieu de propager l'exception
- L'erreur est loguée via `debugging()` avec un message identifiant la méthode échouée

**Dans `classes/certificate_packager.php`** :
- Toutes les méthodes de fetch (`fetch_certificates_for_course()`, `fetch_certificates_for_user()`, etc.) protègent chaque requête par type de certificat individuellement
- Si une requête pour un type de certificat échoue (ex: tool_certificate), les autres types continuent d'être traités
- Chaque échec est logué avec un message explicite : `debugging('nom_methode: type_certificat query failed: ' . $e->getMessage())`

**Avantages** :
- Le plugin reste fonctionnel même si une table de certificats est corrompue ou inaccessible
- Les administrateurs peuvent diagnostiquer les problèmes via les logs Moodle
- Aucun crash fatal ne bloque l'ensemble du processus de téléchargement

### Gestion de collation uniforme

Depuis la version 2026052800, les requêtes UNION dans `certificate_query.php` appliquent une collation uniforme pour prévenir les erreurs "Illegal mix of collations" :

**Implémentation** :
- Récupération de la collation de la base via `$DB->get_dbcollation()`
- Application systématique avec `COLLATE {$collation}` sur toutes les colonnes texte
- Cast explicite des littéraux : `CAST('tool_certificate' AS CHAR(50)) COLLATE ...`

**Colonnes concernées** :
- `cert_type` : Type de certificat (tool_certificate, customcert, etc.)
- `coursename` : Nom du cours
- `templatename` : Nom du modèle de certificat
- `code` : Code unique du certificat

**Méthodes impactées** :
- `build_union_subqueries()` : Requête principale tous certificats
- `build_union_subqueries_for_teacher_scope()` : Requête filtrée pour enseignants

**Cas d'usage** : Cette fonctionnalité est cruciale lorsque :
- La base de données utilise utf8mb4 mais certaines tables sont en utf8mb3
- Migration d'une ancienne installation Moodle vers une version récente
- Installation de plugins de certificats à des moments différents avec des collations par défaut différentes

## Configuration Composer

Le plugin inclut un fichier `composer.json` pour l'autoload PSR-4 :

```json
{
    "name": "elearningtouch/block_download_certificates",
    "description": "Plugin Moodle block_download_certificates",
    "type": "moodle-block",
    "license": "GPL-3.0-or-later",
    "autoload": {
        "psr-4": {
            "block_download_certificates\\": "classes/"
        }
    },
    "require": {},
    "extra": {
        "installer-name": "download_certificates"
    }
}
```

Cela permet d'utiliser les classes du plugin avec l'autoload standard de Composer si nécessaire dans des projets externes.