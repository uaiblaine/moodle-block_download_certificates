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
 * Event: a background ZIP download was requested.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_download_certificates\event;

use block_download_certificates\local\zip_tasks;

/**
 * A user queued a ZIP of certificates; the object is the task row, the context the requester's
 * user context, and the related user the owner of a `user` request.
 *
 * - `other['type']`: the request type, one of {@see \block_download_certificates\local\request::TYPES}.
 * - `other['courseid']`, `['groupid']`, `['cohortid']`: the course, group and cohort of the request,
 *   when it names one.
 * - `other['startdate']`, `['enddate']`: the `Y-m-d` days of a `range` request.
 *
 * @package   block_download_certificates
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zip_requested extends \core\event\base {
    /**
     * Set the basic event data.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = zip_tasks::TABLE;
    }

    /**
     * The localised name of the event.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_zip_requested', 'block_download_certificates');
    }

    /**
     * A description of what happened, for the logs.
     *
     * @return string
     */
    public function get_description() {
        $type = s($this->other['type']);
        $text = "The user with id '{$this->userid}' requested a ZIP of certificates of type '{$type}'";
        // Core sets relateduserid to the owner of a user context when the creator leaves the key out,
        // so it names a target only for a request about one user.
        if ($this->other['type'] === 'user' && !empty($this->relateduserid)) {
            $text .= " for the user with id '{$this->relateduserid}'";
        }
        foreach (['courseid' => 'course', 'cohortid' => 'cohort'] as $key => $label) {
            if (!empty($this->other[$key])) {
                $text .= " for the {$label} with id '" . (int) $this->other[$key] . "'";
            }
        }
        return $text . " as download task '{$this->objectid}'.";
    }

    /**
     * Refuse an event without its object or type.
     *
     * @throws \coding_exception When `objectid` or `other['type']` is missing.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->objectid)) {
            throw new \coding_exception('The \'objectid\' must be set.');
        }
        if (!isset($this->other['type'])) {
            throw new \coding_exception('The \'type\' value must be set in other.');
        }
    }

    /**
     * The task row is not backed up, so restore maps nothing to it.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => zip_tasks::TABLE, 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * `other` holds course, group and cohort ids that restore does not map.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
