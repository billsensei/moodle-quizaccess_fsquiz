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

use mod_quiz\quiz_settings;
use quizaccess_fsquiz;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/fsquiz/rule.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/fsquiz/tests/test_helper_trait.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Unit and integration tests for the quizaccess_fsquiz plugin.
 *
 * @package    quizaccess_fsquiz
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_fsquiz
 * @covers     \quizaccess_fsquiz\fsquiz_settings
 */
final class rule_test extends \advanced_testcase {
    use \quizaccess_fsquiz_test_helper_trait;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_grace_period_is_clamped_to_allowed_range(): void {
        $this->assertSame(fsquiz_settings::MIN_GRACE_PERIOD, fsquiz_settings::clean_grace_period(0));
        $this->assertSame(fsquiz_settings::MIN_GRACE_PERIOD, fsquiz_settings::clean_grace_period(-500));
        $this->assertSame(fsquiz_settings::MIN_GRACE_PERIOD, fsquiz_settings::clean_grace_period(1));
        $this->assertSame(fsquiz_settings::MAX_GRACE_PERIOD, fsquiz_settings::clean_grace_period(999999));
        $this->assertSame(1500, fsquiz_settings::clean_grace_period(1500));
    }

    public function test_settings_are_not_stored_until_first_enabled(): void {
        $quiz = $this->create_test_quiz();

        $this->assertFalse(fsquiz_settings::get_for_quiz($quiz->id));
        $this->assertFalse(fsquiz_settings::is_enabled($quiz->id));

        // Saving a disabled quiz that has never had settings should not create a row.
        $formdata = (object) ['id' => $quiz->id, 'fsquizenabled' => 0, 'fsquizgraceperiod' => 500];
        fsquiz_settings::save($formdata);
        $this->assertFalse(fsquiz_settings::get_for_quiz($quiz->id));
    }

    public function test_settings_save_update_and_delete(): void {
        $quiz = $this->create_test_quiz();

        $formdata = (object) [
            'id' => $quiz->id,
            'fsquizenabled' => 1,
            'fsquizgraceperiod' => 750,
            'fsquizwarningtext' => 'Do not leave this window.',
            'fsquizautosubmittext' => 'You left the window.',
        ];
        fsquiz_settings::save($formdata);

        $stored = fsquiz_settings::get_for_quiz($quiz->id);
        $this->assertNotFalse($stored);
        $this->assertEquals(1, $stored->enabled);
        $this->assertEquals(750, $stored->graceperiod);
        $this->assertEquals('Do not leave this window.', $stored->warningtext);
        $this->assertTrue(fsquiz_settings::is_enabled($quiz->id));

        // Update in place rather than creating a second row.
        $formdata->fsquizgraceperiod = 2000;
        fsquiz_settings::save($formdata);
        $updated = fsquiz_settings::get_for_quiz($quiz->id);
        $this->assertEquals($stored->id, $updated->id);
        $this->assertEquals(2000, $updated->graceperiod);

        fsquiz_settings::delete($quiz->id);
        $this->assertFalse(fsquiz_settings::get_for_quiz($quiz->id));
    }

    public function test_make_returns_null_when_lockdown_disabled(): void {
        $quiz = $this->create_test_quiz();
        $quizobj = quiz_settings::create($quiz->id);

        $this->assertNull(quizaccess_fsquiz::make($quizobj, time(), false));
    }

    public function test_make_returns_rule_when_lockdown_enabled(): void {
        $quiz = $this->create_test_quiz();
        fsquiz_settings::save((object) ['id' => $quiz->id, 'fsquizenabled' => 1, 'fsquizgraceperiod' => 500]);

        $quizobj = quiz_settings::create($quiz->id);
        $rule = quizaccess_fsquiz::make($quizobj, time(), false);

        $this->assertInstanceOf(quizaccess_fsquiz::class, $rule);
        $this->assertTrue($rule->attempt_must_be_in_popup());
        $this->assertNotEmpty($rule->description());
        $this->assertSame(['fullscreen' => true], array_intersect_key(
            $rule->get_popup_options(),
            ['fullscreen' => true]
        ));
    }

