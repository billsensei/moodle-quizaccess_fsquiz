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
 * Backup instructions for the quizaccess_fsquiz subplugin.
 *
 * Only the per-quiz lockdown configuration (quizaccess_fsquiz) is backed up, as part of the
 * quiz activity's own settings. The auto-submit audit log (quizaccess_fsquiz_log) is not: it
 * is a record of what actually happened to specific attempts, not activity configuration, and
 * (like most access-rule plugins' own event logs) is not expected to survive a course
 * backup/restore or duplicate. See README.md.
 *
 * @package    quizaccess_fsquiz
 * @category   backup
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/backup_mod_quiz_access_subplugin.class.php');

/**
 * Backup instructions for the quizaccess_fsquiz subplugin.
 *
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_quizaccess_fsquiz_subplugin extends backup_mod_quiz_access_subplugin {
    /**
     * Stores the fullscreen lockdown settings for a particular quiz.
     *
     * @return backup_subplugin_element
     */
    protected function define_quiz_subplugin_structure() {
        parent::define_quiz_subplugin_structure();
        $quizid = backup::VAR_ACTIVITYID;

        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());

        $subpluginsettings = new backup_nested_element('quizaccess_fsquiz_settings', null, [
            'enabled', 'graceperiod', 'warningtext', 'autosubmittext', 'timemodified',
        ]);

        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($subpluginsettings);

        $subpluginsettings->set_source_table('quizaccess_fsquiz', ['quizid' => $quizid]);

        return $subplugin;
    }
}
