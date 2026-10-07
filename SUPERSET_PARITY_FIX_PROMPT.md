# FIX PROMPT: Superset parity, verification follow-ups

Paste this into Claude Code (or point it at this file).

The Superset parity work (S0–S8 in `SUPERSET_PARITY_MASTER_PROMPT.md`) was independently verified on 2026-10-07. The verification found:
- one approved feature that was never built;
- 6 medium bugs;
- about 25 small issues.

This file is the binding instruction for fixing them.

---

## PART A: Read first and follow the same rules

1. Read these:
   - `CDC_PORTAL_CONTEXT.md`: product, owner decisions and standing rules.
   - `SUPERSET_PARITY_MASTER_PROMPT.md`: Part A rules, Part B owner answers, and the original spec of each milestone.
   - `CDC/qa/PARITY_VERIFICATION_REPORT.md`: the summary of what is wrong.
   - The detailed reports with file:line evidence and reproduction steps: `CDC/qa/parity_verify/S0_S8.md`, `S1_S2.md`, `S3_S4.md`, `S5_S6.md`, `S7.md`.
   - `CDC/PHASE2_DECISIONS.md`, D1–D118. New entries start at **D119**.
   - `CDC/SUPERSET_PARITY_PROGRESS.md`. Add a section `## VERIFICATION FIXES (2026-10-07)` with a checklist of every item below, and keep CURRENT STATE and NEXT ACTION up to date.
2. **Failing probe tests.** `CDC/backend/tests/Feature/ParityVerify/` holds 128 probe tests written by the verifiers. **15 of them fail on purpose**: each one reproduces a bug below.
   - Your fix is done when its probe passes.
   - Do not weaken or delete a probe to make it pass. If you believe a probe's expectation is wrong, stop, explain why under BLOCKED in the progress file, and ask the owner.
   - Write a regression test for every fix that has no probe yet.
3. **Standing rules (unchanged):**
   - No commits unless the owner says so; the author is the CDC account `<placementportaliitism@gmail.com>`, with no AI trailer.
   - Work only in `CDC/`. Use absolute paths and never run `cd` commands in parallel.
   - MySQL only. Schema changes go in **new** migrations only; never edit a migration that has already run.
   - Never rename routes, columns, enum values, JSON keys or audit action names. Renames are UI labels only.
   - **Excel export headers stay exactly as they are.**
   - New frontend files are `.js`/`.jsx` and use MUI `sx`.
   - Every admin write goes through `AuditService`.
   - Mail goes through `MailDispatchService` (`sendBulk` = BCC batches for broadcasts). Use `MAIL_MAILER=log` while testing.
   - IST everywhere. Exports are formula-safe (explicit string cells).
   - Companies see only their own data and published outcomes, and never the internal flags.
   - Students never see applicant counts.
   - No package installs without the owner's OK. No live attack-style testing. Never print secrets.
4. **How to run tests:**
   - Run from `CDC/backend` with `php -d memory_limit=2G vendor/bin/phpunit` (filter with `--filter`).
   - The suite rewrites `CDC/qa/evidence/*.md` and `CDC/security/evidence/authorization_matrix.md`. Restore them afterwards with `git -C /Users/admin/Desktop/CDC-main restore CDC/qa/evidence CDC/security/evidence`.
5. **Verify in the browser** at 375, 1024, 1280 and 1440 px for every UI change, using the local data. The local admin is in the seeder docblock. Before claiming done, check the browser console and the backend log for errors.

---

## PART B: One owner question to ask first (AskUserQuestion)

**"Resend to all pending" invitations currently also re-invites students whose invitation was Revoked. What should happen?**
- (Recommended) Exclude Revoked students from "Resend to all pending". A revoked student can still be re-invited individually or by explicit selection, with a confirmation that says it un-revokes them.
- Keep including them, but say so in the confirm dialog with the count.

Record the answer as a decision and implement it as fix L15.

---

## PART C: Fixes, in this order

