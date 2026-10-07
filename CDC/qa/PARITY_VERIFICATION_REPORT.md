# Superset Parity: Verification Report (2026-10-07)

An independent check that everything in `SUPERSET_PARITY_MASTER_PROMPT.md` (milestones S0–S8) was built, matches the Superset videos, and works.

**Method:**
- The full backend suite, frontend lint and production build.
- Five independent code reviewers, one per milestone group. Each:
  - compared the code line by line against the master prompt, the owner's answers and `superset_video_analysis/`;
  - wrote extra "try to break it" tests.
- A live click-through in the browser against the local MySQL data at 375, 1024 and 1440 px.

No application code was changed during verification.

Detailed per-area reports, with file:line evidence for every row, are in `CDC/qa/parity_verify/`: `S0_S8.md`, `S1_S2.md`, `S3_S4.md`, `S5_S6.md`, `S7.md`.

---

## 1. Verdict

**Almost everything is built and works, but it is not finished.**
- One approved feature is **missing**: Reconcile Ineligible Students.
- There are **6 medium bugs**, two of which can silently give wrong results: lost survey answers, and companies overriding student-category eligibility.

The progress file's claim "S0–S8 complete" is **not accurate** until these are fixed.

| Area | Checklist rows | Pass | Partial | Fail | Not built |
|---|---|---|---|---|---|
| S0 renames + S8 admin hub, placements, student categories, reports | 81 | 69 | 9 | 1 | 0 (3 correctly not built: Custom Fields, Resume Tags, staff roles) |
| S1 stage shortlist + S2 offers | 65 | 56 | 3 | 2 | **4 (Reconcile)** |
| S3 Excel templates + S4 students directory and page | 55 | 50 | 5 | 0 | 0 |
| S5 invitations + S6 job profile management | 50 | 46 | 4 | 0 | 0 |
| S7 notices, stage emails, surveys | 42 | 40 | 1 | 1 | 0 |
| **Total** | **293** | **261** | **22** | **4** | **4** |

**Automated checks:**
- **Backend suite:** 478 tests, 15 failures. These are exactly the 15 failures that existed before this work, all deliberate reproductions of earlier QA and security findings, so nothing previously passing broke.
- **Permission matrix:** it walks every API route, including all the new ones, and found no new violation. It shows only the 6 known rows from before.
- **Frontend:** `npm run lint` gives 0 errors; `npm run build` passes.
- **Reviewer probe tests:** 128 new tests in `CDC/backend/tests/Feature/ParityVerify/`, of which **113 pass**. The 15 that fail are the bugs listed below. They will turn green when each bug is fixed.

---

## 2. Must fix

### High
- **H1. Reconcile Ineligible Students was never built.** The owner approved it (B2-11), but there is no route, service, button or screen; only an unrelated internal helper uses the word. It needs:
  - an explicit per-stage admin action that re-checks the stage's pool with `EligibilityService` and blocks;
  - a reasons report (on screen and as Excel);
  - "Mark selected students as rejected", with a regret mail, audited.

### Medium
- **M1. Survey edits can silently lose student answers.** Until the first response arrives, every admin save, including the Audience tab's Save, deletes and recreates all question rows with new ids.
  - A student who already had the survey open submits under the old ids. The answers are dropped, yet they see "Your response has been submitted".
  - Code: `AdminSurveyController.php:129-153`, `SurveyAnswerService.php:40-45`, `app/admin/surveys/[id]/page.jsx:317-326`.
  - Fix: update questions by id, and reject answer keys that are not questions of this survey.
- **M2. A company can set or override a job profile's Allowed Student Categories.** The eligibility snapshot copies `allowedStudentCategories` from the company-written form data, and that value beats the CDC's choice in the open dialog. The eligible-only mail and the preview count follow the company's value.
  - Code: `AdminPostingController.php:118-121, 212-214, 697-702`.
  - Fix: take that key only from the admin's input, in `snapshot()` and in the `JobPosting::eligibilityRules()` fallback.
- **M3. Older notices disappear from a student's Notices page.** The list takes the newest 300 published notices across all audiences and filters to the student afterwards, so a student's own older notice and unread count can vanish. Surveys has the same pattern with 200. It also runs one query per notice.
  - Code: `StudentNoticeController.php:23-30`, `StudentSurveyController.php:30-36`.
- **M4. A cancelled or closed job profile that had been scheduled still shows "Scheduled to Open".** `lib/format.js:84` checks `is_scheduled` before status. The scheduler itself correctly never opens it or mails anyone.
- **M5. "Download as Excel" on the placement's enrolled list ignores the filters** and exports every enrolment. The Students page download does respect filters.
- **M6. The student quick-view drawer is not wired into** the Progress Grid, Shortlist for Offer, Applicants or Eligible lists; they only link to the full page.

---

## 3. Should fix (low)

- **Renames left over:**
  - "10th % / 12th %" in the admin student dialog and the JNF/INF review and form screens;
  - "Graduating Batch" on the company dashboard and in the forms;
  - "Approved" chip on the student's My Resumes page (should be "Verified");
  - "Sign out" on the student suspended screen;
  - "floated to students" in two company API messages;
  - "drives"/"rounds" in the login copy.
