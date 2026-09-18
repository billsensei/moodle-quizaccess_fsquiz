# Fullscreen Lockdown Quiz (quizaccess_fsquiz)

## This is still in Beta, please use in testing environments only!

A Moodle 5.1 quiz access rule that runs an attempt in a locked-down fullscreen popup and
**immediately auto-submits it, as-is, the moment the student leaves that window** — another
tab, another application, exiting fullscreen, or losing focus for any other reason.

## Why this is a `quizaccess` subplugin, not a new activity type

The brief's example component name was `mod_fsquiz`, but this is built as
**`quizaccess_fsquiz`**, a `mod_quiz` access rule subplugin — the same extension point Moodle
core itself uses for Safe Exam Browser (`quizaccess_seb`) and the older secure-popup window
(`quizaccess_securewindow`). Concretely:

- It attaches to any **existing Quiz activity** (Settings → *Extra restrictions on attempts*),
  rather than being a separate activity type students add to a course.
- Fullscreen/focus enforcement wraps the **real** `mod_quiz` attempt page (`attempt.php`),
  summary page, and review page — not a re-implementation of them.
- When an attempt needs to be forced shut, this plugin does **not** grade anything itself. It
  drives the real attempt form (`#responseform`) to submit itself with the same hidden
  `timeup`/`finishattempt` fields the quiz's own countdown timer sets when time expires, so the
  submission goes through `mod_quiz`'s normal `processattempt.php` → `quiz_attempt::process_attempt()`
  pipeline. Grading, the gradebook, and the question engine behave exactly as they would for a
  student-initiated "Submit all and finish" — because, from Moodle's point of view, that is
  what happened.

Building this as a standalone activity would have meant either wrapping a `mod_quiz` instance
awkwardly (two activities per quiz) or reimplementing question rendering, attempt state, and
grading from scratch. Neither was necessary: `mod_quiz` already has exactly the extension point
this needs.

## Installation

1. Copy this directory to `<moodle>/mod/quiz/accessrule/fsquiz`.
2. Visit *Site administration → Notifications* (or run `php admin/cli/upgrade.php`) to install.
3. Optionally set a site-wide default grace period at *Site administration → Plugins → Quiz
   access rules → Fullscreen lockdown quiz*.

To enable it on a quiz: edit the quiz → *Extra restrictions on attempts* → **Enable fullscreen
lockdown** → Yes, then set the grace period and (optionally) custom warning/auto-submit wording.

## How it works

1. **Starting an attempt.** A student clicking "Attempt quiz" sees a preflight confirmation
   (the same mechanism the password/time-limit rules use) explaining the lockdown, which they
   must acknowledge before the popup opens (`attempt_must_be_in_popup()` + `get_popup_options()`,
   mirroring `quizaccess_securewindow`). The popup uses the `secure` page layout to minimise
   course navigation and chrome.
2. **Entering fullscreen.** Inside the popup, an overlay asks the student to click a button to
   begin. This click is required by browser security: the Fullscreen API can only be invoked
   from a genuine user gesture *inside the document that requests it*, so the popup cannot be
   forced into fullscreen automatically by the page that opened it (`amd/src/lockdown.js`).
3. **Watching for focus loss.** Once fullscreen is entered (or declined — see *Limitations*
   below), `amd/src/lockdown.js` listens for `visibilitychange` (tab hidden), `blur` (window
   loses focus), and `fullscreenchange` (fullscreen exited). Each one starts a debounce timer
   (the configurable **grace period**, default 500ms); returning focus before it fires cancels
   the timer.