### H1 (High). Build "Reconcile Ineligible Students" (owner decision B2-11: YES)
It was approved but never built: there is no route, service or UI. Superset reference: `superset_video_analysis/06_hiring_stages.md` §2 F11.

**Behaviour:**
- On each **stage shortlist page** (`/admin/postings/[id]/rounds/[roundId]`), add a button **"Reconcile Ineligible Students"**.
- It opens a dialog listing every student in **that stage's current pool** (`PipelineService::pool()`) who is **no longer eligible**. Eligibility comes **only** from `EligibilityService::check()`, against the job profile's current eligibility and active blocks and debarments. Never write a second set of eligibility rules.
- **Report:** each row shows Roll Number, name, branch and the student-facing **reasons** (the same sentences students see). Students who are still eligible are not listed. If nobody is ineligible, show "All students in this stage are eligible".
- **"Download report"** gives an Excel of the same list: formula-safe, an IST timestamp footer, admin only, audited as `stage.reconcile_report`.
- **Action:** checkboxes (all ticked by default) plus **"Mark Selected Students As Rejected"**, behind a confirm step that shows the count.
  - On confirm, for each ticked student, write a **published `rejected`** result for this stage, with the remark **"No longer eligible: &lt;reasons&gt;"**.
  - Then send the existing regret mail through `MailDispatchService::sendBulk` (BCC batches). Never mail anyone twice: dispatch by the written row ids, as D92/F-008 do.
- **Guards:**
  - Only the admin can run it. Students and companies get 403/404.
  - Only students in the stage's pool can be rejected. Refuse rows that are already published as rejected, students with an offer on this job profile, and withdrawn applications.
  - The stage's structure rules still hold.
- **Scope:** this is the only place where D103(d) ("applicants keep their applications when eligibility changes") is overridden, and only when the admin explicitly runs it (owner B2-11). Record it as a decision.
- **Audit:** `stage.reconcile` with before and after (application ids, reasons). The rows show in the job profile's Activity and Communication Log (`email_logs.job_posting_id`, `kind = 'reconcile_regret'`).
- **Tests:**
  - only truly ineligible students are listed, and their reasons equal `check()`;
  - a student who became ineligible because of a block is listed;
  - confirming writes published `rejected` rows and one BCC regret batch;
  - running it twice writes and mails nothing new;
  - an offer holder is never rejected;
  - the Excel is formula-safe;
  - student and company get 403/404;
  - audit rows are written.
- **Browser check:** create an ineligible student on QA data (e.g. lower their CGPA through the academic update), reconcile, then restore the data.

### M1. Survey edits can lose student answers
- **Cause:** until the first response arrives, every admin save, including the Audience tab Save, deletes and recreates all question rows with new ids. A student who already has the form open then submits under stale ids. Those answers are silently dropped and the student is still told "submitted".
- **Code:** `AdminSurveyController.php:129-153`, `SurveyAnswerService.php:40-45`, `app/admin/surveys/[id]/page.jsx:317-326`.
- **Fix:**
  1. Saving the template **updates questions by id**: update existing ones, create new ones, delete only the ones the admin removed.
  2. The Audience tab saves **settings only** and never re-sends the questions.
  3. On submit, **reject any answer key that is not a question of this survey** with 422 "This survey was updated — please reload it". The student page shows that message and a Reload button.
  4. A mandatory-question error is shown next to the question.
- **Probe:** S7 P26 must pass. Add a test: an answer for a deleted question gives 422, not an empty 201.

### M2. A company can control a job profile's Allowed Student Categories
- **Cause:** the eligibility snapshot copies `allowedStudentCategories` from the company-written `form_data`, and that beats the CDC's choice. The eligible-only mail and the preview count then follow the company's value.
- **Code:** `AdminPostingController.php:118-121, 212-214, 697-702` and the `JobPosting::eligibilityRules()` fallback.
- **Fix:**
  - Never read `allowedStudentCategories` from `form_data`. Set it only from the admin's validated input: the open dialog, Edit eligibility through `PostingEligibilityService`, and Add New Job.
  - Strip the key from company `form_data` on company store, update and autosave, as `admin_remarks` is stripped (D4).
  - Clean up existing snapshots with a one-off command or migration: drop the key from any posting whose value did not come from an admin. When you can't tell, use the audit log. Record the rule.
