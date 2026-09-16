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

namespace quizaccess_fsquiz\event;

/**
 * Event triggered when a quiz attempt is automatically submitted because the student
 * left the locked-down fullscreen quiz window.
 *
 * @package    quizaccess_fsquiz
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_autosubmitted extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'quiz_attempts';
    }

    /**
     * Validate that required event data has been set.
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!array_key_exists('reason', $this->other)) {
            throw new \coding_exception('The \'reason\' value must be set in other.');
        }
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventattemptautosubmitted', 'quizaccess_fsquiz');
    }

    /**
     * Return the non-localised event description, for the logs.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' had their quiz attempt with id '$this->objectid' " .
            "automatically submitted because focus was lost (reason: '{$this->other['reason']}').";
    }

    /**
     * Return the URL this event relates to: the attempt's review page.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/quiz/review.php', ['attempt' => $this->objectid]);
    }

    /**
     * Used for restore. objectid is a quiz_attempts id.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'quiz_attempts', 'restore' => 'quiz_attempt'];
    }

    /**
     * Used for restore. The 'other' data has nothing that needs to be mapped.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
