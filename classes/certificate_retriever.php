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
 * Certificate file retrieval class for block_download_certificates plugin.
 *
 * Handles retrieving PDF content for all certificate types, prioritizing
 * direct file storage access over HTTP download for performance.
 *
 * @package   block_download_certificates
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Handles retrieving PDF file content for all certificate types.
 *
 * Priority order for each type:
 * 1. Direct file storage via pathnamehash (~1ms)
 * 2. File storage search by component/filearea (~10ms)
 * 3. HTTP download as last resort (~200-500ms)
 */
class block_download_certificates_retriever {
    // URL Generation methods.

    /**
     * Generate download URL for a tool_certificate certificate.
     *
     * @param int $timecreated Time created timestamp
     * @param string $code Certificate code
     * @return string Download URL
     */
    public function get_certificate_download_url($timecreated, $code) {
        global $CFG;
        return $CFG->wwwroot . '/pluginfile.php/1/tool_certificate/issues/' . $timecreated . '/' . $code . '.pdf';
    }

    /**
     * Generate download URL for a mod_simplecertificate certificate.
     *
     * @param string $code Certificate code
     * @return string Download URL
     */
    public function get_simplecertificate_download_url($code) {
        global $CFG;
        return $CFG->wwwroot . '/mod/simplecertificate/wmsendfile.php?code=' . urlencode($code);
    }

    /**
     * Generate download URL for a mod_certificatebeautiful certificate.
     *
     * @param string $code Certificate code
     * @return string Download URL
     */
    public function get_certificatebeautiful_download_url($code) {
        global $CFG;
        return $CFG->wwwroot . '/mod/certificatebeautiful/view-pdf.php?code=' . urlencode($code);
    }

    /**
     * Generate download URL for mod_certificatebeautiful (individual download with action=download).
     *
     * @param string $code Certificate code
     * @return string Download URL
     */
    public function get_certificatebeautiful_individual_download_url($code) {
        global $CFG;
        return $CFG->wwwroot . '/mod/certificatebeautiful/view-pdf.php?code=' . urlencode($code) . '&action=download';
    }

    /**
     * Generate download URL for a mod_certificate certificate.
     *
     * @param int $certificateid Certificate ID (from certificate table)
     * @param string $coursename Course name
     * @param string $certificatename Certificate name
     * @param int $userid User ID (optional, for more specific URL)
     * @return string Download URL
     */
    public function get_mod_certificate_download_url($certificateid, $coursename, $certificatename, $userid = null) {
        global $CFG, $DB;

        $certificate = $DB->get_record('certificate', ['id' => $certificateid], 'course');
        if (!$certificate) {
            return '';
        }

        $course = $DB->get_record('course', ['id' => $certificate->course], 'shortname');
        $courseshortname = $course ? $course->shortname : ($coursename ?: 'Course');

        $cm = get_coursemodule_from_instance('certificate', $certificateid, $certificate->course);
        if (!$cm) {
            return '';
        }

        $modulecontext = context_module::instance($cm->id);

        $issueitemid = $certificateid;
        if ($userid) {
            $issue = $DB->get_record(
                'certificate_issues',
                ['userid' => $userid, 'certificateid' => $certificateid],
                'id'
            );
            if ($issue) {
                $issueitemid = $issue->id;
            }
        }

        $cleancertname = preg_replace('/[^a-zA-Z0-9_-]/', '_', $certificatename ?: 'Certificate');
        $filename = $courseshortname . '_' . $cleancertname . '.pdf';

        return $CFG->wwwroot . '/pluginfile.php/' . $modulecontext->id . '/mod_certificate/issue/' .
                $issueitemid . '/' . rawurlencode($filename);
    }

    // File content retrieval methods (direct file storage - fast).

