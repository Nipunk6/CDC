# 06 - "How to Publish Different stages of hiring" (Superset admin, 436 s) vs CDC portal

Source: 91 key frames in `kf/06_hiring_stages/`, all read in timestamp order. The video has no captions, so the narration below is inferred from what is on screen. Our code was checked under `/Users/admin/Desktop/CDC-main/CDC` (backend Laravel, frontend Next.js). Line numbers are from the current working tree.

Before reading the comparison, note how the two models map:
- **Superset** calls each selection step a **Stage** (Stage 1 "Resume Shortlisting", Stage 2 "Online test", Stage 3 "Technical interview"). You work on the page **"Shortlist for <NEXT stage>"**. It lists the candidates "participating in <current stage>", and you pick who goes on to the next stage. The URL is `.../jobprofiles/<uuid>/stages/1/shortlist`, so the page belongs to stage 1 and its output is the shortlist for stage 2.
- **Ours** calls each step a **Round** (`posting_rounds`). A result (`selected` / `rejected` / `waitlisted`, plus draft or published) is recorded **on the round the student sat**, in `application_round_results`. Round N+1's pool is everyone published as `selected` in round N (`PipelineService::pool`, `CDC/backend/app/Services/PipelineService.php:67-82`).
- So Superset's page "Resume Shortlisting -> Online test" is the same thing as our Pipeline column "Resume Shortlisting": round 1 results, where "selected" means "shortlisted for round 2". Superset's "Shortlisted" equals our `selected`, its "Rejected" equals our `rejected`, and "Hold at current stage" is close to our `waitlisted`. The data model already matches. The differences are in the UI, the wording and a few tools.

---

## 1. Video walkthrough

Superset's sidebar is the same in every frame:
- Logo "superset" with a close "x".
- RECENT JOB PROFILES (chips "CentrAlign Al...", "Founding Eng...", "C-DAC, Kolkata...", "Knowledge", "Accenture Japa...", "Digital Cons...").
- RECENTLY VISITED PLACEMENTS ("Full-Time Placement for 2...", "Internship Placement || ...").
- Home, My Dashboards.
- JOBS: Companies, Inbound Job Posts, **Placements** (active), Job Alerts (with a sparkle icon).
- RELATIONSHIPS: Students, CRM.

The top bar shows the institute name "Indian Institute of Technology Indian School of Mines Dhanbad", a "Search students" box, a download icon, a bell, an apps grid, "Career Development Centre IIT ISM Dhanbad" and a "CD" avatar. A chat bubble sits bottom right.

