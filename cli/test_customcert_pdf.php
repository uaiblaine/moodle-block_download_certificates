<?php
/**
 * Crée le Groupe C dans le cours 54 avec :
 * - Le prof (testteacher01, id=1022)
 * - CertUser3 (id=1019), CertUser4 (id=1020), CertUser5 (id=1021)
 *   (les étudiants qui NE sont PAS dans Groupe A)
 */
define('CLI_SCRIPT', true);
require 'config.php';
require_once($CFG->dirroot . '/group/lib.php');

$courseid = 54;

// Vérifier que le Groupe C n'existe pas déjà.
$existing = $DB->get_record('groups', ['courseid' => $courseid, 'name' => 'Groupe C']);
if ($existing) {
    echo 'Groupe C existe déjà (id=' . $existing->id . ')' . PHP_EOL;
    $groupid = $existing->id;
} else {
    // Créer le Groupe C.
    $group = new stdClass();
    $group->courseid    = $courseid;
    $group->name        = 'Groupe C';
    $group->description = 'Groupe contenant le prof et les étudiants hors Groupe A';
    $group->timecreated  = time();
    $group->timemodified = time();
    $groupid = groups_create_group($group);
    echo 'Groupe C créé : id=' . $groupid . PHP_EOL;
}

// Membres à ajouter : prof (1022) + CertUser3 (1019) + CertUser4 (1020) + CertUser5 (1021).
$members = [
    1022 => 'testteacher01 (Prof Dupont)',
    1019 => 'testcert_user_0003 (CertUser3)',
    1020 => 'testcert_user_0004 (CertUser4)',
    1021 => 'testcert_user_0005 (CertUser5)',
];

foreach ($members as $userid => $label) {
    if (groups_add_member($groupid, $userid)) {
        echo 'Ajouté : ' . $label . PHP_EOL;
    } else {
        echo 'Déjà membre ou erreur : ' . $label . PHP_EOL;
    }
}

// Confirmation finale.
echo PHP_EOL . '=== Membres du Groupe C ===' . PHP_EOL;
$final = groups_get_members($groupid, 'u.id,u.username,u.firstname,u.lastname');
foreach ($final as $m) {
    echo ' - id=' . $m->id . ' | ' . $m->username . ' | ' . $m->firstname . ' ' . $m->lastname . PHP_EOL;
}
echo PHP_EOL . 'OK' . PHP_EOL;
