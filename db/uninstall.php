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
 * Download certificates block uninstall: the background downloads' temporary directories.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove what core's uninstall leaves: the task directories, which hold copies of other users'
 * certificates ({@see \block_download_certificates\local\task_storage::delete_all()}).
 *
 * @return bool Always true.
 */
function xmldb_block_download_certificates_uninstall(): bool {
    \block_download_certificates\local\task_storage::delete_all();
    return true;
}