- **Probe:** S0/S8 F1 (2 probes) must pass. Add a test that a company POST or PUT carrying the key is ignored.

### M3. A student's older notices disappear; surveys have the same pattern
- **Cause:** the student lists take the newest 300 notices (and 200 surveys) across **all** audiences, then filter to the student, with one query per item.
- **Code:** `StudentNoticeController.php:23-30`, `StudentSurveyController.php:30-36`.
- **Fix:**
  - Filter by the student's audience **in SQL**, using `AudienceService` / the audience tables with an `exists` subquery, and paginate (20 per page, `meta` like other lists).
  - The unread count is computed in SQL over all of the student's notices.
  - No per-item queries.
- **Probe:** S7 P29 must pass. Add a test with 350 notices for other audiences plus 1 old notice for the student: it is listed and counted as unread.

### M4. A cancelled or closed job profile still shows "Scheduled to Open"
- **Cause:** `lib/format.js:84` checks `is_scheduled` before status, and `JobPosting::isScheduled()` ignores status.
- **Fix:** `is_scheduled` is true only when the status is `open` and the opening time is in the future. Apply that in the model and in the label helper.
- **Probe:** S5/S6 F-1 must pass.

### M5. The placement enrolled list's "Download as Excel" ignores filters
- **Fix:** the export endpoint for a placement's students accepts the same filters as the enrolled list (`StudentDirectoryFilters`), and the button passes the current filters. The default columns are unchanged. Audited with the filters in `after`.
- Also **keep the enrolled-list filters in the URL**, like `/admin/students` (this reverses the D111 choice; record it).
- **Probe:** S3/S4 F-1 must pass.

### M6. Student quick-view drawer missing from the lists
- Wire the existing quick-view drawer into:
  - **Progress Grid**, **Shortlist for Offer**, **Applicants**, **Eligible**, and the stage shortlist page (if missing).
- Clicking a student's name opens the drawer.
- Keep a link to the full student page inside the drawer.
- Admin only; no new data in company views.

---

## PART D: Low fixes (do all; each is small)

**Renames left over** (labels only, export headers untouched):
- L1. "10th % / 12th %" → **Class X Percentage / Class XII Percentage** in the admin student dialog (`studentformdialog.jsx:171-172`) and in the JNF/INF review and form screens. In the company wizard, change only the visible label.
- L2. "Graduating Batch" → **Passout Batch** on the company dashboard (`app/company/page.tsx:337, 469`) and in the JNF/INF forms (label only; `graduatingBatch` stays).
- L3. The student My Resumes chip "Approved" → **Verified** (`app/student/resumes/page.jsx:186`).
- L4. The student suspended screen "Sign out" → **Logout** (`studentshell.jsx:213`).
- L5. "floated to students" → "opened for applications" in the company API messages (`CompanyJnfController.php:219`, `CompanyInfController.php:219`). Check that no test asserts the old text, or update it.
- L6. "drives"/"rounds" → "job profiles"/"stages" in the login page copy.

**Pipeline and offers:**
- L7. The offer edit API must **not** re-apply blocking unless the request explicitly asks for it (`reapply_blocking: true`). A manually lifted block must never come back (D91). The dialog sends the flag only when the admin confirms the blocking change.
- L8. Use one definition of "M candidates" on the Progress Grid and on the stage page: the pool, with outside-pool rows counted separately as "+N outside pool".
- L9. In Bulk Shortlist / Reject / Hold, a student entered twice (once by roll number, once by email) is merged and reported as a duplicate.
- L10. Make email matching case-insensitive in code (lower-case both sides), not only through MySQL collation.
- L11. The custom-template "Download Current Shortlist" gets the same "Downloaded on … IST" footer and title rows as the default one.
- L12. Template stage columns keep the `[addendum]` marker, as the default admin export does.

