# Changelog

## 1.0.0 (2026-09-16)

Initial release.

### Fixed via live testing against a real Moodle 5.1.7 server

Two bugs in the initial implementation were caught only by installing the plugin on an actual
Moodle instance and driving the real HTTP flow (preflight, attempt, force-submit, review) end
to end - neither was visible from static review or from PHPUnit tests that only exercised the
plugin's own code in isolation:

- **`is_preflight_check_required()` looped forever.** It ignored `$attemptid` and always
  returned `true`, so after a student acknowledged the lockdown warning and an attempt was
  created, the *very next page load of that same attempt* required the acknowledgement again,
  and again - the student could never actually reach the attempt. Fixed to only require the
  check when starting a new attempt (`$attemptid === null`), matching `quizaccess_timelimit`'s
  own pattern. A regression test now asserts `is_preflight_check_required($existingid)` is
  `false`.
- **The force-submit only worked on quizzes with an imminent time limit.** `lockdown.js`
  mirrored `mod/quiz/module.js`'s countdown-timer behaviour of setting `timeup=1` on
  `#responseform`, on the assumption that this alone forces a finish. It does not:
  `quiz_attempt::process_attempt()` recalculates server-side whether time has genuinely run
  out from the quiz's own close/time-limit data, and silently discards a client-submitted
  `timeup=1` unless that deadline is actually imminent. On any quiz without a time limit about
  to expire - the normal case for this plugin - the "auto-submit" would have silently just
  saved the current page's answers and moved on, never actually finishing the attempt. What
  actually forces a finish is `finishattempt=1`, which is only present in the DOM as part of
  the "Submit all and finish" button and does not exist on every page. Fixed by having
  `lockdown.js` create that hidden field if it is not already present, rather than only
  updating it if it happens to exist.

### Design decisions

- **Subplugin architecture over a standalone activity.** Built as `quizaccess_fsquiz` (a
  `mod_quiz` access rule, like `quizaccess_seb`/`quizaccess_securewindow`) rather than a
  standalone `mod_fsquiz` activity, so it attaches to real Quiz activities and reuses
  `mod_quiz`'s own attempt storage, question engine, and gradebook integration wholesale. See
  README.md for the full rationale.

- **How the forced submission is captured.** `amd/src/lockdown.js` does not read, store, or
  transmit any answer data itself. After the grace period elapses it sets the same hidden
  `timeup`/`finishattempt` fields on the real attempt form (`#responseform`) that
  `mod/quiz/module.js` sets when the countdown timer expires, then calls `form.submit()`. The
  browser's normal form POST to `processattempt.php` is what actually grades the attempt, using
  whatever was in the DOM at that instant — so "the grade reflects the state of the attempt at
  the exact moment of exit" is a natural consequence of reusing the real submission path, not
  something this plugin has to implement itself.

- **Audit logging is a separate, best-effort step, not the source of truth for grading.**
  `log.php` records *why* an attempt was forced shut (for the teacher-facing log/notice) before
  the JS submits the real form. If that logging call fails (network blip, etc.), the attempt is
  still submitted — losing the audit trail entry is preferable to leaving a student's attempt
  hanging open after they've already left the window.

- **No custom "reopen attempt" feature.** Considered and rejected: Moodle core has no supported
  way to safely resume a finished attempt's question-engine state, and building one in a
  subplugin risks corrupting attempt data for the sake of a "nice to have." Instead, the log
  viewer links straight to the attempt's review page so a teacher can use Moodle's existing,
  supported manual grading / grade override tools if a false positive is confirmed.

- **Fullscreen requires an explicit in-popup click.** The Fullscreen API can only be invoked
  from a genuine user gesture inside the document that calls it; a page that opened a popup
  cannot force that popup into fullscreen on its behalf. The popup therefore shows a "click to
  begin" overlay rather than attempting (and silently failing at) auto-fullscreen.

- **The auto-submit notice is shown via `\core\notification::add()`** on every visit to the
  affected attempt's review page (not a one-time flash), so a teacher grading days later still
  sees why the attempt ended early. Closing the popup and handing the opener window back to the
  course page, by contrast, is a one-shot action (tracked via a short-lived session flag set by
  `log.php`) — it only happens immediately after the auto-submit, not on every later, ordinary
  visit to the same review page.

- **Grace period default: 500ms**, teacher-configurable per quiz (100–10000ms), with a
  site-wide default admin setting. Chosen as short enough to stop a genuine tab switch while
  absorbing brief OS/accessibility flicker; documented as a trade-off, not a guarantee, in
  README.md's "Accessibility, fairness, and known false-positive risks" section.
