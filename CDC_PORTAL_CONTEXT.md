# CDC Placement Portal: Full Project Context

This file gathers everything needed to continue work on the IIT (ISM) Dhanbad CDC Placement Portal in a new place (a Claude Project, a new chat or a new developer). It covers:
- what the product is and why it exists;
- every product decision the owner has made;
- the business rules the system enforces;
- how the code is organised and how to run it;
- current status, open items and the owner's working rules;
- the style the owner wants for documents written for the professor.

Last updated: 2026-10-07 (Superset parity round and its verification fixes, D105–D125).

---

## 1. Project identity

- **Product:** Training and Placement Portal for the Career Development Centre (CDC), IIT (ISM) Dhanbad.
- **Owner account:** CDC IIT (ISM) Dhanbad, `placementportaliitism@gmail.com`. All git commits are authored by this account.
- **Repository root (local):** `/Users/admin/Desktop/CDC-main`.
  - The working application is in `CDC/` only.
  - Backend: `CDC/backend` (Laravel).
  - Frontend: `CDC/frontend` (Next.js).
  - Everything else at the repo root is reference material or unrelated: `conclave/` (event microsite), `CDC/ml/`, the PDFs, `_xfer`.
- **Reviewer:** the owner's professor. They review documents such as the requirement analysis and want product decisions, not implementation details.
- **Reference product:** Superset (joinsuperset.com), the commercial campus placement platform used by many Indian institutes. The student side was designed as "Superset-inspired with our own UI taste and CDC rules".

---

## 2. What the product does

### 2.1 Original portal (company side, built first)
- **Recruiter self-registration** with work-email verification by link, then a company profile with a logo upload.
- **JNF and INF forms.** Recruiters fill a Job Notification Form (JNF, full-time) and an Internship Notification Form (INF):
  - 7-tab wizards with autosave, duplicate and delete;
  - the full form is stored as one JSON blob, `form_data`;
  - it holds the job description, eligibility (programme, branch, CGPA cut-off, backlog policy, gender, batch), selection rounds, programme-wise salary or stipend, and declarations.
- **CDC admin review:**
  - mark under review, add internal notes, grant the company edit access, edit fields directly;
  - accept or reject;
  - download an accepted form as a one-row CSV.
- **Admins also manage:**
  - companies;
  - the programme and branch catalogue (8 programmes, 52 built-in branches, plus custom branches);
  - policy documents shown inside the forms;
  - other admins (super admin only);
  - notifications;
  - alumni outreach submissions from a public form at `/alumni`.

### 2.2 Student placement module (built second)

It adds everything after a company form is accepted:
- placement cycles and enrolment;
- student accounts with roll-number login and bulk import;
- up to 8 resumes per student, with CDC verification;
- one eligibility engine;
- "floating" an accepted form as a drive into a cycle;
- the student job board and applications with questions;
- the multi-round selection pipeline: attendance, draft and published results, waitlist, addendum, re-add;
- company proposals;
- the results console with offers and placement blocking;
- the placed-elsewhere protocol;
- events and a calendar;
- Excel exports;
- student dashboard and admin analytics;
- a full audit log;
- settings for the mail mode.

### 2.3 Three roles (plus super admin)
- **Admin (CDC staff):**
  - "Admin is god": every capability is ultimately admin-controlled and every automatic decision can be overridden.
  - Every admin write action is audit-logged with who did it, the action, the before and after values, and the IP address.
  - A super admin can also manage admin accounts.
- **Student:**
  - created only by the CDC, with no self sign-up; logs in with their **roll number**;
  - can edit only personal email, phone, home state, LinkedIn, GitHub and photo;
  - academic fields are locked: CGPA, backlogs, programme, branch, batch, marks.
- **Company recruiter:**
  - registers through the existing flow and works only with their own accepted and floated forms;
  - sees applicants, exports, proposes shortlists, waitlists, addenda and replacements, and sees published outcomes;
  - never publishes anything to students.

---

## 3. Owner's product decisions

These come from the owner's answered questionnaire, saved in the repo as `SETUP_GUIDE.md` (despite the name it is the requirements questionnaire), and from later chat decisions.

