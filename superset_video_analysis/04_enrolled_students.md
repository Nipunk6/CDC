# Video 04: "How to See the enrolled Student" (238 s)

Source frames: `scratchpad/kf/04_enrolled_students/` (38 frames, t000m00s to t003m54s). All 38 were read in order. Small UI regions (toolbar icons, cycle-row icons, tooltip, category chip) were cropped and enlarged to read them.

What the video covers: despite its title, the presenter does not open a placement cycle's enrolment list. They open the global **Students** directory (sidebar group RELATIONSHIPS > Students), filter it, open one student's profile in a side drawer and then as a full page, and show the profile tabs. The "enrolled" part appears only on the profile's Overview tab, which lists the placement cycles the student is **Enrolled** in.

Superset URL routes seen in the address bar:
- `app.joinsuperset.com/#/students`: "Colleges and Students" landing page.
- `#/a/colleges/IITISMD_63/students?filter=s`: the student list of one college. The query becomes `filter=c` after "Clear All Filters".
- `#/a/colleges/IITISMD_63/students?filter=c&collegeCode=IITISMD_63&studentId=8c25eeb0-33e9-43b5-9d67-ca2cf56e43df`: the student quick-view drawer, opened over the list.
- `#/a/colleges/IITISMD_63/students/8c25eeb0-33e9-43b5-9d67-ca2cf56e43df`: the student's full profile page. Students are keyed by UUID.

Superset left sidebar, same in every frame (for orientation):
- Home, My Dashboards
- JOBS: Companies, Inbound Job Posts, Placements, Job Alerts (with a sparkle icon)
- RELATIONSHIPS: **Students** (highlighted throughout), CRM
- ENGAGEMENT: Notices, Surveys, Calendar
- ADMIN: Documents, TalentLens Rubrics, Admin
- REPORTS (collapsible): Launchpad, New Placements App

Other persistent elements:
- A purple top banner reads "The new Placements app is here. Try it now".
- The header shows the institute name "Indian Institute of Technology Indian School of Mines Dhanbad".
- On the right of the header: a global "Search students" box, a download icon, a bell, an app grid, and the account "Career Development Centre IIT ISM Dhanbad" with a CD avatar.

---

## 1. Video walkthrough

**0:00, Colleges and Students page (`#/students`)**
- Page title: "Colleges and Students".
- Toolbar on the right, in order:
  - a small icon with a blue notification dot (it looks like an "abc"/edit glyph; purpose unknown);
  - an **"Invitations"** button with an envelope icon;
  - an upload (tray-arrow-up) icon.
- Blue info banner: "You have profile update requests pending for approval." followed by the link "**Click to view requests.**"
- Search box, placeholder "Search student with name, roll number, email, or mobile number...".
- One college card:
  - the IIT (ISM) logo;
  - "Indian Institute of Technology Indian School of Mines Dhanbad";
  - "13217 students registered";
  - "total 12848 students invited".

**0:32, opens the college's student list (`#/a/colleges/IITISMD_63/students?filter=s`)**
- Title "Students of Indian Institute of Technology Indian Sc..." (truncated) with an (i) info icon.
- Toolbar icons on the right: upload, envelope (mail), handshake, a red filled refresh/sync button, and a vertical kebab (⋮).
- Amber banner with a lock icon: "**10** students are waiting on a No Objection Certificate.", with a "**Review Requests**" link on the right.
- Left filter panel (accordion sections, each with a chevron):
  - Department / Course
  - Course Score
  - Class X Percentage
  - Class XII Percentage
  - UG Percentage (Previous Education)
  - Attendance in Training
  - Attendance in Classroom
  - Batch
  - Student Category
  - Gender
  - Backlogs, shown as two checkboxes: "Check for current(ongoing) backlogs" and "Check for total backlogs"
  - Placement Status, continuing below
- Sticky footer of the filter panel:
  - note "All numeric values above must be in percentage(%). If you wish to filter on CGPA, please enter the percentage equivalent";
  - a blue "**Apply Filters**" button and a white "**Clear All Filters**" button;
  - a green "**Download as Excel**" button with a dropdown caret.
- Results area:
  - search field labelled "Search", placeholder "Search name, email or identification number ...";
  - while loading, an empty-state illustration with "Could not find any students".

**0:34, list loaded**
- Title becomes "**13393** Students of Indian Institute of Technology Indian Sc...".
- Table columns: **Name | Identification No. (Roll No.) | Email | Invitation Status | Mobile No. | Student Categories**.
- Each row:
  - an avatar (photo or initials) and the name as a blue link;
  - next to the name, an amber warning-triangle icon or a teal check-circle icon (most likely "profile not verified" or "verified");
  - roll numbers such as 17JE003006, 21MT0002 and 22MS0001;
  - institute and personal emails;
  - Invitation Status "REGISTERED" on every visible row;
  - a 10-digit mobile number;
  - Student Categories as an outlined chip, for example "Minor in Mining Methods &..." on row "A Ashwin".
- Footer: "Showing Page 1 of 447 (13393 records)" and pager buttons ‹ 1 2 … 446 447 ›. That works out to 30 rows per page.

**0:40**: scrolls the table and the pager stays the same.

**0:48**: scrolls the filter panel, showing **Placement Status** radio buttons:
- All Students
- Students who are Placed
- Students who are not Placed yet

**0:50**: back to the top of the filter panel.

**1:02, Batch filter applied**
- The Batch accordion is open with a multi-select containing the chip "2027 Passout Batch ×". A green badge "1 Batch" shows on the accordion header.
- The title becomes "**2043 filtered** Students of ...".
- The toolbar icons are replaced by one "**⋮Actions**" menu.
- Pager: "Showing Page 1 of 69 (2043 records)".
- The table now includes 23JE…, 24MC…, 25MS…, 25MT… rolls and a test-looking row "ABCD Hello / 04217856 / hellomd486@gmail.com".

**1:34, Gender filter added**
- The Gender accordion is open with the chip "Male ×" and a badge "1 Gender".
- Title: "1616 filtered Students...". Pager: 54 pages (1616 records).
- Further filters are now visible:
  - **Blocked Status** (radio): All Students / Students who are Blocked / Students who are not Blocked.
  - **Verified Status** (radio): All Students / Students who are Verified / Students who are not Verified (only partly visible here, fully at 1:44).

