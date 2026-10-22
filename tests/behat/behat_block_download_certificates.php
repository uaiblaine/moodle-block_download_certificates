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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;
use block_download_certificates\local\source\issue_factory;

/**
 * Behat steps of block_download_certificates.
 *
 * @package   block_download_certificates
 * @category  test
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_download_certificates extends behat_base {
    /**
     * Issues tool_certificate certificates without generating their PDFs.
     *
     * Templates come from tool_certificate's own "the following certificate templates exist" step;
     * the issue rows are written directly, as the PHPUnit tests do, so that a scenario pays for no PDF.
     *
     * @Given /^the following certificate issues exist for the download certificates block:$/
     *
     * Supported table fields:
     *
     * - template: The template name (required).
     * - user: The owner's username (required).
     * - course: The course shortname (required).
     * - code: The issue code (required, unique).
     *
     * @param TableNode $data
     */
    public function the_following_certificate_issues_exist_for_the_block(TableNode $data): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/blocks/download_certificates/tests/fixtures/issue_factory.php');

        foreach ($data->getHash() as $row) {
            $templateid = (int) $DB->get_field('tool_certificate_templates', 'id', ['name' => $row['template']], MUST_EXIST);
            $userid = (int) $DB->get_field('user', 'id', ['username' => $row['user']], MUST_EXIST);
            $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $row['course']], MUST_EXIST);
            issue_factory::tool_certificate_issue($templateid, $userid, $courseid, time(), $row['code']);
        }
    }
}
