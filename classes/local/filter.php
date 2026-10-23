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
 * Filter over the certificates of a scope.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

/**
 * What a list, a count or a ZIP narrows the scope to. A filter only ever narrows: the scope decides
 * what may be seen.
 *
 * Dates are a half-open range `[datefrom, dateto)` of timestamps the caller computed in the user's
 * time zone; 0 leaves that end open. Every other 0 or empty value means "no condition".
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filter {
    /** @var array Accepted keys and the PARAM_* type each value is cleaned with. */
    private const KEYS = [
        'courseid' => PARAM_INT,
        'userid' => PARAM_INT,
        'cohortid' => PARAM_INT,
        'groupid' => PARAM_INT,
        'datefrom' => PARAM_INT,
        'dateto' => PARAM_INT,
        'search' => PARAM_TEXT,
    ];

    /** @var int Keep this course only. */
    public readonly int $courseid;

    /** @var int Keep this certificate owner only. */
    public readonly int $userid;

    /** @var int Keep the members of this cohort only. */
    public readonly int $cohortid;

    /** @var int Keep the members of this group only, in the group's own course. */
    public readonly int $groupid;

    /** @var int Keep certificates issued at or after this time. */
    public readonly int $datefrom;

    /** @var int Keep certificates issued before this time. */
    public readonly int $dateto;

    /** @var string Match against the owner's full name and e-mail, the course, template and code. */
    public readonly string $search;

    /**
     * @var bool Whether the search text is matched against the owner's e-mail too. Not stored and not
     *      read from input: the caller decides it from core's identity fields, since matching a
     *      column the viewer may not see would reveal it one search at a time.
     */
    public readonly bool $searchemail;

    /**
     * Build a filter from values that are already clean.
     *
     * @param int $courseid Keep this course only.
     * @param int $userid Keep this certificate owner only.
     * @param int $cohortid Keep the members of this cohort only.
     * @param int $groupid Keep the members of this group only.
     * @param int $datefrom Keep certificates issued at or after this time.
     * @param int $dateto Keep certificates issued before this time.
     * @param string $search The search text.
     * @param bool $searchemail Whether the search text is matched against the owner's e-mail too.
     */
    public function __construct(
        int $courseid = 0,
        int $userid = 0,
        int $cohortid = 0,
        int $groupid = 0,
        int $datefrom = 0,
        int $dateto = 0,
        string $search = '',
        bool $searchemail = true
    ) {
        $this->courseid = max(0, $courseid);
        $this->userid = max(0, $userid);
        $this->cohortid = max(0, $cohortid);
        $this->groupid = max(0, $groupid);
        $this->datefrom = max(0, $datefrom);
        $this->dateto = max(0, $dateto);
        $this->search = trim($search);
        $this->searchemail = $searchemail;
    }

    /**
     * The same filter, with the search text no longer matched against the owner's e-mail.
     *
     * @return self
     */
    public function without_email_search(): self {
        return new self(...array_merge($this->to_array(), ['searchemail' => false]));
    }

    /**
     * Build a filter from untrusted input (request parameters, stored task JSON).
     *
     * Every known key is cleaned with its PARAM_* type; unknown keys and non-scalar values are
     * dropped.
     *
     * @param array $data The raw values.
     * @return self
     */
    public static function from_array(array $data): self {
        $clean = [];
        foreach (self::KEYS as $key => $type) {
            if (!array_key_exists($key, $data) || !is_scalar($data[$key])) {
                continue;
            }
            $value = clean_param((string) $data[$key], $type);
            $clean[$key] = $type === PARAM_INT ? (int) $value : (string) $value;
        }
        return new self(...$clean);
    }

    /**
     * The values, for storage in a task row and for {@see self::from_array()}.
     *
     * @return array Key => value, every key present.
     */
    public function to_array(): array {
        return [
            'courseid' => $this->courseid,
            'userid' => $this->userid,
            'cohortid' => $this->cohortid,
            'groupid' => $this->groupid,
            'datefrom' => $this->datefrom,
            'dateto' => $this->dateto,
            'search' => $this->search,
        ];
    }
}
