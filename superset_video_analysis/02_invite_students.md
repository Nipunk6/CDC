# 02 — "How to Send invite to students for registration in superset portal" (221 s)

Source frames: `scratchpad/kf/02_invite_students/` (23 frames, t000m00s to t003m28s). All 23 frames were read in order; small UI regions were enlarged (crops in `scratchpad/zoom02/`) to read icons, chips and buttons.

Our code root: `/Users/admin/Desktop/CDC-main/CDC` (backend = `CDC/backend`, frontend = `CDC/frontend`). All paths below are relative to `CDC/` unless absolute.

---

## 1. Video walkthrough

**00:00 — Superset admin home** (URL `app.joinsuperset.com/#/a/dashboard`)
- Purple top banner: "The new Placements app is here. Try it now" (with a close ×).
- Header bar: institute name "Indian Institute of Technology Indian School of Mines Dhanbad" on the left; on the right a "Search students" box (magnifier icon), a download icon, a bell, an apps-grid icon, the account name "Career Development Centre IIT ISM Dhanbad" and a "CD" avatar.
- Left sidebar (dark), in group order:
  - (no heading) Home, My Dashboards
  - JOBS: Companies, Inbound Job Posts, Placements, Job Alerts (with a sparkle icon)
  - RELATIONSHIPS: Students, CRM
  - ENGAGEMENT: Notices, Surveys, Calendar
  - ADMIN: Documents, TalentLens Rubrics, Admin
  - REPORTS (collapsible chevron): Launchpad, New Placements App
- Home body, left column: "WEDNESDAY / 30 / SEPTEMBER", greeting "Good Afternoon, Career!", a one-line motivational tagline, "QUICK LINKS" with "Go to Placement Cycles" and "Go to Reports" (external-link icons), "RECENTLY VISITED" (info icon) with "Recently visited job profiles will appear here." and a "+ Add Job Profile" button.
- Home body, right column: "Here's a summary of what's happening ..." with a "Last 30 Days" range dropdown; KPI "Applications 8,786"; a stacked daily bar chart (teal + orange, Sep 3 to Sep 29, y-axis 0 to 1.2k); "From 2 Categories" with a "Show Details" link; an "Ongoing Placements" list with two rows, each with a calendar icon, name, date range, a progress bar with a percentage and two counts:
  - "Internship Placement || 2026-2027 Session || 2028 Pass out…" 30-Apr-2026 - 31-Aug-2027, 10.87%, 1968, 214
  - "Full-Time Placement for 2026-27 (2027 Pass Out Batch)" 30-Jun-2026 - 30-Jun-2027, 8.21%, 1901, 156

**00:26 — Click sidebar RELATIONSHIPS > Students** (URL `/#/students`)
- Page title (top-left of the content panel): **"Colleges and Students"**.
- Top-right of the panel, three header buttons in a row: (1) an icon showing "abc" with a blue notification dot (no label), (2) an envelope icon + **"Invitations"**, (3) an upload (arrow-up-from-tray) icon with no label.
- Blue info banner: "You have profile update requests pending for approval. **Click to view requests.**"
- Full-width search input with magnifier, placeholder: **"Search student with name, roll number, email, or mobile number..."**
- One college card: institute logo, "Indian Institute of Technology Indian School of Mines Dhanbad", "**13217 students registered**", "**total 12848 students invited**".

**00:28** — Hovering the college card highlights it (grey background); it is clickable.

**00:44** — Hover on the unlabelled upload icon shows tooltip: **"Upload Student CSV for Student Invitations"**.

**00:46 — Click upload icon: modal "Bulk Student Invitations" (with an ⓘ icon after the title)**
- Bordered info box:
  - "To understand how to send Student Invites, **click here!**" (help link)
  - "To send Student Invites, please **use this CSV.**" (download link for the sample file)
- Instruction: "Please select the batch and college of the students for whom you are sending this invitation."
- Dropdown 1 (college): pre-filled "Indian Institute of Technology Indian School of Mines …" (truncated).
- Dropdown 2 (batch): placeholder "Select student batch".
- Buttons: "Cancel" (white) and "Next" (blue, disabled/pale until a batch is chosen).

**01:04** — Presenter clicks "use this CSV." The browser downloads **`Report_Student_Upload_Sample_CSV.csv`** (download panel: "Show in Folder", "Show all downloads").

**01:06 – 01:40 — The sample CSV opened in Excel**
- Only row 1 (headers) is filled; no sample data rows. Selecting row 1 shows "Count: 35" in the status bar, so the template has **35 header columns (A to AI)**.
- 01:12: columns A to L are widened and fully readable:
  - A "Institute Roll Number (Mandatory)"
  - B "First Name (Mandatory)"
  - C "Middle Name"
  - D "Last Name"
  - E "Mobile Country Code (e.g. 91)"
  - F "Mobile (10 Digits)"
  - G "Gender (M/F/O)"
  - H "Date Of Birth (YYYY-MM-DD)"
  - I "Email Address (Mandatory)"
  - J "Personal Email Address"
  - K "Current Course Name"
  - L "Current Course Start Date (YYYY-MM-DD)"
