# Téléchargement de Certificats Moodle

## 🎯 Présentation du plugin

Le plugin **Téléchargement de Certificats** est un outil puissant conçu pour simplifier la gestion et le téléchargement des certificats de formation au sein de votre plateforme Moodle. Il s'adresse aux administrateurs, aux gestionnaires de formation, aux enseignants et aux apprenants qui souhaitent accéder facilement aux certificats délivrés par les différents modules de certification installés sur Moodle.

Ce plugin agit comme un point d'accès centralisé qui regroupe tous les certificats générés par les différents systèmes de certification compatibles avec Moodle. Que vous utilisiez le système natif de certificats de Moodle, le plugin Custom Certificate, Simple Certificate, Certificate Beautiful ou tout autre module de certification compatible, ce plugin vous permet de les retrouver, de les consulter et de les télécharger en un seul endroit.

**Valeur ajoutée principale** : Plus besoin de naviguer entre différents cours, différentes activités ou différents plugins pour récupérer les certificats. Une interface unique, intuitive et sécurisée vous permet d'accéder à l'ensemble des certificats de votre plateforme, avec des options de filtrage avancées et un téléchargement par lots pour gagner un temps précieux lors des opérations de masse.

Le plugin s'intègre parfaitement dans l'écosystème Moodle en respectant les rôles et permissions natifs. Chaque utilisateur ne voit que les certificats auxquels il a légitimement accès, garantissant ainsi la confidentialité des données et le respect des règles de gestion de votre organisation.

## ✨ Fonctionnalités principales

### Téléchargement individuel de certificats
Chaque certificat peut être téléchargé individuellement au format PDF. L'interface présente une liste complète des certificats avec des informations détaillées : nom de l'apprenant, adresse email, nom du cours, modèle de certificat utilisé, code unique du certificat et date de délivrance. Un clic suffit pour télécharger le fichier PDF correspondant.

### Téléchargement par lots en archive ZIP
Pour les besoins administratifs ou archivistiques, le plugin permet de télécharger plusieurs certificats simultanément dans une archive ZIP. Plusieurs critères de sélection sont disponibles :
- **Tous les certificats** : Export complet de l'ensemble des certificats de la plateforme
- **Par plage de dates** : Sélectionnez une période spécifique pour exporter uniquement les certificats délivrés durant cette fenêtre temporelle
- **Par cours** : Exportez tous les certificats associés à un cours particulier
- **Par cohorte** : Téléchargez les certificats de tous les membres d'une cohorte donnée
- **Par utilisateur** : Récupérez l'ensemble des certificats d'un apprenant spécifique

### Traitement asynchrone pour les gros volumes
Lorsque le nombre de certificats à télécharger dépasse 30 unités, le plugin bascule automatiquement en mode de traitement asynchrone. Une tâche est créée en arrière-plan, permettant à l'utilisateur de continuer à naviguer sur la plateforme pendant la génération de l'archive. Une notification apparaît lorsque l'archive est prête à être téléchargée.

### Interface de recherche et filtrage avancée
Une barre de recherche permet de filtrer rapidement les certificats par nom d'apprenant, adresse email, nom de cours ou modèle de certificat. La table de résultats est triable par colonne et paginée pour une navigation optimale, avec un choix du nombre de résultats affichés par page (25, 50, 100 ou 200).

### Gestion des permissions par rôle
Le plugin distingue trois niveaux d'accès :
- **Gestionnaires/Administrateurs** : Accès complet à tous les certificats de la plateforme
- **Enseignants** : Accès limité aux certificats des cours qu'ils enseignent, avec un filtrage supplémentaire basé sur les groupes si l'enseignant est membre de groupes spécifiques
- **Apprenants** : Accès uniquement à leurs propres certificats via un bloc simplifié sur leur tableau de bord

### Nomenclature standardisée des fichiers
Chaque certificat téléchargé reçoit un nom de fichier structuré et cohérent facilitant l'organisation et la recherche ultérieure. Le format suit le modèle : `Prénom_Nom_NomDuCertificat_NomDuCours.pdf`, garantissant une identification immédiate du contenu de chaque fichier.

### Nettoyage automatique des tâches expirées
Une tâche planifiée exécute automatiquement le nettoyage des archives temporaires et des enregistrements de tâches obsolètes. Les archives prêtes non téléchargées sont supprimées après 24 heures, les tâches échouées après 7 jours, et les tâches bloquées après 2 heures d'inactivité.

