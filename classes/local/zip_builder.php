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
 * Writes certificate PDFs into a ZIP archive.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\local;

use block_download_certificates\local\source\registry;
use block_download_certificates\local\source\source;

/**
 * Writes certificate PDFs into a ZIP archive, one directory per source type.
 *
 * An archive can be built in several calls (the background task appends one batch per run): entry
 * names already in the archive are reserved when it is opened, and `errors.txt` accumulates.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zip_builder {
    /** @var string The archive entry that lists the issues that could not be added. */
    public const ERRORS_ENTRY = 'errors.txt';

    /** @var source[] The sources references are resolved with, keyed by type. */
    private array $sources;

    /** @var \ZipArchive|null The open archive. */
    private ?\ZipArchive $zip = null;

    /**
     * @var array Lower-cased entry name => true. Names are compared without case: extracted on a
     *      case-insensitive file system, `A.pdf` would overwrite `a.pdf`.
     */
    private array $usednames = [];

    /** @var string[] Temporary copies of stored files, read by ZipArchive only when it closes. */
    private array $tempfiles = [];

    /**
     * Constructor.
     *
     * @param source[]|null $sources Sources keyed by type; null for every available one.
     */
    public function __construct(?array $sources = null) {
        $this->sources = $sources ?? registry::get_available();
    }

    /**
     * Add every referenced issue to the archive, creating it or appending to it.
     *
     * A failing issue never stops the others: its localised reason goes to the returned list and to
     * `errors.txt` inside the archive. Exception text from other components (SQL, file system) is
     * never copied.
     *
     * @param array $refs List of [string $type, int $issueid].
     * @param string $zippath The archive path.
     * @param callable|null $progress Called with the number of references processed so far.
     * @param scope|null $scope When given, an issue outside it is skipped with an error line: a list
     *        frozen earlier is checked again as each issue is added.
     * @return string[] The errors of this call, one line per failed issue.
     * @throws \moodle_exception When the archive cannot be opened or written.
     */
    public function build(array $refs, string $zippath, ?callable $progress = null, ?scope $scope = null): array {
        $this->open($zippath);
        $errors = [];
        $processed = 0;
        try {
            foreach ($refs as $ref) {
                $type = (string) ($ref[0] ?? '');
                $issueid = (int) ($ref[1] ?? 0);
                try {
                    $source = $this->sources[$type] ?? null;
                    if (!$source) {
                        throw new \moodle_exception('sourceunavailable', 'block_download_certificates', '', $type);
                    }
                    $issue = $source->get_issue($issueid);
                    if (!$issue) {
                        throw new \moodle_exception('certificatenotfound', 'block_download_certificates');
                    }
                    if ($scope && !$scope->can_see((int) $issue->userid, (int) $issue->courseid)) {
                        throw new \moodle_exception('zip_error_outofscope', 'block_download_certificates');
                    }
                    $this->add($source, $issue);
                } catch (\Throwable $e) {
                    $errors[] = $this->describe_error($type, $issueid, $e);
                }
                $processed++;
                if ($progress) {
                    $progress($processed);
                }
            }
            if ($errors) {
                $this->append_errors($errors);
            }
        } finally {
            $this->close();
        }
        return $errors;
    }

    /**
     * Open an archive, creating it when it does not exist.
     *
     * @param string $zippath The archive path.
     * @throws \moodle_exception When the archive cannot be opened.
     */
    public function open(string $zippath): void {
        $zip = new \ZipArchive();
        if ($zip->open($zippath, \ZipArchive::CREATE) !== true) {
            throw new \moodle_exception('cannotcreatezipfile', 'block_download_certificates');
        }
        $this->zip = $zip;
        $this->usednames = [];
        $this->tempfiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false) {
                $this->usednames[\core_text::strtolower($name)] = true;
            }
        }
    }

    /**
     * Write one issue's PDF into the open archive.
     *
     * A stored file is copied to a temporary file and added by path, so that its content is never
     * held in memory; a generated PDF is added from its string.
     *
     * @param source $source The issue's source.
     * @param \stdClass $issue The issue in the shared shape.
     * @return string The entry name used.
     * @throws \coding_exception When no archive is open.
     * @throws \moodle_exception When the issue has no PDF or the entry cannot be written.
     */
    public function add(source $source, \stdClass $issue): string {
        if (!$this->zip) {
            throw new \coding_exception('zip_builder::add() needs an archive opened by open().');
        }

        $content = $source->get_content($issue);
        $entry = $this->reserve_name($source->get_type(), $source->get_filename($issue));

        if ($content instanceof \stored_file) {
            $tempfile = $content->copy_content_to_temp('block_download_certificates/parts', 'pdf_');
            if ($tempfile === false) {
                throw new \moodle_exception('nopdf', 'block_download_certificates');
            }
            $this->tempfiles[] = $tempfile;
            $added = $this->zip->addFile($tempfile, $entry);
        } else {
            $added = $this->zip->addFromString($entry, $content);
        }
        if (!$added) {
            throw new \moodle_exception('cannotcreatezipfile', 'block_download_certificates');
        }
        return $entry;
    }

    /**
     * Write the archive and remove the temporary copies.
     *
     * An archive that received no entry is not written to disk.
     *
     * @throws \moodle_exception When the archive cannot be written.
     */
    public function close(): void {
        if (!$this->zip) {
            return;
        }
        $closed = $this->zip->close();
        $this->zip = null;
        foreach ($this->tempfiles as $tempfile) {
            @unlink($tempfile);
        }
        $this->tempfiles = [];
        if (!$closed) {
            throw new \moodle_exception('cannotcreatezipfile', 'block_download_certificates');
        }
    }

    /**
     * Reserve a unique entry name: `type/name.pdf`, then `type/name (2).pdf`, and so on.
     *
     * @param string $type The source type, used as directory.
     * @param string $filename The wanted file name.
     * @return string The entry name.
     */
    private function reserve_name(string $type, string $filename): string {
        $filename = clean_filename($filename);
        if ($filename === '') {
            $filename = 'certificate.pdf';
        }
        $dot = strrpos($filename, '.');
        $base = $dot === false ? $filename : substr($filename, 0, $dot);
        $extension = $dot === false ? '' : substr($filename, $dot);

        $candidate = $type . '/' . $filename;
        $n = 1;
        while (isset($this->usednames[\core_text::strtolower($candidate)])) {
            $n++;
            $candidate = $type . '/' . $base . ' (' . $n . ')' . $extension;
        }
        $this->usednames[\core_text::strtolower($candidate)] = true;
        return $candidate;
    }

    /**
     * The localised line for an issue that could not be added.
     *
     * @param string $type The source type of the reference.
     * @param int $issueid The issue id of the reference.
     * @param \Throwable $e What went wrong.
     * @return string
     */
    private function describe_error(string $type, int $issueid, \Throwable $e): string {
        if ($e instanceof \moodle_exception && $e->module === 'block_download_certificates') {
            $reason = get_string($e->errorcode, 'block_download_certificates', $e->a);
        } else {
            $reason = get_string('zip_error_unexpected', 'block_download_certificates');
        }
        return get_string('zip_error_line', 'block_download_certificates', (object) [
            'type' => $type,
            'issueid' => $issueid,
            'reason' => $reason,
        ]);
    }

    /**
     * Append lines to `errors.txt` in the open archive, keeping the lines of earlier calls.
     *
     * @param string[] $errors The lines.
     * @throws \moodle_exception When the entry cannot be written.
     */
    private function append_errors(array $errors): void {
        $existing = $this->zip->getFromName(self::ERRORS_ENTRY);
        $lines = $existing === false || $existing === '' ? [] : [rtrim($existing, "\n")];
        $content = implode("\n", array_merge($lines, $errors)) . "\n";
        if (!$this->zip->addFromString(self::ERRORS_ENTRY, $content, \ZipArchive::FL_OVERWRITE)) {
            throw new \moodle_exception('cannotcreatezipfile', 'block_download_certificates');
        }
    }
}