| Time | What happens on screen |
|---|---|
| 0:00 | **Placements** list, URL `/#/admin/placements`. Left card: calendar icon, heading "Placements" and the text "Placement Cycles help you manage distinct recruitment phases—like **Final Year Placements** and **Pre-Final Year Internships**—with dedicated timelines, policies, and teams. Each cycle is a self-contained process tailored to a specific student batch and purpose." Button "+ Add placement process". Middle: "Search Placements" box, then cards "Full-Time Placement for 2026-27 (2027 Pass Out Batch), June 2026 - June 2027" and "Internship Placement \|\| 2026-2027 Session \|\| 2028 Pass out batch, April 2026 - August 2027", each with a gear icon. Section "Previous Placements" holds "Internship Placement Cycle for 2029 Graduating Batch [DRAFT], March 2026 - February 2026", "Full-Time Placement for 2025-26 (2026 Pass Out Batch)" and "Internship Placement for 2025-26 (2027 Batch)". Right column: "Recently Visited", the same cycles with gear icons, plus "FT Placement for 2023-2024 (2024 passouts)" and "Full-Time Placement for 2024-25 (2025 Pass Out Batch)". |
| 0:18 | Opens "Full-Time Placement for 2026-27 (2027 Pass Out Batch)". URL `/#/admin/placements/<uuid>?search=(length:50,order:!((column:0,dir:desc)),search:()...`. Yellow banner: "**10** students are waiting on a No Objection Certificate." with link "Review Requests". Filters: "Status [All v]", "Search [Start typing ...]", a person icon and "Check Eligibility". Left: "Jun 2026 - Jun 2027", green badge "1901 Enrolled Students", "out of 2044 eligible students", "Important Links (i)" with "Dashboard", "Collaborators (i)" (CD avatar and +), and "Enrollment Deadline (i) 11 Aug 2026, 18:00". While the list loads, an empty state reads: "You have not added any job profiles yet. Click on the '+ Add' button to create a new job profile for a placement you are participating in or click here". |
| 0:20-0:22 | The list loads. Header icons: kebab and an Excel icon. Left gains "Edit Placement" and more Important Links: Placement Matrix Report, Placement Absentees Report, Student Placements Report, Student Opportunities Report, Download Job Profiles List, Student Offers Report, Application Preferences Report, "List : Job Offers", "List : Students Placed", "List : Students Not Placed". Toolbar: "Clear" (pink pill), "+ Add New Job", "Check Eligibility". Table columns are **Company \| Profile \| Date of Visit (calendar icon) \| Deadline \| Status**. Status values seen: **Completed** (green), **Accepting Applications** (blue, with a dot), **Draft** (grey), **Closed For Applications** (bold black), **In Process** (blue). Typing "c-dac" filters to 4 records: C-DAC Mumbai, Knowledge Associate, Sep 09 2026 05:00 PM, Closed For Applications; C-DAC Pune, E1 - Design Engineer, In Process; C-DAC Kolkata, Knowledge Associate, Aug 27, In Process; C-DAC Kolkata, Knowledge Associate, Draft. Footer: "Showing Page 1 of 1 (4 records)". |
| 0:24-0:28 | Opens **C-DAC Kolkata - Knowledge Associate**. URL `.../placements/<uuid>/companies/<uuid>/jo...`. The page title is "Knowledge Associate", with breadcrumb "Placements / Full-Time Placement for 2026-27 (2027 Pass Out Batch) / Knowledge Associate". Under it: "C-DAC, Kolkata · Kolkata", "Date of visit: «Not Updated» (i)" with a pencil, and chips "Full Time", "Full Time Hiring" and an outlined toggle chip "Mute communication to students ·" with a gear. Header icons: a speaker (announce), a red PDF and a kebab. Left section nav: Collaborators, Summary, Details, Additional Info, TalentLens, Job Profile Tags, Additional Questions, Applicable Courses, Eligibility, Stages, Attached Documents, Category, Withdrawal Options, Advanced Options, and then Communication Log. Right panel: blue button "**74 Applicants**" with a "..." button, "**Application Progress**" bar "74 applied out of 363 eligible", the status text "**Resume Shortlisting** is in progress." with a teal button "**Shortlist for Resume Shortlisting**", and an "Activity" list: "In Process a month ago", "Applications Published a month ago", "Open For Applications a month ago", "Submitted a month ago". |
| 0:30 | Scrolls Details: External ATS (i) DISABLED; Expected Number Of Hires NOT MENTIONED; Job Functions "Engineering - Web / Software"; Cost-to-Company (CTC) "₹ 8,40,000.00 - ₹ 9,10,000.00 per Annum"; "Salary Break-up / Additional Compensation" table (Particulars / Details: "B.E./B.Tech. or equivalent, Approx. Annual CTC: 8.40 LPA" and "M.E./M.Tech. or equivalent, 9.10 LPA"); Job Description. |
| 0:32-0:34 | Switches to Excel workbook "**Shortlisted Candidate Tracker_Written Test**", Sheet0, 65 data rows. Columns: A serial, B a 7-digit number, C name, D roll no (e.g. 25MT0154, 23JE0525, 22JE1046), E programme (M.Tech., B.Tech, M.Tech (Integrated)), F branch. The presenter selects column D (status bar shows "Count: 65"). This is the company's written-test shortlist. |
| 0:42-0:44 | Back on the job profile. "Job Profile Details" with Edit: Title "Knowledge Associate", Job Location "Kolkata", Position Type "Full Time", External ATS, Expected Number Of Hires, Job Functions, CTC. Then scrolls to the top (Summary). |
| 0:46-0:48 | Back to the placement's job list (unfiltered). |
| 0:50-0:52 | Opens **Texas Instruments - Digital Engineer** (Bengaluru). "189 Applicants", "189 applied out of 325 eligible", Summary Status chip "CLOSED FOR APPLICATIONS". The right panel shows a padlock and "Applications for **Digital Engineer** are closed. You can now send list of applicants to the company", a green button "**Send applicant list to company**", "OR", and an outlined button "**Re-open and extend deadline**". Activity: "Open For Applications 5 days ago", "Submitted 5 days ago". |
| 1:04 | Clicks "Re-open and extend deadline". Modal "**Open Applications**": "Set Application Deadline" with hour/minute spinners (12 : 00, PM) and a date field "03 Oct 2026" with a calendar icon. Blue info strip: "Company deadline for accepting job application for this job profile is". Checkbox "Inform all students who are eligible for this job profile." with note "By default only student who are eligible but have not applied for this job profile yet will be informed." Grey text: "A notice will be created and all eligible students will be notified via email and SMS". Buttons: "Schedule For Later" (green, with an envelope icon), "Cancel", "Open for Applications" (blue). |
| 1:10 | Cancels; the modal closes. |
| 1:16-1:20 | Back to the list, searches "c-dac", reopens C-DAC Kolkata Knowledge Associate. |
| 1:22 | Hovers "Shortlist for Resume Shortlisting". |
| 1:24-1:26 | Page "**Shortlist for Online test - Knowledge Associate**" (back chevron). Header: two chips "**Resume Shortlisting** -> **Online test**" and the caption "Showing list of candidates participating in **Resume Shortlisting** to be shortlisted for **Online test**". Top-right icons: person-x (Reconcile), a PDF/download icon, and greyed left/right arrows. Left panel: company logo, "Knowledge Associate", "C-DAC, Kolkata · Kolkata", a big number "**0**", "selected out of **74** candidates", green button "**Publish Shortlist to Students**", split button "**Download Current Shortlist** [v]", and outlined buttons "**Send Notice to Shortlisted Students**", "**Send Email to Shortlisted Students**" and "**Send Email to Students On Hold**". Right: "Search [Search by name or Roll]", button "**Bulk Shortlist/Remove/Hold**", a sort icon and dropdown "Name". Table: header checkbox, **Name** (bold name, then "roll, branch" in grey), **Application Status** ("Applied"), and per-row icons green check / red x / amber hold. Footer: "Showing Page 1 of 1 (74 records)". |
| 1:32-1:40 | Back to Excel (count 65), then highlights "74" on the page to contrast 74 applicants with 65 shortlisted. |
| 1:48-1:54 | Clicks "Bulk Shortlist/Remove/Hold". Modal "**Bulk Shortlist / Reject / Hold**" with tabs **ADD TO SHORTLIST** (active), **REMOVE FROM SHORTLIST**, **HOLD AT CURRENT STAGE**. Radio "(o) Roll Number ( ) Email". "**Enter Roll nos**", hint "Enter Roll nos of students you would like to shortlist separated by a new line or 'Enter Key'", and a large textarea. "**Mark students as**" dropdown, value "**Already Shortlisted for next round**". Checkbox "Send Communication to students" (unticked). Footer: checkbox "**Apply strict check on current stage of applicant**" (ticked), "Cancel", green "**Add students to Shortlist**". |
| 1:58-2:00 | Copies column D in Excel ("Select destination and press ENTER or choose Paste"). |
| 2:02-2:04 | Pastes. Live counter above the box: "You have entered **65** Roll nos". Hovers "Add students to Shortlist". |
| 2:42 | After submitting: "**65** selected out of 74 candidates". Shortlisted rows have a light-green background and status "**Shortlisted**" (green). The rest stay "Applied" (white). A trophy icon appears after "Sucheta Ghosal". |
| 2:50-2:56 | Scrolls. Unshortlisted examples include Suman kumar 25MA0014 (Humanities and Social Sciences), Diptashree Banerjee and RUPESH RAJAK, all "Applied". |
| 2:58-3:02 | Hovers the trophy on "Anirban Das" (23JE0104, Computer Science & Engineering, Shortlisted). Tooltip: "**Placed in Software Engineer at Google India**". |
| 3:10-3:14 | Hovers the person-x icon. Tooltip: "**Reconcile Eligible Students**". |
| 3:16 | Clicks. Modal "**Reconcile Ineligible Students**" with spinner "Checking Students Eligibility....", and footer "Cancel" plus the disabled pink "**Mark Selected Students As Rejected**". |
| 3:18-3:46 | Result: "Download Report" link (download icon). Table: checkbox, **Student Name**, **Reason**. The three records explained:<br>- **Sucheta Ghosal**: section "Placement Eligibility", red x "Maximum of 1 offers allowed in **General 1** category, currently has 1 offers."; section "Allowed Student Categories", green tick "Criteria satisfied".<br>- **SHUBHADEEP MONDAL**: "Allowed Student Categories", tick "Criteria satisfied"; "Academic Eligibility", x "PG - Required: 6 CGPA , Actual: 5.96 CGPA" (highlighted by the presenter).<br>- **Anirban Das**: "Placement Eligibility", x "Maximum of 1 offers allowed in General 1 category, currently has 1 offers."; "Allowed Student Categories", "Criteria satisfied".<br>Footer: "Showing Page 1 of 1 (3 records)". |
| 3:50 | Closes the modal and goes back. |
| 3:52-4:08 | Opens the job profile to explain the reasons. Job description text (zoomed): candidates must check eligibility (CGPA, backlog, gap etc.) and stay eligible until joining. **Eligibility** section: "Select eligibility criteria conflict resolution mode" with text "Candidates who have completed multiple educations of same level (for instance, 2 degrees of Undergraduate Level) can have a lower score in one of them. When turned to ALL, Superset won't allow candidates to apply unless all educations of a specified program level pass the criteria." and a toggle **ANY \| ALL**. One row per programme: M.Tech. "Applicants must have scored 6 CGPA in Postgraduate"; B.Tech "6 CGPA in Undergraduate"; M.Tech (Integrated) "6 CGPA in DUAL"; MA "6 CGPA in Postgraduate". Each row has pencil and delete icons and "+ Add Criteria", and some have "+ Add allowed programs for student previous education". Collapsibles follow: Work Experience Criteria, Attendance Criteria, Gender Criteria, Backlog Criteria (green chip "No ongoing backlogs allowed") and Age Criteria. At 4:08 the status-bar link reads `.../jobprofiles/844e3a8a-.../stages/1/shortlist`. |
| 4:10-4:28 | Back on the shortlist page (65 of 74). Searches "subha", "subh", "su" (6 records), then "SHUBHADEEP": one record, SHUBHADEEP MONDAL 25MT0514, Computer Science & Engineering, **Applied** (he was not on the company list). |
| 4:38 | Opens Reconcile again with the same 3 records, ticks students and clicks "Mark Selected Students As Rejected". |
| 4:44-4:52 | Counter now "**63** selected out of 74". Sucheta Ghosal's row is pink with status "**Rejected**" (red). Anirban Das, also placed, is presumably rejected too (65 - 2 = 63). |
| 5:04 | On the job profile, Summary shows a stages list ending "Stage 3 - Technical interview". Hovers "Communication Log". |
| 5:06 | Page "**Communication log**", breadcrumb "Placements / Placement Details / Job Profile / Communication Log". Filters: "Channel [Email v]", "Search [Search by subject...]" and "Showing communications ...". Rows show date and time (blue), type, subject, count and recipient:<br>- "Sep 7, 05:53 PM", "Job Profile Applications Published", "Applications list published for C-DAC, Kolkata's : Full-Time", 2, "Career Development Centre IIT ISM Dhanbad".<br>- Many rows "Aug 27, 04:48 PM", "Job Application Created Or History Changed", "Application submitted: C-DAC, Kolkata's Knowledge Associate", 1, student name. |
| 5:08-5:10 | Back on the job profile: Eligibility (read-only view), **Attached Documents** (+ Add Document; Name / Uploaded; "JNF_CDAC.docx", "01:16 PM, 26 Aug 2026", Remove), **Category** (Edit; "Level 1 - General 1": "Max offers in this category: 1", "Max attempts before any offer: Unlimited", "Max attempts after any offer: Unlimited"), and **Withdrawal Options (i)** (yellow note "Students are prohibited from withdrawing their applications for this placement cycle. Use the settings below to override this configuration and allow application withdrawl for this job profile." and checkbox "Allow student application withdrawal after the job profile is in process?"). |
| 5:12-5:14 | Back on the shortlist page (63 of 74). |
| 5:18 | Clicks "Publish Shortlist to Students". Modal "**Confirm**": "Are you sure you want to publish the shortlist for Online test with **63** candidates shortlisted?" with "Cancel" and "Continue". |
| 5:20 | Clicks Continue (spinner). |
| 5:36-5:38 | Redirected to the job profile. Green toast: "**Done!** The shortlist has been published!" |
| 5:40 | The right panel now reads "**Online test** is in progress." with button "**Shortlist for Online test**". |
| 5:42-5:44 | Page "**Shortlist for Technical interview - Knowledge Associate**". Chips "Online test -> Technical interview", caption "Showing list of candidates participating in **Online test** to be shortlisted for **Technical interview**". "**0** selected out of **63** candidates". All rows show status "**Qualified**" with the three action icons. A red padlock and a red/off toggle sit next to the sort control (no label visible). Footer: "Showing Page 1 of 1 (63 records)". |
| 6:10-6:12 | Opens Bulk Shortlist / Reject / Hold on this stage (same dialog) and closes it. |
| 6:56-7:12 | Navigates back to the **previous** page "Shortlist for Online test - Knowledge Associate", which is now **read-only**: no row checkboxes, no Application Status column, no action icons, no "Publish Shortlist to Students", no "Bulk..." button and no Reconcile icon. Only the PDF icon and arrows remain at top right, and the left panel still offers Download Current Shortlist and the three Send buttons. Rows are coloured: green for shortlisted, pink for rejected. Debopriya Dasgupta, who was "Applied" and not shortlisted, is now pink, so the unshortlisted were rejected on publish. SHUBHADEEP MONDAL and Anirban Das are pink. Hovering Anirban's trophy shows "Placed in Software Engineer at Google India" again. |

