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
 * Global admin settings for quizaccess_fsquiz.
 *
 * Per-quiz enable/grace-period/wording live on the quiz's own settings form
 * (see rule.php::add_settings_form_fields); this is just the default that is
 * pre-filled when a teacher first enables lockdown on a quiz.
 *
 * @package    quizaccess_fsquiz
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $ADMIN;

if ($hassiteconfig) {
    $settings->add(new admin_setting_configtext(
        'quizaccess_fsquiz/defaultgraceperiod',
        get_string('defaultgraceperiod', 'quizaccess_fsquiz'),
        get_string('defaultgraceperiod_desc', 'quizaccess_fsquiz'),
        \quizaccess_fsquiz\fsquiz_settings::DEFAULT_GRACE_PERIOD,
        PARAM_INT
    ));
}