### Foundation
- Fresh MySQL database `iitism_placement`. Old SQLite data was test data and was discarded.
- New frontend code is JavaScript (`.js`/`.jsx`); existing `.tsx` files stay TypeScript.
- **Placement cycles** were adopted as the backbone, as in Superset.
- The original security holes were fixed while building the student side:
  - the public admin-register route;
  - companies setting their own status or admin remarks;
  - non-expiring tokens;
  - the open PDF proxy.
- **Mail:**
  - The admin picks queued or immediate sending in the Settings page, not through `.env`.
  - Up to about 3,000 students are in a cycle at most.
  - Emails go to institute addresses only and are sent instantly, with no deadline reminders and no digest.
- File storage is the local disk for now; S3 is not planned yet.

### Students
- The admin creates students one at a time or by bulk Excel/CSV, with a preview of errors before the import.
- Login username = **roll number**.
- Invitation = a set-password link (no temporary password to type). The link lasts 7 days; normal forgot-password links last 60 minutes.
- **CGPA and backlogs will auto-sync from the institute database in future.** For now the admin re-uploads them in bulk. This is built as a separate service so the sync can plug in later.
- Students can **request a branch change** (for example an integrated M.Tech or a double major); the admin decides.
- The admin can suspend or reactivate a student.
- Students keep their history across cycles, and the admin sees past-cycle data.

### Resumes
- PDF only, at most 2 MB, up to 8 per student, each labelled by the student.
- Status goes from pending to approved or rejected; a rejection needs a remark. Re-uploading resets the status to pending.
- **A student may apply with an unverified resume.** The application is flagged for the admin throughout that drive, and the student sees a banner to get it verified. The flag clears once the resume is approved.
- A resume attached to a live application is locked: it cannot be replaced or deleted.
- Verification is per resume, in a queue like the JNF review queue.

### Visibility and applying
- A separate **"float" step** comes after acceptance: the admin sets the deadline, then the drive becomes visible and the eligible students are emailed.
- **Mixed visibility:**
  - Students whose branch is eligible see the drive even when they fail another criterion (CGPA, an offer block and so on). They see the reason and the Apply button is disabled.
  - Drives that leave the student's branch out are hidden from them, unless they already applied.
- Eligibility checks programme and branch, CGPA, backlogs (numeric ongoing and total limits), gender, batch, minimum 10th and 12th percentages, blocks and debarment.
- **Application questions** are built by the admin per drive: free text, single-choice and multiple-choice; required or optional.
- Before the deadline a student can change the resume or answers, withdraw and re-apply. Everything freezes at the deadline. There is no per-cycle "no withdrawal" toggle.
- **Students never see applicant counts.** The owner was emphatic about this.
- Job alerts and preferences: later.

### Selection pipeline
- Rounds come from the form's selection rounds, and the admin can add, remove, reorder and schedule them.
- **Shortlists:**
  - A company can propose a shortlist, waitlist, addendum or replacement for its own drive, and the admin approves it.
  - The admin can also upload roll numbers directly.
  - **Only the admin publishes to students.**
- Nothing is visible to students until the admin publishes a round. On publish, selected students get a result mail and everyone else in that round gets a regret mail.
- **Waitlist (final owner instruction):**
  - A waitlist is unordered, with no ranking and no automatic promotion.
  - The admin can move **any** waitlisted student forward.
  - Both the admin and the company (through a proposal) can add or remove waitlisted students; only the admin publishes.
- **Addendum:** candidates added after a round's results are published are flagged as addendum entries.
- **Re-add:** a student rejected in a round can come back only through an admin-only "Re-add" button, with confirmation and a remark. The company is notified automatically.
- Attendance is marked by the admin only.
- Students see their own round-by-round trail.
- **Placed-elsewhere protocol:**
  - When a student gets a blocking offer, their other live applications are flagged.
  - The admin can "Remove from process", which by default notifies that company and invites it to request replacement candidates.

### Results, offers and blocking
- The admin announces the final results:
  - picks the offer type per student;
  - CTC or stipend is pre-filled from the JNF/INF for that student's programme and can be edited;
  - the system suggests a block, which the admin can override;
  - the admin can lift any block at any time.
