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

use mod_quiz\form\preflight_check_form;
use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;
use quizaccess_fsquiz\fsquiz_settings;

/**
 * A rule that requires the quiz to be attempted in a locked-down fullscreen popup.
 *
 * If the student leaves that window (another tab, another app, exiting fullscreen) for
 * longer than a configurable grace period, their attempt is automatically submitted as it
 * stands and the popup closes. This rule deliberately does not implement its own attempt
 * storage or grading: it drives the real mod_quiz attempt form's own submission (the same
 * code path used when the time limit expires), so grading, the gradebook, and the question
 * engine all behave exactly as they would for a normal submission.
 *
 * @package   quizaccess_fsquiz
 * @copyright 2026 Fullscreen Lockdown Quiz contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_fsquiz extends access_rule_base {
    /** @var array options used for opening the fullscreen popup window. */
    protected static $popupoptions = [
        'left' => 0,
        'top' => 0,
        'fullscreen' => true,
        'scrollbars' => true,
        'resizable' => false,
        'directories' => false,
        'toolbar' => false,
        'titlebar' => false,
        'location' => false,
        'status' => false,
        'menubar' => false,
    ];

    /**
     * Return an instance of this rule if fullscreen lockdown is enabled for the quiz.
     *
     * @param quiz_settings $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     * @param bool $canignoretimelimits whether the current user is exempt from time limits.
     * @return self|null the rule, if applicable, else null.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        if (!fsquiz_settings::is_enabled($quizobj->get_quizid())) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    /**
     * Whether the current user should have lockdown enforcement applied to them.
     *
     * Preview users (teachers using "Preview quiz") and anyone with the explicit
     * bypass capability are exempt, so that staff checking or demonstrating the quiz
     * are not themselves auto-submitted for switching windows.
     *
     * @return bool
     */
    protected function is_exempt(): bool {
        return $this->quizobj->is_preview_user()
            || has_capability('quizaccess/fsquiz:bypasslockdown', $this->quizobj->get_context());
    }

    /**
     * Return a brief summary of this rule, to show to users.
     *
     * @return string
     */
    public function description() {
        return get_string('ruledescription', 'quizaccess_fsquiz');
    }

    /**
     * Whether the attempt (and review) must be displayed in a pop-up window.
     *
     * @return bool
     */
    public function attempt_must_be_in_popup() {
        return !$this->is_exempt();
    }

    /**
     * Options required when showing the attempt in a pop-up.
     *
     * @return array
     */
    public function get_popup_options() {
        return self::$popupoptions;
    }

    /**
     * Whether a UI check is required before the user starts/continues their attempt.
     *
     * Only required when starting a brand new attempt ($attemptid is null), not on every
     * subsequent page of an attempt that has already begun - otherwise the student would be
     * asked to re-acknowledge the lockdown warning on every single question page.
     *
     * @param int|null $attemptid the id of the current attempt, if there is one.
     * @return bool
     */
    public function is_preflight_check_required($attemptid) {
        return $attemptid === null && !$this->is_exempt();
    }

    /**
     * Add the lockdown warning and acknowledgement checkbox to the pre-flight check form.
     *
     * @param preflight_check_form $quizform the form being built.
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     * @param int|null $attemptid the id of the current attempt, if there is one.
     */
    public function add_preflight_check_form_fields(
        preflight_check_form $quizform,
        MoodleQuickForm $mform,
        $attemptid
    ) {

        $settings = fsquiz_settings::get_for_quiz($this->quiz->id);
        $warningtext = (!empty($settings->warningtext))
            ? $settings->warningtext
            : get_string('defaultwarningtext', 'quizaccess_fsquiz');

        $mform->addElement('header', 'fsquizpreflightheader', get_string('lockdownwarningheader', 'quizaccess_fsquiz'));
        $mform->addElement('static', 'fsquizwarningmessage', '', nl2br(s($warningtext)));
        $mform->addElement(
            'advcheckbox',
            'fsquizacknowledge',
            '',
            get_string('acknowledgelockdown', 'quizaccess_fsquiz')
        );
        $mform->setType('fsquizacknowledge', PARAM_BOOL);
    }

    /**
     * Validate the pre-flight check form submission: the acknowledgement checkbox is required.
     *
     * @param array $data the submitted form data.
     * @param array $files any files in the submission.
     * @param array $errors the list of validation errors that is being built up.
     * @param int|null $attemptid the id of the current attempt, if there is one.
     * @return array the updated $errors array.
     */
    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        if (empty($data['fsquizacknowledge'])) {
            $errors['fsquizacknowledge'] = get_string('mustacknowledge', 'quizaccess_fsquiz');
        }
        return $errors;
    }

    /**
     * Set up the attempt/summary page to enforce lockdown, or the review page to show the
     * auto-submit notice, depending on which script is currently rendering.
     *
     * @param moodle_page $page the page object to initialise.
     */
    public function setup_attempt_page($page) {
        if ($this->is_exempt() || !fsquiz_settings::is_enabled($this->quiz->id)) {
            return;
        }

        $script = basename(parse_url($page->url->out_omit_querystring(), PHP_URL_PATH) ?? '');
        $attemptid = optional_param('attempt', 0, PARAM_INT);

        if ($script === 'review.php') {
            $this->add_autosubmit_notice($page, $attemptid);
            return;
        }

        if ($script !== 'attempt.php' && $script !== 'summary.php') {
            // Focus-loss enforcement only applies while the attempt is still in progress
            // (the attempt.php question pages and the summary.php "ready to submit" page).
            return;
        }

        $settings = fsquiz_settings::get_for_quiz($this->quiz->id);

        // Minimise course navigation and browser chrome distractions inside the popup.
        $page->set_popup_notification_allowed(false);
        $page->set_pagelayout('secure');
        $page->add_body_class('quizaccess-fsquiz-lockdown');

        $page->requires->js_call_amd('quizaccess_fsquiz/lockdown', 'init', [(object) [
            'attemptid' => $attemptid,
            'graceperiod' => fsquiz_settings::clean_grace_period($settings->graceperiod ?? null),
            'logurl' => (new moodle_url('/mod/quiz/accessrule/fsquiz/log.php'))->out(false),
            'sesskey' => sesskey(),
        ]]);
    }

    /**
     * If this attempt was auto-submitted for leaving the window, queue a notification
     * explaining that to the student (and, implicitly, any teacher reviewing it) the next
     * time a page is rendered for it. Used on the review page, after the attempt is over.
     *
     * The very first time the review page loads after an auto-submit (tracked with a
     * one-shot session flag set by log.php), this also tells the popup to show the notice
     * briefly and then close itself, handing the original (opener) window back to the
     * course page - satisfying both "show a clear notice" and "close the popup" without
     * re-triggering that close/redirect on every later, ordinary visit to this review page.
     *
     * @param moodle_page $page
     * @param int $attemptid
     */
    protected function add_autosubmit_notice($page, int $attemptid): void {
        global $DB, $SESSION;

        $entries = $DB->get_records(
            'quizaccess_fsquiz_log',
            ['attemptid' => $attemptid],
            'timecreated DESC',
            '*',
            0,
            1
        );
        if (!$entries) {
            return;
        }
        $entry = reset($entries);

        $settings = fsquiz_settings::get_for_quiz($this->quiz->id);
        $text = (!empty($settings->autosubmittext))
            ? $settings->autosubmittext
            : get_string('defaultautosubmittext', 'quizaccess_fsquiz');
        $text .= ' ' . get_string('autosubmittedatnotice', 'quizaccess_fsquiz', userdate($entry->timecreated));
        $message = s($text);

        if (has_capability('quizaccess/fsquiz:viewlog', $this->quizobj->get_context())) {
            $logurl = new moodle_url('/mod/quiz/accessrule/fsquiz/viewlog.php', ['cmid' => $this->quizobj->get_cmid()]);
            $message .= ' ' . html_writer::link($logurl, get_string('viewlog', 'quizaccess_fsquiz'));
        }

        \core\notification::add($message, \core\notification::WARNING);

        if (!empty($SESSION->quizaccess_fsquiz_pendingautoclose[$attemptid])) {
            unset($SESSION->quizaccess_fsquiz_pendingautoclose[$attemptid]);

            $courseurl = new moodle_url('/course/view.php', ['id' => $this->quizobj->get_courseid()]);
            $page->requires->js_call_amd('quizaccess_fsquiz/lockdown', 'autoclose', [(object) [
                'courseurl' => $courseurl->out(false),
                'delay' => 4000,
            ]]);
        }
    }

    /**
     * Add the fullscreen lockdown fields to the quiz settings form.
     *
     * @param mod_quiz_mod_form $quizform the quiz settings form that is being built.
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {

        $mform->addElement('selectyesno', 'fsquizenabled', get_string('fsquizenabled', 'quizaccess_fsquiz'));
        $mform->setDefault('fsquizenabled', 0);
        $mform->addHelpButton('fsquizenabled', 'fsquizenabled', 'quizaccess_fsquiz');

        $mform->addElement(
            'text',
            'fsquizgraceperiod',
            get_string('fsquizgraceperiod', 'quizaccess_fsquiz'),
            ['size' => 6]
        );
        $mform->setType('fsquizgraceperiod', PARAM_INT);
        $mform->setDefault(
            'fsquizgraceperiod',
            (int) get_config('quizaccess_fsquiz', 'defaultgraceperiod') ?: fsquiz_settings::DEFAULT_GRACE_PERIOD
        );
        $mform->addHelpButton('fsquizgraceperiod', 'fsquizgraceperiod', 'quizaccess_fsquiz');
        $mform->hideIf('fsquizgraceperiod', 'fsquizenabled', 'eq', 0);

        $mform->addElement(
            'textarea',
            'fsquizwarningtext',
            get_string('fsquizwarningtext', 'quizaccess_fsquiz'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('fsquizwarningtext', PARAM_TEXT);
        $mform->addHelpButton('fsquizwarningtext', 'fsquizwarningtext', 'quizaccess_fsquiz');
        $mform->hideIf('fsquizwarningtext', 'fsquizenabled', 'eq', 0);

        $mform->addElement(
            'textarea',
            'fsquizautosubmittext',
            get_string('fsquizautosubmittext', 'quizaccess_fsquiz'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('fsquizautosubmittext', PARAM_TEXT);
        $mform->addHelpButton('fsquizautosubmittext', 'fsquizautosubmittext', 'quizaccess_fsquiz');
        $mform->hideIf('fsquizautosubmittext', 'fsquizenabled', 'eq', 0);
    }

    /**
     * Validate the grace period submitted via the quiz settings form.
     *
     * @param array $errors the errors found so far.
     * @param array $data the submitted form data.
     * @param array $files information about any uploaded files.
     * @param mod_quiz_mod_form $quizform the quiz form object.
     * @return array $errors the updated $errors array.
     */
    public static function validate_settings_form_fields(
        array $errors,
        array $data,
        $files,
        mod_quiz_mod_form $quizform
    ): array {

        if (!empty($data['fsquizenabled'])) {
            $graceperiod = (int) ($data['fsquizgraceperiod'] ?? 0);
            if ($graceperiod < fsquiz_settings::MIN_GRACE_PERIOD || $graceperiod > fsquiz_settings::MAX_GRACE_PERIOD) {
                $errors['fsquizgraceperiod'] = get_string('fsquizgraceperiod_help', 'quizaccess_fsquiz');
            }
        }

        return $errors;
    }

    /**
     * Save the submitted fullscreen lockdown settings when the quiz settings form is saved.
     *
     * @param stdClass $quiz the data from the quiz form, including $quiz->id.
     */
    public static function save_settings($quiz) {
        fsquiz_settings::save($quiz);
    }

    /**
     * Delete the fullscreen lockdown settings and log when the quiz is deleted.
     *
     * @param stdClass $quiz the data from the database, including $quiz->id.
     */
    public static function delete_settings($quiz) {
        fsquiz_settings::delete($quiz->id);
    }

    /**
     * SQL to load the fullscreen lockdown settings alongside the quiz's own settings.
     *
     * @param int $quizid the id of the quiz we are loading settings for.
     * @return array [fields, joins, params], see {@see access_rule_base::get_settings_sql()}.
     */
    public static function get_settings_sql($quizid): array {
        return [
            'fsquiz.enabled AS fsquizenabled, '
            . 'fsquiz.graceperiod AS fsquizgraceperiod, '
            . 'fsquiz.warningtext AS fsquizwarningtext, '
            . 'fsquiz.autosubmittext AS fsquizautosubmittext',
            'LEFT JOIN {quizaccess_fsquiz} fsquiz ON fsquiz.quizid = quiz.id',
            [],
        ];
    }
}
