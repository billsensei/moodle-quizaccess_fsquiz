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

namespace quizaccess_fsquiz;

/**
 * Simple DML wrapper around the {quizaccess_fsquiz} per-quiz settings table.
 *
 * @package    quizaccess_fsquiz
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fsquiz_settings {
    /** @var int Minimum allowed grace period, in milliseconds. */
    const MIN_GRACE_PERIOD = 100;

    /** @var int Maximum allowed grace period, in milliseconds. */
    const MAX_GRACE_PERIOD = 10000;

    /** @var int Fallback grace period used when none is configured, in milliseconds. */
    const DEFAULT_GRACE_PERIOD = 500;

    /**
     * Get the fullscreen lockdown settings for a quiz.
     *
     * @param int $quizid
     * @return \stdClass|false the settings record, or false if lockdown has never been configured.
     */
    public static function get_for_quiz(int $quizid) {
        global $DB;
        return $DB->get_record('quizaccess_fsquiz', ['quizid' => $quizid]);
    }

    /**
     * Whether lockdown enforcement is switched on for this quiz.
     *
     * @param int $quizid
     * @return bool
     */
    public static function is_enabled(int $quizid): bool {
        $settings = self::get_for_quiz($quizid);
        return !empty($settings) && !empty($settings->enabled);
    }

    /**
     * Save the settings submitted via the quiz settings form.
     *
     * Called from {@see \quizaccess_fsquiz}::save_settings(), which is called from
     * quiz_after_add_or_update() in mod/quiz/lib.php whenever a quiz is added or updated.
     *
     * @param \stdClass $quiz the data from the quiz form, including $quiz->id.
     */
    public static function save(\stdClass $quiz): void {
        global $DB;

        $enabled = !empty($quiz->fsquizenabled) ? 1 : 0;

        $existing = self::get_for_quiz($quiz->id);

        if (!$enabled && !$existing) {
            // Never configured and still disabled: nothing to store.
            return;
        }

        $record = new \stdClass();
        $record->quizid = $quiz->id;
        $record->enabled = $enabled;
        $record->graceperiod = self::clean_grace_period($quiz->fsquizgraceperiod ?? self::DEFAULT_GRACE_PERIOD);
        $record->warningtext = clean_param($quiz->fsquizwarningtext ?? '', PARAM_TEXT);
        $record->autosubmittext = clean_param($quiz->fsquizautosubmittext ?? '', PARAM_TEXT);
        $record->timemodified = time();

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('quizaccess_fsquiz', $record);
        } else {
            $DB->insert_record('quizaccess_fsquiz', $record);
        }
    }

    /**
     * Delete the settings (and log entries) for a quiz that is being deleted.
     *
     * @param int $quizid
     */
    public static function delete(int $quizid): void {
        global $DB;
        $DB->delete_records('quizaccess_fsquiz', ['quizid' => $quizid]);
        $DB->delete_records('quizaccess_fsquiz_log', ['quizid' => $quizid]);
    }

    /**
     * Clamp a submitted grace period into the allowed range.
     *
     * @param mixed $value
     * @return int
     */
    public static function clean_grace_period($value): int {
        $value = (int) $value;
        if ($value <= 0) {
            return self::DEFAULT_GRACE_PERIOD;
        }
        return max(self::MIN_GRACE_PERIOD, min(self::MAX_GRACE_PERIOD, $value));
    }
}