- There is no "dream offer" rule. Students cannot decline an offer in the portal; once selected, they accept.
- **Offer types and their blocks (final rule):**
  - **Internship:** blocks further internships; the student stays eligible for full-time drives.
  - **Intern + PPO accepted:** blocks everything.
  - **PPO offered but not accepted:** no block.
  - **Full-Time:** blocks everything.
  - **Intern + Full-Time** (a new type the owner insisted on): blocks everything.
  - **Intern + performance-based PPO** (final years inside a full-time cycle): blocks further internships; the student stays eligible for all full-time drives.
- **Blocks follow the student across cycles.**
  - The owner's later decision replaced the original "per cycle only" rule.
  - A complete block applies in the offer's cycle and in every other open cycle the student is enrolled in.
  - An internships-only block applies in the offer's cycle and in every open internship cycle.
  - Blocks are carried into cycles the student joins later; a block the admin lifted does not come back.
- **Offer category at float time:** a JNF is Full-Time or Intern + Full-Time; an INF is Internship or Intern + performance-based PPO. The category pre-selects the offer type on the results console.
- Debarment is an admin-set block for the whole cycle.
- The credit-score or penalty system is skipped.

### Events, calendar, exports, dashboards
- **Events:**
  - fields are title, type (PPT, workshop, webinar, other), optional company, time, venue or meeting link, and description;
  - only title, type and time are required;
  - the audience is all students, chosen programmes and branches, or the applicants of a drive;
  - announce only, with no RSVP.
- **Calendar:** for both admin and students, showing deadlines, events and scheduled rounds. Students see only their own relevant items.
- **Exports:**
  - Excel with a **signed resume link** column (works without login, expires after 30 days), instead of a ZIP of resumes.
  - The admin can export everything.
  - A company can export its own drives at any time, limited to roll number, name, academics, answers, published outcomes and the resume link.
  - Contact details go to a company only if the admin enables "share contact details" for that drive.
  - The original one-row CSV of a JNF/INF is kept.
- **Admin dashboard/analytics** (owner gave a free hand):
  - per-cycle and all-cycle views;
  - totals by programme, branch, batch and gender;
  - placed and unplaced counts, with the percentage over **all enrolled students**;
  - drives completed and ongoing, offers by type;
  - highest, average, median and lowest CTC, and stipend statistics;
  - a branch-wise table, applications over time and top recruiters.
- **Student dashboard:** status cards, applications with their trail, a **nudge for eligible drives not yet applied to**, and upcoming items.
- A season report PDF is planned for later.

### UI
- **Student UI:** a Superset-style **left sidebar** (a drawer on mobile) with maroon as the primary colour. It must be polished and fully responsive, because students mostly use phones.
- Student pages: Dashboard, Job Profiles, My Applications, My Resumes, Events, Calendar, Notifications, Profile.
- **Admin top bar** (from 1024 px):
  - shows Dashboard, Cycles, Postings, Students, JNF Reviews, INF Reviews, More, the bell and Sign Out;
  - More opens a drawer grouped into Placement, Company forms and Administration;
  - below 1024 px a menu button replaces the bar.
- **Landing page (`app/page.tsx`):**
  - the header has **Student Login** first, then Recruiter Login and the other buttons;
  - the mobile drawer and the footer quick links also include Student Login;
  - the header collapses to a menu below 1200 px.
- All admin date-times are entered and shown in **IST** and stored in UTC.
- Global justified text was removed; text is left-aligned.
- A demo seeder with realistic data was requested and built.

### Bulk mail (owner request)
- New openings, event announcements and round results are sent as **one message per batch of 100 students, with the students in BCC**. The To address is the portal's own address.
- No student sees another student's address. The batch size is set by `MAIL_BULK_BATCH_SIZE`.
- Personal messages (invitations, offers, receipts, resume and profile notices) are sent one by one.

