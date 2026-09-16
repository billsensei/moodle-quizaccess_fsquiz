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

namespace quizaccess_fsquiz\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use quizaccess_fsquiz\fsquiz_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/fsquiz/rule.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/fsquiz/tests/test_helper_trait.php');

/**
 * Privacy provider tests for quizaccess_fsquiz.
 *
 * @package    quizaccess_fsquiz
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_fsquiz\privacy\provider
 */
final class privacy_test extends \core_privacy\tests\provider_testcase {
    use \quizaccess_fsquiz_test_helper_trait;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_get_contexts_for_userid(): void {
        $quiz = $this->create_test_quiz();
        fsquiz_settings::save((object) ['id' => $quiz->id, 'fsquizenabled' => 1, 'fsquizgraceperiod' => 500]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $quiz->course, 'student');

        $attemptobj = $this->start_and_answer_attempt($quiz, $user);
        $this->insert_autosubmit_log($attemptobj, 'blur');

        $contextlist = provider::get_contexts_for_userid($user->id);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $this->assertContains(
            (int) \context_module::instance($cm->id)->id,
            array_map('intval', $contextlist->get_contextids())
        );
    }

    public function test_export_user_data(): void {
        $quiz = $this->create_test_quiz();
        fsquiz_settings::save((object) ['id' => $quiz->id, 'fsquizenabled' => 1, 'fsquizgraceperiod' => 500]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $quiz->course, 'student');

        $attemptobj = $this->start_and_answer_attempt($quiz, $user);
        $this->insert_autosubmit_log($attemptobj, 'fullscreenexit');

        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = \context_module::instance($cm->id);

        $this->setUser($user);
        $approvedlist = new approved_contextlist($user, 'quizaccess_fsquiz', [$context->id]);
        provider::export_user_data($approvedlist);

        $data = writer::with_context($context)->get_data([get_string('pluginname', 'quizaccess_fsquiz')]);
        $this->assertNotEmpty($data);
        $this->assertCount(1, $data->autosubmits);
        $this->assertEquals('fullscreenexit', $data->autosubmits[0]->reason);
    }

    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $quiz = $this->create_test_quiz();
        fsquiz_settings::save((object) ['id' => $quiz->id, 'fsquizenabled' => 1, 'fsquizgraceperiod' => 500]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $quiz->course, 'student');

        $attemptobj = $this->start_and_answer_attempt($quiz, $user);
        $this->insert_autosubmit_log($attemptobj, 'blur');

        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = \context_module::instance($cm->id);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertEquals(0, $DB->count_records('quizaccess_fsquiz_log', ['quizid' => $quiz->id]));
    }

    public function test_delete_data_for_user(): void {
        global $DB;

        $quiz = $this->create_test_quiz();
        fsquiz_settings::save((object) ['id' => $quiz->id, 'fsquizenabled' => 1, 'fsquizgraceperiod' => 500]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $quiz->course, 'student');

        $attemptobj = $this->start_and_answer_attempt($quiz, $user);
        $this->insert_autosubmit_log($attemptobj, 'blur');

        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = \context_module::instance($cm->id);

        $approvedlist = new approved_contextlist($user, 'quizaccess_fsquiz', [$context->id]);
        provider::delete_data_for_user($approvedlist);

        $this->assertEquals(0, $DB->count_records('quizaccess_fsquiz_log', ['quizid' => $quiz->id, 'userid' => $user->id]));
    }

    public function test_get_and_delete_users_in_context(): void {
        global $DB;

        $quiz = $this->create_test_quiz();
        fsquiz_settings::save((object) ['id' => $quiz->id, 'fsquizenabled' => 1, 'fsquizgraceperiod' => 500]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $quiz->course, 'student');

        $attemptobj = $this->start_and_answer_attempt($quiz, $user);
        $this->insert_autosubmit_log($attemptobj, 'blur');

        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = \context_module::instance($cm->id);

        $userlist = new userlist($context, 'quizaccess_fsquiz');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $user->id, array_map('intval', $userlist->get_userids()));

        $approvedlist = new approved_userlist($context, 'quizaccess_fsquiz', [$user->id]);
        provider::delete_data_for_users($approvedlist);
        $this->assertEquals(0, $DB->count_records('quizaccess_fsquiz_log', ['quizid' => $quiz->id]));
    }
}
