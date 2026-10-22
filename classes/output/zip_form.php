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
 * The context of the ZIP request form.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\output;

use block_download_certificates\local\download;

/**
 * Builds the context of `block_download_certificates/zip_form`: a POST form to one of the ZIP pages
 * with the session key in its body, marked for async_download.js, which queues the request instead
 * of posting it when it holds more than {@see download::SYNC_LIMIT} certificates and asks first when the form
 * carries a confirmation.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class zip_form {
    /**
     * The template context of one ZIP form.
     *
     * @param string $script The download page, in the plugin directory.
     * @param array $fields Field name => value, posted as hidden fields beside the session key.
     * @param string $type The request type, as {@see \block_download_certificates\local\request::TYPES}.
     * @param int $count The certificates the request holds.
     * @param string $label The button text.
     * @param string $buttonclass The Bootstrap classes of the button besides `btn`.
     * @param string|null $confirm The question asked before the request goes, or null to ask none.
     * @return array url, type, count, threshold, sesskey, fields (name, value), label, buttonclass,
     *         confirm (the question or false).
     */
    public static function export(
        string $script,
        array $fields,
        string $type,
        int $count,
        string $label,
        string $buttonclass,
        ?string $confirm = null
    ): array {
        $hidden = [];
        foreach ($fields as $name => $value) {
            $hidden[] = ['name' => $name, 'value' => (string) $value];
        }
        return [
            'url' => (new \moodle_url('/blocks/download_certificates/' . $script))->out(false),
            'type' => $type,
            'count' => $count,
            'threshold' => download::SYNC_LIMIT,
            'sesskey' => sesskey(),
            'fields' => $hidden,
            'label' => $label,
            'buttonclass' => $buttonclass,
            'confirm' => $confirm ?? false,
        ];
    }
}