### Changing eligibility after a drive is published (owner decision 2026-10-06, built as D103)
- The CDC can change a drive's eligibility while it is open or in process, from **Edit eligibility** on the admin posting page or through the admin JNF/INF editor. Completed and cancelled drives cannot be changed.
- Before saving, the CDC sees how many students become eligible, how many stop being eligible, and which current applicants would no longer be eligible, with the reason for each.
- The drive and its JNF/INF are updated together, and every change is audit-logged with the old and new criteria.
- Students who already applied keep their applications and can still view, edit or withdraw them. New applications follow the new criteria.
- When eligibility changes, the CDC can reopen applications (or move the deadline) until a chosen time, so everyone eligible under the new criteria can apply. This is the default when applications were already closed. It is not possible once any results have been entered or when the cycle is closed (D104).
- "Email newly eligible students" is on by default: the usual new-opening mail goes only to students who were not eligible before and are now, while applications are open. When applications are reopened, it goes to every eligible student who was never told about the drive. Applicants and anyone already told are never mailed again.
- The job board, drive page, hidden-branch rule, calendar, dashboard nudge, admin Eligible tab and counts all follow the new criteria automatically.

### Superset parity verification fixes (2026-10-07, D119–D125)
- **Reconcile Ineligible Students** (owner decision B2-11): on each stage's shortlist page the CDC can re-check that stage's students against the job profile's current eligibility, blocks and debarments, see the reasons (also as Excel), and mark the chosen ones as rejected in that stage with the regret mail. It is the only place where "applicants keep their applications when eligibility changes" is overridden, and only when the CDC runs it. Offer holders and students already decided in the stage are never touched.
- **Allowed Student Categories** are set only by the CDC (open dialog, Edit eligibility); a company's form can never add or change them.
- **Surveys:** saving a survey keeps each question's identity, so a student who has the form open never loses answers; if a survey changed while a student was answering, the student is asked to reload instead of having answers dropped. With "Allow multiple submission" off, a second simultaneous first response is refused.
- **Notices and surveys for students** are listed 20 per page and always include every item addressed to that student, however many other notices exist; the unread count covers all of them.
- **Offer edits:** a block the CDC lifted by hand never comes back when an offer's type is changed, unless the CDC explicitly chooses to bring it back.

---

## 4. Business rules as implemented

### Eligibility (one implementation: `app/Services/EligibilityService.php`)
A student is eligible only if all of these hold:
- they are enrolled and `active` in the drive's cycle;
- their account is active;
- they have no active block covering this drive type in that cycle, and no debarment;
- the drive's matrix selects their programme and branch;
- CGPA ≥ the branch cut-off;
- backlogs:
  - for new forms, ongoing ≤ `maxOngoingBacklogs` and total ≤ `maxTotalBacklogs` (blank means unlimited);
  - for legacy forms with only `backlogsAllowed`, false means 0 ongoing and 0 total are required;
- the gender filter is `all` or matches;
- the graduating batch matches (PhD is exempt);
- 10th and 12th percentages ≥ the minimums, if set.

Notes:
- A blank or non-numeric cut-off means no restriction.
- A missing value fails a set cut-off with "not on record".
- Values are compared at 2-decimal precision.
- `check()` returns `{eligible, reasons[]}` as student-facing sentences. `eligibleStudentsQuery()` returns the SQL audience. Tests prove the two agree.
- Every caller uses this service: the board badge, apply, the float emails, the admin eligible list and the exports.
- The rules come from the drive's `eligibility_snapshot`, set at float time. After floating, it changes only through `PostingEligibilityService` (Edit eligibility dialog and admin form editor), which writes the snapshot and the form's `form_data` together (D103).

### Drive lifecycle
- `open` → `in_process` (applications closed) → `completed` (final results) or `cancelled`.
- A closed cycle stops applications, but its pipelines continue.
- A floated form cannot return to review while its drive is active.

### Pipeline rules
- Results are entered only after applications close.
- Pool: round 1 is all live applicants; later rounds are those published as `selected` in the previous round. Entries outside the pool are allowed but warned.
- Results are drafts (`published_at` null) until published.
- Publishing can mark everyone else in the pool as not selected, so regret mails go out.
- Rounds publish in order. The final round is announced only on the Results console.
- Published rows are never overwritten; draft rows can be deleted.
- Structure freezes once results exist: no reordering after a publish, no reopening, no deleting a round that has pending proposals.
- A waitlist "Move" turns the published `waitlisted` row into a published `selected` row in the same round (remark "Promoted from waitlist"). The next round then decides that student.
- **Proposals:**
  - Only current applicants are accepted; an unknown roll number makes the whole proposal 422.
  - Proposals are allowed only after applications close.
  - Approval writes drafts only.
  - A waitlist proposal is the complete desired waitlist.
  - A completed drive still accepts replacement and addendum proposals.

