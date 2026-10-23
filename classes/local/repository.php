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
 * Every read of certificates.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

use block_download_certificates\local\source\registry;
use block_download_certificates\local\source\source;

/**
 * Every read of certificates: search table, counts, menus, single downloads, ZIP lists.
 *
 * Each query runs over the `UNION ALL` of the available sources (alias `c`, joined to its owner as
 * `u`), with the scope and the filter ANDed in, so that a list, its count and the ZIP built from it
 * can never disagree.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository {
    /** @var array Sort key => column. */
    private const SORTS = [
        'timecreated' => 'c.timecreated',
        'firstname' => 'u.firstname',
        'lastname' => 'u.lastname',
        'email' => 'u.email',
        'coursename' => 'c.coursename',
        'templatename' => 'c.templatename',
        'code' => 'c.code',
        'type' => 'c.type',
    ];

    /** @var int Running counter that keeps filter placeholder names unique per statement. */
    private static int $counter = 0;

    /** @var source[] The sources the union is built from, keyed by type. */
    private array $sources;

    /**
     * Constructor.
     *
     * @param source[]|null $sources Sources keyed by type; null for every available one.
     */
    public function __construct(?array $sources = null) {
        $this->sources = $sources ?? registry::get_available();
    }

    /**
     * The sources the union is built from.
     *
     * @return source[] Keyed by type.
     */
    public function get_sources(): array {
        return $this->sources;
    }

    /**
     * One page of certificates.
     *
     * @param scope $scope Who is asking.
     * @param filter $filter What to keep.
     * @param string $sort A key of {@see self::SORTS}; anything else sorts by date.
     * @param string $order `ASC` or `DESC` (anything else is `DESC`).
     * @param int $offset Rows to skip.
     * @param int $limit Rows to return (0: all).
     * @return \stdClass[] Rows of the shared shape plus the owner's name fields and e-mail.
     */
    public function search(scope $scope, filter $filter, string $sort, string $order, int $offset, int $limit): array {
        global $DB;

        if (!$base = $this->get_base_sql($scope, $filter)) {
            return [];
        }
        [$from, $where, $params] = $base;

        $sortcol = self::SORTS[$sort] ?? self::SORTS['timecreated'];
        $direction = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

        // The unique id breaks ties, so that pages never repeat or skip a row.
        $sql = "SELECT c.uniqueid, c.issueid, c.type, c.userid, c.courseid, c.coursename, c.templatename,
                       c.code, c.timecreated, u.email, {$namefields}
                  {$from}
                 WHERE {$where}
              ORDER BY {$sortcol} {$direction}, c.uniqueid ASC";

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params, max(0, $offset), max(0, $limit)) as $row) {
            $rows[] = source::normalise($row);
        }
        return $rows;
    }

    /**
     * The number of certificates {@see self::search()} pages through.
     *
     * @param scope $scope Who is asking.
     * @param filter $filter What to keep.
     * @return int
     */
    public function count(scope $scope, filter $filter): int {
        global $DB;

        if (!$base = $this->get_base_sql($scope, $filter)) {
            return 0;
        }
        [$from, $where, $params] = $base;
        return (int) $DB->count_records_sql("SELECT COUNT(1) {$from} WHERE {$where}", $params);
    }

    /**
     * The certificates a ZIP is built from, oldest first, in a stable order.
     *
     * @param scope $scope Who is asking.
     * @param filter $filter What to keep.
     * @return array List of [string $type, int $issueid].
     */
    public function list_issue_refs(scope $scope, filter $filter): array {
        global $DB;

        if (!$base = $this->get_base_sql($scope, $filter)) {
            return [];
        }
        [$from, $where, $params] = $base;

        $rs = $DB->get_recordset_sql(
            "SELECT c.uniqueid, c.type, c.issueid {$from} WHERE {$where} ORDER BY c.timecreated ASC, c.uniqueid ASC",
            $params
        );
        $refs = [];
        foreach ($rs as $row) {
            $refs[] = [(string) $row->type, (int) $row->issueid];
        }
        $rs->close();
        return $refs;
    }

    /**
     * The courses that hold certificates in scope, for menus. The site course is left out.
     *
     * @param scope $scope Who is asking.
     * @return \stdClass[] Keyed by course id: id, fullname, shortname, name (formatted, not escaped: the
     *         templates escape it once), total.
     */
    public function get_courses(scope $scope): array {
        global $DB;

        if (!$base = $this->get_base_sql($scope, new filter())) {
            return [];
        }
        [$from, $where, $params] = $base;
        $params['bdcmenusiteid'] = SITEID;
        $params['bdcmenuctxlevel'] = CONTEXT_COURSE;
        $ctxfields = \context_helper::get_preload_record_columns_sql('ctx');

        $sql = "SELECT co.id, co.fullname, co.shortname, agg.total, {$ctxfields}
                  FROM (SELECT c.courseid, COUNT(1) AS total
                          {$from}
                         WHERE {$where}
                      GROUP BY c.courseid) agg
                  JOIN {course} co ON co.id = agg.courseid
                  JOIN {context} ctx ON ctx.instanceid = co.id AND ctx.contextlevel = :bdcmenuctxlevel
                 WHERE co.id <> :bdcmenusiteid
              ORDER BY co.fullname ASC, co.id ASC";

        $courses = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            \context_helper::preload_from_record($record);
            $id = (int) $record->id;
            $courses[$id] = (object) [
                'id' => $id,
                'fullname' => $record->fullname,
                'shortname' => $record->shortname,
                'name' => format_string($record->fullname, true, ['context' => \context_course::instance($id), 'escape' => false]),
                'total' => (int) $record->total,
            ];
        }
        return $courses;
    }

    /**
     * The owners of certificates in scope, for menus.
     *
     * @param scope $scope Who is asking.
     * @return \stdClass[] Keyed by user id: id, email, the name fields, fullname, total.
     */
    public function get_users(scope $scope): array {
        global $DB;

        if (!$base = $this->get_base_sql($scope, new filter())) {
            return [];
        }
        [$from, $where, $params] = $base;
        $namefields = \core_user\fields::for_name()->get_sql('mu', false, '', '', false)->selects;

        $sql = "SELECT mu.id, mu.email, {$namefields}, agg.total
                  FROM (SELECT c.userid, COUNT(1) AS total
                          {$from}
                         WHERE {$where}
                      GROUP BY c.userid) agg
                  JOIN {user} mu ON mu.id = agg.userid
              ORDER BY mu.lastname ASC, mu.firstname ASC, mu.id ASC";

        $users = [];
        foreach ($DB->get_records_sql($sql, $params) as $user) {
            $user->id = (int) $user->id;
            $user->total = (int) $user->total;
            $user->fullname = fullname($user);
            $users[$user->id] = $user;
        }
        return $users;
    }

    /**
     * The cohorts whose members hold certificates in scope, for menus.
     *
     * A cohort is listed when it is visible or the scope's user holds `moodle/cohort:view` in its
     * context: the visibility test of core's `cohort_get_available_cohorts()`, without its limit to
     * the parent contexts of a current context.
     *
     * @param scope $scope Who is asking.
     * @return \stdClass[] Keyed by cohort id: id, name (formatted in the cohort's context, not escaped),
     *         total.
     */
    public function get_cohorts(scope $scope): array {
        global $DB;

        if (!$base = $this->get_base_sql($scope, new filter())) {
            return [];
        }
        [$from, $where, $params] = $base;
        $ctxfields = \context_helper::get_preload_record_columns_sql('ctx');

        $sql = "SELECT ch.id, ch.name, ch.visible, ch.contextid, agg.total, {$ctxfields}
                  FROM (SELECT chm.cohortid, COUNT(1) AS total
                          {$from}
                          JOIN {cohort_members} chm ON chm.userid = c.userid
                         WHERE {$where}
                      GROUP BY chm.cohortid) agg
                  JOIN {cohort} ch ON ch.id = agg.cohortid
                  JOIN {context} ctx ON ctx.id = ch.contextid
              ORDER BY ch.name ASC, ch.id ASC";

        $cohorts = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            \context_helper::preload_from_record($record);
            $context = \context::instance_by_id((int) $record->contextid);
            if (!$record->visible && !has_capability('moodle/cohort:view', $context, $scope->get_user_id())) {
                continue;
            }
            $cohorts[(int) $record->id] = (object) [
                'id' => (int) $record->id,
                'name' => format_string($record->name, true, ['context' => $context, 'escape' => false]),
                'total' => (int) $record->total,
            ];
        }
        return $cohorts;
    }

    /**
     * One issue for a single download, after the scope check.
     *
     * @param scope $scope Who is asking.
     * @param string $type The source type.
     * @param int $issueid The id in the source's issue table.
     * @return \stdClass The issue in the shared shape.
     * @throws \moodle_exception `certificatenotfound` when there is no such issue, `accessdenied`
     *         when it is outside the scope.
     */
    public function get_issue_for_download(scope $scope, string $type, int $issueid): \stdClass {
        $source = $this->sources[$type] ?? null;
        $issue = $source ? $source->get_issue($issueid) : null;
        if (!$issue) {
            throw new \moodle_exception('certificatenotfound', 'block_download_certificates');
        }
        if (!$scope->can_see($issue->userid, $issue->courseid)) {
            throw new \moodle_exception('accessdenied', 'block_download_certificates');
        }
        return $issue;
    }

    /**
     * The FROM and WHERE every query shares.
     *
     * @param scope $scope Who is asking.
     * @param filter $filter What to keep.
     * @return array|null [string $from, string $where, array $params]; null when no source is available.
     */
    private function get_base_sql(scope $scope, filter $filter): ?array {
        if (!$this->sources) {
            return null;
        }

        $unions = [];
        $params = [];
        foreach ($this->sources as $source) {
            [$sql, $sourceparams] = $source->get_issue_sql();
            $unions[] = $sql;
            $params += $sourceparams;
        }
        $from = 'FROM (' . implode(' UNION ALL ', $unions) . ') c JOIN {user} u ON u.id = c.userid';

        [$scopewhere, $scopeparams] = $scope->get_sql('c.userid', 'c.courseid');
        [$filterwhere, $filterparams] = $this->get_filter_sql($filter);

        return [$from, "{$scopewhere} AND {$filterwhere}", $params + $scopeparams + $filterparams];
    }

    /**
     * The filter as a condition over `c` and `u`.
     *
     * @param filter $filter What to keep.
     * @return array [string $where, array $params]
     */
    private function get_filter_sql(filter $filter): array {
        global $DB;

        $p = 'bdcfilter' . (++self::$counter) . '_';
        $conditions = ['1 = 1'];
        $params = [];

        if ($filter->courseid) {
            $conditions[] = "c.courseid = :{$p}courseid";
            $params[$p . 'courseid'] = $filter->courseid;
        }
        if ($filter->userid) {
            $conditions[] = "c.userid = :{$p}userid";
            $params[$p . 'userid'] = $filter->userid;
        }
        if ($filter->groupid) {
            $conditions[] = "EXISTS (SELECT 1
                                       FROM {groups_members} {$p}gm
                                       JOIN {groups} {$p}g ON {$p}g.id = {$p}gm.groupid
                                      WHERE {$p}gm.userid = c.userid
                                        AND {$p}g.courseid = c.courseid
                                        AND {$p}gm.groupid = :{$p}groupid)";
            $params[$p . 'groupid'] = $filter->groupid;
        }
        if ($filter->cohortid) {
            $conditions[] = "EXISTS (SELECT 1
                                       FROM {cohort_members} {$p}cm
                                      WHERE {$p}cm.userid = c.userid
                                        AND {$p}cm.cohortid = :{$p}cohortid)";
            $params[$p . 'cohortid'] = $filter->cohortid;
        }
        if ($filter->datefrom) {
            $conditions[] = "c.timecreated >= :{$p}datefrom";
            $params[$p . 'datefrom'] = $filter->datefrom;
        }
        if ($filter->dateto) {
            $conditions[] = "c.timecreated < :{$p}dateto";
            $params[$p . 'dateto'] = $filter->dateto;
        }
        if ($filter->search !== '') {
            $like = '%' . $DB->sql_like_escape($filter->search) . '%';
            $fields = ['(' . $DB->sql_fullname('u.firstname', 'u.lastname') . ')'];
            if ($filter->searchemail) {
                $fields[] = 'u.email';
            }
            array_push($fields, 'c.coursename', 'c.templatename', 'c.code');
            $matches = [];
            foreach ($fields as $i => $field) {
                $matches[] = $DB->sql_like($field, ":{$p}search{$i}", false, false);
                $params[$p . 'search' . $i] = $like;
            }
            $conditions[] = '(' . implode(' OR ', $matches) . ')';
        }

        return [implode(' AND ', $conditions), $params];
    }
}
