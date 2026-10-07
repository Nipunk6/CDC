# MASTER PROMPT: Superset parity for the IIT (ISM) CDC Placement Portal

Paste this file (or point Claude Code at it) to start the work. It is the binding instruction file for adding the Superset features we do not have yet. It was written after watching the CDC's nine Superset training videos frame by frame and checking every feature against our code on 2026-10-06.

---

## PART A: How you must work

### A1. Read first, every session
1. `CDC_PORTAL_CONTEXT.md` (repo root). This is the full product context, the owner's decisions and the standing rules.
2. `CDC/PHASE2_DECISIONS.md`: every micro-decision, D1 to D104. Never contradict an entry; add new ones from **D105** upward.
3. `superset_video_analysis/01…09_*.md`: one analysis per video. Each contains a timestamped walkthrough, every Superset label exactly as shown on screen, our status with file:line evidence, and a rename table. **When this prompt says "use Superset's labels", copy them from these files. Never invent wording.**
4. This file's Part B (the owner's recorded answers), then the milestone you are on.
5. Create `CDC/SUPERSET_PARITY_PROGRESS.md` on the first session, using the same format as `PHASE2_PROGRESS.md`: CURRENT STATE, BLOCKED / QUESTIONS FOR OWNER, MILESTONE CHECKLIST, CHANGELOG. Update it after every task and before you stop. Its `NEXT ACTION` line must let a fresh session continue with no other context.

### A2. Standing rules (from the owner, non-negotiable)
- **No commits** unless the owner says so in chat. When committing, the author is the CDC account `<placementportaliitism@gmail.com>`, with no AI co-author trailer.
- Work only inside `CDC/`. Backend commands run from `CDC/backend`, frontend commands from `CDC/frontend`. Use absolute paths and never run `cd`-ing commands in parallel.
- **MySQL only.** New schema goes in **new** migration files; never edit a migration that has already run. Never rename existing routes, DB columns, enum values, JSON response keys or audit action names. **Renames are UI labels only.**
- New frontend files are `.js`/`.jsx` and use MUI `sx` with theme tokens. Existing `.tsx` files stay TypeScript and get minimal edits.
- **Every admin write is audit-logged** through `AuditService`, with before and after values.
- **Mail:**
  - Use `MAIL_MAILER=log` while testing; never send real mail.
  - Every student-facing bulk send goes through `MailDispatchService::sendBulk` (BCC batches, institute addresses only).
  - Personal mails go through `MailDispatchService::send`.
- **IST everywhere.** Admin date-times are entered and shown in IST (`App\Support\Ist`, `toLocalInput`/`fromLocalInput`) and stored in UTC.
- **Exports stay formula-safe.** Write every text cell and header with `setCellValueExplicit(..., TYPE_STRING)`, as `ExportService` already does (D87/D88).
- **Company data limits:**
  - companies see only their own job profiles and only published outcomes;
  - they never see `used_unverified_resume` or `placed_elsewhere_flag`;
  - contact details reach them only when `share_contact_details` is on.
- **Students never see applicant counts.**
- **"Admin is god":** never remove an admin override.
- **Installs:** no package installs or downloads without the owner's OK. If a milestone needs a package, stop and ask in BLOCKED.
- **Security testing:** no live attack-style testing. Never print or commit secrets.
- **Browser checks:** verify every UI change in the browser at 375, 1024, 1280 and 1440 px, using the seeded demo data (`Phase2DemoSeeder`), before calling it done.

### A3. Verify before you build (mandatory for every item)
The gap list below was correct on 2026-10-06, but code changes. **Before starting any item, grep and read the relevant code** and confirm the item is still missing or partial. If it already exists, mark it "already built" in the progress file with file:line evidence, and move on. Never build a second implementation of anything. In particular:
- eligibility logic is `EligibilityService`;
- blocking is `BlockingPolicy`;
- pipeline rules are `PipelineService`;
- mail is `MailDispatchService`;
- exports are `ExportService`;
- spreadsheet reading is `SpreadsheetImportService`;
- eligibility edits after opening are `PostingEligibilityService` (D103/D104).

### A4. Definition of done (per milestone)
- All tasks are ticked in the progress file.
- `php artisan migrate:fresh --seed` succeeds on MySQL, and `Phase2DemoSeeder` still runs. Extend the seeder so every new screen has demo data.
- `php artisan test` passes. Each new feature has feature tests, including permission tests: student and company get 403/404 on admin routes, and companies are always scoped to their own data.
- `npm run lint` (0 errors) and `npm run build` pass.
- Browser check done at the four widths, plus screenshots. Existing flows still work: company login and JNF, admin review queue, opening a job profile, publishing a stage, announcing results.
- Decisions recorded (D105+) and the progress file updated.

