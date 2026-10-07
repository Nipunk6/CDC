# 07 — "How to Manage the profile after completion" (Superset admin, 410 s)

Source: 105 change-frames in `kf/07_manage_after_completion/` (t000m00s … t006m44s), all read in order.
Compared against `CDC_PORTAL_CONTEXT.md`, `CDC/PHASE2_DECISIONS.md` (D1–D104) and the code under `CDC/backend` and `CDC/frontend`.

In this video the presenter opens a Job Profile whose hiring process is already **Completed** (Siemens EDA, "Student Intern (Software Profile)/(Digital Profile)", in "Full-Time Placement for 2026-27 (2027 Pass Out Batch)"). He then shows what an admin can still do with it:
- walk the stage-by-stage shortlists down to the Offer stage (with CTC);
- open the Progress Grid and edit one student's application history;
- download each stage's shortlist with a custom Excel template;
- download all shortlisted students' resumes as a ZIP;
- send a notice or an email to shortlisted or on-hold students.

The video shows no offer-letter upload, no offer revocation and no "close profile" action. Offer-letter columns appear only in the exported Excel.

---

## 1. Video walkthrough

**0:00 — Placements list** (URL `#/admin/placements`)
- Superset left sidebar:
  - RECENT JOB PROFILES (chips such as "CentrAlign AI… · Founding Eng…", "C-DAC, Kolkata… · Knowledge", "Accenture Japa… · Digital Cons…");
  - RECENTLY VISITED PLACEMENTS;
  - Home, My Dashboards;
  - JOBS: Companies, Inbound Job Posts, **Placements** (highlighted), Job Alerts ✦;
  - RELATIONSHIPS: Students, CRM.
- Top bar: institute name, "Search students", download icon, bell, app-grid icon, "Career Development Centre IIT ISM Dhanbad" with a "CD" avatar.
- Page "Placements":
  - Left: a calendar illustration, the heading "Placements" and the blurb "Placement Cycles help you manage distinct recruitment phases—like **Final Year Placements** and **Pre-Final Year Internships**—with dedicated timelines, policies, and teams. Each cycle is a self-contained process tailored to a specific student batch and purpose.", then a "+ Add placement process" button.
  - Middle: "Search Placements", current cycles as cards with a gear icon (e.g. "Full-Time Placement for 2026-27 (2027 Pass Out Batch) · June 2026 - June 2027"; "Internship Placement || 2026-2027 Session || 2028 Pass out batch · April 2026 - August 2027"), then "Previous Placements" (one with a "[DRAFT]" tag).
  - Right: a "Recently Visited" list.

**0:06–0:10 — The presenter opens the FT 2026-27 cycle** (URL `#/admin/placements/<uuid>?search=(length:50,order:!((column:0,dir:desc)),search:(),…)`)
- Yellow banner: "**10** students are waiting on a No Objection Certificate." with a "Review Requests" link on the right.
- Left column:
  - calendar icon, "Jun 2026 - Jun 2027", "✎ Edit Placement";
  - green pill "**1901 Enrolled Students**" with "out of 2044 eligible students" under it;
  - "Important Links ⓘ": Dashboard, Placement Matrix Report, Placement Absentees Report, Student Placements Report, Student Opportunities Report, Download Job Profiles List, Student Offers Report, Application Preferences Report, then "List : Job Offers", "List : Students Placed", "List : Students Not Placed";
  - "Collaborators ⓘ" (a CD avatar and a + button);
  - "Enrollment Deadline ⓘ 11 Aug 2026, 18:00" ("No deadline set" while loading).