### Results console
- Final results are announced only when every earlier round is completed and the final round is the last round.
- There is one offer per application. The console can be run again for later selections.
- Publishing creates offers and blocks (via `BlockingPolicy`), sets placed-elsewhere flags, completes the drive, and sends offer and regret mails.

### Visibility
- Draft results are visible to the admin only.
- Students see only their own published results.
- Companies see only published results for their own drives, and never `used_unverified_resume` or `placed_elsewhere_flag`. They see contact details only when sharing is on.
- Students never see applicant counts.

---

## 5. Emails (each is an email plus an in-app notification)

- **E1:** student invitation, with the roll number and a 7-day set-password link.
- **E2:** drive floated, sent to eligible students (BCC batches); after an eligibility change, sent only to newly eligible students who were never told about the drive.
- **E3:** application submitted, sent to the student.
- **E4:** round result. Selected and waitlisted students get a result mail; the rest of the round gets a regret mail (BCC batches).
- **E5:** final result. Selected students get an offer mail; the others get a regret mail.
- **E6:** event announced, sent to the audience (BCC batches).
- **E7:** resume approved or rejected, with the remark.
- **E8:** profile changed by an admin, or a branch change decided.
- **E9:** a student removed from a process as placed elsewhere, or a rejected student re-added. Sent to the company.
- **E10:** company proposal submitted (to admins) or decided (to the company).

All student sends go through `MailDispatchService`, which reads `mail_mode` (`queued` or `sync`) and writes `email_logs` rows (queued, then sent or failed). Production must run `php artisan queue:work`.

---

## 6. Tech stack and code map

### Stack
- **Backend:**
  - PHP 8.2 and Laravel 12, with Sanctum bearer tokens that expire after 7 days;
  - MySQL only in development and production; the tests use in-memory SQLite;
  - PhpSpreadsheet for Excel import and export;
  - a database queue.
- **Frontend:**
  - Next.js 16 App Router and React 19;
  - MUI 6 with the maroon `#7B1113` primary and navy secondary theme from `lib/theme.ts`;
  - NextAuth v5 beta (JWT session, `trustHost: true`);
  - `@mui/x-charts` 7.x;
  - React Hook Form with Yup.
  - Next 16 specifics: middleware lives in `proxy.ts`; route `params` are Promises (`use(params)`); read `node_modules/next/dist/docs/` before frontend work.
- **Conventions:**
  - business logic lives in controllers, cross-cutting logic in `app/Services`, and there are no repositories or DTOs;
  - JSON keys are snake_case;
  - success responses are `{message, <resource>}`; paginated lists are `{<resources>: [], meta: {current_page, last_page, per_page, total}}`;
  - component file names are all lowercase;
  - styling uses MUI `sx` only;
  - indentation is 2 spaces for JS and 4 for PHP.

### Key backend files (`CDC/backend/app`)
- **Services:**
  - `EligibilityService`
  - `BlockingPolicy`: the offer matrix, `targets()`, `carryForward()`, `flagLiveApplications()`
  - `PipelineService`
  - `MailDispatchService`: `send()` and `sendBulk()` for BCC batches
  - `ExportService`: formula-safe; every text value is written as an explicit string
  - `SpreadsheetImportService`
  - `StudentAcademicSyncService`: the future institute-database sync entry point
  - `StudentAccountService`
  - `AuditService`: `log()` and `logAs()`
  - `SettingsService`
  - `FileUploadService`
  - `PortalNotificationService`
  - `StakeholderNotifier`