**1:44, 427 filtered (55 pages → 15 pages, "427 records")**
- The visible names are mostly female, so the presenter probably switched Gender to Female (see Uncertain).
- The panel is scrolled to the bottom, showing:
  - **Placement Status**, Blocked Status and **Verified Status** (All Students / Students who are Verified / Students who are not Verified);
  - **Invitation Status** (radio): All Students / Invited / Registered;
  - **Student Scope** (radio): "All Students" selected, other options cut off.

**1:56, percentage filters expanded**
- Class X Percentage and Class XII Percentage each have two number spinners, "Greater Than (%)" and "Less Than (%)".
- UG Percentage (Previous Education) has the same pair plus a helper text: "Matches students with a previous UG education record. Students currently pursuing UG are not included — use the CGPA filter for those."
- Batch still shows "1 Batch".

**2:00 to 2:38**: scrolls the 427-row result.

**2:40, "Clear All Filters"**
- The URL changes to `filter=c`.
- The Batch input resets to placeholder "Select Batches" and the Gender input to "Select Genders".
- The title briefly shows "427 Students of..." (no "filtered"). At 2:42 it is back to "13393 Students of ..." with 447 pages, and the toolbar icons return.

**2:46, clicks the first student, "Aakash Maheshwari"**
- A right-side drawer slides over the list (the list stays visible on the left) and shows "Loading ...".
- The URL gains `&collegeCode=IITISMD_63&studentId=<uuid>`.

**2:48, drawer header**
- Green topographic banner, close "×" at the top right.
- Large initials avatar "AM", the name "Aakash Maheshwari" and a small "open in new" icon.
- Header line: "**2021 Passout Batch | 17JE003006 | Superset ID: 325159**", then "7th Semester, B.Tech", then "Department of Engineering".
- Tabs: **OVERVIEW | ABOUT | ACADEMIC DETAILS | PROFILE | RESUMES & DOCUMENTS**.

**2:50, OVERVIEW tab**
- Top right: outlined green "**Mark profile as verified**" and outlined red "**Ask Resubmission**".
- "**Placements** (i)" section, with links "**Download Placement Report**" and "**Download Eligibility Report**" on the right.
  - Text: "Aakash has participated in the following placement cycles".
  - One row:
    - a checkbox-like icon;
    - "**Superset Placements 2020-2024**" with an open-in-new icon;
    - the status "**Enrolled**" in the middle;
    - three small icon buttons on the right: a green-filled trophy, a gear, and a list.
  - Under the row, two expanders: "› Show Applications" and "› Show Attendance".
- "**Events**" section with the expander "› Show Event Attendance".
- "**Notes**" section with the placeholder "✎... Write notes about this student" and a comment input with the CD avatar.

**2:54, ABOUT tab**
- "Overview" block with an "✎ Edit" link. Label and value rows:
  - Name: Aakash Maheshwari
  - **Contact No.**: +91 7739270134, with a green verified tick
  - Email: (address) ✓
  - Personal Email: (address) ✓
  - Date of Birth: "28 January 1999 (27 years)"
  - Gender: Male
  - Category: empty
  - **Student Categories**: empty
  - **WorkEx Month Override**: empty
- At the bottom right: "**Confirm Correctness**" (green tick) and "**Reject**" (red ×).

**2:56, ACADEMIC DETAILS tab, scrolled down**
- The previous card's footer is visible: "✎ Edit", "**Show Term-wise Details**", "No Marksheet", plus "Reject" and "Confirm Correctness".
- "**Education**" section with a "+ **Add Education**" link. Two entries:
  - "St. Josephs Sr Secondary School", CBSE, 12th, 2016 — 2017, **91.2% Score**, with "✎ Edit", "Marksheet not uploaded", "Reject" and "Confirm Correctness".
  - "G.D Mother International School", CBSE, 10th, 2014 — 2015, "**9.8 /10 CGPA**" and "**93.1% Score**", with the same footer.
- Each entry has a small status icon at its top right.

**2:58, ACADEMIC DETAILS tab, top: "Course" card**
- Red banner: "**This section needs changes**".
- Comment bubble: "Career Development Centre IIT ISM Dhanbad wrote 3 years ago", with the quote "Please upload your current semester marksheet."
- Course card:
  - "Civil Engineering", "B.Tech";
  - "Branch: Civil Engineering", "Department: Department of Engineering";
  - "2021 Passout Batch | 17JE003006";
  - "Aug 2017 — Jun 2021";
  - "**74.00 CGPA**" and "**74% Score**".

**3:00, PROFILE tab**
Each section below shows a "Student has not added … yet" line when it is empty.
- "**Education Gaps**": "Student has not added any information yet!"
- "**Internships & Work Experience**": "Student has not added any work experience yet."
- "**Technical Skills**" with a "? What to add here?" help link, and section buttons "⊗ Reject All" and "✓ Mark all as verified".
  - Each skill row has a name, 4 proficiency dots, a level word, and its own "⊗ Reject" and "✓ Mark as Verified" buttons.
  - Rows: C++ Language (3 dots, "Advance"), Machine Learning (2 dots, "Intermediate"), Python3 (2, "Intermediate"), SQL (2, "Intermediate").

**3:02, PROFILE tab, scrolled**
- "**Positions of Responsibilities**" (with "? What to add here?") and section buttons Reject All / Mark all as verified.
  - "Coordinator, Fast Forward India, Aug 2017 - Dec 2019"
  - "event organiser, truss the frame (concetto annual techno management fest), May 2018 - Aug 2018"
  - Each entry has Reject / Mark as Verified.
- "**Projects**": none added.
- "**Subjects**" (with "? What to add here?"): none added.
- "**Communication Languages**" (continues).

**3:04, PROFILE tab, further down**
- Communication Languages, **Awards and Recognitions**, **Certifications**, **Competitions** and **Conferences and Workshops**, all empty.

**3:06**: scrolls back up in Profile; the cursor hovers "RESUMES & DOCUMENTS".

**3:08 to 3:18**: back on ACADEMIC DETAILS.
- At 3:14 the cursor hovers the "Reject" button of the 12th-class entry and a faint tooltip "**Ask Re-Submission**" appears.

**3:20**: ABOUT tab again.

**3:22**: the drawer is closed and the full list (13393) shows again.