---

## PART B: Owner answers (recorded 2026-10-06; binding)

The owner has answered every question. **Do not ask these again.** On the first session, record them in `CDC/PHASE2_DECISIONS.md` as D105 (renames) and D106 (product decisions), then build. Ask the owner only about things this file does not answer, using the BLOCKED section of the progress file.

### B1. Renames: UI labels only. Routes, DB columns, enum values, JSON keys and audit action names stay unchanged.

**Rename all of these (milestone S0):**

| Our label (where) | New label (Superset) |
|---|---|
| "Postings" / "Job Postings" (admin nav, list title, cycle tab, card stat); "Drives" (company nav) | **Job Profiles** |
| "Float to Students" / "Float Posting" / "Floated to students" (`floatdialog.jsx`) | **Open Profile for Applications** / **Open for Applications** / **Open For Applications** |
| Status chip "Open" / student "Applications closed" | **Accepting Applications** / **Closed For Applications** (display only; `open`/`in_process` stay) |
| "Application questions" / tab "Questions" / "Add question" / "Required" / "Type" / "Question N" | **Additional Questions** / **Add Additional Question** / **This question is mandatory** / **Answer Type** / **Question Title** |
| Question types "Text answer / Choose one / Choose many" | **Text Answer / Multiple options, single answer / Multiple options, multiple answers** |
| Enrolment status "active" / "Active" | **Enrolled** |
| "Export" (job profile page, admin and company) | **Download Applicants** |
| "Export Students" (placement page) | **Download as Excel** |
| "Manage Admins" / "Add Admin" / "Add New Admin" / "Email Address" / "Delete" | **Users** / **+ Add User** / **Add User** / **Email ID** / **Delete this User** |
| "Portal Settings" (settings page title) | **Admin** (nav entry stays "Settings") |
| "Graduating batch" | **Passout Batch** |
| "Roll no" (lists) | **Roll Number** |
| "Phone" | **Mobile No.** (lists) / **Contact No.** (profile) |
| "10th % / 12th %" (screens) | **Class X Percentage / Class XII Percentage** |
| Resume "Approve" / status "Approved" | **Mark as verified** / **Verified** |
| Branch change "Approve / Reject" | **Accept Changes / Reject Changes** |
| "Applied (N)" | **N Applicants** |
| "Eligible students / Applied" stat cards | **Application Progress: X applied out of Y eligible** |
| "Results & Offers" / "Final selections" | **Shortlist for Offer** |
| "Compensation" / "CTC / year (INR)" | **CTC Offered** + **CTC Interval** (YEAR/MONTH) |
| "Publish round" / "Publish & notify" | **Publish Shortlist to Students** / **Continue** |
| "Enter results (paste / upload)" / "Mark pasted roll numbers as" | **Bulk Shortlist / Reject / Hold** / **Mark students as** |
| Student "Category" (GEN/OBC/SC/ST/EWS) | **Social Category** |
| "Sign Out" | **Logout** |
| "Audit trail" (student page tab) | **Activity** |
| "Cycles (n)" (student page tab) | **Placements** |
| **Cycles / Placement Cycles** (nav, page title, buttons such as "Add Placement Cycle" → "+ Add placement process") | **Placements** |
| **Rounds / Round** (tab, buttons, dialogs, column, student trail) | **Stages / Stage** ("Add round" → "Add New Stage") |
| **Waitlist / Waitlisted** (tab, chips, mails' visible text, student trail, company grid) | **On Hold** / **Hold at current stage**. The meaning is unchanged: D90/D92 rules still apply and only the words change. |
| **Selected** in non-final rounds (chips, student trail "Cleared") | **Shortlisted**; the final round keeps **Selected** |
| **Pipeline** tab | **Progress Grid** |
| Result chips on the all-stages grid: Selected / Rejected / Pending | **Passed / Failed / In Process** (admin grid only; students keep Shortlisted / Not selected wording) |

**Do NOT rename:**
- **JNF Reviews / INF Reviews** stay; do not use "Inbound Job Posts".
- **Companies** stays; do not use "CRM".
- **Suspend** stays; it is not Superset's "Freeze Student".
- **Excel export column headers stay exactly as today**, so nobody's spreadsheets break. New columns added later may use Superset wording.

**Top bar width:** "Placements" and "Job Profiles" are longer than "Cycles" and "Postings". Re-measure the admin top bar (D98) at 1024, 1100, 1280 and 1440 px. If it overflows, use the D34 mechanism (move an item into the More drawer or shorten the title) and record a decision. Update the glossary in `CDC_PORTAL_CONTEXT.md` to the new words.

### B2. Product decisions
1. **The CDC can create a job profile (and the company) directly**, without a JNF/INF: **YES** (S6.1).
2. **Staff roles and permissions** (Account Admin / Account User / Faculty Member): **LATER**. Do not build S9.
3. **Student coordinators with admin rights:** **NO**.
4. **Students entering their own academic data for CDC verification:** **NO**. The CDC imports the extra fields instead (S4.6).
5. **PPO consent surveys:** **(a)** the answer is information only, and the CDC acts manually. A survey must never change an offer or block.
6. **Who can answer public surveys:** **signed-in students only**.
7. **A published survey with responses:** **archive only, never hard-delete**. Drafts may be deleted.
8. **Editing an offer after publishing:** **YES**. It is audited, and a type change re-runs blocking. **Revoke offer:** admin only, with confirmation (S2).
9. **Revoke Invites:** **YES**. It cancels the link and is **refused for already-activated accounts** (use Suspend) (S5.4).
10. **Institute email domain** for student create, import and edit: **`iitism.ac.in`**. Put it in config so it can be extended. The demo seeder's `@students.cdc-demo.test` addresses must keep working: allow that domain only in local/testing environments, or make the seeder bypass the check, and record which you chose.
11. **Reconcile Ineligible Students:** **YES**. It is an explicit admin action per stage, with a reasons report (also as Excel) and a regret mail. It overrides D103(d) only when the admin runs it.
12. **Bulk shortlist upload:** keep the **warning by default** (D70(b)) and add an optional **"strict"** toggle.
13. **Superset extras: all NO** (do not build):
    - Reminder Rules
    - Withdrawal Options
    - Category offer limits / dream tiers
    - Mute communication to students
    - "Send applicant list to company" as a gate (the plain send action in S6.11 **is** built)
    - Resume ZIP download per stage
    - Overwriting published results
    - Placement story
    - SMS
    - AI auto-fill / TalentLens
    - Fee Payments
    - Mandatory Documents
    - Event attendance
    - Delete Student
    
    Exceptions:
    - **NOC requests: LATER**.
    - **Student Categories for eligibility (minors, double majors): YES** (S8.4).

### B3. Smaller follow-ups
- Companies see notices addressed to their job profile: **NO**.
- Company can download the Eligible List: **NO**.
- Company custom Excel templates: **NO** (later).
- Resume Tags: **LATER**. Do not build S8.7.
- Survey file-upload limit: **5 MB**.

---

## PART C: What already exists (do not rebuild)

Verified on 2026-10-06. Re-verify per A3.
- **Opening a job profile for applications.** The float flow already covers:
  - deadline (IST), change deadline, offer category, share contact details;
  - Additional Questions (text / single / multi, mandatory);
  - eligibility snapshot, plus editing eligibility after opening with the reopen option (D103/D104);
  - eligible-only E2 mail in BCC batches;
  - "applied out of eligible" counts;
  - a mandatory resume on apply.
- **Stage publishing.** Draft then publish, publishing in order, "mark everyone else in this round's pool as not selected" with regret mails, waitlist move (D92), addendum, Re-add, company proposals, results console with offers and blocks, placed-elsewhere and Remove from process.
- **Students.** Single create, bulk import with dry run, academic bulk update, suspend and reactivate, single Resend Invite (7-day link), branch-change queue, resume verification queue.
- **Cycles.** Create, enrol by paste or upload, close and reopen, cycle student export.
- **Other.** Events plus calendar, analytics, audit log, settings (mail mode), Branch Manager (custom branches; this covers Superset's "Data Addition Requests").

---

## PART D: Milestones (build in this order; each is shippable on its own)

Each item lists: **Superset name** (the label to use, already reflecting the B1 renames), the requirement, implementation notes and acceptance checks. Every product question is answered in Part B; follow those answers. Exact Superset labels, dropdown values and screen layouts are in the referenced analysis file and section.

### S0. Approved renames (Part B1)
- Change the UI labels only, across admin, company and student pages and emails' visible text.
- **Excel export headers are NOT renamed.**
- Keep routes, keys and enum values unchanged.
- Re-measure the admin top bar (D98) at 1024, 1100, 1280 and 1440 px. If "Placements" or "Job Profiles" overflow, follow the D34 mechanism (hand the item to the More drawer) and record a decision.
- Update `CDC_PORTAL_CONTEXT.md` glossary accordingly.
- **Acceptance:** grep shows no leftover old label in UI strings; the top bar has no overflow; tests pass (update assertions that check text).

### S1. Stage shortlist workspace (videos 06, 07; analysis 06 §2 F6–F16, 07 §2 F9–F13)
1. **Shortlist for &lt;Stage&gt;** page, one per round: `/admin/postings/[id]/rounds/[roundId]` (or a round picker inside the Progress Grid/Pipeline tab).
   - Shows only that round's pool: round 1 is all live applicants; later rounds are those published as selected in the previous round (`PipelineService::pool()`).
   - Header caption "&lt;this stage&gt; → &lt;next stage or FINAL OFFER&gt;", a counter "**N selected out of M candidates**", ← / → arrows to the previous and next stage, search ("Search by name or Roll"), sort, pagination.
   - Rows coloured by the current decision (draft vs published clearly different). The name opens the student quick-view drawer (S4.3).
2. **Per-row Shortlist / Reject / Hold** buttons, plus row checkboxes, "select all" and bulk actions on the selection.
   - They write **draft** results through the existing `POST /admin/postings/{p}/rounds/{r}/results` `entries[]`.
   - No new result semantics. Hold = the existing `waitlisted` result, labelled On Hold.
   - Published rows stay protected (D70e): Re-add and Addendum remain the only late paths.
3. **Bulk Shortlist / Reject / Hold** dialog upgrade (`pipelinetab.jsx`):
   - "Mark students as" with Add to shortlist / Hold at current stage / Remove from shortlist;
   - accept roll number **or email** as the identifier;
   - a live "You have entered N roll nos" counter;
   - the optional "strict check on current stage" toggle (**off by default**; when off, entries outside the pool are allowed with a warning, as in D70(b)) (B2-12);
   - unknown and withdrawn entries reported, never dropped.
4. **Download Current Shortlist**, per stage:
   - an Excel of the stage's pool with the current decision and published/draft marker;
   - title rows "shortlisted during '&lt;stage&gt;' to be proceeded to &lt;next stage / FINAL OFFER&gt;" and a footer timestamp in IST;
   - admin only;
   - it supports the S3 custom templates once they exist.
5. **Process status card** on the job profile Overview:
   - "**&lt;Stage&gt; is in progress.**" with a button to that stage's page;
   - a per-stage list with ✓ for completed and 🏆 for the final stage, each linking to its shortlist page;
   - after completion, a "The process is complete — final selected list" card linking to Shortlist for Offer.
6. **Progress Grid overall Status** column on the all-rounds grid: Offered / Not selected (Superset "Disqualified") / On hold / In process, with a legend and an Excel button.
7. **Placed tooltip**: a trophy icon with "Placed in &lt;role&gt; at &lt;company&gt;" (admin only). Add the student's offers to the pipeline payload.
- **Acceptance:**
  - Per-row actions create drafts that students and the company cannot see until publish.
  - The counter equals the pool and draft counts.
  - Arrows move across stages.
  - The download matches the screen.
  - Existing publish tests still pass.

### S2. Shortlist for Offer: edit after publish (video 07; analysis 07 §2 F10)
- On the results console (renamed **Shortlist for Offer** if approved), each announced offer gets **Edit**: CTC Offered, CTC Interval (YEAR / MONTH), currency and offer type.
  - Audit with before and after (`offer.update`).
  - A type change re-runs `BlockingPolicy`: update or lift the affected blocks and placed-elsewhere flags, and show the effect before saving.
  - The student is notified (in-app plus a personal mail) when the type or CTC changes.
- Optional **Upload CTCs**: an Excel of roll number, CTC, interval and currency, through `SpreadsheetImportService`, with a dry run.
- **Revoke offer** (approved, B2-8): admin only, confirmation plus remark; lifts the blocks created by that offer and clears the related placed-elsewhere flags; audited; the student is notified.
- **Acceptance:** an edit updates analytics and exports; a type change from Full-Time to PPO offered lifts the block and clears the flags; all of it is audited.

### S3. Excel Templates and downloads (video 08; analysis 08 entire)
1. **Excel Templates** library: an admin page in the drawer under a new **Reports** group, with "+ Add New", duplicate, delete and rename.
   - New table `export_templates` (name, type, ordered `columns` JSON `[{key, label}]`, created_by, timestamps), in a new migration. Audit every write.
2. **Add Excel Template** dialog: Template Name (required) and Template Type.
   - Start with **STUDENT_LIST** (used for applicants, eligible lists, stage shortlists and cycle student lists).
   - Each type defines which fields are allowed.
3. **Template editor**: one row per column with **Column Key** and an editable **Display Name in Report**; drag to reorder (`@hello-pangea/dnd` is already installed); delete row; a searchable field picker that hides fields already used; autosave indicator.
4. **Field catalogue**, backend: a registry of key → default label, value resolver and audience (admin or company-safe).
   - Seed it with every field we store: profile, academics, contact, application status and time, resume label and verification and the signed link, answers, per-round results, offer (CTC, currency, interval), blocks.
   - Add **S.No.**, **CTC Currency** and **Last Edited** (missing from today's export).
   - "Placement Cycle Specific Information" groups (offers, best CTC, blocks and enrolment for a chosen cycle) are admin only.
5. **Download Applicants** on the job profile page becomes a split button with **Excel - Default Template** (today's export) and **Excel - Custom Template**. The custom option opens a "Select Custom Template" dialog and calls `?template=<id>`.
6. **Download Eligible List**: a new Excel export of `eligibleStudentsQuery()` with an Applied / Not applied column, from the Eligible tab, with default and custom template. **Admin only**; companies never get it (B3).
7. Company exports keep the company-safe field set. Company custom templates: **not now** (B3).
- Formula safety: headers and values are written as explicit strings.
- **Acceptance:**
  - a template's columns and order match the output exactly;
  - company exports never contain admin-only keys, even if a template lists them;
  - a template with "=cmd" as a display name is written as text.

### S4. Students directory and student page (videos 03, 04; analysis 03, 04)
1. **Apply Filters** on `/admin/students` and on the placement's enrolled list:
   - multi-select Batch / Programme / Branch;
   - Gender;
   - Class X / Class XII % and CGPA, as greater-than / less-than ranges;
   - ongoing and total backlogs;
   - **Placement Status** (Placed / Not placed yet, as defined in D80, optionally per cycle);
   - **Blocked Status** (an active `placement_blocks` row);
   - **Invitation Status** (after S5).
   
   Also a "Clear All Filters" button, filter count badges, a title "N filtered students", and "Showing page X of Y (N records)". Filters are kept in the URL.
2. List columns: add Email and **Mobile No.**, plus a photo avatar. Search also covers mobile and personal email ("Search student with name, roll number, email, or mobile number...").
3. **Global "Search students"** box in the admin top bar: type roll number or name and jump to the student page. A quick-view **drawer** opens from lists such as pipeline, results and applicants.
4. **Download as Excel** of the filtered list, which also supports S3 templates; audited.
5. **Student page** (`/admin/students/[id]`):
   - **summary card** with photo, CGPA, Applications count and Offers count;
   - **Placements** section grouped by cycle, each showing **Enrolled** or **Placed (&lt;role&gt; at &lt;company&gt;)**, with **Show Applications** and **Show Attendance** (round attendance per application);
   - a **Resumes & Documents** tab listing the resumes with status, inline **Mark as verified** / Reject (reusing `AdminResumeController`, including the 409 race guard), plus "Mark all as verified";
   - **Notes** ("Write notes about this student"): admin-only internal notes in a new table `student_notes` (author, body, time), audited, never shown to the student or companies;
   - **Download Placement Report** (applications, stages, offers) and **Download Eligibility Report** (every job profile in the student's cycles with `EligibilityService::check` reasons), as Excel.
6. **Extra academic fields, CDC-entered only** (allowed under the locked-academics rule):
   - current semester, course start and end dates, lateral entry;
   - 10th / 12th board and year of passing;
   - previous degree and its CGPA or percentage, for M.Tech, MBA and PhD students.
   
   Add them in a new migration, to the bulk import and the academic update, to the admin form and student page (read-only for the student), and to the S3 field catalogue. Semester-wise CGPA waits for the institute sync.
7. **Pending requests banner** on Students and the admin Dashboard: "You have N profile update requests pending for approval", counting pending branch changes and pending resumes, with links to the queues.
8. **Placement enrolled list**:
   - names link to the student page;
   - status shows **Enrolled**;
   - a new route and action to **suspend / reactivate an enrolment**. The status already exists in `cycle_enrollments`, and D88(c) already tells admins to "suspend instead", but nothing implements it. It must be audited, and a suspended enrolment makes the student ineligible in that cycle (`EligibilityService` already checks `active`).
- **Acceptance:**
  - each filter returns exactly the matching students on seeded data (write tests per filter);
  - Notes never appear in any student or company payload;
  - suspending an enrolment hides that cycle's job profiles from Apply, with the reason shown.

### S5. Student invitations (video 02; analysis 02)
1. **Invitation status:** new columns on `users` or `student_profiles`, in a new migration: `invited_at`, `last_invited_at`, `invite_count`, `activated_at` (first password set) and `invite_revoked_at`.
   - Derived statuses: **Sent / Accepted / Revoked**, where Accepted = password set (no self sign-up).
   - Set them in the invitation job, resend and `resetPassword`. Backfill existing students: `activated_at` = now for anyone who has logged in or has a non-null `remember_token`. Decide and record the backfill rule.
2. **Send Invitations** page (or a Students tab):
   - status chips with counts;
   - Status and Batches filters and search;
   - columns Name, Mobile, Batch, Email, Personal Email, Roll Number, Gender, Status, DOB, Programme/Branch;
   - pagination.
3. **Re - Send Invites** in bulk: multi-select, plus "resend to all pending". Already-accepted students are skipped and cannot be selected. Uses the personal invitation mail through `MailDispatchService`, audited. Add the same guard to the single resend route, so an active student is not sent a new set-password link.
4. **Revoke Invites** (approved, B2-9): deletes the `invites` broker token, sets `invite_revoked_at` and is audited. It is **refused for already-activated accounts** (message: use Suspend). Resending un-revokes.
5. Header counts on Students: "**N students registered** · total N students invited".
6. **Import upgrades:**
   - map columns **by header name**, not position;
   - accept Superset's sample CSV headers (First/Middle/Last name merged into `full_name`, Mobile Country Code + Mobile into `phone`, Email Address → institute email, Xth/XIIth Score → 10th/12th %);
   - make our template headers human-readable with hints, e.g. "Institute Roll Number (Mandatory)", "Gender (M/F/O)", "Date Of Birth (YYYY-MM-DD)";
   - allow an optional "Select student batch" pre-selection.
7. **Large files:** report rows beyond `SpreadsheetImportService::MAX_ROWS` (5,000) as errors instead of silently dropping them. Raise the cap for student import if safe, chunking the processing.
8. **Institute email domain check** (B2-10): `iitism.ac.in`, from config, on create, import and edit, including the seeder handling described in B2-10.
- **Acceptance:** an import of 6,000 rows reports or handles every row; statuses change correctly through invite, accept and revoke; bulk resend never mails accepted students.

### S6. Job profile management (videos 05, 06, 07; analysis 05, 06 F1–F5/F18, 07 F1–F6)
1. **Add New Job** inside a placement (approved, B2-1):
   - the CDC creates a job profile with the same JNF/INF fields, picking or creating the company (a company record without a login is fine);
   - the profile is created as accepted and goes straight to "Open Profile for Applications";
   - reuse the existing wizard components in an admin mode and the existing `form_data` contract, so `EligibilityService`, presenters and exports work unchanged.
2. **Schedule For Later**:
   - in the open dialog, choose an "open applications at" time (IST);
   - the job profile stays not visible until then;
   - a scheduled command (`php artisan schedule:work` locally, cron in production) opens it and sends E2 at that time;
   - can be cancelled or edited before then; audited.
3. **Date of Visit / Process**: an optional date on the job profile, shown on the admin list, student card and calendar.
4. **Attached Documents**: upload and remove PDFs (JD, company deck; PDF only, at most 5 MB, private disk) on a job profile. Students in its cycles download them through an authenticated stream. Audited.
5. **Stages:**
   - an optional **venue** on rounds ("Add venue & schedule"), shown in the calendar and the student trail;
   - add round types **Online test** and **Take Home Assignment** (new slugs; keep the old ones).
6. **Additional Questions help text**: an optional `help_text` per question, shown under the question to students.
7. **Activity**: a per-job-profile timeline built from `audit_logs` (opened, deadline changed, eligibility edited, closed, reopened, stage published, results announced, offers edited).
8. **Communication Log**:
   - add nullable `job_posting_id` and `kind` to `email_logs` in a new migration, filled by every job-profile mail (E2, E3, E4, E5, E9, S7 mails);
   - a tab listing messages with date, type, subject, recipients count, and status queued/sent/failed;
   - subject search.
9. **Copy Link** button: copies the student URL of the job profile (login-gated, no personal data in the URL).
10. **Steps to publish** checklist on the open card: form accepted, open placement chosen, deadline set, stages present, questions reviewed.
11. **Send Applicant List to company**: an admin action that emails the company the company-safe export as an attachment (or a signed download link). Audited and logged in the Communication Log. This is not a gate; companies can still export any time.
12. **Status display labels**:
    - Accepting Applications (open, deadline in the future);
    - Closed For Applications (`in_process`, no stage published);
    - In Process (`in_process` after a publish);
    - Completed and Cancelled.
    
    Display only, from one helper in `lib/format.js`.
13. **Job profile list**: search box; columns Company / Profile / Date of Visit / Deadline / Status.
- **Acceptance:**
  - a scheduled open fires once and mails once;
  - documents are reachable only by allowed users;
  - the Communication Log lists every mail of that job profile;
  - an admin-created job profile behaves exactly like a floated JNF in eligibility, stages, results and exports.

### S7. Engagement: Notices, stage emails, Surveys (videos 06, 07, 09; analysis 06 F15, 07 F17–F18, 09 entire)
1. **Notices** (Superset sidebar ENGAGEMENT → Notices): a notice board.
   - The admin composes a title, rich text (displayed as plain text like events, D76) and an optional PDF attachment.
   - Audiences:
     - all students;
     - programme/branch;
     - a placement;
     - a job profile's applicants;
     - a stage's **published** shortlisted or on-hold students.
   - Draft → Publish (audited, optional email through `sendBulk`).
   - A student **Notices** page shows the notices in their audience, newest first, with unread dots. Add it to the student nav (keep it phone-friendly).
   - Companies do **not** see notices (B3).
2. **Send Notice** / **Send Email to Shortlisted / On Hold**: shortcuts on the stage shortlist page (S1) that prefill the audience.
   - Email: subject, rich text, optional attachment, sent as BCC batches to institute addresses.
   - Logged in the Communication Log; audited.
   - Only published decisions can be targeted.
3. **Surveys** (ENGAGEMENT → Surveys):
   - **Survey Forms** list: Status and Type filters, search, **+ Create New Survey**; card actions **View form / Delete / View Report / Clone**.
   - **Create a new form** modal: form name (required) and "Objective of the survey / Welcome text".
   - **Template** tab with a **Toolbar**:
     - **Multiple options, single answer**, **Multiple options, multiple answers**, **Text Answer**, **Yes/No**, **Dropdown**, **Date** (build first);
     - **Rating**, **Static Text**, **Rich Text** (next);
     - **File Upload** (PDF/image, **max 5 MB**, private disk) and **Sequence** (last).
     
     Each question has mandatory and help text, and can be reordered and deleted. Also concluding text, **Preview**, **Save Form** and **Publish Survey**.
   - **Audience** tab:
     - **Make this survey public**: means any **signed-in student** can answer, regardless of target audience. Never recruiters, never anonymous (B2-6);
     - **Allow multiple submission**;
     - **Allow edits before deadline**, with a survey deadline in IST;
     - **Target Audience / + Add Audience**: several groups, reusing the event audience logic (`CampusEvent::audienceQuery`) plus placement, batch and "offer holders of a job profile";
     - **Copy Link**.
   - Publish: draft → published (`published_at`), audited, with an optional announcement mail in BCC batches. **No reminder mails** (B2-13).
   - Student side: a **Surveys** page (nav item) listing open surveys in the student's audience, an answer page, submit confirmation, and edit before the deadline if allowed.
   - **View Report**: a responses table, responders and non-responders, and an Excel export through `ExportService` (formula-safe, `Q{n}:` headers). Counts are admin only.
   - **Clone**: copy the questions and settings, not the responses, as a new draft.
   - **Delete**: drafts can be deleted; a published survey is **archived only**, never hard-deleted (B2-7). Archived surveys disappear for students but keep their report.
   - **PPO consent** (B2-5, option a): a survey may be linked to a job profile for context, and its answers are information only. Never change an offer, offer type or block from a survey answer; the CDC acts manually.
   - New tables (new migrations): `surveys`, `survey_questions`, `survey_audiences`, `survey_responses` (answers JSON, per question id).
- **Acceptance:**
  - only the audience can see and answer;
  - non-audience students get 404;
  - mandatory questions are enforced on the server;
  - the report and export match the responses;
  - clone excludes responses;
  - every mail goes in BCC batches.

### S8. Admin hub, Reports and configuration (video 01, 07; analysis 01, 07 F2)
1. **Admin** settings hub: one page with a left vertical tab list. Its first tabs:
   - **Account**: an uploadable account logo, used in the admin, student and company shells and the email header, replacing the hard-coded image and text; plus the institute display name. Store it via `SettingsService`, with the file on the private disk and served by a public read-only route for the logo only.
   - **Users**: the S0 renames and the directory below.
   - **Mail** (the existing mail mode).
   - **Branch Manager** (moved here).
   - **Excel Templates** (S3).
   
   Reached from a new account menu in the top bar (Account / Settings / Logout).
2. **Users** directory: search ("Search by name or email…"), a master-detail card, and **Edit** with First/Middle/Last name, **Designation**, **Mobile** (country code), **Alias** and read-only Email ID.
   - New nullable columns on `users`, in a new migration.
   - It stays super-admin managed and audited.
   - Roles and permissions are milestone S9 only if B2-2 is approved.
3. **Placements** list extras: **Search Placements**, current versus **Previous Placements** (closed or ended), a **Draft** status for cycles that are not yet visible, and "Recently Visited".
   - A Draft status needs a new value. Add it in a new migration as a separate `is_draft` boolean rather than altering the enum, and record the decision.
4. **Student Categories for Placement** (approved, B2-13):
   - CDC-defined categories (title, description), e.g. "Minor in Data Science", "Double Major in CSE";
   - a many-to-many assignment to students (single and bulk import by roll number);
   - an optional **Allowed Student Categories** criterion in job profile eligibility and in Edit eligibility, implemented **inside `EligibilityService`** (`check()` and `eligibleStudentsQuery()` must agree, with tests), with the reason sentence "Requires student category: …".
5. **Reports** group (drawer): cycle lists as Excel downloads, admin only, audited:
   - **Job Offers**;
   - **Students Placed**;
   - **Students Not Placed** (denominator rules per D80);
   - **Placement Matrix** (branch × company offer counts);
   - **Absentees** (round attendance "no");
   - **Job Profiles** list.
6. **Custom Fields**: not in this round. Student Registration fields are ruled out by B2-4.
7. **Resume Tags**: **LATER** (B3). Do not build.

### S9. DEFERRED (owner: LATER): staff roles and permissions (B2-2). Do not build in this round; kept for reference only.
- Roles **Account Admin / Account User / Faculty Member**.
- Permission toggles copied exactly from analysis 01 §2.6: Global Access, Placement Cycle Level Access, College Level Access and Department Level Access, with **Read / Read and Verify / Read and Write** over the programme › branch tree, Notice Board and Excel Templates.
- Enforced by a policy layer on every admin route, plus scoping of student and job-profile queries by department.
- Account Admins keep "access to everything".
- This is a large cross-cutting change: write a separate design note and get the owner's sign-off before coding.

---

## PART E: Data model additions (summary; each in a new migration)
- `export_templates`
- `student_notes`
- `surveys`, `survey_questions`, `survey_audiences`, `survey_responses`
- `notices` (+ audiences)
- `posting_documents`
- `student_categories` + `student_category_student`
- **Users:** nullable `first_name`, `middle_name`, `last_name`, `designation`, `mobile`, `alias`
- **Invitation columns:** `invited_at`, `last_invited_at`, `invite_count`, `activated_at`, `invite_revoked_at`
- **Student profiles:** CDC-entered academic extras (S4.6)
- **Job postings:** `visit_date`, `scheduled_open_at`; **posting rounds:** `venue`; **posting questions:** `help_text`
- **Placement cycles:** `is_draft`
- **Email logs:** `job_posting_id`, `kind`
- **New round-type slugs:** `online_test`, `take_home_assignment`

Give composite indexes explicit short names (D72, MySQL 64-character limit).

## PART F: Mails added (all through MailDispatchService; BCC for broadcasts)
- Notice published (optional), stage email (S7.2), survey published (optional), applicant list to company (S6.11), offer changed (S2), invitation resend in bulk (S5).
- Each one writes `email_logs` with `job_posting_id` where it applies.
- No reminder mails unless approved.

## PART G: When you stop
Update `CDC/SUPERSET_PARITY_PROGRESS.md` (CURRENT STATE, NEXT ACTION), put open questions under BLOCKED, and tell the owner in chat:
- what was done;
- any open questions (only things Part B does not answer);
- the single next action.

Never commit unless told.
