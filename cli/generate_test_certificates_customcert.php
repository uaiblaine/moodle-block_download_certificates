<?php
// This file is part of Moodle - http://moodle.org/
//
// CLI script to generate test certificates using mod_customcert.
//
// Usage: php blocks/download_certificates/cli/generate_test_certificates_customcert.php --count=20
//
// @package   block_download_certificates

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/course/lib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'count'   => 20,
        'users'   => 5,
        'courses' => 2,
        'help'    => false,
        'cleanup' => false,
    ],
    ['c' => 'count', 'u' => 'users', 'n' => 'courses', 'h' => 'help']
);

if ($options['help']) {
    echo "Génère des certificats de test via mod_customcert.

Options :
  -c, --count=N     Nombre de certificats à générer (défaut : 20)
  -u, --users=N     Nombre d'utilisateurs test à créer (défaut : 5)
  -n, --courses=N   Nombre de cours test à créer (défaut : 2)
  --cleanup         Supprime les données de test existantes
  -h, --help        Affiche cette aide

Exemple :
  php blocks/download_certificates/cli/generate_test_certificates_customcert.php --count=20 --users=5 --courses=2
";
    exit(0);
}

global $DB, $CFG;

// Vérification du prérequis.
cli_writeln("=== Vérification des prérequis ===");
$dbman = $DB->get_manager();
if (!$dbman->table_exists('customcert_issues') || !$dbman->table_exists('customcert')) {
    cli_error('Le plugin mod_customcert n\'est pas installé !');
}
cli_writeln("  mod_customcert : OK");

// =========================================================================
// Cleanup si demandé.
// =========================================================================
if ($options['cleanup']) {
    cli_writeln("\n=== Suppression des données de test ===");

    // Issues.
    $testusers = $DB->get_records_select('user', "username LIKE 'testcert_user_%'", [], '', 'id');
    if ($testusers) {
        $userids = array_keys($testusers);
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('customcert_issues', "userid {$insql}", $inparams);
        foreach ($userids as $uid) {
            $DB->delete_records('user_enrolments', ['userid' => $uid]);
            $u = $DB->get_record('user', ['id' => $uid]);
            if ($u) delete_user($u);
        }
        cli_writeln("  Supprimé " . count($userids) . " utilisateurs test et leurs issues.");
    }

    // Cours.
    $testcourses = $DB->get_records_select('course', "shortname LIKE 'TESTCERT_%'", [], '', 'id');
    foreach ($testcourses as $c) {
        delete_course($c->id, false);
    }
    if ($testcourses) cli_writeln("  Supprimé " . count($testcourses) . " cours test.");

    // customcert modules.
    $DB->delete_records_select('customcert', "name LIKE 'Cert Test%'");
    $DB->delete_records_select('customcert_templates', "name LIKE 'Template Test%'");
    cli_writeln("Cleanup terminé.\n");

    if ($options['count'] <= 0) exit(0);
}

$count     = (int) $options['count'];
$numusers  = (int) $options['users'];
$numcourses= (int) $options['courses'];

// =========================================================================
// Étape 1 : Créer les utilisateurs test.
// =========================================================================
cli_writeln("\n=== Création des utilisateurs test ===");

$existingusers = $DB->get_records_select('user', "username LIKE 'testcert_user_%' AND deleted = 0", [], '', 'id,username');
$userids = array_keys($existingusers);

if (count($userids) >= $numusers) {
    cli_writeln("  Réutilisation de " . count($userids) . " utilisateurs existants.");
} else {
    $needed = $numusers - count($userids);
    cli_writeln("  Création de {$needed} utilisateurs...");

    for ($i = count($userids) + 1; $i <= $numusers; $i++) {
        $username = 'testcert_user_' . str_pad($i, 4, '0', STR_PAD_LEFT);
        $existing = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
        if ($existing) {
            $userids[] = $existing->id;
            continue;
        }
        $user = new stdClass();
        $user->username     = $username;
        $user->firstname    = 'Test';
        $user->lastname     = 'CertUser' . $i;
        $user->email        = 'testcert_user_' . $i . '@example.com';
        $user->password     = hash_internal_user_password('TestCert1!');
        $user->confirmed    = 1;
        $user->mnethostid   = $CFG->mnet_localhost_id;
        $user->timecreated  = time();
        $user->timemodified = time();
        $user->auth         = 'manual';
        $user->id           = $DB->insert_record('user', $user);
        $userids[] = $user->id;
    }
    cli_writeln("  Total utilisateurs : " . count($userids));
}

// Créer aussi un enseignant de test.
$teacher = $DB->get_record('user', ['username' => 'testteacher01', 'deleted' => 0]);
if (!$teacher) {
    $teacher = new stdClass();
    $teacher->username     = 'testteacher01';
    $teacher->firstname    = 'Prof';
    $teacher->lastname     = 'Dupont';
    $teacher->email        = 'testteacher01@example.com';
    $teacher->password     = hash_internal_user_password('Teacher123!');
    $teacher->confirmed    = 1;
    $teacher->mnethostid   = $CFG->mnet_localhost_id;
    $teacher->timecreated  = time();
    $teacher->timemodified = time();
    $teacher->auth         = 'manual';
    $teacher->id           = $DB->insert_record('user', $teacher);
    cli_writeln("  Enseignant créé : testteacher01 (id=" . $teacher->id . ")");
} else {
    cli_writeln("  Enseignant existant : testteacher01 (id=" . $teacher->id . ")");
}

// =========================================================================
// Étape 2 : Créer les cours test.
// =========================================================================
cli_writeln("\n=== Création des cours test ===");

$existingcourses = $DB->get_records_select('course', "shortname LIKE 'TESTCERT_%'", [], '', 'id,shortname');
$courseids = array_keys($existingcourses);