**3:34, full-page student profile (`#/a/colleges/IITISMD_63/students/<uuid>`)**
- Reached by typing the roll number in the global header search "Search students": the box shows "17JE003006" with a clear ×.
- Left profile card:
  - wave banner and "AM" avatar;
  - "Aakash Maheshwari", "2021 Passout Batch | 17JE003006", "Superset Id: 325159", "7th Semester, B.Tech", "Department of Engineering";
  - three big stats: "**74.00** CGPA", "**1** Applications", "**0** Offers".
- Right side: the same five tabs. OVERVIEW shows the same Placements, Events and Notes content as the drawer.

**3:36, full page, top strip**
- Red error strip: "**Student profile has been rejected. Waiting for re-submission**" with a "View Details" link.
- On the right, a filled green "**Mark as verified**" button and an outlined red "**Ask Resubmission**" button.

**3:38, RESUMES & DOCUMENTS tab**
- "**Resume**" section with "✓ Mark all as verified" and "⊗ Reject all".
- One resume row:
  - PDF icon and a blue star (most likely "default resume");
  - file name "AakashMaheshwari_17JE003006 (1 ..." with a pencil (rename) icon;
  - "Created 08:24 PM 30 Nov 2020 | ⤓ Download";
  - on the right, "✓ Mark as Verified" and "⊗ Reject".
- Footnote: "** Resumes were created using Superset Resume Builder. Any other resume here was created outside the Superset app."
- "**Documents**" section: "Student has not uploaded any document yet".

**3:40**: OVERVIEW again, while ABOUT is being clicked.

**3:46, ABOUT tab (full page)**
- Same Overview block; the label reads "**Contact**" here, not "Contact No.".
- Below it, a new "**Address**" section with "**Permanent Address**": "smriti plaza flat no.403,Motijheel, Muzaffarpur, Bihar, 842001".

**3:48 to 3:54, ACADEMIC DETAILS tab (full page)**
- The same Course card with its "This section needs changes" note, then the Education entries. End of video.

---

## 2. Superset features shown

Our evidence paths are relative to `/Users/admin/Desktop/CDC-main/CDC/`.

### F1. Students directory: "Colleges and Students" landing
- **Superset name and location:** "Colleges and Students", from the sidebar RELATIONSHIPS > Students (`#/students`).
- **What it does:**
  - Lists the colleges the account manages, one card each, with "N students registered" and "total N students invited".
  - Page-level search box "Search student with name, roll number, email, or mobile number...".
  - Toolbar with an "Invitations" button, an upload icon, and an icon with a blue notification dot.
  - Banner "You have profile update requests pending for approval. Click to view requests."
- **Who uses it:** admin (CDC).
- **Our status: PARTIAL.**
  - We are a single institute, so the college level is not needed.
  - The admin Students page is `frontend/app/admin/students/page.jsx` (PageHeader at lines 113-131, subtitle `${meta.total} student(s)`).
  - We do not track "invited" versus "registered" counts. Nothing records that a student has set a password: `student_invite_tokens` holds only email, token and created_at (`backend/database/migrations/2026_10_01_000019_create_student_invite_tokens_table.php`), and `users` has no such flag.
  - There is no pending-requests banner on the Students page. Branch-change requests live on a separate page, `frontend/app/admin/branch-changes/page.jsx` (title "Branch Change Requests", line 82).
  - Our search covers roll number, name and institute email (`AdminStudentController.php` lines 81-88), but not mobile number.
- **Naming:**
  - Ours "Students" (nav, `components/admin/adminshell.tsx` line 50) matches Superset "Students".
  - Ours "Branch Change Requests" versus Superset "profile update requests".
  - Our search label "Search roll no, name or email" versus Superset "Search student with name, roll number, email, or mobile number...".
- **Conflict check:** Superset's "profile update requests" means students can request changes to any profile field. We decided that students can request only a **branch change**, and that academic fields are locked (CDC_PORTAL_CONTEXT §2.3 and §3 "Students"). Widening this to general profile-update requests is **NEEDS OWNER DECISION**. A pending-count banner for our existing branch-change queue has no conflict.

### F2. Student list with count title and pagination
- **Superset name and location:** "N Students of <college>". When filters are applied it reads "N filtered Students of <college>". Reached from Students > the college card.
- **What it does:**
  - Paginated table, 30 per page, footer "Showing Page X of Y (N records)", pager with first, last and ellipsis.
  - Columns: **Name** (avatar, linked name, verified or unverified icon), **Identification No. (Roll No.)**, **Email**, **Invitation Status** (value "REGISTERED", or "INVITED" by the filter), **Mobile No.**, **Student Categories** (chips).
  - Search inside the list: "Search name, email or identification number ...".
  - Empty state: "Could not find any students".
  - (i) info icon beside the title.
- **Who uses it:** admin.
- **Our status: PARTIAL.**
  - `frontend/app/admin/students/page.jsx`. Columns (lines 205-213): Roll no, Name, Programme, Branch, Batch, CGPA, Backlogs, Active (a suspend switch), View.
  - API `GET /admin/students` (`backend/routes/api.php` line 95; `AdminStudentController::index` lines 66-111; payload `listPayload` lines 525-541) returns no phone number.
  - 50 per page (`AdminStudentController::PER_PAGE`, line 30).
  - Missing:
    - Email column;
    - Mobile No. column, which also needs `phone` in `listPayload`;
    - Invitation Status column;
    - Student Categories column;
    - a profile-verified indicator;
    - an avatar in the row;
    - the "N filtered" wording;
    - a "Showing Page X of Y (N records)" footer: we only show `subtitle` "N student(s)" and an MUI Pagination.
  - We have extra columns Superset does not show (Programme, Branch, Batch, CGPA, Backlogs, Active). Those are worth keeping.
- **Naming:**
  - "Roll no" versus "Identification No. (Roll No.)".
  - Empty state "No students match. Add one, or import a spreadsheet." versus "Could not find any students".
  - Subtitle "N student(s)" versus title "N Students of …" or "N filtered Students of …".
  - Recommend changing the column header to "Roll No." and keeping "Identification No." only if the owner wants exact parity. Low value.
- **Conflict check:** none.

