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

use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/fsquiz/rule.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Shared helpers for quizaccess_fsquiz tests: build a course + quiz with a couple of
 * short-answer questions, and walk a test user through starting, answering, and finishing
 * an attempt exactly the way processattempt.php would (via quiz_attempt's own
 * process_submitted_actions()/process_submit()/process_grade_submission() methods), so
 * tests exercise the real mod_quiz grading pipeline rather than a re-implementation of it.
 *
 * @package    quizaccess_fsquiz
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait quizaccess_fsquiz_test_helper_trait {
    /**
     * Create a course and a quiz with two short-answer questions.
     *
     * @param array $quizoptions extra options passed to the quiz generator (e.g. fsquiz settings).
     * @return \stdClass the quiz record.
     */
    protected function create_test_quiz(array $quizoptions = []): \stdClass {
        $course = $this->getDataGenerator()->create_course();

        /** @var \mod_quiz_generator $quizgenerator */
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(array_merge([
            'course' => $course->id,
            'grade' => 100.0,
        ], $quizoptions));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($saq->id, $quiz);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);

        return $quiz;
    }

    /**
     * Start an attempt for the given quiz/user and answer both questions, without finishing.
     *
     * @param \stdClass $quiz
     * @param \stdClass $user
     * @return quiz_attempt
     */
    protected function start_and_answer_attempt(\stdClass $quiz, \stdClass $user): quiz_attempt {
        $this->setUser($user);

        $starttime = time();
        $quizobj = quiz_settings::create($quiz->id, $user->id);

        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $attempt = quiz_create_attempt($quizobj, 1, false, $starttime, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $starttime);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($starttime, false, [
            1 => ['answer' => 'frog'],
            2 => ['answer' => '3.14'],
        ]);

        return quiz_attempt::create($attempt->id);
    }

    /**
     * Finish an in-progress attempt exactly the way a real (or forced) submission would:
     * this is the same pair of calls that quiz_attempt::process_attempt() makes internally
     * when processattempt.php handles finishattempt=1, whether that came from the student
     * clicking "Submit all and finish", the countdown timer expiring, or our own lockdown.js
     * force-submitting #responseform after a focus-loss grace period.
     *
     * @param quiz_attempt $attemptobj
     * @return quiz_attempt the re-loaded, now-finished attempt.
     */
    protected function finish_attempt(quiz_attempt $attemptobj): quiz_attempt {
        $timenow = time();
        $attemptobj->process_submit($timenow, false);
        $attemptobj->process_grade_submission($timenow);
        $this->setUser();

        return quiz_attempt::create($attemptobj->get_attemptid());
    }

    /**
     * Record a focus-loss auto-submit the same way log.php does, without going through HTTP.
     *
     * @param quiz_attempt $attemptobj
     * @param string $reason
     * @return \stdClass the inserted log record.
     */
    protected function insert_autosubmit_log(quiz_attempt $attemptobj, string $reason = 'blur'): \stdClass {
        global $DB;

        $record = new \stdClass();
        $record->quizid = $attemptobj->get_quizid();
        $record->cmid = $attemptobj->get_cmid();
        $record->attemptid = $attemptobj->get_attemptid();
        $record->userid = $attemptobj->get_userid();
        $record->reason = $reason;
        $record->timecreated = time();
        $record->id = $DB->insert_record('quizaccess_fsquiz_log', $record);

        return $record;
    }
}
