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
 * What the pages and scripts share: guards, ZIP requests, responses.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

/**
 * The entry points' side of the library: who may open the panels, which ZIP a request may ask
 * for, how request values become a filter, and how a failure is told to the client.
 *
 * A ZIP request names one of {@see self::TYPES}; the same check runs when the request arrives
 * (synchronous download or task creation) and again when the background task runs.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class request {
    /** @var string[] The ZIP request types the pages and the task table use. */
    public const TYPES = ['all', 'course', 'user', 'cohort', 'range'];

    /** @var string[] The types that reach beyond the courses a teacher holds. */
    private const MANAGER_TYPES = ['all', 'cohort', 'range'];

    /** @var array Sort keys the table script sends that the repository names differently. */
    private const SORT_ALIASES = ['cert_type' => 'type'];

    /**
     * Refuse the guest account, after `require_login()`.
     *
     * @throws \moodle_exception `noguest` for the guest account.
     */
    public static function reject_guest(): void {
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
    }

    /**
     * Whether the scope opens a panel page: a manager, or a user with a course of their own.
     *
     * @param scope $scope Who is asking.
     * @return bool
     */
    public static function can_open_panel(scope $scope): bool {
        return $scope->is_manager() || (bool) $scope->get_course_ids();
    }

    /**
     * The page a user returns to after a refused download.
     *
     * @param scope $scope Who is asking.
     * @return \moodle_url
     */
    public static function panel_url(scope $scope): \moodle_url {
        if ($scope->is_manager()) {
            return new \moodle_url('/blocks/download_certificates/index.php');
        }
        if ($scope->get_course_ids()) {
            return new \moodle_url('/blocks/download_certificates/index_teacher.php');
        }
        return new \moodle_url('/my/');
    }

    /**
     * The repository sort key for the one the table script sends.
     *
     * @param string $sort The cleaned request value.
     * @return string
     */
    public static function sort_key(string $sort): string {
        return self::SORT_ALIASES[$sort] ?? $sort;
    }

    /**
     * One page of the certificates table, in the shape the table script reads.
     *
     * @param scope $scope Who is asking.
     * @param string $search The search text.
     * @param int $page The page, from 0.
     * @param int $perpage Rows per page, kept between 10 and 200.
     * @param string $sort The sort key the script sends.
     * @param string $order `ASC` or `DESC`.
     * @param repository|null $repository The repository to read from; null for the available sources.
     * @return array success, data (rows), total, page, perpage, pages.
     */
    public static function search_page(
        scope $scope,
        string $search,
        int $page,
        int $perpage,
        string $sort,
        string $order,
        ?repository $repository = null
    ): array {
        $repository = $repository ?? new repository();
        $page = max(0, $page);
        $perpage = min(max($perpage, 10), 200);
        $filter = new filter(search: $search);

        $total = $repository->count($scope, $filter);
        $rows = $repository->search($scope, $filter, self::sort_key($sort), $order, $page * $perpage, $perpage);

        $system = \context_system::instance();
        $unknown = get_string('unknown', 'block_download_certificates');
        $data = [];
        foreach ($rows as $row) {
            $coursecontext = $row->courseid ? \context_course::instance($row->courseid, IGNORE_MISSING) : false;
            $downloadurl = new \moodle_url('/blocks/download_certificates/download_precise.php', [
                'type' => $row->type,
                'issueid' => $row->issueid,
                'sesskey' => sesskey(),
            ]);
            $data[] = [
                'id' => $row->uniqueid,
                'cert_type' => $row->type,
                'username' => fullname($row),
                'email' => $row->email,
                'coursename' => format_string(
                    $row->coursename !== '' ? $row->coursename : $unknown,
                    true,
                    ['context' => $coursecontext ?: $system]
                ),
                'templatename' => format_string(
                    $row->templatename !== '' ? $row->templatename : $unknown,
                    true,
                    ['context' => $system]
                ),
                'code' => $row->code,
                'timecreated' => userdate($row->timecreated),
                'timecreated_raw' => $row->timecreated,
                'userid' => $row->userid,
                'download_url' => $downloadurl->out(false),
            ];
        }

        return [
            'success' => true,
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'pages' => (int) ceil($total / $perpage),
        ];
    }

    /**
     * The groups a course download may be narrowed to.
     *
     * A manager gets every group of the course. A teacher gets them for one of their own courses:
     * only their own groups (in the course's default grouping) under separate groups without
     * `moodle/site:accessallgroups`, the rule {@see scope} applies, else every group.
     *
     * @param scope $scope Who is asking.
     * @param int $courseid The course.
     * @return array List of id, name (formatted in the course context).
     * @throws \moodle_exception `accessdenied` for a course outside the user's courses.
     */
    public static function course_groups(scope $scope, int $courseid): array {
        if (!$scope->is_manager() && !in_array($courseid, $scope->get_course_ids(), true)) {
            throw new \moodle_exception('accessdenied', 'block_download_certificates');
        }
        $course = get_course($courseid);
        $context = \context_course::instance($course->id);
        $userid = $scope->get_user_id();

        if (
            !$scope->is_manager()
            && (int) groups_get_course_groupmode($course) === SEPARATEGROUPS
            && !has_capability('moodle/site:accessallgroups', $context, $userid)
        ) {
            $groups = groups_get_all_groups($course->id, $userid, (int) $course->defaultgroupingid);
        } else {
            $groups = groups_get_all_groups($course->id);
        }

        $result = [];
        foreach ($groups as $group) {
            $result[] = ['id' => (int) $group->id, 'name' => format_string($group->name, true, ['context' => $context])];
        }
        return $result;
    }

    /**
     * The filter of a ZIP request, from the values the async script posts.
     *
     * Only the keys the type means are kept: a `course` request carries no user, an `all` request
     * nothing. A `range` arrives as the dates the browser picked, sent as the epoch of their UTC
     * midnight; the dates are read back and turned into a range in the user's time zone.
     *
     * @param string $type One of {@see self::TYPES}.
     * @param array $params The decoded, untrusted values.
     * @param \DateTimeZone|null $timezone The zone of a range; null for the current user's.
     * @return filter
     * @throws \moodle_exception `invalidtype` for an unknown type, `invalidaterange` for a bad range.
     */
    public static function filter_for_type(string $type, array $params, ?\DateTimeZone $timezone = null): filter {
        switch ($type) {
            case 'all':
                return new filter();
            case 'course':
                return filter::from_array(array_intersect_key($params, ['courseid' => 1, 'groupid' => 1]));
            case 'user':
                return filter::from_array(array_intersect_key($params, ['userid' => 1]));
            case 'cohort':
                return filter::from_array(array_intersect_key($params, ['cohortid' => 1]));
            case 'range':
                $start = self::date_param($params, 'start_date', 'startdate');
                $end = self::date_param($params, 'end_date', 'enddate');
                [$from, $to] = self::date_range($start, $end, $timezone);
                return filter::from_array(['datefrom' => $from, 'dateto' => $to]);
        }
        throw new \moodle_exception('invalidtype', 'block_download_certificates');
    }

    /**
     * A date of a range request: the `Y-m-d` string of a form, or the epoch the async script sends.
     *
     * @param array $params The untrusted values.
     * @param string $textkey The key of the `Y-m-d` string.
     * @param string $epochkey The key of the epoch.
     * @return string The `Y-m-d` date, or '' when neither is usable.
     */
    private static function date_param(array $params, string $textkey, string $epochkey): string {
        if (isset($params[$textkey]) && is_scalar($params[$textkey])) {
            return clean_param((string) $params[$textkey], PARAM_ALPHANUMEXT);
        }
        if (isset($params[$epochkey]) && is_numeric($params[$epochkey])) {
            return gmdate('Y-m-d', (int) $params[$epochkey]);
        }
        return '';
    }

    /**
     * The half-open range `[from, to)` covering two calendar days and every day between them, in
     * a time zone.
     *
     * @param string $start The first day, `Y-m-d`.
     * @param string $end The last day, `Y-m-d`.
     * @param \DateTimeZone|null $timezone The zone; null for the current user's.
     * @return int[] [int $from, int $to]
     * @throws \moodle_exception `invalidaterange` for a malformed day or a last day before the first.
     */
    public static function date_range(string $start, string $end, ?\DateTimeZone $timezone = null): array {
        $timezone = $timezone ?? \core_date::get_user_timezone_object();
        $first = self::parse_day($start, $timezone);
        $last = self::parse_day($end, $timezone);
        if (!$first || !$last || $last < $first) {
            throw new \moodle_exception('invalidaterange', 'block_download_certificates');
        }
        return [$first->getTimestamp(), $last->modify('+1 day')->getTimestamp()];
    }

    /**
     * The midnight starting a day, or null when the text is not a real `Y-m-d` day.
     *
     * @param string $day The text.
     * @param \DateTimeZone $timezone The zone.
     * @return \DateTimeImmutable|null
     */
    private static function parse_day(string $day, \DateTimeZone $timezone): ?\DateTimeImmutable {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $day, $timezone);
        // The round trip refuses what PHP would roll over, such as 2026-02-31.
        return $date && $date->format('Y-m-d') === $day ? $date : null;
    }

    /**
     * Refuse a ZIP request the scope may not make.
     *
     * - `all`, `cohort` and `range` are for managers; a cohort must be one the user may list.
     * - `course` needs a manager or one of the scope's own courses.
     * - Anything but the user's own certificates needs a scope that reaches somebody else.
     *
     * @param scope $scope Who is asking, as the scope is now.
     * @param string $type One of {@see self::TYPES}.
     * @param filter $filter The request's filter.
     * @throws \moodle_exception `invalidtype`, `accessdenied`, `coursenotselected`,
     *         `cohortnotselected`, `invalidparameters` or `invalidaterange`.
     */
    public static function check_zip(scope $scope, string $type, filter $filter): void {
        if (!in_array($type, self::TYPES, true)) {
            throw new \moodle_exception('invalidtype', 'block_download_certificates');
        }
        if (in_array($type, self::MANAGER_TYPES, true) && !$scope->is_manager()) {
            throw new \moodle_exception('accessdenied', 'block_download_certificates');
        }
        if ($filter->userid !== $scope->get_user_id() && !$scope->can_see_others()) {
            throw new \moodle_exception('accessdenied', 'block_download_certificates');
        }

        switch ($type) {
            case 'course':
                if (!$filter->courseid) {
                    throw new \moodle_exception('coursenotselected', 'block_download_certificates');
                }
                if (!$scope->is_manager() && !in_array($filter->courseid, $scope->get_course_ids(), true)) {
                    throw new \moodle_exception('accessdenied', 'block_download_certificates');
                }
                break;
            case 'user':
                if (!$filter->userid) {
                    throw new \moodle_exception('invalidparameters', 'block_download_certificates');
                }
                break;
            case 'cohort':
                if (!$filter->cohortid) {
                    throw new \moodle_exception('cohortnotselected', 'block_download_certificates');
                }
                if (!self::can_list_cohort($scope, $filter->cohortid)) {
                    throw new \moodle_exception('accessdenied', 'block_download_certificates');
                }
                break;
            case 'range':
                if (!$filter->datefrom || $filter->dateto <= $filter->datefrom) {
                    throw new \moodle_exception('invalidaterange', 'block_download_certificates');
                }
                break;
        }
    }

    /**
     * Whether the scope's user may list a cohort: visible, or `moodle/cohort:view` in its context,
     * the rule {@see repository::get_cohorts()} lists by.
     *
     * @param scope $scope Who is asking.
     * @param int $cohortid The cohort.
     * @return bool False for a cohort that does not exist.
     */
    public static function can_list_cohort(scope $scope, int $cohortid): bool {
        global $DB;

        $cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id, contextid, visible');
        if (!$cohort) {
            return false;
        }
        $context = \context::instance_by_id((int) $cohort->contextid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }
        return (bool) $cohort->visible || has_capability('moodle/cohort:view', $context, $scope->get_user_id());
    }

    /**
     * The download name of a ZIP: ids only, never a course or user name.
     *
     * @param string $type One of {@see self::TYPES}.
     * @param filter $filter The request's filter.
     * @return string
     */
    public static function zip_filename(string $type, filter $filter): string {
        $ids = [
            'course' => $filter->courseid,
            'user' => $filter->userid,
            'cohort' => $filter->cohortid,
        ];
        $name = 'certificates';
        if (in_array($type, self::TYPES, true) && $type !== 'all') {
            $name .= '_' . $type;
        }
        if (!empty($ids[$type])) {
            $name .= '_' . $ids[$type];
        }
        return $name . '.zip';
    }

    /**
     * The message a client is shown for a failure.
     *
     * The plugin's own exceptions carry a localised reason meant for the user; any other text
     * (SQL, file system, another component's debug details) stays on the server.
     *
     * @param \Throwable $e The failure.
     * @return string
     */
    public static function error_message(\Throwable $e): string {
        if ($e instanceof \moodle_exception && $e->module === 'block_download_certificates') {
            return get_string($e->errorcode, 'block_download_certificates', $e->a);
        }
        return get_string('requestfailed', 'block_download_certificates');
    }

    /**
     * Return a page script's user to their panel with the plugin's own reason; does not return.
     *
     * Another component's exception goes on to core's handler, which shows debug details only to
     * those allowed to see them.
     *
     * @param scope $scope Who is asking.
     * @param \moodle_exception $e The failure.
     * @throws \moodle_exception The failure itself when it is not the plugin's.
     */
    public static function redirect_on_error(scope $scope, \moodle_exception $e): void {
        if ($e->module !== 'block_download_certificates') {
            throw $e;
        }
        redirect(self::panel_url($scope), self::error_message($e), null, \core\output\notification::NOTIFY_ERROR);
    }

    /**
     * Send a JSON body with its content type; the AJAX scripts' only output.
     *
     * @param array $data The body.
     */
    public static function send_json(array $data): void {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data);
    }
}
