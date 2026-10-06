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
 * Certificate packager class for block_download_certificates plugin.
 *
 * Handles ZIP creation, filename generation, and file sending for
 * batch and individual certificate downloads.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch' contact@elearningtouch.com (Maintainer)
 * @author    Thomas Clément 222384061+ClementThomasELT@users.noreply.github.com (Coder)
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Handles ZIP packaging, filename generation, and file delivery.
 */
class block_download_certificates_packager {

    /** @var block_download_certificates_retriever File retriever instance. */
    protected $retriever;

    /** @var block_download_certificates_query Query instance. */
    protected $query;

    /**
     * Constructor.
     *
     * @param block_download_certificates_retriever $retriever Retriever instance
     * @param block_download_certificates_query $query Query instance
     */
    public function __construct(
        block_download_certificates_retriever $retriever,
        block_download_certificates_query $query
    ) {
        $this->retriever = $retriever;
        $this->query = $query;
    }

    // =========================================================================
    // Filename generation methods.
    // =========================================================================

    /**
     * Generate filename for tool_certificate.
     *
     * @param object $cert Certificate data
     * @return string Filename
     */
    public function generate_certificate_filename($cert) {
        $username = $this->get_clean_username($cert);
        $certname = !empty($cert->templatename) ? clean_filename($cert->templatename) : 'certificate';
        $coursename = !empty($cert->coursename) ? '_' . clean_filename($cert->coursename) : '';
        $filename = $username . '_' . $certname . $coursename . '.pdf';
        return $filename;
    }

    /**
     * Generate filename for customcert certificate.
     *
     * @param object $cert Certificate data
     * @return string Filename
     */
    public function generate_customcert_filename($cert) {
        $username = $this->get_clean_username($cert);
        $certname = !empty($cert->certificatename) ? clean_filename($cert->certificatename) : 'certificate';
        $coursename = !empty($cert->coursename) ? '_' . clean_filename($cert->coursename) : '';
        return $username . '_' . $certname . $coursename . '.pdf';
    }

    /**
     * Generate filename for mod_certificate.
     *
     * @param object $cert Certificate data
     * @return string Filename
     */
    public function generate_mod_certificate_filename($cert) {
        $username = $this->get_clean_username($cert);
        $certname = !empty($cert->certificatename) ? clean_filename($cert->certificatename) : 'certificate';
        $coursename = !empty($cert->coursename) ? '_' . clean_filename($cert->coursename) : '';
        return $username . '_' . $certname . $coursename . '.pdf';
    }

    /**
     * Generate filename for simplecertificate.
     *
     * @param object $cert Certificate data
     * @return string Filename
     */
    public function generate_simplecertificate_filename($cert) {
        $username = $this->get_clean_username($cert);
        $certname = !empty($cert->certificatename) ? clean_filename($cert->certificatename) : 'simplecertificate';
        $coursename = !empty($cert->coursename) ? '_' . clean_filename($cert->coursename) : '';
        return $username . '_' . $certname . $coursename . '.pdf';
    }

    /**
     * Generate filename for certificatebeautiful.
     *
     * @param object $cert Certificate data
     * @return string Filename
     */
    public function generate_certificatebeautiful_filename($cert) {
        $username = $this->get_clean_username($cert);
        $certname = !empty($cert->certificatename) ? clean_filename($cert->certificatename) : 'certificatebeautiful';
        $coursename = !empty($cert->coursename) ? '_' . clean_filename($cert->coursename) : '';
        return $username . '_' . $certname . $coursename . '.pdf';
    }

    /**
     * Get a clean username string from certificate data.
     *
     * @param object $cert Certificate data with firstname/lastname or userid
     * @return string Clean username
     */
    protected function get_clean_username($cert) {
        global $DB;

        if (empty($cert->firstname) || empty($cert->lastname)) {
            $userid = !empty($cert->userid) ? $cert->userid : null;
            if ($userid) {
                $user = $DB->get_record('user', ['id' => $userid], 'firstname, lastname');
                if ($user) {
                    $cert->firstname = $user->firstname;
                    $cert->lastname = $user->lastname;
                }
            }
        }

        $firstname = !empty($cert->firstname) ? clean_filename($cert->firstname) : 'Unknown';
        $lastname = !empty($cert->lastname) ? clean_filename($cert->lastname) : 'User';
        return $firstname . '_' . $lastname;
    }

    // =========================================================================
    // File sending methods.
    // =========================================================================

    /**
     * Send PDF file to user for download.
     *
     * @param string $filecontent File content
     * @param string $filename Filename
     */
    public function send_file_to_user($filecontent, $filename) {
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($filecontent));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');