    /**
     * Get tool_certificate file content from Moodle file storage.
     *
     * @param int $timecreated Time created timestamp
     * @param string $code Certificate code
     * @return string|false File content or false on failure
     */
    public function get_certificate_file_content($timecreated, $code) {
        try {
            $fs = get_file_storage();
            $context = context_system::instance();
            $filename = $code . '.pdf';

            // Method 1: Direct file lookup with timecreated as itemid.
            $file = $fs->get_file($context->id, 'tool_certificate', 'issues', $timecreated, '/', $filename);
            if ($file && !$file->is_directory()) {
                return $file->get_content();
            }

            // Method 2: Try with different itemid variations.
            $itemids = [$timecreated, 0, crc32($code), abs(crc32($code))];
            foreach ($itemids as $itemid) {
                $file = $fs->get_file($context->id, 'tool_certificate', 'issues', $itemid, '/', $filename);
                if ($file && !$file->is_directory()) {
                    return $file->get_content();
                }
            }

            // Method 3: Search in area files with timecreated.
            $files = $fs->get_area_files(
                $context->id,
                'tool_certificate',
                'issues',
                $timecreated,
                'timemodified DESC',
                false
            );
            foreach ($files as $file) {
                if (
                    $file->get_filename() === $filename ||
                    strpos($file->get_filename(), $code) !== false
                ) {
                    return $file->get_content();
                }
            }

            // Method 4: Search in area files with itemid 0.
            $files = $fs->get_area_files($context->id, 'tool_certificate', 'issues', 0, 'timemodified DESC', false);
            foreach ($files as $file) {
                if (
                    $file->get_filename() === $filename ||
                    strpos($file->get_filename(), $code) !== false
                ) {
                    return $file->get_content();
                }
            }

            // Method 5: Try to find certificate record and use its ID.
            global $DB;
            $certificate = $DB->get_record('tool_certificate_issues', [
                'timecreated' => $timecreated,
                'code' => $code,
            ]);
            if ($certificate) {
                $file = $fs->get_file($context->id, 'tool_certificate', 'issues', $certificate->id, '/', $filename);
                if ($file && !$file->is_directory()) {
                    return $file->get_content();
                }
            }

            return false;
        } catch (Exception $e) {
            debugging('Error getting certificate file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get mod_certificate file content from Moodle file storage.
     *
     * @param int $certificateid Certificate ID
     * @param string $coursename Course name
     * @param string $certificatename Certificate name
     * @param int $userid User ID (optional)
     * @return string|false File content or false on failure
     */
    public function get_mod_certificate_file_content($certificateid, $coursename, $certificatename, $userid = null) {
        global $DB;

        try {
            $certificate = $DB->get_record('certificate', ['id' => $certificateid]);
            if (!$certificate) {
                return false;
            }

            $course = $DB->get_record('course', ['id' => $certificate->course], 'shortname');
            $courseshortname = $course ? $course->shortname : ($coursename ?: 'Course');

            $cm = get_coursemodule_from_instance('certificate', $certificateid, $certificate->course);
            if (!$cm) {
                return false;
            }

            $modulecontext = context_module::instance($cm->id);
            $fs = get_file_storage();

            $issueitemid = $certificateid;
            if ($userid) {
                $issue = $DB->get_record(
                    'certificate_issues',
                    ['userid' => $userid, 'certificateid' => $certificateid],
                    'id'
                );
                if ($issue) {
                    $issueitemid = $issue->id;
                }
            }

            $cleancoursename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $courseshortname);
            $cleancertname = preg_replace('/[^a-zA-Z0-9_-]/', '_', $certificatename ?: 'Certificate');
            $filename = $cleancoursename . '_' . $cleancertname . '.pdf';

            // Try direct file lookup.
            $file = $fs->get_file($modulecontext->id, 'mod_certificate', 'issue', $issueitemid, '/', $filename);
            if ($file && !$file->is_directory()) {
                return $file->get_content();
            }

            // Try different filename patterns.
            $possiblenames = [
                $filename,
                $issueitemid . '.pdf',
                $certificateid . '.pdf',
                'certificate.pdf',
                'certificate_' . $issueitemid . '.pdf',
                'certificate_' . $certificateid . '.pdf',
            ];

            foreach ($possiblenames as $testname) {
                $file = $fs->get_file($modulecontext->id, 'mod_certificate', 'issue', $issueitemid, '/', $testname);
                if ($file && !$file->is_directory()) {
                    return $file->get_content();
                }
            }

            // Search all files in area with issue itemid.
            $files = $fs->get_area_files(
                $modulecontext->id,
                'mod_certificate',
                'issue',
                $issueitemid,
                'timemodified DESC',
                false
            );
            foreach ($files as $file) {
                if (strpos($file->get_filename(), '.pdf') !== false) {
                    return $file->get_content();
                }
            }

            // Fallback: try with certificate ID as itemid.
            if ($issueitemid !== $certificateid) {
                $files = $fs->get_area_files(
                    $modulecontext->id,
                    'mod_certificate',
                    'issue',
                    $certificateid,
                    'timemodified DESC',
                    false
                );
                foreach ($files as $file) {
                    if (strpos($file->get_filename(), '.pdf') !== false) {
                        return $file->get_content();
                    }
                }
            }

            return false;
        } catch (Exception $e) {
            debugging('Error getting mod_certificate file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get simplecertificate file content from Moodle file storage.
     *
     * @param string $code Certificate code
     * @return string|false File content or false on failure
     */
    public function get_simplecertificate_file_content($code) {
        global $DB;

        try {
            $issue = $DB->get_record('simplecertificate_issues', ['code' => $code]);
            if ($issue && !empty($issue->pathnamehash)) {
                $fs = get_file_storage();
                $file = $fs->get_file_by_hash($issue->pathnamehash);

                if ($file && !$file->is_directory()) {
                    return $file->get_content();
                }
            }

            // If direct file access fails, return false to fall back to URL method.
            return false;
        } catch (Exception $e) {
            debugging('Error getting simplecertificate file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get certificatebeautiful file content from storage.
     *
     * @param string $code Certificate code
     * @return string|false File content or false on failure
     */
    public function get_certificatebeautiful_file_content($code) {
        global $DB;

        try {
            $issue = $DB->get_record('certificatebeautiful_issue', ['code' => $code]);
            if (!$issue) {
                return false;
            }

            $fs = get_file_storage();

            // Method 1: Try pathnamehash if available.
            if (!empty($issue->pathnamehash)) {
                $file = $fs->get_file_by_hash($issue->pathnamehash);
                if ($file && !$file->is_directory()) {
                    return $file->get_content();
                }
            }

            // Method 2: Try via course module context.
            if ($issue->cmid) {
                $cm = get_coursemodule_from_id('certificatebeautiful', $issue->cmid);
                if ($cm) {
                    $context = context_module::instance($cm->id);

                    $fileareas = ['issued', 'certificate', 'certificates', 'pdf', 'issue'];
                    $filenames = [
                        $code . '.pdf',
                        $issue->id . '.pdf',
                        'certificate_' . $code . '.pdf',
                        'cert_' . $issue->userid . '_' . $issue->id . '.pdf',
                    ];

                    foreach ($fileareas as $filearea) {
                        foreach ($filenames as $filename) {
                            $file = $fs->get_file(
                                $context->id,
                                'mod_certificatebeautiful',
                                $filearea,
                                $issue->id,
                                '/',
                                $filename
                            );
                            if ($file && !$file->is_directory()) {
                                return $file->get_content();
                            }

                            $file = $fs->get_file(
                                $context->id,
                                'mod_certificatebeautiful',
                                $filearea,
                                0,
                                '/',
                                $filename
                            );
                            if ($file && !$file->is_directory()) {
                                return $file->get_content();
                            }

                            $file = $fs->get_file(
                                $context->id,
                                'mod_certificatebeautiful',
                                $filearea,
                                $issue->userid,
                                '/',
                                $filename
                            );
                            if ($file && !$file->is_directory()) {
                                return $file->get_content();
                            }
                        }
                    }
                }
            }

            // Method 3: Try via course context.
            if (!empty($issue->courseid)) {
                $coursecontext = context_course::instance($issue->courseid);
                $fileareas = ['certificates', 'certificate', 'issued'];

                foreach ($fileareas as $filearea) {
                    $file = $fs->get_file(
                        $coursecontext->id,
                        'mod_certificatebeautiful',
                        $filearea,
                        $issue->id,
                        '/',
                        $code . '.pdf'
                    );
                    if ($file && !$file->is_directory()) {
                        return $file->get_content();
                    }
                }
            }

            return false;
        } catch (Exception $e) {
            debugging('Error getting certificatebeautiful file: ' . $e->getMessage());
            return false;
        }
    }

    // Combined retrieval methods (file storage + HTTP fallback).

    /**
     * Get mod_certificate content: try file storage first, then HTTP.
     *
     * @param int $certificateid Certificate ID
     * @param string $coursename Course name
     * @param string $certificatename Certificate name
     * @param int $userid User ID
     * @return string|false File content or false on failure
     */
    public function get_mod_certificate_content($certificateid, $coursename, $certificatename, $userid) {
        try {
            $content = $this->get_mod_certificate_file_content($certificateid, $coursename, $certificatename, $userid);
            if ($content !== false) {
                return $content;
            }

            $fileurl = $this->get_mod_certificate_download_url($certificateid, $coursename, $certificatename, $userid);
            if (empty($fileurl)) {
                return false;
            }

            $content = $this->download_file_via_http($fileurl);
            if ($content !== false && strlen($content) > 100) {
                return $content;
            }

            return false;
        } catch (Exception $e) {
            debugging('Error getting mod_certificate content: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get simplecertificate content: try file storage first, then HTTP.
     *
     * @param string $code Certificate code
     * @return string|false File content or false on failure
     */
    public function get_simplecertificate_content($code) {
        try {
            $content = $this->get_simplecertificate_file_content($code);
            if ($content !== false) {
                return $content;
            }

            $fileurl = $this->get_simplecertificate_download_url($code);
            if (empty($fileurl)) {
                return false;
            }

            $content = $this->download_file_via_http($fileurl);
            if ($content !== false && strlen($content) > 100) {
                return $content;
            }

            return false;
        } catch (Exception $e) {
            debugging('Error getting simplecertificate content: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get certificatebeautiful content: try file storage first, then HTTP.
     *
     * @param string $code Certificate code
     * @return string|false File content or false on failure
     */
    public function get_certificatebeautiful_content($code) {
        try {
            $content = $this->get_certificatebeautiful_file_content($code);
            if ($content !== false) {
                return $content;
            }

            $fileurl = $this->get_certificatebeautiful_download_url($code);
            if (empty($fileurl)) {
                return false;
            }

            $content = $this->download_file_via_http($fileurl);
            if ($content !== false && strlen($content) > 100) {
                return $content;
            }

            return false;
        } catch (Exception $e) {
            debugging('Error getting certificatebeautiful content: ' . $e->getMessage());
            return false;
        }
    }

    // HTTP download methods (fallback - slower).

    /**
     * Download file via HTTP request.
     *
     * @param string $url File URL
     * @return string|false File content or false on failure
     */
    public function download_file_via_http($url) {
        global $CFG;

        try {
            $curl = new curl();

            $curl->setopt([
                'CURLOPT_TIMEOUT' => 60,
                'CURLOPT_CONNECTTIMEOUT' => 10,
                'CURLOPT_FOLLOWLOCATION' => true,
                'CURLOPT_MAXREDIRS' => 5,
                'CURLOPT_SSL_VERIFYPEER' => false,
                'CURLOPT_SSL_VERIFYHOST' => false,
                'CURLOPT_USERAGENT' => 'Moodle Certificate Downloader/1.0',
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_FAILONERROR' => false,
                'CURLOPT_COOKIEJAR' => '',
                'CURLOPT_COOKIEFILE' => '',
            ]);

            // Add authentication for internal URLs.
            if (strpos($url, $CFG->wwwroot) === 0) {
                $sessioncookie = $this->get_session_cookie();
                $headers = [
                    'Accept: application/pdf,*/*',
                    'Cache-Control: no-cache',
                    'Pragma: no-cache',
                ];

                if ($sessioncookie) {
                    $curl->setopt(['CURLOPT_COOKIE' => $sessioncookie]);
                }

                $curl->setopt(['CURLOPT_HTTPHEADER' => $headers]);

                if (isset($_SERVER['HTTP_HOST'])) {
                    $curl->setopt(['CURLOPT_REFERER' => $CFG->wwwroot]);
                }
            }

            $content = $curl->get($url);
            $info = $curl->get_info();
            $errno = $curl->get_errno();

            if ($errno !== 0) {
                debugging('cURL error: ' . $curl->error . ' (code: ' . $errno . ')');
                return false;
            }

            $httpcode = intval($info['http_code']);
            if ($httpcode < 200 || $httpcode >= 300) {
                debugging('HTTP error ' . $httpcode . ' for URL: ' . $url);

                if ($httpcode === 403 || $httpcode === 401) {
                    return $this->try_alternative_download($url);
                }

                return false;
            }

            if (strlen($content) < 100) {
                debugging('Downloaded content too small: ' . strlen($content) . ' bytes');
                return false;
            }

            return $content;
        } catch (Exception $e) {
            debugging('HTTP download error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Try alternative download methods when authentication fails.
     *
     * @param string $url File URL
     * @return string|false File content or false on failure
     */
    public function try_alternative_download($url) {
        global $CFG;

        // Parse URL to extract certificate parameters.
        if (preg_match('#/pluginfile\.php/(\d+)/([^/]+)/([^/]+)/(\d+)/([^/]+)\.pdf#', $url, $matches)) {
            $timecreated = $matches[4];
            $code = str_replace('.pdf', '', $matches[5]);

            $content = $this->get_certificate_file_content($timecreated, $code);
            if ($content !== false) {
                return $content;
            }
        }

        // Try with file_get_contents as last resort.
        if (strpos($url, 'http') !== 0 || strpos($url, $CFG->wwwroot) === 0) {
            $blockpath = str_replace($CFG->wwwroot, $CFG->dirroot, $url);
            if (file_exists($blockpath)) {
                return file_get_contents($blockpath);
            }
        }

        return false;
    }

    /**
     * Get session cookie for authentication.
     *
     * @return string|false Session cookie or false
     */
    public function get_session_cookie() {
        global $CFG;

        $sessionname = 'MoodleSession' . $CFG->sessioncookie;
        $sessionid = session_id();

        if (!empty($sessionid)) {
            return $sessionname . '=' . $sessionid;
        }

        return false;
    }
}