### F3. "Apply Filters" panel on the student list
- **Superset name and location:** the left filter panel on the student list, with footer buttons "Apply Filters" and "Clear All Filters".
- **What it does:** accordion filters, each showing a green count badge once set (for example "1 Batch", "1 Gender"). All visible filters:

| Superset filter | Control and options seen |
|---|---|
| Department / Course | accordion (contents not opened) |
| Course Score | accordion (not opened; percentage range by the footer note) |
| Class X Percentage | "Greater Than (%)" and "Less Than (%)" number spinners |
| Class XII Percentage | same two spinners |
| UG Percentage (Previous Education) | same two spinners, plus the helper text "Matches students with a previous UG education record. Students currently pursuing UG are not included — use the CGPA filter for those." |
| Attendance in Training | accordion (not opened) |
| Attendance in Classroom | accordion (not opened) |
| Batch | multi-select, placeholder "Select Batches", values like "2027 Passout Batch" |
| Student Category | accordion (not opened) |
| Gender | multi-select, placeholder "Select Genders", value "Male" |
| Backlogs | checkboxes "Check for current(ongoing) backlogs" and "Check for total backlogs" |
| Placement Status | radio: All Students / Students who are Placed / Students who are not Placed yet |
| Blocked Status | radio: All Students / Students who are Blocked / Students who are not Blocked |
| Verified Status | radio: All Students / Students who are Verified / Students who are not Verified |
| Invitation Status | radio: All Students / Invited / Registered |
| Student Scope | radio: All Students (selected) / further options cut off |

  - Rule shown: "All numeric values above must be in percentage(%). If you wish to filter on CGPA, please enter the percentage equivalent".
  - "Clear All Filters" resets every filter and reloads the full list.
- **Who uses it:** admin.
- **Our status: PARTIAL.**
  - We have (`frontend/app/admin/students/page.jsx` lines 145-197; backend validation at `AdminStudentController.php` lines 68-98):
    - search;
    - Programme (single select);
    - Branch (single select, enabled only after a programme is chosen);
    - Batch (one number field);
    - Status (All / Active / Suspended, which is the account switch);
    - a "Search" button.
  - Missing:
    - Gender filter (we store `gender`, so this is easy);
    - Class X and Class XII % ranges (we store `tenth_percent` and `twelfth_percent`);
    - a CGPA range (we store `current_cgpa`; Superset uses "Course Score" in %, but we work in CGPA);
    - Backlogs filters (we store `ongoing_backlogs` and `total_backlogs`);
    - Placement Status placed / not placed (derivable from `offers` with the D80 definition: any offer other than `ppo_offered`);
    - Blocked Status (derivable from active `placement_blocks`);
    - Invitation Status (needs a new "password set / registered" marker);
    - Verified Status (see F8);
    - UG % (no field);
    - Student Category tags (no field);
    - Attendance in Training and Classroom (no concept);
    - Student Scope (meaning unknown);
    - multi-select for batch, programme and branch;
    - a "Clear All Filters" button;
    - per-filter count badges.
  - On the cycle page's enrolled list (`frontend/app/admin/placement-cycles/[id]/page.jsx` lines 497-510) the only filter is one search box. The backend accepts `status=active|suspended` (`AdminPlacementCycleController.php` lines 138-150), but the UI never sends it.
- **Naming:**
  - "Programme"/"Branch" versus "Department / Course".
  - "Batch" versus "Batch" (values: ours "2027", Superset "2027 Passout Batch").
  - "Status: Active/Suspended" versus Superset "Blocked Status". These are not the same concept: ours is account suspension, Superset's "Blocked" is likely placement blocking.
  - "Search" button versus "Apply Filters"; we have no "Clear All Filters".
  - Superset "Class X Percentage" and "Class XII Percentage" versus our field labels "10th %" and "12th %".
- **Conflict check:**
  - Gender, %, CGPA, backlogs, placed, blocked and batch filters: no conflict.
  - Attendance in Training and Classroom: we have no training or classroom attendance. Building it is new scope and needs the owner.
  - Invitation Status: no conflict, but it needs a new tracked field (new behaviour, so ask the owner per §10 "Ask the owner before any product-behaviour change").
  - Verified Status depends on F8, which is **NEEDS OWNER DECISION**.

### F4. "Download as Excel" from the filtered student list
- **Superset name and location:** the green "Download as Excel" button with a dropdown caret, at the bottom of the filter panel.
- **What it does:** exports the currently filtered students. The caret suggests format or column options; it was not opened.
- **Who uses it:** admin.
- **Our status: PARTIAL.**
  - There is no export on `/admin/students`.
  - Per cycle there is "Export Students" (`frontend/app/admin/placement-cycles/[id]/page.jsx` lines 278-285, which calls `GET /admin/placement-cycles/{cycle}/students/export`, `backend/routes/api.php` line 91, `ExportService::studentsWorkbook` lines 157-166). It exports every enrolled student with fixed columns: Roll No, Name, Programme, Branch, Batch, Gender, CGPA, backlogs, 10th/12th %, Category, PwD, Home State, emails, Phone, Account, Enrolment, live applications, Offers, Best CTC and stipend, Active Blocks. It ignores any filter.
  - Missing: an export of the global student list, and an export that respects the active filters.
- **Naming:** "Export Students" versus "Download as Excel".
- **Conflict check:** none. Exports are admin-only and admins "can export everything" (CDC_PORTAL_CONTEXT §3 Exports). The export must be audit-logged like `cycle.export`.

### F5. Bulk actions on the student list (toolbar icons and the "Actions" menu)
- **Superset name and location:** toolbar icons on the list (upload, envelope, handshake, red refresh, ⋮). They become a single "⋮Actions" menu when filters are applied.
- **What it does:** not demonstrated in the video.
  - The icons suggest: upload or import students; email the listed students; something CRM-related (handshake); sync or refresh the data (red); and more actions.
- **Who uses it:** admin.
- **Our status: PARTIAL.**
  - Upload or import: IMPLEMENTED as "Import" (bulk student import with a dry-run preview) and "Update Academics" (bulk CGPA and backlog update) (`frontend/app/admin/students/page.jsx` lines 121-129; routes `backend/routes/api.php` lines 97-100).
  - Email the listed or filtered students: NOT IMPLEMENTED. Our student mail goes only through fixed triggers (E1 to E10).
  - Sync: the future academic sync is planned (`StudentAcademicSyncService`); today it is a manual re-upload.
  - Bulk actions on a filtered set (for example enrol the filtered students into a cycle, or suspend them): NOT IMPLEMENTED.
