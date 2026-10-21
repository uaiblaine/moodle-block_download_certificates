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
 * Upgrade steps for block_download_certificates.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * Versions are numbered in the range of the Moodle version a branch requires (5.3: 20261005XX,
 * 5.2: 20260420XX, 5.1: 20251006XX, 4.5: 20241007XX), so a release for one Moodle version never
 * carries the number of another. Upstream releases were numbered by date up to 2026100700, above
 * those ranges: a site coming from upstream 1.6.1 sets its version to the switch number README.md
 * gives for its branch, then upgrades. The first step below therefore reads the schema, not
 * $oldversion, and brings any upstream schema to the current one.
 *
 * @param int $oldversion The currently installed plugin version.
 * @return bool True on success.
 */
function xmldb_block_download_certificates_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026100500) {
        $table = new xmldb_table('block_download_certificates_tasks');
        $legacy = new xmldb_table('block_download_cert_tasks');

        // Upstream created the table as block_download_cert_tasks in version 2026022000 and renamed it
        // in 2026100700; earlier versions had no table.
        if (!$dbman->table_exists($table)) {
            if ($dbman->table_exists($legacy)) {
                $dbman->rename_table($legacy, 'block_download_certificates_tasks');
            } else {
                $dbman->install_one_table_from_xmldb_file(
                    __DIR__ . '/install.xml',
                    'block_download_certificates_tasks'
                );
            }
        }

        // Upstream added this field in version 2026030900.
        $field = new xmldb_field('batchoffset', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'error');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Upstream's installer added a dashboard block under the truncated name download_certificat,
        // with no block context; core never renders such a row.
        $DB->delete_records('block_instances', ['blockname' => 'download_certificat']);

        upgrade_block_savepoint(true, 2026100500, 'download_certificates');
    }

    return true;
}
