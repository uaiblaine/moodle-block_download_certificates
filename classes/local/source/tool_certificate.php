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
 * Certificate source for tool_certificate.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local\source;

/**
 * Issues of tool_certificate (Workplace certificates).
 *
 * The plugin keeps each issue's PDF in the system context, file area `issues`, item id = issue id,
 * file name `<code>.pdf` ({@see \tool_certificate\template::get_issue_file()}).
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_certificate extends source {
    /**
     * The value stored in the `type` column of the union and in frozen task references.
     *
     * @return string
     */
    public function get_type(): string {
        return 'tool_certificate';
    }

    /**
     * The frankenstyle name of the certificate plugin.
     *
     * @return string
     */
    public function get_component(): string {
        return 'tool_certificate';
    }

    /**
     * The table that holds the plugin's issues, without prefix.
     *
     * @return string
     */
    protected function get_issue_table(): string {
        return 'tool_certificate_issues';
    }

    /**
     * The plugin classes {@see self::get_content()} needs.
     *
     * @return string[]
     */
    protected function get_required_classes(): array {
        return [\tool_certificate\template::class];
    }

    /**
     * One SELECT returning the issues in the shared row shape, deleted users excluded.
     *
     * The issue's course is optional (NULL for a certificate issued outside a course); archived
     * issues (course reset) stay, as the plugin still lists them to their owner.
     *
     * @return array [string $sql, array $params]
     */
    public function get_issue_sql(): array {
        global $DB;

        $uniqueid = $DB->sql_concat("'tc_'", 'tci.id');
        $sql = "SELECT {$uniqueid} AS uniqueid,
                       tci.id AS issueid,
                       'tool_certificate' AS type,
                       tci.userid AS userid,
                       COALESCE(tci.courseid, 0) AS courseid,
                       COALESCE(tcco.fullname, '') AS coursename,
                       COALESCE(tct.name, '') AS templatename,
                       COALESCE(tci.code, '') AS code,
                       tci.timecreated AS timecreated
                  FROM {tool_certificate_issues} tci
                  JOIN {user} tcu ON tcu.id = tci.userid AND tcu.deleted = 0
             LEFT JOIN {course} tcco ON tcco.id = tci.courseid
             LEFT JOIN {tool_certificate_templates} tct ON tct.id = tci.templateid";
        return [$sql, []];
    }

    /**
     * The stored PDF of the issue, created through the plugin's own API when it is missing.
     *
     * @param \stdClass $issue A row in the shared shape.
     * @return \stored_file|string
     * @throws \moodle_exception When the issue has no code or no PDF can be obtained.
     */
    public function get_content(\stdClass $issue): \stored_file|string {
        global $DB;

        $record = $DB->get_record('tool_certificate_issues', ['id' => (int) $issue->issueid]);
        // The file name is the code: an empty code would name every code-less issue's file alike.
        if (!$record || (string) $record->code === '') {
            throw new \moodle_exception('nopdf', 'block_download_certificates');
        }

        $fs = get_file_storage();
        $file = $fs->get_file(
            \context_system::instance()->id,
            'tool_certificate',
            'issues',
            (int) $record->id,
            '/',
            $record->code . '.pdf'
        );
        $empty = $file && !$file->is_directory() && (int) $file->get_filesize() === 0;
        if ((!$file || $empty) && $DB->record_exists('tool_certificate_templates', ['id' => (int) $record->templateid])) {
            // An empty stored file is what the plugin writes when it renders a template whose page list
            // it had cached before the pages existed; rendering again from a fresh instance fixes it.
            $file = \tool_certificate\template::instance((int) $record->templateid)->create_issue_file($record, $empty);
        }
        if (!$file || $file->is_directory() || (int) $file->get_filesize() === 0) {
            throw new \moodle_exception('nopdf', 'block_download_certificates');
        }
        return $file;
    }
}
