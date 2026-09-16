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
 * Shows teachers the audit trail of attempts that were automatically submitted because a
 * student left the locked-down fullscreen quiz window, so they can judge whether any were
 * likely false positives (see the accessibility/fairness notes in README.md) and, if so,
 * regrade or otherwise follow up on that attempt manually. This plugin does not attempt to
 * "reopen" a finished attempt itself: Moodle core has no supported way to safely reopen a
 * finished attempt's question engine state, so the responsible action is to review the
 * attempt and adjust its grade, which is what the links below point at.
 *
 * @package    quizaccess_fsquiz
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('quizaccess/fsquiz:viewlog', $context);

$PAGE->set_url('/mod/quiz/accessrule/fsquiz/viewlog.php', ['cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('viewlogtitle', 'quizaccess_fsquiz', format_string($quiz->name)));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('viewlog', 'quizaccess_fsquiz'));

$logs = $DB->get_records_sql(
    "SELECT l.*, u.firstname, u.lastname
       FROM {quizaccess_fsquiz_log} l
       JOIN {user} u ON u.id = l.userid
      WHERE l.quizid = :quizid
   ORDER BY l.timecreated DESC",
    ['quizid' => $quiz->id]
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('viewlogtitle', 'quizaccess_fsquiz', format_string($quiz->name)));

if (!$logs) {
    echo $OUTPUT->notification(get_string('nologs', 'quizaccess_fsquiz'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('coluser', 'quizaccess_fsquiz'),
        get_string('colattempt', 'quizaccess_fsquiz'),
        get_string('colreason', 'quizaccess_fsquiz'),
        get_string('coltime', 'quizaccess_fsquiz'),
        get_string('colactions', 'quizaccess_fsquiz'),
    ];

    foreach ($logs as $log) {
        $reviewurl = new moodle_url('/mod/quiz/review.php', ['attempt' => $log->attemptid]);
        $reasonstring = get_string('reason_' . $log->reason, 'quizaccess_fsquiz');

        $table->data[] = [
            fullname($log),
            html_writer::link($reviewurl, '#' . $log->attemptid),
            $reasonstring,
            userdate($log->timecreated),
            html_writer::link($reviewurl, get_string('reviewattempt', 'quizaccess_fsquiz')),
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
