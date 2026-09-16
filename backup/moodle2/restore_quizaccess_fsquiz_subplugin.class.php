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
 * Restore instructions for the quizaccess_fsquiz subplugin.
 *
 * @package    quizaccess_fsquiz
 * @category   backup
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/restore_mod_quiz_access_subplugin.class.php');

/**
 * Restore instructions for the quizaccess_fsquiz subplugin.
 *
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_quizaccess_fsquiz_subplugin extends restore_mod_quiz_access_subplugin {
    /**
     * Provides path structure required to restore the fullscreen lockdown settings.
     *
     * @return array
     */
    protected function define_quiz_subplugin_structure() {
        $paths = [];

        $path = $this->get_pathfor('/quizaccess_fsquiz_settings');
        $paths[] = new restore_path_element('quizaccess_fsquiz_settings', $path);

        return $paths;
    }

    /**
     * Process the restored data for the quizaccess_fsquiz table.
     *
     * @param array $data
     */
    public function process_quizaccess_fsquiz_settings($data) {
        global $DB;

        $data = (object) $data;
        $data->quizid = $this->get_new_parentid('quiz');

        unset($data->id);
        $data->timemodified = time();

        $DB->insert_record('quizaccess_fsquiz', $data);
    }
}