- **Controllers:**
  - Admin: `AdminPlacementCycleController`, `AdminStudentController`, `AdminBranchChangeController`, `AdminResumeController`, `AdminPostingController`, `AdminPipelineController`, `AdminProposalController`, `AdminResultController`, `AdminBlockController`, `AdminEventController`, `AdminAnalyticsController`, `AdminAuditLogController`, `AdminSettingsController`.
  - Student: `StudentProfileController`, `StudentResumeController`, `StudentPostingController`, `StudentApplicationController`, `StudentBranchChangeController`, `StudentDashboardController`.
  - Company: `CompanyPipelineController`.
  - Shared: `CalendarController`, `EventFeedController`.
  - Original portal: `AuthController`, `CompanyAuthController`, `CompanyJnfController`, `CompanyInfController`, `AdminFormReviewController`, `AdminManagementController`, `AdminProgrammeBranchController`, `PolicyDocumentController`, `AlumniOutreachController`, `NotificationController`, and others.
- **Support:** `App\Support\ProgrammeCatalogue` (`config/programmes.php` plus custom branches), `PostingPresenter` (hand-whitelisted student payloads; the Company model is never serialised), `Ist` (IST parsing).
- **Middleware:** `EnsureUserIsActive` (alias `active`), which answers suspended users with 403 "Account suspended. Contact CDC.".
- **Rate limits:**
  - `api`: 60 requests per minute per user or IP;
  - `login`: 10 per minute per account, on top of the IP limit;
  - `signed-files`: 600 per minute per IP.

### Main tables added
- `users` gains the `student` role and `is_active`.
- `student_profiles`, `placement_cycles`, `cycle_enrollments`, `resumes`.
- `job_postings`: morphs to a JNF or INF and holds `eligibility_snapshot`, `offer_type` and `share_contact_details`.
- `posting_questions`, `posting_rounds`, `applications`.
- `application_round_results`: `published_at` null means draft; also has `is_addendum`.
- `shortlist_proposals`, `offers`.
- `placement_blocks`: scope `all` or `internships_only`; reason `offer`, `debarred` or `manual`; plus the `active` flag. Blocks are never hard-deleted.
- `events` (model `CampusEvent`), `branch_change_requests`, `portal_settings`.
- `audit_logs`: includes `actor_name` and `actor_email`.
- `student_invite_tokens`.

### Frontend
- **Student:** `app/student/*` (dashboard, postings, applications, resumes, events, calendar, notifications, profile), `components/student/studentshell.jsx`, `lib/studentapi.js`.
- **Admin pages:** `app/admin/{placement-cycles, students, branch-changes, resumes, postings, proposals, events, calendar, analytics, audit-logs, settings}`.
- **Company:** `app/company/postings` (the "Drives" nav item).
- **Shared:** `components/shared/monthcalendar.jsx`, `postingpreview.jsx`, `blockingrules.jsx`, `pageheader.jsx`, `lib/format.js`.
- **Auth:**
  - `app/auth/login/[type]/page.tsx` has a student variant with a roll-number field;
  - `app/auth/student/set-password/page.jsx`;
  - `proxy.ts` gates by role and redirects signed-in users away from `/auth/*`.

### Project documents in the repo
- `PHASE2_IMPLEMENTATION_SPEC.md` (repo root): the binding build spec, with milestones M0 to M10, schema C1 to C18 and rules B1 to B7.
- `SETUP_GUIDE.md` (repo root): the owner's answered requirements questionnaire.
- `PROJECT_CONTEXT.md` (repo root): a deep audit of the original company-side code.
- `CDC/PHASE2_PROGRESS.md`: progress and handover notes.
- `CDC/PHASE2_DECISIONS.md`: an append-only decision log, D1 to D104. **This is the source of truth for every micro-decision and owner override.**
- `CDC/QA_REPORT.md` and `CDC/qa/`: the full QA audit and re-test results.
- `CDC/SECURITY_REPORT.md` and `CDC/security/`: the security audit.
- `Requirement_Analysis_Report.docx` (repo root): the report for the professor (see section 9).

---

## 7. How to run locally

Backend (from `CDC/backend`):
```bash
composer install
cp .env.example .env    # then set DB_CONNECTION=mysql, DB_DATABASE=iitism_placement, DB credentials, MAIL_MAILER=log
php artisan key:generate
php artisan migrate:fresh --seed
php artisan db:seed --class=Phase2DemoSeeder
php artisan storage:link
php artisan serve        # http://127.0.0.1:8000
php artisan queue:work   # separate terminal, needed for queued mail
```

