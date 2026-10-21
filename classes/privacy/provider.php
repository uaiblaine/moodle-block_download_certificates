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
 * Privacy provider for the certificate download requests stored by the block.
 *
 * @package   block_download_certificates
 * @copyright 2015 Manieer Chhettri
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: the asynchronous download requests of a user, kept in the user's own context.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string The only table the block owns. */
    private const TABLE = 'block_download_certificates_tasks';

    /**
     * Describe the personal data stored by the block.
     *
     * @param collection $collection The collection to add the metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE, [
            'userid' => 'privacy:metadata:block_download_certificates_tasks:userid',
            'type' => 'privacy:metadata:block_download_certificates_tasks:type',
            'params' => 'privacy:metadata:block_download_certificates_tasks:params',
            'status' => 'privacy:metadata:block_download_certificates_tasks:status',
            'filepath' => 'privacy:metadata:block_download_certificates_tasks:filepath',
            'timecreated' => 'privacy:metadata:block_download_certificates_tasks:timecreated',
            'timemodified' => 'privacy:metadata:block_download_certificates_tasks:timemodified',
            'error' => 'privacy:metadata:block_download_certificates_tasks:error',
        ], 'privacy:metadata:block_download_certificates_tasks');

        return $collection;
    }

    /**
     * Get the contexts holding data of a user: their own user context, when they requested a download.
     *
     * @param int $userid The user to search for.
     * @return contextlist The contexts with data of the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT ctx.id
               FROM {context} ctx
              WHERE ctx.contextlevel = :contextlevel
                AND ctx.instanceid = :userid
                AND EXISTS (SELECT 1 FROM {' . self::TABLE . '} t WHERE t.userid = ctx.instanceid)',
            ['contextlevel' => CONTEXT_USER, 'userid' => $userid]
        );

        return $contextlist;
    }

    /**
     * Add the users who have data in a user context.
     *
     * @param userlist $userlist The userlist to add the users to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        if ($DB->record_exists(self::TABLE, ['userid' => $context->instanceid])) {
            $userlist->add_user((int) $context->instanceid);
        }
    }

    /**
     * Export the download requests of the approved user context.
     *
     * The generated ZIP archive is not exported: it only repackages certificates the user can download elsewhere.
     *
     * @param approved_contextlist $contextlist The approved contexts to export for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_USER || (int) $context->instanceid !== (int) $user->id) {
                continue;
            }
            $tasks = $DB->get_records(self::TABLE, ['userid' => $user->id], 'id ASC');
            foreach ($tasks as $task) {
                $data = (object) [
                    'type' => $task->type,
                    'params' => $task->params,
                    'status' => $task->status,
                    'filepath' => $task->filepath,
                    'timecreated' => transform::datetime($task->timecreated),
                    'timemodified' => transform::datetime($task->timemodified),
                    'error' => $task->error,
                ];
                writer::with_context($context)->export_data([
                    get_string('privacy:path:tasks', 'block_download_certificates'),
                    (string) $task->id,
                ], $data);
            }
        }
    }

    /**
     * Delete the data of every user in a context.
     *
     * @param \context $context The context to delete in.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        self::delete_tasks(['userid' => $context->instanceid]);
    }

    /**
     * Delete the data of one user in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and the user.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_USER && (int) $context->instanceid === (int) $user->id) {
                self::delete_tasks(['userid' => $user->id]);
            }
        }
    }

    /**
     * Delete the data of several users in one context.
     *
     * @param approved_userlist $userlist The approved users and the context.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        // A user context holds the data of its owner only.
        if (in_array((int) $context->instanceid, array_map('intval', $userlist->get_userids()), true)) {
            self::delete_tasks(['userid' => $context->instanceid]);
        }
    }

    /**
     * Delete the matching rows and the ZIP file each one points to.
     *
     * @param array $conditions Field values selecting the rows.
     * @return void
     */
    private static function delete_tasks(array $conditions): void {
        global $DB;

        $tasks = $DB->get_records(self::TABLE, $conditions, '', 'id, filepath');
        foreach ($tasks as $task) {
            self::unlink_if_owned((string) $task->filepath);
        }
        $DB->delete_records(self::TABLE, $conditions);
    }

    /**
     * Unlink a stored path, but only when it is a file inside the block's own temp directory.
     *
     * The column is data, not a trusted location, so a path outside the directory is left alone.
     *
     * @param string $filepath The stored path.
     * @return void
     */
    private static function unlink_if_owned(string $filepath): void {
        if ($filepath === '') {
            return;
        }
        $base = realpath(make_temp_directory('block_download_certificates'));
        $real = realpath($filepath);
        if ($base === false || $real === false || !is_file($real)) {
            return;
        }
        if (strpos($real, $base . DIRECTORY_SEPARATOR) === 0) {
            unlink($real);
        }
    }
}