- Columns M to AC are visible only truncated (from 01:06/01:08): M–S are seven more "Current Co…" columns; T "Xth Score…", U "Xth Score…", V "Xth Aggreg…", W "Xth Schoo…", X "Xth Start D…", Y "Xth End Da…", Z "XIIth Score…", AA "XIIth Score…", AB "XIIth Aggre…", AC "XIIth Scho…", AD "XII…". AE–AI are off-screen.
- Only three columns are marked "(Mandatory)": roll number, first name, email address.
- 01:40: presenter closes Excel, "Save your changes to this file?" → "Don't Save".

**01:42 – 01:54 — Back in the modal, choose the batch**
- 01:44: "Select student batch" dropdown open. Visible options (scrollable list): "1994 Passout Batch", "2000 Passout Batch", "2001 Passout Batch", "2002 Passout Batch", "2003 Passout Batch", "2004 Passout Batch", "2005 Passout Batch", "2006 Passout Batch", "2007 Passout Batch", "2008 Passout Batch" (cut off; list continues). The label format is "<YYYY> Passout Batch".
- 01:54: "2028 Passout Batch" selected; "Next" becomes enabled (solid blue) and is clicked.

**01:56 — Full-page upload screen** (URL still `/#/students`, sidebar hidden)
- Title: **"Upload Student Invitations for Indian Institute of Technology Indian School of Mines Dhanbad (2028 Passout Batch)"**
- Text: "You can upload student invitations using the sample format provided."
- Link: "**Download sample CSV format here**"
- Large round blue file-plus icon, caption (italic): "**Click on icon above to select CSV file**"
- Footer, bottom-right: "Cancel" and "**Upload file**" (upload icon; disabled/pale because no file is chosen).
- No file is actually uploaded in the video; the post-upload result is never shown.

**02:18 – 02:20** — Presenter goes back; the "Bulk Student Invitations" modal is shown again with "2028 Passout Batch" still selected, then it is closed (Cancel). Back on "Colleges and Students".

**02:26 — Click "Invitations"** (URL `/#/students/invitations/IITISMD_63`)
- Breadcrumb: "All Students / Send Invitations - Indian Institute of Technology Indian School of Mines Dhanbad".
- Page title with back chevron and ⓘ: **"Send Invitations - Indian Institute of Technology Indian School of Mines Dhanbad"**.
- Filter bar: "Status" dropdown ("Select a status"), "Batches" dropdown ("Select a batch"), "Search" box with placeholder "**Search by name, email or roll no ...**".
- Top-right: three square icon buttons — paper-plane, document (file) icon, "+".
- Table loads (spinner).

**02:28 — Invitations table loaded**
- Status summary chips (top-right, outlined pills): **"Revoked (28)"**, **"Sent (477)"**, **"Accepted (1000+)"**.
- Columns: [checkbox] | **Name** | **Mobile** | **Batch** | **Email** | **Personal Email Address** | **Roll Number** | **Gender** | **Status** | **DOB** | **Current Course Name** (a horizontal scrollbar under the table suggests more columns to the right).
- Status cell values seen: **ACCEPTED** (solid blue chip) and **SENT** (outlined blue chip).
- Sample rows: RAGHUBIR 2028 26IM0004@iitism.ac.in ACCEPTED; NITISH 2028 ACCEPTED; Shreyash Datta 2029 25JE0246@iitism.ac.in SENT; Vitthal Shukla 2029 SENT; Shreshth Pathak 2029 SENT; Shaurya Tomar 2029 ACCEPTED; Vikas Yadav, Mrityunjay Chakrabarty, Yatendra Singh, YALANGI KENNY NISANTH, Ashit Kumar Padhi, Sagar Navinchandra Heruvala, SRIKRISHNA KARTHIKEYA GOLLA, Nayan Mondal, Vaishnavi Arora (all 2028, ACCEPTED). Email = roll number @iitism.ac.in. Mobile, Personal Email Address, Gender, DOB and Current Course Name show "-" for every row.
- The list mixes batches (2028 and 2029) even though "2028" was chosen earlier — the list is institute-wide, filtered only by the Batches dropdown.
- Footer: "**Showing Page 1 of 857 (12848 records)**" and a pager "‹ 1 2 … 856 857 ›" — i.e. 15 rows per page; 12848 equals the "students invited" figure on the college card.
- Row checkboxes for ACCEPTED rows are greyed out (disabled); only SENT rows have active checkboxes.

**02:40 — Select rows for bulk action**
- Presenter ticks Shreyash Datta, Vitthal Shukla and Shreshth Pathak (all SENT). The header checkbox becomes checked and the Name header is replaced by two buttons: **"Re - Send Invites"** (paper-plane icon) and **"Revoke Invites"** (× icon). The status chips at top-right disappear while a selection exists.
- Hovering a row shows two inline icons at the end of the Name cell: a pencil (edit) and an eye (view).

**03:08** — Selection cleared; hover on "Vitthal Shukla" again shows the pencil and eye icons. Status chips are back.