Frontend (from `CDC/frontend`):
```bash
npm install
npm run dev              # http://localhost:3000 (needs .env.local with NEXT_PUBLIC_API_URL, NEXTAUTH_SECRET, NEXTAUTH_URL)
```

**Demo data (local only, from `Phase2DemoSeeder`):**
- 2 cycles: FT 2026-27 and Internship 2026-27.
- 120 students:
  - B.Tech `23JE####`, M.Tech `25MT####`, MBA `25MB####`;
  - addresses `@students.cdc-demo.test`;
  - all with the password `Student@2026`;
  - they log in at `/auth/login/student` with their roll number, for example `23JE0101`.
- 3 companies: `hr@nimbus.demo`, `hr@vertex.demo` and `hr@helix.demo`, password `Company@2026`.
- A completed SDE drive with 8 offers, blocks, a waitlist and an addendum, plus open drives and events.
- The seeder sends no mail.
- Admin login comes from `ADMIN_EMAIL` / `ADMIN_PASSWORD` in the backend `.env`.

**Tests:**
- `php artisan test` (groups `qa` and `security` exist);
- `npm run lint`;
- `npm run build`;
- `npm run test:e2e:student` (Playwright).

---

## 8. Current status (2026-10-06)

- **Build:** all milestones M0 to M10 are complete. Git is clean; the latest commits are "update project" (`dabf153`, `2f91fc0`).
- **QA audit:**
  - 183 test rows across 1,008 route-by-actor cells and 36 cross-company probes, with zero wrong-actor successes.
  - Performance passed at about 2,800 students and 6,000 applications.
  - All S1, S2 and S3 findings and the owner-decided items were fixed with regression tests.
  - S4 and S5 findings stay open unless the owner approves them; the QA tests that still fail reproduce those open findings on purpose.
- **Security audit:** rating **HIGH**; Critical 0, High 1, Medium 10, Low 11, Info 13.
  - SEC-001 (High): upgrade Next.js 16.2.1 and next-auth beta.30, which have published advisories, to next ≥ 16.3.8.
  - SEC-002: the NextAuth secret is in git history and still in use. Owner rotation is in progress.
  - SEC-003 to SEC-011 (Medium):
    - account enumeration;
    - SVG logos on the public disk;
    - an open redirect through `callbackUrl`;
    - CSV formula injection in the original JNF/INF CSV;
    - the Host-header verification link;
    - login throttle spray;
    - the token exposed to page JavaScript with no CSP;
    - the public mail endpoints have no cooldown;
    - Laravel and symfony-mime advisories.
  - Launch recommendation: **safe after fixing SEC-001 and SEC-002**; SEC-003 to SEC-011 within two weeks of launch.
  - **The security fixes (Part 12) wait for the owner to reply "approved, fix".**
  - Admin-password and Gmail-password items are deliberately left out of the reports. The owner will handle them in production.
- **Recent UI change:** Student Login button added to the landing page (header, mobile drawer and footer).
- **Requirement Analysis Report** delivered (plain Word document, see section 9). The professor's review is pending.

---

## 9. Writing documents for the professor (owner's style rules)

The requirement analysis report (`Requirement_Analysis_Report.docx`) went through several revisions. Final rules:
- **Never mention "Phase 1" or "Phase 2".** Call them "the existing company side" and "the student placement module".
- **Future tense throughout** ("we will", "students will"). The professor treats the project as something being built, starting from a base model.
- **Product manager or HR tone:** decisions and reasons only. No library names, no technology details, no database tables, no architecture, security or testing sections, no file names.
- **Leave out the obvious:** no intro, definitions, problem statement, contents, out-of-scope, assumptions or conclusion, and nothing like "the portal is hosted and owned by the institute".
- **Plain look:**
  - no cover page, header or footer;
  - plain black bold headings;
  - dot bullets only, never numbered;
  - no emojis;
  - no shaded boxes or labels such as "Key decision:";
  - no bold lead-in phrases or "Label: text" patterns.
