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
 * @copyright 2025 E-learning Touch' contact@elearningtouch.com (Maintainer)
 * @author    Thomas Clément 222384061+ClementThomasELT@users.noreply.github.com (Coder)
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute block_download_certificates upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_download_certificates_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026022000) {
        // Define table block_download_cert_tasks.
        $table = new xmldb_table('block_download_cert_tasks');

        // Adding fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('params', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('progress', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('total', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('filepath', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('error', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes.
        $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('userid_status_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'status']);

        // Conditionally create the table.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Block savepoint reached.
        upgrade_block_savepoint(true, 2026022000, 'download_certificates');
    }

    if ($oldversion < 2026030900) {
        // Add batchoffset field for chunked processing.
        $table = new xmldb_table('block_download_cert_tasks');
        $field = new xmldb_field('batchoffset', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'error');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Block savepoint reached.
        upgrade_block_savepoint(true, 2026030900, 'download_certificates');
    }

    if ($oldversion < 2026100700) {
        // Rename block_download_cert_tasks to block_download_certificates_tasks (frankenstyle table prefix).
        $table = new xmldb_table('block_download_cert_tasks');

        if ($dbman->table_exists($table) && !$dbman->table_exists('block_download_certificates_tasks')) {
            $dbman->rename_table($table, 'block_download_certificates_tasks');
        }

        // Block savepoint reached.
        upgrade_block_savepoint(true, 2026100700, 'download_certificates');
    }

    return true;
}