**03:10 – 03:28 — Back to Students, search**
- 03:10: click sidebar Students → "Colleges and Students" (banner briefly fades in).
- 03:12: focus the search box.
- 03:28: types "25mt00025" → message **'We could not find any student matching "25mt00025"'** above the college card (so the roll number is searched case-insensitively and the student does not exist / was never invited — the presenter's point is "search first, invite if not found").

---

## 2. Superset features shown

### F1. Students landing page — "Colleges and Students"
- **Superset name / path:** Sidebar RELATIONSHIPS > **Students** → page "**Colleges and Students**" (`/#/students`).
- **What it does:** Entry point for all student administration. Shows each college as a card with logo, name, "**N students registered**" and "**total N students invited**" (13217 / 12848). The card is clickable (opens that college's student list, not shown). Header buttons: "abc" icon (unknown, see Uncertain), "**Invitations**", upload icon ("Upload Student CSV for Student Invitations"). Used by admin.
- **Our status: PARTIAL.**
  - We have `/admin/students` (`frontend/app/admin/students/page.jsx:113-131`) titled "Students" with subtitle "`{total} student(s)`", backed by `GET /admin/students` (`backend/routes/api.php:95`, `AdminStudentController::index` lines 66-111).
  - Missing: separate "registered" (activated) vs "invited" counts. We store no invitation status, so neither number can be computed today (see F8/F9).
  - The college card is not needed: we serve one institute.
- **Naming:** ours "Students" (nav label `components/admin/adminshell.tsx:50`, page title `app/admin/students/page.jsx:115`) vs Superset "Students" (nav) / "Colleges and Students" (page). Nav label matches. Do **not** rename the page to "Colleges and Students" (single institute). Count wording: ours "N student(s)" vs Superset "N students registered" / "total N students invited" — adopt Superset's two counts once invitation status exists.
- **Conflict check:** none.

### F2. Student search on the Students page
- **Superset name / path:** Students page search box, placeholder "**Search student with name, roll number, email, or mobile number...**". No-result text: '**We could not find any student matching "<query>"**'.
- **What it does:** Live search across name, roll number, email and mobile; case-insensitive (lower-case "25mt00025" searched). Used by admin to check whether a student already exists before inviting.
- **Our status: PARTIAL.**
  - `AdminStudentController::index` search (lines 81-88) matches `roll_no`, `full_name`, `institute_email` only. **Mobile (`phone`) and personal email are not searched.**
  - Frontend field label "Search roll no, name or email" (`app/admin/students/page.jsx:147-154`); search runs on Enter or the "Search" button, not live.
  - Empty state text: "No students match. Add one, or import a spreadsheet." (`page.jsx:221`).
- **Naming:** ours "Search roll no, name or email" vs Superset "Search student with name, roll number, email, or mobile number..." — rename after adding phone to the query. Empty-state: ours "No students match. Add one, or import a spreadsheet." vs Superset 'We could not find any student matching "<query>"' — optional rename (ours is more helpful; could combine: 'No student matches "<query>". Add one, or import a spreadsheet.').
- **Conflict check:** none.

### F3. Global "Search students" in the header
- **Superset name / path:** header bar, every page: "**Search students**".
- **What it does:** Quick student lookup from anywhere in the admin app. Admin.
- **Our status: NOT IMPLEMENTED.** `components/admin/adminshell.tsx` has no search control (grep for "search" returns nothing); the top bar shows Dashboard, Cycles, Postings, Students, JNF Reviews, INF Reviews, More, bell, Sign Out.
- **Naming:** n/a (new). Use "Search students".
- **Conflict check:** none. Low priority.

### F4. Profile update requests (banner + approval queue)
- **Superset name / path:** Students page info banner: "**You have profile update requests pending for approval. Click to view requests.**"
- **What it does:** Students submit profile changes that the CDC approves; the banner links to the queue (queue itself not shown). Admin.
- **Our status: PARTIAL.**
  - Students edit personal email, phone, home state, LinkedIn, GitHub directly with no approval (`backend/app/Http/Controllers/StudentProfileController.php:26-45`, `StudentProfile::SELF_EDITABLE`); photo via `uploadPhoto`.
  - The only approval queue is **Branch Change Requests** (`app/admin/branch-changes/page.jsx:82`, nav "Branch Changes" in `adminshell.tsx:53`). There is no "pending requests" banner on the Students page.
- **Naming:** ours "Branch Change Requests" / nav "Branch Changes" vs Superset "profile update requests". Keep ours unless the scope widens.
- **Conflict check:** **NEEDS OWNER DECISION.** CDC_PORTAL_CONTEXT.md §2.3 says the student "can edit only personal email, phone, home state, LinkedIn, GitHub and photo" (direct edit) and academic fields are locked. Making those edits go through approval changes that decision. A no-conflict subset: show a banner "N branch change requests pending. View requests." on the Students page.

### F5. "Upload Student CSV for Student Invitations" → "Bulk Student Invitations" modal
- **Superset name / path:** Students page, upload icon (tooltip "**Upload Student CSV for Student Invitations**") → modal "**Bulk Student Invitations**" ⓘ.
- **What it does / fields:**
  - Help link "To understand how to send Student Invites, **click here!**"
  - Sample download "To send Student Invites, please **use this CSV.**"
  - Instruction "Please select the batch and college of the students for whom you are sending this invitation."
  - College dropdown (pre-filled with the institute).
  - Batch dropdown "Select student batch", options "<YYYY> Passout Batch" (1994, 2000, 2001 … 2008 … 2028 seen).
  - Buttons "Cancel", "Next" (disabled until a batch is picked).
  - Rule visible: batch is chosen once for the whole file, not per row.
  - Admin only.
- **Our status: PARTIAL.**
  - "Import" button (`app/admin/students/page.jsx:124-126`) opens `SpreadsheetImportDialog` titled "Import Students" (`page.jsx:271-284`; component `components/admin/spreadsheetimportdialog.jsx`), backed by `POST /admin/students/import` (`routes/api.php:98`, `AdminStudentController::bulkImport` lines 268-384) and `GET /admin/students/import/template` (`routes/api.php:97`).
  - Missing: (a) batch pre-selection step — our batch is a per-row column `graduating_batch` (`StudentAccountService::IMPORT_COLUMNS`, lines 24-28); (b) a help link; (c) the college dropdown (not needed: single institute).
  - Ours is better in one way: dry-run "Check file" preview of errors before anything is created (`bulkImport` lines 337-345; dialog lines 157-161).
- **Naming mismatches:** button "Import" vs tooltip "Upload Student CSV for Student Invitations"; dialog title "Import Students" vs "Bulk Student Invitations"; "Download template" (dialog line 99) vs "use this CSV." / "Download sample CSV format here"; ours "Batch"/"Graduating batch" vs "Passout Batch". Recommended: rename the button to "Invite Students (CSV)" or keep "Import" with tooltip "Upload student CSV for invitations"; rename dialog title to "Bulk Student Invitations"; "Download template" → "Download sample CSV".
- **Conflict check:** none. Our "invitation" is a set-password link to a CDC-created account, not a self-registration invite (see F9 note), which is consistent with owner decisions.

### F6. Sample CSV template (`Report_Student_Upload_Sample_CSV.csv`)
- **Superset name / path:** link "use this CSV." in the modal and "Download sample CSV format here" on the upload page.
- **What it does / columns:** header-only CSV, 35 columns. Readable: Institute Roll Number (Mandatory), First Name (Mandatory), Middle Name, Last Name, Mobile Country Code (e.g. 91), Mobile (10 Digits), Gender (M/F/O), Date Of Birth (YYYY-MM-DD), Email Address (Mandatory), Personal Email Address, Current Course Name, Current Course Start Date (YYYY-MM-DD), then seven more "Current Co…" columns, then Xth Score…, Xth Score…, Xth Aggreg…, Xth Schoo…, Xth Start D…, Xth End Da…, XIIth Score…, XIIth Score…, XIIth Aggre…, XIIth Scho…, XII… and 5 more unseen. Only roll number, first name and email are mandatory. Format hints are embedded in the header text (date format, digit count, allowed gender letters).
- **Our status: PARTIAL.**
  - Our template is `student_import_template.xlsx` (xlsx, header-only, bold) with 18 snake_case headers: `roll_no, full_name, institute_email, programme, branch, graduating_batch, gender, current_cgpa, ongoing_backlogs, total_backlogs, tenth_percent, twelfth_percent, date_of_birth, personal_email, phone, category, pwd, home_state` (`StudentAccountService.php:24-28`; `AdminStudentController::importTemplate` 386-389, `templateResponse` 507-523). Upload accepts csv/txt/xlsx/xls up to 5 MB (`SpreadsheetImportService::UPLOAD_RULES`, line 20).
  - Required in ours: roll_no, full_name, institute_email, programme, branch, graduating_batch, gender (`StudentAccountService::rules` 39-70). Superset requires only roll, first name, email.
  - Differences: name is one field (`full_name`) vs First/Middle/Last; phone is one free field vs Mobile Country Code + Mobile (10 Digits); no "Current Course Start Date", no school names, no 10th/12th start/end dates, no score-type/aggregate columns; ours has CGPA, backlogs, category, PwD, home state which Superset's visible columns lack. Gender: ours accepts M/F/O as well as male/female/other (`normalise`, lines 110-113) — compatible. DOB: ours accepts Y-m-d, d-m-Y, d/m/Y, d.m.Y and Excel serials (`normaliseDate` 220-243) — compatible with YYYY-MM-DD.
  - **Our import maps columns by position, not by header name** (`bulkImport` lines 300-305 use `IMPORT_COLUMNS` index), and only skips row 1 if cell A normalises to "rollno" (line 285). A Superset-format CSV (e.g. the CDC's existing export of 12,848 students) cannot be imported as-is.
  - Our headers carry no format hints (e.g. "(Mandatory)", "(YYYY-MM-DD)").
- **Naming:** see Rename list (roll number, name, email, mobile, DOB, Xth/XIIth). Suggest human-readable headers with hints, e.g. "Roll Number (Mandatory)", "Date of Birth (YYYY-MM-DD)", "Gender (M/F/O)", while still accepting the current snake_case headers.
- **Conflict check:** none. (Superset collects personal email; we also store it but mail only the institute address — consistent with "Emails go to institute addresses only".)

### F7. Upload page — "Upload Student Invitations for <College> (<Batch>)"
- **Superset name / path:** reached by "Next" in the modal; full-page screen.
- **What it does / fields:** title "Upload Student Invitations for … (2028 Passout Batch)"; "You can upload student invitations using the sample format provided."; "Download sample CSV format here"; file picker icon "Click on icon above to select CSV file"; footer "Cancel", "Upload file" (disabled until a file is chosen). CSV only (caption says CSV). Admin.
- **Our status: IMPLEMENTED (different shape).** Our dialog: "Download template", "Choose file" (accepts .xlsx/.xls/.csv), caption "No file chosen (.xlsx, .xls or .csv, max 5 MB)", "Check file" (dry run), then "Import N row(s)"; per-row error table Row / Roll no / Field / Problem (`spreadsheetimportdialog.jsx:92-170`). The batch is not in the title because we have no batch pre-selection (see F5).
- **Side finding (bug-level):** `SpreadsheetImportService::rows()` stops reading after `MAX_ROWS = 5000` rows (lines 17, 59-61) and `bulkImport` does not report that rows were dropped. A file larger than 5,000 rows is silently truncated. Superset's list shows 12,848 invites, so a one-shot migration would hit this.
- **Naming:** "Choose file" vs "Click on icon above to select CSV file"; "Upload"/"Import N row(s)" vs "Upload file"; "Download template" vs "Download sample CSV format here". Optional renames; keep "Check file" (we have no Superset equivalent).
- **Conflict check:** none.

### F8. Invitations list — "Send Invitations - <College>"
- **Superset name / path:** Students page header button "**Invitations**" → "**Send Invitations - Indian Institute of Technology Indian School of Mines Dhanbad**" ⓘ (`/#/students/invitations/IITISMD_63`), breadcrumb "All Students / Send Invitations - …".
- **What it does / fields:**
  - Filters: "Status" ("Select a status"), "Batches" ("Select a batch"), "Search" ("Search by name, email or roll no ...").
  - Columns: Name, Mobile, Batch, Email, Personal Email Address, Roll Number, Gender, Status, DOB, Current Course Name (+ possibly more off-screen).
  - Status chips: ACCEPTED (solid), SENT (outlined); REVOKED exists (count chip).
  - Footer "Showing Page X of Y (N records)", 15 rows/page, numbered pager.
  - Row hover icons: pencil (edit), eye (view).
  - Top-right icon buttons: paper-plane, document, "+" (meanings not shown; see Uncertain).
  - Admin only.
- **Our status: NOT IMPLEMENTED.**
  - There is no invitation list or invitation status anywhere. The student list (`app/admin/students/page.jsx:204-213`) has columns Roll no, Name, Programme, Branch, Batch, CGPA, Backlogs, Active, View, filters Programme/Branch/Batch/Status(Active|Suspended) — "Status" there means account suspension, not invitation.
  - Data available today: `student_invite_tokens` (email PK, token, created_at; migration `backend/database/migrations/2026_10_01_000019_create_student_invite_tokens_table.php`) holds at most one outstanding invite per email, is replaced on resend and deleted when any password is set (`AuthController::resetPassword` lines 145-162). `email_logs` (status enum queued/sent/failed, `2026_03_30_000025_create_email_logs_table.php`) records each E1 send, but queued rows stay "queued" after sending (D39). No column records "invited at" or "activated/accepted at" on `users`/`student_profiles`.
  - What to build: persistent invitation fields (e.g. `invited_at`, `last_invited_at`, `invite_count`, `activated_at`, `invite_revoked_at`) set by `StudentAccountService::sendInvitation/deliverInvitation` and `AuthController::resetPassword`; an admin page (or a tab/filter on Students) listing students with invitation status, Status + Batch filters, search, status-count chips, pagination, mobile/personal email/gender/DOB/programme columns.
- **Naming:** none today. Superset page name "Send Invitations"; button "Invitations". Recommended: nav/tab "Invitations", page title "Student Invitations" (the "Send Invitations - <college>" form is redundant for one institute). Breadcrumb "All Students" — ours already uses back label "All Students" on the student detail page (`app/admin/students/[id]/page.jsx:139`), matches.
- **Conflict check:** none, provided "Accepted" means "student set their password" (see F9).

### F9. Invitation statuses: Sent / Accepted / Revoked (+ counts)
- **Superset name / path:** invitations page, "Status" column and count chips "**Revoked (28)**", "**Sent (477)**", "**Accepted (1000+)**"; "Status" filter. Counts above 1000 show as "1000+".
- **What it does:** Tracks each invite: SENT (mailed, not yet acted on), ACCEPTED (student registered), REVOKED (invite withdrawn). Accepted rows cannot be selected for bulk actions.
- **Our status: NOT IMPLEMENTED.** No status field (see F8). The detail page has a "Resend Invite" button but no indicator whether the student has ever set a password.
- **Naming:** none. Use Superset's "Sent", "Accepted", "Revoked" (or "Sent", "Activated", "Revoked" if the owner prefers wording that matches our set-password model). Consider also "Expired" (our link lasts 7 days, D94) — Superset shows no such status.
- **Conflict check:** **NEEDS OWNER DECISION on meaning only.** In Superset, "Accepted" follows the student registering in response to an invite. Our owner rule is: students are created only by the CDC, no self sign-up; the invitation is a set-password link (CDC_PORTAL_CONTEXT.md §3 Students). Building status tracking does not conflict as long as "Accepted" = password set. Building a Superset-style "student fills in their own registration form" would conflict with "created only by the CDC, no self sign-up" and must not be done without the owner.

### F10. Bulk "Re - Send Invites"
- **Superset name / path:** invitations table, tick rows → button "**Re - Send Invites**" (paper-plane icon) replaces the header.
- **What it does:** Re-sends the invitation email to all selected students. Only non-accepted rows can be ticked (accepted rows have disabled checkboxes).
- **Our status: PARTIAL.**
  - Single-student resend exists: `POST /admin/students/{studentProfile}/resend-invitation` (`routes/api.php:106`, `AdminStudentController::resendInvitation` lines 257-263, audited `student.invite_resend`), button "Resend Invite" on the student detail page (`app/admin/students/[id]/page.jsx:117-124, 145-147`).
  - Missing: multi-select and bulk resend; a "resend to all pending" action; a guard that skips students who already set a password. Today `resendInvitation` sends regardless, which also issues a fresh 7-day set-password link to an already-active student.
  - Mail rule to keep: invitations are personal mails sent one by one (§3 Bulk mail), so bulk resend must loop `sendInvitation` per student, not use BCC batches.
- **Naming:** ours "Resend Invite" vs Superset "Re - Send Invites". Recommend "Resend Invites" for the bulk button (Superset's hyphenation looks like a typo); keep "Resend Invite" for the single button.
- **Conflict check:** none.

### F11. Bulk "Revoke Invites"
- **Superset name / path:** invitations table, tick rows → "**Revoke Invites**" (× icon).
- **What it does:** Withdraws outstanding invitations (status becomes REVOKED; count chip "Revoked (28)").
- **Our status: NOT IMPLEMENTED.**
  - Nearest: Suspend/Reactivate (`PATCH /admin/students/{id}/suspend|reactivate`, `routes/api.php:104-105`, `AdminStudentController::setActive` 446-472). Suspension blocks login via the `active` middleware but **does not invalidate the invite link**: `AuthController::resetPassword` (lines 131-173) has no `is_active` check, so a suspended student can still set a password (but cannot log in).
  - To build: a revoke action that deletes the `invites` broker token (`Password::broker('invites')->deleteToken`), records `invite_revoked_at`, audit-logs, and is selectable in bulk; resend clears the revoked state.
- **Naming:** none today; adopt "Revoke Invites".
- **Conflict check:** **NEEDS OWNER DECISION** on whether "revoke" is a separate state from "suspend" (owner has a suspend/reactivate decision; "Admin is god" requires any revoke to be reversible by resend).

### F12. Row actions: edit (pencil) and view (eye)
- **Superset name / path:** invitations table row hover icons (no text).
- **What it does:** Edit the invited student's data / view the student (presumed from icons).
- **Our status: PARTIAL.** Student list has only a View (eye) icon per row (`app/admin/students/page.jsx:243-247`); Edit is a button on the detail page (`[id]/page.jsx:142-144`) using `StudentFormDialog` (`components/admin/studentformdialog.jsx`, title "Edit <roll>"). No inline edit from the list.
- **Naming:** ours icon-only "View" (column header "View") and "Edit" button vs Superset icon-only pencil/eye. Matches in spirit.
- **Conflict check:** none. Low priority.

### F13. Invitations page header buttons: paper-plane, document, "+"
- **Superset name / path:** top-right of "Send Invitations" page; unlabelled icon buttons.
- **What it does:** Not demonstrated. Likely: paper-plane = send/resend invitations (to all pending or filtered), document = download/export the invitation list (or the CSV upload), "+" = invite a single student.
- **Our status:**
  - Single invite ("+"): **IMPLEMENTED** as "Add Student" (`app/admin/students/page.jsx:121-123`; `POST /admin/students`, `AdminStudentController::store` 167-187, which creates the account and sends E1; success message "Student created. An invitation has been sent to their institute email.").
  - Export of the student/invitation list: **NOT IMPLEMENTED** for the master student list. Only cycle-level export exists: `GET /admin/placement-cycles/{id}/students/export` (`routes/api.php:91`, `AdminPlacementCycleController::exportStudents` line 126).
  - "Send to all pending": **NOT IMPLEMENTED** (see F10).
- **Naming:** ours "Add Student" vs Superset "+" (no label). Keep ours.
- **Conflict check:** none.

### F14. Batch labelled as "Passout Batch"
- **Superset name / path:** batch dropdown options "<YYYY> Passout Batch"; "Batches" filter; "Batch" column; home page uses "2028 Pass out…" / "(2027 Pass Out Batch)".
- **What it does:** Batch = graduating (pass-out) year, used to scope uploads and filters.
- **Our status: IMPLEMENTED** as `graduating_batch` (labels "Graduating batch" in `studentformdialog.jsx:145` and `[id]/page.jsx:45`; "Batch" in list column and filter, `app/admin/students/page.jsx:179, 209`; cycle page "Allowed Programmes & Batches", `app/admin/placement-cycles/[id]/page.jsx:355`).
- **Naming:** ours "Graduating batch"/"Batch" vs Superset "Passout Batch"/"Batch". Optional rename of "Graduating batch" → "Passout batch" in UI text only (never the column/key).
- **Conflict check:** none.

### F15. Institute email in the "Email" column
- **Superset name / path:** CSV "Email Address (Mandatory)"; invitations table "Email" (all `<roll>@iitism.ac.in`), separate "Personal Email Address".
- **What it does:** The institute address is the login/invite address; personal email is optional.
- **Our status: PARTIAL.** We store `institute_email` (required, unique) and `personal_email` (optional) and mail invitations to the institute address (`deliverInvitation`, `StudentAccountService.php:200-218`). But **nothing validates the institute domain**: `rules()` (lines 46-50) only checks `email` + uniqueness; grep for "iitism.ac.in" in `backend/app` and `backend/config` finds nothing. The owner rule "Emails go to institute addresses only" is enforced only by admin care.
- **Naming:** ours "Institute email" vs Superset "Email Address"/"Email"; ours "Personal email" vs "Personal Email Address". Keep "Institute email" (clearer); optional "Personal email address".
- **Conflict check:** adding domain validation **supports** an owner rule; but it is a behaviour change, so confirm the domain list (e.g. `iitism.ac.in` only?) with the owner.

### F16. Admin home dashboard (shown incidentally at 00:00)
- **Superset name / path:** Sidebar "Home" (`/#/a/dashboard`): greeting, Quick Links (Go to Placement Cycles, Go to Reports), Recently Visited job profiles + "Add Job Profile", "Here's a summary of what's happening ..." with "Last 30 Days", Applications KPI + daily stacked bar chart, "From 2 Categories"/"Show Details", "Ongoing Placements" with % progress and two counts per cycle.
- **Our status: PARTIAL** (not the subject of this video). Admin dashboard `frontend/app/admin/page.tsx` (Quick Stats ~line 203, Quick Actions ~line 276) and Placement Analytics `app/admin/analytics/page.jsx` ("Applications per day" panel line 208). No "Recently visited", no "Last 30 Days" range picker on home, no "Ongoing Placements" progress list on home (not verified in depth).
- **Naming:** ours "Dashboard" vs "Home"; "Cycles" vs "Placement Cycles"/"Placements"; "Analytics" vs "Reports". Defer to the dashboard video analysis.
- **Conflict check:** none here. Low priority for this spec.

---

## 3. Gap summary

| Superset name | What to build | Priority | Conflicts |
|---|---|---|---|
| Invitation status (Sent / Accepted / Revoked) | Persist invited_at, last_invited_at, invite_count, activated_at (first password set), invite_revoked_at; derive status; set them in `sendInvitation`/`deliverInvitation`/`resetPassword` | High | NEEDS OWNER DECISION on wording/meaning: "Accepted" must mean "password set", not self-registration (no self sign-up rule) |
| Send Invitations (invitations list) | Admin page or Students-page tab: Status + Batch filters, search by name/email/roll, status-count chips (Sent / Accepted / Revoked, "1000+" cap optional), columns Name, Mobile, Batch, Email, Personal Email, Roll Number, Gender, Status, DOB, Programme/Branch, pagination with "Showing page X of Y (N records)" | High | None |
| Re - Send Invites (bulk) | Multi-select + bulk resend endpoint (per-student mails via `MailDispatchService`, audited), "resend to all pending" option, skip/disable already-activated students; add the same guard to the single `resend-invitation` route | High | None |
| students registered / students invited counts | Two counts on the Students page header | Medium | Depends on status tracking |
| Revoke Invites | Bulk revoke: delete the `invites` broker token, set invite_revoked_at, audit; resend un-revokes; decide relation to Suspend | Medium | NEEDS OWNER DECISION (revoke vs suspend) |
| Sample CSV (Superset format) | Header-name-based column mapping; accept Superset's CSV headers (First/Middle/Last name merged into full_name, Mobile Country Code + Mobile merged into phone, Email Address → institute_email, Xth/XIIth Score → 10th/12th %); human-readable headers with format hints in our template | Medium | None |
| Upload Student Invitations (large files) | Report (not silently drop) rows beyond `SpreadsheetImportService::MAX_ROWS` = 5000, or raise the cap for student import | Medium | None |
| Email Address (institute) | Validate `institute_email` domain against a configured institute domain list | Medium | Confirm domain list with owner (supports "institute email only") |
| Search student with … mobile number | Add `phone` (and personal email) to `AdminStudentController::index` search; update placeholder | Low | None |
| Bulk Student Invitations → Select student batch | Optional batch pre-selection that fills/validates `graduating_batch` for every row of the file | Low | None |
| Bulk Student Invitations → "click here!" help | Link to a short how-to | Low | None |
| Header "Search students" | Global student quick-search in the admin top bar | Low | None |
| Invitation list export (document icon, presumed) | Excel export of the student/invitation list with status | Low | None |
| Row edit (pencil) / view (eye) | Inline Edit icon next to View on the student list | Low | None |
| Profile update requests (banner + approval) | Option A (no conflict): banner "N branch change requests pending" on Students page. Option B: approval queue for student profile edits | Low | Option B: NEEDS OWNER DECISION (students currently edit contact fields directly) |
| Home dashboard extras (Recently visited, Last 30 Days, Ongoing Placements progress) | Defer to dashboard video spec | Low | None |

---

## 4. Rename list

UI text only. Owner rule: never rename routes, columns or response keys.

| Where it appears in our UI/code | Our current name | Superset name |
|---|---|---|
| Students page title, `app/admin/students/page.jsx:115` | Students | Colleges and Students (keep ours; single institute) |
| Students page subtitle, `page.jsx:116` | `{N} student(s)` | `{N} students registered` · `total {N} students invited` |
| Students page button, `page.jsx:124-126` | Import | Upload Student CSV for Student Invitations (tooltip) |
| Import dialog title, `page.jsx:278` | Import Students | Bulk Student Invitations / Upload Student Invitations for <College> (<Batch>) |
| Import dialog, `spreadsheetimportdialog.jsx:99` | Download template | use this CSV. / Download sample CSV format here |
| Template file name, `AdminStudentController.php:388` | student_import_template.xlsx | Report_Student_Upload_Sample_CSV.csv |
| Import dialog, `spreadsheetimportdialog.jsx:105` | Choose file | Click on icon above to select CSV file |
| Import dialog, `spreadsheetimportdialog.jsx:168` | Upload / Import N row(s) | Upload file |
| Student detail button, `app/admin/students/[id]/page.jsx:146` | Resend Invite | Re - Send Invites (bulk) |
| (none) | — | Revoke Invites |
| (none) | — | Invitations (button) / Send Invitations (page) |
| (none) | — | Sent / Accepted / Revoked (status chips) |
| Students list "Status" filter, `page.jsx:185-192` | Status: Active / Suspended | Status: Sent / Accepted / Revoked (different concept; keep both, label ours "Account status") |
| Search label, `page.jsx:149` | Search roll no, name or email | Search student with name, roll number, email, or mobile number... |
| Empty state, `page.jsx:221` | No students match. Add one, or import a spreadsheet. | We could not find any student matching "<query>" |
| Form / detail labels, `studentformdialog.jsx:145`, `[id]/page.jsx:45` | Graduating batch | Passout Batch |
| List column / filter, `page.jsx:179, 209` | Batch | Batch / Batches (match) |
| Form / detail, `studentformdialog.jsx:117`, `[id]/page.jsx:39` | Roll number | Institute Roll Number / Roll Number |
| List column, `page.jsx:205` | Roll no | Roll Number |
| Form, `studentformdialog.jsx:118` | Full name | First Name / Middle Name / Last Name (data-model difference; keep full_name, map on import) |
| Form / detail, `studentformdialog.jsx:119`, `[id]/page.jsx:40` | Institute email | Email Address / Email |
| Form / detail, `studentformdialog.jsx:120`, `[id]/page.jsx:41` | Personal email | Personal Email Address |
| Form / detail, `studentformdialog.jsx:162`, `[id]/page.jsx:42` | Phone | Mobile (+ Mobile Country Code) |
| Form / detail, `studentformdialog.jsx:161`, `[id]/page.jsx:50` | Date of birth | Date Of Birth / DOB |
| Form / detail, `studentformdialog.jsx:159-160`, `[id]/page.jsx:51-52` | 10th % / 12th % | Xth Score / XIIth Score |
| Form / detail | Programme / Branch | Current Course Name (Superset merges; keep ours) |
| Import template headers, `StudentAccountService.php:24-28` | snake_case (`roll_no`, `full_name`, …) | Human-readable with hints, e.g. "Institute Roll Number (Mandatory)", "Gender (M/F/O)", "Date Of Birth (YYYY-MM-DD)" |
| Student detail back link, `[id]/page.jsx:139` | All Students | All Students (breadcrumb) — match |
| Admin nav, `adminshell.tsx:50` | Students | Students — match |

---

## 5. Uncertain

- **"abc" icon with a blue dot** (Students page header, left of "Invitations"): never clicked or hovered; purpose unknown (possibly a spelling/name-correction or bulk data-update tool, or the profile-update-requests shortcut, given the dot).
- **Invitations page icons** (paper-plane, document, "+"): never clicked; meanings in F13 are inferred from the icons.
- **Sample CSV columns M–S and AD–AI**: headers were truncated or off-screen. M–S start with "Current Co…" (seven columns; likely course end date, specialization/branch, score type, score, etc.); T–AC are Xth/XIIth score, aggregate, school, start/end date columns (exact text cut off); AD–AI are unseen. Total of 35 is from Excel's "Count: 35".
- **What happens after "Upload file"**: no file was uploaded, so Superset's validation, error report, duplicate handling and invite-sending confirmation are unknown.
- **Batch dropdown full list**: only 1994 and 2000–2008 were visible before 2028 was chosen; the range above 2008 and whether future batches beyond 2028/2029 exist is unknown. 1995–1999 are absent.
- **Superset "Accepted" semantics**: whether it means the student completed a Superset sign-up form (self-registration) or simply activated the account is not shown.
- **Revoke behaviour**: whether revoking blocks an already-sent link, deletes the student, or can be undone is not shown. Whether REVOKED rows are selectable is not shown (only SENT and ACCEPTED rows were on page 1).
- **"13217 students registered" > "12848 students invited"**: implies some students were registered without an invite (e.g. earlier imports), but the video does not explain this.
- **Status chip "Accepted (1000+)"**: whether the count is capped in display at 1000 or loaded lazily is unclear.
- **Profile update requests queue**: banner seen, queue never opened; which fields need approval is unknown.
- **Edit (pencil) on an invitation row**: whether it edits the invite data before acceptance or the full student profile is not shown.
- **Search behaviour on Students page**: whether it searches live as you type or on Enter, and whether it matches partial roll numbers, is not shown (only one no-result search was demonstrated).