- **Naming:** "Import" and "Update Academics" versus icon-only buttons in Superset. Nothing to rename.
- **Conflict check:** ad-hoc email to a filtered student list is a new communication channel the owner has not decided on. The owner fixed the mail set and the BCC-batch rule (§3 "Bulk mail", "Mail"). **NEEDS OWNER DECISION.**

### F6. No Objection Certificate (NOC) request queue
- **Superset name and location:** the amber banner on the student list, "N students are waiting on a No Objection Certificate.", with a "Review Requests" link.
- **What it does:** students request an NOC (usually for an off-campus or external offer or internship) and the CDC reviews the requests.
- **Who uses it:** a student requests it; the admin reviews it.
- **Our status: NOT IMPLEMENTED.** `grep -i "no objection|noc"` finds nothing in `backend/app`, `frontend/app` or `frontend/components`.
- **Naming:** none on our side.
- **Conflict check:** none is recorded, but this is a new module, so it needs owner approval (§10). It also touches the "placed elsewhere" and blocking rules if an NOC is for an outside offer, so ask the owner how an NOC should interact with blocks.

### F7. Student quick-view drawer and full profile page, header and summary card
- **Superset name and location:** clicking a name opens a right-side drawer over the list (URL `?studentId=<uuid>`). The open-in-new icon, or the global search, opens the full page `/students/<uuid>`.
- **What it shows:**
  - Header: avatar (photo or initials), name, "<YYYY> Passout Batch | <Roll No> | Superset ID: <n>", "<n>th Semester, <Degree>", "Department of <X>".
  - On the full page, a left card with big stats: CGPA (74.00), Applications (1), Offers (0).
  - Tabs: OVERVIEW / ABOUT / ACADEMIC DETAILS / PROFILE / RESUMES & DOCUMENTS.
- **Who uses it:** admin.
- **Our status: PARTIAL.**
  - Our detail is a separate page, `frontend/app/admin/students/[id]/page.jsx`:
    - header title = name, subtitle "roll · branch · batch" (lines 134-158);
    - actions "Edit", "Resend Invite", "Suspend"/"Reactivate";
    - tabs "Overview / Cycles (n) / Applications (n) / Offers & Blocks / Audit trail" (lines 177-183);
    - the photo shows as an Avatar in the Overview tab (lines 186-191).
  - API `GET /admin/students/{studentProfile}` (`AdminStudentController::show`, lines 113-165).
  - Missing:
    - a quick-view drawer from the list (ours navigates away);
    - current semester and department (not stored; `student_profiles` columns are in `backend/database/migrations/2026_09_21_162407_create_student_profiles_table.php` lines 15-38);
    - a summary stats card (CGPA, Applications count, Offers count). The data is already in the payload: `current_cgpa`, `applications`, `offers`.
  - A Superset ID equivalent is not needed; the roll number is our identifier.
- **Naming:**
  - Ours "Graduating batch" versus Superset "Passout Batch" ("2021 Passout Batch").
  - Ours tab "Overview" holds personal and academic fields, which Superset splits into "About" and "Academic Details". Superset's "Overview" holds cycles, applications, events and notes.
  - Ours "Cycles (n)" versus Superset "Placements" (section heading) and "placement cycles" (body text).
  - Ours back label "All Students"; Superset has none (drawer close ×).
- **Conflict check:** none for layout or naming. Storing semester and department is a small new data field (ask the owner).

### F8. Profile verification workflow (whole profile and per section or item)
- **Superset name and location:**
  - Whole profile: "Mark profile as verified" / "Mark as verified" and "Ask Resubmission" buttons on the drawer and full page, with a status strip "Student profile has been rejected. Waiting for re-submission" and a "View Details" link.
  - Per section: "Confirm Correctness" and "Reject". Reject's tooltip reads "Ask Re-Submission".
  - Per item and per section on the Profile and Resumes tabs: "Mark as Verified", "Reject", "Mark all as verified", "Reject All".
  - A rejected section shows a red "This section needs changes" banner and the CDC's comment ("Career Development Centre IIT ISM Dhanbad wrote 3 years ago: Please upload your current semester marksheet.").
  - The list shows a verified (teal check) or unverified (amber triangle) icon next to each name, and a "Verified Status" filter.
- **What it does:** students fill in their own profile. The CDC verifies each piece or sends it back with a comment, and the student re-submits.
- **Who uses it:** admin verifies; student edits and re-submits.
- **Our status: NOT IMPLEMENTED for profiles. IMPLEMENTED for resumes only** (F12).
  - There is no verification status on `student_profiles` (migration lines 15-38) and no verify or resubmit endpoints in `backend/routes/api.php` lines 95-106.
- **Naming:** none on our side for profiles.
- **Conflict check: NEEDS OWNER DECISION.** The owner decided that students are created only by the CDC and "can edit only personal email, phone, home state, LinkedIn, GitHub and photo; academic fields are locked" (CDC_PORTAL_CONTEXT §2.3), and that CGPA and backlogs come from the CDC or the institute sync (§3 Students).
  - Superset's model (student-entered data, CDC verify or reject with resubmission) contradicts that for academic data.
  - It could still apply to new student-entered sections (F10) if the owner adds them.

### F9. Overview tab: Placements (cycle enrolment), applications, attendance, events, notes, reports
- **Superset name and location:** the OVERVIEW tab of the student drawer or page.
- **What it does:**
  - "**Placements** (i)": "<First name> has participated in the following placement cycles".
    - One row per cycle: cycle name with a link, status "**Enrolled**", and three icon buttons (trophy, gear, list; their purpose is not shown).
    - Expanders "Show Applications" and "Show Attendance" per cycle.
  - "**Download Placement Report**" and "**Download Eligibility Report**" links (per student).
  - "**Events**" section with "Show Event Attendance".
  - "**Notes**" section: "Write notes about this student", a comment box with the admin's avatar (internal notes).
