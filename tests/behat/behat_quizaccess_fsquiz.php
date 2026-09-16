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
 * Step definitions related to quizaccess_fsquiz.
 *
 * @package   quizaccess_fsquiz
 * @category  test
 * @copyright 2026 Fullscreen Lockdown Quiz contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;

/**
 * Simulates the browser-level events that a real tab switch, app switch, or fullscreen
 * exit would fire, since Selenium/WebDriver has no supported way to genuinely take OS
 * focus away from the browser window under test. Dispatching the same DOM events that
 * amd/src/lockdown.js listens for is the standard, honest way to exercise that JS in an
 * automated browser test; it is not a substitute for manual/exploratory testing of the
 * real fullscreen and window-switching behaviour across browsers, which is called out as
 * a limitation in README.md.
 *
 * @copyright 2026 Fullscreen Lockdown Quiz contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_quizaccess_fsquiz extends behat_base {
    /**
     * Dispatch a window "blur" event, simulating the student switching to another
     * tab/application while attempting a locked-down quiz.
     *
     * @Given /^I simulate leaving the fullscreen quiz window$/
     */
    public function i_simulate_leaving_the_fullscreen_quiz_window(): void {
        $this->execute_script('window.dispatchEvent(new Event("blur"));');
    }

    /**
     * Dispatch a window "focus" event, simulating the student returning to the quiz
     * window before the grace period has elapsed (should cancel a pending auto-submit).
     *
     * @Given /^I simulate returning to the fullscreen quiz window$/
     */
    public function i_simulate_returning_to_the_fullscreen_quiz_window(): void {
        $this->execute_script('window.dispatchEvent(new Event("focus"));');
    }

    /**
     * Go directly to the fullscreen lockdown auto-submit log for a quiz, identified by
     * name. This plugin does not (yet) add its own entry to the quiz's navigation menus
     * (see README.md); teachers normally reach this page via the link shown in the
     * auto-submit notice on an affected attempt's review page.
     *
     * @Given /^I view the fullscreen lockdown log for the quiz "(?P<quiz_name>(?:[^"]|\\")*)"$/
     * @param string $quizname
     */
    public function i_view_the_fullscreen_lockdown_log_for_the_quiz(string $quizname): void {
        global $DB;

        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
        $this->getSession()->visit($this->locate_path('/mod/quiz/accessrule/fsquiz/viewlog.php?cmid=' . $cm->id));
    }

    /**
     * Insert rows directly into the auto-submit audit log, bypassing the JS/grace-period
     * flow, for scenarios that only care about what teachers see afterwards (not about
     * exercising the focus-loss detection itself - see fullscreen_exit_triggers_submit.feature
     * for that).
     *
     * | quiz   | user     | reason           | attempt |
     * | Quiz 1 | student1 | blur             | 1       |
     *
     * "attempt" is the attempt number (as in "user has attempted quiz" fixtures), not a raw id.
     *
     * @Given /^the following fsquiz auto-submit logs exist:$/
     * @param TableNode $data
     */
    public function the_following_fsquiz_autosubmit_logs_exist(TableNode $data): void {
        global $DB;

        foreach ($data->getHash() as $row) {
            $quiz = $DB->get_record('quiz', ['name' => $row['quiz']], '*', MUST_EXIST);
            $user = $DB->get_record('user', ['username' => $row['user']], '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
            $attempt = $DB->get_record('quiz_attempts', [
                'quiz' => $quiz->id,
                'userid' => $user->id,
                'attempt' => $row['attempt'],
            ], '*', MUST_EXIST);

            $DB->insert_record('quizaccess_fsquiz_log', (object) [
                'quizid' => $quiz->id,
                'cmid' => $cm->id,
                'attemptid' => $attempt->id,
                'userid' => $user->id,
                'reason' => $row['reason'],
                'timecreated' => time(),
            ]);
        }
    }
}
