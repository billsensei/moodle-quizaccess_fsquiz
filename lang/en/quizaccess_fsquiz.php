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
 * English language strings for quizaccess_fsquiz.
 *
 * @package    quizaccess_fsquiz
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['acknowledgelockdown'] = 'I understand that leaving this window will immediately submit my attempt as it stands.';
$string['autosubmittedatnotice'] = 'Auto-submitted at {$a} because focus was lost.';
$string['beginattempt'] = 'Enter fullscreen and begin';
$string['colactions'] = 'Actions';
$string['colattempt'] = 'Attempt';
$string['colreason'] = 'Reason';
$string['coltime'] = 'Time';
$string['coluser'] = 'User';
$string['defaultautosubmittext'] = 'Your attempt was automatically submitted because you left the locked-down quiz window.';
$string['defaultgraceperiod'] = 'Default grace period (milliseconds)';
$string['defaultgraceperiod_desc'] = 'Default value pre-filled in the "Grace period" field when a teacher enables fullscreen lockdown on a new quiz activity. Individual activities can override this.';
$string['defaultwarningtext'] = 'This quiz must be completed in fullscreen. If you switch to another tab or application, exit fullscreen, or otherwise leave this window, your attempt will be submitted immediately with whatever answers you have entered so far.';
$string['eventattemptautosubmitted'] = 'Quiz attempt auto-submitted due to focus loss';
$string['fsquiz:bypasslockdown'] = 'Bypass fullscreen lockdown enforcement';
$string['fsquiz:viewlog'] = 'View the fullscreen lockdown auto-submit log';
$string['fsquizautosubmittext'] = 'Message shown after an auto-submit';
$string['fsquizautosubmittext_help'] = 'Plain text shown to students (and recorded for teachers) when their attempt is force-submitted for leaving the quiz window. Leave blank to use the default wording.';
$string['fsquizenabled'] = 'Enable fullscreen lockdown';
$string['fsquizenabled_help'] = 'When enabled, attempts at this quiz open in a fullscreen popup window. If a student switches away from that window (another tab, another application, or exits fullscreen) for longer than the grace period, their attempt is automatically submitted as it stands and the popup closes.';
$string['fsquizgraceperiod'] = 'Grace period (milliseconds)';
$string['fsquizgraceperiod_help'] = 'How long the student is allowed to be away from the quiz window before their attempt is auto-submitted. This absorbs brief, harmless focus changes (an OS notification, a screen-reader focus shift) without being long enough to allow a genuine tab switch. Default: 500ms. Must be between 100 and 10000.';
$string['fsquizwarningtext'] = 'Warning shown before the attempt starts';
$string['fsquizwarningtext_help'] = 'Plain text shown to students before they begin a locked-down attempt, explaining that leaving the quiz window will auto-submit their work. Leave blank to use the default wording.';
$string['fullscreenunavailable'] = 'Your browser would not allow fullscreen mode. Your attempt will still be auto-submitted if you switch away from this window, but exiting fullscreen itself cannot be detected.';
$string['lockdowninstructions'] = 'This attempt must be completed in fullscreen. Click below to enter fullscreen and begin. Leaving this window at any point will submit your attempt immediately.';
$string['lockdowntitle'] = 'Fullscreen lockdown quiz';
$string['lockdownwarningheader'] = 'Fullscreen lockdown quiz';
$string['mustacknowledge'] = 'You must confirm that you understand the lockdown rules before starting the attempt.';
$string['nologs'] = 'No attempts at this quiz have been auto-submitted due to focus loss.';
$string['pluginname'] = 'Fullscreen lockdown quiz';
$string['privacy:metadata:quizaccess_fsquiz_log'] = 'A record of a quiz attempt that was automatically submitted because the student left the locked-down quiz window.';
$string['privacy:metadata:quizaccess_fsquiz_log:attemptid'] = 'The quiz attempt that was auto-submitted.';
$string['privacy:metadata:quizaccess_fsquiz_log:reason'] = 'The detected reason focus was lost (tab switch, window blur, fullscreen exit).';
$string['privacy:metadata:quizaccess_fsquiz_log:timecreated'] = 'The time the auto-submit was triggered.';
$string['privacy:metadata:quizaccess_fsquiz_log:userid'] = 'The user whose attempt was auto-submitted.';
$string['reason_blur'] = 'Window lost focus';
$string['reason_fullscreenexit'] = 'Exited fullscreen';
$string['reason_osfocuslost'] = 'Lost OS-level focus';
$string['reason_visibilitychange'] = 'Switched tab / minimised (page hidden)';
$string['reviewattempt'] = 'Review attempt';
$string['ruledescription'] = 'This quiz must be attempted in a locked-down fullscreen window. Leaving the window will automatically submit your attempt.';
$string['viewlog'] = 'Fullscreen lockdown log';
$string['viewlogtitle'] = 'Fullscreen lockdown auto-submit log: {$a}';