    public function test_preflight_check_requires_acknowledgement(): void {
        $quiz = $this->create_test_quiz();
        fsquiz_settings::save((object) ['id' => $quiz->id, 'fsquizenabled' => 1, 'fsquizgraceperiod' => 500]);
        $quizobj = quiz_settings::create($quiz->id);
        $rule = quizaccess_fsquiz::make($quizobj, time(), false);

        $this->assertTrue($rule->is_preflight_check_required(null));
        // Once an attempt exists, re-acknowledgement must not be required on every page of
        // it - only when starting. Confirmed against a real server: without this, students
        // were stuck in an infinite preflight loop and could never reach the attempt itself.
        $this->assertFalse($rule->is_preflight_check_required(12345));

        $errors = $rule->validate_preflight_check(['fsquizacknowledge' => 0], [], [], null);
        $this->assertArrayHasKey('fsquizacknowledge', $errors);

        $errors = $rule->validate_preflight_check(['fsquizacknowledge' => 1], [], [], null);
        $this->assertArrayNotHasKey('fsquizacknowledge', $errors);
    }

    /**
     * The core guarantee this plugin makes: force-submitting a lockdown quiz attempt goes
     * through exactly the same mod_quiz code path (and therefore produces exactly the same
     * grade) as a normal "Submit all and finish", using whatever answers were entered before
     * the focus was lost.
     */
    public function test_forced_submit_grades_whatever_was_answered(): void {
        $quiz = $this->create_test_quiz();
        fsquiz_settings::save((object) ['id' => $quiz->id, 'fsquizenabled' => 1, 'fsquizgraceperiod' => 500]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $quiz->course, 'student');

        $attemptobj = $this->start_and_answer_attempt($quiz, $user);
        $this->assertEquals(\mod_quiz\quiz_attempt::IN_PROGRESS, $attemptobj->get_state());

        // Simulate lockdown.js's grace-period timeout firing: log the reason, then force
        // the same submit+grade calls that #responseform's POST to processattempt.php makes.
        $log = $this->insert_autosubmit_log($attemptobj, 'visibilitychange');
        $finished = $this->finish_attempt($attemptobj);

        $this->assertEquals(\mod_quiz\quiz_attempt::FINISHED, $finished->get_state());
        // Both questions were answered correctly, so the attempt should be full marks.
        $this->assertEquals(2, $finished->get_sum_marks());

        $gradeitem = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $quiz->id]);
        $grade = new \grade_grade(['itemid' => $gradeitem->id, 'userid' => $user->id]);
        $this->assertEquals(100.0, round((float) $grade->finalgrade, 2));

        $this->assertEquals('visibilitychange', $log->reason);
        $this->assertEquals($finished->get_attemptid(), $log->attemptid);
        $this->assertEquals($user->id, $log->userid);
    }

    public function test_autosubmit_log_records_partial_answers(): void {
        global $DB;

        $quiz = $this->create_test_quiz();
        fsquiz_settings::save((object) ['id' => $quiz->id, 'fsquizenabled' => 1, 'fsquizgraceperiod' => 500]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $quiz->course, 'student');

        $this->setUser($user);
        $starttime = time();
        $quizobj = quiz_settings::create($quiz->id, $user->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $attempt = quiz_create_attempt($quizobj, 1, false, $starttime, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $starttime);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        // Only answer the first question - simulates leaving mid-way through.
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($starttime, false, [1 => ['answer' => 'frog']]);
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);

        $this->insert_autosubmit_log($attemptobj, 'fullscreenexit');
        $finished = $this->finish_attempt($attemptobj);

        $this->assertEquals(\mod_quiz\quiz_attempt::FINISHED, $finished->get_state());
        // Only one of two marks: the ungraded/blank question is marked wrong, not excluded.
        $this->assertEquals(1, $finished->get_sum_marks());

        $count = $DB->count_records('quizaccess_fsquiz_log', ['attemptid' => $attempt->id]);
        $this->assertEquals(1, $count);
    }

    public function test_delete_settings_removes_logs_too(): void {
        global $DB;

        $quiz = $this->create_test_quiz();
        fsquiz_settings::save((object) ['id' => $quiz->id, 'fsquizenabled' => 1, 'fsquizgraceperiod' => 500]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $quiz->course, 'student');

        $attemptobj = $this->start_and_answer_attempt($quiz, $user);
        $this->insert_autosubmit_log($attemptobj, 'blur');
        $this->assertEquals(1, $DB->count_records('quizaccess_fsquiz_log', ['quizid' => $quiz->id]));

        fsquiz_settings::delete($quiz->id);

        $this->assertEquals(0, $DB->count_records('quizaccess_fsquiz', ['quizid' => $quiz->id]));
        $this->assertEquals(0, $DB->count_records('quizaccess_fsquiz_log', ['quizid' => $quiz->id]));
    }
}
