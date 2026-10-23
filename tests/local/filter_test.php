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
 * Tests for the certificate filter.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

/**
 * A filter built from untrusted input keeps the known keys, cleaned, and nothing else.
 *
 * Metadata stays in docblocks because the plugin still supports Moodle 4.5, whose moodle-cs cannot
 * read attributes and warns per method.
 *
 * @package    block_download_certificates
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_download_certificates\local\filter
 */
final class filter_test extends \advanced_testcase {
    /**
     * Unknown keys are dropped, values cast and cleaned, non-scalar values ignored.
     */
    public function test_from_array_drops_unknown_keys_and_casts(): void {
        $filter = filter::from_array([
            'courseid' => '12abc',
            'userid' => '-3',
            'cohortid' => ['7'],
            'groupid' => 5,
            'datefrom' => '1700000000',
            'dateto' => '1700086400',
            'search' => '  <b>Ada</b> Lovelace ',
            'type' => 'user',
            'sql' => '1 = 1',
        ]);

        $this->assertSame([
            'courseid' => 12,
            'userid' => 0,
            'cohortid' => 0,
            'groupid' => 5,
            'datefrom' => 1700000000,
            'dateto' => 1700086400,
            'search' => 'Ada Lovelace',
        ], $filter->to_array());
    }

    /**
     * An empty array is the empty filter; to_array() round-trips through from_array().
     */
    public function test_round_trip(): void {
        $empty = filter::from_array([]);
        $this->assertSame((new filter())->to_array(), $empty->to_array());

        $filter = new filter(3, 4, 5, 6, 100, 200, 'code');
        $this->assertSame($filter->to_array(), filter::from_array($filter->to_array())->to_array());
    }

    /**
     * Turning the e-mail search off keeps every other value; the switch is never stored nor read
     * from input, so a stored task or a request cannot set it.
     */
    public function test_without_email_search_keeps_the_rest(): void {
        $filter = new filter(3, 4, 5, 6, 100, 200, 'code');
        $this->assertTrue($filter->searchemail);

        $narrowed = $filter->without_email_search();
        $this->assertFalse($narrowed->searchemail);
        $this->assertSame($filter->to_array(), $narrowed->to_array());
        $this->assertArrayNotHasKey('searchemail', $narrowed->to_array());
        $this->assertTrue(filter::from_array(['searchemail' => '0', 'search' => 'x'])->searchemail);
    }
}