        echo $filecontent;
        exit;
    }

    /**
     * Send PDF content as download (alias for send_file_to_user with cleaner headers).
     *
     * @param string $content PDF content
     * @param string $filename Filename
     */
    public function send_pdf_download($content, $filename) {
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        echo $content;
        exit;
    }

    /**
     * Send ZIP file to user.
     *
     * @param string $filepath ZIP file path
     * @param string $filename ZIP filename
     */
    public function send_zip_file($filepath, $filename) {
        if (!file_exists($filepath)) {
            throw new moodle_exception('filenotfound', 'block_download_certificates');
        }

        // Verify ZIP content.
        $zip = new ZipArchive();
        if ($zip->open($filepath) === true) {
            $zip->close();
        }

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');

        readfile($filepath);
        exit;
    }

    // =========================================================================
    // Certificate content retrieval helpers (delegates to retriever).
    // =========================================================================

    /**
     * Get content for a tool_certificate (file storage + HTTP fallback).
     *
     * @param object $cert Certificate record
     * @return string|false File content or false
     */
    protected function get_tool_certificate_content($cert) {
        $filecontent = $this->retriever->get_certificate_file_content($cert->timecreated, $cert->code);
        if ($filecontent === false) {
            $fileurl = $this->retriever->get_certificate_download_url($cert->timecreated, $cert->code);
            $filecontent = $this->retriever->download_file_via_http($fileurl);
        }
        return $filecontent;
    }

    /**
     * Get content for a customcert certificate (via customcert API).
     *
     * @param object $cert Certificate record
     * @return string|false File content or false
     */
    protected function get_customcert_content($cert) {
        global $DB, $CFG;

        $sql = "SELECT ct.id as templateid, ct.name as templatename, ct.contextid
                FROM {customcert} cc
                JOIN {customcert_templates} ct ON ct.id = cc.templateid
                WHERE cc.id = :certificateid";

        $templateinfo = $DB->get_record_sql($sql, ['certificateid' => $cert->customcertid]);

        if (!$templateinfo) {
            return false;
        }

        $templatedata = new \stdClass();
        $templatedata->id = $templateinfo->templateid;
        $templatedata->name = $templateinfo->templatename;
        $templatedata->contextid = $templateinfo->contextid;

        $template = new \mod_customcert\template($templatedata);
        $filecontent = $template->generate_pdf(false, $cert->userid, true);

        if ($filecontent !== false && !empty($filecontent)) {
            return $filecontent;
        }

        return false;
    }

    // =========================================================================
    // Batch ZIP creation — generic method.
    // =========================================================================

    /**
     * Add certificates to a ZIP archive.
     *
     * This is the core method that processes all certificate types and adds them to a ZIP.
     *
     * @param ZipArchive $zip ZIP archive to add files to
     * @param array $toolcertificates tool_certificate issues
     * @param array $customcertificates customcert issues
     * @param array $modcertificates mod_certificate issues
     * @param array $simplecertificates simplecertificate issues
     * @param array $beautifulcertificates certificatebeautiful issues
     * @param array $addedfiles Reference to track added files (for dedup)
     * @param callable|null $progresscallback Optional callback called after each certificate: fn(int $processed)
     * @return array ['filesadded' => int, 'errors' => array]
     */
    protected function add_certificates_to_zip(
        ZipArchive $zip,
        array $toolcertificates,
        array $customcertificates,
        array $modcertificates,
        array $simplecertificates,
        array $beautifulcertificates,
        array &$addedfiles = [],
        $progresscallback = null
    ) {
        $filesadded = 0;
        $errors = [];
        $processed = 0;

        // Process tool_certificate.
        foreach ($toolcertificates as $cert) {
            try {
                $filecontent = $this->get_tool_certificate_content($cert);
                if ($filecontent !== false) {
                    $filename = 'tool_certificate/' . $this->generate_certificate_filename($cert);
                    // If filename already exists, add a numeric suffix to avoid collision.
                    if (in_array($filename, $addedfiles)) {
                        $counter = 2;
                        $base = pathinfo($filename, PATHINFO_DIRNAME) . '/' .
                                pathinfo($filename, PATHINFO_FILENAME);
                        $ext = pathinfo($filename, PATHINFO_EXTENSION);
                        do {
                            $filename = $base . '_' . $counter . '.' . $ext;
                            $counter++;
                        } while (in_array($filename, $addedfiles));
                    }
                    $zip->addFromString($filename, $filecontent);
                    $addedfiles[] = $filename;
                    $filesadded++;
                } else {
                    $errors[] = 'Failed to download tool_certificate: ' . $cert->code;
                }
            } catch (Exception $e) {
                $errors[] = 'Error downloading tool_certificate ' . $cert->code . ': ' . $e->getMessage();
            }
            unset($filecontent);
            $processed++;
            if ($processed % 50 === 0) {
                gc_collect_cycles();
            }
            if ($progresscallback) {
                $progresscallback($filesadded);
            }
        }

        // Process customcert.
        foreach ($customcertificates as $cert) {
            try {
                $filecontent = $this->get_customcert_content($cert);
                if ($filecontent !== false) {
                    $filename = 'customcert/' . $this->generate_customcert_filename($cert);
                    if (in_array($filename, $addedfiles)) {
                        $counter = 2;
                        $base = pathinfo($filename, PATHINFO_DIRNAME) . '/' .
                                pathinfo($filename, PATHINFO_FILENAME);
                        $ext = pathinfo($filename, PATHINFO_EXTENSION);
                        do {
                            $filename = $base . '_' . $counter . '.' . $ext;
                            $counter++;
                        } while (in_array($filename, $addedfiles));
                    }
                    $zip->addFromString($filename, $filecontent);
                    $addedfiles[] = $filename;
                    $filesadded++;
                } else {
                    $errors[] = 'Failed to generate customcert PDF for user: ' . $cert->userid;
                }
            } catch (Exception $e) {
                $errors[] = 'Error downloading customcert ' . $cert->id . ': ' . $e->getMessage();
            }
            unset($filecontent);
            $processed++;
            if ($processed % 50 === 0) {
                gc_collect_cycles();
            }
            if ($progresscallback) {
                $progresscallback($filesadded);
            }
        }

        // Process mod_certificate.
        foreach ($modcertificates as $cert) {
            try {
                $filecontent = $this->retriever->get_mod_certificate_content(
                    $cert->certificateid, $cert->coursename, $cert->certificatename, $cert->userid
                );
                if ($filecontent !== false && !empty($filecontent)) {
                    $filename = 'mod_certificate/' . $this->generate_mod_certificate_filename($cert);
                    if (in_array($filename, $addedfiles)) {
                        $counter = 2;
                        $base = pathinfo($filename, PATHINFO_DIRNAME) . '/' .
                                pathinfo($filename, PATHINFO_FILENAME);
                        $ext = pathinfo($filename, PATHINFO_EXTENSION);
                        do {
                            $filename = $base . '_' . $counter . '.' . $ext;
                            $counter++;
                        } while (in_array($filename, $addedfiles));
                    }
                    $zip->addFromString($filename, $filecontent);
                    $addedfiles[] = $filename;
                    $filesadded++;
                } else {
                    $errors[] = 'Failed to download mod_certificate PDF for user: ' . $cert->userid;
                }
            } catch (Exception $e) {
                $errors[] = 'Error downloading mod_certificate ' . $cert->id . ': ' . $e->getMessage();
            }
            unset($filecontent);
            $processed++;
            if ($processed % 50 === 0) {
                gc_collect_cycles();
            }
            if ($progresscallback) {
                $progresscallback($filesadded);
            }
        }

        // Process simplecertificate.
        foreach ($simplecertificates as $cert) {
            try {
                $filecontent = $this->retriever->get_simplecertificate_content($cert->code);
                if ($filecontent !== false && !empty($filecontent)) {
                    $filename = 'mod_simplecertificate/' . $this->generate_simplecertificate_filename($cert);
                    if (in_array($filename, $addedfiles)) {
                        $counter = 2;
                        $base = pathinfo($filename, PATHINFO_DIRNAME) . '/' .
                                pathinfo($filename, PATHINFO_FILENAME);
                        $ext = pathinfo($filename, PATHINFO_EXTENSION);
                        do {
                            $filename = $base . '_' . $counter . '.' . $ext;
                            $counter++;
                        } while (in_array($filename, $addedfiles));
                    }
                    $zip->addFromString($filename, $filecontent);
                    $addedfiles[] = $filename;
                    $filesadded++;
                } else {
                    $errors[] = 'Failed to download simplecertificate for user: ' .
                        $cert->userid . ' (code: ' . $cert->code . ')';
                }
            } catch (Exception $e) {
                $errors[] = 'Error downloading simplecertificate ' . $cert->id . ': ' . $e->getMessage();
            }
            unset($filecontent);
            $processed++;
            if ($processed % 50 === 0) {
                gc_collect_cycles();
            }
            if ($progresscallback) {
                $progresscallback($filesadded);
            }
        }

        // Process certificatebeautiful.
        foreach ($beautifulcertificates as $cert) {
            try {
                $filecontent = $this->retriever->get_certificatebeautiful_content($cert->code);
                if ($filecontent !== false && !empty($filecontent)) {
                    $filename = 'mod_certificatebeautiful/' . $this->generate_certificatebeautiful_filename($cert);
                    if (in_array($filename, $addedfiles)) {
                        $counter = 2;
                        $base = pathinfo($filename, PATHINFO_DIRNAME) . '/' .
                                pathinfo($filename, PATHINFO_FILENAME);
                        $ext = pathinfo($filename, PATHINFO_EXTENSION);
                        do {
                            $filename = $base . '_' . $counter . '.' . $ext;
                            $counter++;
                        } while (in_array($filename, $addedfiles));
                    }
                    $zip->addFromString($filename, $filecontent);
                    $addedfiles[] = $filename;
                    $filesadded++;
                } else {
                    $errors[] = 'Failed to download certificatebeautiful for user: ' .
                        $cert->userid . ' (code: ' . $cert->code . ')';
                }
            } catch (Exception $e) {
                $errors[] = 'Error downloading certificatebeautiful ' . $cert->id . ': ' . $e->getMessage();
            }
            unset($filecontent);
            $processed++;
            if ($processed % 50 === 0) {
                gc_collect_cycles();
            }
            if ($progresscallback) {
                $progresscallback($filesadded);
            }
        }

        return ['filesadded' => $filesadded, 'errors' => $errors];
    }

    /**
     * Create a ZIP, add certificates, finalize, and send to user.
     *
     * @param string $zipfilename Name for the ZIP file
     * @param array $toolcerts tool_certificate issues
     * @param array $customcerts customcert issues
     * @param array $modcerts mod_certificate issues
     * @param array $simplecerts simplecertificate issues
     * @param array $beautifulcerts certificatebeautiful issues
     * @param string $errorcontext Context label for error log
     * @param string $novalidexception Exception string key when no valid certificates found
     * @param bool $useTempDir Whether to use make_temp_directory (persistent) instead of make_request_directory
     */
    protected function create_and_send_zip(
        $zipfilename,
        array $toolcerts,
        array $customcerts,
        array $modcerts,
        array $simplecerts,
        array $beautifulcerts,
        $errorcontext,
        $novalidexception,
        $useTempDir = false
    ) {
        if ($useTempDir) {
            $tempdir = make_temp_directory('block_download_certificates');
        } else {
            $tempdir = make_request_directory();
        }

        $zippath = $tempdir . '/' . $zipfilename;

        // Remove existing ZIP to avoid mixing.
        if (file_exists($zippath)) {
            unlink($zippath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zippath, ZipArchive::CREATE) !== true) {
            throw new moodle_exception('cannotcreatezipfile', 'block_download_certificates');
        }

        $addedfiles = [];
        $result = $this->add_certificates_to_zip(
            $zip, $toolcerts, $customcerts, $modcerts, $simplecerts, $beautifulcerts, $addedfiles
        );

        if (!empty($result['errors'])) {
            $errorlog = "Download errors ({$errorcontext}):\n" . implode("\n", $result['errors']);
            $zip->addFromString('download_errors.txt', $errorlog);
        }

        $zip->close();

        if ($result['filesadded'] === 0) {
            unlink($zippath);
            throw new moodle_exception($novalidexception, 'block_download_certificates');
        }

        $this->send_zip_file($zippath, $zipfilename);
        unlink($zippath);
    }

    /**
     * Generate a ZIP file to disk without sending it (for async tasks).
     *
     * @param string $type Download type (all, course, user, cohort, range)
     * @param array $params Parameters for the download filter
     * @param callable|null $progresscallback Optional callback: fn(int $processed)
     * @return string Path to the generated ZIP file
     * @throws moodle_exception
     */
    public function generate_zip_to_file($type, array $params = [], $progresscallback = null) {
        global $DB;

        $dbman = $DB->get_manager();

        // Fetch certificates based on type.
        $certs = $this->fetch_certificates_for_type($type, $params);

        $total = count($certs['tool']) + count($certs['custom']) + count($certs['mod'])
               + count($certs['simple']) + count($certs['beautiful']);

        if ($total === 0) {
            throw new moodle_exception('nocertificates', 'block_download_certificates');
        }

        // Generate ZIP filename.
        $zipfilename = $this->generate_zip_filename($type, $params);

        // Use temp directory (persistent across requests).
        $tempdir = make_temp_directory('block_download_certificates/async');
        $zippath = $tempdir . '/' . $zipfilename;

        if (file_exists($zippath)) {
            unlink($zippath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zippath, ZipArchive::CREATE) !== true) {
            throw new moodle_exception('cannotcreatezipfile', 'block_download_certificates');
        }

        $addedfiles = [];
        $result = $this->add_certificates_to_zip(
            $zip, $certs['tool'], $certs['custom'], $certs['mod'],
            $certs['simple'], $certs['beautiful'], $addedfiles, $progresscallback
        );

        if (!empty($result['errors'])) {
            $errorlog = "Download errors (async {$type}):\n" . implode("\n", $result['errors']);
            $zip->addFromString('download_errors.txt', $errorlog);
        }

        $zip->close();

        if ($result['filesadded'] === 0) {
            unlink($zippath);
            throw new moodle_exception('novalidcertificates', 'block_download_certificates');
        }

        return $zippath;
    }

    /**
     * Generate a ZIP file in batches for async tasks.
     *
     * Processes only a slice of certificates (offset + limit) and appends them
     * to an existing ZIP file. This allows splitting the work across multiple
     * cron runs to avoid memory/timeout limits.
     *
     * @param string $type Download type (all, course, user, cohort, range)
     * @param array $params Parameters for the download filter
     * @param int $offset Number of certificates already processed (skip these)
     * @param int $batchsize Number of certificates to process in this batch
     * @param string|null $existingzippath Path to existing ZIP to append to (null = create new)
     * @param callable|null $progresscallback Optional callback: fn(int $processed)
     * @return array ['zippath' => string, 'processed' => int, 'total' => int, 'errors' => array]
     * @throws moodle_exception
     */
    public function generate_zip_batch(
        $type,
        array $params = [],
        $offset = 0,
        $batchsize = 500,
        $existingzippath = null,
        $progresscallback = null
    ) {
        // Fetch ALL certificate records (metadata only, not PDF content).
        $certs = $this->fetch_certificates_for_type($type, $params);

        // Flatten all types into a single ordered list with type tags.
        $allcerts = [];
        foreach ($certs['tool'] as $cert) {
            $allcerts[] = ['type' => 'tool', 'cert' => $cert];
        }
        foreach ($certs['custom'] as $cert) {
            $allcerts[] = ['type' => 'custom', 'cert' => $cert];
        }
        foreach ($certs['mod'] as $cert) {
            $allcerts[] = ['type' => 'mod', 'cert' => $cert];
        }
        foreach ($certs['simple'] as $cert) {
            $allcerts[] = ['type' => 'simple', 'cert' => $cert];
        }
        foreach ($certs['beautiful'] as $cert) {
            $allcerts[] = ['type' => 'beautiful', 'cert' => $cert];
        }

        $total = count($allcerts);

        // Free the original arrays.
        unset($certs);
        gc_collect_cycles();

        // Extract the batch slice.
        $batch = array_slice($allcerts, $offset, $batchsize);
        unset($allcerts);
        gc_collect_cycles();

        if (empty($batch)) {
            return [
                'zippath' => $existingzippath,
                'processed' => 0,
                'total' => $total,
                'errors' => [],
            ];
        }

        // Determine ZIP path.
        if ($existingzippath && file_exists($existingzippath)) {
            $zippath = $existingzippath;
        } else {
            $zipfilename = $this->generate_zip_filename($type, $params);
            $tempdir = make_temp_directory('block_download_certificates/async');
            $zippath = $tempdir . '/' . $zipfilename;
            if (file_exists($zippath)) {
                unlink($zippath);
            }
        }

        // Open ZIP in append mode if it exists, create mode otherwise.
        $zip = new ZipArchive();
        $zipflags = file_exists($zippath) ? ZipArchive::CREATE : ZipArchive::CREATE;
        if ($zip->open($zippath, $zipflags) !== true) {
            throw new moodle_exception('cannotcreatezipfile', 'block_download_certificates');
        }

        // Build list of already-added files from existing ZIP.
        $addedfiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $addedfiles[] = $zip->getNameIndex($i);
        }

        $filesadded = 0;
        $errors = [];
        $processed = 0;

        foreach ($batch as $item) {
            $cert = $item['cert'];
            $certtype = $item['type'];

            try {
                $filecontent = false;
                $filename = '';

                switch ($certtype) {
                    case 'tool':
                        $filecontent = $this->get_tool_certificate_content($cert);
                        $filename = 'tool_certificate/' . $this->generate_certificate_filename($cert);
                        break;
                    case 'custom':
                        $filecontent = $this->get_customcert_content($cert);
                        $filename = 'customcert/' . $this->generate_customcert_filename($cert);
                        break;
                    case 'mod':
                        $filecontent = $this->retriever->get_mod_certificate_content(
                            $cert->certificateid, $cert->coursename, $cert->certificatename, $cert->userid
                        );
                        $filename = 'mod_certificate/' . $this->generate_mod_certificate_filename($cert);
                        break;
                    case 'simple':
                        $filecontent = $this->retriever->get_simplecertificate_content($cert->code);
                        $filename = 'mod_simplecertificate/' . $this->generate_simplecertificate_filename($cert);
                        break;
                    case 'beautiful':
                        $filecontent = $this->retriever->get_certificatebeautiful_content($cert->code);
                        $filename = 'mod_certificatebeautiful/' . $this->generate_certificatebeautiful_filename($cert);
                        break;
                }

                if ($filecontent !== false && !empty($filecontent)) {
                    // Handle filename collisions.
                    if (in_array($filename, $addedfiles)) {
                        $counter = 2;
                        $base = pathinfo($filename, PATHINFO_DIRNAME) . '/' .
                                pathinfo($filename, PATHINFO_FILENAME);
                        $ext = pathinfo($filename, PATHINFO_EXTENSION);
                        do {
                            $filename = $base . '_' . $counter . '.' . $ext;
                            $counter++;
                        } while (in_array($filename, $addedfiles));
                    }

                    $zip->addFromString($filename, $filecontent);
                    $addedfiles[] = $filename;
                    $filesadded++;
                } else {
                    $errors[] = "Failed to get content for {$certtype} certificate (id: " .
                        ($cert->id ?? 'unknown') . ')';
                }
            } catch (\Exception $e) {
                $errors[] = "Error processing {$certtype} certificate (id: " .
                    ($cert->id ?? 'unknown') . '): ' . $e->getMessage();
            }

            // Free memory immediately.
            unset($filecontent, $cert);
            $processed++;

            if ($processed % 50 === 0) {
                gc_collect_cycles();
            }

            if ($progresscallback) {
                $progresscallback($offset + $processed);
            }
        }

        $zip->close();

        // Free batch memory.
        unset($batch);
        gc_collect_cycles();

        return [
            'zippath' => $zippath,
            'processed' => $processed,
            'total' => $total,
            'errors' => $errors,
            'filesadded' => $filesadded,
        ];
    }

    /**
     * Fetch certificates for a given type and parameters.
     *
     * @param string $type Download type
     * @param array $params Parameters
     * @return array ['tool' => [], 'custom' => [], 'mod' => [], 'simple' => [], 'beautiful' => []]
     */
    public function fetch_certificates_for_type($type, array $params = []) {
        global $DB;

        $dbman = $DB->get_manager();

        switch ($type) {
            case 'all':
                return [
                    'tool' => $this->query->get_tool_certificate_issues(),
                    'custom' => $this->query->get_customcert_issues(),
                    'mod' => $this->query->get_mod_certificate_issues(),
                    'simple' => $this->query->get_simplecertificate_issues(),
                    'beautiful' => $this->query->get_certificatebeautiful_issues(),
                ];

            case 'course':
                $courseid = $params['courseid'] ?? 0;
                return $this->fetch_certificates_for_course($courseid);

            case 'user':
                $userid = $params['userid'] ?? 0;
                return $this->fetch_certificates_for_user($userid);

            case 'cohort':
                $cohortid = $params['cohortid'] ?? 0;
                return $this->fetch_certificates_for_cohort($cohortid);

            case 'range':
                $startdate = $params['startdate'] ?? 0;
                $enddate = $params['enddate'] ?? 0;
                return $this->fetch_certificates_for_range($startdate, $enddate);

            default:
                throw new moodle_exception('invalidtype', 'block_download_certificates');
        }
    }

    /**
     * Count certificates for a given download type.
     *
     * Uses optimized COUNT(*) SQL queries for 'all' type to avoid loading all records.
     *
     * @param string $type Download type
     * @param array $params Parameters
     * @return int Total count
     */
    public function count_certificates_for_type($type, array $params = []) {
        if ($type === 'all') {
            // Use optimized SQL count queries instead of loading all records.
            return $this->query->count_all_certificates();
        }

        // For filtered types, we still need to fetch and count.
        $certs = $this->fetch_certificates_for_type($type, $params);
        return count($certs['tool']) + count($certs['custom']) + count($certs['mod'])
             + count($certs['simple']) + count($certs['beautiful']);
    }

    /**
     * Generate a ZIP filename based on type.
     *
     * @param string $type Download type
     * @param array $params Parameters
     * @return string Filename
     */
    protected function generate_zip_filename($type, array $params = []) {
        global $DB;

        $date = date('Y-m-d_H-i-s');
        switch ($type) {
            case 'all':
                return "all_certificates_{$date}.zip";
            case 'course':
                $course = $DB->get_record('course', ['id' => $params['courseid'] ?? 0], 'fullname');
                $name = $course ? clean_filename($course->fullname) : 'course';
                return "Certificats_{$name}.zip";
            case 'user':
                $user = $DB->get_record('user', ['id' => $params['userid'] ?? 0], 'firstname, lastname');
                $name = $user ? clean_filename($user->firstname . '_' . $user->lastname) : 'user';
                return "Certificats_{$name}.zip";
            case 'cohort':
                $cohort = $DB->get_record('cohort', ['id' => $params['cohortid'] ?? 0], 'name');
                $name = $cohort ? clean_filename($cohort->name) : 'cohort';
                return "Certificats_cohorte_{$name}.zip";
            case 'range':
                $start = date('Y-m-d', $params['startdate'] ?? 0);
                $end = date('Y-m-d', $params['enddate'] ?? 0);
                return "certificates_{$start}_to_{$end}.zip";
            default:
                return "certificates_{$date}.zip";
        }
    }

    /**
     * Fetch certificates for a course.
     *
     * @param int $courseid Course ID
     * @return array
     */
    protected function fetch_certificates_for_course($courseid) {
        global $DB;
        $dbman = $DB->get_manager();

        $toolcerts = [];
        if ($dbman->table_exists('tool_certificate_issues')) {
            try {
                $toolcerts = $DB->get_records_sql(
                    "SELECT tci.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, tc.name as templatename
                     FROM {tool_certificate_issues} tci
                     JOIN {user} u ON u.id = tci.userid
                     LEFT JOIN {course} c ON c.id = tci.courseid
                     LEFT JOIN {tool_certificate_templates} tc ON tc.id = tci.templateid
                     WHERE tci.courseid = ?", [$courseid]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_course: tool_certificate query failed: ' . $e->getMessage());
            }
        }

        $customcerts = [];
        if ($dbman->table_exists('customcert_issues')) {
            try {
                $customcerts = $DB->get_records_sql(
                    "SELECT ci.id, ci.userid, ci.customcertid, ci.code, ci.emailed, ci.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cc.name as certificatename
                     FROM {customcert_issues} ci
                     JOIN {user} u ON u.id = ci.userid
                     JOIN {customcert} cc ON cc.id = ci.customcertid
                     LEFT JOIN {course} c ON c.id = cc.course
                     WHERE cc.course = ?", [$courseid]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_course: customcert query failed: ' . $e->getMessage());
            }
        }

        $modcerts = [];
        if ($dbman->table_exists('certificate_issues')) {
            try {
                $modcerts = $DB->get_records_sql(
                    "SELECT ci.id, ci.userid, ci.certificateid, ci.code, ci.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cert.name as certificatename
                     FROM {certificate_issues} ci
                     JOIN {user} u ON u.id = ci.userid
                     JOIN {certificate} cert ON cert.id = ci.certificateid
                     LEFT JOIN {course} c ON c.id = cert.course
                     WHERE cert.course = ?", [$courseid]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_course: mod_certificate query failed: ' . $e->getMessage());
            }
        }

        $simplecerts = [];
        if ($dbman->table_exists('simplecertificate_issues')) {
            try {
                $simplecerts = $DB->get_records_sql(
                    "SELECT si.id, si.certificateid, si.userid, si.certificatename, si.code,
                            si.timecreated, si.pathnamehash, si.coursename,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename_full
                     FROM {simplecertificate_issues} si
                     JOIN {user} u ON u.id = si.userid
                     LEFT JOIN {course} c ON c.shortname = si.coursename
                     WHERE c.id = ? AND (si.timedeleted IS NULL OR si.timedeleted = 0)", [$courseid]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_course: simplecertificate query failed: ' . $e->getMessage());
            }
        }

        $beautifulcerts = [];
        if ($dbman->table_exists('certificatebeautiful_issue')) {
            try {
                $beautifulcerts = $DB->get_records_sql(
                    "SELECT cbi.id, cbi.userid, cbi.cmid, cbi.certificatebeautifulid, cbi.code,
                            cbi.version, cbi.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cb.name as certificatename
                     FROM {certificatebeautiful_issue} cbi
                     JOIN {user} u ON u.id = cbi.userid
                     LEFT JOIN {certificatebeautiful} cb ON cb.id = cbi.certificatebeautifulid
                     LEFT JOIN {course_modules} cm ON cm.id = cbi.cmid
                     LEFT JOIN {course} c ON c.id = cm.course
                     WHERE c.id = ?", [$courseid]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_course: certificatebeautiful query failed: ' . $e->getMessage());
            }
        }

        return ['tool' => $toolcerts, 'custom' => $customcerts, 'mod' => $modcerts,
                'simple' => $simplecerts, 'beautiful' => $beautifulcerts];
    }

    /**
     * Fetch certificates for a user.
     *
     * @param int $userid User ID
     * @return array
     */
    protected function fetch_certificates_for_user($userid) {
        global $DB;
        $dbman = $DB->get_manager();

        $toolcerts = [];
        if ($dbman->table_exists('tool_certificate_issues')) {
            try {
                $toolcerts = $DB->get_records_sql(
                    "SELECT tci.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, tc.name as templatename
                     FROM {tool_certificate_issues} tci
                     JOIN {user} u ON u.id = tci.userid
                     LEFT JOIN {course} c ON c.id = tci.courseid
                     LEFT JOIN {tool_certificate_templates} tc ON tc.id = tci.templateid
                     WHERE tci.userid = ?", [$userid]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_user: tool_certificate query failed: ' . $e->getMessage());
            }
        }

        $customcerts = [];
        if ($dbman->table_exists('customcert_issues')) {
            try {
                $customcerts = $DB->get_records_sql(
                    "SELECT ci.id, ci.userid, ci.customcertid, ci.code, ci.emailed, ci.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cc.name as certificatename
                     FROM {customcert_issues} ci
                     JOIN {user} u ON u.id = ci.userid
                     JOIN {customcert} cc ON cc.id = ci.customcertid
                     LEFT JOIN {course} c ON c.id = cc.course
                     WHERE ci.userid = ?", [$userid]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_user: customcert query failed: ' . $e->getMessage());
            }
        }

        $modcerts = [];
        if ($dbman->table_exists('certificate_issues')) {
            try {
                $modcerts = $DB->get_records_sql(
                    "SELECT ci.id, ci.userid, ci.certificateid, ci.code, ci.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cert.name as certificatename
                     FROM {certificate_issues} ci
                     JOIN {user} u ON u.id = ci.userid
                     JOIN {certificate} cert ON cert.id = ci.certificateid
                     LEFT JOIN {course} c ON c.id = cert.course
                     WHERE ci.userid = ?", [$userid]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_user: mod_certificate query failed: ' . $e->getMessage());
            }
        }

        $simplecerts = [];
        if ($dbman->table_exists('simplecertificate_issues')) {
            try {
                $simplecerts = $DB->get_records_sql(
                    "SELECT si.id, si.certificateid, si.userid, si.certificatename, si.code,
                            si.timecreated, si.pathnamehash, si.coursename,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename_full
                     FROM {simplecertificate_issues} si
                     JOIN {user} u ON u.id = si.userid
                     LEFT JOIN {course} c ON c.shortname = si.coursename
                     WHERE si.userid = ? AND (si.timedeleted IS NULL OR si.timedeleted = 0)", [$userid]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_user: simplecertificate query failed: ' . $e->getMessage());
            }
        }

        $beautifulcerts = [];
        if ($dbman->table_exists('certificatebeautiful_issue')) {
            try {
                $beautifulcerts = $DB->get_records_sql(
                    "SELECT cbi.id, cbi.userid, cbi.cmid, cbi.certificatebeautifulid, cbi.code,
                            cbi.version, cbi.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cb.name as certificatename
                     FROM {certificatebeautiful_issue} cbi
                     JOIN {user} u ON u.id = cbi.userid
                     LEFT JOIN {certificatebeautiful} cb ON cb.id = cbi.certificatebeautifulid
                     LEFT JOIN {course_modules} cm ON cm.id = cbi.cmid
                     LEFT JOIN {course} c ON c.id = cm.course
                     WHERE cbi.userid = ?", [$userid]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_user: certificatebeautiful query failed: ' . $e->getMessage());
            }
        }

        return ['tool' => $toolcerts, 'custom' => $customcerts, 'mod' => $modcerts,
                'simple' => $simplecerts, 'beautiful' => $beautifulcerts];
    }

    /**
     * Fetch certificates for a cohort (all members).
     *
     * @param int $cohortid Cohort ID
     * @return array
     */
    protected function fetch_certificates_for_cohort($cohortid) {
        global $DB;
        $dbman = $DB->get_manager();

        // Get cohort member IDs.
        $members = $DB->get_records('cohort_members', ['cohortid' => $cohortid], '', 'userid');
        if (empty($members)) {
            return ['tool' => [], 'custom' => [], 'mod' => [], 'simple' => [], 'beautiful' => []];
        }
        $userids = array_keys($members);
        list($insql, $inparams) = $DB->get_in_or_equal($userids);

        $toolcerts = [];
        if ($dbman->table_exists('tool_certificate_issues')) {
            try {
                $toolcerts = $DB->get_records_sql(
                    "SELECT tci.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, tc.name as templatename
                     FROM {tool_certificate_issues} tci
                     JOIN {user} u ON u.id = tci.userid
                     LEFT JOIN {course} c ON c.id = tci.courseid
                     LEFT JOIN {tool_certificate_templates} tc ON tc.id = tci.templateid
                     WHERE tci.userid $insql", $inparams
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_cohort: tool_certificate query failed: ' . $e->getMessage());
            }
        }

        $customcerts = [];
        if ($dbman->table_exists('customcert_issues')) {
            try {
                $customcerts = $DB->get_records_sql(
                    "SELECT ci.id, ci.userid, ci.customcertid, ci.code, ci.emailed, ci.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cc.name as certificatename
                     FROM {customcert_issues} ci
                     JOIN {user} u ON u.id = ci.userid
                     JOIN {customcert} cc ON cc.id = ci.customcertid
                     LEFT JOIN {course} c ON c.id = cc.course
                     WHERE ci.userid $insql", $inparams
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_cohort: customcert query failed: ' . $e->getMessage());
            }
        }

        $modcerts = [];
        if ($dbman->table_exists('certificate_issues')) {
            try {
                $modcerts = $DB->get_records_sql(
                    "SELECT ci.id, ci.userid, ci.certificateid, ci.code, ci.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cert.name as certificatename
                     FROM {certificate_issues} ci
                     JOIN {user} u ON u.id = ci.userid
                     JOIN {certificate} cert ON cert.id = ci.certificateid
                     LEFT JOIN {course} c ON c.id = cert.course
                     WHERE ci.userid $insql", $inparams
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_cohort: mod_certificate query failed: ' . $e->getMessage());
            }
        }

        $simplecerts = [];
        if ($dbman->table_exists('simplecertificate_issues')) {
            try {
                $simplecerts = $DB->get_records_sql(
                    "SELECT si.id, si.certificateid, si.userid, si.certificatename, si.code,
                            si.timecreated, si.pathnamehash, si.coursename,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename_full
                     FROM {simplecertificate_issues} si
                     JOIN {user} u ON u.id = si.userid
                     LEFT JOIN {course} c ON c.shortname = si.coursename
                     WHERE si.userid $insql AND (si.timedeleted IS NULL OR si.timedeleted = 0)", $inparams
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_cohort: simplecertificate query failed: ' . $e->getMessage());
            }
        }

        $beautifulcerts = [];
        if ($dbman->table_exists('certificatebeautiful_issue')) {
            try {
                $beautifulcerts = $DB->get_records_sql(
                    "SELECT cbi.id, cbi.userid, cbi.cmid, cbi.certificatebeautifulid, cbi.code,
                            cbi.version, cbi.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cb.name as certificatename
                     FROM {certificatebeautiful_issue} cbi
                     JOIN {user} u ON u.id = cbi.userid
                     LEFT JOIN {certificatebeautiful} cb ON cb.id = cbi.certificatebeautifulid
                     LEFT JOIN {course_modules} cm ON cm.id = cbi.cmid
                     LEFT JOIN {course} c ON c.id = cm.course
                     WHERE cbi.userid $insql", $inparams
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_cohort: certificatebeautiful query failed: ' . $e->getMessage());
            }
        }

        return ['tool' => $toolcerts, 'custom' => $customcerts, 'mod' => $modcerts,
                'simple' => $simplecerts, 'beautiful' => $beautifulcerts];
    }

    /**
     * Fetch certificates for a date range.
     *
     * @param int $startdate Start timestamp
     * @param int $enddate End timestamp
     * @return array
     */
    protected function fetch_certificates_for_range($startdate, $enddate) {
        global $DB;
        $dbman = $DB->get_manager();

        $toolcerts = [];
        if ($dbman->table_exists('tool_certificate_issues')) {
            try {
                $toolcerts = $DB->get_records_sql(
                    "SELECT tci.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, tc.name as templatename
                     FROM {tool_certificate_issues} tci
                     JOIN {user} u ON u.id = tci.userid
                     LEFT JOIN {course} c ON c.id = tci.courseid
                     LEFT JOIN {tool_certificate_templates} tc ON tc.id = tci.templateid
                     WHERE tci.timecreated >= ? AND tci.timecreated <= ?", [$startdate, $enddate]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_range: tool_certificate query failed: ' . $e->getMessage());
            }
        }

        $customcerts = [];
        if ($dbman->table_exists('customcert_issues')) {
            try {
                $customcerts = $DB->get_records_sql(
                    "SELECT ci.id, ci.userid, ci.customcertid, ci.code, ci.emailed, ci.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cc.name as certificatename
                     FROM {customcert_issues} ci
                     JOIN {user} u ON u.id = ci.userid
                     JOIN {customcert} cc ON cc.id = ci.customcertid
                     LEFT JOIN {course} c ON c.id = cc.course
                     WHERE ci.timecreated >= ? AND ci.timecreated <= ?", [$startdate, $enddate]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_range: customcert query failed: ' . $e->getMessage());
            }
        }

        $modcerts = [];
        if ($dbman->table_exists('certificate_issues')) {
            try {
                $modcerts = $DB->get_records_sql(
                    "SELECT ci.id, ci.userid, ci.certificateid, ci.code, ci.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cert.name as certificatename
                     FROM {certificate_issues} ci
                     JOIN {user} u ON u.id = ci.userid
                     JOIN {certificate} cert ON cert.id = ci.certificateid
                     LEFT JOIN {course} c ON c.id = cert.course
                     WHERE ci.timecreated >= ? AND ci.timecreated <= ?", [$startdate, $enddate]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_range: mod_certificate query failed: ' . $e->getMessage());
            }
        }

        $simplecerts = [];
        if ($dbman->table_exists('simplecertificate_issues')) {
            try {
                $simplecerts = $DB->get_records_sql(
                    "SELECT si.id, si.certificateid, si.userid, si.certificatename, si.code,
                            si.timecreated, si.pathnamehash, si.coursename,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename_full
                     FROM {simplecertificate_issues} si
                     JOIN {user} u ON u.id = si.userid
                     LEFT JOIN {course} c ON c.shortname = si.coursename
                     WHERE si.timecreated >= ? AND si.timecreated <= ?
                     AND (si.timedeleted IS NULL OR si.timedeleted = 0)", [$startdate, $enddate]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_range: simplecertificate query failed: ' . $e->getMessage());
            }
        }

        $beautifulcerts = [];
        if ($dbman->table_exists('certificatebeautiful_issue')) {
            try {
                $beautifulcerts = $DB->get_records_sql(
                    "SELECT cbi.id, cbi.userid, cbi.cmid, cbi.certificatebeautifulid, cbi.code,
                            cbi.version, cbi.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cb.name as certificatename
                     FROM {certificatebeautiful_issue} cbi
                     JOIN {user} u ON u.id = cbi.userid
                     LEFT JOIN {certificatebeautiful} cb ON cb.id = cbi.certificatebeautifulid
                     LEFT JOIN {course_modules} cm ON cm.id = cbi.cmid
                     LEFT JOIN {course} c ON c.id = cm.course
                     WHERE cbi.timecreated >= ? AND cbi.timecreated <= ?", [$startdate, $enddate]
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_range: certificatebeautiful query failed: ' . $e->getMessage());
            }
        }

        return ['tool' => $toolcerts, 'custom' => $customcerts, 'mod' => $modcerts,
                'simple' => $simplecerts, 'beautiful' => $beautifulcerts];
    }

    // =========================================================================
    // Helper de vérification d'accès.
    // =========================================================================

    /**
     * Vérifie que l'utilisateur courant est gestionnaire (block/download_certificates:manage)
     * ou enseignant dans au moins un cours.
     *
     * Utilisé par toutes les méthodes download_single_* qui peuvent être appelées
     * aussi bien par un gestionnaire que par un enseignant.
     * La vérification de périmètre (quel user, quel cours) est faite en amont
     * dans download_precise.php et download_user.php.
     *
     * @throws required_capability_exception Si l'utilisateur n'a ni le rôle gestionnaire ni enseignant
     */
    private function require_manager_or_teacher(): void {
        global $USER;
        $context = context_system::instance();
        if (has_capability('block/download_certificates:manage', $context)) {
            return; // Gestionnaire OK.
        }
        require_once(__DIR__ . '/certificate_access.php');
        $access = new block_download_certificates_access();
        if ($access->is_teacher($USER->id)) {
            return; // Enseignant OK.
        }
        // Ni l'un ni l'autre : on lève l'exception Moodle standard.
        require_capability('block/download_certificates:manage', $context);
    }

    // =========================================================================
    // Public batch download methods.
    // =========================================================================


    /**
     * Download all certificates as ZIP.
     */
    public function download_all_certificates() {
        $context = context_system::instance();
        require_capability('block/download_certificates:manage', $context);

        $toolcerts = $this->query->get_tool_certificate_issues();
        $customcerts = $this->query->get_customcert_issues();
        $modcerts = $this->query->get_mod_certificate_issues();
        $simplecerts = $this->query->get_simplecertificate_issues();
        $beautifulcerts = $this->query->get_certificatebeautiful_issues();

        if (empty($toolcerts) && empty($customcerts) && empty($modcerts) &&
            empty($simplecerts) && empty($beautifulcerts)) {
            throw new moodle_exception('nocertificates', 'block_download_certificates');
        }

        $zipfilename = 'all_certificates_' . date('Y-m-d_H-i-s') . '.zip';
        $this->create_and_send_zip(
            $zipfilename, $toolcerts, $customcerts, $modcerts, $simplecerts, $beautifulcerts,
            'all certificates', 'novalidcertificates'
        );
    }

    /**
     * Download certificates by date range as ZIP.
     *
     * @param int $startdate Start date timestamp
     * @param int $enddate End date timestamp
     */
    public function download_certificates_by_date_range($startdate, $enddate) {
        global $DB;

        $context = context_system::instance();
        require_capability('block/download_certificates:manage', $context);

        if ($startdate > $enddate) {
            throw new moodle_exception('invalidaterange', 'block_download_certificates');
        }

        $dbman = $DB->get_manager();

        $toolcerts = [];
        if ($dbman->table_exists('tool_certificate_issues')) {
            try {
                $sql = "SELECT tci.id, tci.userid, tci.templateid, tci.code, tci.emailed,
                               tci.timecreated, tci.expires, tci.data, tci.component, tci.courseid,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               tc.name as templatename
                        FROM {tool_certificate_issues} tci
                        JOIN {user} u ON u.id = tci.userid
                        LEFT JOIN {course} c ON c.id = tci.courseid
                        LEFT JOIN {tool_certificate_templates} tc ON tc.id = tci.templateid
                        WHERE tci.timecreated >= ? AND tci.timecreated <= ?
                        ORDER BY tci.timecreated DESC";
                $toolcerts = $DB->get_records_sql($sql, [$startdate, $enddate]);
            } catch (Exception $e) {
                debugging('download_certificates_by_date_range: tool_certificate query failed: ' . $e->getMessage());
            }
        }

        $customcerts = [];
        if ($dbman->table_exists('customcert_issues')) {
            try {
                $sql = "SELECT ci.id, ci.userid, ci.customcertid, ci.code, ci.emailed, ci.timecreated,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               cc.name as certificatename
                        FROM {customcert_issues} ci
                        JOIN {user} u ON u.id = ci.userid
                        JOIN {customcert} cc ON cc.id = ci.customcertid
                        LEFT JOIN {course} c ON c.id = cc.course
                        WHERE ci.timecreated >= ? AND ci.timecreated <= ?
                        ORDER BY ci.timecreated DESC";
                $customcerts = $DB->get_records_sql($sql, [$startdate, $enddate]);
            } catch (Exception $e) {
                debugging('download_certificates_by_date_range: customcert query failed: ' . $e->getMessage());
            }
        }

        $modcerts = [];
        if ($dbman->table_exists('certificate_issues')) {
            try {
                $sql = "SELECT ci.id, ci.userid, ci.certificateid, ci.code, ci.timecreated,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               cert.name as certificatename
                        FROM {certificate_issues} ci
                        JOIN {user} u ON u.id = ci.userid
                        JOIN {certificate} cert ON cert.id = ci.certificateid
                        LEFT JOIN {course} c ON c.id = cert.course
                        WHERE ci.timecreated >= ? AND ci.timecreated <= ?
                        ORDER BY ci.timecreated DESC";
                $modcerts = $DB->get_records_sql($sql, [$startdate, $enddate]);
            } catch (Exception $e) {
                debugging('download_certificates_by_date_range: mod_certificate query failed: ' . $e->getMessage());
            }
        }

        $simplecerts = [];
        if ($dbman->table_exists('simplecertificate_issues')) {
            try {
                $sql = "SELECT si.id, si.certificateid, si.userid, si.certificatename, si.code,
                               si.timecreated, si.timedeleted, si.haschange, si.pathnamehash, si.coursename,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename_full
                        FROM {simplecertificate_issues} si
                        JOIN {user} u ON u.id = si.userid
                        LEFT JOIN {course} c ON c.shortname = si.coursename
                        WHERE si.timecreated >= ? AND si.timecreated <= ?
                        AND (si.timedeleted IS NULL OR si.timedeleted = 0)
                        ORDER BY si.timecreated DESC";
                $simplecerts = $DB->get_records_sql($sql, [$startdate, $enddate]);
            } catch (Exception $e) {
                debugging('download_certificates_by_date_range: simplecertificate query failed: ' . $e->getMessage());
            }
        }

        $beautifulcerts = [];
        if ($dbman->table_exists('certificatebeautiful_issue')) {
            try {
                $sql = "SELECT cbi.id, cbi.userid, cbi.cmid, cbi.certificatebeautifulid, cbi.code,
                               cbi.version, cbi.timecreated,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               cb.name as certificatename
                        FROM {certificatebeautiful_issue} cbi
                        JOIN {user} u ON u.id = cbi.userid
                        LEFT JOIN {certificatebeautiful} cb ON cb.id = cbi.certificatebeautifulid
                        LEFT JOIN {course_modules} cm ON cm.id = cbi.cmid
                        LEFT JOIN {course} c ON c.id = cm.course
                        WHERE cbi.timecreated >= ? AND cbi.timecreated <= ?
                        ORDER BY cbi.timecreated DESC";
                $beautifulcerts = $DB->get_records_sql($sql, [$startdate, $enddate]);
            } catch (Exception $e) {
                debugging('download_certificates_by_date_range: certificatebeautiful query failed: ' . $e->getMessage());
            }
        }

        if (empty($toolcerts) && empty($customcerts) && empty($modcerts) &&
            empty($simplecerts) && empty($beautifulcerts)) {
            throw new moodle_exception('nocertificatesinrange', 'block_download_certificates');
        }

        $startdatestr = date('Y-m-d', $startdate);
        $enddatestr = date('Y-m-d', $enddate);
        $zipfilename = "certificates_{$startdatestr}_to_{$enddatestr}.zip";

        $this->create_and_send_zip(
            $zipfilename, $toolcerts, $customcerts, $modcerts, $simplecerts, $beautifulcerts,
            "date range {$startdatestr} to {$enddatestr}", 'novalidcertificatesinrange'
        );
    }

    /**
     * Download all certificates for a specific course as ZIP.
     *
     * @param int $courseid Course ID
     */
    public function download_certificates_by_course($courseid) {
        global $DB;

        $context = context_system::instance();
        require_capability('block/download_certificates:manage', $context);

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            throw new moodle_exception('coursenotfound', 'block_download_certificates');
        }

        $dbman = $DB->get_manager();

        $toolcerts = [];
        if ($dbman->table_exists('tool_certificate_issues')) {
            try {
                $sql = "SELECT tci.id, tci.userid, tci.templateid, tci.code, tci.emailed,
                               tci.timecreated, tci.expires, tci.data, tci.component, tci.courseid,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               tc.name as templatename
                        FROM {tool_certificate_issues} tci
                        JOIN {user} u ON u.id = tci.userid
                        LEFT JOIN {course} c ON c.id = tci.courseid
                        LEFT JOIN {tool_certificate_templates} tc ON tc.id = tci.templateid
                        WHERE tci.courseid = :courseid
                        ORDER BY u.lastname, u.firstname, tci.timecreated";
                $toolcerts = $DB->get_records_sql($sql, ['courseid' => $courseid]);
            } catch (Exception $e) {
                debugging('download_certificates_by_course: tool_certificate query failed: ' . $e->getMessage());
            }
        }

        $customcerts = [];
        if ($dbman->table_exists('customcert_issues')) {
            try {
                $sql = "SELECT ci.id, ci.userid, ci.customcertid, ci.code, ci.emailed, ci.timecreated,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               cc.name as certificatename
                        FROM {customcert_issues} ci
                        JOIN {user} u ON u.id = ci.userid
                        JOIN {customcert} cc ON cc.id = ci.customcertid
                        LEFT JOIN {course} c ON c.id = cc.course
                        WHERE cc.course = :courseid
                        ORDER BY u.lastname, u.firstname, ci.timecreated";
                $customcerts = $DB->get_records_sql($sql, ['courseid' => $courseid]);
            } catch (Exception $e) {
                debugging('download_certificates_by_course: customcert query failed: ' . $e->getMessage());
            }
        }

        $modcerts = [];
        if ($dbman->table_exists('certificate_issues')) {
            try {
                $sql = "SELECT ci.id, ci.userid, ci.certificateid, ci.code, ci.timecreated,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               cert.name as certificatename
                        FROM {certificate_issues} ci
                        JOIN {user} u ON u.id = ci.userid
                        JOIN {certificate} cert ON cert.id = ci.certificateid
                        LEFT JOIN {course} c ON c.id = cert.course
                        WHERE cert.course = :courseid
                        ORDER BY u.lastname, u.firstname, ci.timecreated";
                $modcerts = $DB->get_records_sql($sql, ['courseid' => $courseid]);
            } catch (Exception $e) {
                debugging('download_certificates_by_course: mod_certificate query failed: ' . $e->getMessage());
            }
        }

        $simplecerts = [];
        if ($dbman->table_exists('simplecertificate_issues')) {
            try {
                $sql = "SELECT si.id, si.certificateid, si.userid, si.certificatename, si.code,
                               si.timecreated, si.timedeleted, si.haschange, si.pathnamehash, si.coursename,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename_full
                        FROM {simplecertificate_issues} si
                        JOIN {user} u ON u.id = si.userid
                        LEFT JOIN {course} c ON c.shortname = si.coursename
                        WHERE c.id = :courseid AND (si.timedeleted IS NULL OR si.timedeleted = 0)
                        ORDER BY u.lastname, u.firstname, si.timecreated";
                $simplecerts = $DB->get_records_sql($sql, ['courseid' => $courseid]);
            } catch (Exception $e) {
                debugging('download_certificates_by_course: simplecertificate query failed: ' . $e->getMessage());
            }
        }

        $beautifulcerts = [];
        if ($dbman->table_exists('certificatebeautiful_issue')) {
            try {
                $sql = "SELECT cbi.id, cbi.userid, cbi.cmid, cbi.certificatebeautifulid, cbi.code,
                               cbi.version, cbi.timecreated,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               cb.name as certificatename
                        FROM {certificatebeautiful_issue} cbi
                        JOIN {user} u ON u.id = cbi.userid
                        LEFT JOIN {certificatebeautiful} cb ON cb.id = cbi.certificatebeautifulid
                        LEFT JOIN {course_modules} cm ON cm.id = cbi.cmid
                        LEFT JOIN {course} c ON c.id = cm.course
                        WHERE c.id = :courseid
                        ORDER BY u.lastname, u.firstname, cbi.timecreated";
                $beautifulcerts = $DB->get_records_sql($sql, ['courseid' => $courseid]);
            } catch (Exception $e) {
                debugging('download_certificates_by_course: certificatebeautiful query failed: ' . $e->getMessage());
            }
        }

        if (empty($toolcerts) && empty($customcerts) && empty($modcerts) &&
            empty($simplecerts) && empty($beautifulcerts)) {
            throw new moodle_exception('nocertificatesforcourse', 'block_download_certificates');
        }

        $cleancoursename = clean_filename($course->fullname);
        $zipfilename = "Certificats_{$cleancoursename}.zip";

        $this->create_and_send_zip(
            $zipfilename, $toolcerts, $customcerts, $modcerts, $simplecerts, $beautifulcerts,
            "course '{$course->fullname}'", 'novalidcertificatesforcourse', true
        );
    }

    /**
     * Download all certificates for a specific user as ZIP.
     *
     * @param int $userid User ID
     */
    public function download_user_certificates($userid) {
        global $DB, $USER;

        if ($userid !== $USER->id) {
            $context = context_system::instance();
            // Autoriser les enseignants (la vérification de périmètre est faite dans download_user.php).
            require_once(__DIR__ . '/certificate_access.php');
            $access = new block_download_certificates_access();
            if (!$access->is_teacher($USER->id)) {
                require_capability('block/download_certificates:manage', $context);
            }
        }

        $dbman = $DB->get_manager();

        $toolcerts = [];
        if ($dbman->table_exists('tool_certificate_issues')) {
            try {
                $sql = "SELECT tci.*, u.firstname, u.lastname, c.fullname as coursename, tc.name as templatename
                        FROM {tool_certificate_issues} tci
                        JOIN {user} u ON u.id = tci.userid
                        LEFT JOIN {course} c ON c.id = tci.courseid
                        LEFT JOIN {tool_certificate_templates} tc ON tc.id = tci.templateid
                        WHERE tci.userid = ?
                        ORDER BY tci.timecreated DESC";
                $toolcerts = $DB->get_records_sql($sql, [$userid]);
            } catch (Exception $e) {
                debugging('download_user_certificates: tool_certificate query failed: ' . $e->getMessage());
            }
        }

        $customcerts = [];
        if ($dbman->table_exists('customcert_issues')) {
            try {
                $sql = "SELECT ci.id, ci.userid, ci.customcertid, ci.code, ci.emailed, ci.timecreated,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               cc.name as certificatename
                        FROM {customcert_issues} ci
                        JOIN {user} u ON u.id = ci.userid
                        JOIN {customcert} cc ON cc.id = ci.customcertid
                        LEFT JOIN {course} c ON c.id = cc.course
                        WHERE ci.userid = ?
                        ORDER BY ci.timecreated DESC";
                $customcerts = $DB->get_records_sql($sql, [$userid]);
            } catch (Exception $e) {
                debugging('download_user_certificates: customcert query failed: ' . $e->getMessage());
            }
        }

        $modcerts = [];
        if ($dbman->table_exists('certificate_issues')) {
            try {
                $sql = "SELECT ci.id, ci.userid, ci.certificateid, ci.code, ci.timecreated,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               cert.name as certificatename
                        FROM {certificate_issues} ci
                        JOIN {user} u ON u.id = ci.userid
                        JOIN {certificate} cert ON cert.id = ci.certificateid
                        LEFT JOIN {course} c ON c.id = cert.course
                        WHERE ci.userid = ?
                        ORDER BY ci.timecreated DESC";
                $modcerts = $DB->get_records_sql($sql, [$userid]);
            } catch (Exception $e) {
                debugging('download_user_certificates: mod_certificate query failed: ' . $e->getMessage());
            }
        }

        $simplecerts = [];
        if ($dbman->table_exists('simplecertificate_issues')) {
            try {
                $sql = "SELECT si.id, si.certificateid, si.userid, si.certificatename, si.code,
                               si.timecreated, si.timedeleted, si.haschange, si.pathnamehash, si.coursename,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename_full
                        FROM {simplecertificate_issues} si
                        JOIN {user} u ON u.id = si.userid
                        LEFT JOIN {course} c ON c.shortname = si.coursename
                        WHERE si.userid = ? AND (si.timedeleted IS NULL OR si.timedeleted = 0)
                        ORDER BY si.timecreated DESC";
                $simplecerts = $DB->get_records_sql($sql, [$userid]);
            } catch (Exception $e) {
                debugging('download_user_certificates: simplecertificate query failed: ' . $e->getMessage());
            }
        }

        $beautifulcerts = [];
        if ($dbman->table_exists('certificatebeautiful_issue')) {
            try {
                $sql = "SELECT cbi.id, cbi.userid, cbi.cmid, cbi.certificatebeautifulid, cbi.code,
                               cbi.version, cbi.timecreated,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               cb.name as certificatename
                        FROM {certificatebeautiful_issue} cbi
                        JOIN {user} u ON u.id = cbi.userid
                        LEFT JOIN {certificatebeautiful} cb ON cb.id = cbi.certificatebeautifulid
                        LEFT JOIN {course_modules} cm ON cm.id = cbi.cmid
                        LEFT JOIN {course} c ON c.id = cm.course
                        WHERE cbi.userid = ?
                        ORDER BY cbi.timecreated DESC";
                $beautifulcerts = $DB->get_records_sql($sql, [$userid]);
            } catch (Exception $e) {
                debugging('download_user_certificates: certificatebeautiful query failed: ' . $e->getMessage());
            }
        }

        if (empty($toolcerts) && empty($customcerts) && empty($modcerts) &&
            empty($simplecerts) && empty($beautifulcerts)) {
            throw new moodle_exception('nocertificatesuser', 'block_download_certificates');
        }

        $user = $DB->get_record('user', ['id' => $userid],
            'firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename');
        $username = fullname($user);
        $zipfilename = 'certificates_' . clean_filename($username) . '.zip';

        $this->create_and_send_zip(
            $zipfilename, $toolcerts, $customcerts, $modcerts, $simplecerts, $beautifulcerts,
            "user '{$username}'", 'novalidcertificatesuser'
        );
    }

    /**
     * Download certificates for all members of a cohort.
     *
     * @param int $cohortid Cohort ID
     */
    public function download_cohort_certificates($cohortid) {
        global $DB;

        $cohort = $DB->get_record('cohort', ['id' => $cohortid], '*', MUST_EXIST);

        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                FROM {cohort_members} cm
                JOIN {user} u ON u.id = cm.userid
                WHERE cm.cohortid = :cohortid
                AND u.deleted = 0
                ORDER BY u.lastname, u.firstname";
        $cohortmembers = $DB->get_records_sql($sql, ['cohortid' => $cohortid]);

        if (empty($cohortmembers)) {
            throw new moodle_exception('nocohortmembers', 'block_download_certificates');
        }

        $userids = array_keys($cohortmembers);
        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $dbman = $DB->get_manager();

        $toolcerts = [];
        if ($dbman->table_exists('tool_certificate_issues')) {
            try {
                $sql = "SELECT tci.id, tci.userid, tci.templateid, tci.code, tci.emailed,
                               tci.timecreated, tci.expires, tci.data, tci.component, tci.courseid,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               tc.name as templatename
                        FROM {tool_certificate_issues} tci
                        JOIN {user} u ON u.id = tci.userid
                        LEFT JOIN {course} c ON c.id = tci.courseid
                        LEFT JOIN {tool_certificate_templates} tc ON tc.id = tci.templateid
                        WHERE tci.userid $insql
                        ORDER BY u.lastname, u.firstname, tci.timecreated DESC";
                $toolcerts = $DB->get_records_sql($sql, $params);
            } catch (Exception $e) {
                debugging('download_cohort_certificates: tool_certificate query failed: ' . $e->getMessage());
            }
        }

        $customcerts = [];
        if ($dbman->table_exists('customcert_issues')) {
            try {
                $sql = "SELECT ci.id, ci.userid, ci.customcertid, ci.code, ci.emailed, ci.timecreated,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               cc.name as certificatename
                        FROM {customcert_issues} ci
                        JOIN {user} u ON u.id = ci.userid
                        JOIN {customcert} cc ON cc.id = ci.customcertid
                        LEFT JOIN {course} c ON c.id = cc.course
                        WHERE ci.userid $insql
                        ORDER BY u.lastname, u.firstname, ci.timecreated DESC";
                $customcerts = $DB->get_records_sql($sql, $params);
            } catch (Exception $e) {
                debugging('download_cohort_certificates: customcert query failed: ' . $e->getMessage());
            }
        }

        $modcerts = [];
        if ($dbman->table_exists('certificate_issues')) {
            try {
                $sql = "SELECT ci.id, ci.userid, ci.certificateid, ci.code, ci.timecreated,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               cert.name as certificatename
                        FROM {certificate_issues} ci
                        JOIN {user} u ON u.id = ci.userid
                        JOIN {certificate} cert ON cert.id = ci.certificateid
                        LEFT JOIN {course} c ON c.id = cert.course
                        WHERE ci.userid $insql
                        ORDER BY u.lastname, u.firstname, ci.timecreated DESC";
                $modcerts = $DB->get_records_sql($sql, $params);
            } catch (Exception $e) {
                debugging('download_cohort_certificates: mod_certificate query failed: ' . $e->getMessage());
            }
        }

        $simplecerts = [];
        if ($dbman->table_exists('simplecertificate_issues')) {
            try {
                $sql = "SELECT si.id, si.certificateid, si.userid, si.certificatename, si.code,
                               si.timecreated, si.timedeleted, si.haschange, si.pathnamehash, si.coursename,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename_full
                        FROM {simplecertificate_issues} si
                        JOIN {user} u ON u.id = si.userid
                        LEFT JOIN {course} c ON c.shortname = si.coursename
                        WHERE si.userid $insql AND (si.timedeleted IS NULL OR si.timedeleted = 0)
                        ORDER BY u.lastname, u.firstname, si.timecreated";
                $simplecerts = $DB->get_records_sql($sql, $params);
            } catch (Exception $e) {
                debugging('download_cohort_certificates: simplecertificate query failed: ' . $e->getMessage());
            }
        }

        $beautifulcerts = [];
        if ($dbman->table_exists('certificatebeautiful_issue')) {
            try {
                $sql = "SELECT cbi.id, cbi.userid, cbi.cmid, cbi.certificatebeautifulid, cbi.code,
                               cbi.version, cbi.timecreated,
                               u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                               u.middlename, u.alternatename, u.email,
                               c.fullname as coursename,
                               cb.name as certificatename
                        FROM {certificatebeautiful_issue} cbi
                        JOIN {user} u ON u.id = cbi.userid
                        LEFT JOIN {certificatebeautiful} cb ON cb.id = cbi.certificatebeautifulid
                        LEFT JOIN {course_modules} cm ON cm.id = cbi.cmid
                        LEFT JOIN {course} c ON c.id = cm.course
                        WHERE cbi.userid $insql
                        ORDER BY u.lastname, u.firstname, cbi.timecreated";
                $beautifulcerts = $DB->get_records_sql($sql, $params);
            } catch (Exception $e) {
                debugging('download_cohort_certificates: certificatebeautiful query failed: ' . $e->getMessage());
            }
        }

        if (empty($toolcerts) && empty($customcerts) && empty($modcerts) &&
            empty($simplecerts) && empty($beautifulcerts)) {
            throw new moodle_exception('nocertificatescohort', 'block_download_certificates');
        }

        $zipfilename = 'cohort_' . clean_filename($cohort->name) . '_certificates_' . date('Y-m-d_H-i-s') . '.zip';

        $this->create_and_send_zip(
            $zipfilename, $toolcerts, $customcerts, $modcerts, $simplecerts, $beautifulcerts,
            "cohort '{$cohort->name}'", 'novalidcertificatescohort', true
        );
    }

    // =========================================================================
    // Individual download methods.
    // =========================================================================

    /**
     * Download a single tool_certificate file.
     *
     * @param int $timecreated Time created timestamp
     * @param string $code Certificate code
     */
    public function download_single_certificate($timecreated, $code) {
        global $DB;

        $this->require_manager_or_teacher();

        $dbman = $DB->get_manager();
        $certificate = null;
        if ($dbman->table_exists('tool_certificate_issues')) {
            try {
                $sql = "SELECT tci.*, u.firstname, u.lastname, u.email,
                               tc.name as templatename, c.fullname as coursename
                        FROM {tool_certificate_issues} tci
                        JOIN {user} u ON u.id = tci.userid
                        LEFT JOIN {tool_certificate_templates} tc ON tc.id = tci.templateid
                        LEFT JOIN {course} c ON c.id = tci.courseid
                        WHERE tci.timecreated = :timecreated AND tci.code = :code";
                $certificate = $DB->get_record_sql($sql, [
                    'timecreated' => $timecreated,
                    'code' => $code,
                ]);
            } catch (Exception $e) {
                debugging('download_single_certificate: tool_certificate query failed: ' . $e->getMessage());
            }
        }

        if (!$certificate) {
            throw new moodle_exception('certificatenotfound', 'block_download_certificates');
        }

        $filecontent = $this->retriever->get_certificate_file_content($timecreated, $code);
        if ($filecontent === false) {
            $fileurl = $this->retriever->get_certificate_download_url($timecreated, $code);
            $filecontent = $this->retriever->download_file_via_http($fileurl);
        }

        if ($filecontent === false) {
            throw new moodle_exception('cannotdownloadcertificate', 'block_download_certificates');
        }

        $filename = $this->generate_certificate_filename($certificate);
        $this->send_file_to_user($filecontent, $filename);
    }

    /**
     * Download a single customcert certificate file.
     *
     * @param int $userid User ID
     * @param int $certificateid Certificate ID
     */
    public function download_single_customcert($userid, $certificateid) {
        global $DB;

        $this->require_manager_or_teacher();

        $issue = $DB->get_record('customcert_issues',
            ['userid' => $userid, 'customcertid' => $certificateid],
            '*', IGNORE_MISSING);

        if (!$issue) {
            throw new moodle_exception('certificatenotfound', 'block_download_certificates');
        }

        $sql = "SELECT ci.*, u.firstname, u.lastname, cc.name as certificatename, c.fullname as coursename,
                       ct.id as templateid, ct.name as templatename, ct.contextid
                FROM {customcert_issues} ci
                JOIN {user} u ON u.id = ci.userid
                JOIN {customcert} cc ON cc.id = ci.customcertid
                LEFT JOIN {course} c ON c.id = cc.course
                JOIN {customcert_templates} ct ON ct.id = cc.templateid
                WHERE ci.userid = :userid AND ci.customcertid = :certificateid";

        $certificate = $DB->get_record_sql($sql, ['userid' => $userid, 'certificateid' => $certificateid]);

        if (!$certificate) {
            throw new moodle_exception('certificatenotfound', 'block_download_certificates');
        }

        try {
            $templatedata = new \stdClass();
            $templatedata->id = $certificate->templateid;
            $templatedata->name = $certificate->templatename;
            $templatedata->contextid = $certificate->contextid;

            $template = new \mod_customcert\template($templatedata);
            $filecontent = $template->generate_pdf(false, $userid, true);

            if ($filecontent === false || empty($filecontent)) {
                throw new moodle_exception('cannotdownloadcertificate', 'block_download_certificates');
            }

            $filename = $this->generate_customcert_filename($certificate);
            $this->send_pdf_download($filecontent, $filename);

        } catch (Exception $e) {
            debugging('Error generating customcert PDF: ' . $e->getMessage());
            throw new moodle_exception('cannotdownloadcertificate', 'block_download_certificates');
        }
    }

    /**
     * Download a single mod_certificate file.
     *
     * @param int $userid User ID
     * @param int $certificateid Certificate ID
     */
    public function download_single_mod_certificate($userid, $certificateid) {
        global $DB;

        $this->require_manager_or_teacher();

        $issue = $DB->get_record('certificate_issues',
            ['userid' => $userid, 'certificateid' => $certificateid],
            '*', IGNORE_MISSING);

        if (!$issue) {
            throw new moodle_exception('certificatenotfound', 'block_download_certificates');
        }

        $sql = "SELECT ci.*, u.firstname, u.lastname, cert.name as certificatename, c.fullname as coursename
                FROM {certificate_issues} ci
                JOIN {user} u ON u.id = ci.userid
                JOIN {certificate} cert ON cert.id = ci.certificateid
                LEFT JOIN {course} c ON c.id = cert.course
                WHERE ci.userid = :userid AND ci.certificateid = :certificateid";

        $certificate = $DB->get_record_sql($sql, ['userid' => $userid, 'certificateid' => $certificateid]);

        if (!$certificate) {
            throw new moodle_exception('certificatenotfound', 'block_download_certificates');
        }

        try {
            $fileurl = $this->retriever->get_mod_certificate_download_url(
                $certificateid, $certificate->coursename, $certificate->certificatename, $userid
            );

            if (empty($fileurl)) {
                throw new moodle_exception('cannotdownloadcertificate', 'block_download_certificates',
                    '', 'Unable to generate download URL for mod_certificate.');
            }

            redirect($fileurl);

        } catch (Exception $e) {
            debugging('Error accessing mod_certificate: ' . $e->getMessage());
            throw new moodle_exception('cannotdownloadcertificate', 'block_download_certificates');
        }
    }

    /**
     * Download a single simplecertificate file.
     *
     * @param int $userid User ID
     * @param int $certificateid Certificate ID
     * @param string $code Certificate code
     */
    public function download_single_simplecertificate($userid, $certificateid, $code) {
        global $DB;

        $this->require_manager_or_teacher();

        $issue = $DB->get_record('simplecertificate_issues',
            ['userid' => $userid, 'certificateid' => $certificateid, 'code' => $code],
            '*', IGNORE_MISSING);

        if (!$issue) {
            throw new moodle_exception('certificatenotfound', 'block_download_certificates');
        }

        if (!empty($issue->timedeleted) && $issue->timedeleted != 0) {
            throw new moodle_exception('certificatenotfound', 'block_download_certificates');
        }

        try {
            $fileurl = $this->retriever->get_simplecertificate_download_url($code);

            if (empty($fileurl)) {
                throw new moodle_exception('cannotdownloadcertificate', 'block_download_certificates',
                    '', 'Unable to generate download URL for mod_simplecertificate.');
            }

            redirect($fileurl);

        } catch (Exception $e) {
            debugging('Error accessing mod_simplecertificate: ' . $e->getMessage());
            throw new moodle_exception('cannotdownloadcertificate', 'block_download_certificates');
        }
    }

    /**
     * Download a single certificatebeautiful file.
     *
     * @param int $userid User ID
     * @param int $certificateid Certificate ID
     * @param string $code Certificate code
     */
    public function download_single_certificatebeautiful($userid, $certificateid, $code) {
        global $DB;

        $context = context_system::instance();
        require_capability('block/download_certificates:manage', $context);

        $issue = $DB->get_record('certificatebeautiful_issue',
            ['userid' => $userid, 'certificatebeautifulid' => $certificateid, 'code' => $code],
            '*', IGNORE_MISSING);

        if (!$issue) {
            throw new moodle_exception('certificatenotfound', 'block_download_certificates');
        }

        try {
            $fileurl = $this->retriever->get_certificatebeautiful_individual_download_url($code);

            if (empty($fileurl)) {
                throw new moodle_exception('cannotdownloadcertificate', 'block_download_certificates',
                    '', 'Unable to generate download URL for mod_certificatebeautiful.');
            }

            redirect($fileurl);

        } catch (Exception $e) {
            debugging('Error accessing mod_certificatebeautiful: ' . $e->getMessage());
            throw new moodle_exception('cannotdownloadcertificate', 'block_download_certificates');
        }
    }

    // =========================================================================
    // Méthodes filtrées pour les enseignants.
    // =========================================================================

    /**
     * Récupère les certificats d'un cours filtrés sur une liste d'utilisateurs autorisés.
     *
     * Variante de fetch_certificates_for_course() restreinte à un sous-ensemble
     * d'utilisateurs (périmètre de l'enseignant : groupes ou tous les inscrits).
     *
     * Sécurité : toutes les valeurs sont passées en paramètres SQL.
     *
     * @param int   $courseid       ID du cours
     * @param int[] $visibleuserids IDs d'étudiants visibles par l'enseignant
     * @return array ['tool' => [], 'custom' => [], 'mod' => [], 'simple' => [], 'beautiful' => []]
     */
    public function fetch_certificates_for_course_teacher(int $courseid, array $visibleuserids): array {
        global $DB;

        if (empty($visibleuserids)) {
            return ['tool' => [], 'custom' => [], 'mod' => [], 'simple' => [], 'beautiful' => []];
        }

        $dbman = $DB->get_manager();
        list($userinsql, $userinparams) = $DB->get_in_or_equal($visibleuserids, SQL_PARAMS_NAMED, 'uid');

        $toolcerts = [];
        if ($dbman->table_exists('tool_certificate_issues')) {
            try {
                $toolcerts = $DB->get_records_sql(
                    "SELECT tci.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, tc.name as templatename
                     FROM {tool_certificate_issues} tci
                     JOIN {user} u ON u.id = tci.userid
                     LEFT JOIN {course} c ON c.id = tci.courseid
                     LEFT JOIN {tool_certificate_templates} tc ON tc.id = tci.templateid
                     WHERE tci.courseid = :courseid AND tci.userid {$userinsql}",
                    array_merge(['courseid' => $courseid], $userinparams)
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_course_teacher: tool_certificate query failed: ' . $e->getMessage());
            }
        }

        $customcerts = [];
        if ($dbman->table_exists('customcert_issues')) {
            try {
                list($ccuserinsql, $ccuserinparams) = $DB->get_in_or_equal($visibleuserids, SQL_PARAMS_NAMED, 'ccuid');
                $customcerts = $DB->get_records_sql(
                    "SELECT ci.id, ci.userid, ci.customcertid, ci.code, ci.emailed, ci.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cc.name as certificatename
                     FROM {customcert_issues} ci
                     JOIN {user} u ON u.id = ci.userid
                     JOIN {customcert} cc ON cc.id = ci.customcertid
                     LEFT JOIN {course} c ON c.id = cc.course
                     WHERE cc.course = :courseid AND ci.userid {$ccuserinsql}",
                    array_merge(['courseid' => $courseid], $ccuserinparams)
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_course_teacher: customcert query failed: ' . $e->getMessage());
            }
        }

        $modcerts = [];
        if ($dbman->table_exists('certificate_issues')) {
            try {
                list($mcuserinsql, $mcuserinparams) = $DB->get_in_or_equal($visibleuserids, SQL_PARAMS_NAMED, 'mcuid');
                $modcerts = $DB->get_records_sql(
                    "SELECT ci.id, ci.userid, ci.certificateid, ci.code, ci.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cert.name as certificatename
                     FROM {certificate_issues} ci
                     JOIN {user} u ON u.id = ci.userid
                     JOIN {certificate} cert ON cert.id = ci.certificateid
                     LEFT JOIN {course} c ON c.id = cert.course
                     WHERE cert.course = :courseid AND ci.userid {$mcuserinsql}",
                    array_merge(['courseid' => $courseid], $mcuserinparams)
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_course_teacher: mod_certificate query failed: ' . $e->getMessage());
            }
        }

        $simplecerts = [];
        if ($dbman->table_exists('simplecertificate_issues')) {
            try {
                list($scuserinsql, $scuserinparams) = $DB->get_in_or_equal($visibleuserids, SQL_PARAMS_NAMED, 'scuid');
                $simplecerts = $DB->get_records_sql(
                    "SELECT si.id, si.certificateid, si.userid, si.certificatename, si.code,
                            si.timecreated, si.pathnamehash, si.coursename,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename_full
                     FROM {simplecertificate_issues} si
                     JOIN {user} u ON u.id = si.userid
                     LEFT JOIN {course} c ON c.shortname = si.coursename
                     WHERE c.id = :courseid AND si.userid {$scuserinsql}
                       AND (si.timedeleted IS NULL OR si.timedeleted = 0)",
                    array_merge(['courseid' => $courseid], $scuserinparams)
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_course_teacher: simplecertificate query failed: ' . $e->getMessage());
            }
        }

        $beautifulcerts = [];
        if ($dbman->table_exists('certificatebeautiful_issue')) {
            try {
                list($cbuserinsql, $cbuserinparams) = $DB->get_in_or_equal($visibleuserids, SQL_PARAMS_NAMED, 'cbuid');
                $beautifulcerts = $DB->get_records_sql(
                    "SELECT cbi.id, cbi.userid, cbi.cmid, cbi.certificatebeautifulid, cbi.code,
                            cbi.version, cbi.timecreated,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename, u.email,
                            c.fullname as coursename, cb.name as certificatename
                     FROM {certificatebeautiful_issue} cbi
                     JOIN {user} u ON u.id = cbi.userid
                     LEFT JOIN {certificatebeautiful} cb ON cb.id = cbi.certificatebeautifulid
                     LEFT JOIN {course_modules} cm ON cm.id = cbi.cmid
                     LEFT JOIN {course} c ON c.id = cm.course
                     WHERE c.id = :courseid AND cbi.userid {$cbuserinsql}",
                    array_merge(['courseid' => $courseid], $cbuserinparams)
                );
            } catch (Exception $e) {
                debugging('fetch_certificates_for_course_teacher: certificatebeautiful query failed: ' . $e->getMessage());
            }
        }

        return ['tool' => $toolcerts, 'custom' => $customcerts, 'mod' => $modcerts,
                'simple' => $simplecerts, 'beautiful' => $beautifulcerts];
    }

    /**
     * Télécharge les certificats d'un cours filtrés sur le périmètre de l'enseignant.
     *
     * Vérifie que :
     * 1. L'utilisateur courant enseigne dans ce cours.
     * 2. Les étudiants dans le ZIP sont ceux du périmètre (groupes ou tous inscrits).
     * 3. Si $groupid est fourni, filtre uniquement les membres de ce groupe.
     *
     * @param int      $courseid   ID du cours
     * @param int      $teacherid  ID de l'enseignant (typiquement $USER->id)
     * @param int|null $groupid    ID de groupe spécifique, ou null pour tous les groupes du périmètre
     * @throws moodle_exception Si le cours n'est pas dans le périmètre ou aucun certificat
     */
    public function download_certificates_by_course_for_teacher(int $courseid, int $teacherid,
                                                                 ?int $groupid = null): void {
        global $DB;

        // Vérification : le cours doit être dans le périmètre de l'enseignant.
        require_once(__DIR__ . '/certificate_access.php');
        $access = new block_download_certificates_access();
        $access->require_teacher_in_course($courseid);

        // Récupération du périmètre de visibilité pour ce cours.
        $scope = $access->get_teacher_visible_user_ids($teacherid);
        $visibleuserids = $scope[$courseid] ?? [];

        if (empty($visibleuserids)) {
            throw new moodle_exception('teacher_no_certificates', 'block_download_certificates');
        }

        // Si un groupe spécifique est demandé, vérifier qu'il est dans le périmètre
        // et restreindre la liste aux membres de ce groupe.
        $groupname = '';
        if ($groupid) {
            $group = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid], 'id,name', IGNORE_MISSING);
            if (!$group) {
                throw new moodle_exception('invalidgroup', 'block_download_certificates');
            }

            // Vérifier que l'enseignant a bien accès à ce groupe (intersection).
            $groupmembers = array_keys(groups_get_members($groupid, 'u.id'));
            $visibleuserids = array_values(array_intersect($visibleuserids, $groupmembers));
            $groupname = clean_filename($group->name);

            if (empty($visibleuserids)) {
                throw new moodle_exception('nocertificatesforcourse', 'block_download_certificates');
            }
        }

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            throw new moodle_exception('coursenotfound', 'block_download_certificates');
        }

        // Fetch des certificats filtrés.
        $certs = $this->fetch_certificates_for_course_teacher($courseid, $visibleuserids);

        if (empty($certs['tool']) && empty($certs['custom']) && empty($certs['mod']) &&
            empty($certs['simple']) && empty($certs['beautiful'])) {
            throw new moodle_exception('nocertificatesforcourse', 'block_download_certificates');
        }

        $cleancoursename = clean_filename($course->fullname);
        // Nom du ZIP : inclure le groupe si filtré.
        if ($groupname) {
            $zipfilename = "Certificats_{$cleancoursename}_{$groupname}.zip";
        } else {
            $zipfilename = "Certificats_{$cleancoursename}.zip";
        }

        $this->create_and_send_zip(
            $zipfilename, $certs['tool'], $certs['custom'], $certs['mod'],
            $certs['simple'], $certs['beautiful'],
            "teacher course '{$course->fullname}'", 'novalidcertificatesforcourse', true
        );
    }
}

