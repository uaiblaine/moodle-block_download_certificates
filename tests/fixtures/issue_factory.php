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
 * Certificate issues for tests, written straight to the certificate plugins' tables.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local\source;

/**
 * Templates through the plugins' generators; issue rows inserted directly, so that a test controls
 * the issue time and code and pays for no PDF it does not read.
 *
 * Each plugin's issue API generates a PDF (tool_certificate) or, from mod_customcert 5.2, emits a
 * deprecation notice (`certificate::issue_certificate()`); the PDF paths are covered by the source
 * tests through the plugins' own generators.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class issue_factory {
    /**
     * A tool_certificate template, optionally with one blank page so that it renders a PDF.
     *
     * @param string $name The template name.
     * @param bool $withpage Whether to add a page.
     * @return \tool_certificate\template
     */
    public static function tool_certificate_template(string $name, bool $withpage = false): \tool_certificate\template {
        $generator = \testing_util::get_data_generator()->get_plugin_generator('tool_certificate');
        $template = $generator->create_template((object) ['name' => $name]);
        if (!$withpage) {
            return $template;
        }
        $generator->create_page($template);
        // The generator's new_page() cached the page list before the page was saved: an issue made from
        // this object would store an empty PDF.
        return \tool_certificate\template::instance($template->get_id());
    }

    /**
     * A tool_certificate issue row, without its PDF.
     *
     * @param int $templateid The template.
     * @param int $userid The owner.
     * @param int $courseid The course, 0 for none.
     * @param int $timecreated The issue time.
     * @param string $code The code.
     * @return int The issue id.
     */
    public static function tool_certificate_issue(
        int $templateid,
        int $userid,
        int $courseid,
        int $timecreated,
        string $code
    ): int {
        global $DB;

        return (int) $DB->insert_record('tool_certificate_issues', (object) [
            'userid' => $userid,
            'templateid' => $templateid,
            'code' => $code,
            'emailed' => 0,
            'timecreated' => $timecreated,
            'expires' => null,
            'data' => '{}',
            'component' => 'tool_certificate',
            'courseid' => $courseid ?: null,
            'archived' => 0,
        ]);
    }

    /**
     * A mod_customcert activity; the plugin gives it a template with one page.
     *
     * @param int $courseid The course.
     * @param string $name The activity name.
     * @return \stdClass The customcert record.
     */
    public static function customcert_instance(int $courseid, string $name): \stdClass {
        return \testing_util::get_data_generator()->create_module('customcert', ['course' => $courseid, 'name' => $name]);
    }

    /**
     * A mod_customcert issue row.
     *
     * @param int $customcertid The activity instance.
     * @param int $userid The owner.
     * @param int $timecreated The issue time.
     * @param string $code The code.
     * @return int The issue id.
     */
    public static function customcert_issue(int $customcertid, int $userid, int $timecreated, string $code): int {
        global $DB;

        return (int) $DB->insert_record('customcert_issues', (object) [
            'userid' => $userid,
            'customcertid' => $customcertid,
            'code' => $code,
            'emailed' => 0,
            'timecreated' => $timecreated,
        ]);
    }
}
