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
 * Base class of a certificate source.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local\source;

/**
 * One certificate plugin: the only place that knows its tables, columns and how its PDF is obtained.
 *
 * Every source returns its issues in one row shape, so that the repository can `UNION ALL` the
 * sources without casts or collations: `uniqueid` (char, type prefix + issue id), `issueid`,
 * `type`, `userid`, `courseid` (0 when the issue has no course), `coursename`, `templatename`,
 * `code`, `timecreated`.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class source {
    /**
     * The value stored in the `type` column of the union and in frozen task references.
     *
     * @return string
     */
    abstract public function get_type(): string;

    /**
     * The frankenstyle name of the certificate plugin.
     *
     * @return string
     */
    abstract public function get_component(): string;

    /**
     * The table that holds the plugin's issues, without prefix.
     *
     * @return string
     */
    abstract protected function get_issue_table(): string;

    /**
     * The plugin classes {@see self::get_content()} needs.
     *
     * @return string[]
     */
    abstract protected function get_required_classes(): array;

    /**
     * One SELECT returning the issues in the shared row shape, deleted users excluded.
     *
     * @return array [string $sql, array $params]
     */
    abstract public function get_issue_sql(): array;

    /**
     * The PDF of an issue.
     *
     * @param \stdClass $issue A row in the shared shape, as returned by {@see self::get_issue()}.
     * @return \stored_file|string The stored PDF, or the generated PDF content.
     * @throws \moodle_exception When the issue has no PDF.
     */
    abstract public function get_content(\stdClass $issue): \stored_file|string;

    /**
     * The localised name of the certificate plugin.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', $this->get_component());
    }

    /**
     * Whether the plugin is installed and up to date, its issue table exists and the classes the
     * PDF path needs are loadable.
     *
     * An unavailable source contributes nothing and raises nothing.
     *
     * @return bool
     */
    public function is_available(): bool {
        global $DB;

        $info = \core_plugin_manager::instance()->get_plugin_info($this->get_component());
        if (!$info || !$info->is_installed_and_upgraded()) {
            return false;
        }
        if (!$DB->get_manager()->table_exists($this->get_issue_table())) {
            return false;
        }
        foreach ($this->get_required_classes() as $classname) {
            if (!class_exists($classname)) {
                return false;
            }
        }
        return true;
    }

    /**
     * One issue in the shared row shape.
     *
     * @param int $issueid The id in the plugin's issue table.
     * @return \stdClass|null Null when there is no such issue or its user is deleted.
     */
    public function get_issue(int $issueid): ?\stdClass {
        global $DB;

        [$sql, $params] = $this->get_issue_sql();
        $params['bdcsourceissueid'] = $issueid;
        $row = $DB->get_record_sql(
            "SELECT i.* FROM ({$sql}) i WHERE i.issueid = :bdcsourceissueid",
            $params
        );
        return $row ? self::normalise($row) : null;
    }

    /**
     * The archive and download name of an issue: `<fullname>_<template>_<code>.pdf`, cleaned.
     *
     * @param \stdClass $issue A row in the shared shape.
     * @return string The file name, without directory.
     */
    public function get_filename(\stdClass $issue): string {
        $user = \core_user::get_user((int) $issue->userid);
        $fullname = $user ? fullname($user) : (string) $issue->userid;
        $template = format_string(
            (string) $issue->templatename,
            true,
            ['context' => \context_system::instance(), 'escape' => false]
        );
        return clean_filename($fullname . '_' . $template . '_' . $issue->code . '.pdf');
    }

    /**
     * Cast a row of the shared shape: drivers return every column as a string.
     *
     * @param \stdClass $row A row read from the union or from {@see self::get_issue_sql()}.
     * @return \stdClass
     */
    public static function normalise(\stdClass $row): \stdClass {
        foreach (['issueid', 'userid', 'courseid', 'timecreated'] as $field) {
            $row->$field = (int) $row->$field;
        }
        foreach (['uniqueid', 'type', 'coursename', 'templatename', 'code'] as $field) {
            $row->$field = (string) $row->$field;
        }
        return $row;
    }
}