- Toolbar: "Status [All ▾]", "🔍 Search Start typing …", a person-filter icon, "+ Add New Job", "⏚ Check Eligibility". Top right: a kebab menu and an Excel icon.
- Table columns: Company | Profile | Date of Visit (calendar icon; tooltip "Date of visit not specified") | Deadline | Status.
- Status values seen: **Completed** (green), **Accepting Applications** (blue dot), **Draft** (grey), **Closed For Applications** (black bold), **In Process** (blue).
- Rows include: Siemens EDA India Pr… / Student Intern (Software… / Completed; CentrAlign AI / Founding Engineer / Oct 07, 2026 05:00 PM / Accepting Applications; Cracku / Draft; Texas Instruments Digital Engineer and Analog Engineer / Closed For Applications; Accenture Japan / Digital Consultant / In Process.
- An empty state seen while loading: "You have not added any job profiles yet. Click on the '+ Add' button to create a new job profile for a placement you are participating in or click here".

**0:40–0:42 — The presenter opens the completed Siemens job profile** (URL `…/companies/<uuid>/job…`)
- Header: title, then "Siemens EDA India Private Limited · Bangalore/Noida/Hyderabad/Kolkata".
- "📅 Date of visit: «Not Updated» ⓘ".
- Chips: "Internship", "Intern + Performance Based PPO", "Mute communication to students ⚙" (outlined blue, with a settings cog).
- Breadcrumb: Placements / Full-Time Placement for 2026-27 (2027 Pass Out Batch) / Student Intern ….
- Top bar: "📣 Trigger placement story", a speaker (announce) icon, a red PDF icon and a kebab menu.
- Right panel:
  - a blue "**136 Applicants**" button with a "•••" menu;
  - "Application Progress", a progress bar and "136 applied out of 352 eligible";
  - a green check circle and "**The process is complete! The final list of selected students is here**" ("here" is a link);
  - "Activity": "In Process 21 hours ago", "Applications Published 21 hours ago", "Submitted 21 hours ago".
- Left section nav: Collaborators, Summary, Details, Additional Info, TalentLens, Job Profile Tags, Additional Questions, Applicable Courses, Eligibility, Stages, Attached Documents, Category, Withdrawal Options, Advanced Options, then "Communication Log" (separated).
- Summary rows:
  - Company;
  - Created ("Created for **Full-Time Placement for 2026-27 (2027 Pass Out Batch)**");
  - Date of Visit / Process «Not Updated» ⓘ;
  - Status **COMPLETED** (green badge);
  - Job Profile Group ("Assign group to this Job Profile", dotted link);
  - Share Job Profile ("📋 Copy link ⓘ");
  - Flexible Mode for Job Profile Shortlisting ("Disabled");
  - Process Grid ("**View Progress Grid**");
  - Process Status ⓘ, a table with one "Shortlist" link per stage: Stage 1 ✓ Resume shortlisting, Stage 2 ✓ Online test, Stage 3 ✓ Technical interview R1, Stage 4 🏆 Technical interview R2.
- Below that: "Job Profile Details ✎ Edit", with Title and Job Location.

**0:54–1:02 — "Shortlist for Offer - Student Intern (Soft…"** (reached from the Stage 4 "Shortlist" link)
- Centre header: "[Technical interview R2] → [Offer]" and "Showing list of candidates participating in **Technical interview R2** to be shortlisted for **Offer**".
- Left panel:
  - company logo, profile title, company · location;
  - a big "**2**", "selected out of **11** candidates";
  - "📄 Download Current Shortlist ▾" (split button);
  - "Send Notice to Shortlisted Students", "✉ Send Email to Shortlisted Students", "✉ Send Email to Students On Hold".
- Top-right icons: upload (↑), PDF/resume, ← (tooltip "**Previous Stage Shortlist**") and →.
- Table:
  - "🔍 Search · Search by name or Roll", sort "⇅ Name ▾";
  - columns: [select-all checkbox] | Name | **CTC Offered** | **CTC Interval**;
  - each row has a checkbox and a circled ✓ icon, then name and "roll, branch" (e.g. "23JE0964, Electrical Engineering");
  - **selected rows are green**, with a 🏆 after the name and CTC "50000 INR", interval "MONTH" (Hitesh Kumar Singh, Bhavsar Vedant Manoj);
  - the other 9 rows are **red/pink** with empty CTC;
  - footer: "Showing Page 1 of 1 (11 records)" and a pager.

**1:04–1:16 — Back through earlier stages with ←**
- "Shortlist for Technical interview R2": "[Technical interview R1] → [Technical interview R2]", "**11** selected out of **15** candidates". Name column only, no CTC. Green = selected, red = not.
- "Shortlist for Technical interview R1": "[Online test] → [Technical interview R1]", "**15** selected out of **97** candidates".
- "Shortlist for Online test": "[Resume shortlisting] → [Online test]", "**97** selected out of **136** candidates", "Showing Page 1 of 2 (136 records)".
- A loading state shows "0 selected out of candidates" and "Could not find any students for given search parameters".
- On non-final stages the "Send Email…" buttons are greyed until the page loads.

**1:22–1:40 — Forward again with → to "Shortlist for Offer"** (same view as before)

**1:44–1:48 — Click a student name (Shaumya Kumar)**
- A right-side drawer opens: green banner, photo, "Shaumya Kumar ↗", "**2027 Passout Batch | 23JE0907 | Superset ID: 6136088**", "7th Semester, B.Tech", "Department of Engineering".
- Buttons: "✓ Mark profile as verified" (green outline) and "⊗ Ask Resubmission" (red outline).
- Tabs: OVERVIEW · ABOUT · ACADEMIC DETAILS · PROFILE · RESUMES & DOCUMENTS.
- "Placements ⓘ" section, with "📄 Download Placement Report" and "📄 Download Eligibility Report":
  - "Shaumya has participated in the following placement cycles";
  - "Internship Placement for 2025-26 (2027 Batch) ↗ — **Enrolled**", with 🏆 / ⚙ / ☰ icons, "› Show Applications" and "› Show Attendance";
  - "Full-Time Placement for 2026-27 (2027 Pass Out Batch) ↗ — Enrolled", with 🏆.
- The drawer closes with ×.

**2:00–2:16 — Back to the job profile and scroll the Summary**
- Same Summary as above, now showing "Process Status" with the four stages and their "Shortlist" links.
- Cursor on "**View Progress Grid**".

**2:24–2:26 — Progress Grid page** (breadcrumb "Placements / Placement Details / Student Intern … / Applications")
- Title: "Student Intern (Software Profile)/(Digital Profile) at Siemens EDA India Private Limited"; Excel icon at top right.
- "**Stages :** 1 - Resume shortlisting /2 - Online test /3 - Technical interview R1 /4 - Technical interview R2".
- "**Legend :** ✓ - Passed / ✗ - Failed / 🕓 - On Hold / ● - In Process".
- Search: "Search by applicant name or roll number".
- Columns: # | Name | Roll Number | 1 | 2 | 3 | 4 | **Status**. Each stage cell is ✓ or ✗; the Status column shows "**Disqualified**" (red) for everyone visible (e.g. "Aayush Chandak 23JE0008 ✓ ✓ ✓ ✗ Disqualified").

**2:28–2:32 — The presenter searches "23JE0907"**
- A blue info box appears: "You are making changes to **<profile>** at **<company>**. You can edit only one student information at one time. Click on the ✎ button to edit a student".
- Result row: "Shaumya Kumar ✎ | 23JE0907 | ✓ ✓ ✓ ✗ | Disqualified"; pager "Previous 1 Next".

**2:40–2:46 — Click ✎ to open the modal "Update application history for Shaumya Kumar"**
- One card per column, scrolling horizontally:
  - "Applicant": Shaumya Kumar 23JE0907;
  - "Resume shortlisting", "Online test", "Technical interview R1": each "✓ **SHORTLISTED**" (green) with a red ⊖ to remove;
  - "🔒 **Technical interview R2**": shows a red-bordered card at first; after a click it shows "🏆 **OFFERED**".
- Buttons: "Save Changes" (pale yellow and disabled until something changes, then active yellow) and "Cancel". The presenter clicks **Cancel**.

**2:48** The grid is unchanged ("Disqualified").

**2:54–2:58** Back to the profile, then to "Shortlist for Offer".

**3:20–3:24** Back to the profile. The presenter switches to a local Excel file "**Shortlisted Candidate Tracker_Written Test**": columns are S.No, a 7-digit ID (Superset ID, e.g. 7123729), Name, Roll No (e.g. 25MT0154), Program (M.Tech./B.Tech/M.Tech (Integrated)) and Branch. This is CDC's offline tracker of shortlisted students.

**3:38–3:46** Back on the profile. Scrolls through Summary, Process Status and "Job Profile Details ✎ Edit".

**4:00–4:04 — Shortlist for Offer**
- Hover tooltip on the PDF icon: "**Download all shortlisted student's Resumés**".

**4:22–4:26 — Download Current Shortlist**
- Modal "**Select Custom Template**", field "Select template" with the placeholder "Select an Option" and a search box.
- Options: Standard, standard, Data With all Sem CGPA, IIT ISM DHANBAD, IIT(ISM)Dhanbad, bhuvi, bhuvneshwari, DOC, Amdocs, PO… (the list scrolls).
- He picks "IIT(ISM)Dhanbad". Buttons: Cancel and "📄 **Download Excel File**".

**4:28 — Download panel** (header download icon)
- Blue toast: "**Download Queued!** Please wait while the system is generating the report. You'll be notified by email once the report is ready to download."
- Panel entries:
  - "Shortlist For Student Intern (Software Profile)/(digital Profile)(stage 4) At Siemens Eda India Private Limited (Template: Iit(ism)dhanbad)", with a "⬇ Download" button and "Generated a few seconds ago";
  - "Enrolled Students List For Placement: Full-Time Placement For 2026-27 (2027 Pass Out Batch) (Iit(ism)dhanbad) — **EXPIRED** Generated 3 hours ago";
  - "Applicant List For Digital Consultant At Accenture Japan Ltd. 2026 (Template: Iit(ism)dhanbad) — EXPIRED";
  - "See All" at the bottom.

**4:30–4:34**
- Save dialog; file name "Software_Profile_Digital_Profile_Stage_4_at_Siemens_EDA_India_Private_Limited_Template_IIT…", type Microsoft Excel Worksheet.
- Green toast "**Done** Download Started".
- The browser's download history shows "Shortlist_for_Student_Intern_Software_Profile_Digital_Profile_Stage_4_at_Siemens_EDA_India_Private_Limited_Template_IIT.xlsx", 7.9 KB. It also lists an earlier "Applicant_List_for_Digital_Consultant_at_Accenture_Japan_Ltd._2026_Template_IIT_ISM_Dhanbad.xlsx" and "Enrolled_Students_List_For_Placement_Full-Time_…_IIT_ISM_Dhanbad.xlsx".

**4:36–4:44 — The Stage 4 Excel opens**
- Rows 1–12 are blank (a logo area).
- Row 13: "List of candidates shortlisted during '**Technical interview R2**' to be proceeded to **FINAL OFFER**".
- Row 14: "SHORTLIST FOR STUDENT INTERN (SOFTWARE PROFILE)/(DIGITAL PROFILE) : TECHNICAL INTERVIEW R2 ROUND".
- Row 16 headers (teal): S.No. | Superset Id | Name | Roll No | Current Program | Current Course | Secondary Course | Current CGPA | Class 10 % | 10th YOP | Class 12 % | 12th YOP | UG Program | UG Branch/Specialization | UG percentage | … (columns P–Z are not seen) … | **CTC offered** | **CTC Currency** | **CTC Interval** | **Attached Resume** | **Resume Link** | **Offer Letter** | **Signed Offer Letter** | **Last Edited**.
- Data rows:
  - "1 | 6135999 | Bhavsar Vedant Manoj | 23JE0232 | B.Tech | Electronics & Communication Engineering | NA | 8.17 | 97.8 | 2021 | 84.33 | 2023 | NA | NA | NA … | 50000 | INR | MONTH | vedant_bhavsar_on_p_5 | Link | NA | NA | 2026 Oct 05 06:30 PM";
  - Hitesh Kumar Singh likewise (Attached Resume "RESUME").
- Row 20: "Generated by Superset at 03:01 PM 06 Oct 2026 IST".

**4:46** The offline tracker is shown again.

**4:48–5:12 — The same download from "Shortlist for Technical interview R2"** (stage 3)
- Template picker, queued toast, and the file "…Stage_3_at_Siemens_EDA…_Template_IIT.xlsx".
- Excel: "List of candidates shortlisted during 'Technical interview R1' to be proceeded to Technical interview R2" / "SHORTLIST FOR … : TECHNICAL INTERVIEW R1 ROUND", 11 data rows with the same columns. Footer "Generated by Superset at 03:02 PM 06 Oct 2026 IST".

**5:22–5:24** On stage 3 he clicks "Download all shortlisted student's Resumés". Toast "Download Queued!".

**5:30–5:52** He goes to Shortlist for Offer and does the same.
- Download panel entries:
  - "Applicant Resumes Shortlisted For Student Intern (Software Profile)/(digital Profile) (stage 4) At Siemens Eda India Private Limited — **WORKING** | Started a few seconds ago", with a "☐ **Email me this report.**" checkbox and "× **Cancel**";
  - "Applicant Resumes … (stage 3) … — ⬇ Download, Generated a few seconds ago";
  - "Shortlist For … (stage 3) … (Template: …".
- Result: "APPLICANT_RESUMES_shortlisted_for_Student_Intern_Software_Profile_Digital_Profile_Stage_4_at_Siemens_EDA_India_Private_L.zip", 453 KB.

**5:56–6:04 — The ZIPs on the desktop**
- Stage 4 ZIP: "23JE0232 Bhavsar Vedant Manoj.pdf" and "23JE0407 Hitesh Kumar Singh.pdf". Files are named "<ROLL> <Full Name>".
- Stage 3 ZIP: 11 PDFs named the same way.

**6:18–6:20 — "Send Notice to Shortlisted Students"**
- Modal "**Create Notice**": "This message will be sent to **all applicants of Student Intern (Software Profile)/(Digital Profile) Siemens EDA India Private Limited**".
- Fields:
  - Title;
  - tabs "NOTIFY COLLABORATORS & ADMINS" | "NOTIFY USER GROUPS", with the dropdown "Select collaborators or other users to notify";
  - Content: rich text with Paragraph ▾, B, I, U, align left/centre/right, superscript, subscript, bullet list, numbered list, undo, redo, link, unlink;
  - "📎 Add Attachment".
- Buttons: Cancel and "**Create Notice**" (disabled until filled).

**6:22–6:26 — "Send Email to Shortlisted Students"**
- Modal "**Send Email**": "This message will be sent to **all students shortlisted for Final Offer**".
- Fields:
  - Subject;
  - the same two notify tabs and dropdown;
  - Message: rich text with Paragraph ▾, B, I, U, align ×3, bullet, numbered, undo, redo, link, unlink, image, "<>" code view and full-screen;
  - "📎 Add Attachment".
- Buttons: Cancel and "**Send Email**" (disabled until filled).

**6:30–6:44** He closes the dialog and points at "Send Email to Students On Hold". Back on "Shortlist for Technical interview R2" the same three buttons exist (the Send Email buttons are greyed while loading). End.

---

## 2. Superset features shown

Our code paths are relative to `CDC/`. Our posting statuses are `open | in_process | completed | cancelled` (`backend/database/migrations/2026_09_27_000008_create_job_postings_table.php:19`). Round statuses are `pending | ongoing | completed` (`…000010…:21`). Result values are `pending | selected | rejected | waitlisted` (`…000012…:19`).

### F1. Job-profile list in a cycle, with lifecycle statuses
- **Superset name and path:** Placements → <cycle> → job profile table. The filter is "Status [All ▾]"; columns are Company, Profile, Date of Visit, Deadline, Status; buttons "+ Add New Job" and "Check Eligibility".
- **What it does:** lists every job profile in the cycle with its lifecycle status. Statuses seen: **Draft**, **Accepting Applications**, **Closed For Applications**, **In Process**, **Completed**. Used by admins.
- **Our status: PARTIAL.**
  - The cycle detail Postings tab is `frontend/components/admin/cyclepostings.jsx:58-81`. Its columns are Company · Role, Form, Status, Applied, Deadline.
  - The global list is `frontend/app/admin/postings/page.jsx` (title "Job Postings", line 66; status filter open/in_process/completed/cancelled, lines 95-97).
  - Missing:
    - a "Closed For Applications" status separate from "In Process" (our `in_process` covers both "deadline passed, nothing entered" and "rounds running");
    - a "Draft" posting state (we float only accepted forms, so Draft lives in JNF/INF review);
    - a "Date of Visit" column;
    - a status filter on the cycle's Postings tab.
- **Naming:**

  | Ours | Superset |
  |---|---|
  | "Open" | "Accepting Applications" |
  | "In Process" (one state) | "Closed For Applications" and "In Process" |
  | "Completed" | "Completed" (same) |
  | "Cancelled" | (none seen) |
  | "Job Postings" / "Postings" | "Job Profiles" / "Add New Job" |
  | "Company · Role" | "Company" and "Profile" (two columns) |

- **Conflict check:** none. Splitting `in_process` into two displayed labels can be presentation only (derive "Closed For Applications" when `in_process` and no round has results). D62 defines the stored lifecycle; changing the stored enum would need an owner decision, a display label would not.

### F2. Cycle "Important Links" reports and lists
- **Superset name and path:** Placements → <cycle> → left panel "Important Links".
- **What it does:** one-click reports per cycle: Dashboard, Placement Matrix Report, Placement Absentees Report, Student Placements Report, Student Opportunities Report, Download Job Profiles List, Student Offers Report, Application Preferences Report, "List : Job Offers", "List : Students Placed", "List : Students Not Placed". Used by admins.
- **Our status: PARTIAL.**
  - "Export Students" on the cycle page (`frontend/app/admin/placement-cycles/[id]/page.jsx:281-284`) calls `GET /admin/placement-cycles/{cycle}/students/export` (`backend/routes/api.php:91`, `ExportService::studentsWorkbook`, `backend/app/Services/ExportService.php:157-225`). It gives one row per enrolled student with Offers, Best CTC, Best Stipend and Active Blocks, so it covers "Students Placed / Not Placed / Student Offers" in one sheet, but not as separate filtered lists.
  - Analytics (`frontend/app/admin/analytics/page.jsx`) has placed and unplaced counts, offers by type and top recruiters.
  - Missing: separate lists for Job Offers, Students Placed and Students Not Placed; a Placement Matrix (branch × company); an Absentees report (attendance "no" across rounds; the data exists in `application_round_results.attendance`); a Student Opportunities report (eligible-vs-applied per student); a job-profiles list export; an "Application Preferences" report (we have no preferences feature).
- **Naming:** our "Export Students" vs Superset's "Student Placements Report"; our "Placement Analytics" vs Superset's "Dashboard"; our "Enrolled Students (N)" tab matches Superset's "N Enrolled Students".
- **Conflict check:** "Application Preferences Report" assumes job preferences, which are "later" in the owner's list (§3 "Job alerts and preferences: later"). Do not build that one now.

### F3. Job Profile detail page after completion (summary, process status, completion card)
- **Superset name and path:** Job profile page → Summary.
  - Rows: Status **COMPLETED**, Process Grid "View Progress Grid", Process Status (Stage 1..n with a ✓ per finished stage, 🏆 on the final stage, a "Shortlist" link per stage).
  - Right panel: "The process is complete! The final list of selected students is **here**".
  - Chips: job type ("Internship") and offer category ("Intern + Performance Based PPO").
- **What it does:** shows at a glance that the drive is finished, links straight to each stage's shortlist and to the final selected list. Used by admins.
- **Our status: PARTIAL.**
  - The posting page (`frontend/app/admin/postings/[id]/page.jsx`) has a status chip (line 76), "Results & Offers" (lines 90-92) and tabs Overview, Applicants, Eligible, Pipeline, Waitlist, Proposals, Rounds, Questions (lines 25-34).
  - The Rounds tab lists rounds with Final chip and status (`frontend/components/admin/posting/roundstab.jsx:106-125`).
  - Overview shows Type and Offer category (`frontend/components/admin/posting/overviewtab.jsx:124-128`).
  - Missing:
    - a "process complete" banner on the posting page with a link to the final offer list (today you must open Results & Offers, where offered rows show an "Offered" chip; `app/admin/postings/[id]/results/page.jsx:146-147`);
    - per-stage ✓/🏆 progress with a direct "Shortlist" link per round;
    - an offer-category chip in the header.
- **Naming:**

  | Ours | Superset |
  |---|---|
  | "Rounds" (tab) | "Stages" |
  | "Final" chip | 🏆 on the last stage |
  | "Results & Offers" | "final list of selected students" / "Shortlist for Offer" |
  | "Completed" chip | "COMPLETED" badge (same meaning) |
  | "Intern + performance-based PPO" (`frontend/lib/offerpolicy.js:13`, `backend/app/Models/Offer.php:20`) | "Intern + Performance Based PPO" (capitalisation and hyphen) |

- **Conflict check:** none.

### F4. Application Progress and applicant count
- **Superset name and path:** Job profile → right panel: "**136 Applicants**" (button with a ••• menu), "Application Progress" bar, "136 applied out of 352 eligible".
- **What it does:** shows the admin how many eligible students applied.
- **Our status: IMPLEMENTED** as numbers. Overview stat cards show "Eligible students", "Applied", "Withdrawn", "Unverified resume" and "Placed elsewhere" (`frontend/components/admin/posting/overviewtab.jsx:88-102`, from `AdminPostingController` `stats`, `backend/app/Http/Controllers/AdminPostingController.php:786-791`). The Eligible tab gives applied / not applied (D97). There is no progress bar and no "X applied out of Y eligible" sentence.
- **Naming:** our "Eligible students" + "Applied" cards vs Superset's "Application Progress — X applied out of Y eligible"; our "Applicants" tab vs Superset's "N Applicants".
- **Conflict check:** "**Students never see applicant counts**" (owner). This must stay admin-only. Superset shows it only to admins too, so there is no conflict as long as it never reaches student payloads.

### F5. Activity feed per job profile
- **Superset name and path:** Job profile → right panel "Activity" (e.g. "In Process 21 hours ago", "Applications Published", "Submitted").
- **What it does:** a lifecycle timeline of the profile. Admins see it.
- **Our status: PARTIAL.**
  - Every admin write is audit-logged. The global Audit Log page filters only by admin, action and date (`backend/app/Http/Controllers/AdminAuditLogController.php:20-41`); there is no filter by subject or posting and no feed on the posting page.
  - The posting Overview shows only "Floated: <time> by <name>" (`overviewtab.jsx:137-139`).
- **Naming:** our "Audit Log"/"Audit trail" vs Superset's "Activity"; our "Floated" vs "Applications Published"; our "Close applications" / in_process vs "In Process"; our form "accepted" vs "Submitted".
- **Conflict check:** none.

### F6. Communication Log per job profile
- **Superset name and path:** Job profile → left nav "Communication Log".
- **What it does:** lists the mails and notices sent for this profile (not opened in the video; inferred from the name).
- **Our status: NOT IMPLEMENTED per posting.** `email_logs` rows exist (`MailDispatchService`, D39/D102), but no posting-scoped view exists. The `posting.notify` ledger (D103(f)) records E2 recipients only.
- **Naming:** none today.
- **Conflict check:** none.

### F7. Mute communication to students
- **Superset name and path:** Job profile header chip "Mute communication to students ⚙".
- **What it does:** suppresses automatic student mails for this profile (the cog suggests per-channel settings; not opened).
- **Our status: NOT IMPLEMENTED.** All E2/E4/E5 mails are sent automatically.
- **Conflict check: NEEDS OWNER DECISION.** The owner rules say "on publish, selected students get a result mail and everyone else in that round gets a regret mail" and "emails … are sent instantly". A per-drive mute would override those rules.

### F8. Trigger placement story
- **Superset name and path:** Job profile top bar "📣 Trigger placement story" (and a speaker icon next to it).
- **What it does:** not shown. It probably publishes a placement announcement ("story") to students or social channels.
- **Our status: NOT IMPLEMENTED.** Our closest equivalent is the E5 offer mail and the student dashboard "🎉 Congratulations!" banner (`frontend/app/student/page.jsx:63-70`).
- **Conflict check: NEEDS OWNER DECISION** if it broadcasts names or CTC of placed students to other students. Nothing in our rules covers publicising offers.

### F9. Stage shortlist page ("Shortlist for <Stage>") with stage-to-stage navigation
- **Superset name and path:** Job profile → Process Status → "Shortlist" on a stage. Page title "Shortlist for <Stage> - <Profile>".
- **What it does:**
  - Header "[Previous stage] → [This stage]" and "Showing list of candidates participating in <prev> to be shortlisted for <stage>".
  - Left counter "N selected out of M candidates".
  - Rows from the previous stage's pool, green for selected and red for not; "Search by name or Roll"; sort "Name ▾"; paging "Showing Page x of y (n records)".
  - ← "Previous Stage Shortlist" and → next-stage arrows.
  - Name click opens the student drawer (F16).
  - Used by admins.
- **Our status: PARTIAL.**
  - The Pipeline tab is one grid of all rounds × all applicants, with per-round menus: Enter results, Mark attendance, Addendum, Publish round (`frontend/components/admin/posting/pipelinetab.jsx:215-315`). The column header shows "N draft · pool N" (line 230). Search is by roll, name or branch (line 209). Pool logic: `backend/app/Services/PipelineService.php:67`.
  - Missing:
    - a per-round view of only that round's pool with a "selected out of pool" count;
    - previous/next round navigation;
    - green/red row colouring;
    - sort control;
    - pagination (the grid is a scroll box, `maxHeight: 640`).
- **Naming:**

  | Ours | Superset |
  |---|---|
  | "Pipeline" (tab) | "Shortlist for <stage>" / "Process Grid" |
  | "pool N" | "N candidates" |
  | "Selected" | "selected" / "shortlisted" |
  | "Enter results" | (shortlisting is done on this page) |

- **Conflict check:** none. Our draft/publish model (D70) must remain: a Superset-style page must still show draft vs published.

### F10. Offer stage with CTC Offered and CTC Interval
- **Superset name and path:** "Shortlist for Offer". Columns are Name | **CTC Offered** (e.g. "50000 INR") | **CTC Interval** (e.g. "MONTH"); there is a selection checkbox per row, 🏆 after selected names, and an upload (↑) icon at the top right.
- **What it does:** final selection with compensation per student. The amount and currency go in one value; the interval says per month or per year. Used by admins.
- **Our status: PARTIAL.**
  - The Results console (`frontend/app/admin/postings/[id]/results/page.jsx`) has checkbox, Student, Offer type, Compensation ("CTC / year (INR)" and "Stipend / month (INR)") and Block (lines 286-310, 186-206).
  - Backend: `POST /admin/postings/{p}/results/publish` (`backend/app/Http/Controllers/AdminResultController.php:125-300`). Model fields `ctc_annual`, `stipend_monthly`, `currency` (`backend/app/Models/Offer.php:29-41`; migration `2026_09_27_000014_create_offers_table.php:21-24`).
  - Missing:
    - **editing an offer's CTC or type after publishing** (no update route for offers, `backend/routes/api.php:142-143`; a second publish for the same application is 422, `AdminResultController.php:164-166`);
    - bulk upload of offer CTC by spreadsheet (the ↑ icon; tooltip not shown);
    - 🏆 marker in lists.
- **Naming:**

  | Ours | Superset |
  |---|---|
  | "Compensation" column; "CTC / year", "Stipend / month" | "CTC Offered" + "CTC Interval" (MONTH, and presumably YEAR) |
  | "Final selections" | "Shortlist for Offer" |
  | "Offered" chip | 🏆 |
  | "Publish results" | (no publish button seen; selections show directly) |

  Recommendation: keep our two stored fields (analytics depend on them, D80/D88(b)/D100). Optionally relabel the inputs "CTC Offered (per year)" and "Stipend Offered (per month)" to read like Superset.
- **Conflict check:**
  - Editing CTC or offer type after publishing is consistent with "Admin is god" (no conflict), but changing the offer type must re-run `BlockingPolicy`. Flag for spec.
  - **Revoking or removing an offer** (implied by the history editor in F12) conflicts with "Publishing creates offers and blocks … Published rows are never overwritten" (§4) and has no owner rule. **NEEDS OWNER DECISION.**

### F11. Progress Grid ("View Progress Grid" → Applications page)
- **Superset name and path:** Job profile → Summary → Process Grid "View Progress Grid". Breadcrumb "Placements / Placement Details / <profile> / Applications".
- **What it does:** shows all applicants × stages.
  - Header "Stages : 1 - … /2 - … /3 - … /4 - …".
  - "Legend : ✓ - Passed / ✗ - Failed / 🕓 - On Hold / ● - In Process".
  - Search "Search by applicant name or roll number".
  - Columns # | Name | Roll Number | 1 | 2 | … | **Status**. The overall status seen is "**Disqualified**".
  - Excel download icon; ✎ per student to edit (F12).
- **Our status: PARTIAL.**
  - The Pipeline tab grid has the same matrix: chips Selected / Rejected / Waitlist / Pending, a dashed or "(draft)" style for drafts, ✓/✗ attendance icons and "+" for addendum (`pipelinetab.jsx:50-91, 215-278`), plus a sticky name column and search.
  - The posting export has R1..Rn columns (`ExportService.php:96-113`).
  - Missing: an overall per-applicant **Status** column (e.g. Offered / Disqualified / In process / On hold), a legend line, numbered stage headers, and an Excel button on the grid itself (export is a page-level button).
- **Naming:**

  | Ours | Superset |
  |---|---|
  | "Selected" | "Passed" (✓) |
  | "Rejected" | "Failed" (✗) |
  | "Waitlist" / "Waitlisted" | "On Hold" (🕓) |
  | "Pending" | "In Process" (●) |
  | (no overall status) | "Disqualified" (and presumably "Offered", "In Process") |
  | "Pipeline" | "Process Grid" / "Progress Grid" |
  | "Round" | "Stage" |

  Student side: our "Cleared" / "Selected" / "Not selected" / "Waitlisted" (`frontend/components/student/roundtrail.jsx:8-11`) would map to Superset's "Shortlisted / Offered / Failed / On Hold".
- **Conflict check:** none for display. Renaming "Waitlist" to "On Hold" is only a label (D90 semantics unchanged).

### F12. Update application history (edit one student across all stages)
- **Superset name and path:** Progress Grid → ✎ next to a name → modal "**Update application history for <Name>**".
- **What it does:**
  - One card per stage. Non-final stages show "✓ SHORTLISTED" (green) with ⊖ to remove; the final stage shows "🔒 <stage>" with "🏆 OFFERED", or a red not-selected state, toggled by clicking.
  - Info box: "You can edit only one student information at one time."
  - Buttons: "Save Changes" (enabled only after a change) and "Cancel".
  - The presenter toggled a rejected student's final stage to OFFERED and then cancelled, so the modal can turn a rejected student into an offered one after completion.
  - Used by admins.
- **Our status: PARTIAL** (by design).
  - Published rows are never overwritten (`backend/app/Services/PipelineService.php:85, 111-120`; D70(e), D75(e)).
  - Allowed corrections:
    - Re-add for a published rejection (`POST …/rounds/{r}/readd/{application}`, `api.php:138`; UI `pipelinetab.jsx:75-81, 380-390`);
    - Addendum (`api.php:137`);
    - delete a draft (`api.php:139`);
    - Remove from process (`api.php:141`);
    - re-running the Results console for later selections (D73).
  - Missing: a single per-student editor; turning a published "selected" back to "not selected"; removing or revoking an offer.
- **Naming:** our "Re-add previously rejected candidate" / "Remove this draft" / "Remove from process" vs Superset's "Update application history"; our "Selected" vs "SHORTLISTED"; our "Offered" vs "OFFERED".
- **Conflict check: NEEDS OWNER DECISION.**
  - It contradicts "Published rows are never overwritten; draft rows can be deleted" (§4 Pipeline rules), the admin-only **Re-add** protocol with confirm, remark and company notice (§3), and "Structure freezes once results exist".
  - Undoing an offer would also need rules for its block (blocks follow the student across cycles) and its placed-elsewhere flags.
  - A Superset-style editor would have to route through Re-add / Addendum (audited, company notified) or the owner must approve true overwrites.

### F13. Download Current Shortlist (per stage, with custom template)
- **Superset name and path:** any "Shortlist for <stage>" page → "📄 Download Current Shortlist ▾" → modal "**Select Custom Template**" → "Select template" (placeholder "Select an Option", searchable; e.g. Standard, Data With all Sem CGPA, IIT(ISM)Dhanbad, DOC, Amdocs, PO…) → "Download Excel File".
- **What it does:** an Excel file of the students who **cleared this stage**.
  - Title rows: "List of candidates shortlisted during '<stage>' to be proceeded to <next stage | FINAL OFFER>" and "SHORTLIST FOR <PROFILE> : <STAGE> ROUND".
  - The template decides the columns. Seen: S.No., Superset Id, Name, Roll No, Current Program, Current Course, Secondary Course, Current CGPA, Class 10 %, 10th YOP, Class 12 %, 12th YOP, UG Program, UG Branch/Specialization, UG percentage, … CTC offered, CTC Currency, CTC Interval, Attached Resume, Resume Link, Offer Letter, Signed Offer Letter, Last Edited.
  - Footer "Generated by Superset at <time> IST".
  - File name "Shortlist_for_<Profile>_Stage_<n>_at_<Company>_Template_<Template>.xlsx".
  - Used by admins (sent to the company).
- **Our status: PARTIAL.**
  - One posting-wide export: "Export" (`app/admin/postings/[id]/page.jsx:82-89`) → `GET /admin/postings/{p}/export` (`AdminPostingController.php:360-365`) → `ExportService::applicantsWorkbook` (`ExportService.php:30-152`). It gives every application with fixed columns, one column per round, offer type and CTC/stipend, and a signed "Resume Link".
  - Company export: `CompanyPipelineController.php:108-113`.
  - Missing:
    - an export filtered to **one round's selected list**;
    - title and footer rows;
    - saved **custom column templates**;
    - 10th/12th year of passing, UG fields and "last edited" columns;
    - CTC currency as its own column (we put the currency only in the posting).
- **Naming:**

  | Ours | Superset |
  |---|---|
  | "Export" | "Download Current Shortlist" |
  | "Programme" | "Current Program" |
  | "Branch" | "Current Course" |
  | "CGPA" | "Current CGPA" |
  | "10th %" / "12th %" | "Class 10 %" / "Class 12 %" |
  | "Offer CTC (annual)" | "CTC offered" (+ "CTC Interval") |
  | "Offer Stipend (monthly)" | "CTC offered" with interval MONTH |
  | "Resume Label" | "Attached Resume" |
  | "Resume Link" | "Resume Link" (same) |
  | (none) | "Superset Id" (we use Roll No as the identifier; no rename needed) |

- **Conflict check:** none for per-round export or templates. The company export must keep its restricted field set (§3 Exports: roll number, name, academics, answers, published outcomes, resume link; contact only with "share contact details"), so any company-facing template must be limited to those fields.

### F14. Background report generation and download centre
- **Superset name and path:** header ⬇ icon → panel of reports.
  - Each entry: title; "WORKING | Started … ago" with "☐ Email me this report." and "× Cancel"; or "⬇ Download · Generated … ago"; or "**EXPIRED** Generated … ago". "See All" at the bottom.
  - Toasts: "Download Queued! Please wait while the system is generating the report. You'll be notified by email once the report is ready to download." and "Done — Download Started".
- **What it does:** runs exports as background jobs and keeps the file for a limited time. Used by admins.
- **Our status: NOT IMPLEMENTED.** Exports stream synchronously (`ExportService::stream`, `ExportService.php:271`). Performance passed at about 2,800 students (§8), so this is not needed for correctness.
- **Naming:** none.
- **Conflict check:** none. "Email me this report" would go to an admin, not students, so the "institute addresses only" rule is not affected. Low priority.

### F15. Download all shortlisted students' resumes (ZIP)
- **Superset name and path:** any "Shortlist for <stage>" page → PDF icon (tooltip "**Download all shortlisted student's Resumés**").
- **What it does:** a queued job builds "APPLICANT_RESUMES_shortlisted_for_<Profile>_Stage_<n>_at_<Company>.zip" with one PDF per selected student named "<ROLL> <Full Name>.pdf" (the resume they applied with). Used by admins to forward to the company.
- **Our status: NOT IMPLEMENTED** (no ZIP code anywhere in `backend/app`). Instead, every export row has a signed "Open resume" link valid for 30 days (`ExportService.php:137-143`; `Resume::signedUrl(30)`).
- **Naming:** none.
- **Conflict check: NEEDS OWNER DECISION.** Owner decision §3 Exports: "Excel with a **signed resume link** column (works without login, expires after 30 days), **instead of a ZIP of resumes**." Building a ZIP reverses that decision.

### F16. Student quick-view drawer from a shortlist
- **Superset name and path:** click a name on any shortlist → right drawer.
  - Header "<Name> ↗", "<Batch> Passout Batch | <Roll> | Superset ID: <id>", "<n>th Semester, <Degree>", "Department of …".
  - Buttons "Mark profile as verified" and "Ask Resubmission".
  - Tabs OVERVIEW / ABOUT / ACADEMIC DETAILS / PROFILE / RESUMES & DOCUMENTS.
  - "Placements" section with "Download Placement Report" and "Download Eligibility Report", "<Name> has participated in the following placement cycles", one row per cycle (name ↗, "Enrolled", 🏆 / ⚙ / ☰ icons, "› Show Applications", "› Show Attendance").
  - Used by admins.
- **Our status: PARTIAL.**
  - Names in the pipeline and results link to the full page `/admin/students/{id}` (`pipelinetab.jsx:252`, `results/page.jsx:154`). That page has tabs Overview, "Cycles (n)", "Applications (n)", "Offers & Blocks", "Audit trail" (`frontend/app/admin/students/[id]/page.jsx:178-182`), with the offers table at lines 288-327.
  - Missing:
    - a drawer, so the admin stays on the shortlist;
    - per-student "Placement Report" and "Eligibility Report" downloads (only `show/update/photo/suspend/reactivate/resendInvitation` exist in `AdminStudentController`);
    - an attendance view per cycle;
    - profile verification (Mark verified / Ask Resubmission). We verify **resumes**, not profiles.
- **Naming:**

  | Ours | Superset |
  |---|---|
  | "Cycles" tab | "Placements" ("has participated in the following placement cycles") |
  | "Applications" | "Show Applications" |
  | "Offers & Blocks" | 🏆 per cycle |
  | enrolment "active" | "Enrolled" |
  | "Resumes" (student page) | "RESUMES & DOCUMENTS" |

- **Conflict check:** "Mark profile as verified / Ask Resubmission" assumes students edit their own academic profile. In our portal academic fields are **locked and admin-entered** (§2.3), so profile verification has little to check. Do not build it unless the owner asks. Not a hard conflict.

### F17. Send Notice to Shortlisted Students
- **Superset name and path:** shortlist page → "Send Notice to Shortlisted Students" → modal "**Create Notice**".
- **What it does:**
  - Banner: "This message will be sent to all applicants of <profile> <company>". The audience text says all applicants even though the button says shortlisted.
  - Fields: Title; tabs NOTIFY COLLABORATORS & ADMINS / NOTIFY USER GROUPS with "Select collaborators or other users to notify"; Content rich text (Paragraph, B/I/U, align ×3, superscript, subscript, bullet and numbered lists, undo/redo, link/unlink); "Add Attachment".
  - Buttons Cancel / Create Notice. Probably an in-app notice to students, also copied to chosen staff.
  - Used by admins.
- **Our status: NOT IMPLEMENTED** as a notice.
  - The nearest thing is an **Event** with audience "posting_applicants" (`backend/app/Http/Controllers/AdminEventController.php:113-136`). It is announce-only, typed (PPT, workshop, webinar, other) and needs a time, so it is not a free notice.
  - There is no per-round audience and no attachments.
- **Naming:** our "Events" / "Publish" vs Superset's "Notice" / "Create Notice".
- **Conflict check:** none. Delivery must follow the BCC-batch rule (D89, §3 Bulk mail) and the event rich-text rule (displayed as plain text, D76) or an equivalent sanitiser.

### F18. Send Email to Shortlisted Students and Send Email to Students On Hold
- **Superset name and path:** shortlist page → "Send Email to Shortlisted Students" or "Send Email to Students On Hold" → modal "**Send Email**".
- **What it does:**
  - Banner: "This message will be sent to all students shortlisted for Final Offer" (for the Offer stage).
  - Fields: Subject; the same notify tabs; Message rich text (adds image, "<>" source view and full-screen); "Add Attachment".
  - Buttons Cancel / Send Email. "On Hold" sends to the stage's waitlisted students.
  - Used by admins.
- **Our status: NOT IMPLEMENTED.** We send only the system mails: E4 round result and regret, E5 offer and regret, waitlist result mails (`PipelineService::publish`, `AdminResultController::publish`, `SendRoundResultMails` job, D75(b), D89, D90). There is no free-text mail to a round's selected or waitlisted students.
- **Naming:** our "Waitlisted" / "Waitlist" vs Superset's "On Hold"; our "selected" vs "Shortlisted".
- **Conflict check:** no owner rule forbids it. It must use `MailDispatchService::sendBulk` (BCC batches of `MAIL_BULK_BATCH_SIZE`, To = portal address, §3 Bulk mail), go only to institute addresses (§3 Mail), and be audit-logged. Attachments would need local-disk storage (S3 not planned).

### F19. Offline shortlist tracker (context, not a feature)
- At 3:24 and 4:46 the CDC's own Excel "Shortlisted Candidate Tracker_Written Test" (Superset ID, Name, Roll No, Program, Branch) is shown. CDC keeps shortlists outside Superset and probably uploads them through the ↑ icon.
- **Our status: IMPLEMENTED** as roll-number paste or .xlsx/.csv upload per round ("Enter results (paste / upload)", `pipelinetab.jsx:287, 328-353`; `SpreadsheetImportService`, D22). We key on Roll No, not a platform ID, so no change is needed.

### F20. NOC requests banner (seen on the cycle page; not part of this video's topic)
- **Superset:** "10 students are waiting on a No Objection Certificate." → "Review Requests".
- **Our status: NOT IMPLEMENTED** (no NOC code). Listed for completeness only. Probably for students taking off-campus offers. Out of scope here.
- **Conflict check:** none known. It needs owner input before any spec.

### F21. Other job-profile summary fields seen (not discussed in the video)
- Date of Visit / Process, Job Profile Group ("Assign group to this Job Profile"), Share Job Profile ("Copy link"), Flexible Mode for Job Profile Shortlisting ("Disabled"), Collaborators, TalentLens, Job Profile Tags, Withdrawal Options, Advanced Options, Attached Documents, Category.
- **Our status: NOT IMPLEMENTED,** except:
  - Eligibility (Edit eligibility, D103/D104);
  - Additional Questions (our Questions tab);
  - Stages (our Rounds tab);
  - Category (our "Offer category");
  - Withdrawal Options: per-drive withdrawal settings are deliberately absent ("There is no per-cycle 'no withdrawal' toggle", §3; excluded in §11). Building Superset's Withdrawal Options would **NEED OWNER DECISION**.
- These belong to other videos and are recorded here only so nothing is lost.

---

## 3. Gap summary

| Superset name | What to build | Priority | Conflicts |
|---|---|---|---|
| Shortlist for <Stage> (F9) | Per-round shortlist view: the previous round's pool, "N selected out of M candidates", green/red rows, ← / → round navigation, search, sort, paging; name opens a drawer | High | None (keep draft/published) |
| Shortlist for Offer, CTC edit (F10) | Edit offer type / CTC / stipend after publishing (audited; re-run BlockingPolicy if the type changes); optional spreadsheet upload of CTCs | High | Type change touches blocks; revoking an offer needs an owner decision |
| Download Current Shortlist (F13) | Export one round's selected list, with title rows "shortlisted during '<round>' to be proceeded to <next/FINAL OFFER>", a footer timestamp and IST | High | Company copy limited to the allowed field set |
| Process Status / completion card (F3) | On the posting page: per-round ✓ / 🏆 list with a "Shortlist" link each; a "The process is complete — final selected list" card linking to the offers | Medium | None |
| Progress Grid overall Status (F11) | Add an overall status column (Offered / Not selected ("Disqualified") / On hold / In process) and a legend; Excel button on the grid | Medium | None |
| Send Email to Shortlisted / On Hold (F18) | Compose an email (subject, rich text, attachment) to a round's selected or waitlisted students; BCC batches; audit | Medium | Must follow the BCC batch and institute-address rules |
| Send Notice (F17) | In-app notice (title, content, attachment) to a drive's applicants or a round's selected students | Medium | None (sanitise HTML as for events) |
| Custom export templates (F13) | Saved column sets for exports (e.g. "IIT(ISM)Dhanbad", "Data With all Sem CGPA") | Medium | Company-facing templates must respect the company field limits |
| Cycle reports / Lists (F2) | Lists: Job Offers, Students Placed, Students Not Placed; Placement Matrix (branch × company); Absentees report; Student Opportunities report; Job Profiles list export | Medium | "Application Preferences Report" depends on preferences (owner: later) |
| Activity feed (F5) | Posting-scoped timeline from audit_logs (floated, closed, round published, results published) | Low | None |
| Communication Log (F6) | Posting-scoped list of mails sent (from email_logs and the posting.notify ledger) | Low | None |
| Student drawer + reports (F16) | Quick-view drawer from pipeline and results; per-student "Placement Report" and "Eligibility Report" downloads | Low | Profile verification unnecessary (academics locked) |
| Job profile status labels (F1) | Show "Accepting Applications" / "Closed For Applications" / "In Process" / "Completed" (display only) | Low | Changing the stored enum would need an owner decision; labels do not |
| Download centre (F14) | Queued exports with Working / Download / Expired, "Email me this report", Cancel | Low | None |
| Update application history (F12) | Per-student stage editor, including turning a published result or an offer back | — | **NEEDS OWNER DECISION** (published rows never overwritten; Re-add protocol; offers/blocks) |
| Download all resumes ZIP (F15) | ZIP of the selected students' resumes per round, "<ROLL> <Name>.pdf" | — | **NEEDS OWNER DECISION** (owner chose signed links instead of a ZIP) |
| Mute communication to students (F7) | Per-drive switch suppressing automatic student mails | — | **NEEDS OWNER DECISION** (result and offer mails are rules) |
| Trigger placement story (F8) | Public placement announcement | — | **NEEDS OWNER DECISION** (publicising offers; behaviour unknown) |
| Withdrawal Options (F21) | Per-drive withdrawal settings | — | **NEEDS OWNER DECISION** (owner excluded a no-withdrawal toggle) |
| NOC requests (F20) | No Objection Certificate request queue | — | Owner input needed; out of this video's scope |

---

## 4. Rename list

| Where it appears in our UI or code | Our current name | Superset name |
|---|---|---|
| Admin nav (`components/admin/adminshell.tsx:48`) | Cycles | Placements |
| Admin nav (`adminshell.tsx:49`); postings list title (`app/admin/postings/page.jsx:66`) | Postings / Job Postings | Job Profiles |
| Posting page back link (`app/admin/postings/[id]/page.jsx:73`) | All Postings | Placements / <cycle> (breadcrumb) |
| Cycle detail tab (`app/admin/placement-cycles/[id]/page.jsx:323`) | Postings | Job profiles (list) |
| Postings table column (`components/admin/cyclepostings.jsx:60`) | Company · Role | Company / Profile |
| Posting status label `open` (`lib/format.js` titleCase) | Open | Accepting Applications |
| Posting status label `in_process`, before any results | In Process | Closed For Applications |
| Posting status label `in_process`, results being entered | In Process | In Process |
| Posting status label `completed` | Completed | Completed (no change) |
| Posting page tab (`app/admin/postings/[id]/page.jsx:32`); Rounds table column "Round" (`roundstab.jsx:107`) | Rounds / Round | Stages / Stage |
| Pipeline tab (`app/admin/postings/[id]/page.jsx:29`) | Pipeline | Process Grid ("View Progress Grid") |
| Pipeline column header (`pipelinetab.jsx:230`) | pool N | N candidates ("N selected out of M candidates") |
| Pipeline result chip `selected` (`pipelinetab.jsx:55`) | Selected | Passed (grid) / Shortlisted (history editor) |
| Pipeline result chip `rejected` | Rejected | Failed |
| Pipeline result chip `waitlisted` (`pipelinetab.jsx:55`); Waitlist tab (`page.jsx:30`) | Waitlist / Waitlisted | On Hold |
| Pipeline result chip `pending` | Pending | In Process |
| Final round chip (`pipelinetab.jsx:225`, `roundstab.jsx:119`) | Final | 🏆 (final stage) / Offer |
| Posting header button (`app/admin/postings/[id]/page.jsx:91`) | Results & Offers | Shortlist for Offer |
| Results page title (`results/page.jsx:242`) | Results — <company> · <title> | Shortlist for Offer - <profile> |
| Results card heading (`results/page.jsx:284`) | Final selections | Shortlist for Offer (Technical interview R2 → Offer) |
| Results column (`results/page.jsx:293`) | Compensation | CTC Offered / CTC Interval |
| Results input labels (`results/page.jsx:191, 199`) | CTC / year (INR); Stipend / month (INR) | CTC Offered + CTC Interval (YEAR / MONTH) |
| Results offered marker (`results/page.jsx:147`) | Offered (chip) | 🏆 / OFFERED |
| Results waitlist heading (`results/page.jsx:303`) | Waitlisted (tick to promote) | Students On Hold |
| Offer type label `intern_performance_ppo` (`backend/app/Models/Offer.php:20`, `frontend/lib/offerpolicy.js:13`) | Intern + performance-based PPO | Intern + Performance Based PPO |
| Overview stat cards (`overviewtab.jsx:89-92`) | Eligible students / Applied | Application Progress — "X applied out of Y eligible"; "N Applicants" |
| Overview detail (`overviewtab.jsx:137-139`) | Floated | Applications Published (activity) |
| Overview lifecycle button (`overviewtab.jsx:208`) | Close applications | Closed For Applications (status) |
| Posting export button (`app/admin/postings/[id]/page.jsx:88`) | Export | Download Current Shortlist (per stage) / Applicant List |
| Export column (`ExportService.php:46`) | Programme | Current Program |
| Export column (`ExportService.php:47`) | Branch | Current Course |
| Export column (`ExportService.php:49`) | CGPA | Current CGPA |
| Export columns (`ExportService.php:52-53`) | 10th % / 12th % | Class 10 % / Class 12 % |
| Export column (`ExportService.php:80`) | Resume Label | Attached Resume |
| Export column (`ExportService.php:118`) | Offer CTC (annual) | CTC offered (+ CTC Currency, CTC Interval) |
| Export column (`ExportService.php:119`) | Offer Stipend (monthly) | CTC offered with CTC Interval = MONTH |
| Export link text (`ExportService.php:140`) | Open resume | Link |
| Cycle export button (`placement-cycles/[id]/page.jsx:284`) | Export Students | Student Placements Report / Enrolled Students List |
| Admin student page tab (`app/admin/students/[id]/page.jsx:179`) | Cycles (n) | Placements ("participated in the following placement cycles") |
| Admin student page tab (`students/[id]/page.jsx:180`) | Applications (n) | Show Applications |
| Admin student page tab (`students/[id]/page.jsx:182`) | Audit trail | Activity |
| Cycle enrolment status (`cycle_enrollments.status` `active`) | Active | Enrolled |
| Pipeline / Re-add / draft actions (`pipelinetab.jsx:76, 83, 388`) | Re-add previously rejected candidate / Remove this draft | Update application history |
| Student round trail (`components/student/roundtrail.jsx:8-11`) | Cleared / Selected / Not selected / Waitlisted / Result pending | Shortlisted / Offered / Failed / On Hold / In Process |
| Audit Log page (admin nav, `adminshell.tsx:64`) | Audit Log | Activity (per profile) |

None of these is a stored value that must change. The owner rule "never rename existing routes, columns or response keys" (§10) means every rename above is a **UI label change only**.

---

## 5. Uncertain

- **Upload (↑) icon** on the "Shortlist for Offer" page (also seen briefly on other stage pages). It was never clicked and no tooltip appeared. It is probably "upload shortlist / offer details from Excel", but this cannot be confirmed.
- **Lock icon** on "🔒 Technical interview R2" in the Update application history modal. It may mean the final or offer stage is locked, or locked because the profile is completed. The presenter clicked the card and it changed to "OFFERED", and "Save Changes" became active, so it does not seem to block editing.
- **What the red card meant** at 2:40. Only its red left border was visible before the horizontal scroll, so the exact label of the not-selected state (e.g. "REJECTED" / "NOT SHORTLISTED") was not readable.
- **Overall Status values** in the Progress Grid. Only "Disqualified" was visible; the labels for offered or in-process applicants (e.g. "Offered", "Selected", "In Process") were not shown.
- **Excel columns P to Z** of the shortlist export were never scrolled into view, so the columns between "UG percentage" and "CTC offered" are unknown (probably PG/other academic fields, contact details and answers).
- **"CTC Interval" options.** Only "MONTH" was seen; "YEAR" (or "ANNUM") is assumed.
- **"Trigger placement story"** and the speaker icon next to it were never clicked, so their behaviour is unknown.
- **"Mute communication to students ⚙"** was never opened; its scope (all mails, or chosen types) is unknown.
- **"NOTIFY USER GROUPS"** tab contents were never shown.
- **"Send Notice to Shortlisted Students" audience.** The button says shortlisted, but the modal says "all applicants of <profile>". It is unclear whether the notice reaches all applicants or only the stage's selected students.
- **"136 Applicants •••" menu, the top kebab menus and the PDF icon** on the job profile page were not opened.
- **"Communication Log"** and the other left-nav sections of the job profile (Details, TalentLens, Withdrawal Options, Advanced Options and so on) were not opened.
- **"Offer Letter" / "Signed Offer Letter"** appear only as Excel columns (both "NA"). The video shows no screen where an offer letter is uploaded or where a student uploads a signed copy, so the workflow (admin upload vs student upload, and whether signing implies acceptance) cannot be inferred. If a student-signed offer letter means an accept step, it would touch the owner rule "students cannot decline an offer; once selected, they accept" (**needs owner decision** before anything is built).
- **"Download Placement Report" / "Download Eligibility Report"** in the student drawer were not clicked; their content is unknown.
- **The 🏆 / ⚙ / ☰ icons** on each cycle row in the student drawer were not explained. 🏆 probably means "has an offer in this cycle".
- **"Flexible Mode for Job Profile Shortlisting: Disabled"** is not explained in the video.
