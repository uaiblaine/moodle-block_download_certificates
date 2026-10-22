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
 * Certificate source for mod_customcert.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local\source;

/**
 * Issues of mod_customcert (Custom certificate activities).
 *
 * The plugin stores no PDF: it renders the activity's template for the issue's user on demand.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customcert extends source {
    /**
     * The value stored in the `type` column of the union and in frozen task references.
     *
     * @return string
     */
    public function get_type(): string {
        return 'customcert';
    }

    /**
     * The frankenstyle name of the certificate plugin.
     *
     * @return string
     */
    public function get_component(): string {
        return 'mod_customcert';
    }

    /**
     * The table that holds the plugin's issues, without prefix.
     *
     * @return string
     */
    protected function get_issue_table(): string {
        return 'customcert_issues';
    }

    /**
     * The plugin classes {@see self::get_content()} needs.
     *
     * @return string[]
     */
    protected function get_required_classes(): array {
        return [\mod_customcert\template::class];
    }

    /**
     * One SELECT returning the issues in the shared row shape, deleted users excluded.
     *
     * The template name is the activity name, which is what the plugin shows its users.
     *
     * @return array [string $sql, array $params]
     */
    public function get_issue_sql(): array {
        global $DB;

        $uniqueid = $DB->sql_concat("'cc_'", 'cci.id');
        $sql = "SELECT {$uniqueid} AS uniqueid,
                       cci.id AS issueid,
                       'customcert' AS type,
                       cci.userid AS userid,
                       ccc.course AS courseid,
                       COALESCE(ccco.fullname, '') AS coursename,
                       COALESCE(ccc.name, '') AS templatename,
                       COALESCE(cci.code, '') AS code,
                       cci.timecreated AS timecreated
                  FROM {customcert_issues} cci
                  JOIN {customcert} ccc ON ccc.id = cci.customcertid
                  JOIN {user} ccu ON ccu.id = cci.userid AND ccu.deleted = 0
             LEFT JOIN {course} ccco ON ccco.id = ccc.course";
        return [$sql, []];
    }

    /**
     * The PDF rendered by the activity's template for the issue's user.
     *
     * From mod_customcert 5.2 the rendering lives in `pdf_generation_service` and the template's own
     * `generate_pdf()` is a deprecated shim that emits a debugging notice; earlier releases only
     * have the template method, whose constructor takes the template record.
     *
     * @param \stdClass $issue A row in the shared shape.
     * @return \stored_file|string
     * @throws \moodle_exception When the issue has no template or the template renders nothing.
     */
    public function get_content(\stdClass $issue): \stored_file|string {
        global $DB;

        $template = $DB->get_record_sql(
            "SELECT t.id, t.name, t.contextid
               FROM {customcert_issues} i
               JOIN {customcert} c ON c.id = i.customcertid
               JOIN {customcert_templates} t ON t.id = c.templateid
              WHERE i.id = :issueid",
            ['issueid' => (int) $issue->issueid]
        );
        if (!$template || !class_exists(\mod_customcert\template::class)) {
            throw new \moodle_exception('nopdf', 'block_download_certificates');
        }

        if (
            class_exists(\mod_customcert\service\pdf_generation_service::class)
            && method_exists(\mod_customcert\template::class, 'from_record')
        ) {
            $content = \mod_customcert\service\pdf_generation_service::create()->generate_pdf(
                \mod_customcert\template::from_record($template),
                false,
                (int) $issue->userid,
                true
            );
        } else {
            $content = (new \mod_customcert\template($template))->generate_pdf(false, (int) $issue->userid, true);
        }

        if (!is_string($content) || $content === '') {
            throw new \moodle_exception('nopdf', 'block_download_certificates');
        }
        return $content;
    }
}