- **Who uses it:** admin.
- **Our status: PARTIAL.**
  - Cycle enrolment: IMPLEMENTED as the "Cycles (n)" tab (`frontend/app/admin/students/[id]/page.jsx` lines 207-245). Columns: Cycle (linked), Type, Cycle status, Enrolment (chip "Active"/"Suspended"), Enrolled on.
  - Applications: IMPLEMENTED across all cycles in the "Applications (n)" tab (lines 247-286). Columns: Posting (linked), Company, Status, Flags ("Unverified resume", "Placed elsewhere"), Applied. They are not grouped per cycle.
  - Attendance per student: NOT shown. Round attendance exists in `application_round_results` but does not appear on the student page.
  - Offers and blocks: IMPLEMENTED ("Offers & Blocks" tab, lines 288-328). Superset puts these behind the cycle-row icons.
  - Event attendance: NOT IMPLEMENTED (we have no RSVP or attendance).
  - Notes about a student: NOT IMPLEMENTED. No notes table or endpoint; the audit trail is the only history (lines 330-362).
  - Placement Report and Eligibility Report per student: NOT IMPLEMENTED. `EligibilityService::check()` could produce a per-drive eligible/ineligible list with reasons.
- **Naming:**
  - Enrolment status: ours "active"/"suspended" (chip text from `titleCase(enrollment.status)`, and raw lowercase "active" on the cycle page, `placement-cycles/[id]/page.jsx` line 548) versus Superset "**Enrolled**". Recommend showing "Enrolled" for `active`. The stored value stays as is (§10: never rename columns).
  - Tab "Cycles" versus Superset section "Placements" / "placement cycles".
- **Conflict check:**
  - **Event attendance is NEEDS OWNER DECISION.** Events are "announce only, with no RSVP" and RSVP is listed under "Excluded unless the owner asks" (§3 Events, §11).
  - Notes and reports have no conflict.

### F10. Student profile content (About, Academic Details, Profile sections)
- **Superset name and location:** the ABOUT, ACADEMIC DETAILS and PROFILE tabs.
- **What it shows:**
  - **About > Overview** (with "Edit"): Name; Contact No. (or Contact) with a verified tick; Email ✓; Personal Email ✓; Date of Birth with age ("28 January 1999 (27 years)"); Gender; Category; Student Categories; WorkEx Month Override.
  - **About > Address**: Permanent Address.
  - **Academic Details > Course**: programme name, degree, Branch, Department, "<Batch> Passout Batch | <Roll>", start to end dates ("Aug 2017 — Jun 2021"), CGPA (74.00) and Score % (74%), "Show Term-wise Details", marksheet status ("No Marksheet"), Edit.
  - **Academic Details > Education** ("+ Add Education"): one entry per school or degree with institution, board, class (12th or 10th), years, Score % and/or CGPA ("9.8 /10"), marksheet status ("Marksheet not uploaded"), Edit.
  - **Profile**: Education Gaps; Internships & Work Experience; Technical Skills (name, 4-level proficiency with levels such as "Intermediate" and "Advance"); Positions of Responsibilities (title, organisation, from–to); Projects; Subjects; Communication Languages; Awards and Recognitions; Certifications; Competitions; Conferences and Workshops. Each has a "What to add here?" help link where shown.
- **Who uses it:** the student fills it in; the admin views, edits and verifies.
- **Our status: PARTIAL** (About and Academic). **NOT IMPLEMENTED** (Profile sections, address, marksheets, term-wise grades, education history).
  - Our admin Overview fields (`frontend/app/admin/students/[id]/page.jsx` lines 39-59): Roll number, Institute email, Personal email, Phone, Programme, Branch, Graduating batch, CGPA, Ongoing backlogs, Total backlogs, Gender, Date of birth, 10th %, 12th %, Category, PwD, Home state, LinkedIn, GitHub.
  - The admin edits these through `StudentFormDialog` ("Edit", line 142; `PATCH /admin/students/{id}`, which is audited and sends E8).
  - Not stored: permanent address; school names, boards and years for 10th and 12th; UG % for PG students; course start and end dates; semester-wise grades; marksheet files; "Student Categories" tags; WorkEx months; and every Profile-tab section.
- **Naming:**
  - "Phone" versus "Contact No." (About) and "Mobile No." (list).
  - "Institute email" versus "Email".
  - "Date of birth" versus "Date of Birth" (Superset also shows the age).
  - "10th %" and "12th %" versus "Class X Percentage" and "Class XII Percentage" (filters) and "10th"/"12th" Score (Education).
  - "Graduating batch" versus "Passout Batch".
  - "CGPA" is the same.
  - "Category" is the same, but Superset also has a separate "Student Categories" (tags).
- **Conflict check: NEEDS OWNER DECISION.** Student-entered academic history and verified skills, projects and similar sections contradict "academic fields are locked" and the student's limited edit set (§2.3).
  - CDC-entered additions (address, school details, UG %) would not conflict, but they are new data, so ask the owner.

### F11. Global "Search students" in the header
- **Superset name and location:** the header search "Search students", available on every page.
- **What it does:** jumps straight to a student's full profile by roll number (the presenter typed "17JE003006").
- **Who uses it:** admin.
- **Our status: NOT IMPLEMENTED.** `components/admin/adminshell.tsx` has no search; `grep -i search` there returns nothing. The search exists only on the Students page.
- **Naming:** n/a.
- **Conflict check:** none.

### F12. Resumes & Documents tab per student
- **Superset name and location:** the RESUMES & DOCUMENTS tab of the student profile.
- **What it does:**
  - "Resume" list, each row with: PDF icon, a star (default or primary resume), file name with a rename pencil, "Created <time> <date> | Download", and per-row "Mark as Verified" / "Reject".
  - Section-level "Mark all as verified" / "Reject all".
  - Footnote about the Superset Resume Builder.
  - "Documents" section for other student-uploaded documents ("Student has not uploaded any document yet").