if (count($courseids) >= $numcourses) {
    cli_writeln("  Réutilisation de " . count($courseids) . " cours existants.");
} else {
    $needed = $numcourses - count($courseids);
    cli_writeln("  Création de {$needed} cours...");

    for ($i = count($courseids) + 1; $i <= $numcourses; $i++) {
        $shortname = 'TESTCERT_' . str_pad($i, 3, '0', STR_PAD_LEFT);
        $existing  = $DB->get_record('course', ['shortname' => $shortname]);
        if ($existing) {
            $courseids[] = $existing->id;
            continue;
        }
        $coursedata = new stdClass();
        $coursedata->shortname   = $shortname;
        $coursedata->fullname    = 'Cours Test Certificat ' . $i;
        $coursedata->category    = 1;
        $coursedata->format      = 'topics';
        $coursedata->numsections = 1;
        $course    = create_course($coursedata);
        $courseids[] = $course->id;
        cli_writeln("  Cours créé : " . $shortname . " (id=" . $course->id . ")");
    }
    cli_writeln("  Total cours : " . count($courseids));
}

// =========================================================================
// Étape 3 : Inscrire les utilisateurs dans les cours.
// =========================================================================
cli_writeln("\n=== Inscription des utilisateurs ===");

$enrolplugin   = enrol_get_plugin('manual');
$editingrole   = $DB->get_record('role', ['shortname' => 'editingteacher']);
$studentrole   = $DB->get_record('role', ['shortname' => 'student']);
$enrolcount    = 0;

foreach ($courseids as $courseid) {
    $enrolinstance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual']);
    if (!$enrolinstance) {
        $enrolplugin->add_instance($DB->get_record('course', ['id' => $courseid]));
        $enrolinstance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual']);
    }

    // Inscrire l'enseignant.
    if (!$DB->record_exists('user_enrolments', ['enrolid' => $enrolinstance->id, 'userid' => $teacher->id])) {
        $enrolplugin->enrol_user($enrolinstance, $teacher->id, $editingrole->id);
        $enrolcount++;
    }

    // Inscrire les étudiants.
    foreach ($userids as $userid) {
        if (!$DB->record_exists('user_enrolments', ['enrolid' => $enrolinstance->id, 'userid' => $userid])) {
            $enrolplugin->enrol_user($enrolinstance, $userid, $studentrole->id);
            $enrolcount++;
        }
    }
}
cli_writeln("  {$enrolcount} nouvelles inscriptions créées.");

// =========================================================================
// Étape 4 : Créer le template customcert (1 par cours).
// =========================================================================
cli_writeln("\n=== Création des modules customcert ===");

$syscontext = context_system::instance();
$customcertids = []; // [courseid => customcertid]

foreach ($courseids as $courseid) {
    // Chercher si un customcert existe déjà dans ce cours.
    $existing = $DB->get_record('customcert', ['course' => $courseid]);
    if ($existing) {
        $customcertids[$courseid] = $existing->id;
        cli_writeln("  Customcert existant dans cours {$courseid} (id=" . $existing->id . ")");
        continue;
    }

    // Créer un template.
    $tpl = new stdClass();
    $tpl->name        = 'Template Test Cours ' . $courseid;
    $tpl->contextid   = $syscontext->id;
    $tpl->timecreated = time();
    $tpl->timemodified= time();
    $tpl->id = $DB->insert_record('customcert_templates', $tpl);

    // Créer le module customcert dans le cours.
    $cc = new stdClass();
    $cc->course       = $courseid;
    $cc->name         = 'Cert Test Cours ' . $courseid;
    $cc->intro        = '';
    $cc->introformat  = 1;
    $cc->templateid   = $tpl->id;
    $cc->timecreated  = time();
    $cc->timemodified = time();
    $cc->id = $DB->insert_record('customcert', $cc);
    $customcertids[$courseid] = $cc->id;
    cli_writeln("  Customcert créé dans cours {$courseid} (id=" . $cc->id . ")");
}

// =========================================================================
// Étape 5 : Émettre les issues (certificats obtenus).
// =========================================================================
cli_writeln("\n=== Émission de {$count} certificats ===");

$issued    = 0;
$starttime = microtime(true);
$coursekeys= array_values($courseids);

for ($i = 0; $i < $count; $i++) {
    $userid    = $userids[$i % count($userids)];
    $courseid  = $coursekeys[$i % count($coursekeys)];
    $certid    = $customcertids[$courseid];

    // Éviter les doublons (1 certificat par utilisateur par customcert).
    if ($DB->record_exists('customcert_issues', ['userid' => $userid, 'customcertid' => $certid])) {
        continue;
    }

    $issue = new stdClass();
    $issue->userid        = $userid;
    $issue->customcertid  = $certid;
    $issue->code          = strtoupper(substr(md5(uniqid('', true)), 0, 10));
    $issue->emailed       = 0;
    $issue->timecreated   = time() - rand(3600, 30 * 86400);
    $DB->insert_record('customcert_issues', $issue);
    $issued++;
}

$totaltime = round(microtime(true) - $starttime, 2);

cli_writeln("\n=== Terminé ! ===");
cli_writeln("  Certificats émis : {$issued}");
cli_writeln("  Durée : {$totaltime}s");
cli_writeln("  Total customcert_issues en DB : " . $DB->count_records('customcert_issues'));
cli_writeln("");
cli_writeln("  Comptes de test :");
cli_writeln("    Enseignant : testteacher01 / Teacher123!");
cli_writeln("    Etudiants  : testcert_user_0001 à testcert_user_" . str_pad($numusers, 4, '0', STR_PAD_LEFT) . " / TestCert1!");