**Reports and settings:**
- L13. The Placement Matrix report excludes `ppo_offered`, per D80, like the other reports.
- L14. Admin hub: the Branch Manager and Excel Templates tabs render their real content inside the hub, not as link-out cards. Remove the duplicate Branch Manager drawer entry; the old URL redirects to the hub tab.

**Invitations and job profiles:**
- L15. "Resend to all pending": implement the owner's answer from Part B.
- L16. Applying to a scheduled job profile that has not opened yet returns **404**, the same as its detail page. Add the scheduled check to `StudentApplicationController::isVisible`.
- L17. The student job profile card and the company detail page use the shared status-label helper, so they show **Closed For Applications** before any stage is published.
- L18. Import: map Superset's **"Current Course Name"** to programme and branch through `ProgrammeCatalogue::resolve`. Rows that can't be resolved are reported with a clear reason.
- L19. The shortlist-proposal mail to admins sets `job_posting_id` and `kind` on its `email_logs` row (`StakeholderNotifier::notifyAdmins`), so it appears in the Communication Log.

**Surveys and notices:**
- L20. Survey file-upload validation errors are shown under the matching question (`studentUpload` must keep the per-field errors).
- L21. With "Allow multiple submission" off, prevent duplicate first submissions in the database. Add a unique index in a **new** migration, or lock inside a transaction, and handle the conflict as a clean 409.
- L22. The survey Excel export adds a "Non-responders" sheet: roll number, name, branch, for the target audience.
- L23. Notice and stage-email attachments: don't delete a file that queued emails still reference (delete it after sending, or keep it until the batches finish). Clean up `stage-emails/` files after the send completes.
- L24. Check that an uploaded file's real type matches its extension (a PDF named `x.png` is refused).

**UI polish:**
- L25. The Job Profiles list shows a loading state, not "0 job profile(s)", while it loads. Check the other new list pages too.
- L26. The survey builder warns before leaving the page with unsaved changes. The Excel template editor flushes a pending autosave before leaving, and shows "Saving…" until the save is confirmed.
- L27. Replace the native `window.confirm` on survey Delete with the app's MUI confirm dialog, matching the rest of the app.
- L28. Search treats `%` and `_` literally (escape them) in the student directory, the student category member search and the other new `like` searches.
- L29. Remove the unused `Image` imports in `adminshell.tsx` and `companyshell.tsx`.

**Owner-visible notes (record only, no change unless the owner asks):**
- The stage page has one **"Send Email to Shortlisted / On Hold"** button with an audience choice, while Superset has separate buttons. Keep it as is and record the decision.
- "Attached Resume" is marked company-safe in the field catalogue but is unused, because companies get no custom templates. Mark it admin-only for now.

---

## PART E: Definition of done

- Every H/M/L item above is ticked in the progress file, with file:line and test names.
- **All 128 ParityVerify probes pass.**
- The full suite fails only the 15 baseline tests listed in `SUPERSET_PARITY_PROGRESS.md`, plus nothing new.
- `php artisan migrate:fresh --seed` plus `Phase2DemoSeeder` succeed on a scratch MySQL database.
- `npm run lint` has 0 errors and 0 new warnings (the unused imports are gone). `npm run build` passes.
- Browser check at 375, 1024, 1280 and 1440 px for H1, M1, M3, M5, M6, L14, L25–L27, with screenshots. No console errors and no backend log errors.
- Decisions recorded (D119+). `CDC_PORTAL_CONTEXT.md` updated where behaviour changed (Reconcile, survey save, notices pagination).
- **Report to the owner in chat:** what was fixed, the test results, anything blocked, and the next action. **Do not commit.**
