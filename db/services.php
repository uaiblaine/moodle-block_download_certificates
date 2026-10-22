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
 * Web service functions of block_download_certificates.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_download_certificates_create_task' => [
        'classname' => 'block_download_certificates\external\create_task',
        'description' => 'Queue a background ZIP of the certificates a request names, within the caller\'s scope.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'block_download_certificates_get_groups' => [
        'classname' => 'block_download_certificates\external\get_groups',
        'description' => 'The groups a course download may be narrowed to, within the caller\'s scope.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'block_download_certificates_get_task_status' => [
        'classname' => 'block_download_certificates\external\get_task_status',
        'description' => 'The state of one of the caller\'s background ZIP downloads.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'block_download_certificates_search_certificates' => [
        'classname' => 'block_download_certificates\external\search_certificates',
        'description' => 'One page of the certificates within the caller\'s scope.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
