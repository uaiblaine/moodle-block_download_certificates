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
 * Block download_certificates definition.
 *
 * @package   block_download_certificates
 * @copyright 2015 Manieer Chhettri
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_download_certificates\local\zip_tasks;
use block_download_certificates\output\block_content;

/**
 * Block download_certificates class.
 *
 * @package   block_download_certificates
 * @copyright 2015 Manieer Chhettri
 * @copyright 2025 E-learning Touch <contact@elearningtouch.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_download_certificates extends block_base {
    /**
     * Initialize block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_download_certificates');
    }

    /**
     * Defines where the block can be used.
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'admin' => true,
            'site-index' => true,
            'course-view' => true,
            'mod' => false,
            'my' => true,
            'user-profile' => true,
        ];
    }

    /**
     * Controls whether multiple instances of the block are allowed.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Controls whether the block has a settings.php file.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }

    /**
     * The block's content: one template over the context {@see block_content} builds for this page.
     *
     * Empty, which hides the block, for a guest, and on a profile whose user the viewer's scope does
     * not reach.
     *
     * @return stdClass
     */
    public function get_content() {
        global $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        try {
            // The page's renderer, not $OUTPUT: a block may be built before the page has set it up.
            $output = $this->page->get_renderer('core');
            $data = block_content::for_page($this->page, (int) $USER->id)->export_for_template($output);
        } catch (\Throwable $e) {
            debugging('Download certificates block: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $data = ['error' => true, 'hascontent' => true, 'haszip' => false];
        }
        if (!$data['hascontent']) {
            return $this->content;
        }

        if ($data['haszip']) {
            // The module queues a ZIP request from the threshold on and asks the confirmation of a
            // form that carries one.
            $this->page->requires->js_call_amd('block_download_certificates/async_download', 'init', [[
                'userid' => (int) $USER->id,
                'pendingtasks' => zip_tasks::get_pending((int) $USER->id),
            ]]);
        }
        $this->content->text = $this->page->get_renderer('core')->render_from_template(
            'block_download_certificates/block_content',
            $data
        );
        return $this->content;
    }
}
