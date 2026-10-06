<?php
/**
 * CLI : Crée des groupes de test pour valider le cloisonnement enseignant.
 *
 * Scénario :
 *   - Cours TESTCERT_001 (déjà créé)
 *   - 2 groupes : Groupe A et Groupe B
 *   - Enseignant testteacher01 → membre du Groupe A seulement
 *   - Etudiants 1-2 → Groupe A  |  Etudiants 3-5 → Groupe B
 *
 * Résultat attendu :
 *   L'enseignant ne voit que les certificats des étudiants 1 et 2.
 *
 * @package block_download_certificates
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

global $DB;

cli_writeln("=== Création des groupes de test ===");

// Récupérer le cours.
$course = $DB->get_record('course', ['shortname' => 'TESTCERT_001']);
if (!$course) {
    cli_error('Cours TESTCERT_001 introuvable. Lance d\'abord generate_test_certificates_customcert.php');
}
cli_writeln("Cours : " . $course->fullname . " (id=" . $course->id . ")");

// Récupérer l'enseignant.
$teacher = $DB->get_record('user', ['username' => 'testteacher01', 'deleted' => 0]);
if (!$teacher) {
    cli_error('Enseignant testteacher01 introuvable.');
}
cli_writeln("Enseignant : testteacher01 (id=" . $teacher->id . ")");

// Récupérer les étudiants de test.
$students = array_values($DB->get_records_select(
    'user',
    "username LIKE 'testcert_user_%' AND deleted = 0",
    [],
    'username ASC',
    'id,username,firstname,lastname'
));
if (count($students) < 2) {
    cli_error('Pas assez d\'étudiants. Lance d\'abord generate_test_certificates_customcert.php');
}
cli_writeln("Etudiants trouvés : " . count($students));

// Supprimer les groupes existants du cours pour repartir de zéro.
$existing = $DB->get_records('groups', ['courseid' => $course->id]);
foreach ($existing as $g) {
    $DB->delete_records('groups_members', ['groupid' => $g->id]);
    $DB->delete_records('groups', ['id' => $g->id]);
}
if ($existing) {
    cli_writeln("Anciens groupes supprimés (" . count($existing) . ").");
}

// Créer Groupe A.
$g1 = new stdClass();
$g1->courseid     = $course->id;
$g1->name         = 'Groupe A';
$g1->timecreated  = time();
$g1->timemodified = time();
$g1->id = $DB->insert_record('groups', $g1);
cli_writeln("Groupe A créé (id=" . $g1->id . ")");

// Créer Groupe B.
$g2 = new stdClass();
$g2->courseid     = $course->id;
$g2->name         = 'Groupe B';
$g2->timecreated  = time();
$g2->timemodified = time();
$g2->id = $DB->insert_record('groups', $g2);
cli_writeln("Groupe B créé (id=" . $g2->id . ")");

// Ajouter l'enseignant dans le Groupe A uniquement.
$gm = new stdClass();
$gm->groupid  = $g1->id;
$gm->userid   = $teacher->id;
$gm->timeadded = time();
$DB->insert_record('groups_members', $gm);
cli_writeln("Enseignant → Groupe A");

// Répartir les étudiants.
$groupAstudents = array_slice($students, 0, 2); // étudiants 1 et 2
$groupBstudents = array_slice($students, 2);    // étudiants 3, 4, 5

foreach ($groupAstudents as $s) {
    $gm = new stdClass();
    $gm->groupid   = $g1->id;
    $gm->userid    = $s->id;
    $gm->timeadded = time();
    $DB->insert_record('groups_members', $gm);
    cli_writeln("  " . $s->username . " → Groupe A");
}

foreach ($groupBstudents as $s) {
    $gm = new stdClass();
    $gm->groupid   = $g2->id;
    $gm->userid    = $s->id;
    $gm->timeadded = time();
    $DB->insert_record('groups_members', $gm);
    cli_writeln("  " . $s->username . " → Groupe B");
}

cli_writeln("");
cli_writeln("=== RÉSULTAT ATTENDU ===");
cli_writeln("L'enseignant est dans Groupe A uniquement.");
cli_writeln("Il doit voir SEULEMENT les certificats de :");
foreach ($groupAstudents as $s) {
    cli_writeln("  ✓ " . $s->username . " (" . $s->firstname . " " . $s->lastname . ")");
}
cli_writeln("Il NE doit PAS voir :");
foreach ($groupBstudents as $s) {
    cli_writeln("  ✗ " . $s->username . " (" . $s->firstname . " " . $s->lastname . ")");
}
cli_writeln("");
cli_writeln("=== Terminé ===");
