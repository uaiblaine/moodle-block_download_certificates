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
 * Downloads served within the request.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

/**
 * Downloads served within the request: one certificate, or a ZIP small enough to build at once.
 *
 * Larger selections go to the background task; the request path refuses them instead of holding
 * the request (and memory) for an unbounded build.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class download {
    /** @var int The most certificates a request builds a ZIP of; the threshold the async UI uses. */
    public const SYNC_LIMIT = 30;

    /**
     * Build the ZIP of a selection in this request's own directory.
     *
     * @param scope $scope Who is asking.
     * @param filter $filter What to keep.
     * @param repository|null $repository The repository to read from; null for the available sources.
     * @return array [string $zippath, string[] $errors]
     * @throws \moodle_exception `nocertificates` for an empty selection, `toomanyforsync` above
     *         {@see self::SYNC_LIMIT}, `cannotcreatezipfile` when no archive could be written.
     */
    public static function build_zip(scope $scope, filter $filter, ?repository $repository = null): array {
        $repository = $repository ?? new repository();

        $count = $repository->count($scope, $filter);
        if ($count === 0) {
            throw new \moodle_exception('nocertificates', 'block_download_certificates');
        }
        if ($count > self::SYNC_LIMIT) {
            throw new \moodle_exception(
                'toomanyforsync',
                'block_download_certificates',
                '',
                (object) ['count' => $count, 'limit' => self::SYNC_LIMIT]
            );
        }

        $zippath = make_request_directory() . '/certificates.zip';
        $builder = new zip_builder($repository->get_sources());
        $errors = $builder->build($repository->list_issue_refs($scope, $filter), $zippath);
        if (!is_file($zippath)) {
            throw new \moodle_exception('cannotcreatezipfile', 'block_download_certificates');
        }
        return [$zippath, $errors];
    }

    /**
     * Build and send the ZIP of a selection; does not return.
     *
     * @param scope $scope Who is asking.
     * @param filter $filter What to keep.
     * @param string $filename The download name of the archive.
     * @throws \moodle_exception As {@see self::build_zip()}.
     */
    public static function send_zip(scope $scope, filter $filter, string $filename): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        [$zippath] = self::build_zip($scope, $filter);
        send_temp_file($zippath, clean_filename($filename));
    }

    /**
     * Send one certificate after the scope check; does not return.
     *
     * @param scope $scope Who is asking.
     * @param string $type The source type.
     * @param int $issueid The id in the source's issue table.
     * @throws \moodle_exception `certificatenotfound`, `accessdenied` or `nopdf`.
     */
    public static function send_single(scope $scope, string $type, int $issueid): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $repository = new repository();
        $issue = $repository->get_issue_for_download($scope, $type, $issueid);
        $source = $repository->get_sources()[$type];
        $content = $source->get_content($issue);
        $filename = $source->get_filename($issue);

        if ($content instanceof \stored_file) {
            send_stored_file($content, 0, 0, true, ['filename' => $filename]);
        } else {
            send_file($content, $filename, 0, 0, true, true, 'application/pdf');
        }
    }
}
