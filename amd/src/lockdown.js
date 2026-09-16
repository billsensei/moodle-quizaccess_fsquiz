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
 * Fullscreen lockdown enforcement for the quiz attempt page.
 *
 * Shows an overlay asking the student to enter fullscreen to begin (the Fullscreen API can
 * only be invoked from a genuine user gesture inside this document, so we cannot force it
 * automatically from the page that opened the popup). Once fullscreen is entered, watches
 * for visibilitychange, window blur, and fullscreenchange. After a short configurable grace
 * period (to absorb OS notification flicker, screen-reader focus shifts, etc.) it force
 * submits the real attempt form (#responseform) exactly the way the quiz timer does when
 * time expires: it sets the same hidden timeup/finishattempt fields and calls form.submit(),
 * so grading goes through mod_quiz's own normal code path. No answer data is read, stored,
 * or transmitted by this module.
 *
 * @module     quizaccess_fsquiz/lockdown
 * @copyright  2026 Fullscreen Lockdown Quiz contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import Notification from 'core/notification';
import Prefetch from 'core/prefetch';
import {getString} from 'core/str';
import {markFormSubmitted} from 'core_form/changechecker';

const SELECTORS = {
    form: '#responseform',
    beginButton: '[data-action="quizaccess-fsquiz-begin"]',
};

const TEMPLATE = 'quizaccess_fsquiz/lockdown_overlay';

/**
 * Set a hidden field's value within a form, creating the field first if it is not already
 * present (some attempt-form hidden fields, like finishattempt, are normally only rendered
 * as part of a button that does not appear on every page of the quiz).
 *
 * @param {HTMLFormElement} form
 * @param {string} name
 * @param {string} value
 * @return {void}
 */
const setHiddenField = (form, name, value) => {
    let input = form.querySelector(`input[name="${name}"]`);
    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        form.appendChild(input);
    }
    input.value = value;
};

/**
 * Attach the focus-loss/fullscreen-exit listeners and start watching.
 *
 * @param {Object} config {attemptid, graceperiod, logurl, sesskey}
 * @return {void}
 */
const watch = (config) => {
    let graceTimer = null;
    let submitted = false;

    const stopWatching = () => {
        document.removeEventListener('visibilitychange', onVisibilityChange);
        window.removeEventListener('blur', onBlur);
        window.removeEventListener('focus', onFocus);
        document.removeEventListener('fullscreenchange', onFullscreenChange);
    };

    const submitAttempt = (reason) => {
        if (submitted) {
            return;
        }
        submitted = true;
        stopWatching();

        const form = document.querySelector(SELECTORS.form);
        const finish = () => {
            if (!form) {
                return;
            }

            // mod_quiz's own processattempt.php recalculates server-side whether time has
            // actually run out from the quiz's real close/time-limit data; it does NOT trust
            // a client-submitted timeup=1 unless that deadline is genuinely imminent (so this
            // is a no-op on most quizzes, but harmless to set for quizzes that do have a time
            // limit close to expiring anyway).
            setHiddenField(form, 'timeup', '1');

            // finishattempt is what actually forces process_attempt() to finish and grade the
            // attempt rather than just recording this page's answers and moving on. Unlike
            // timeup, it is only present in the DOM as part of the "Submit all and finish"
            // button, which does not exist on every page (see SELECTORS.form usage above) -
            // so it must be added here, not just updated if already present.
            setHiddenField(form, 'finishattempt', '1');

            markFormSubmitted(form);
            form.submit();
        };

        // Record why we are about to force-submit before navigating away. If this request
        // fails for any reason (network blip, session hiccup) we still submit the attempt:
        // losing the audit trail entry is far preferable to leaving the attempt hanging open
        // after the student has already left the window.
        window.fetch(config.logurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            credentials: 'same-origin',
            keepalive: true,
            body: new URLSearchParams({
                attempt: config.attemptid,
                reason: reason,
                sesskey: config.sesskey,
            }),
        }).catch(() => null).finally(finish);
    };

    const scheduleSubmit = (reason) => {
        if (submitted || graceTimer) {
            return;
        }
        graceTimer = window.setTimeout(() => {
            graceTimer = null;
            submitAttempt(reason);
        }, config.graceperiod);
    };

    const cancelSchedule = () => {
        if (graceTimer) {
            window.clearTimeout(graceTimer);
            graceTimer = null;
        }
    };

    const onVisibilityChange = () => {
        if (document.visibilityState === 'hidden') {
            scheduleSubmit('visibilitychange');
        } else {
            cancelSchedule();
        }
    };

    const onBlur = () => {
        // A window.blur can also fire when focus moves to an OS-level dialog (a print
        // prompt, a file picker triggered by the page itself) without the student having
        // switched away. document.hasFocus() lets a same-tick refocus cancel the timer,
        // which the grace period is there to absorb anyway.
        scheduleSubmit('blur');
    };

    const onFocus = () => cancelSchedule();

    const onFullscreenChange = () => {
        if (!document.fullscreenElement) {
            scheduleSubmit('fullscreenexit');
        } else {
            cancelSchedule();
        }
    };

    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('blur', onBlur);
    window.addEventListener('focus', onFocus);
    document.addEventListener('fullscreenchange', onFullscreenChange);
};