---

## 2. Superset features shown

Status legend: **IMPLEMENTED**, **PARTIAL**, **NOT IMPLEMENTED**. "Ours" paths are relative to `/Users/admin/Desktop/CDC-main/CDC/`.

### F1. Placements list and placement job list (context)
- **Superset name / path:** Sidebar "Placements" -> "Placements" page -> a placement ("Full-Time Placement for 2026-27 (2027 Pass Out Batch)").
- **What it does:**
  - The cycle list has "Search Placements", current and "Previous Placements", "[DRAFT]" cycles, a gear per cycle, "+ Add placement process" and "Recently Visited".
  - The placement page has a Status filter (All), a search box, "Clear", "+ Add New Job" and "Check Eligibility".
  - Job table columns: Company, Profile, Date of Visit, Deadline, Status.
  - Job statuses: Draft, Accepting Applications, Closed For Applications, In Process, Completed.
  - Left panel: enrolled vs eligible count, Important Links (reports), Collaborators, Enrollment Deadline, and a "No Objection Certificate" banner with "Review Requests". Used by the admin.
- **Our status: PARTIAL.**
  - We have a cycles page (`frontend/app/admin/placement-cycles/page.jsx`) and a postings list (`frontend/app/admin/postings/page.jsx`) with a cycle and status filter (`postings/page.jsx:79-99`).
  - The postings list has **no text search** (only two Select filters) and no Date of Visit.
  - Our statuses are `open`, `in_process`, `completed` and `cancelled` (`postings/page.jsx:95`). There is no Draft, because a posting exists only after floating, and no split between "Closed For Applications" and "In Process" (`AdminPostingController::close`, `backend/app/Http/Controllers/AdminPostingController.php:337-340`, sets `in_process` right away).
  - NOC requests and reports are out of scope for this video.
- **Naming:**
  - Superset "Placements" is our nav "Cycles" (`frontend/components/admin/adminshell.tsx:48`) and "Placement cycle" filter label.
  - "Job Profiles" is our admin nav "Postings" (`adminshell.tsx:49`) and page title "Job Postings" (`postings/page.jsx:66`). Our student side already says "Job Profiles" (`components/student/studentshell.jsx:44`), so admin and student wording differ.
  - Status labels: Open vs **Accepting Applications**; In Process vs **Closed For Applications** (before the first round is published) and **In Process** (after).
  - Renaming the labels is display-only, so it is safe.
- **Conflict check:** None for labels. A "Draft" posting state does not exist by design (float = publish, D62).

### F2. Job profile page: header chips, Applicants button, Application Progress, stage panel, Activity
- **Superset name / path:** Placements -> placement -> job row -> job profile page (right panel).
- **What it does:**
  - Header: title, breadcrumb, company and location, "Date of visit: «Not Updated»" (editable), chips "Full Time" and "Full Time Hiring", a speaker icon (announce), PDF and kebab.
  - Right panel:
    - "**N Applicants**" button with a "..." menu;
    - "**Application Progress**" bar with "X applied out of Y eligible";
    - the current stage: "**<Stage name>** is in progress." with a CTA "**Shortlist for <Stage name>**" that opens the stage's shortlist page;
    - "**Activity**", a timeline of job statuses with relative times: Submitted -> Open For Applications -> Applications Published -> In Process.
  - Used by the admin.
- **Our status: PARTIAL.**
  - Counts: the Overview tab has stat cards "Eligible students", "Applied", "Withdrawn", "Unverified resume" and "Placed elsewhere" (`frontend/components/admin/posting/overviewtab.jsx:88-102`). The data is equivalent; there is no "applied out of eligible" progress bar.
  - Current stage panel: **missing**. Round status `pending`/`ongoing`/`completed` exists (migration `2026_09_27_000010_create_posting_rounds_table.php:21`). Publishing sets the next round to `ongoing` (`PipelineService.php:211-216`). Status shows only as a chip in each Pipeline column header (`pipelinetab.jsx:228`) and in the Rounds tab (`roundstab.jsx:124`). Nothing says "Online test is in progress" or links straight to that round's working view.
  - Activity timeline: **missing** on the posting page. Status changes are audit-logged (`posting.close`, `posting.reopen`, `round.publish` in `AdminPostingController.php:555-569` and `AdminPipelineController.php:223`) but only shown on the global Audit Log page (`app/admin/audit-logs`).
- **Naming:**
  - Superset "N Applicants" is our Applicants tab "Applied (N)" (`applicantstab.jsx:80`).
  - "Application Progress" is our cards "Eligible students" / "Applied".
  - "<Stage> is in progress" is our round chip "Ongoing".
  - "Shortlist for <Stage>" has no equivalent; we open the "Pipeline" tab.
- **Conflict check:** None. The counts are admin-only, so "students never see applicant counts" is respected.

### F3. Closed job panel: "Send applicant list to company" / "Re-open and extend deadline"
- **Superset name / path:** Job profile right panel when status is "CLOSED FOR APPLICATIONS".
- **What it does:**
  - Padlock and "Applications for <job> are closed. You can now send list of applicants to the company".
  - "**Send applicant list to company**" publishes the applicant list to the recruiter. The Communication Log entry is "Job Profile Applications Published" and the Activity entry "Applications Published".
  - "OR **Re-open and extend deadline**" opens F4.
