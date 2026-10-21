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
 * Certificate query class for block_download_certificates plugin.
 *
 * Handles all SQL queries for retrieving certificate data from various plugins.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Handles all SQL queries for certificate data retrieval.
 */
class block_download_certificates_query {
    // Individual certificate type queries (get_*_issues).

    /**
     * Get tool_certificate issues.
     *
     * @return array
     */
    public function get_tool_certificate_issues() {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('tool_certificate_issues')) {
            return [];
        }

        $sql = "SELECT tci.id, tci.userid, tci.templateid, tci.code, tci.emailed,
                       tci.timecreated, tci.expires, tci.data, tci.component, tci.courseid,
                       u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email,
                       c.fullname as coursename,
                       tc.name as templatename
                FROM {tool_certificate_issues} tci
                JOIN {user} u ON u.id = tci.userid
                LEFT JOIN {course} c ON c.id = tci.courseid
                LEFT JOIN {tool_certificate_templates} tc ON tc.id = tci.templateid
                ORDER BY tci.timecreated DESC";

        try {
            return $DB->get_records_sql($sql);
        } catch (Exception $e) {
            debugging('get_tool_certificate_issues: query failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get customcert issues.
     *
     * @return array
     */
    public function get_customcert_issues() {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('customcert_issues')) {
            return [];
        }

        $sql = "SELECT ci.id, ci.userid, ci.customcertid, ci.code, ci.emailed, ci.timecreated,
                       u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email,
                       c.fullname as coursename,
                       cc.name as certificatename
                FROM {customcert_issues} ci
                JOIN {user} u ON u.id = ci.userid
                JOIN {customcert} cc ON cc.id = ci.customcertid
                LEFT JOIN {course} c ON c.id = cc.course
                ORDER BY ci.timecreated DESC";

        try {
            return $DB->get_records_sql($sql);
        } catch (Exception $e) {
            debugging('get_customcert_issues: query failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get mod_certificate issues.
     *
     * @return array
     */
    public function get_mod_certificate_issues() {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('certificate_issues')) {
            return [];
        }

        $sql = "SELECT ci.id, ci.userid, ci.certificateid, ci.code, ci.timecreated,
                       u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email,
                       c.fullname as coursename,
                       cert.name as certificatename
                FROM {certificate_issues} ci
                JOIN {user} u ON u.id = ci.userid
                JOIN {certificate} cert ON cert.id = ci.certificateid
                LEFT JOIN {course} c ON c.id = cert.course
                ORDER BY ci.timecreated DESC";

        try {
            return $DB->get_records_sql($sql);
        } catch (Exception $e) {
            debugging('get_mod_certificate_issues: query failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get mod_simplecertificate issues.
     *
     * @return array
     */
    public function get_simplecertificate_issues() {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('simplecertificate_issues')) {
            return [];
        }

        $sql = "SELECT si.id, si.certificateid, si.userid, si.certificatename, si.code,
                       si.timecreated, si.timedeleted, si.haschange, si.pathnamehash, si.coursename,
                       u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email,
                       (SELECT c.fullname FROM {course} c WHERE c.shortname = si.coursename LIMIT 1) as coursename_full
                FROM {simplecertificate_issues} si
                JOIN {user} u ON u.id = si.userid
                WHERE si.timedeleted IS NULL OR si.timedeleted = 0
                ORDER BY si.timecreated DESC";

        try {
            return $DB->get_records_sql($sql);
        } catch (Exception $e) {
            debugging('get_simplecertificate_issues: query failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get mod_certificatebeautiful issues.
     *
     * @return array
     */
    public function get_certificatebeautiful_issues() {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('certificatebeautiful_issue')) {
            return [];
        }

        $sql = "SELECT cbi.*,
                       u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email,
                       c.fullname as coursename,
                       cb.name as certificatename
                FROM {certificatebeautiful_issue} cbi
                JOIN {user} u ON u.id = cbi.userid
                LEFT JOIN {certificatebeautiful} cb ON cb.id = cbi.certificatebeautifulid
                LEFT JOIN {course_modules} cm ON cm.id = cbi.cmid
                LEFT JOIN {course} c ON c.id = cm.course
                ORDER BY cbi.timecreated DESC";

        try {
            return $DB->get_records_sql($sql);
        } catch (Exception $e) {
            debugging('get_certificatebeautiful_issues: query failed: ' . $e->getMessage());
            return [];
        }
    }

    // Aggregate queries (courses, users, cohorts).

    /**
     * Get list of courses that have certificates.
     *
     * @return array Array of courses with certificate counts
     */
    public function get_courses_with_certificates() {
        global $DB;

        $context = context_system::instance();
        require_capability('block/download_certificates:manage', $context);

        $dbman = $DB->get_manager();

        // Get courses with tool_certificate certificates.
        $toolcertcourses = [];
        if ($dbman->table_exists('tool_certificate_issues')) {
            try {
                $sql = "SELECT c.id, c.fullname, c.shortname, COUNT(tci.id) as tool_certificate_count
                        FROM {course} c
                        INNER JOIN {tool_certificate_issues} tci ON c.id = tci.courseid
                        WHERE c.id > 1
                        GROUP BY c.id, c.fullname, c.shortname";
                $toolcertcourses = $DB->get_records_sql($sql);
            } catch (Exception $e) {
                debugging('get_courses_with_certificates: tool_certificate query failed: ' . $e->getMessage());
            }
        }

        // Get courses with customcert certificates.
        $customcertcourses = [];
        if ($dbman->table_exists('customcert_issues')) {
            try {
                $sql = "SELECT c.id, c.fullname, c.shortname, COUNT(ci.id) as customcert_count
                        FROM {course} c
                        INNER JOIN {customcert} cc ON c.id = cc.course
                        INNER JOIN {customcert_issues} ci ON cc.id = ci.customcertid
                        WHERE c.id > 1
                        GROUP BY c.id, c.fullname, c.shortname";
                $customcertcourses = $DB->get_records_sql($sql);
            } catch (Exception $e) {
                debugging('get_courses_with_certificates: customcert query failed: ' . $e->getMessage());
            }
        }

        // Get courses with mod_certificate certificates.
        $modcertcourses = [];
        if ($dbman->table_exists('certificate_issues')) {
            try {
                $sql = "SELECT c.id, c.fullname, c.shortname, COUNT(ci.id) as mod_certificate_count
                        FROM {course} c
                        INNER JOIN {certificate} cert ON c.id = cert.course
                        INNER JOIN {certificate_issues} ci ON cert.id = ci.certificateid
                        WHERE c.id > 1
                        GROUP BY c.id, c.fullname, c.shortname";
                $modcertcourses = $DB->get_records_sql($sql);
            } catch (Exception $e) {
                debugging('get_courses_with_certificates: mod_certificate query failed: ' . $e->getMessage());
            }
        }

        // Get courses with mod_simplecertificate certificates.
        $simplecertcourses = [];
        if ($dbman->table_exists('simplecertificate_issues')) {
            try {
                $sql = "SELECT c.id, c.fullname, c.shortname, COUNT(si.id) as simplecertificate_count
                        FROM {course} c
                        INNER JOIN {simplecertificate_issues} si ON c.shortname = si.coursename
                        WHERE c.id > 1 AND (si.timedeleted IS NULL OR si.timedeleted = 0)
                        GROUP BY c.id, c.fullname, c.shortname";
                $simplecertcourses = $DB->get_records_sql($sql);
            } catch (Exception $e) {
                debugging('get_courses_with_certificates: simplecertificate query failed: ' . $e->getMessage());
            }
        }

        // Get courses with mod_certificatebeautiful certificates.
        $certificatebeautifulcourses = [];
        if ($dbman->table_exists('certificatebeautiful_issue')) {
            try {
                $sql = "SELECT c.id, c.fullname, c.shortname, COUNT(cbi.id) as certificatebeautiful_count
                        FROM {course} c
                        INNER JOIN {course_modules} cm ON c.id = cm.course
                        INNER JOIN {certificatebeautiful_issue} cbi ON cm.id = cbi.cmid
                        WHERE c.id > 1
                        GROUP BY c.id, c.fullname, c.shortname";
                $certificatebeautifulcourses = $DB->get_records_sql($sql);
            } catch (Exception $e) {
                debugging('get_courses_with_certificates: certificatebeautiful query failed: ' . $e->getMessage());
            }
        }

        // Merge and combine counts.
        $allcourses = [];
        $certtypes = [
            'tool_certificate_count' => $toolcertcourses,
            'customcert_count' => $customcertcourses,
            'mod_certificate_count' => $modcertcourses,
            'simplecertificate_count' => $simplecertcourses,
            'certificatebeautiful_count' => $certificatebeautifulcourses,
        ];

        foreach ($certtypes as $countkey => $courses) {
            foreach ($courses as $course) {
                if (!isset($allcourses[$course->id])) {
                    $allcourses[$course->id] = [
                        'id' => $course->id,
                        'fullname' => format_string($course->fullname, true, ['context' => context_system::instance()]),
                        'shortname' => $course->shortname,
                        'tool_certificate_count' => 0,
                        'customcert_count' => 0,
                        'mod_certificate_count' => 0,
                        'simplecertificate_count' => 0,
                        'certificatebeautiful_count' => 0,
                    ];
                }
                $allcourses[$course->id][$countkey] = $course->$countkey;
            }
        }

        // Calculate total count and format for return.
        $courselist = [];
        foreach ($allcourses as $course) {
            $totalcount = $course['tool_certificate_count'] + $course['customcert_count'] +
                $course['mod_certificate_count'] + $course['simplecertificate_count'] +
                $course['certificatebeautiful_count'];
            $courselist[] = [
                'id' => $course['id'],
                'fullname' => $course['fullname'],
                'shortname' => $course['shortname'],
                'certificate_count' => $totalcount,
                'tool_certificate_count' => $course['tool_certificate_count'],
                'customcert_count' => $course['customcert_count'],
                'mod_certificate_count' => $course['mod_certificate_count'],
                'simplecertificate_count' => $course['simplecertificate_count'],
                'certificatebeautiful_count' => $course['certificatebeautiful_count'],
            ];
        }

        usort($courselist, function ($a, $b) {
            return strcmp($a['fullname'], $b['fullname']);
        });

        return $courselist;
    }

    /**
     * Get available cohorts with certificate counts.
     *
     * @return array Array of cohorts with certificate counts
     */
    public function get_cohorts_with_certificates() {
        global $DB;

        $cohorts = $DB->get_records('cohort', null, 'name ASC', 'id, name, description');
        $dbman = $DB->get_manager();

        $cohortsdata = [];
        foreach ($cohorts as $cohort) {
            $membercount = $DB->count_records('cohort_members', ['cohortid' => $cohort->id]);

            if ($membercount > 0) {
                $toolcertcount = 0;
                if ($dbman->table_exists('tool_certificate_issues')) {
                    $sql = "SELECT COUNT(DISTINCT tci.id)
                            FROM {tool_certificate_issues} tci
                            JOIN {cohort_members} cm ON cm.userid = tci.userid
                            WHERE cm.cohortid = :cohortid";
                    $toolcertcount = $DB->count_records_sql($sql, ['cohortid' => $cohort->id]);
                }

                $customcertcount = 0;
                if ($dbman->table_exists('customcert_issues')) {
                    $sql = "SELECT COUNT(DISTINCT ci.id)
                            FROM {customcert_issues} ci
                            JOIN {cohort_members} cm ON cm.userid = ci.userid
                            WHERE cm.cohortid = :cohortid";
                    $customcertcount = $DB->count_records_sql($sql, ['cohortid' => $cohort->id]);
                }

                $modcertcount = 0;
                if ($dbman->table_exists('certificate_issues')) {
                    $sql = "SELECT COUNT(DISTINCT ci.id)
                            FROM {certificate_issues} ci
                            JOIN {cohort_members} cm ON cm.userid = ci.userid
                            WHERE cm.cohortid = :cohortid";
                    $modcertcount = $DB->count_records_sql($sql, ['cohortid' => $cohort->id]);
                }

                $simplecertcount = 0;
                if ($dbman->table_exists('simplecertificate_issues')) {
                    $sql = "SELECT COUNT(DISTINCT si.id)
                            FROM {simplecertificate_issues} si
                            JOIN {cohort_members} cm ON cm.userid = si.userid
                            WHERE cm.cohortid = :cohortid
                            AND (si.timedeleted IS NULL OR si.timedeleted = 0)";
                    $simplecertcount = $DB->count_records_sql($sql, ['cohortid' => $cohort->id]);
                }

                $certificatebeautifulcount = 0;
                if ($dbman->table_exists('certificatebeautiful_issue')) {
                    $sql = "SELECT COUNT(DISTINCT cbi.id)
                            FROM {certificatebeautiful_issue} cbi
                            JOIN {cohort_members} cm ON cm.userid = cbi.userid
                            WHERE cm.cohortid = :cohortid";
                    $certificatebeautifulcount = $DB->count_records_sql($sql, ['cohortid' => $cohort->id]);
                }

                $totalcertcount = $toolcertcount + $customcertcount + $modcertcount +
                    $simplecertcount + $certificatebeautifulcount;

                if ($totalcertcount > 0) {
                    $cohortsdata[] = [
                        'id' => $cohort->id,
                        'name' => format_string($cohort->name, true, ['context' => context_system::instance()]),
                        'description' => $cohort->description,
                        'member_count' => $membercount,
                        'certificate_count' => $totalcertcount,
                    ];
                }
            }
        }

        return $cohortsdata;
    }

    /**
     * Get list of users that have certificates.
     *
     * @return array Array of users with certificate counts
     */
    public function get_users_with_certificates() {
        global $DB;

        $context = context_system::instance();
        require_capability('block/download_certificates:manage', $context);

        $dbman = $DB->get_manager();

        // Get users with tool_certificate certificates.
        $toolcertusers = [];
        if ($dbman->table_exists('tool_certificate_issues')) {
            try {
                $sql = "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email, COUNT(tci.id) as tool_certificate_count
                        FROM {user} u
                        INNER JOIN {tool_certificate_issues} tci ON u.id = tci.userid
                        WHERE u.deleted = 0
                        GROUP BY u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                 u.middlename, u.alternatename, u.email";
                $toolcertusers = $DB->get_records_sql($sql);
            } catch (Exception $e) {
                debugging('get_users_with_certificates: tool_certificate query failed: ' . $e->getMessage());
            }
        }

        // Get users with customcert certificates.
        $customcertusers = [];
        if ($dbman->table_exists('customcert_issues')) {
            try {
                $sql = "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email, COUNT(ci.id) as customcert_count
                        FROM {user} u
                        INNER JOIN {customcert_issues} ci ON u.id = ci.userid
                        WHERE u.deleted = 0
                        GROUP BY u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                 u.middlename, u.alternatename, u.email";
                $customcertusers = $DB->get_records_sql($sql);
            } catch (Exception $e) {
                debugging('get_users_with_certificates: customcert query failed: ' . $e->getMessage());
            }
        }

        // Get users with mod_certificate certificates.
        $modcertusers = [];
        if ($dbman->table_exists('certificate_issues')) {
            try {
                $sql = "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email, COUNT(ci.id) as mod_certificate_count
                        FROM {user} u
                        INNER JOIN {certificate_issues} ci ON u.id = ci.userid
                        WHERE u.deleted = 0
                        GROUP BY u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                 u.middlename, u.alternatename, u.email";
                $modcertusers = $DB->get_records_sql($sql);
            } catch (Exception $e) {
                debugging('get_users_with_certificates: mod_certificate query failed: ' . $e->getMessage());
            }
        }

        // Get users with simplecertificate certificates.
        $simplecertusers = [];
        if ($dbman->table_exists('simplecertificate_issues')) {
            try {
                $sql = "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email, COUNT(si.id) as simplecertificate_count
                        FROM {user} u
                        INNER JOIN {simplecertificate_issues} si ON u.id = si.userid
                        WHERE u.deleted = 0 AND (si.timedeleted IS NULL OR si.timedeleted = 0)
                        GROUP BY u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                 u.middlename, u.alternatename, u.email";
                $simplecertusers = $DB->get_records_sql($sql);
            } catch (Exception $e) {
                debugging('get_users_with_certificates: simplecertificate query failed: ' . $e->getMessage());
            }
        }

        // Get users with mod_certificatebeautiful certificates.
        $certificatebeautifulusers = [];
        if ($dbman->table_exists('certificatebeautiful_issue')) {
            try {
                $sql = "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email, COUNT(cbi.id) as certificatebeautiful_count
                        FROM {user} u
                        INNER JOIN {certificatebeautiful_issue} cbi ON u.id = cbi.userid
                        WHERE u.deleted = 0
                        GROUP BY u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                 u.middlename, u.alternatename, u.email";
                $certificatebeautifulusers = $DB->get_records_sql($sql);
            } catch (Exception $e) {
                debugging('get_users_with_certificates: certificatebeautiful query failed: ' . $e->getMessage());
            }
        }

        // Merge and combine counts.
        $allusers = [];
        $usertypes = [
            'tool_certificate_count' => $toolcertusers,
            'customcert_count' => $customcertusers,
            'mod_certificate_count' => $modcertusers,
            'simplecertificate_count' => $simplecertusers,
            'certificatebeautiful_count' => $certificatebeautifulusers,
        ];

        foreach ($usertypes as $countkey => $users) {
            foreach ($users as $user) {
                if (!isset($allusers[$user->id])) {
                    $allusers[$user->id] = [
                        'id' => $user->id,
                        'firstname' => $user->firstname,
                        'lastname' => $user->lastname,
                        'fullname' => fullname($user),
                        'email' => $user->email,
                        'tool_certificate_count' => 0,
                        'customcert_count' => 0,
                        'mod_certificate_count' => 0,
                        'simplecertificate_count' => 0,
                        'certificatebeautiful_count' => 0,
                    ];
                }
                $allusers[$user->id][$countkey] = $user->$countkey;
            }
        }

        // Calculate total count and format for return.
        $userlist = [];
        foreach ($allusers as $user) {
            $totalcount = $user['tool_certificate_count'] + $user['customcert_count'] +
                $user['mod_certificate_count'] + $user['simplecertificate_count'] +
                $user['certificatebeautiful_count'];
            $userlist[] = [
                'id' => $user['id'],
                'fullname' => $user['fullname'],
                'email' => $user['email'],
                'certificate_count' => $totalcount,
            ];
        }

        usort($userlist, function ($a, $b) {
            return strcmp($a['fullname'], $b['fullname']);
        });

        return $userlist;
    }

    /**
     * Count total certificates across all types.
     *
     * Uses optimized COUNT(*) SQL queries instead of loading all records.
     *
     * @return int Total number of certificates
     */
    public function count_all_certificates() {
        return $this->count_tool_certificate_issues()
             + $this->count_customcert_issues()
             + $this->count_mod_certificate_issues()
             + $this->count_simplecertificate_issues()
             + $this->count_certificatebeautiful_issues();
    }

    // Optimized counting methods (COUNT SQL).

    /**
     * Count tool_certificate issues.
     *
     * @return int
     */
    public function count_tool_certificate_issues() {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('tool_certificate_issues')) {
            return 0;
        }

        return $DB->count_records_sql(
            "SELECT COUNT(tci.id)
             FROM {tool_certificate_issues} tci
             JOIN {user} u ON u.id = tci.userid"
        );
    }

    /**
     * Count customcert issues.
     *
     * @return int
     */
    public function count_customcert_issues() {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('customcert_issues')) {
            return 0;
        }

        return $DB->count_records_sql(
            "SELECT COUNT(ci.id)
             FROM {customcert_issues} ci
             JOIN {user} u ON u.id = ci.userid
             JOIN {customcert} cc ON cc.id = ci.customcertid"
        );
    }

    /**
     * Count mod_certificate issues.
     *
     * @return int
     */
    public function count_mod_certificate_issues() {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('certificate_issues')) {
            return 0;
        }

        return $DB->count_records_sql(
            "SELECT COUNT(ci.id)
             FROM {certificate_issues} ci
             JOIN {user} u ON u.id = ci.userid
             JOIN {certificate} cert ON cert.id = ci.certificateid"
        );
    }

    /**
     * Count simplecertificate issues.
     *
     * @return int
     */
    public function count_simplecertificate_issues() {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('simplecertificate_issues')) {
            return 0;
        }

        return $DB->count_records_sql(
            "SELECT COUNT(si.id)
             FROM {simplecertificate_issues} si
             JOIN {user} u ON u.id = si.userid
             WHERE si.timedeleted IS NULL OR si.timedeleted = 0"
        );
    }

    /**
     * Count certificatebeautiful issues.
     *
     * @return int
     */
    public function count_certificatebeautiful_issues() {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('certificatebeautiful_issue')) {
            return 0;
        }

        return $DB->count_records_sql(
            "SELECT COUNT(cbi.id)
             FROM {certificatebeautiful_issue} cbi
             JOIN {user} u ON u.id = cbi.userid"
        );
    }

    // Paginated search across all certificate types.

    /**
     * Build the sub-queries for each certificate type with a unified column set.
     *
     * Each sub-query returns: id, original_id, cert_type, userid, firstname, lastname,
     * firstnamephonetic, lastnamephonetic, middlename, alternatename, email,
     * coursename, templatename, code, timecreated.
     *
     * @return array ['unions' => string[], 'params' => array] SQL fragments and params
     */
    private function build_union_subqueries() {
        global $DB;

        $dbman = $DB->get_manager();
        $unions = [];
        $params = [];

        // Source: tool_certificate.
        if ($dbman->table_exists('tool_certificate_issues')) {
            $uniqueid = $DB->sql_concat("'tc_'", 'tci.id');
            $unions[] = "SELECT {$uniqueid} AS id, tci.id AS original_id, 'tool_certificate' AS cert_type,
                                tci.userid,
                                u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                u.middlename, u.alternatename, u.email,
                                COALESCE(c.fullname, '') AS coursename,
                                COALESCE(tc.name, '') AS templatename,
                                COALESCE(tci.code, '') AS code,
                                tci.timecreated
                         FROM {tool_certificate_issues} tci
                         JOIN {user} u ON u.id = tci.userid
                         LEFT JOIN {course} c ON c.id = tci.courseid
                         LEFT JOIN {tool_certificate_templates} tc ON tc.id = tci.templateid";
        }

        // Source: customcert.
        if ($dbman->table_exists('customcert_issues')) {
            $uniqueid = $DB->sql_concat("'cc_'", 'ci.id');
            $unions[] = "SELECT {$uniqueid} AS id, ci.customcertid AS original_id, 'customcert' AS cert_type,
                                ci.userid,
                                u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                u.middlename, u.alternatename, u.email,
                                COALESCE(c.fullname, '') AS coursename,
                                COALESCE(cc.name, '') AS templatename,
                                COALESCE(ci.code, '') AS code,
                                ci.timecreated
                         FROM {customcert_issues} ci
                         JOIN {user} u ON u.id = ci.userid
                         JOIN {customcert} cc ON cc.id = ci.customcertid
                         LEFT JOIN {course} c ON c.id = cc.course";
        }

        // Source: mod_certificate.
        if ($dbman->table_exists('certificate_issues')) {
            $uniqueid = $DB->sql_concat("'mc_'", 'ci.id');
            $unions[] = "SELECT {$uniqueid} AS id, ci.certificateid AS original_id, 'mod_certificate' AS cert_type,
                                ci.userid,
                                u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                u.middlename, u.alternatename, u.email,
                                COALESCE(c.fullname, '') AS coursename,
                                COALESCE(cert.name, '') AS templatename,
                                COALESCE(ci.code, '') AS code,
                                ci.timecreated
                         FROM {certificate_issues} ci
                         JOIN {user} u ON u.id = ci.userid
                         JOIN {certificate} cert ON cert.id = ci.certificateid
                         LEFT JOIN {course} c ON c.id = cert.course";
        }

        // Source: simplecertificate.
        if ($dbman->table_exists('simplecertificate_issues')) {
            $uniqueid = $DB->sql_concat("'sc_'", 'si.id');
            $unions[] = "SELECT {$uniqueid} AS id, si.certificateid AS original_id, 'mod_simplecertificate' AS cert_type,
                                si.userid,
                                u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                u.middlename, u.alternatename, u.email,
                                COALESCE(si.coursename, '') AS coursename,
                                COALESCE(si.certificatename, '') AS templatename,
                                COALESCE(si.code, '') AS code,
                                si.timecreated
                         FROM {simplecertificate_issues} si
                         JOIN {user} u ON u.id = si.userid
                         WHERE si.timedeleted IS NULL OR si.timedeleted = 0";
        }

        // Source: certificatebeautiful.
        if ($dbman->table_exists('certificatebeautiful_issue')) {
            $uniqueid = $DB->sql_concat("'cb_'", 'cbi.id');
            $unions[] = "SELECT {$uniqueid} AS id, cbi.certificatebeautifulid AS original_id,
                                'mod_certificatebeautiful' AS cert_type,
                                cbi.userid,
                                u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                u.middlename, u.alternatename, u.email,
                                COALESCE(c.fullname, '') AS coursename,
                                COALESCE(cb.name, '') AS templatename,
                                '' AS code,
                                cbi.timecreated
                         FROM {certificatebeautiful_issue} cbi
                         JOIN {user} u ON u.id = cbi.userid
                         LEFT JOIN {certificatebeautiful} cb ON cb.id = cbi.certificatebeautifulid
                         LEFT JOIN {course_modules} cm ON cm.id = cbi.cmid
                         LEFT JOIN {course} c ON c.id = cm.course";
        }

        return ['unions' => $unions, 'params' => $params];
    }


    /**
     * Apply a search filter to the UNION query by wrapping it.
     *
     * Searches across firstname, lastname, email, coursename, templatename, code.
     *
     * @param string $basesql The UNION ALL query
     * @param string $search Search term
     * @param array $params Existing params (modified by reference)
     * @return string Wrapped SQL with search filter
     */
    private function apply_search_filter($basesql, $search, &$params) {
        global $DB;

        if (empty(trim($search))) {
            return $basesql;
        }

        $searchterm = '%' . $DB->sql_like_escape(trim($search)) . '%';
        $likefirst = $DB->sql_like('allcerts.firstname', ':sfirst', false);
        $likelast = $DB->sql_like('allcerts.lastname', ':slast', false);
        $likeemail = $DB->sql_like('allcerts.email', ':semail', false);
        $likecourse = $DB->sql_like('allcerts.coursename', ':scourse', false);
        $liketemplate = $DB->sql_like('allcerts.templatename', ':stemplate', false);
        $likecode = $DB->sql_like('allcerts.code', ':scode', false);

        $params['sfirst'] = $searchterm;
        $params['slast'] = $searchterm;
        $params['semail'] = $searchterm;
        $params['scourse'] = $searchterm;
        $params['stemplate'] = $searchterm;
        $params['scode'] = $searchterm;

        return "SELECT allcerts.* FROM ({$basesql}) allcerts
                WHERE {$likefirst} OR {$likelast} OR {$likeemail}
                   OR {$likecourse} OR {$liketemplate} OR {$likecode}";
    }

    /**
     * Search all certificates with pagination, sorting, and optional search filter.
     *
     * Uses UNION ALL across all certificate types, then applies search, sort,
     * and pagination. Returns a normalized array of certificate objects.
     *
     * @param string $search Search term (searches name, email, course, template, code)
     * @param int $offset Offset for pagination
     * @param int $limit Number of results to return
     * @param string $sort Column to sort by (timecreated, firstname, coursename, etc.)
     * @param string $order Sort direction (ASC or DESC)
     * @return array Array of certificate objects
     */
    public function search_all_certificates(
        $search = '',
        $offset = 0,
        $limit = 50,
        $sort = 'timecreated',
        $order = 'DESC'
    ) {
        global $DB;

        $subqueries = $this->build_union_subqueries();

        if (empty($subqueries['unions'])) {
            return [];
        }

        $basesql = implode(' UNION ALL ', $subqueries['unions']);
        $params = $subqueries['params'];

        // Apply search filter.
        $sql = $this->apply_search_filter($basesql, $search, $params);

        // Validate sort column.
        $allowedsorts = ['timecreated', 'firstname', 'lastname', 'email',
                         'coursename', 'templatename', 'code', 'cert_type'];
        if (!in_array($sort, $allowedsorts)) {
            $sort = 'timecreated';
        }
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        // Wrap for sorting (need outer query for UNION).
        if (strpos($sql, 'allcerts') !== false) {
            // Already wrapped by search filter.
            $sql .= " ORDER BY allcerts.{$sort} {$order}";
        } else {
            $sql = "SELECT allcerts.* FROM ({$sql}) allcerts ORDER BY allcerts.{$sort} {$order}";
        }

        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Count search results across all certificate types.
     *
     * @param string $search Search term
     * @return int Total count
     */
    public function count_search_results($search = '') {
        global $DB;

        $subqueries = $this->build_union_subqueries();

        if (empty($subqueries['unions'])) {
            return 0;
        }

        $basesql = implode(' UNION ALL ', $subqueries['unions']);
        $params = $subqueries['params'];

        // Apply search filter.
        $filteredsql = $this->apply_search_filter($basesql, $search, $params);

        if (strpos($filteredsql, 'allcerts') !== false) {
            // Already wrapped.
            $countsql = "SELECT COUNT(*) FROM ({$filteredsql}) counted";
        } else {
            $countsql = "SELECT COUNT(*) FROM ({$filteredsql}) counted";
        }

        return $DB->count_records_sql($countsql, $params);
    }

    // Méthodes filtrées pour les enseignants.

    /**
     * Construit les sous-requêtes UNION pour un enseignant en respectant
     * le périmètre cours par cours (important pour le cloisonnement par groupes).
     *
     * Génère des conditions de la forme :
     *   (courseid = X AND userid IN (u1, u2)) OR (courseid = Y AND userid IN (u1, u2, u3))
     *
     * Cela garantit que dans le cours X, seuls u1 et u2 sont visibles même
     * si u3 est visible dans un autre cours Y.
     *
     * @param array $scope Tableau [courseid => [userid, ...]] retourné par certificate_access
     * @return array ['unions' => string[], 'params' => array]
     */
    private function build_union_subqueries_for_teacher(array $scope) {
        global $DB;

        if (empty($scope)) {
            return ['unions' => [], 'params' => []];
        }

        $dbman = $DB->get_manager();
        $unions = [];
        $params = [];

        // Construire les conditions (course = X AND userid IN (...)) pour chaque cours.
        $courseconditions = [];
        $prefixcounter = 0;
        foreach ($scope as $courseid => $userids) {
            if (empty($userids)) {
                continue;
            }
            $prefix = 'uid' . ($prefixcounter++);
            [$uinsql, $uinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, $prefix);
            $params = array_merge($params, $uinparams);
            $ckey = 'cid' . ($prefixcounter - 1);
            $params[$ckey] = (int) $courseid;
            $courseconditions[] = "(:{$ckey} AND u.id {$uinsql})";
        }

        if (empty($courseconditions)) {
            return ['unions' => [], 'params' => []];
        }

        // Listes plates pour le filtrage par cours (WHERE c.id IN (...)).
        $courseids = array_keys($scope);
        $alluserids = [];
        foreach ($scope as $uids) {
            $alluserids = array_merge($alluserids, $uids);
        }
        $alluserids = array_unique($alluserids);

        // Clause cours.
        [$courseinsql, $courseinparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'tcid');
        $params = array_merge($params, $courseinparams);

        // Clause users (liste plate — le filtrage cours×users est fait par courseconditions sur u.id).
        [$userinsql, $userinparams] = $DB->get_in_or_equal($alluserids, SQL_PARAMS_NAMED, 'tuid');
        $params = array_merge($params, $userinparams);

        // Construire la condition combinée course+user.
        // On génère pour chaque sous-requête : WHERE c.id IN (...) AND u.id IN (...)
        // PUIS on filtre a posteriori avec courseconditions si besoin.
        // Méthode plus robuste : on utilise EXISTS ou on génère la condition directement dans le WHERE.
        //
        // Pour rester compatible SQL standard on génère :
        // WHERE (c.id = X1 AND ci.userid IN (u1,u2)) OR (c.id = X2 AND ci.userid IN (u1,u2,u3)).

        // Reconstruire les conditions avec le bon alias de colonne pour chaque type de table.
        // Alias : 'coursecol' = colonne du courseid dans la table, 'usercol' = userid.
        $buildcond = function (string $coursecolexpr, string $usercolexpr) use ($scope, $DB, &$params) {
            $parts = [];
            $pc = count($params); // Running counter for placeholder names.
            foreach ($scope as $courseid => $userids) {
                if (empty($userids)) {
                    continue;
                }
                $ck = 'bc_c' . $pc;
                $uk = 'bc_u' . $pc;
                [$uinsql, $uinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, $uk);
                $params[$ck] = (int) $courseid;
                $params = array_merge($params, $uinparams);
                $parts[] = "({$coursecolexpr} = :{$ck} AND {$usercolexpr} {$uinsql})";
                $pc++;
            }
            return empty($parts) ? '1=0' : '(' . implode(' OR ', $parts) . ')';
        };

        // Source: tool_certificate.
        if ($dbman->table_exists('tool_certificate_issues')) {
            $cond = $buildcond('tci.courseid', 'tci.userid');
            $uniqueid = $DB->sql_concat("'tc_'", 'tci.id');
            $unions[] = "SELECT {$uniqueid} AS id, tci.id AS original_id, 'tool_certificate' AS cert_type,
                                tci.userid,
                                u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                u.middlename, u.alternatename, u.email,
                                COALESCE(c.fullname, '') AS coursename,
                                COALESCE(tc.name, '') AS templatename,
                                COALESCE(tci.code, '') AS code,
                                tci.timecreated
                         FROM {tool_certificate_issues} tci
                         JOIN {user} u ON u.id = tci.userid
                         LEFT JOIN {course} c ON c.id = tci.courseid
                         LEFT JOIN {tool_certificate_templates} tc ON tc.id = tci.templateid
                         WHERE {$cond}";
        }

        // Source: customcert.
        if ($dbman->table_exists('customcert_issues')) {
            $cond = $buildcond('cc.course', 'ci.userid');
            $uniqueid = $DB->sql_concat("'cc_'", 'ci.id');
            $unions[] = "SELECT {$uniqueid} AS id, ci.customcertid AS original_id, 'customcert' AS cert_type,
                                ci.userid,
                                u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                u.middlename, u.alternatename, u.email,
                                COALESCE(c.fullname, '') AS coursename,
                                COALESCE(cc.name, '') AS templatename,
                                COALESCE(ci.code, '') AS code,
                                ci.timecreated
                         FROM {customcert_issues} ci
                         JOIN {user} u ON u.id = ci.userid
                         JOIN {customcert} cc ON cc.id = ci.customcertid
                         LEFT JOIN {course} c ON c.id = cc.course
                         WHERE {$cond}";
        }

        // Source: mod_certificate.
        if ($dbman->table_exists('certificate_issues')) {
            $cond = $buildcond('cert.course', 'ci.userid');
            $uniqueid = $DB->sql_concat("'mc_'", 'ci.id');
            $unions[] = "SELECT {$uniqueid} AS id, ci.certificateid AS original_id, 'mod_certificate' AS cert_type,
                                ci.userid,
                                u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                u.middlename, u.alternatename, u.email,
                                COALESCE(c.fullname, '') AS coursename,
                                COALESCE(cert.name, '') AS templatename,
                                COALESCE(ci.code, '') AS code,
                                ci.timecreated
                         FROM {certificate_issues} ci
                         JOIN {user} u ON u.id = ci.userid
                         JOIN {certificate} cert ON cert.id = ci.certificateid
                         LEFT JOIN {course} c ON c.id = cert.course
                         WHERE {$cond}";
        }

        // Source: simplecertificate, which stores no course id: filtered by user only.
        if ($dbman->table_exists('simplecertificate_issues')) {
            [$scuserinsql, $scuserinparams] = $DB->get_in_or_equal($alluserids, SQL_PARAMS_NAMED, 'scuid');
            $params = array_merge($params, $scuserinparams);
            $uniqueid = $DB->sql_concat("'sc_'", 'si.id');
            $unions[] = "SELECT {$uniqueid} AS id, si.certificateid AS original_id, 'mod_simplecertificate' AS cert_type,
                                si.userid,
                                u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                u.middlename, u.alternatename, u.email,
                                COALESCE(si.coursename, '') AS coursename,
                                COALESCE(si.certificatename, '') AS templatename,
                                COALESCE(si.code, '') AS code,
                                si.timecreated
                         FROM {simplecertificate_issues} si
                         JOIN {user} u ON u.id = si.userid
                         WHERE si.userid {$scuserinsql}
                           AND (si.timedeleted IS NULL OR si.timedeleted = 0)";
        }

        // Source: certificatebeautiful.
        if ($dbman->table_exists('certificatebeautiful_issue')) {
            $cond = $buildcond('cm.course', 'cbi.userid');
            $uniqueid = $DB->sql_concat("'cb_'", 'cbi.id');
            $unions[] = "SELECT {$uniqueid} AS id, cbi.certificatebeautifulid AS original_id,
                                'mod_certificatebeautiful' AS cert_type,
                                cbi.userid,
                                u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                u.middlename, u.alternatename, u.email,
                                COALESCE(c.fullname, '') AS coursename,
                                COALESCE(cb.name, '') AS templatename,
                                '' AS code,
                                cbi.timecreated
                         FROM {certificatebeautiful_issue} cbi
                         JOIN {user} u ON u.id = cbi.userid
                         LEFT JOIN {certificatebeautiful} cb ON cb.id = cbi.certificatebeautifulid
                         LEFT JOIN {course_modules} cm ON cm.id = cbi.cmid
                         LEFT JOIN {course} c ON c.id = cm.course
                         WHERE {$cond}";
        }

        return ['unions' => $unions, 'params' => $params];
    }

    /**
     * Recherche paginée de certificats filtrée pour un enseignant.
     *
     * Variante de search_all_certificates() restreinte au périmètre de l'enseignant
     * (cours enseignés + étudiants visibles selon les groupes).
     *
     * @param array  $scope     Course id => list of the user ids visible in that course
     * @param string $search    Terme de recherche
     * @param int    $offset    Décalage pour la pagination
     * @param int    $limit     Nombre de résultats à retourner
     * @param string $sort      Colonne de tri
     * @param string $order     Sens du tri (ASC ou DESC)
     * @return array Tableau d'objets certificat
     */
    public function search_all_certificates_for_teacher(
        array $scope,
        string $search = '',
        int $offset = 0,
        int $limit = 50,
        string $sort = 'timecreated',
        string $order = 'DESC'
    ): array {
        global $DB;

        if (empty($scope)) {
            return [];
        }

        $subqueries = $this->build_union_subqueries_for_teacher($scope);

        if (empty($subqueries['unions'])) {
            return [];
        }

        $basesql = implode(' UNION ALL ', $subqueries['unions']);
        $params = $subqueries['params'];

        // Appliquer le filtre de recherche textuelle.
        $sql = $this->apply_search_filter($basesql, $search, $params);

        // Valider la colonne de tri (liste blanche).
        $allowedsorts = ['timecreated', 'firstname', 'lastname', 'email',
                         'coursename', 'templatename', 'code', 'cert_type'];
        if (!in_array($sort, $allowedsorts)) {
            $sort = 'timecreated';
        }
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        // Envelopper pour le tri (nécessaire avec UNION).
        if (strpos($sql, 'allcerts') !== false) {
            $sql .= " ORDER BY allcerts.{$sort} {$order}";
        } else {
            $sql = "SELECT allcerts.* FROM ({$sql}) allcerts ORDER BY allcerts.{$sort} {$order}";
        }

        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Compte les résultats de recherche filtrés pour un enseignant.
     *
     * @param array  $scope  Course id => list of the user ids visible in that course
     * @param string $search Terme de recherche
     * @return int Total des résultats
     */
    public function count_search_results_for_teacher(
        array $scope,
        string $search = ''
    ): int {
        global $DB;

        if (empty($scope)) {
            return 0;
        }

        $subqueries = $this->build_union_subqueries_for_teacher($scope);

        if (empty($subqueries['unions'])) {
            return 0;
        }

        $basesql = implode(' UNION ALL ', $subqueries['unions']);
        $params = $subqueries['params'];

        $filteredsql = $this->apply_search_filter($basesql, $search, $params);
        $countsql = "SELECT COUNT(*) FROM ({$filteredsql}) counted";

        return (int) $DB->count_records_sql($countsql, $params);
    }

    /**
     * Retourne la liste des cours avec certificats filtrée pour un enseignant.
     *
     * Variante de get_courses_with_certificates() sans require_capability(:manage)
     * et restreinte aux cours de l'enseignant.
     *
     * @param array $scope Course id => list of the user ids visible in that course
     * @return array Tableau de cours avec les compteurs de certificats
     */
    public function get_courses_with_certificates_for_teacher(array $scope): array {
        global $DB;

        if (empty($scope)) {
            return [];
        }

        $courseids = array_keys($scope);
        $dbman = $DB->get_manager();

        // Pour chaque cours, on ne compte que les certificats des utilisateurs
        // visibles par l'enseignant dans ce cours (respect du cloisonnement par groupes).
        $allcourses = [];

        foreach ($courseids as $courseid) {
            $userids = $scope[$courseid] ?? [];
            if (empty($userids)) {
                continue;
            }

            $coursecount = 0;

            // Source: tool_certificate.
            if ($dbman->table_exists('tool_certificate_issues')) {
                [$userinql, $userinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'tu');
                try {
                    $sql = "SELECT COUNT(tci.id)
                            FROM {tool_certificate_issues} tci
                            WHERE tci.courseid = :tccourseid AND tci.userid {$userinql}";
                    $coursecount += (int)$DB->count_records_sql(
                        $sql,
                        array_merge(['tccourseid' => $courseid], $userinparams)
                    );
                } catch (Exception $e) {
                    debugging('get_courses_with_certificates_for_teacher: tool_certificate failed: ' . $e->getMessage());
                }
            }

            // Source: customcert.
            if ($dbman->table_exists('customcert_issues')) {
                [$userinql, $userinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'cu');
                try {
                    $sql = "SELECT COUNT(ci.id)
                            FROM {customcert_issues} ci
                            JOIN {customcert} cc ON cc.id = ci.customcertid
                            WHERE cc.course = :cccourseid AND ci.userid {$userinql}";
                    $coursecount += (int)$DB->count_records_sql(
                        $sql,
                        array_merge(['cccourseid' => $courseid], $userinparams)
                    );
                } catch (Exception $e) {
                    debugging('get_courses_with_certificates_for_teacher: customcert failed: ' . $e->getMessage());
                }
            }

            // Source: mod_certificate.
            if ($dbman->table_exists('certificate_issues')) {
                [$userinql, $userinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'mc');
                try {
                    $sql = "SELECT COUNT(ci.id)
                            FROM {certificate_issues} ci
                            JOIN {certificate} cert ON cert.id = ci.certificateid
                            WHERE cert.course = :mccourseid AND ci.userid {$userinql}";
                    $coursecount += (int)$DB->count_records_sql(
                        $sql,
                        array_merge(['mccourseid' => $courseid], $userinparams)
                    );
                } catch (Exception $e) {
                    debugging('get_courses_with_certificates_for_teacher: mod_certificate failed: ' . $e->getMessage());
                }
            }

            if ($coursecount === 0) {
                continue; // Ne pas inclure les cours sans certificats accessibles.
            }

            // Récupération du nom du cours.
            $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname');
            if (!$course) {
                continue;
            }

            $allcourses[$courseid] = [
                'id'                => $course->id,
                'fullname'          => format_string(
                    $course->fullname,
                    true,
                    ['context' => context_system::instance()]
                ),
                'shortname'         => $course->shortname,
                'certificate_count' => $coursecount,
            ];
        }

        $courselist = array_values($allcourses);
        usort($courselist, function ($a, $b) {
            return strcmp($a['fullname'], $b['fullname']);
        });

        return $courselist;
    }

    /**
     * Retourne la liste des utilisateurs avec certificats filtrée pour un enseignant.
     *
     * Variante de get_users_with_certificates() sans require_capability(:manage)
     * et restreinte aux utilisateurs visibles par l'enseignant.
     *
     * @param int[] $userids IDs d'étudiants visibles par l'enseignant
     * @return array Tableau d'utilisateurs avec les compteurs de certificats
     */
    public function get_users_with_certificates_for_teacher(array $userids): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        $dbman = $DB->get_manager();

        // Source: tool_certificate.
        $toolcertusers = [];
        if ($dbman->table_exists('tool_certificate_issues')) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'tuid');
            try {
                $sql = "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email, COUNT(tci.id) as tool_certificate_count
                        FROM {user} u
                        INNER JOIN {tool_certificate_issues} tci ON u.id = tci.userid
                        WHERE u.deleted = 0 AND u.id {$insql}
                        GROUP BY u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                 u.middlename, u.alternatename, u.email";
                $toolcertusers = $DB->get_records_sql($sql, $inparams);
            } catch (Exception $e) {
                debugging('get_users_with_certificates_for_teacher: tool_certificate failed: ' . $e->getMessage());
            }
        }

        // Source: customcert.
        $customcertusers = [];
        if ($dbman->table_exists('customcert_issues')) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'ccuid');
            try {
                $sql = "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email, COUNT(ci.id) as customcert_count
                        FROM {user} u
                        INNER JOIN {customcert_issues} ci ON u.id = ci.userid
                        WHERE u.deleted = 0 AND u.id {$insql}
                        GROUP BY u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                 u.middlename, u.alternatename, u.email";
                $customcertusers = $DB->get_records_sql($sql, $inparams);
            } catch (Exception $e) {
                debugging('get_users_with_certificates_for_teacher: customcert failed: ' . $e->getMessage());
            }
        }

        // Fusionner les résultats.
        $allusers = [];
        $usertypes = [
            'tool_certificate_count' => $toolcertusers,
            'customcert_count'       => $customcertusers,
        ];

        foreach ($usertypes as $countkey => $users) {
            foreach ($users as $user) {
                if (!isset($allusers[$user->id])) {
                    $allusers[$user->id] = [
                        'id'                     => $user->id,
                        'firstname'              => $user->firstname,
                        'lastname'               => $user->lastname,
                        'fullname'               => fullname($user),
                        'email'                  => $user->email,
                        'tool_certificate_count' => 0,
                        'customcert_count'       => 0,
                        'certificate_count'      => 0,
                    ];
                }
                $allusers[$user->id][$countkey] = $user->$countkey;
            }
        }

        $userlist = [];
        foreach ($allusers as $user) {
            $totalcount = $user['tool_certificate_count'] + $user['customcert_count'];
            $user['certificate_count'] = $totalcount;
            $userlist[] = $user;
        }

        usort($userlist, function ($a, $b) {
            return strcmp($a['fullname'], $b['fullname']);
        });

        return $userlist;
    }
}