/**
 * Ask the browser for fullscreen, remove the overlay, and start watching regardless of
 * whether fullscreen was actually granted (focus/visibility detection works either way;
 * only fullscreenchange detection depends on fullscreen having been entered).
 *
 * @param {HTMLElement} overlay
 * @param {Object} config
 * @return {Promise}
 */
const beginLockdown = async(overlay, config) => {
    try {
        if (document.documentElement.requestFullscreen) {
            await document.documentElement.requestFullscreen();
        }
    } catch (e) {
        Notification.addNotification({
            message: await getString('fullscreenunavailable', 'quizaccess_fsquiz'),
            type: 'warning',
        });
    }
    overlay.remove();
    watch(config);
};

/**
 * Render the "enter fullscreen to begin" overlay and wire up its button.
 *
 * @param {Object} config
 * @return {void}
 */
const showOverlay = (config) => {
    Templates.renderForPromise(TEMPLATE, {}).then(({html, js}) => {
        Templates.appendNodeContents(document.body, html, js);
        const overlay = document.body.lastElementChild;
        const button = overlay.querySelector(SELECTORS.beginButton);
        button.addEventListener('click', () => beginLockdown(overlay, config), {once: true});
        button.focus();
        return null;
    }).catch(Notification.exception);
};

/**
 * Initialise fullscreen lockdown on the current attempt/summary page.
 *
 * @param {Object} config {attemptid: Number, graceperiod: Number, logurl: String, sesskey: String}
 * @return {void}
 */
export const init = (config) => {
    Prefetch.prefetchTemplate(TEMPLATE);
    Prefetch.prefetchStrings('quizaccess_fsquiz', [
        'lockdowntitle', 'lockdowninstructions', 'beginattempt', 'fullscreenunavailable',
    ]);
    showOverlay(config);
};

/**
 * Called on the review page, only immediately after an auto-submit (see
 * quizaccess_fsquiz::add_autosubmit_notice() and its one-shot session flag). Gives the
 * student a moment to read the auto-submit notice already on the page, then hands the
 * window that opened this popup back to the course page and closes this popup - fulfilling
 * both "show a clear notice" and "close the popup" without disrupting any later, ordinary
 * visit to this same review page (init() above is not called again, so this never re-fires).
 *
 * @param {Object} config {courseurl: String, delay: Number}
 * @return {void}
 */
export const autoclose = (config) => {
    window.setTimeout(() => {
        if (window.opener && !window.opener.closed) {
            window.opener.location.href = config.courseurl;
            window.close();
        } else {
            // Fullscreen lockdown popups were blocked, or this is not actually a popup
            // (e.g. a teacher preview reached this state some other way): just navigate on.
            window.location.href = config.courseurl;
        }
    }, config.delay);
};