- **Who uses it:** admin verifies; student uploads.
- **Our status: PARTIAL.**
  - Resume verification is IMPLEMENTED as a queue: `frontend/app/admin/resumes/page.jsx`, title "Resume Verification" (line 126), tabs Pending/Approved/Rejected with counts, search by roll number or name, buttons "Approve" (line 231) and "Reject" (line 244) with a required remark ("What should the student fix?", line 286). API at `AdminResumeController::index` (line 28), `update` (line 82), routes lines 108-110.
  - Missing:
    - a resumes tab on the admin **student** page (to see one student's resumes, the admin must search the queue);
    - "Mark all as verified" / "Reject all" bulk actions;
    - a default-resume star (no `is_default` column; `resumes` migration lines 15-27);
    - a Documents section (no student documents table);
    - a resume builder. Ours is PDF upload only, by decision (§3 Resumes).
- **Naming:**
  - "Approve" / status "approved" versus "**Mark as Verified**" / "Verified".
  - "Reject" is the same.
  - "Resume Verification" (page) versus "Resume" (section).
  - Suggest renaming the button "Approve" to "Mark as Verified" and the status chip "Approved" to "Verified". Display text only; the stored enum `approved` stays (§10).
- **Conflict check:**
  - A resume builder conflicts with the "PDF only" decision (§3 Resumes): **NEEDS OWNER DECISION** if wanted.
  - A Documents upload (marksheets and similar) is new scope (ask the owner).
  - The rest has no conflict.

### F13. Invitations
- **Superset name and location:** the "Invitations" button on the Colleges and Students page, the "Invitation Status" column (REGISTERED or INVITED), and the "Invitation Status" filter (All Students / Invited / Registered). The landing page also counts "students registered" and "students invited".
- **What it does:** tracks who was invited and who has completed registration, and manages invitations.
- **Who uses it:** admin.
- **Our status: PARTIAL.**
  - Invitations are sent on create and import (`AdminStudentController.php` lines 179 and 364) and can be resent per student ("Resend Invite", `frontend/app/admin/students/[id]/page.jsx` lines 145-147; `POST /admin/students/{id}/resend-invitation`, audited `student.invite_resend`, lines 257-262).
  - Missing: an "invited versus registered" status (no field records that the student set a password or ever logged in), a column, a filter, and a list or bulk resend of pending invitations.
- **Naming:** "Resend Invite" versus "Invitations"; statuses "Invited" and "Registered" (we have none).
- **Conflict check:** none. It needs a new marker such as the time the password was first set (new migration; ask the owner per §10).

### F14. Enrolled-students view inside a placement cycle (our counterpart; not shown in the video)
The video title promises the enrolled students, but Superset showed them only through the profile's "Placements: Enrolled" row. Our natural counterpart is the cycle page tab, so it is recorded here for completeness.
- **Our implementation:** "Enrolled Students (N)" tab on `frontend/app/admin/placement-cycles/[id]/page.jsx` (tab at line 322, content at lines 403-574).
  - "Enrol Students": paste roll numbers or upload a file.
  - List columns (lines 526-532): Roll No, Name, Programme, Branch, Batch, Status, Actions ("Remove from cycle").
  - Search "Roll no, name, branch..." (line 500); 50 per page.
- **Our status against Superset list capabilities: PARTIAL.** Missing:
  - The name or roll number does not link to the student page (lines 538-539 are plain text), while Superset names open the profile.
  - No filters (gender, batch, CGPA, placed or not placed, blocked, and so on).
  - No email or phone columns.
  - The status chip shows raw "active" and not "Enrolled".
  - No action to set an enrolment to `suspended`. The enum exists (`cycle_enrollments.status`, migration `2026_09_27_000005` line 18), `EligibilityService` honours it (line 48), and the unenroll refusal tells the admin to "suspend instead" (`AdminPlacementCycleController::unenroll`, line 303). But no route exists: `backend/routes/api.php` lines 90-93 hold only list, export, enrol and unenrol.
- **Naming:** tab "Enrolled Students" fits Superset's "Enrolled". Status "active" versus "Enrolled"; "suspended" has no Superset equivalent in the video.
- **Conflict check:** none. A suspend-enrolment action is implied by D88(c) and by the existing schema.

---

## 3. Gap summary

| Superset name | What to build | Priority | Conflicts |
|---|---|---|---|
| Student list columns (Email, Mobile No., Invitation Status, verified icon) | Add Email and Phone columns (add `phone` to `listPayload`), a photo avatar, and a "Showing Page X of Y (N records)" footer; title "N filtered students" when filters are set | Medium | None |
| Apply Filters: Gender, Class X %, Class XII %, CGPA (Course Score), Backlogs | Add gender multi-select, 10th/12th/CGPA "greater than / less than" ranges, ongoing/total backlog filters, multi-select batch/programme/branch, a "Clear All Filters" button and per-filter count badges, on `/admin/students` and on the cycle enrolled list | High | None |
| Placement Status filter (Placed / Not Placed yet) | Filter by having an offer other than `ppo_offered` (D80), optionally per cycle | High | None |
| Blocked Status filter | Filter by an active `placement_blocks` row | Medium | None |
| Invitation Status (column, filter, "Invitations", registered/invited counts) | Record when a student first sets a password; show Invited/Registered; filter; list and bulk-resend pending invitations | Medium | New field, owner approval (§10) |
| Download as Excel (filtered list) | Export of `/admin/students` honouring the current filters (and of the cycle enrolled list), audit-logged | Medium | None |
| Global "Search students" | Header search by roll number or name in `adminshell.tsx` that jumps to `/admin/students/{id}` | Medium | None |
| Student profile summary card | CGPA, Applications count and Offers count on the student page (data already returned) | Low | None |
| Student quick-view drawer | Open a student in a side drawer from the list without leaving it | Low | None |
| Placements section: "Show Applications" / "Show Attendance" per cycle | Group applications by cycle on the student page and show round attendance and outcomes per application | Medium | None |
| Download Placement Report / Download Eligibility Report (per student) | Per-student Excel/PDF: applications and outcomes; eligibility per drive with `EligibilityService` reasons | Medium | None |
| Notes ("Write notes about this student") | Admin-only internal notes per student (author, time), audit-logged, never shown to students | Medium | None (new feature; owner approval) |
| Enrolment shown as "Enrolled"; enrolment suspend | Show "Enrolled" for `active`; add an action and route to suspend or reactivate an enrolment; link names on the cycle list to the student page | High | None (implied by D88(c) and the schema) |
| Resumes & Documents tab per student | Resumes tab on the admin student page; "Mark all as verified" / "Reject all"; optional default-resume star | Medium | None |
| Documents (student uploads, marksheets) | Student document uploads with admin view | Low | New scope, owner approval |
| Profile update requests banner | Pending count banner on Students for our branch-change queue (no conflict); general profile-update requests | Low | General requests: NEEDS OWNER DECISION (students may request only branch changes) |
| Profile verification (Mark profile as verified, Ask Resubmission, Confirm Correctness, Reject, Verified Status filter, "This section needs changes" comments) | Per-profile and per-section verification with resubmission comments | Low | **NEEDS OWNER DECISION** (academic fields locked, students edit only contact fields, §2.3) |
| Profile sections (Skills, Positions of Responsibilities, Projects, Internships & Work Experience, Education Gaps, Subjects, Languages, Awards, Certifications, Competitions, Conferences) | Student-entered profile sections | Low | **NEEDS OWNER DECISION** (same rule) |
| Academic Details: education history, term-wise details, marksheets, course dates, UG % (Previous Education), Address, Semester, Department, Student Categories tags, WorkEx Month Override | New profile data, CDC-entered or synced | Low | Student-entered: NEEDS OWNER DECISION; CDC-entered: new data, owner approval |
| No Objection Certificate requests | NOC request and review queue with a banner | Low | New module; ask how an NOC interacts with blocks and placed-elsewhere |
| Bulk actions: email filtered students, Actions menu | Email or act on the filtered list | Low | Ad-hoc email: **NEEDS OWNER DECISION** (fixed mail set, §3 Mail and Bulk mail) |
| Event attendance ("Show Event Attendance") | Track attendance at events per student | Low | **NEEDS OWNER DECISION** (events are announce-only, no RSVP, §3 and §11) |
| Attendance in Training / Classroom filters | Training and classroom attendance data | Low | New scope, owner approval |

---

## 4. Rename list

Display text only. Stored values, columns, routes and response keys must not be renamed (§10).

| Where it appears in our UI or code | Our current name | Superset name |
|---|---|---|
| Admin nav (`components/admin/adminshell.tsx` line 48) and cycle list page title (`app/admin/placement-cycles/page.jsx` line 217) | Cycles / Placement Cycles | Placements (sidebar) / "placement cycles" (body text) |
| Student detail tab (`app/admin/students/[id]/page.jsx` line 179) | Cycles (n) | Placements |
| Enrolment status chip (`app/admin/students/[id]/page.jsx` line 237; `app/admin/placement-cycles/[id]/page.jsx` line 548) | active (raw lowercase on the cycle page) / Active | Enrolled |
| Students list column (`app/admin/students/page.jsx` line 205) | Roll no | Identification No. (Roll No.) |
| Cycle enrolled list column (`app/admin/placement-cycles/[id]/page.jsx` line 526) | Roll No | Identification No. (Roll No.) |
| Students list search label (`app/admin/students/page.jsx` line 149) | Search roll no, name or email | Search name, email or identification number ... |
| Students list filter button (`app/admin/students/page.jsx` line 194) | Search | Apply Filters (plus Clear All Filters) |
| Students list filters (lines 156-176) | Programme / Branch | Department / Course |
| Students list filter (line 186) | Status (Active / Suspended) | none (Superset's "Blocked Status" is a different concept; do not rename) |
| Students list empty state (line 221) | No students match. Add one, or import a spreadsheet. | Could not find any students |
| Students page subtitle (line 116) | N student(s) | N Students / N filtered Students |
| Student fields (`app/admin/students/[id]/page.jsx` lines 39-59; student profile `app/student/profile/page.jsx` lines 36-57) | Phone | Contact No. (profile) / Mobile No. (list) |
| Same | Institute email | Email |
| Same | Graduating batch | Passout Batch (e.g. "2021 Passout Batch") |
| Same | 10th % / 12th % | Class X Percentage / Class XII Percentage (filters); 10th / 12th Score (Education) |
| Same | Date of birth | Date of Birth (Superset also shows the age) |
| Student detail tabs (line 178) | Overview (personal plus academic fields) | About + Academic Details (Superset's "Overview" holds placements, events and notes) |
| Cycle page export button (`app/admin/placement-cycles/[id]/page.jsx` line 284) | Export Students | Download as Excel |
| Student detail button (line 146) | Resend Invite | Invitations (Superset's management entry); statuses Invited / Registered |
| Resume queue buttons (`app/admin/resumes/page.jsx` lines 231 and 244) | Approve / Reject | Mark as Verified / Reject (plus Mark all as verified / Reject all) |
| Resume status chips and tabs (`app/admin/resumes/page.jsx` lines 152 and 215) | Approved / Pending / Rejected | Verified / (not verified) / Rejected |
| Branch-change page title (`app/admin/branch-changes/page.jsx` line 82) | Branch Change Requests | profile update requests (Superset's wording is broader; rename only if the owner widens the scope) |

---

## 5. Uncertain

- **1:44, 427 filtered:** the frame does not show the Gender accordion. The drop from 1616 (Male) to 427 and the mostly female names suggest a switch to Female, but this was not seen.
- **Name icons:** I take the amber triangle and teal check-circle beside names to mean "profile not verified / verified", but no tooltip was shown.
- **List toolbar icons (upload, envelope, handshake, red refresh, kebab) and the "⋮Actions" menu:** none was clicked, so their functions are inferred from the icons. The handshake may link to CRM, and the red refresh may be a sync.
- **The small dotted icon on the Colleges and Students toolbar:** purpose unknown.
- **The three icons on the cycle row in Placements (green trophy, gear, list):** purpose not shown (possibly offers or placement status, enrolment settings, and an application list or log).
- **"Show Applications", "Show Attendance" and "Show Event Attendance":** never expanded, so their columns are unknown.
- **Filters not opened:** "Department / Course", "Course Score", "Attendance in Training", "Attendance in Classroom" and "Student Category". Their options are unknown. "Student Scope" showed only "All Students"; the other options were cut off.
- **"Download as Excel" dropdown caret:** not opened.
- **(i) info icons** next to the list title and the "Placements" heading: tooltip text not shown.
- **"View Details"** on the "Student profile has been rejected" strip: not clicked.
- **3:08 and 3:40:** the tab underline and the content do not match (transition frames). I assumed the presenter was moving between tabs.
- **Default-resume star:** "star = default resume" is inferred; no tooltip was shown.
- **Pagination size:** 30 per page is computed from 13393 / 447 records; it was not stated.
- **"Edit" in the About and Academic sections:** not clicked, so the edit form fields are unknown.
- **"Superset ID":** an internal Superset identifier. Assumed not needed for us.
