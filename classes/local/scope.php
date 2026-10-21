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
 * Who may see which certificates.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

/**
 * The certificates one user may see, as SQL over (user id, course id) pairs.
 *
 * One scope decides search, counts, menus, single downloads, request ZIPs and the background task
 * alike. The rules:
 * - the user's own certificates are always in scope;
 * - `:manage` in the system context sees every certificate;
 * - otherwise, in each course (never the site course) where the user holds `:downloadcourse` and
 *   could enter the course as `require_login()` decides it, the actively enrolled participants;
 *   under separate groups without `moodle/site:accessallgroups`, only those who share one of the
 *   user's groups (in the course's default grouping) in that course.
 * The optional course and profile user narrow every rule above, the user's own certificates
 * included.
 *
 * Participants stay in SQL so that a large course never becomes a placeholder list (PostgreSQL
 * stops at 65535 placeholders); only the user's courses and groups are listed.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope {
    /** @var int Running counter that keeps placeholder names unique across get_sql() calls. */
    private static int $counter = 0;

    /** @var int The user the scope belongs to. */
    private int $userid;

    /** @var bool Whether the user holds `:manage` in the system context. */
    private bool $manager;

    /** @var int|null The course every rule is narrowed to. */
    private ?int $courseid;

    /** @var int|null The certificate owner every rule is narrowed to. */
    private ?int $profileuserid;

    /**
     * @var array Course id => null (every actively enrolled participant) or int[] (the group ids
     *      whose actively enrolled members are visible; empty when the user is in no group).
     */
    private array $courses;

    /**
     * Use {@see self::for_user()}.
     *
     * @param int $userid The user the scope belongs to.
     * @param bool $manager Whether the user is a manager.
     * @param int|null $courseid The course narrowing.
     * @param int|null $profileuserid The certificate owner narrowing.
     * @param array $courses Course id => null or group ids, as {@see self::$courses}.
     */
    private function __construct(int $userid, bool $manager, ?int $courseid, ?int $profileuserid, array $courses) {
        $this->userid = $userid;
        $this->manager = $manager;
        $this->courseid = $courseid;
        $this->profileuserid = $profileuserid;
        $this->courses = $courses;
    }

    /**
     * Build the scope of a user from the capabilities and enrolments as they are now.
     *
     * @param int $userid The user.
     * @param int|null $courseid Keep this course only (0 or null: every course).
     * @param int|null $profileuserid Keep this certificate owner only (0 or null: every owner).
     * @return self
     */
    public static function for_user(int $userid, ?int $courseid = null, ?int $profileuserid = null): self {
        $courseid = $courseid ?: null;
        $profileuserid = $profileuserid ?: null;

        $manager = has_capability('block/download_certificates:manage', \context_system::instance(), $userid);
        $courses = $manager ? [] : self::load_courses($userid, $courseid);

        return new self($userid, $manager, $courseid, $profileuserid, $courses);
    }

    /**
     * The courses where the user may download participants' certificates, with their group rule.
     *
     * @param int $userid The user.
     * @param int|null $onlycourseid The course narrowing.
     * @return array Course id => null or group ids, as {@see self::$courses}.
     */
    private static function load_courses(int $userid, ?int $onlycourseid): array {
        $records = get_user_capability_course(
            'block/download_certificates:downloadcourse',
            $userid,
            true,
            'visible,groupmode,defaultgroupingid,ctxid,ctxpath,ctxdepth,ctxlevel,ctxinstance,ctxlocked'
        );
        if (!$records) {
            return [];
        }

        $courses = [];
        foreach ($records as $course) {
            \context_helper::preload_from_record($course);
            $id = (int) $course->id;
            if ($id === (int) SITEID || ($onlycourseid !== null && $id !== $onlycourseid)) {
                continue;
            }
            $context = \context_course::instance($id);
            if (!self::can_enter($course, $context, $userid)) {
                continue;
            }
            if (
                (int) groups_get_course_groupmode($course) === SEPARATEGROUPS
                && !has_capability('moodle/site:accessallgroups', $context, $userid)
            ) {
                // Hidden-visibility groups count: membership, not what the viewer may list, decides
                // the separation, and the result must not depend on the $USER running the code.
                $usergroups = groups_get_user_groups($id, $userid, true);
                $groupids = $usergroups[(int) $course->defaultgroupingid] ?? [];
                $courses[$id] = array_values(array_map('intval', $groupids));
            } else {
                $courses[$id] = null;
            }
        }
        return $courses;
    }

    /**
     * Whether the user could enter the course, as `require_login()` decides it for a course.
     *
     * @param \stdClass $course The course record with `visible`.
     * @param \context_course $context The course context.
     * @param int $userid The user.
     * @return bool
     */
    private static function can_enter(\stdClass $course, \context_course $context, int $userid): bool {
        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context, $userid)) {
            return false;
        }
        return is_enrolled($context, $userid, '', true) || has_capability('moodle/course:view', $context, $userid);
    }

    /**
     * The user the scope belongs to.
     *
     * @return int
     */
    public function get_user_id(): int {
        return $this->userid;
    }

    /**
     * The course narrowing.
     *
     * @return int|null
     */
    public function get_course_id(): ?int {
        return $this->courseid;
    }

    /**
     * The certificate owner narrowing.
     *
     * @return int|null
     */
    public function get_profile_user_id(): ?int {
        return $this->profileuserid;
    }

    /**
     * Whether the user holds `:manage` in the system context.
     *
     * @return bool
     */
    public function is_manager(): bool {
        return $this->manager;
    }

    /**
     * The courses where the user holds `:downloadcourse` and could enter, for menus.
     *
     * Empty for a manager, who is not limited to courses.
     *
     * @return int[]
     */
    public function get_course_ids(): array {
        return array_map('intval', array_keys($this->courses));
    }

    /**
     * Whether the scope reaches anybody's certificates besides the user's own.
     *
     * False for a teacher whose only courses are under separate groups with no group of theirs.
     *
     * @return bool
     */
    public function can_see_others(): bool {
        if ($this->manager) {
            return true;
        }
        foreach ($this->courses as $groupids) {
            if ($groupids === null || $groupids) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a certificate of this owner in this course is in scope: the single-download check.
     *
     * It runs the SQL of {@see self::get_sql()} over the pair, so that it can never disagree with
     * the lists.
     *
     * @param int $userid The certificate owner.
     * @param int $courseid The certificate's course (0 when it has none).
     * @return bool
     */
    public function can_see(int $userid, int $courseid): bool {
        global $DB;

        // The course goes in as an integer literal: the scope SQL repeats the column, and a named
        // placeholder may appear once per statement.
        [$where, $params] = $this->get_sql('u.id', (string) $courseid);
        $params['bdccanseeuserid'] = $userid;
        return $DB->record_exists_sql(
            "SELECT 1 FROM {user} u WHERE u.id = :bdccanseeuserid AND {$where}",
            $params
        );
    }

    /**
     * The condition to AND into a query over certificates.
     *
     * Placeholder names are unique per call, so two scopes can share one statement.
     *
     * @param string $useridcol The SQL expression of the certificate owner's id.
     * @param string $courseidcol The SQL expression of the certificate's course id.
     * @return array [string $where, array $params]
     */
    public function get_sql(string $useridcol, string $courseidcol): array {
        $p = 'bdcscope' . (++self::$counter) . '_';
        $params = [];
        $conditions = [];

        if ($this->manager) {
            $conditions[] = '1 = 1';
        } else {
            $alternatives = ["{$useridcol} = :{$p}own"];
            $params[$p . 'own'] = $this->userid;
            if ($coursesql = $this->get_courses_sql($p, $useridcol, $courseidcol, $params)) {
                $alternatives[] = $coursesql;
            }
            $conditions[] = '(' . implode(' OR ', $alternatives) . ')';
        }

        if ($this->courseid !== null) {
            $conditions[] = "{$courseidcol} = :{$p}course";
            $params[$p . 'course'] = $this->courseid;
        }
        if ($this->profileuserid !== null) {
            $conditions[] = "{$useridcol} = :{$p}profileuser";
            $params[$p . 'profileuser'] = $this->profileuserid;
        }

        return [implode(' AND ', $conditions), $params];
    }

    /**
     * The participants of the user's courses: active enrolment, then the group rule per course.
     *
     * The enrolment test mirrors the `$onlyactive` branch of core's `get_enrolled_join()` (status
     * of the enrolment and of its instance, start and end dates, time rounded to 100 s), applied
     * per row instead of per context so that every course shares one subquery.
     *
     * @param string $p The placeholder prefix of this call.
     * @param string $useridcol The SQL expression of the certificate owner's id.
     * @param string $courseidcol The SQL expression of the certificate's course id.
     * @param array $params The parameters, extended in place.
     * @return string|null Null when no course reaches anybody.
     */
    private function get_courses_sql(string $p, string $useridcol, string $courseidcol, array &$params): ?string {
        global $DB;

        $everyone = [];
        $groupids = [];
        foreach ($this->courses as $courseid => $groups) {
            if ($groups === null) {
                $everyone[] = (int) $courseid;
            } else {
                $groupids = array_merge($groupids, $groups);
            }
        }

        $reach = [];
        if ($everyone) {
            [$insql, $inparams] = $DB->get_in_or_equal($everyone, SQL_PARAMS_NAMED, $p . 'all');
            $reach[] = "{$courseidcol} {$insql}";
            $params += $inparams;
        }
        if ($groupids) {
            // The group must belong to the certificate's course: a group id of course A must not
            // admit the same member's certificate of course B.
            [$insql, $inparams] = $DB->get_in_or_equal(array_unique($groupids), SQL_PARAMS_NAMED, $p . 'grp');
            $reach[] = "EXISTS (SELECT 1
                                  FROM {groups_members} {$p}gm
                                  JOIN {groups} {$p}g ON {$p}g.id = {$p}gm.groupid
                                 WHERE {$p}gm.userid = {$useridcol}
                                   AND {$p}g.courseid = {$courseidcol}
                                   AND {$p}gm.groupid {$insql})";
            $params += $inparams;
        }
        if (!$reach) {
            return null;
        }

        $now = round(time(), -2);
        $params += [
            $p . 'active' => ENROL_USER_ACTIVE,
            $p . 'enabled' => ENROL_INSTANCE_ENABLED,
            $p . 'now1' => $now,
            $p . 'now2' => $now,
        ];
        $enrolled = "EXISTS (SELECT 1
                               FROM {user_enrolments} {$p}ue
                               JOIN {enrol} {$p}e ON {$p}e.id = {$p}ue.enrolid
                              WHERE {$p}ue.userid = {$useridcol}
                                AND {$p}e.courseid = {$courseidcol}
                                AND {$p}ue.status = :{$p}active
                                AND {$p}e.status = :{$p}enabled
                                AND {$p}ue.timestart < :{$p}now1
                                AND ({$p}ue.timeend = 0 OR {$p}ue.timeend > :{$p}now2))";

        return '(' . $enrolled . ' AND (' . implode(' OR ', $reach) . '))';
    }
}