- **Highlight only by text colour:** one colour (blue `1F4E79`) for key decisions, including differences from Superset and added value.
- **It must not read as AI-generated:** simple wording and natural sentences.
- **Sections of the final report:**
  - Overview
  - User Roles
  - Comparison with Superset (what we will adopt, do differently and add)
  - Functional Requirements, by module
  - Key Policy Decisions (eligibility, offer types and restrictions, who sees what)
  - Communication
  - Delivery Plan
- **Correction from the owner:** eligibility can be changed later, so the point now reads: "Eligibility will be set when a drive is published, and the CDC will be able to change it later if needed."
- **WhatsApp message used to send the report:** "Good evening Sir, This is the requirement analysis report for the placement portal. Kindly review it and let me know if any changes are needed. Thank you."

---

## 10. Standing working rules (for any assistant or developer)

- **Do not commit** unless the owner explicitly asks; the owner pushes to GitHub themselves.
- When committing, the author is the CDC account `<placementportaliitism@gmail.com>`. Never use other personal identities, and add **no AI co-author trailer**.
- Work only inside `CDC/`. Run backend commands from `CDC/backend` and frontend commands from `CDC/frontend`. Use absolute paths, and never run `cd`-ing commands in parallel.
- MySQL only. Never edit an already-run migration; schema changes go in new migration files. Never rename existing routes, columns or response keys.
- New frontend files are `.js`/`.jsx`. Use MUI `sx` and theme tokens only.
- **Never send real mail** while testing; use `MAIL_MAILER=log`.
- **Never print or commit secrets.**
- **No package installs or downloads without the owner's permission.**
- **No live attack-style security testing.** Security review is done by reading code and config.
- Every admin write must be audit-logged.
- Record every micro-decision in `CDC/PHASE2_DECISIONS.md` (append-only, never contradict an earlier entry), and keep `CDC/PHASE2_PROGRESS.md` current.
- Verify UI changes in a browser at several widths (375, 1024, 1280 and 1440 px) before reporting them done.
- Ask the owner before any product-behaviour change. "Admin is god": never remove an admin override.

---

## 11. Future scope (not built yet)

- **Planned later:**
  - automatic CGPA and backlog sync from the institute database;
  - job alerts and preferences;
  - a season report PDF.
- **Excluded unless the owner asks:**
  - a credit-score or penalty system;
  - deadline reminder mails;
  - a per-cycle switch that forbids withdrawals;
  - S3 storage;
  - SSO;
  - student self sign-up;
  - event RSVP;
  - a dream-offer rule;
  - students declining offers.
- **Security hardening:** the SEC items above, after owner approval.
- **Deferred code refactor:** four `react-hooks/set-state-in-effect` lint suppressions in shared form components (see D33).

---

## 12. Glossary

- **CDC:** Career Development Centre.
- **JNF / INF:** Job / Internship Notification Form.
UI wording follows Superset since D105 (labels only; code, routes and database names keep the old words, shown in brackets).
- **Placement** (code: cycle, `placement_cycles`): a placement season.
- **Job Profile** (code: drive, posting, `job_postings`): an accepted JNF or INF published to students. **Open for Applications** (code: float) is the act of publishing it. Its status shows as Accepting Applications, Closed For Applications, In Process, Completed or Cancelled.
- **Stage** (code: round, `posting_rounds`): a selection step.
- **Shortlisted:** students who cleared a non-final stage; the final stage says Selected.
- **On Hold** (code: waitlist, `waitlisted`): students held in reserve at a stage, unordered.
- **Progress Grid** (code: pipeline): the all-stages grid on a job profile.
- **Shortlist for Offer** (code: results console): where final selections and offers are announced.
- **Enrolled** (code: enrolment status `active`): a student taking part in a placement.
- **Addendum:** students added after a stage was published.
- **Re-add:** bringing back a rejected student, admin only.
- **Users:** the admin accounts page (formerly Manage Admins). **Social Category:** GEN/OBC/SC/ST/EWS, kept distinct from Superset's Student Categories.
- **PPO:** Pre-Placement Offer.
- **CTC:** annual compensation.
- **Block:** a placement restriction after an offer.
- **Debarment:** a disciplinary block for a cycle.
- **Placed elsewhere:** the flag on a student's other applications after a blocking offer.
- **Signed link:** a tamper-proof expiring URL used for resumes.
- **IST:** Indian Standard Time.
