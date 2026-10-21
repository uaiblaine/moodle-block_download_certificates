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
 * Classe de gestion du périmètre d'accès pour les enseignants.
 *
 * Cette classe centralise la logique de détection des rôles enseignant
 * et de calcul du périmètre de visibilité (cours + étudiants autorisés).
 *
 * Règle métier :
 *   - Un enseignant voit tous les certificats de ses cours.
 *   - S'il appartient à ≥1 groupe dans un cours, il ne voit que les étudiants
 *     membres de ses groupes dans CE cours (la règle s'applique cours par cours).
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Gestion du périmètre d'accès enseignant (rôles editingteacher / teacher).
 */
class block_download_certificates_access {
    /** @var int[] Cache des IDs de cours enseignés (indexés par userid). */
    private static $teachercoursecache = [];

    /** @var array Cache du périmètre visible par cours (indexé par userid). */
    private static $teacherscopecache = [];

    // Détection du rôle enseignant.

    /**
     * Détermine si un utilisateur est enseignant dans au moins un cours.
     *
     * Détection basée sur les rôles Moodle natifs : editingteacher (roleId 3)
     * et teacher (roleId 4), via les role_assignments au niveau cours (contextlevel 50).
     *
     * Sécurité : requête paramétrée uniquement, pas d'injection possible.
     *
     * @param int $userid ID de l'utilisateur à vérifier
     * @return bool True si l'utilisateur est enseignant dans au moins un cours
     */
    public function is_teacher(int $userid): bool {
        return !empty($this->get_teacher_course_ids($userid));
    }

    /**
     * Retourne la liste des IDs de cours où l'utilisateur est enseignant.
     *
     * Utilise un cache statique pour éviter les requêtes répétées dans la
     * même requête HTTP.
     *
     * @param int $userid ID de l'utilisateur
     * @return int[] Tableau d'IDs de cours
     */
    public function get_teacher_course_ids(int $userid): array {
        global $DB;

        if (isset(self::$teachercoursecache[$userid])) {
            return self::$teachercoursecache[$userid];
        }

        // Requête paramétrée — pas de concaténation avec des données utilisateur.
        // contextlevel = 50 = CONTEXT_COURSE.
        $sql = "SELECT DISTINCT ctx.instanceid AS courseid
                FROM {role_assignments} ra
                JOIN {context} ctx ON ctx.id = ra.contextid
                JOIN {role} r ON r.id = ra.roleid
                WHERE ra.userid = :userid
                  AND ctx.contextlevel = 50
                  AND r.shortname IN ('editingteacher', 'teacher')";

        $records = $DB->get_records_sql($sql, ['userid' => $userid]);
        $courseids = array_map('intval', array_keys($records));

        self::$teachercoursecache[$userid] = $courseids;
        return $courseids;
    }

    // Calcul du périmètre visible (étudiants autorisés par cours).

    /**
     * Retourne le périmètre de visibilité de l'enseignant, cours par cours.
     *
     * Retourne un tableau associatif [courseid => [userid, userid, ...]].
     * Pour chaque cours :
     *   - Si l'enseignant appartient à ≥1 groupe dans ce cours :
     *     → Uniquement les membres de ses groupes dans ce cours.
     *   - Sinon :
     *     → Tous les étudiants inscrits actifs dans ce cours.
     *
     * Sécurité : toutes les requêtes sont paramétrées.
     *
     * @param int $userid ID de l'enseignant
     * @return array Tableau [courseid => [userid, ...]]
     */
    public function get_teacher_visible_user_ids(int $userid): array {
        global $DB;

        if (isset(self::$teacherscopecache[$userid])) {
            return self::$teacherscopecache[$userid];
        }

        $courseids = $this->get_teacher_course_ids($userid);
        if (empty($courseids)) {
            self::$teacherscopecache[$userid] = [];
            return [];
        }

        $scope = [];

        foreach ($courseids as $courseid) {
            // Vérifier si l'enseignant appartient à des groupes dans ce cours.
            $teachergroups = $DB->get_records_sql(
                "SELECT g.id
                 FROM {groups} g
                 JOIN {groups_members} gm ON gm.groupid = g.id
                 WHERE g.courseid = :courseid AND gm.userid = :userid",
                ['courseid' => $courseid, 'userid' => $userid]
            );

            if (!empty($teachergroups)) {
                // L'enseignant est dans des groupes → périmètre restreint aux membres de ses groupes.
                $groupids = array_keys($teachergroups);
                [$insql, $inparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'gid');
                $inparams['courseid'] = $courseid;

                $members = $DB->get_records_sql(
                    "SELECT DISTINCT gm.userid
                     FROM {groups_members} gm
                     JOIN {groups} g ON g.id = gm.groupid
                     WHERE g.courseid = :courseid AND gm.groupid {$insql}",
                    $inparams
                );

                $scope[$courseid] = array_map('intval', array_column($members, 'userid'));
            } else {
                // L'enseignant n'est pas dans un groupe → tous les étudiants inscrits actifs.
                $members = $DB->get_records_sql(
                    "SELECT DISTINCT ue.userid
                     FROM {user_enrolments} ue
                     JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE e.courseid = :courseid AND ue.status = 0",
                    ['courseid' => $courseid]
                );

                $scope[$courseid] = array_map('intval', array_column($members, 'userid'));
            }
        }

        self::$teacherscopecache[$userid] = $scope;
        return $scope;
    }

    /**
     * Retourne la liste aplatie de tous les userids visibles par l'enseignant
     * (union de tous les cours, sans doublon).
     *
     * @param int $userid ID de l'enseignant
     * @return int[] Tableau plat d'IDs utilisateurs
     */
    public function get_teacher_all_visible_user_ids(int $userid): array {
        $scope = $this->get_teacher_visible_user_ids($userid);
        if (empty($scope)) {
            return [];
        }

        $allids = [];
        foreach ($scope as $userids) {
            $allids = array_merge($allids, $userids);
        }

        return array_unique($allids);
    }

    // Vérifications de droits pour les actions de téléchargement.

    /**
     * Vérifie que l'enseignant courant peut accéder au certificat d'un étudiant
     * dans un cours donné. Lève une moodle_exception si l'accès est refusé.
     *
     * Principe "fail close" : en cas de doute, on refuse l'accès.
     *
     * @param int $certuserid ID de l'étudiant propriétaire du certificat
     * @param int $courseid   ID du cours auquel appartient le certificat
     * @throws moodle_exception Si l'accès est refusé
     */
    public function require_teacher_access_to_certificate(int $certuserid, int $courseid): void {
        global $USER;

        $scope = $this->get_teacher_visible_user_ids($USER->id);

        // Vérifier que le cours fait partie du périmètre de l'enseignant.
        if (!isset($scope[$courseid])) {
            throw new moodle_exception('accessdenied', 'block_download_certificates');
        }

        // Vérifier que l'étudiant est visible dans ce cours.
        if (!in_array($certuserid, $scope[$courseid], true)) {
            throw new moodle_exception('accessdenied', 'block_download_certificates');
        }
    }

    /**
     * Vérifie que l'enseignant courant enseigne dans un cours donné.
     * Lève une moodle_exception si ce n'est pas le cas.
     *
     * @param int $courseid ID du cours
     * @throws moodle_exception Si le cours n'est pas dans le périmètre
     */
    public function require_teacher_in_course(int $courseid): void {
        global $USER;

        $courseids = $this->get_teacher_course_ids($USER->id);
        if (!in_array($courseid, $courseids, true)) {
            throw new moodle_exception('accessdenied', 'block_download_certificates');
        }
    }

    /**
     * Retourne les groupes de l'enseignant dans un cours donné.
     *
     * @param int $userid   ID de l'enseignant
     * @param int $courseid ID du cours
     * @return array Tableau d'objets groupes (id, name)
     */
    public function get_teacher_groups_in_course(int $userid, int $courseid): array {
        global $DB;

        return array_values($DB->get_records_sql(
            "SELECT g.id, g.name
             FROM {groups} g
             JOIN {groups_members} gm ON gm.groupid = g.id
             WHERE g.courseid = :courseid AND gm.userid = :userid
             ORDER BY g.name",
            ['courseid' => $courseid, 'userid' => $userid]
        ));
    }

    /**
     * Réinitialise les caches statiques (utile pour les tests unitaires).
     */
    public static function reset_cache(): void {
        self::$teachercoursecache = [];
        self::$teacherscopecache = [];
    }
}