- **Our status:**
  - Re-open: **IMPLEMENTED**, in a different form. Overview -> Lifecycle "Reopen applications" (`overviewtab.jsx:211-215`) requires the deadline to be extended first in the Settings card (`AdminPostingController.php:347-358`, message "Extend the application deadline before reopening the posting."). There is also the Edit eligibility -> "Reopen applications for everyone eligible" path (D104, `components/admin/editeligibilitydialog.jsx:121-188`).
  - Send applicant list to company: **NOT IMPLEMENTED as a gate**. Companies always see live applicants and can export at any time (`backend/app/Http/Controllers/CompanyPipelineController.php:63-105`; CDC_PORTAL_CONTEXT "A company can export its own drives at any time").
- **Naming:**
  - "Re-open and extend deadline" is our "Reopen applications" (and the deadline field "Application deadline (IST)", `overviewtab.jsx:156`). Superset does both in one action; we do it in two steps.
  - "Applications Published" (to company) has no equivalent.
- **Conflict check: NEEDS OWNER DECISION.**
  - A "send applicant list to company" gate contradicts the recorded rule that a company sees its applicants and can export **at any time** (Exports section, D78, Q8.2).
  - Merging deadline extension and reopen into one dialog does not conflict.

### F4. "Open Applications" dialog
- **Superset name / path:** Job profile -> "Re-open and extend deadline".
- **What it does:**
  - "Set Application Deadline": hour and minute spinners, AM/PM, date picker.
  - Info "Company deadline for accepting job application for this job profile is" (shows the company's own deadline).
  - Checkbox "Inform all students who are eligible for this job profile." with note "By default only student who are eligible but have not applied for this job profile yet will be informed."
  - Footer note "A notice will be created and all eligible students will be notified via email and SMS".
  - Buttons "Schedule For Later", "Cancel", "Open for Applications".
- **Our status: PARTIAL.**
  - Deadline plus reopen exist (F3).
  - The plain Overview reopen sends **no mail**. Only the D104 eligibility-dialog reopen mails, and only to eligible students never told about the drive (`PostingEligibilityService`, D104).
  - Missing: one-step "set deadline + open", **Schedule For Later**, the "inform all eligible" option, display of the company's deadline from the JNF, and SMS.
- **Naming:** "Open for Applications" is our "Reopen applications"; "Schedule For Later" has no equivalent.
- **Conflict check: NEEDS OWNER DECISION.**
  - "Inform all students who are eligible", which includes those who already applied, conflicts with D103/D104: "Applicants and anyone already told are never mailed again".
  - SMS is not in scope (mail and in-app only).
  - Schedule-for-later is new behaviour and needs owner approval.

### F5. "Mute communication to students"
- **Superset name / path:** Job profile header, outlined toggle chip with a gear.
- **What it does:** Per job, it stops automatic student communications (and opens settings via the gear).
- **Our status: NOT IMPLEMENTED.** There is no per-posting mute. E2, E4 and E5 always send (`PipelineService::dispatchResultMails`, `PipelineService.php:239-253`).
- **Naming:** None in our UI.
- **Conflict check: NEEDS OWNER DECISION.** The owner rule says publishing sends selected students a result mail and everyone else a regret mail. A mute switch would override that rule.

### F6. Stage shortlist page ("Shortlist for <next stage> - <job>")
- **Superset name / path:** Job profile -> "Shortlist for <Stage>". URL `.../jobprofiles/<id>/stages/<n>/shortlist`.
- **What it does:** A focused page for **one** stage transition. It shows:
  - the title "Shortlist for <next> - <job>" with a back chevron;
  - chips "<current> -> <next>" and the caption "Showing list of candidates participating in <current> to be shortlisted for <next>";
  - a left panel with the logo, job, company and location, a big counter "**N** selected out of **M** candidates", and the actions (F12, F14, F15);
  - a table with a header checkbox, Name (name, then roll and branch), Application Status, and the per-row actions (F7);
  - search "Search by name or Roll", sort "Name", pagination "Showing Page x of y (n records)";
  - top-right icons for Reconcile (F11), PDF download and previous/next stage arrows (F16).
- **Our status: PARTIAL.**
  - Our "Pipeline" tab is one grid of **all rounds x all applicants** (`frontend/components/admin/posting/pipelinetab.jsx:215-278`).
  - Each column header shows the round name, a Final chip, a status chip, "X draft · pool Y" and a kebab menu (`pipelinetab.jsx:220-238`).
  - It has a search box "Search roll no, name, branch" (`:209`) and the summary "N active applicant(s) · dashed = draft, filled = published" (`:210-212`).
  - Backend: `GET /admin/postings/{p}/pipeline` (`backend/routes/api.php:131`, `AdminPipelineController::show`, `:33-73`).
  - Missing:
    - a per-round focused view showing only that round's pool;
    - a "selected out of pool" counter (we show draft and pool counts only);
    - the stage-transition caption;
    - a sort control;
    - row checkboxes;
    - paging (our grid scrolls inside `maxHeight: 640`).
- **Naming:** Superset "Shortlist for <next stage>" is our "Pipeline" tab plus a round column; "N selected out of M candidates" is our "X draft · pool Y". Rename suggestions are in section 4.
- **Conflict check:** None. The pool rule (`PipelineService::pool`) already matches "candidates participating in <current stage>".

### F7. Per-row Shortlist / Reject / Hold actions and row selection
- **Superset name / path:** Shortlist page, each row has three icon buttons: green check-in-circle (shortlist), red x-in-circle (reject), amber hold/envelope icon (hold). Rows also have checkboxes and there is a select-all checkbox.
- **What it does:** One click decides a single candidate. Rows recolour (green for Shortlisted, pink for Rejected) and the counter updates. Checkboxes allow selecting rows for bulk actions.
- **Our status: NOT IMPLEMENTED.**
  - In the grid a result cell (`pipelinetab.jsx:52-91`) shows the attendance tick, a result chip, an "Undo" icon for Re-add (published rejected rows only) and an "x" to remove a draft.
  - There is **no per-row "select / reject / waitlist" button**. Results are entered only through the bulk "Enter results (paste / upload)" dialog (`pipelinetab.jsx:281-288`).
  - The backend already supports single-row writes: `POST .../rounds/{r}/results` accepts `entries[{roll_no,result}]` (`AdminPipelineController.php:86-94`). Only the UI is missing.
- **Naming:** Shortlist / Reject / Hold vs our Selected / Rejected / Waitlisted.
- **Conflict check:** None, provided per-row clicks write **drafts** like today. Superset's rows also become visible only on "Publish Shortlist to Students". Hold maps to waitlisted; see F9 for the naming decision.

### F8. Bulk Shortlist / Reject / Hold dialog
- **Superset name / path:** Shortlist page -> "Bulk Shortlist/Remove/Hold". The modal title is "Bulk Shortlist / Reject / Hold".
- **What it does / fields:**
  - Tabs: **ADD TO SHORTLIST**, **REMOVE FROM SHORTLIST**, **HOLD AT CURRENT STAGE**.
  - Identifier radio: **Roll Number** (default) or **Email**.
  - Label "Enter Roll nos" (it presumably changes for Email), helper "Enter Roll nos of students you would like to shortlist separated by a new line or 'Enter Key'", a textarea, and a live counter "You have entered **65** Roll nos".
  - "**Mark students as**" dropdown; only "Already Shortlisted for next round" was visible.
  - Checkbox "**Send Communication to students**" (default off).
  - Checkbox "**Apply strict check on current stage of applicant**" (default on): only applicants currently at this stage are accepted.
  - Buttons "Cancel" and "**Add students to Shortlist**".
  - Used by the admin.
- **Our status: PARTIAL.** Pipeline column menu -> "Enter results (paste / upload)" (`pipelinetab.jsx:281-288`). The dialog "Enter results - <round>" (`:319`) has:
  - Select "Mark pasted roll numbers as" with Selected / Waitlisted / Rejected (`:330-337`). This covers add, hold and reject. "Remove from shortlist" is done by deleting a draft (`:82-88`, `DELETE .../rounds/{r}/results/{application}`, `api.php:139`).
  - Textarea "Roll numbers (one per line, or comma separated)" (`:338-345`), split on whitespace, comma or semicolon (`:44-48`).
  - **Extra on ours:** upload ".xlsx/.csv (roll_no, result)" (`:346-349`; backend `collectEntries`, `AdminPipelineController.php:474-510`).
  - Error and warning report "Check these roll numbers" listing unknown, withdrawn or out-of-pool rolls (`pipelinetab.jsx:192-206`; `AdminPipelineController.php:106-141`).
  - Missing:
    - (a) **Email** as an identifier: rolls only (`resolveApplicants`, `PipelineService.php:33-59`).
    - (b) a **live count** of entered rolls.
    - (c) a **strict-check toggle**: ours always saves out-of-pool entries with a warning, "Was not selected in the previous round." (`AdminPipelineController.php:118-122`).
    - (d) "Send Communication to students" at entry time: we never mail at entry and always mail at publish.
    - (e) a "Mark students as" sub-status such as "Already Shortlisted for next round" (unknown semantics).
- **Naming:** "Enter results (paste / upload)" vs "Bulk Shortlist/Remove/Hold"; "Mark pasted roll numbers as" vs "Mark students as"; "Roll numbers (one per line, or comma separated)" vs "Enter Roll nos"; "Save" vs "Add students to Shortlist".
- **Conflict check:**
  - (c) Strict check as an **opt-in** toggle is fine. Making it the **default** reverses D70(b), "entering a result for someone outside the pool is allowed (admin is god) but returned as a warning": **NEEDS OWNER DECISION** on the default.
  - (d) "Send Communication to students" at entry time conflicts with "Nothing is visible to students until the admin publishes a round": **NEEDS OWNER DECISION** (recommend not building it).
  - (a) Email lookup has no conflict.

### F9. Application Status values on stage pages
- **Superset name / path:** "Application Status" column on the shortlist page.
- **Values seen:**
  - **Applied** (grey): in the first stage and not yet decided.
  - **Shortlisted** (green, green row).
  - **Rejected** (red, pink row).
  - **Qualified** (grey): in the next stage's page, for everyone who cleared the previous stage and is undecided here.
  - Hold was not seen, but "Students On Hold" and "HOLD AT CURRENT STAGE" imply an **On Hold** status.
- **Our status: IMPLEMENTED (data), PARTIAL (labels).**
  - Enum `pending`, `selected`, `rejected`, `waitlisted` (migration `2026_09_27_000012_create_application_round_results_table.php:19`), plus draft or published (`published_at`).
  - Grid chips: "Selected", "Rejected", "Waitlist", "Pending" is not shown; "(draft)" suffix, "+" for addendum, dashed border for draft (`pipelinetab.jsx:50-74`).
  - A pool member with no row shows "—" (`:53`).
  - Student trail: "Cleared" (non-final selected), "Selected" (final), "Not selected", "Waitlisted", "Result pending" (`components/student/roundtrail.jsx:7-12`).
  - Mail subjects: "Shortlisted: ...", "Waitlisted: ...", "Update on your application: ..." (`backend/app/Mail/RoundResultMail.php:32-36`).
- **Naming mismatches:**
  - admin "Selected" vs **Shortlisted** (our mail already says "Shortlisted");
  - "Waitlist"/"Waitlisted" vs **On Hold** / "Hold at current stage";
  - "—" for an undecided pool member vs **Qualified** (round 2+) or **Applied** (round 1);
  - student "Cleared" vs Shortlisted, so our wording is inconsistent even internally.
- **Conflict check:**
  - Renaming Selected to Shortlisted in admin grids is display-only, with no conflict. Keep "Selected" for the **final** round, where it means an offer.
  - Renaming "Waitlist" to "On Hold" changes terminology the owner used in a binding decision (D90, "Waitlist (final owner instruction)", glossary "Waitlist: students held in reserve, unordered"): **NEEDS OWNER DECISION**.
  - Semantics are also not identical. Our published waitlist mails the student "You are waitlisted" (`PipelineService.php:326`), while Superset's hold seems silent until "Send Email to Students On Hold".

### F10. "Placed" indicator with tooltip
- **Superset name / path:** A trophy icon after the student name on stage pages. Tooltip: "Placed in <Role> at <Company>" (e.g. "Placed in Software Engineer at Google India").
- **Our status: PARTIAL.**
  - The Pipeline grid shows a red-flag chip "Placed" when `placed_elsewhere_flag` is set (`pipelinetab.jsx:259`). The Applicants tab shows "Placed elsewhere" (`applicantstab.jsx:132`). The Results console shows "Placed elsewhere" and active block sentences (`app/admin/postings/[id]/results/page.jsx:161-164`).
  - No tooltip with the role and company of the offer. The pipeline payload (`AdminPipelineController.php:53-70`) does not include the student's offers.
  - We flag only when a **blocking** offer exists (BlockingPolicy). Superset shows the trophy for any placement.
- **Naming:** "Placed" / "Placed elsewhere" vs the trophy plus "Placed in X at Y". Add the tooltip and keep our label.
- **Conflict check:** None. Showing the offer company to admins is fine; companies must still never see `placed_elsewhere_flag` (D71).

### F11. Reconcile Ineligible Students
- **Superset name / path:** Shortlist page top-right person-x icon. The tooltip says "Reconcile Eligible Students"; the modal title says "Reconcile Ineligible Students".
- **What it does:**
  - Re-checks every candidate at this stage against the job's **current** eligibility and placement policy ("Checking Students Eligibility....").
  - Lists the failures with **Student Name** and **Reason**, grouped by criterion: "Placement Eligibility" (offer-count cap per category), "Allowed Student Categories", "Academic Eligibility" (e.g. "PG - Required: 6 CGPA , Actual: 5.96 CGPA"). Passing groups show "Criteria satisfied".
  - "Download Report", row checkboxes and select-all, pagination.
  - "**Mark Selected Students As Rejected**" (disabled until a selection; it rejected 2 shortlisted candidates in the video, 65 -> 63) and "Cancel".
- **Our status: PARTIAL.**
  - `EligibilityService::check()` already returns student-facing reasons (CDC_PORTAL_CONTEXT section 4).
  - The D103 eligibility-change preview lists current applicants who **would** stop being eligible, with reasons (`backend/app/Services/PostingEligibilityService.php:125-160`, reasons at `:152`). That only runs for a **criteria change**, not as a standalone re-check after CGPA re-uploads or new offers.
  - Students placed elsewhere are flagged automatically (`placed_elsewhere_flag`, BlockingPolicy) and can be removed **one at a time** with "Remove from process" (`components/admin/posting/removefromprocess.jsx:47-63`; `AdminPipelineController::removeFromProcess`, `:409-467`; route `api.php:141`). That writes a published `rejected` row "Selected elsewhere via CDC" and by default notifies the company.
  - Missing: a per-round "check current pool against eligibility now" list with reasons, a report download, and **bulk** reject of the ticked students.
- **Naming:** "Reconcile Ineligible Students" vs our "Remove from process" (placed-elsewhere only) and the "would no longer be eligible" list in Edit eligibility.
- **Conflict check: NEEDS OWNER DECISION.**
  - D103(d): "a student who applied and is now ineligible keeps the application". A reconcile tool that rejects them in the current round is an explicit admin action ("admin is god"), but it is a new product behaviour.
  - Owner choices to settle:
    - (i) whether a bulk reject from reconcile should notify companies the way "Remove from process" does;
    - (ii) whether the rejected students get a regret mail immediately or at the next publish;
    - (iii) whether the remark should be "Selected elsewhere via CDC" or "No longer eligible".
  - Superset's "Maximum of 1 offers allowed in General 1 category" is a category or offer-count model; ours is an offer-type block matrix. Reconcile must use **our** `EligibilityService` and blocks, not Superset's categories (see F21).

### F12. Publish Shortlist to Students (confirm, toast, stage advance)
- **Superset name / path:** Shortlist page left panel -> green "**Publish Shortlist to Students**".
- **What it does:**
  - Confirm modal "Confirm": "Are you sure you want to publish the shortlist for <next stage> with **N** candidates shortlisted?", with Cancel and Continue.
  - On success: toast "**Done!** The shortlist has been published!" and a redirect to the job profile, which now reads "<next stage> is in progress".
  - Unshortlisted candidates at the stage become **Rejected** (seen afterwards on Debopriya Dasgupta).
  - The next stage page shows the shortlisted as "Qualified".
- **Our status: IMPLEMENTED.**
  - Pipeline column menu -> "Publish round" (`pipelinetab.jsx:306-314`) opens the dialog "Publish <round>?" (`:322`). Copy: "N draft result(s) will become visible and every affected student is emailed (selected/waitlisted: result mail; rejected: regret mail)." Checkbox "Mark everyone else in this round's pool (N) as not selected" is default on for the first publish (`:309`, `:368-379`). Button "Publish & notify" (`:403`).
  - Backend: `POST .../rounds/{r}/publish` (`api.php:134`; `AdminPipelineController::publish`, `:189-239`).
    - Rounds publish in order: `"Publish \"X\" before this round."` (`:206-210`).
    - The final round is refused here (`:200-204`).
    - `PipelineService::publish` (`:174-232`) rejects the remaining pool, stamps `published_at`, completes the round, sets the next round `ongoing`, and moves the posting to `in_process`.
    - Mails go as BCC batches (`dispatchResultMails`, `:239-253`; `notifyResults`, `:310-345`). The response message is "Published: N selected, N waitlisted, N not selected. Students have been notified."
  - Differences:
    - our confirm counts drafts, not "N shortlisted";
    - no redirect; we show a success Alert at the top of the posting page (`app/admin/postings/[id]/page.jsx:101-105`);
    - no "<next round> is in progress" CTA (see F2).
- **Naming:** "Publish round" vs **"Publish Shortlist to Students"**; "Publish <round>?" vs **"Confirm"** with "Are you sure you want to publish the shortlist for <next> with N candidates shortlisted?"; "Publish & notify" vs **"Continue"**; success "Published: ..." vs "Done! The shortlist has been published!".
- **Conflict check:** None for the wording. The final round must still publish **only** from the Results console (`AdminResultController`, `app/admin/postings/[id]/results/page.jsx:312-324`). A Superset-style "Publish Shortlist" button must stay disabled on the final round, as ours is (`pipelinetab.jsx:307`, label "Publish from the Results page").

### F13. Read-only view of an already published stage
- **Superset name / path:** Revisiting "Shortlist for <stage>" after publishing.
- **What it does:** Checkboxes, status column, row actions, Publish, Bulk and Reconcile all disappear. Rows stay colour-coded (green shortlisted, pink rejected). Download and the Send Notice/Email buttons stay.
- **Our status: IMPLEMENTED, with intentional extras.**
  - Published rows can never be overwritten (`PipelineService::writeDrafts`, `:107-116`).
  - The owner added **Addendum** (menu "Addendum (after publishing)", `pipelinetab.jsx:297-305`; `api.php:137`) and **Re-add** (the Undo icon on published rejected cells, `:75-81`; `api.php:138`; it requires a confirm and a remark and notifies the company, `AdminPipelineController.php:284-326`).
  - Published cells are filled chips; drafts are dashed (`pipelinetab.jsx:66-71`).
- **Naming:** "Addendum" and "Re-add" have no Superset equivalent in this video; keep them.
- **Conflict check:** Making a published round fully read-only like Superset would **remove** Addendum and Re-add, which contradicts the owner decisions (CDC_PORTAL_CONTEXT "Addendum", "Re-add"; D70(e)). **Do not adopt.**

### F14. Download Current Shortlist
- **Superset name / path:** Shortlist page left panel, split button "Download Current Shortlist" with a dropdown caret; also a PDF icon at top right.
- **What it does:** Downloads the current stage's list. The caret options were not visible (probably formats or scopes).
- **Our status: PARTIAL.**
  - Only a posting-wide Excel: the "Export" button (`app/admin/postings/[id]/page.jsx:82-89`; `GET /admin/postings/{p}/export`, `api.php:125`; `ExportService`). It holds every application with one column per round (`R{n}:` prefix, "(draft)" marker; D78, D88).
  - Missing: no per-round "current shortlist" download (e.g. only that round's selected rows, or its pool with draft decisions) and no PDF.
- **Naming:** "Export" vs "Download Current Shortlist".
- **Conflict check:** None. A draft shortlist download must remain admin-only; companies see published outcomes only (D78).

### F15. Send Notice / Email to Shortlisted Students / Students On Hold
- **Superset name / path:** Shortlist page left panel, buttons "Send Notice to Shortlisted Students", "Send Email to Shortlisted Students" and "Send Email to Students On Hold".
- **What it does:** Ad-hoc communication to exactly the students shortlisted (or on hold) at this stage, e.g. test venue or timing. "Notice" probably means an in-portal notice or announcement and "Email" an email; this is inferred from the labels.
- **Our status: NOT IMPLEMENTED (for a round audience).**
  - Automatic E4 result mails go out on publish (`PipelineService::notifyResults`, `:310-345`).
  - Events can target `all`, `branches` or `posting_applicants` (all live applicants of a drive) (`backend/app/Http/Controllers/AdminEventController.php:113-118`). There is **no** audience for "selected in round X" or "waitlisted in round X", and no free-form email composer.
- **Naming:** None.
- **Conflict check:** None found. Use the BCC batching rule (D89) and the institute-address-only rule. Adding a round audience to Events (e.g. `round_selected`, `round_waitlisted`) would reuse the E6 pipeline. Mailing **draft** shortlists before publish would leak results, so restrict this to **published** rows.

### F16. Stage navigation arrows and stage-transition header
- **Superset name / path:** Shortlist page top-right "<-" and "->" arrows; header chips "<current> -> <next>".
- **What it does:** Moves between consecutive stage pages (Resume Shortlisting -> Online test -> Technical interview).
- **Our status: PARTIAL.** All rounds sit side by side in one grid (`pipelinetab.jsx:220-239`), so navigation is not needed today. It would be needed with a per-round view (F6).
- **Naming:** None.
- **Conflict check:** None.

### F17. Padlock and toggle on a later stage (purpose unclear)
- **Superset name / path:** Next-stage page (Online test -> Technical interview), a red padlock and a red off-toggle next to Sort.
- **What it does:** Not explained on screen. It could be "lock this stage", "hide from company" or "strict mode"; see Uncertain.
- **Our status:** Cannot assess.
- **Conflict check:** n/a.

### F18. Communication Log (per job profile)
- **Superset name / path:** Job profile left nav -> "Communication Log". Breadcrumb "Placements / Placement Details / Job Profile / Communication Log", page "Communication log".
- **What it does:** Every message tied to this job.
  - Filter "Channel" (value "Email"; other channels were not seen, SMS probably exists), "Search by subject...", and "Showing communications ...".
  - Columns: date and time (link), event type (e.g. "Job Profile Applications Published", "Job Application Created Or History Changed"), subject (e.g. "Application submitted: C-DAC, Kolkata's Knowledge Associate"), recipient count, recipient (student or CDC).
- **Our status: NOT IMPLEMENTED** (per posting).
  - `email_logs` stores `user_id`, `recipient_email`, `subject`, `template`, `status`, `error_message`, `sent_at` and `message_ref` (`backend/database/migrations/2026_03_30_000025_create_email_logs_table.php:14-24`, `2026_10_01_000020_add_message_ref_to_email_logs.php`). It has **no `job_posting_id`**, and there is no admin UI for email logs (no route in `api.php`, no `app/admin/email-logs`).
  - The global Audit Log page records admin actions, not messages.
  - D103 keeps a `posting.notify` audit row with the mailed student ids (the E2 ledger), which is partial evidence.
- **Naming:** None ("Audit Log" is a different thing).
- **Conflict check:** None. A schema change needs a new migration (the owner rule: never edit an already-run migration).

### F19. Stages (job profile section)
- **Superset name / path:** Job profile left nav "Stages"; the Summary shows a list "Stage 1 ... Stage 3 Technical interview" with a minus icon.
- **What it does:** Ordered stage definitions for the job. Here: Resume Shortlisting, Online test, Technical interview.
- **Our status: IMPLEMENTED.**
  - "Rounds" tab (`app/admin/postings/[id]/page.jsx:32`; `components/admin/posting/roundstab.jsx`). Table columns: #, Round, Type, Scheduled, Status, Actions.
  - Actions: move up and down, edit, delete (`:102-160`), and "Add round" (`:161-165`).
  - Type list `ROUND_TYPES` (`:39-51`): Pre-Placement Talk, Resume Shortlisting, Written Test, Aptitude Test, Technical Test, Group Discussion, HR Interview, Technical Interview, Psychometric Test, Medical Test, Other.
  - "This is the final round (offers are announced after it)" (`:217`).
  - Routes at `api.php:126-129`. Rounds are created from the JNF/INF selection rounds at float (D62).
- **Naming:** "Rounds" / "Round" / "Add round" / "Round name" vs **"Stages" / "Stage" / "Add stage" / "Stage name"**. The type label "Written Test" is close to Superset's "Online test", which is free text there.
- **Conflict check:** Display rename only. The glossary and decisions say "Round", so the owner should confirm the wording (low risk). Reordering after publish stays forbidden (D75(h), D102).

### F20. Eligibility section, "conflict resolution mode" ANY / ALL (context, shown to explain the reconcile reasons)
- **Superset name / path:** Job profile -> Eligibility.
- **What it does:**
  - "Select eligibility criteria conflict resolution mode" toggle **ANY \| ALL** for students with multiple degrees of the same level.
  - One row per programme: "Applicants must have scored <n> CGPA in <Postgraduate/Undergraduate/DUAL>".
  - "+ Add Criteria", "+ Add allowed programs for student previous education".
  - Collapsibles: Work Experience, Attendance, Gender, Backlog ("No ongoing backlogs allowed") and Age criteria.
- **Our status: PARTIAL.**
  - We have a per-programme and per-branch CGPA cut-off, backlog caps (ongoing and total), gender, batch, minimum 10th and 12th (EligibilityService; Edit eligibility dialog D103).
  - Not ours: ANY/ALL mode, previous-education program rules, work experience, attendance, age.
- **Naming:** Superset "Criteria" vs our "Eligibility".
- **Conflict check:** Not part of the hiring-stages topic. Adding criteria types needs an owner decision; out of scope here.

### F21. Category (offer-count policy) (context)
- **Superset name / path:** Job profile -> Category, "Level 1 - General 1".
- **What it does:** "Max offers in this category: 1", "Max attempts before any offer: Unlimited", "Max attempts after any offer: Unlimited". This drives the reconcile reason "Maximum of 1 offers allowed in General 1 category".
- **Our status: NOT IMPLEMENTED by design.** We use offer-type blocks (`BlockingPolicy`; CDC_PORTAL_CONTEXT "Offer types and their blocks (final rule)", D91).
- **Conflict check: NEEDS OWNER DECISION.** Do not adopt. It would replace the owner's final blocking matrix, and there is no dream-offer or attempt-count rule ("There is no 'dream offer' rule").

### F22. Withdrawal Options (context)
- **Superset name / path:** Job profile -> Withdrawal Options.
- **What it does:** A per-cycle prohibition with a per-job override: "Allow student application withdrawal after the job profile is in process?"
- **Our status:** Not applicable. Students can withdraw until the deadline, and everything freezes at the deadline.
- **Conflict check:** CDC_PORTAL_CONTEXT lists "a per-cycle switch that forbids withdrawals" under **Excluded unless the owner asks**. Do not build.

### F23. Attached Documents (context)
- **Superset name / path:** Job profile -> Attached Documents. "+ Add Document"; columns Name and Uploaded; a "Remove" action (e.g. JNF_CDAC.docx).
- **Our status: PARTIAL.** The posting links to its JNF/INF form (`overviewtab.jsx:113-118`). There are no free attachments per posting. Out of scope for this video.

### F24. Search and sort inside a stage
- **Superset name / path:** Shortlist page, "Search by name or Roll" (live, filters records with a count) and a sort control "Name" with an up/down icon.
- **Our status: PARTIAL.** The pipeline search covers roll, name and branch (`pipelinetab.jsx:177-182`, `:209`). There is no sort control; the order is fixed by `applied_at` (`AdminPipelineController.php:44`).
- **Naming:** "Search roll no, name, branch" vs "Search by name or Roll".
- **Conflict check:** None.

---

## 3. Gap summary

| Superset name | What to build | Priority | Conflicts |
|---|---|---|---|
| Shortlist for <stage> page (F6) | A per-round working view: only that round's pool, "N selected out of M candidates" counter, "<this round> -> <next round>" caption, search, sort, paging, previous/next round arrows (F16). Can live inside the Pipeline tab as a round picker or as `/admin/postings/{id}/rounds/{r}`. | High | None |
| Per-row Shortlist / Reject / Hold (F7) | Per-row icon buttons writing a **draft** result through the existing `POST .../rounds/{r}/results` `entries[]`, plus row checkboxes and select-all with bulk actions on the selection. | High | None (drafts only) |
| Bulk Shortlist / Reject / Hold (F8) | Add Email as identifier, a live "You have entered N roll nos" counter, explicit Add / Remove / Hold tabs, and a "strict check on current round" toggle. | Medium | Strict check as the default reverses D70(b): NEEDS OWNER DECISION. "Send Communication to students" at entry conflicts with "nothing visible until publish": NEEDS OWNER DECISION. |
| Reconcile Ineligible Students (F11) | A per-round re-check of the current pool against `EligibilityService` and blocks, a reasons list, an Excel report, and "Mark selected students as rejected" (bulk, audited). | Medium | D103(d) says applicants keep their applications; company notification and regret timing are open: NEEDS OWNER DECISION |
| Send Notice / Email to Shortlisted / On Hold (F15) | Round-scoped audience (published selected / waitlisted of round X) for Events, or a simple BCC email composer. | Medium | None (published rows only, BCC, D89) |
| Download Current Shortlist (F14) | A per-round Excel (and optionally PDF) of the round's pool with its current decisions. | Medium | None (admin only) |
| Communication Log (F18) | Add `job_posting_id` and `kind` to `email_logs` (new migration). A posting tab listing messages: channel, subject search, date, type, recipients count. | Medium | None |
| Stage in progress panel (F2) | Overview card "<round> is in progress" with a button to that round's view; an "X applied out of Y eligible" progress bar. | Low | None |
| Activity timeline (F2) | Per-posting status history from `audit_logs` (float, close, reopen, round.publish, results). | Low | None |
| Placed tooltip (F10) | Include the student's offers (role, company) in the pipeline payload; tooltip "Placed in <role> at <company>". | Low | None (admin only) |
| Open Applications dialog (F4) | One step "set new deadline + reopen", showing the company's JNF deadline; optional "Schedule for later". | Low | "Inform all eligible incl. applicants" conflicts with D103/D104 ("never mailed again"): NEEDS OWNER DECISION. SMS not in scope. |
| Send applicant list to company (F3) | A gate before the company sees applicants. | Low | Contradicts "company can export at any time" (D78, Q8.2): NEEDS OWNER DECISION |
| Mute communication to students (F5) | A per-posting switch suppressing E2/E4/E5. | Low | Contradicts the publish-sends-result/regret-mail rule: NEEDS OWNER DECISION |
| Job list search and statuses (F1) | A search box on Postings; display "Accepting Applications" / "Closed For Applications" / "In Process" (derive from `in_process` plus whether any round is published). | Low | None (display only) |
| Category offer limits (F21) | Not to be built. | n/a | Replaces BlockingPolicy: NEEDS OWNER DECISION (recommend no) |
| Withdrawal Options (F22) | Not to be built. | n/a | Excluded by owner |
| Fully read-only published stage (F13) | Not to be built. | n/a | Would remove Addendum and Re-add (owner decisions) |

---

## 4. Rename list

| Where it appears in our UI or code | Our current name | Superset name |
|---|---|---|
| Admin nav `components/admin/adminshell.tsx:49`; page title `app/admin/postings/page.jsx:66` | Postings / Job Postings | Job Profiles (our student nav already says "Job Profiles", `studentshell.jsx:44`) |
| Admin nav `adminshell.tsx:48`; filter label `postings/page.jsx:81` | Cycles / Placement cycle | Placements (Superset's own help text calls them "Placement Cycles"; low value) |
| Posting status chip `open` (`postings/page.jsx:95`, `lib/format.js:52-61` via `titleCase`) | Open | Accepting Applications |
| Posting status `in_process` before any round is published | In Process | Closed For Applications |
| Posting status `in_process` after a round is published | In Process | In Process (same) |
| Overview Lifecycle button `overviewtab.jsx:213` | Reopen applications | Re-open and extend deadline |
| Overview stats `overviewtab.jsx:89, 92` | Eligible students / Applied | Application Progress, "X applied out of Y eligible" |
| Applicants tab label `applicantstab.jsx:80` | Applied (N) | N Applicants |
| Posting tab `app/admin/postings/[id]/page.jsx:32`; `roundstab.jsx` "Add round" (`:163`), dialog "Add round"/"Edit round" (`:168`), "Round name" (`:172`), column "Round" (`:107`) | Rounds / Round / Add round / Round name | Stages / Stage / Add stage / Stage name |
| `roundstab.jsx:42` round type | Written Test | Online test (free text in Superset) |
| Posting tab `page.jsx:29` | Pipeline | Shortlist for <stage> (per-stage pages) |
| Pipeline column menu `pipelinetab.jsx:287` | Enter results (paste / upload) | Bulk Shortlist/Remove/Hold |
| Pipeline dialog title `pipelinetab.jsx:319` | Enter results - <round> | Bulk Shortlist / Reject / Hold |
| Pipeline dialog select `pipelinetab.jsx:331` | Mark pasted roll numbers as | Mark students as |
| Pipeline dialog options `pipelinetab.jsx:333-335` | Selected / Waitlisted / Rejected | Add to shortlist / Hold at current stage / Remove from shortlist (statuses Shortlisted / On Hold / Rejected) |
| Pipeline dialog textarea `pipelinetab.jsx:341` | Roll numbers (one per line, or comma separated) | Enter Roll nos ("separated by a new line or 'Enter Key'") |
| Pipeline dialog submit `pipelinetab.jsx:403` (results kind) | Save | Add students to Shortlist |
| Pipeline result chip `pipelinetab.jsx:55` (non-final rounds) | Selected | Shortlisted (keep "Selected" for the final round) |
| Pipeline result chip `pipelinetab.jsx:55`; Waitlist tab `page.jsx:30`; `waitlisttab.jsx`; company grid `app/company/postings/[id]/page.jsx:238`; student trail `roundtrail.jsx:10`; mail `RoundResultMail.php:34` | Waitlist / Waitlisted | On Hold / Hold at current stage (NEEDS OWNER DECISION: "waitlist" is the owner's term in D90) |
| Pipeline empty cell for a pool member `pipelinetab.jsx:53` | "—" | Qualified (round 2+) / Applied (round 1) |
| Pipeline column caption `pipelinetab.jsx:230` | X draft · pool Y | N selected out of M candidates |
| Pipeline column menu `pipelinetab.jsx:313` | Publish round | Publish Shortlist to Students |
| Publish dialog title `pipelinetab.jsx:322` | Publish <round>? | Confirm |
| Publish dialog body `pipelinetab.jsx:371-372` | "N draft result(s) will become visible..." | "Are you sure you want to publish the shortlist for <next stage> with N candidates shortlisted?" |
| Publish dialog button `pipelinetab.jsx:403` | Publish & notify | Continue |
| Publish success `AdminPipelineController.php:231-236` | "Published: N selected, N waitlisted, N not selected. Students have been notified." | "Done! The shortlist has been published!" |
| Student trail `roundtrail.jsx:8` (non-final selected) | Cleared | Shortlisted (our mail subject already says "Shortlisted:", `RoundResultMail.php:33`) |
| Student trail `roundtrail.jsx:9` | Not selected | Rejected (Superset's student-side wording was not shown; keep the softer student wording unless the owner says otherwise) |
| Pipeline placed chip `pipelinetab.jsx:259`; Applicants `applicantstab.jsx:132` | Placed / Placed elsewhere | Trophy icon with tooltip "Placed in <role> at <company>" |
| Applicants row action `removefromprocess.jsx:47, 50` | Remove from process | Mark Selected Students As Rejected (inside "Reconcile Ineligible Students") |
| Posting header button `page.jsx:88` (per round, if built) | Export | Download Current Shortlist |
| Pipeline search `pipelinetab.jsx:209` | Search roll no, name, branch | Search by name or Roll |
| Round status chip `roundstab.jsx:124`, `pipelinetab.jsx:228` | Ongoing | "<Stage> is in progress." |

---

## 5. Uncertain

- **No audio or captions.** The narration is inferred from the screens. The presenter's spoken explanation of each step is unknown.
- **"Mark students as" dropdown:** only "Already Shortlisted for next round" was visible. The other options and what this value means (perhaps "move straight to the next stage" vs "shortlisted pending confirmation") are unknown.
- **Remove and Hold tabs:** "REMOVE FROM SHORTLIST" and "HOLD AT CURRENT STAGE" were never opened, so their fields are unknown. The "Email" mode was also never selected, so whether the label changes to "Enter Emails" is unknown.
- **Hold status:** no "On Hold" status chip or row colour was ever shown. The label is inferred from "Students On Hold" and "HOLD AT CURRENT STAGE". The amber third row icon is assumed to be "Hold"; its glyph looks like an envelope or tray.
- **Strict check:** what "Apply strict check on current stage of applicant" does when an entered roll is not at the current stage (skip silently, error, or report) was not shown. All 65 rolls were valid.
- **Who was rejected in reconcile:** the exact students ticked before "Mark Selected Students As Rejected" were not shown. 65 -> 63 and Sucheta's red row suggest Sucheta Ghosal and Anirban Das (the two shortlisted, placed students). SHUBHADEEP MONDAL was only "Applied" and turned pink after publish anyway.
- **Unshortlisted on publish:** that unshortlisted "Applied" candidates become "Rejected" on publish is inferred from the post-publish colours (Debopriya Dasgupta and SHUBHADEEP MONDAL pink). Whether students are mailed automatically on publish, and whether "Send Communication" or "Mute communication" interact with it, is not visible.
- **Padlock and red toggle on the "Online test -> Technical interview" page:** label and function unknown (stage lock? company visibility? strict mode?).
- **"..." next to "N Applicants", the speaker icon and the kebab** on the job profile: menus were not opened.
- **"Download Current Shortlist" dropdown caret options and the top-right PDF icon** on the shortlist page: not opened.
- **"Send Notice..." vs "Send Email..."**: the difference (portal notice or announcement vs email) is inferred from the labels only; no dialog was opened.
- **Communication Log channels:** only "Email" was visible. "SMS" is likely, given the Open Applications note "notified via email and SMS", but was not shown.
- **"Schedule For Later"** in the Open Applications dialog: its behaviour (scheduled reopen vs scheduled notice) was not shown. The blue "Company deadline for accepting job application for this job profile is" strip had no value rendered.
- **Excel column B** (7-digit numbers such as 7123729, 6136038) is probably an application or registration ID from the company's test portal. It was not used.
- **"Shortlist for Resume Shortlisting"** is the label on the button while Resume Shortlisting is in progress, yet it opens the page titled "Shortlist for Online test". This looks like a Superset labelling quirk (button = current stage, page = next stage), not a separate feature.
- **Stage list in the Summary (5:04)** only showed "Stage 3 - Technical interview". Stages 1 and 2 are inferred from the stage pages.
- **Tooltip vs title:** the Reconcile icon tooltip says "Reconcile **Eligible** Students" while the modal title says "Reconcile **Ineligible** Students". Both are quoted as seen.