- **The offer edit API re-applies blocking by default.** Blocks an admin lifted by hand come back unless the request says otherwise; only the dialog guards against this.
- **Different candidate counts:** "M candidates" differs between the Progress Grid (pool only) and the stage page (pool plus outside-pool rows).
- **Bulk entry by roll and email:** the same student entered once by roll number and once by email is written twice and not reported.
- **Email identifier matching** only works case-insensitively on MySQL (fails on SQLite tests).
- **Custom-template shortlist download** has no "Downloaded on … IST" footer.
- **Template stage columns** drop the `[addendum]` marker.
- **Placement Matrix report** counts "PPO offered, not accepted" offers.
- **Admin settings hub:** the Branch Manager and Excel Templates tabs are only link-out cards, and Branch Manager is still also in the drawer.
- **Applying to a scheduled job profile** before it opens returns 422 "placement closed" instead of 404, which confirms that it exists.
- **Status labels:** the student card and the company detail page say "In Process" before any stage is published; they don't use the shared status helper.
- **"Resend to all pending"** also re-invites Revoked students, and the dialog doesn't say so. Needs an owner decision.
- **Superset's own sample CSV** has no Programme/Branch columns, so every row fails. The rows are reported, not dropped. Map "Current Course Name".
- **Shortlist proposal mail** to admins is missing from the job profile's Communication Log, because `job_posting_id` is not set.
- **Surveys:**
  - file-upload validation errors show as one general message, not under each question;
  - two simultaneous first submissions can both save when multiple submission is off;
  - the export lists responders only, not non-responders.
- **Notices:** deleting a notice attachment while its emails are queued makes those batches fail; stage-email attachments are never cleaned up.
- **Template editor autosave:** edits from the last 700 ms can be lost when leaving the page.
- **Search wildcards:** search treats `%` and `_` as wildcards. This predates the work.
- **UI, from the live check:**
  - the Job Profiles list shows "0 job profile(s)" for a moment while loading;
  - leaving the survey builder discards unsaved questions without a warning;
  - Delete uses the browser's native confirm box, unlike the rest of the app's dialogs.
- **Lint:** two unused `Image` imports (`adminshell.tsx`, `companyshell.tsx`).

---

## 4. Confirmed working

**Rules that must always hold:**
- Drafts are invisible to students and companies until published.
- Published results are never overwritten.
- Stages publish in order, and the final stage is published only from Shortlist for Offer.
- Regret mails go out once, in BCC batches.
- Companies never see the placed-elsewhere or unverified-resume flags.
- Every new admin route returns 403/404 to students and companies and 401 to guests.
- Every admin write is audited.
- No route, column, enum value or response key was renamed, no old migration was edited, and the Excel export headers are identical to before.

**Feature by feature:**
- **S0:** the approved renames are in place in the main screens. The admin top bar fits on one line at 1024 px (measured in the browser). No sideways scroll at 375 px.
- **S1:** the stage page has the following, and per-row actions write drafts only (tested live and undone):
  - "N selected out of M candidates";
  - previous/next stage arrows;
  - per-row Shortlist / Reject / Hold;
  - Bulk Shortlist / Reject / Hold with the strict toggle;
  - Download Current Shortlist.

  The Progress Grid has the overall status column, legend, Passed/Failed chips and the 🏆 placed marker. The "process complete" card works.
- **S2:**
  - **Editing an offer:** changing Full-Time to "PPO offered" lifts the blocks and placed-elsewhere flags in every cycle.
  - **Revoking an offer:** this lifts only that offer's blocks.
  - **Upload CTCs:** the dry run writes nothing.
- **S3:** templates control the columns and their order. A company download drops admin-only and contact fields even if a template lists them. `=HYPERLINK(...)` is written as text. The Eligible List is admin only.
- **S4:**
  - **Filters:** all 31 filter cases return exactly the right students. Live: the Placed filter showed 14, and the database has 14.
  - **Notes:** never appear in any student or company response.
  - **Academic extras:** students cannot change any of the 11 extra academic fields.
  - **Suspended enrolment:** suspending a student's enrolment makes them ineligible, with the reason shown.
  - **Student page:** the summary card, Placements per cycle (Placed at … / Enrolled), Resumes & Documents, Notes, Activity and the report buttons are present.
- **S5:**
  - **Revoking invitations:** refused for activated accounts. Revoked and older reset links stop working.
  - **Resending:** a bulk resend never mails accepted students.
  - **Imports and email domain:** a 6,000-row import accounts for every row. The demo domain is rejected outside local and testing setups.
- **S6:**
  - a scheduled opening opens and mails exactly once, even if the scheduler runs three times;
  - attached documents are protected from guests, companies and other branches;
  - an admin-created job behaves exactly like a company JNF;
  - Send Applicant List sends only the company-safe file.
- **S7:**
  - **Mail and visibility:** broadcasts go in BCC with no student in To, and rich text is never shown as raw HTML. Students outside the audience get 404; companies get 403/404.
  - **Answer rules:** required answers and allowed options are enforced on the server. Uploads are limited to 5 MB PDF/JPG/PNG and stored privately. Edits after the deadline are refused.
  - **Delete, clone and PPO consent:** deleting a published survey archives it and keeps the responses. Clone copies no responses. A PPO consent answer never changes an offer or block.
  - **Live:** the builder (11 question types) and the Audience tab match Superset's screens.
- **S8:**
  - Admin hub: Account logo and name, Users (super admin only).
  - Placements: search, Draft and "Publish placement", Recently Visited.
  - Student Categories: eligibility checks agree in both code paths.
  - Reports: the CDC "placed" rule and INR-only money figures are followed, and the files are formula-safe.

---

## 5. Notes for the owner

- **Probe tests:** the reviewers' tests in `CDC/backend/tests/Feature/ParityVerify/` are deliberately left failing where they found a bug. Keep them; each turns green once its bug is fixed.
- **Not verified by eye:** the student pages (Notices, Surveys) were checked through the API, not with a student login in the browser, because the browser holds the admin session.
- **Data restored:** the live check created one draft shortlist row and one draft survey, and both were removed afterwards.
- **Nothing is committed.**
