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
 * Files of a background ZIP download.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

/**
 * The directory of one background download task, named after its task id, and what it holds: the
 * frozen list of issue references and the ZIP.
 *
 * A directory per task id keeps two tasks from ever writing or deleting each other's archive, which
 * names built from a course or user name did.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class task_storage {
    /** @var string The parent of every task directory, relative to `$CFG->tempdir`. */
    public const BASEDIR = 'block_download_certificates/tasks';

    /** @var string The frozen list of issue references. */
    private const REFS_FILE = 'refs.json';

    /** @var string The archive. */
    private const ZIP_FILE = 'certificates.zip';

    /**
     * The task's directory, created when missing.
     *
     * @param int $taskid The id of the task row.
     * @return string The absolute path.
     * @throws \coding_exception On an invalid task id.
     */
    public static function make_directory(int $taskid): string {
        return make_temp_directory(self::BASEDIR . '/' . self::check_id($taskid));
    }

    /**
     * The task's directory path, without creating it.
     *
     * @param int $taskid The id of the task row.
     * @return string The absolute path.
     * @throws \coding_exception On an invalid task id.
     */
    public static function get_directory(int $taskid): string {
        global $CFG;

        return $CFG->tempdir . '/' . self::BASEDIR . '/' . self::check_id($taskid);
    }

    /**
     * The task's archive path; the directory is created when missing.
     *
     * @param int $taskid The id of the task row.
     * @return string The absolute path.
     */
    public static function get_zip_path(int $taskid): string {
        return self::make_directory($taskid) . '/' . self::ZIP_FILE;
    }

    /**
     * Freeze the list of issue references the task builds its archive from.
     *
     * The file is written beside its final name and renamed, so that a reader never sees half a list.
     *
     * @param int $taskid The id of the task row.
     * @param array $refs List of [string $type, int $issueid].
     * @throws \moodle_exception When the file cannot be written.
     */
    public static function write_refs(int $taskid, array $refs): void {
        $clean = [];
        foreach ($refs as $ref) {
            $clean[] = [(string) ($ref[0] ?? ''), (int) ($ref[1] ?? 0)];
        }
        $path = self::make_directory($taskid) . '/' . self::REFS_FILE;
        $temppath = $path . '.part';
        if (
            file_put_contents($temppath, json_encode($clean)) === false
            || !rename($temppath, $path)
        ) {
            @unlink($temppath);
            throw new \moodle_exception('taskrefsunreadable', 'block_download_certificates');
        }
    }

    /**
     * The frozen list of issue references.
     *
     * @param int $taskid The id of the task row.
     * @return array List of [string $type, int $issueid].
     * @throws \moodle_exception When the list is missing or malformed.
     */
    public static function read_refs(int $taskid): array {
        $path = self::get_directory($taskid) . '/' . self::REFS_FILE;
        $json = is_readable($path) ? file_get_contents($path) : false;
        $data = $json === false ? null : json_decode($json, true);
        if (!is_array($data)) {
            throw new \moodle_exception('taskrefsunreadable', 'block_download_certificates');
        }

        $refs = [];
        foreach ($data as $ref) {
            if (
                !is_array($ref) || count($ref) !== 2
                || !is_string($ref[0] ?? null) || !is_int($ref[1] ?? null)
            ) {
                throw new \moodle_exception('taskrefsunreadable', 'block_download_certificates');
            }
            $refs[] = [$ref[0], $ref[1]];
        }
        return $refs;
    }

    /**
     * Whether the task's frozen list exists.
     *
     * @param int $taskid The id of the task row.
     * @return bool
     */
    public static function has_refs(int $taskid): bool {
        return is_file(self::get_directory($taskid) . '/' . self::REFS_FILE);
    }

    /**
     * Remove the task's directory and everything in it.
     *
     * @param int $taskid The id of the task row.
     */
    public static function delete(int $taskid): void {
        $directory = self::get_directory($taskid);
        if (is_dir($directory)) {
            remove_dir($directory);
        }
    }

    /**
     * Whether a path lies strictly inside the task directories, symbolic links and `..` resolved.
     *
     * @param string $path An absolute path, typically a task row's stored file path.
     * @return bool False for a path that does not exist or resolves elsewhere.
     */
    public static function owns_path(string $path): bool {
        global $CFG;

        $base = realpath($CFG->tempdir . '/' . self::BASEDIR);
        $real = $path === '' ? false : realpath($path);
        return $base !== false && $real !== false && str_starts_with($real, $base . DIRECTORY_SEPARATOR);
    }

    /**
     * Refuse a task id that cannot be a row id.
     *
     * @param int $taskid The id of the task row.
     * @return int The id.
     * @throws \coding_exception When the id is not positive.
     */
    private static function check_id(int $taskid): int {
        if ($taskid <= 0) {
            throw new \coding_exception('Invalid task id: ' . $taskid);
        }
        return $taskid;
    }
}
