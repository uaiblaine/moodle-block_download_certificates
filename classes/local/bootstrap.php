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
 * Bootstrap major version marker for the plugin's own pages.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

/**
 * Tells the stylesheet and the page module which Bootstrap major version the site runs.
 *
 * Moodle 4.5 ships Bootstrap 4, whose forward bridge (theme/boost/scss/moodle/bs5-bridge.scss) maps
 * only g-0, btn-close, the start/end spacers, floats, text alignment, borders and roundings. The
 * pages use Bootstrap 5 names alone; styles.css polyfills the other ones they use, and
 * certificate_table.js gives the tabs the attributes Bootstrap 4's data-API listens to, both gated
 * on a body class only 4.5 receives. An ungated rule would outrank core's own definition on 5.x.
 *
 * The block renders inside pages the plugin does not own, after the header, so it cannot add a body
 * class: its markup uses no name this polyfill provides.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bootstrap {
    /** @var string Body class added on sites running Bootstrap 4 (Moodle 4.5). */
    public const BODY_CLASS_BS4 = 'block-download-certificates-bs4';

    /** @var int First Moodle branch that ships Bootstrap 5. */
    private const FIRST_BS5_BRANCH = 500;

    /**
     * Whether the current site runs Bootstrap 4 rather than Bootstrap 5.
     *
     * @return bool True on Moodle 4.5, false from Moodle 5.0 on.
     */
    public static function is_bs4(): bool {
        global $CFG;

        return (int) $CFG->branch < self::FIRST_BS5_BRANCH;
    }

    /**
     * Add the Bootstrap 4 marker to the page when the site needs the polyfill.
     *
     * Call it from every page of the plugin before the header is printed.
     */
    public static function mark_page(): void {
        global $PAGE;

        if (self::is_bs4()) {
            $PAGE->add_body_class(self::BODY_CLASS_BS4);
        }
    }
}