4. **Forcing the submission.** If the grace period elapses, the JS:
   - Records the reason (`visibilitychange` / `blur` / `fullscreenexit`) via a small
     sesskey-protected endpoint (`log.php`, modelled on `mod_quiz`'s own `autosave.ajax.php`).
   - Sets `#responseform`'s hidden `timeup`/`finishattempt` fields and calls `form.submit()` —
     exactly what `mod/quiz/module.js` does when the countdown timer reaches zero. Whatever
     answers were on the page at that moment are what gets submitted and graded; unanswered
     questions are marked wrong/blank under the quiz's normal grading rules.
5. **After the submit.** The review page shows a notification (built from the configurable
   "message after auto-submit" text, or a sensible default) explaining what happened and when.
   The very first time that page loads after the auto-submit, the popup also shows that notice
   for a few seconds and then closes itself, handing the original window back to the course
   page — later, ordinary visits to the same review page just show the persistent notice
   without re-triggering the close.

No custom question/answer storage exists anywhere in this plugin: `quizaccess_fsquiz` and
`quizaccess_fsquiz_log` hold only the per-quiz lockdown settings and an audit trail of *why* and
*when* attempts were force-submitted.

Course backup/restore/duplicate carries the lockdown **settings** across (`backup/moodle2/`),
the same way any other quiz access rule's settings would be. The auto-submit **audit log** is
deliberately not included in backups: it is a record of what happened to specific attempts, not
activity configuration.

## Settings

| Setting | Where | Purpose |
|---|---|---|
| Default grace period | Site administration → Plugins → Quiz access rules | Pre-fills the per-quiz grace period field. |
| Enable fullscreen lockdown | Quiz settings → Extra restrictions on attempts | Per-activity on/off switch. |
| Grace period (ms) | Quiz settings | Debounce window before an auto-submit fires. Clamped to 100–10000ms. |
| Warning shown before the attempt starts | Quiz settings | Shown on the preflight confirmation screen. |
| Message shown after an auto-submit | Quiz settings | Shown on the review page notice. |

## Capabilities

- `quizaccess/fsquiz:viewlog` (teacher, editingteacher, manager) — view the auto-submit audit
  log for a quiz, at `mod/quiz/accessrule/fsquiz/viewlog.php?cmid=<cmid>`. A link to this page
  is included automatically in the review-page notice for anyone who holds it.
- `quizaccess/fsquiz:bypasslockdown` (editingteacher, manager) — exempts the user from fullscreen
  lockdown, in addition to the standard "Preview quiz" exemption every access rule already
  respects. Useful for a teacher taking a real (non-preview) attempt without being locked down.

## Accessibility, fairness, and known false-positive risks

Focus-loss detection is inherently imperfect. This plugin cannot distinguish "a student is
looking something up on their phone" from:

- **Assistive technology.** A screen reader or switch-access tool moving focus to its own UI,
  or an OS accessibility panel, can trigger a `blur` event without the student having
  deliberately left the quiz.
- **OS-level popups and notifications.** A password manager prompt, a system update dialog, or
  an incoming-call notification can steal focus momentarily.
- **Multi-monitor setups.** Some window managers fire spurious blur/visibility events when
  windows are dragged between displays or when a second monitor's screensaver activates.
- **Fullscreen refusal.** Some browsers/extensions block the Fullscreen API outright. If that
  happens, `lockdown.js` still watches focus/visibility (so leaving the window is still
  detected), but `fullscreenchange` detection is meaningless since fullscreen was never
  entered — the student sees a warning about this (`fullscreenunavailable` string).
- **The grace period is a mitigation, not a fix.** It absorbs *brief* flicker, not a
  determined attempt to alt-tab and back quickly. There is an inherent trade-off between
  tolerating real accessibility/OS behaviour and tolerating a genuine but quick tab switch;
  this plugin deliberately keeps the default short (500ms) and makes it teacher-configurable
  rather than trying to guess intent.

**When a false positive is reported:** every auto-submit is logged with who, when, and why
(`quizaccess/fsquiz:viewlog`, linked from the review page notice). This plugin deliberately
does **not** attempt to programmatically "reopen" a finished attempt — Moodle core has no
supported way to safely resume a finished attempt's question-engine state, and hacking that in
a subplugin risks corrupting attempt data. Instead, `viewlog.php` links straight to the
attempt's review page, where a teacher can manually re-grade / override the grade using
Moodle's existing, supported grading tools, informed by the logged reason and timestamp.

## Testing

- **PHPUnit** (`tests/rule_test.php`, `tests/privacy_test.php`): settings persistence and grace
  period clamping, `make()`/preflight-check contract, and — the important one — that force-
  finishing an attempt via the same `process_submit()`/`process_grade_submission()` calls
  `processattempt.php` itself uses produces the correct grade from whatever was answered
  (including a partial-answers case), plus the full privacy provider contract (export,
  delete-for-user, delete-for-users, delete-for-context).
- **Behat** (`tests/behat/fullscreen_exit_triggers_submit.feature`): a custom step
  (`tests/behat/behat_quizaccess_fsquiz.php`) dispatches the same `blur`/`focus` DOM events a
  real tab switch would fire, since WebDriver has no supported way to take genuine OS focus
  away from the browser under test — this is the standard, honest way to exercise this kind of
  JS in an automated browser test, not a substitute for manual cross-browser testing of the
  real fullscreen/window-switching behaviour.

### Verified against a real Moodle 5.1.7 install

This plugin was installed on a live Moodle 5.1.7 (MariaDB) server via the normal CLI upgrade,
and exercised through the real HTTP flow (login, preflight acknowledgement, attempt, force
submit, review, teacher log viewer) rather than only reviewed statically. That live run is what
caught the two bugs documented at the top of CHANGES.md - both invisible to static review and
to PHPUnit tests that only call the plugin's own code in isolation, since one only manifests
across two real HTTP requests to core, and the other depends on how `processattempt.php`
recalculates `timeup` server-side against the quiz's actual close time.

`moodle-plugin-ci` was also run for real against the deployed plugin:

```bash
php vendor/bin/moodle-plugin-ci phplint    path/to/quizaccess_fsquiz   # 16/16 files, no syntax errors
php vendor/bin/moodle-plugin-ci phpcs      path/to/quizaccess_fsquiz   # 0 errors, 0 warnings
php vendor/bin/moodle-plugin-ci phpmd      path/to/quizaccess_fsquiz   # only unavoidable "unused
                                                                        # parameter" notes from
                                                                        # implementing required
                                                                        # access_rule_base/event
                                                                        # method signatures
php vendor/bin/moodle-plugin-ci validate -m path/to/moodle path/to/quizaccess_fsquiz  # passes
grunt eslint:amd    # (from the plugin directory)                       # clean
grunt amd           # (from the plugin directory)                       # builds amd/build/ ok
```

**Not yet run for real**: `moodle-plugin-ci phpunit` (would need Moodle's separate PHPUnit test
environment - `admin/tool/phpunit/cli/init.php` and a second test database - initialised on a
server, which wasn't available here) and `moodle-plugin-ci behat` (needs Selenium/a browser
driver). The PHPUnit and Behat suites under `tests/` are believed correct - they follow the same
patterns just verified live, and the PHPUnit suite exercises the identical
`process_submit()`/`process_grade_submission()` grading path used above - but haven't
themselves been executed by the test runner. Run those two before relying on them as a
regression gate:

```bash
export MOODLE_DIR=/path/to/moodle
export DB=mysqli   # or pgsql
php vendor/bin/moodle-plugin-ci install --plugin path/to/quizaccess_fsquiz
php vendor/bin/moodle-plugin-ci phpunit path/to/quizaccess_fsquiz
php vendor/bin/moodle-plugin-ci behat --profile chrome path/to/quizaccess_fsquiz
```

## Privacy (GDPR)

The only personal data this plugin stores is the audit log (`quizaccess_fsquiz_log`): which
user, which attempt, when, and the machine-readable reason. The privacy provider
(`classes/privacy/provider.php`) implements full export/delete support, including the
multi-user (`core_userlist_provider`) API. The settings table (`quizaccess_fsquiz`) holds no
personal data.

## License

GPL v3 or later, consistent with Moodle core.
# moodle-quizaccess_fsquiz
