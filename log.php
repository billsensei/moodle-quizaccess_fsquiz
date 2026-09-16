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
 * AJAX endpoint called by amd/src/lockdown.js just before it force-submits the attempt
 * form, to record why the attempt is being auto-submitted. Modelled on mod/quiz's own
 * autosave.ajax.php: a plain sesskey-protected script rather than a full external/web
 * service definition, since this is only ever called from the attempt page's own JS.
 *
 * The actual attempt submission (and therefore grading) is never performed here: this
 * script only writes an audit trail row. The browser is responsible for submitting the
 * real #responseform to mod/quiz/processattempt.php immediately afterwards, which is what
 * causes the attempt to be graded, exactly as a normal "Submit all and finish" would.
 *
 * @package    quizaccess_fsquiz
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

require_sesskey();

$attemptid = required_param('attempt', PARAM_INT);
$reason    = required_param('reason', PARAM_ALPHA);
$cmid      = optional_param('cmid', null, PARAM_INT);

$allowedreasons = ['visibilitychange', 'blur', 'fullscreenexit', 'osfocuslost'];
if (!in_array($reason, $allowedreasons, true)) {
    throw new invalid_parameter_exception('Unrecognised auto-submit reason');
}

$attemptobj = quiz_create_attempt_handling_errors($attemptid, $cmid);

require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

if ($attemptobj->get_userid() != $USER->id) {
    throw new moodle_exception('notyourattempt', 'quiz', $attemptobj->view_url());
}

if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/quiz:attempt');
}

if (has_capability('quizaccess/fsquiz:bypasslockdown', $attemptobj->get_context())) {
    // Exempt users should never reach this (the JS is not loaded for them), but guard
    // server-side too in case of a stale page/session.
    echo json_encode(['status' => 'exempt']);
    exit;
}

if ($attemptobj->is_finished()) {
    // A duplicate/late call after the attempt was already closed some other way.
    echo json_encode(['status' => 'alreadyfinished']);
    exit;
}

$record = new stdClass();
$record->quizid = $attemptobj->get_quizid();
$record->cmid = $attemptobj->get_cmid();
$record->attemptid = $attemptobj->get_attemptid();
$record->userid = $USER->id;
$record->reason = $reason;
$record->timecreated = time();
$DB->insert_record('quizaccess_fsquiz_log', $record);

// One-shot flag: the next time the review page for this attempt is rendered (which will be
// almost immediately, once the browser finishes submitting #responseform), it should show
// the auto-submit notice and then close the popup, instead of just leaving the notice for
// any later, ordinary visit. See quizaccess_fsquiz::add_autosubmit_notice().
$SESSION->quizaccess_fsquiz_pendingautoclose[$attemptobj->get_attemptid()] = true;

$event = \quizaccess_fsquiz\event\attempt_autosubmitted::create([
    'objectid' => $attemptobj->get_attemptid(),
    'context' => $attemptobj->get_context(),
    'relateduserid' => $USER->id,
    'other' => ['reason' => $reason],
]);
$event->add_record_snapshot('quiz_attempts', $attemptobj->get_attempt());
$event->trigger();

echo json_encode(['status' => 'OK']);