## 📋 Prérequis et Installation

### Configuration système requise
- **Version Moodle** : 4.1 minimum (build 2022112800)
- **Version PHP** : 7.4 ou supérieure (selon les exigences de votre version Moodle)
- **Extensions PHP requises** : ZipArchive (activée par défaut sur la plupart des installations)
- **Espace disque** : Suffisant pour stocker temporairement les archives ZIP générées

### Plugins de certificats compatibles
Le plugin fonctionne avec les systèmes de certification suivants (au moins l'un doit être installé) :
- Certificats natifs Moodle (tool_certificate)
- Custom Certificate (mod_customcert)
- Certificate (mod_certificate)
- Simple Certificate (mod_simplecertificate)
- Certificate Beautiful (mod_certificatebeautiful)

### Procédure d'installation

**Étape 1 : Téléchargement du plugin**
Téléchargez le dossier complet du plugin et placez-le dans le répertoire `/blocks/download_certificates/` de votre installation Moodle. Assurez-vous que la structure des fichiers est respectée.

**Étape 2 : Vérification des permissions**
Vérifiez que les permissions du système de fichiers permettent à Moodle de lire et d'exécuter les fichiers du plugin. Le serveur web doit avoir accès en lecture au dossier du plugin.

**Étape 3 : Installation via l'interface d'administration**
Connectez-vous en tant qu'administrateur et accédez à `Administration du site > Notifications`. Moodle détectera automatiquement le nouveau plugin et proposera de procéder à son installation. Suivez les instructions à l'écran pour compléter l'installation de la base de données.

**Étape 4 : Configuration des permissions**
Après l'installation, accédez à `Administration du site > Utilisateurs > Permissions > Définir les rôles` pour vérifier que les capacités du plugin sont correctement attribuées aux rôles concernés.

**Étape 5 : Ajout du bloc (optionnel)**
Pour que les apprenants puissent accéder rapidement à leurs certificats, ajoutez le bloc "Téléchargement de Certificats" au tableau de bord par défaut via `Administration du site > Apparence > Blocs > Configuration du tableau de bord`.

## ⚙️ Configuration (Administrateur)

### Activation du plugin
Le plugin peut être activé ou désactivé globalement depuis les paramètres d'administration. Lorsqu'il est désactivé, aucune fonctionnalité de téléchargement n'est accessible, mais les données existantes sont conservées.

### Capacités et permissions
Quatre capacités principales sont définies pour le plugin :

**Voir le bloc de téléchargement** : Permet à un utilisateur d'afficher le bloc sur son tableau de bord. Cette capacité est accordée par défaut à tous les utilisateurs connectés, y compris les étudiants, les enseignants et les gestionnaires.

**Gérer les téléchargements** : Capacité réservée aux administrateurs et gestionnaires. Elle donne accès à l'interface complète de gestion avec tous les modes de téléchargement et l'accès à l'ensemble des certificats de la plateforme.

**Ajouter une instance du bloc** : Permet d'ajouter le bloc à une page de cours. Cette capacité est accordée aux enseignants éditeurs et aux gestionnaires.

**Ajouter une instance au tableau de bord** : Permet aux utilisateurs d'ajouter le bloc à leur tableau de bord personnel. Cette capacité est accordée à tous les utilisateurs connectés.

### Seuil de traitement asynchrone
Par défaut, le basculement vers le traitement asynchrone s'active lorsque le nombre de certificats à télécharger atteint 30 unités. Ce seuil est conçu pour équilibrer performance et expérience utilisateur.

### Périmètre d'accès des enseignants
Les enseignants ne voient que les certificats des cours dans lesquels ils ont un rôle d'enseignement. Si un enseignant est membre de groupes spécifiques dans un cours, sa visibilité est restreinte aux seuls apprenants de ces groupes. Cette règle s'applique cours par cours de manière indépendante.

## 🎬 Scénario d'utilisation

### Scénario 1 : Export annuel pour archivage réglementaire
**Contexte** : Une organisation de formation doit archiver tous les certificats délivrés au cours de l'année civile pour conformité réglementaire.

**Besoin** : Télécharger l'intégralité des certificats émis entre le 1er janvier et le 31 décembre dans une archive unique.

**Étapes** :
1. L'administrateur accède à la page de gestion des certificats
2. Il sélectionne l'onglet "Par plage de dates"
3. Il définit la date de début (01/01/2024) et la date de fin (31/12/2024)
4. Il clique sur le bouton de téléchargement
5. Si le nombre de certificats dépasse 30, une barre de progression apparaît
6. L'administrateur peut fermer la fenêtre et revenir plus tard
7. Une notification indique que l'archive est prête
8. Il télécharge l'archive ZIP contenant tous les certificats nommés de manière standardisée

**Résultat** : Une archive ZIP organisée contenant tous les certificats de l'année, prête pour l'archivage.

### Scénario 2 : Enseignant récupérant les certificats de ses groupes
**Contexte** : Un enseignant gère plusieurs groupes dans un cours et souhaite récupérer uniquement les certificats de ses apprenants directs.

**Besoin** : Télécharger les certificats des apprenants dont il a la responsabilité pédagogique.

**Étapes** :
1. L'enseignant accède à l'interface enseignant du plugin
2. Il voit uniquement les cours dans lesquels il enseigne
3. Il sélectionne un cours dans la liste déroulante
4. Si l'enseignant appartient à des groupes, un sélecteur de groupe apparaît
5. Il choisit un groupe spécifique ou "Tous les groupes"
6. Il lance le téléchargement
7. Le système filtre automatiquement les certificats selon son périmètre d'accès

**Résultat** : L'enseignant obtient uniquement les certificats des apprenants de son périmètre, respectant les règles de confidentialité.

### Scénario 3 : Apprenant téléchargeant ses propres certificats
**Contexte** : Un apprenant a terminé plusieurs formations et souhaite rassembler tous ses certificats.

**Besoin** : Accéder et télécharger l'ensemble de ses certificats personnels.

**Étapes** :
1. L'apprenant se connecte à son tableau de bord Moodle
2. Le bloc "Téléchargement de Certificats" affiche le nombre de certificats disponibles
3. Il clique sur le bouton "Télécharger mes certificats"
4. Une archive ZIP contenant tous ses certificats est générée
5. Il télécharge l'archive sur son poste

**Résultat** : L'apprenant récupère facilement tous ses certificats en une seule opération.

## 🛠️ Guide d'utilisation Administrateur

### Installation et activation du plugin
1. Placez les fichiers du plugin dans le dossier `/blocks/download_certificates/`
2. Connectez-vous en tant qu'administrateur
3. Naviguez vers `Administration du site > Notifications`
4. Suivez la procédure d'installation automatique
5. Vérifiez que le plugin apparaît dans la liste des blocs installés

### Configuration globale du plugin
1. Accédez à `Administration du site > Plugins > Blocs > Télécharger des certificats`
2. Activez ou désactivez le plugin selon vos besoins
3. Vérifiez l'attribution des capacités aux différents rôles
4. Configurez le bloc par défaut sur le tableau de bord si nécessaire

### Gestion des permissions
1. Naviguez vers `Administration du site > Utilisateurs > Permissions > Définir les rôles`
2. Pour chaque rôle (Étudiant, Enseignant, Gestionnaire), vérifiez les capacités suivantes :
   - Voir le bloc de téléchargement de certificats
   - Gérer le téléchargement de certificats (réservé aux gestionnaires)
   - Ajouter une instance du bloc
3. Ajustez les permissions si votre organisation a des besoins spécifiques

### Maintenance et mises à jour
1. Avant toute mise à jour, effectuez une sauvegarde complète de votre plateforme
2. Téléchargez la nouvelle version du plugin
3. Remplacez les fichiers dans le dossier du plugin
4. Accédez à `Administration du site > Notifications` pour appliquer les mises à jour de base de données
5. La tâche de nettoyage automatique s'exécute toutes les heures pour supprimer les archives expirées

### Surveillance des tâches asynchrones
Les tâches de téléchargement en arrière-plan sont suivies dans la base de données. En cas de problème :
1. Vérifiez l'état des tâches dans l'interface utilisateur
2. Les tâches échouées affichent un message d'erreur explicatif
3. Les tâches bloquées plus de 2 heures sont automatiquement marquées comme échouées
4. Les archives prêtes non téléchargées expirent après 24 heures

## 📖 Guide d'utilisation (Enseignant/Formateur)

### 1. Accéder à l'interface enseignant
1. Connectez-vous à Moodle avec votre compte enseignant
2. Le bloc "Téléchargement de Certificats" apparaît sur votre tableau de bord ou dans vos cours
3. Cliquez sur "Gérer les certificats" pour accéder à l'interface complète
4. Vous êtes automatiquement dirigé vers l'interface enseignant filtrée

**Note importante** : Si vous avez également un rôle de gestionnaire, vous accéderez à l'interface complète administrateur.

### 2. Comprendre votre périmètre de visibilité
Votre accès aux certificats est automatiquement filtré selon ces règles :
- Vous voyez uniquement les certificats des cours où vous avez un rôle d'enseignement
- Si vous appartenez à un ou plusieurs groupes dans un cours, vous ne voyez que les certificats des apprenants de ces groupes
- Cette règle s'applique indépendamment pour chaque cours

**Note** : Le filtrage est appliqué automatiquement par le système. Vous ne verrez dans les listes déroulantes que les cours et les apprenants auxquels vous avez accès.

### 3. Télécharger les certificats d'un cours
1. Cliquez sur l'onglet "Par cours" dans l'interface
2. Sélectionnez le cours souhaité dans la liste déroulante
3. Si vous appartenez à des groupes dans ce cours, un sélecteur de groupe apparaît
4. Choisissez un groupe spécifique ou "Tous les groupes" pour voir tous vos apprenants du cours
5. Cliquez sur le bouton "Télécharger les certificats du cours"
6. Si le nombre de certificats est important, le traitement se fait en arrière-plan
7. Une notification vous informe lorsque l'archive est prête

### 4. Télécharger les certificats d'un apprenant spécifique
1. Cliquez sur l'onglet "Par utilisateur" dans l'interface
2. Utilisez la liste déroulante pour rechercher un apprenant
3. La liste affiche uniquement les apprenants de votre périmètre de visibilité
4. Sélectionnez l'apprenant souhaité
5. Cliquez sur "Télécharger les certificats"
6. Une archive ZIP contenant tous les certificats de cet apprenant dans vos cours est générée

### 5. Rechercher un certificat spécifique
1. Utilisez la barre de recherche en haut de la table des certificats
2. Saisissez un nom, un email, un nom de cours ou un modèle de certificat
3. La recherche se met à jour automatiquement après 300 millisecondes
4. Cliquez sur l'icône de téléchargement en face du certificat souhaité
5. Le fichier PDF est téléchargé immédiatement

### 6. Trier et paginer les résultats
1. Cliquez sur l'en-tête d'une colonne pour trier les certificats (nom, email, cours, date)
2. Cliquez à nouveau pour inverser l'ordre de tri (croissant/décroissant)
3. Utilisez le sélecteur "Par page" pour choisir le nombre de résultats affichés (25, 50, 100, 200)
4. Naviguez entre les pages à l'aide des contrôles de pagination en bas de table

### 7. Gérer les téléchargements en arrière-plan
1. Lorsqu'un téléchargement dépasse 30 certificats, une barre de progression apparaît
2. Vous pouvez fermer cette fenêtre et continuer à naviguer sur Moodle
3. Une bannière persistante en haut de page affiche l'avancement
4. Lorsque l'archive est prête, un bouton de téléchargement apparaît
5. Vous pouvez fermer la bannière si vous souhaitez télécharger plus tard
6. Les archives non téléchargées expirent après 24 heures

### Astuces et avertissements

**Astuce 1** : Pour les exports réguliers, privilégiez le téléchargement par plage de dates afin de ne récupérer que les certificats délivrés depuis votre dernier export.

**Astuce 2** : Les noms de fichiers sont standardisés. Utilisez cette convention pour organiser vos archives sur votre système de fichiers local.

**Avertissement 1** : Les archives ZIP sont supprimées automatiquement après 24 heures. Téléchargez-les rapidement après leur génération.

**Avertissement 2** : Si vous appartenez à des groupes dans un cours, assurez-vous de sélectionner le bon groupe pour obtenir les certificats de tous vos apprenants concernés.

**Avertissement 3** : Le téléchargement de gros volumes peut prendre plusieurs minutes. Privilégiez le mode asynchrone et ne fermez pas votre navigateur avant que la tâche ne soit créée.

## 🔐 Permissions détaillées

### Voir le bloc de téléchargement de certificats
**Description** : Permet à l'utilisateur d'afficher le bloc "Téléchargement de Certificats" sur son tableau de bord ou dans les cours où le bloc est ajouté.

**Rôles concernés** :
- Étudiant : Autorisé
- Enseignant : Autorisé
- Enseignant éditeur : Autorisé
- Gestionnaire : Autorisé
- Utilisateur connecté : Autorisé
- Invité : Refusé

**Impact fonctionnel** : Sans cette permission, le bloc n'apparaît pas pour l'utilisateur, mais il peut toujours accéder à la page de gestion s'il a d'autres permissions.

### Gérer les téléchargements de certificats
**Description** : Donne accès à l'interface complète de gestion avec tous les modes de téléchargement (tous, par date, par cours, par cohorte, par utilisateur) et la visibilité sur l'ensemble des certificats de la plateforme.

**Rôles concernés** :
- Gestionnaire : Autorisé
- Administrateur : Autorisé (par défaut)
- Étudiant : Refusé
- Enseignant : Refusé

**Impact fonctionnel** : Cette permission est requise pour accéder à la page principale de gestion (`/blocks/download_certificates/index.php`). Sans elle, l'utilisateur est redirigé vers son tableau de bord.

### Ajouter une instance du bloc
**Description** : Permet d'ajouter le bloc "Téléchargement de Certificats" à une page de cours ou à une page système.

**Rôles concernés** :
- Enseignant éditeur : Autorisé
- Gestionnaire : Autorisé
- Étudiant : Refusé

**Impact fonctionnel** : Permet aux enseignants d'ajouter le bloc dans leurs cours pour un accès rapide.

### Ajouter une instance au tableau de bord
**Description** : Permet à l'utilisateur d'ajouter le bloc à son tableau de bord personnel.

**Rôles concernés** :
- Tous les utilisateurs connectés : Autorisé

**Impact fonctionnel** : Chaque utilisateur peut personnaliser son tableau de bord avec ce bloc pour accéder rapidement à ses certificats.

### Accès spécifique des enseignants
**Description** : Les enseignants bénéficient d'un accès intermédiaire qui leur permet de télécharger les certificats de leurs cours, mais avec un filtrage automatique basé sur leur périmètre pédagogique.

**Règles d'accès** :
- Un enseignant voit tous les certificats de tous ses cours
- Si l'enseignant appartient à un ou plusieurs groupes dans un cours, sa visibilité est restreinte aux seuls apprenants de ces groupes dans ce cours
- Cette règle s'applique cours par cours de manière indépendante
- Les enseignants ne peuvent pas télécharger par cohorte ou par plage de dates (réservé aux gestionnaires)

**Rôles concernés** :
- Enseignant : Accès filtré automatique
- Enseignant éditeur : Accès filtré automatique

## 📝 Changelog

### Version 2026052800

**Changements** :
- Mise à jour du numéro de version du plugin
- Aucun changement fonctionnel ou technique majeur

### Version 2026033101

**Changements** :
- Suppression de l'affichage du périmètre de visibilité (liste des cours) dans le bloc pour les enseignants
- Suppression du bandeau d'information "teacher_scope_courses" dans l'interface enseignant
- Simplification de l'interface utilisateur pour une meilleure lisibilité

### Version 2026033100

**Nouvelles fonctionnalités** :
- Interface de gestion complète avec tableau dynamique chargé via AJAX
- Recherche en temps réel avec filtrage par nom, email, cours et modèle
- Tri des colonnes et pagination configurable (25, 50, 100, 200 résultats)
- Traitement asynchrone pour les téléchargements de plus de 30 certificats
- Barre de progression persistante avec possibilité de navigation pendant le traitement
- Support de 5 types de certificats (tool_certificate, customcert, mod_certificate, simplecertificate, certificatebeautiful)
- Interface dédiée pour les enseignants avec filtrage automatique par cours et groupes
- Bloc tableau de bord pour les apprenants avec accès à leurs propres certificats
- Nomenclature standardisée des fichiers téléchargés
- Nettoyage automatique des tâches et archives expirées

**Améliorations** :
- Architecture modulaire avec séparation des responsabilités (requêtes, récupération, empaquetage)
- Gestion optimisée de la mémoire pour les gros volumes de certificats
- Interface responsive compatible avec les thèmes Moodle 4.x
- Support bilingue français/anglais complet

**Corrections** :
- Gestion correcte des permissions enseignants avec filtrage par groupes
- Prévention des fuites de mémoire lors du traitement par lots
- Validation des sessions pour toutes les opérations de téléchargement

## 📧 Contact

- Email : support@elearningtouch.com
- Site web : https://www.elearningtouch.com/