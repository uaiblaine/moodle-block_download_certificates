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
 * In-memory certificate source for tests that need no certificate plugin.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local\source;

/**
 * A source whose issues, contents and failures are set by the test.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_source extends source {
    /** @var \stdClass[] Issues in the shared shape plus a `filename`, keyed by issue id. */
    public array $issues = [];

    /** @var \Throwable[] What get_content() throws, keyed by issue id. */
    public array $failures = [];

    /** @var \stored_file[] What get_content() returns instead of a string, keyed by issue id. */
    public array $stored = [];

    /**
     * Register an issue.
     *
     * @param int $issueid The issue id.
     * @param string $filename The file name get_filename() returns.
     * @return \stdClass The issue.
     */
    public function add_issue(int $issueid, string $filename): \stdClass {
        $this->issues[$issueid] = (object) [
            'uniqueid' => 'fk_' . $issueid,
            'issueid' => $issueid,
            'type' => 'fake',
            'userid' => 0,
            'courseid' => 0,
            'coursename' => '',
            'templatename' => '',
            'code' => 'CODE' . $issueid,
            'timecreated' => 0,
            'filename' => $filename,
        ];
        return $this->issues[$issueid];
    }

    /**
     * The source type.
     *
     * @return string
     */
    public function get_type(): string {
        return 'fake';
    }

    /**
     * The component the source pretends to be.
     *
     * @return string
     */
    public function get_component(): string {
        return 'block_download_certificates';
    }

    /**
     * Any existing table: availability is not under test.
     *
     * @return string
     */
    protected function get_issue_table(): string {
        return 'block_download_certificates_tasks';
    }

    /**
     * No classes needed.
     *
     * @return string[]
     */
    protected function get_required_classes(): array {
        return [];
    }

    /**
     * Not used: the fake has no table.
     *
     * @return array
     * @throws \coding_exception Always.
     */
    public function get_issue_sql(): array {
        throw new \coding_exception('fake_source has no SQL.');
    }

    /**
     * A registered issue.
     *
     * @param int $issueid The issue id.
     * @return \stdClass|null
     */
    public function get_issue(int $issueid): ?\stdClass {
        return $this->issues[$issueid] ?? null;
    }

    /**
     * The registered failure, stored file, or a PDF-like string naming the issue.
     *
     * @param \stdClass $issue The issue.
     * @return \stored_file|string
     * @throws \Throwable The registered failure.
     */
    public function get_content(\stdClass $issue): \stored_file|string {
        if (isset($this->failures[$issue->issueid])) {
            throw $this->failures[$issue->issueid];
        }
        return $this->stored[$issue->issueid] ?? '%PDF-fake ' . $issue->issueid;
    }

    /**
     * The registered file name.
     *
     * @param \stdClass $issue The issue.
     * @return string
     */
    public function get_filename(\stdClass $issue): string {
        return $issue->filename;
    }
}
