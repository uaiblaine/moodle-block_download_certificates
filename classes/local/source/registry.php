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
 * The list of certificate sources.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local\source;

/**
 * The one list of certificate sources every caller iterates.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {
    /**
     * Every source the block knows, installed or not.
     *
     * @return source[] Keyed by type.
     */
    public static function get_all(): array {
        $sources = [];
        foreach ([new tool_certificate(), new customcert()] as $source) {
            $sources[$source->get_type()] = $source;
        }
        return $sources;
    }

    /**
     * The sources whose plugin is installed and usable.
     *
     * @return source[] Keyed by type.
     */
    public static function get_available(): array {
        return array_filter(self::get_all(), static fn(source $source): bool => $source->is_available());
    }

    /**
     * One available source.
     *
     * @param string $type The source type.
     * @return source|null Null when the type is unknown or its plugin unavailable.
     */
    public static function get(string $type): ?source {
        return self::get_available()[$type] ?? null;
    }
}
