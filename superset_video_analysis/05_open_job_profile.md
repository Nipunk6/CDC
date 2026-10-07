# 05 — "How to open a profile for application" (Superset admin, 209 s)

Source frames: `scratchpad/kf/05_open_job_profile/` (57 frames, t000m00s to t003m26s, all read in order).
Our code checked: `CDC/backend` (routes/api.php, AdminPostingController, JobPosting, SendPostingFloatedMails, EligibilityService, CompanyPipelineController, StudentApplicationController, migrations) and `CDC/frontend` (components/admin/floatdialog.jsx, questionbuilder.jsx, editeligibilitydialog.jsx, cyclepostings.jsx, components/admin/posting/*, app/admin/postings/*, adminshell.tsx, studentshell.jsx, companyshell.tsx, components/forms/jnfformpro.tsx, lib/offerpolicy.js, lib/format.js).

Superset vocabulary used in this video: a placement cycle is a **Placement**; a drive is a **Job Profile**; floating is **Open (Profile) for Applications**; rounds are **Stages**; application questions are **Additional Questions**; the branch matrix is **Applicable Courses**.

---

## 1. Video walkthrough

Persistent chrome in every frame (admin, logged in as "Career Development Centre IIT ISM Dhanbad", avatar "CD"):
- Top bar: institute name "Indian Institute of Technology Indian School of Mines Dhanbad", a "Search students" box, a download icon, a bell, an apps-grid icon, the account name and avatar.
- Left sidebar (collapsible with an "x"): Home, My Dashboards; section JOBS: Companies, Inbound Job Posts, **Placements** (highlighted throughout), Job Alerts (sparkle icon); section RELATIONSHIPS: Students, CRM; section ENGAGEMENT: Notices, Surveys, Calendar; section ADMIN: Documents, TalentLens Rubrics.
- A chat-support bubble bottom right.

**0:00 — Placements list.** URL `app.joinsuperset.com/#/admin/placements`. Page title "Placements".
- Left panel: purple calendar icon, heading "Placements", explainer text (cycles = distinct recruitment phases such as Final Year Placements and Pre-Final Year Internships, each with its own timelines, policies and teams, tailored to one student batch), and an outlined button "+ Add placement process".
- Centre: a "Search Placements" box, then current cycles as cards (calendar icon, name, date range, gear icon on the right):
  - "Full-Time Placement for 2026-27 (2027 Pass Out Batch)", June 2026 - June 2027
  - "Internship Placement || 2026-2027 Session || 2028 Pass out batch", April 2026 - August 2027
- Sub-heading "Previous Placements": "Internship Placement Cycle for 2029 Graduating Batch [DRAFT]" (gold icon, March 2026 - February 2026), "Full-Time Placement for 2025-26 (2026 Pass Out Batch)" (May 2025 - May 2026), "Internship Placement for 2025-26 (2027 Batch)" (May 2025 - May 2026), more below.
- Right column "Recently Visited": same style list with gear icons, including "FT Placement for 2023-2024 (2024 passouts)" (May 2023 - December 2024) and "Full-Time Placement for 2024-25 (2025 Pass Out Batch)" (May 2024 - May 2025).

**0:20 — Opens "Full-Time Placement for 2026-27 (2027 Pass Out Batch)".** URL `/admin/placements/<cycle-uuid>?search=(length:50,order:!((column:0,dir:desc)),search:(),se…` (the job list state is kept in the URL).
- Header: back arrow + cycle name. A yellow banner: "**10** students are waiting on a No Objection Certificate." with a link "Review Requests" at the right.
- Left info column: calendar icon, "Jun 2026 - Jun 2027"; green pill "1901 Enrolled Students" with "out of 2044 eligible students"; "Important Links (i)" with "Dashboard"; "Collaborators (i)" with one avatar and a "+" circle; "Enrollment Deadline (i)" = "11 Aug 2026, 18:00".
- Job list toolbar: "Status" dropdown (value "All"), a "Search" field with placeholder "Start typing ...", a small people/filter icon button, and "Check Eligibility" (funnel icon) on the right.
- Table columns: **Company | Profile | Date of Visit | Deadline | Status**. Each row has a company logo, truncated company name, truncated profile title, a calendar icon in Date of Visit, a deadline like "Oct 06, 2026 05:00 PM", and a coloured status. Rows visible: Siemens EDA India Pr... / Student Intern (Software ... / Completed; CentrAlign AI / Founding Engineer / Draft; Accenture S&C / GN Management Consulting ... / Oct 06, 2026 05:00 PM / Accepting Applications (blue dot); Cracku / JEE Physics Content Inter... / Draft; Texas Instruments / Digital Engineer / Oct 03, 2026 12:00 PM / Closed For Applications; Texas Instruments / Analog Engineer / same; Deloitte Consulting ... / Analyst or Equivalent – D... / Draft; Axxela Research & An... / Trainee Analyst / Oct 03, 2026 10:00 AM / Closed For Applications; Josh Talks Pvt. Ltd.... / Product Manager / Oct 01, 2026 05:00 PM / Closed For Applications; Accenture Japan Ltd. / Digital Consultant / Sep 30, 2026 05:00 PM / In Process; Microsoft / Applied Scientist with a grey "PPO" chip / Completed.

**0:22-0:24 — Page fully loaded.** A kebab menu and an Excel-export icon appear top right; "Edit Placement" link under the date range; "+ Add New Job" appears in the toolbar left of "Check Eligibility". "Important Links" expands to: Dashboard, Placement Matrix Report, Placement Absentees Report, Student Placements Report, Student Opportunities Report, Download Job Profiles List, Student Offers Report, Application Preferences Report, then "List : Job Offers", "List : Students Placed", "List : Students Not Placed".

**0:30 — Clicks "+ Add New Job".** URL `/admin/placements/<cycle>/companies/jobprofiles/new?...`. Title "Full-Time Placement for 2026-27 (2027 Pass Out Batch) / New Job Profile (i)". Two panes:
- Left, "Auto-fill with AI": "Upload a Job Description PDF or paste the JD text to pre-fill the form." A dashed drop zone "Click or drop PDF here", "Max 5 MB"; separator "— or paste JD text —"; a textarea "Paste the job description text here..."; purple button "Extract & Pre-fill".
- Right, the form (asterisk = required):
  - "Company*" with helper "Suggestions will appear once you start typing company name." and placeholder "Start typing company name ..."; beside it "Not listed?" and "+ Add a new company".
  - "Job Profile Title*" (text).
  - "Job Profile Source*" with a "Manage Sources" (gear) link and helper "Is this job profile is part of **Campus Placement** or is a **PPO** or an **External Offer** etc."; dropdown "Select an Option".
  - "Job Location*" (text).
  - "Job Function*" multi-select "Select Some Options".
  - "Category*" (continues below).

**0:32 — Scrolls the form.**
- "Category*" dropdown "Select an Option".
- "Salary/Stipend*": "Amount*" with a "Specify Range" link, "Currency" dropdown (INR), "Interval" dropdown (shows "AN..." truncated). Checkboxes "This job profile does not have any Stipend" and "Stipend is to be announced later". A checkbox labelled "Add Salary break-up / Additional Details / Equity Offered".
- "Job Description" marked "Optional": rich-text editor with Paragraph style dropdown, Bold, Italic, Underline, align left/centre/right, superscript, subscript, bulleted list, numbered list, undo, redo, insert link, remove link.

**0:34 — Bottom of the form.** Checkbox "Import job details of another job (i)". Checkbox "**Allow Flexible mode for Job Profile Shortlisting**" with helper "This feature enables partial shortlisting of the job profile. Do not enable it unless necessary." A divider, then red text "The form has errors", a disabled-looking "Create New Job Profile" button and "Cancel". The presenter does not fill the form.

**0:44 — Leaves the form.** Back on the Placements list (URL `/admin/placements?search=...`) with a red toast "Uh-Oh! Placement does not exist" (a navigation glitch, not a feature).

**0:46 — Re-opens the FT 2026-27 cycle** (toast still visible), then **0:48 clicks the CentrAlign AI "Founding Engineer" row (status Draft).**

**0:48-0:50 — Job profile page (draft).** URL `/admin/placements/<cycle>/companies/<company-uuid>/jo...`. Page title "Founding Engineer - [DRAFT]". Header icons top right: a speaker/megaphone icon, a red PDF icon, a kebab menu.
- Hero: placeholder building image, breadcrumb "Placements / Full-Time Placement for 2026-27 (2027 Pass Out Batch) / Founding Engineer", title "Founding Engineer", subtitle "CentrAlign AI · Remote", "Date of visit: «Not Updated» (i)" with a pencil, chips "Full Time", "Full Time Hiring", and an outlined chip "Mute communication to students" with a gear.
- Left section nav (scroll-spy): Collaborators, Summary, Details, Additional Info, TalentLens, Job Profile Tags, Additional Questions, Applicable Courses, Eligibility, Stages, Attached Documents, Category, Withdrawal Options, Advanced Options; separated below: Communication Log.
- Right yellow panel: "Please complete the following steps to publish the job profile" with a green-ticked, struck-through checklist: "Assign a category", "Select applicable courses", "Set eligibility criteria", "Setup hiring workflow". Below, a large green tick, "Looks like the job profile is ready to be submitted", and a green "Submit Now" button.
- Main body: "Collaborators (i)" with two "CD" avatars and a "+" button. "Summary": Company "CentrAlign AI" (pencil); Created "Created for **Full-Time Placement for 2026-27 (2027 Pass Out Batch)**"; Date of Visit / Process "«Not Updated» (i)" (pencil); Status chip "DRAFT".

**0:54 — Summary continued.** Job Profile Group "Assign group to this Job Profile" (dotted link); Share Job Profile "Copy link (i)"; Flexible Mode for Job Profile Shortlisting "Disabled" (pencil). Then "Job Profile Details" with an "Edit" link: Title "Founding Engineer", Job Location "Remote".

**1:00 — Details.** Position Type "Full Time"; External ATS (i) "DISABLED"; Expected Number Of Hires "NOT MENTIONED"; Job Functions chip "Engineering - Web / Software"; Cost-to-Company (CTC) "₹ 24,00,000.00 per Annum"; "Salary Break-up / Additional Compensation" as a table "Salary Details": Stipend during internship "N/A (full-time role)"; Internship Start Month and Year / Internship End Month and Year (blank); "CTC (in LPA) after PPO conversion *" = 24; "Fixed*" = "18 LPA (Rs 1.5 lakh/month in..." (truncated); "Joining Bonus" = "Click here to enter text." (This break-up table looks like the JNF's own template imported as rich content.)

**1:02 — Skills and TalentLens.** "Skills Required": "You have not added any required skills yet! Click here to add skills + Add Skills". "TalentLens Profile Match Configuration" (sparkle): "Configure Superset TalentLens to automatically generate a match score for applicants on this job profile." Box: "No Profile Match Configuration associated with this job profile." link "Add Configuration". Then "Additional Information (i)" with "Edit".

**1:04-1:06 — Additional Information content.** Very large bold text: a CDC disclaimer that the CDC cannot verify every company and students should research the company before applying, followed by the rule that candidates must read the whole notification and apply only if they meet every criterion (CGPA, backlog, education gap) and employment condition (medical, bond), from application until joining, or face consequences (text cut off).

**1:08 — Job Profile Tags and Additional Questions.** "Sector *" multi-select with chip "Software/IT ×". "Additional Questions (i)": helper "If you have some specific questions about this job profile that you want to ask students. You can add that by clicking on 'Add Question'". One existing question card with edit (pencil) and delete (trash) icons and a type tag "DROPDOWN : SINGLE". Its text is an acknowledgement that the student will take part in all of the company's processes, that skipping any process without written CDC approval blocks them for 5 ongoing on-campus opportunities, and that a violation blocks them permanently from 2027 on-campus full-time opportunities. Below the card: "+ Add Additional Question".

**1:10-1:14 — Adds a question.** The inline form: "Question Title*" with helper "Text of the question to be asked. For eg. What is your Father's Name" (text input); "Answer Type*" with helper "Choose the type of answer", dropdown placeholder "Select answer type"; checkbox "This question is mandatory"; "Help Text" with helper "Anything that will help the users understand that what kind of information is required." (text input); buttons "Add Custom Question" (blue) and "Cancel".

**1:20 — Validation.** Focus leaves the empty title: the field turns red with "Required field". The answer-type dropdown is hovered but never opened.

**1:28-1:38 — Applicable Courses.** "Applicable Courses (i)" with "Edit Courses". A tab per institute ("INDIAN INS...ANBAD"). A two-column table: left the programme with its code, right a bulleted list of courses in the form "<Course> - <Department> (<Specialisation in italics>), <School>", e.g. "Electrical Engineering - Electrical Engineering (Power System Engineering), Department of Engineering", "Data Analytics - Mathematics & Computing , Department of Engineering". Programme groups seen: (first group's label scrolled off), "M.Tech. (IITISMD_63)" with a long list, "M.Tech (Integrated) (IITISMD_63)" with Applied Geophysics, Mathematics & Computing, Applied Geology. "Show More" / "Show Less" toggles the list. Then "Allowed Batches" with "Edit Batches": one card "2027 Passout Batch". Then "Eligibility Criteria (i)" with "Edit".

**1:42-1:44 — Clicks "Edit Courses".** Modal "Set Course Eligibility", "Please select the courses applicable for the current job profile", a grid/list view toggle (list selected), institute tab "INDIAN INS...ANBAD". Left: programme list, each with a code in italics (iitismd_63): M.Sc. (Tech., 3 years) (selected), M.Tech., M.Tech (Integrated), MBA (Semester), B.Tech, M.Sc., MA (more below). Right: checkbox "Select all courses in M.Sc. (Tech., 3 years)", a search box "Search by course name..", course checkboxes "Applied Geology , Applied Geology , Department of Science & Technology", "Applied Geophysics , Applied Geophysics , Department of Science & Technology". Footer "Cancel" / "Save". **1:46** the modal is cancelled.

**1:48-2:02 — Eligibility Criteria.** "Select eligibility criteria conflict resolution mode": candidates with several degrees at the same level (for instance two UG degrees) may have a lower score in one; when set to ALL the student may apply only if every education of that level passes. Toggle "ANY | ALL" (ALL selected). Then a row per allowed programme:
- M.Tech.: "All students of M.Tech. are **eligible**." "You can + Add a Criteria to define conditions for student eligibility," "+ Add Criteria", "+ Add allowed programs for student previous education".
- B.Tech: same text, "+ Add Criteria" (no previous-education link).
- M.Tech (Integrated): same, "+ Add Criteria".
Below, collapsible criteria rows (icon, title, chevron) start: "Work Experience Criteria", "Attendance Criteria".

**2:04-2:06 — Clicks "Edit" on Eligibility Criteria.** Full-page "Set Eligibility Criteria", "You can either allow all students to apply or set eligibility criteria for respective courses by setting overall / indivisual percentage(%) below". Grid columns: (programme) | **Allow All** | **Set Overall%** | **PG** | **Dual Degree** | **UG** | **Diploma** | **Class 12** | **Class 10**. Rows M.Tech., B.Tech, M.Tech (Integrated), each with "Allow All" ticked (so the other inputs are greyed). Inputs show "–" and a "%" suffix; some cells are "N/A" (B.Tech: PG and Dual Degree; M.Tech (Integrated): PG); the programme's own level has a small dropdown after "%" (M.Tech: PG; B.Tech: UG; M.Tech (Integrated): Dual Degree). Section "Add allowed previous education for students": "You can select allowed previous education for M.tech and Ph.D students. For example, All M.Tech students must have B.Tech in Computer Science to be eligible" and a "+ Add Criteria" button. Footer "Cancel" / "Save Ch(anges)" (cut by the chat bubble).

**2:12 — Back; full list of criteria rows:** Work Experience Criteria; Attendance Criteria (% icon); Gender Criteria; Backlog Criteria (green chip "No ongoing backlogs allowed"); Age Criteria; Education Gap Criteria; Resume Prerequisite (green chip "Mandatory"); Exam Score Criteria; Allowed Student Categories. None is expanded.

**2:14 — Stages (hiring workflow).** A vertical flow of stage cards joined by arrows: "Resume shortlisting", "Any Other Round", "Technical interview". Each card: icon, name, "(no-entry icon) Venue and schedule not added yet", link "Add venue & schedule", gear and trash icons. Then a dashed "+ Add New Stage" card and a final "Offer" node (trophy). Right column: a stage library, each with "+ Add": (one item cut off above), Resume shortlisting, Written test, Group discussion, Technical interview, HR interview, Online test, Take Home Assignment.

**2:20 — Attached Documents and Category.** "Attached Documents" with "+ Add Document"; table "Name | Uploaded": "JNF_IIT_ISM_Dhanbad_FoundingEngineer.pdf" / "03:50 PM, 05 Oct 2026" / "Remove" (trash). "Category" with "Edit": "Level 1 - General 1" with bullets "Max offers in this category: 1", "Max attempts before any offer: Unlimited", "Max attempts after any offer: Unlimited".

**2:24 — Withdrawal Options and Advanced Options.** "Withdrawal Options (i)": yellow note "Students are prohibited from withdrawing their applications for this placement cycle. Use the settings below to override this configuration and allow application withdrawl for this job profile." Checkbox "Allow student application withdrawal after the job profile is in process?" (unticked). "Advanced Options" with a "Show Advanced Options" expander (never opened).

**2:26-2:30 — Submit.** Clicks "Submit Now". Modal "Confirm": "Are you sure you want to submit job profile - Founding Engineer?" with "Yes" / "No". Clicks Yes.

**2:32-2:34 — Error.** Two red toasts: "Uh-Oh! Error in Attribute Definition - Invalid JSON string in values : Must be an array of strings: Config Error, Contact Support". (A Superset configuration bug; the profile stays DRAFT.)

**2:36 — Reloads the page** ("Getting opportunities ..." spinner).

**2:38 — After reload** the sidebar gains two sections above Home: "RECENT JOB PROFILES" (CentrAlign AI... · Founding Eng..., C-DAC, Kolkata... · Knowledge, Accenture Japa... · Digital Cons...) and "RECENTLY VISITED PLACEMENTS" (Full-Time Placement for 2..., Internship Placement || ...).

**2:40 — Clicks "Submit Now" again** (confirm not captured), and this time it works.

**2:44 — Submitted state.** Title loses "[DRAFT]"; Summary Status chip "SUBMITTED". The right panel now shows: a blue split button "No Applicants" with a "•••" menu; "Application Progress" bar with "0 applied out of (loading) eligible"; an alarm-clock icon and "Job profile - Founding Engineer has been submitted. You can open applications for this job profile."; green button "Open Profile for Applications"; "OR"; outlined button "Send Applicant List to company"; "Activity": "Submitted a few seconds ago".

**2:48 — Clicks "Open Profile for Applications".** A modal fades in (first frame): "Set Application Deadline" with spinner controls for hours : minutes : seconds and AM/PM ("06 : 45 : 55 PM"), a date field "06 Oct 2026" with a calendar button; checkbox "Share job applications progress with company."; blue info line "Application progress will be shared with the company."; grey note "A notice will be created and all eligible students will be notified via email and SMS"; buttons "Schedule For Later" (green, with an icon), "Cancel", "Open for Applications". The eligible count has loaded: "0 applied out of 1350 eligible".

**2:50-2:58 — Modal closed;** the presenter scrolls the Summary/Details and Applicable Courses again (no changes).

**3:00 — Re-opens the modal, now titled "Open Applications".** "Set Application Deadline": "06 : 46 PM" (no seconds field this time) and "06 Oct 2026" with the calendar button being clicked. Same checkbox, info line, note and three buttons.

**3:10-3:12 — Applications opened.** A progress modal lists three steps with spinners: "Sending Emails ...", "Publishing new Notice ...", "Sending Mobile Notifications ...", and a disabled "Close" button. Behind it the right panel has changed: inbox icon, "Eligible students can now apply for job profile - Founding Engineer. The application deadline is set to **05:00 PM on 07 Oct 2026**", buttons "Change Application Deadline" and "Send Reminder Email"; Activity "Open For Applications a few seconds ago", "Submitted a few seconds ago".

**3:20-3:26 — Progress modal closed;** the presenter scrolls to the Eligibility criteria list once more. End.

---

## 2. Superset features shown

Status legend: IMPLEMENTED / PARTIAL / NOT IMPLEMENTED. "NEEDS OWNER DECISION" marks anything that would contradict, or reopen, a recorded owner decision in `CDC_PORTAL_CONTEXT.md` / `PHASE2_DECISIONS.md`.

### A. Cycle-level screens seen on the way (likely covered in more depth by the cycle videos)

**A1. Placements list** — sidebar "Placements" (`/admin/placements`).
- Search Placements; current cycles; "Previous Placements"; a [DRAFT] cycle; per-cycle gear (settings); "Recently Visited" column; "+ Add placement process". Admin only.
- Ours: PARTIAL. `frontend/app/admin/placement-cycles/page.jsx` (heading "Placement Cycles", line 216-217) lists cycles with create/close; `placement_cycles.status` is only `open|closed` (`backend/database/migrations/2026_09_27_000004_create_placement_cycles_table.php:20`). Missing: search box, current/previous split, draft cycles, recently-visited list.
- Naming: ours nav "Cycles" (`components/admin/adminshell.tsx:48`), page "Placement Cycles" vs Superset "Placements" / "Add placement process". Rename optional (see Rename list); "Placement Cycles" is clearer, keep unless the owner wants Superset wording.
- Conflict: none.

**A2. Cycle header block** — "1901 Enrolled Students out of 2044 eligible students", "Important Links" (11 reports/lists), "Collaborators", "Enrollment Deadline", NOC banner "10 students are waiting on a No Objection Certificate. Review Requests", "Edit Placement", Excel export icon, kebab.
- Ours: PARTIAL. Cycle page stat cards "Enrolled students", "Postings floated", "Offers made" (`app/admin/placement-cycles/[id]/page.jsx:229-231`), tabs Overview / Enrolled Students (N) / Postings / Blocks (lines 321-324), cycle export (`GET /admin/placement-cycles/{id}/students/export`, routes/api.php:91). Missing: "out of N eligible" denominator, enrolment deadline (no column in the migration), collaborators, NOC requests, the named report links (we have Analytics and the cycle export instead).
- Conflict: none recorded. Out of scope for this video; listed for completeness.

**A3. Cycle job list** — columns Company | Profile | Date of Visit | Deadline | Status; Status filter ("All"), "Search … Start typing", a filter icon, "+ Add New Job", "Check Eligibility"; PPO chip on a row; statuses Completed, Draft, Accepting Applications, Closed For Applications, In Process.
- Ours: PARTIAL. Cycle tab `components/admin/cyclepostings.jsx:60-64` has columns "Company · Role | Form | Status | Applied | Deadline" with no filter/search; the global list `app/admin/postings/page.jsx` has Placement cycle + Status filters (lines 80-101) but no search. No Date of Visit, no "Add New Job" (see B1), no "Check Eligibility" tool, no PPO marker, no Draft rows (a posting exists only after floating).
- Naming: column "Company · Role" vs "Company" + "Profile"; status "Open" vs "Accepting Applications" (see B24).
- Conflict: none.

### B. Job profile features (the subject of the video)

**B1. New Job Profile created by the admin inside a cycle** — cycle page "+ Add New Job" → `/admin/placements/<cycle>/companies/jobprofiles/new`, title "New Job Profile". Admin only.
- Fields: Company* (type-ahead, "Not listed? + Add a new company"), Job Profile Title*, Job Profile Source* (+ "Manage Sources"), Job Location*, Job Function* (multi), Category*, Salary/Stipend* (Amount*, "Specify Range", Currency, Interval, two checkboxes, salary break-up checkbox), Job Description (optional, rich text), "Import job details of another job", "Allow Flexible mode for Job Profile Shortlisting". Validation: required asterisks, "The form has errors" blocks "Create New Job Profile". The profile is created as DRAFT.
- Ours: NOT IMPLEMENTED. A drive can only come from a company-filled JNF/INF that the CDC accepts and then floats (`AdminPostingController@store`, `backend/app/Http/Controllers/AdminPostingController.php:121-207`, refuses non-accepted forms at 141-143). There is no `POST /admin/jnfs` or `POST /admin/infs` and no admin "create company" route (admin companies routes are index/show/update only, routes/api.php:186-188).
- What to build: an admin "New job profile" path (create a JNF/INF on behalf of a company, or a CDC-owned posting) inside a cycle, plus "Add a new company" for off-portal recruiters.
- Naming: n/a (absent). If built, call it "Add New Job" / "New Job Profile".
- Conflict: no recorded decision forbids it, but it changes how drives originate (company form → CDC review → float). Product-behaviour change → confirm with owner (standing rule, context section 10).

**B2. Auto-fill with AI** — left pane of the New Job Profile form: JD PDF upload ("Click or drop PDF here", "Max 5 MB") or pasted text, "Extract & Pre-fill".
- Ours: NOT IMPLEMENTED (no AI/extraction code in `backend/app`).
- Conflict: needs a new external AI service or package, which the owner must approve ("No package installs or downloads without the owner's permission"). NEEDS OWNER DECISION. Priority Low.

**B3. Job Profile Source** — dropdown "Select an Option", helper names Campus Placement, PPO, External Offer; "Manage Sources" lets the CDC edit the list.
- Ours: NOT IMPLEMENTED as such. Our float-time "Offer category" (Full-Time / Intern + Full-Time for a JNF; Internship / Intern + performance-based PPO for an INF; `JobPosting::OFFER_CATEGORIES`, `backend/app/Models/JobPosting.php:118-121`; `frontend/lib/offerpolicy.js:6-15`) is a different axis (what the offer means for blocking), not where the opportunity came from. There is no way to record a PPO or an external/off-campus offer that did not come through a floated drive.
- Conflict: recording external offers would interact with blocking (`BlockingPolicy`) — owner should decide whether such offers block. NEEDS OWNER DECISION. Priority Medium.

**B4. Job Function** — required multi-select; shown on the profile as a chip "Engineering - Web / Software".
- Ours: NOT IMPLEMENTED per job. The company profile has "Sector" and "Industry Sector Tags" (`components/forms/jnfformpro.tsx` labels at lines 726 and 790), which are company-level.
- Priority Low (useful for analytics and later job alerts). Conflict: none.

**B5. Category (offer tier)** — required on create; on the profile "Category" → "Level 1 - General 1" with "Max offers in this category: 1", "Max attempts before any offer: Unlimited", "Max attempts after any offer: Unlimited"; first item of the publish checklist ("Assign a category").
- Ours: PARTIAL. We have one "Offer category" per posting (float dialog `components/admin/floatdialog.jsx:206-218`; Overview tab `components/admin/posting/overviewtab.jsx:162-174`) that drives the fixed CDC blocking matrix (`BlockingPolicy`). No tiers/levels, no per-category offer cap, no attempt counters.
- Naming: ours "Offer category" vs Superset "Category". Keep ours (more precise) or rename to "Category"; low value.
- Conflict: tier levels with "max offers" and "attempts before/after an offer" are Superset's dream-offer machinery. The owner decided "There is no 'dream offer' rule" and blocks are fixed by offer type. NEEDS OWNER DECISION. Do not build without approval.

**B6. Salary/Stipend block** — Amount* with "Specify Range", Currency (INR), Interval (value truncated "AN..."), "This job profile does not have any Stipend", "Stipend is to be announced later", "Add Salary break-up / Additional Details / Equity Offered". Profile shows "Cost-to-Company (CTC) ₹ 24,00,000.00 per Annum" and a salary break-up table.
- Ours: PARTIAL. The company JNF has currency, per-programme CTC (`programmeSalaries`) and `salaryComponents` (`jnfformpro.tsx:116-119`); the posting exposes it via `JobPosting::compensationFor()` (`JobPosting.php:184-228`) and Overview "Compensation" (`overviewtab.jsx:129-136`). Missing: "to be announced later" and "no stipend" flags, explicit pay interval, equity field, admin entry (company enters it).
- Naming: ours "Compensation" (Overview) vs "Cost-to-Company (CTC)" / "Salary/Stipend". Minor.
- Conflict: none.

**B7. Job Description (rich text, optional)**.
- Ours: IMPLEMENTED in the company JNF ("Job Description", RichTextEditor, `jnfformpro.tsx:919-922`), editable by the admin through the JNF editor (`PATCH /admin/jnfs/{jnf}/form-data`, routes/api.php:176). Students see it through `components/shared/postingpreview.jsx`.
- Conflict: none.

**B8. Import job details of another job** — checkbox on the New Job Profile form.
- Ours: PARTIAL. Companies can duplicate their own JNF/INF (`POST /company/jnfs/{jnf}/duplicate`, routes/api.php:211; `/company/infs/{inf}/duplicate`, 224). Admins cannot copy one posting's details into another.
- Priority Low. Conflict: none.

**B9. Flexible mode for Job Profile Shortlisting** — create-form checkbox and a Summary row "Flexible Mode for Job Profile Shortlisting: Disabled" (editable). Helper: enables partial shortlisting; "Do not enable it unless necessary."
- Ours: NOT IMPLEMENTED. Our pipeline already lets the admin upload shortlists for any subset at any time after applications close (`AdminPipelineController@results`), so the exact meaning of "partial shortlisting" is unclear (see Uncertain).
- Priority Low. Conflict: none known.

**B10. Job profile page layout** — title "<Title> - [DRAFT]", breadcrumb Placements / <cycle> / <title>, hero (logo, title, "Company · Location", Date of visit, chips "Full Time", "Full Time Hiring", "Mute communication to students"), left scroll-spy section nav (Collaborators … Advanced Options, Communication Log), right action panel (checklist / progress / next action / Activity), header icons (speaker, PDF, kebab).
- Ours: PARTIAL. The admin posting page (`app/admin/postings/[id]/page.jsx`) has a header "<Company> — <Title>", subtitle "<cycle> · apply by <deadline>", status chip, "Edit eligibility", "Export", "Results & Offers" (lines 68-95), and tabs Overview, Applicants, Eligible, Pipeline, Waitlist, Proposals, Rounds, Questions (lines 25-34). The job's own details (description, salary, skills, eligibility text) live on a separate page, the admin JNF/INF review page, where the float card sits (`app/admin/jnfs/[id]/page.tsx:953-956`). There is no single page that shows details + eligibility + stages + questions + documents together, and no breadcrumb.
- Naming: page/back label "All Postings", list title "Job Postings" vs "Job Profile". See Rename list.
- Conflict: none.

**B11. Draft → Submitted lifecycle with a publish checklist** — DRAFT status; yellow panel "Please complete the following steps to publish the job profile" (Assign a category; Select applicable courses; Set eligibility criteria; Setup hiring workflow), "Looks like the job profile is ready to be submitted", "Submit Now", confirm "Are you sure you want to submit job profile - <title>?" Yes/No; status becomes SUBMITTED; Activity "Submitted a few seconds ago".
- Ours: PARTIAL, different model. Our equivalent of "submitted" is a JNF/INF in status `accepted` (statuses `submitted, under_review, accepted, rejected, draft`, `AdminFormReviewController.php:36`), and floating creates the posting directly in `open`. The posting table has no draft/submitted state (`job_postings.status` enum `open, in_process, completed, cancelled`, migration `2026_09_27_000008…:19`). There is no readiness checklist; the float dialog only shows the eligible count (`floatdialog.jsx:220-225`) and requires a confirm tick (239-242).
- What could be added: a readiness checklist on the float card (offer category chosen, at least one branch selected, at least one round, deadline set).
- Conflict: none. Priority Low.

**B12. Date of Visit / Process** — hero "Date of visit: «Not Updated» (i)" with pencil; Summary row "Date of Visit / Process"; a cycle-list column "Date of Visit".
- Ours: NOT IMPLEMENTED. Rounds have `scheduled_at` (`posting_rounds` migration line 20) and the JNF has "Tentative Joining Month", but no campus visit date on the posting.
- Priority Medium (feeds calendar and the cycle list). Conflict: none.

**B13. Mute communication to students** — chip with gear in the hero.
- Ours: NOT IMPLEMENTED per posting. Mail has only a global queued/immediate mode (Settings). Float, round and result mails always go out.
- Priority Low. Conflict: none, but it would let the CDC suppress E2/E4/E5 for one drive; confirm with owner since the mail rules were specified.

**B14. Collaborators (per job profile and per cycle)** — avatars + "+".
- Ours: NOT IMPLEMENTED. Every admin can act on every posting ("Admin is god").
- Priority Low. Conflict: restricting admins by collaborator lists would weaken "Admin is god"; if built it should be informational (owner/watchers), not a permission. NEEDS OWNER DECISION only if it restricts access.

**B15. Summary rows** — Company (editable), Created "Created for <cycle>", Date of Visit / Process, Status (DRAFT / SUBMITTED), Job Profile Group ("Assign group to this Job Profile"), Share Job Profile ("Copy link"), Flexible Mode.
- Ours: PARTIAL. Overview "Details" card shows Form (link to JNF/INF #id), Cycle, Type, Offer category, Compensation, Floated (time + admin), Status (`overviewtab.jsx:109-142`). Missing: Job Profile Group, Copy link (the student URL `/student/postings/{id}` exists and is built in `SendPostingFloatedMails`, but there is no copy button), editable company.
- Priority Low (Copy link is a quick win). Conflict: none.

**B16. Job Profile Details** — Title, Job Location, Position Type, External ATS (DISABLED), Expected Number Of Hires (NOT MENTIONED), Job Functions, CTC, Salary Break-up, Skills Required ("+ Add Skills"), with an "Edit" link.
- Ours: PARTIAL. All except External ATS and Job Functions exist in the company JNF: "Job Title / Profile Name", "Place of Posting", "Work Mode", "Expected Hires", "Minimum Hires", "Required Skills", "Company Registration Link (if any)" (`jnfformpro.tsx:828-949`); JNF vs INF gives the position type. They are shown on the JNF page and student preview, not on the posting page. "Company Registration Link" is the nearest thing to External ATS but is not an on/off redirect of applications.
- Conflict: none.

**B17. TalentLens Profile Match Configuration** — AI match score for applicants; "Add Configuration".
- Ours: NOT IMPLEMENTED.
- Conflict: AI service/package needs owner approval. NEEDS OWNER DECISION. Priority Low.

**B18. Additional Information (with a standing CDC disclaimer)** — rich text, "Edit".
- Ours: PARTIAL. JNF "Additional Information" exists (`jnfformpro.tsx:939`), written by the company. No CDC-wide disclaimer is shown on student job pages (no such text in `frontend/components` or `frontend/app`).
- What to build: an admin-editable standard notice shown on every student job profile page (or reuse policy documents).
- Priority Low. Conflict: none.

**B19. Job Profile Tags — Sector*** — required multi-select, chip "Software/IT".
- Ours: PARTIAL. Sector is a company-profile field, not a per-job tag.
- Priority Low. Conflict: none.

**B20. Additional Questions** — section "Additional Questions (i)", helper text, question cards with type tag (seen: "DROPDOWN : SINGLE"), edit and delete icons, "+ Add Additional Question"; inline form "Question Title*" (helper "For eg. What is your Father's Name"), "Answer Type*" ("Select answer type"), "This question is mandatory", "Help Text", "Add Custom Question" / "Cancel"; validation "Required field". Used by the CDC for a participation acknowledgement.
- Ours: PARTIAL. `components/admin/questionbuilder.jsx`: per question "Question N" text, "Type" = "Text answer" / "Choose one" / "Choose many" (line 24), options (at least two distinct), "Required", move up/down, remove, "Add question"; float dialog section "Application questions" (`floatdialog.jsx:230-238`); posting tab "Questions" with "Save Questions", frozen after the deadline (`components/admin/posting/questionstab.jsx:13,40,50`). Backend: `posting_questions` (`qtype` enum text/mcq_single/mcq_multi, `required`, `sort_order`; migration `2026_09_27_000009…:17-21`), validation `AdminPostingController.php:668-688` (max 20 questions, 20 options, 1000 chars). Missing: **Help Text** per question (no column), other answer types Superset may offer (not visible), per-question save (we save the whole list).
- Naming: "Application questions" / "Questions" / "Add question" / "Required" / "Type" vs "Additional Questions" / "Add Additional Question" / "This question is mandatory" / "Answer Type" / "Question Title". Our type labels vs Superset "DROPDOWN : SINGLE" (Superset's tag for our "Choose one").
- Conflict: the question itself is fine. The penalty it announces (blocked for 5 opportunities after skipping a process, permanent block on violation) is a penalty system; the owner skipped "The credit-score or penalty system". Building automatic penalties tied to such an acknowledgement: NEEDS OWNER DECISION. (The admin can already block manually via Blocks.)

**B21. Applicable Courses + "Set Course Eligibility" modal** — per-institute tab; programme groups with codes; courses "<Course> - <Department> (<Specialisation>), <School>"; Show More / Show Less; "Edit Courses" modal with grid/list toggle, programme list, "Select all courses in <programme>", "Search by course name..", course checkboxes, Cancel / Save. Second checklist item ("Select applicable courses").
- Ours: IMPLEMENTED (different shape). The eligibility matrix (programme × branch) in the JNF/INF wizard and in the "Edit eligibility" dialog (`components/admin/editeligibilitydialog.jsx`, title "Edit eligibility" line 194; grid `components/forms/shared/eligibilitygrid.tsx` with a per-programme select-all checkbox, lines 332, 519-522), enforced by `EligibilityService` (`backend/app/Services/EligibilityService.php`), changeable after floating (D103). Missing: search by course name inside the grid, specialisation as a separate level (our branches are flat strings), multi-institute tabs (not needed).
- Naming: "eligibility matrix" / "Edit eligibility" vs "Applicable Courses" / "Edit Courses" / "Set Course Eligibility". Rename the admin section heading to "Applicable Courses" if adopting Superset wording.
- Conflict: none.

**B22. Allowed Batches** — "2027 Passout Batch", "Edit Batches".
- Ours: IMPLEMENTED. `graduatingBatch` in the snapshot (`JobPosting::SNAPSHOT_KEYS`, `JobPosting.php:19-21`), edited as "Graduating batch" (`editeligibilitydialog.jsx:226`), checked in `EligibilityService` (`batchesFor`, line 346; PhD exempt, D68).
- Naming: "Graduating batch" (value "2027") vs "Allowed Batches" (value "2027 Passout Batch").
- Conflict: none.

**B23. Eligibility Criteria (all sub-features)**
- **B23a. Conflict resolution mode ANY / ALL** (multiple degrees at the same level). Ours: NOT IMPLEMENTED; we store one current programme per student and no earlier degrees (`student_profiles` columns, migration `2026_09_21_162407…:15-38`). Priority Low. Conflict: none.
- **B23b. Per-programme score grid** — Allow All, Set Overall%, PG, Dual Degree, UG, Diploma, Class 12, Class 10, with N/A where a level does not apply and a unit dropdown on the programme's own level. Ours: PARTIAL. We have per-branch CGPA cut-off ("Global Min CGPA" + per-branch, `eligibilitygrid.tsx:438`) and drive-wide "Minimum 10th %" / "Minimum 12th %" (`editeligibilitydialog.jsx:236,246`; `EligibilityService.php:119`). Missing: per-programme 10th/12th, UG/PG/Diploma/Dual-degree marks (no columns), % vs CGPA choice. Priority Medium (M.Tech drives often require UG marks). Conflict: none, but new academic fields must come from the CDC (students cannot edit academics), so it needs import columns.
- **B23c. Allowed previous education** ("All M.Tech students must have B.Tech in Computer Science"). Ours: NOT IMPLEMENTED (no previous-degree data). Priority Medium. Conflict: none.
- **B23d. Work Experience Criteria** — NOT IMPLEMENTED. Low.
- **B23e. Attendance Criteria** — NOT IMPLEMENTED (no attendance data). Low.
- **B23f. Gender Criteria** — IMPLEMENTED: All / Male only / Female only (`editeligibilitydialog.jsx:218-220`; `EligibilityService.php:107, 365`).
- **B23g. Backlog Criteria** (chip "No ongoing backlogs allowed") — IMPLEMENTED: backlogs allowed toggle, Max ongoing, Max total per branch (`eligibilitygrid.tsx:457-472`; `EligibilityService.php:94-98`). Naming: Superset summarises as a chip; ours shows it in the grid.
- **B23h. Age Criteria** — NOT IMPLEMENTED; `date_of_birth` is stored (migration line 29) but unused. Low.
- **B23i. Education Gap Criteria** — NOT IMPLEMENTED (no data). Low.
- **B23j. Resume Prerequisite** (chip "Mandatory") — IMPLEMENTED as always-mandatory: apply requires `resume_id` (`StudentApplicationController.php:57, 79-81`). Not configurable per drive. Note: owner allows applying with an unverified resume (flagged), so "Mandatory" here means "a resume is attached", not "verified".
- **B23k. Exam Score Criteria** — NOT IMPLEMENTED. Low.
- **B23l. Allowed Student Categories** — NOT IMPLEMENTED; `category` and `pwd` are stored (migration lines 32-33) but not used in eligibility. Low; sensitive (reserved-category drives, e.g. PSUs) → ask the owner before exposing category in eligibility.

**B24. Job profile status values** — Draft, Submitted (on the profile); Accepting Applications, Closed For Applications, In Process, Completed (cycle list); plus a PPO chip.
- Ours: PARTIAL. Posting statuses `open`, `in_process`, `completed`, `cancelled` (migration `…000008…:19`), shown with `titleCase` as "Open", "In Process", "Completed", "Cancelled" (`lib/format.js:71-74`); the student card shows "Applications closed" / "In process" / "Completed" (`components/student/postingcard.jsx:65`). We have no separate "Closed For Applications" (deadline passed but selection not started); an `open` posting past its deadline still reads "Open" to the admin (the list only dims the date via `deadline_passed`, `app/admin/postings/page.jsx:148`).
- What to build: display "Accepting Applications" for `open` before the deadline and "Closed For Applications" for `open` after the deadline (display-only, no new status needed); keep "In Process", "Completed", "Cancelled".
- Naming: see Rename list ("Open" → "Accepting Applications"; student "In process" → "In Process").
- Conflict: none (display only; do not rename the stored enum, per "Never rename existing routes, columns or response keys").

**B25. Stages (hiring workflow)** — stage cards with "Venue and schedule not added yet" / "Add venue & schedule", gear (settings) and delete; "+ Add New Stage"; final "Offer" node; library: Resume shortlisting, Written test, Group discussion, Technical interview, HR interview, Online test, Take Home Assignment, plus "Any Other Round" (and one item cut off). Checklist item "Setup hiring workflow".
- Ours: PARTIAL. Rounds are copied from the JNF's selection rounds at float (`AdminPostingController::copyRounds`, lines 595-632) and managed in the "Rounds" tab (`components/admin/posting/roundstab.jsx`): "Add round" (line 162-163), "Round name", "Type", "Scheduled at, IST (optional)", "Status", "This is the final round (offers are announced after it)", reorder, delete (with D75/D84 guards). Types `ROUND_TYPES` (roundstab.jsx:39-51; backend `JobPosting::ROUND_LABELS`, JobPosting.php:24-36): Pre-Placement Talk, Resume Shortlisting, Written Test, Aptitude Test, Technical Test, Group Discussion, HR Interview, Technical Interview, Psychometric Test, Medical Test, Other. Missing: **venue** (no column on `posting_rounds`, migration `…000010…:17-22`), types "Online test" and "Take Home Assignment", a visual flow ending in "Offer".
- Naming: "Rounds" / "Add round" vs "Stages" / "Add New Stage"; "Other" vs "Any Other Round"; case differences ("Resume Shortlisting" vs "Resume shortlisting", etc.). The owner's glossary defines "Round"; rename only if the owner wants Superset wording.
- Conflict: none (venue is additive; owner said the admin can add, remove, reorder and schedule rounds).

**B26. Attached Documents** — "+ Add Document", table Name / Uploaded / Remove (e.g. the company's JNF PDF).
- Ours: NOT IMPLEMENTED. No file attachment on JNF/INF or postings (no `jd`/`attachment` fields in `jnfformpro.tsx` or the JNF model). Students cannot download a JD PDF.
- Priority Medium. Conflict: none (local disk storage per owner).

**B27. Withdrawal Options** — note "Students are prohibited from withdrawing their applications for this placement cycle…" (a cycle-level default) and a per-profile override "Allow student application withdrawal after the job profile is in process?".
- Ours: different rule, by owner decision. Students can withdraw and re-apply until the deadline; everything freezes at the deadline (`StudentApplicationController@withdraw`, lines 188-204, gated by `acceptsApplications()` at 193). "There is no per-cycle 'no withdrawal' toggle" and "a per-cycle switch that forbids withdrawals" is excluded unless the owner asks.
- Status: NOT IMPLEMENTED (intentionally). NEEDS OWNER DECISION for both the cycle-level prohibition and the "withdraw after in process" override.

**B28. Advanced Options** — collapsed ("Show Advanced Options"); contents not shown. Status unknown (see Uncertain).

**B29. Communication Log** — separate nav item on the profile.
- Ours: PARTIAL. Emails are logged globally (`email_logs`: recipient, subject, template, status, sent_at; migration `2026_03_30_000025…:16-22`), and `posting.notify` audit rows record who was told about a drive (`SendPostingFloatedMails`). There is no per-posting communication view and `email_logs` has no posting reference.
- Priority Low. Conflict: none.

**B30. Header icons** — speaker/megaphone, PDF, kebab.
- Ours: PARTIAL / unclear. The PDF icon most likely downloads the job profile as PDF; we offer the JNF/INF one-row CSV (`GET /admin/jnfs/{jnf}/csv`, routes/api.php:172) and the applicant Excel export, not a PDF. Speaker icon purpose unclear (see Uncertain).

**B31. Application Progress panel** — "No Applicants" split button with "•••" menu; bar "Application Progress"; "0 applied out of 1350 eligible".
- Ours: IMPLEMENTED (admin only). Overview stat cards "Eligible students", "Applied", "Withdrawn", "Unverified resume", "Placed elsewhere" (`overviewtab.jsx:88-102`, data from `AdminPostingController::detail` stats, lines 786-792) and the "Applicants" / "Eligible" tabs (Applied / Not applied, D97). Students never see counts (owner rule) — Superset's panel is admin-side, so no conflict.
- Naming: "Applied" / "Eligible students" vs "Application Progress … applied out of … eligible"; "Applicants" tab vs "No Applicants" button. Could add a single progress line "X applied out of Y eligible" to the posting header.

**B32. Send Applicant List to company** — button on the submitted (and presumably later) panel.
- Ours: PARTIAL, different model. The company sees its own live applicants at any time and can export them (`CompanyPipelineController@applicants`, lines 63-106; `@export`, 108; owner: "A company can export its own drives at any time"). There is no admin action that pushes the list to the company by email.
- Priority Low. Conflict: none (an extra push is additive).

**B33. Open for Applications dialog** — trigger "Open Profile for Applications"; modal "Open Applications": "Set Application Deadline" (hh : mm [: ss] AM/PM spinners + date with calendar), checkbox "Share job applications progress with company." with "Application progress will be shared with the company.", note "A notice will be created and all eligible students will be notified via email and SMS", buttons "Schedule For Later", "Cancel", "Open for Applications".
- Ours: IMPLEMENTED (core), PARTIAL (extras). Float card on the accepted JNF/INF page: "Not visible to students yet" + "Float to Students" (`floatdialog.jsx:153-171`); dialog "Float to students" (176) with Placement cycle (186-196, only open cycles of the matching type), "Application deadline (IST)" datetime, default 23:59 IST one week ahead (37, 197-204), "Offer category" + consequence text (206-218), blocking rules panel (219), eligible-count preview "<n> of <m> enrolled students are eligible and will be emailed" (220-225), "Share applicants' phone and personal email with the company in exports" (226-229), "Application questions" (230-238), required tick "I understand eligible students are notified immediately." (239-242), "Float Posting" (249-251). Backend: future-deadline check (`AdminPostingController.php:691-698`), accepted-only, not already floated, open cycle of matching type (141-162), creates rounds and questions, audit `posting.float` (191-199), dispatches E2 (201).
- Missing:
  - **Schedule For Later** (open applications automatically at a chosen time): NOT IMPLEMENTED. Priority Medium. Conflict: none.
  - **Share job applications progress with company** as a toggle: ours always shares live applicants with the company (B32). Making it optional would change the owner's "export at any time" rule → NEEDS OWNER DECISION.
  - **"A notice will be created"**: ours creates an in-app notification per student ("New opening: <company>", `SendPostingFloatedMails`) — equivalent; no public notice board (Superset "Notices").
  - **SMS**: NOT IMPLEMENTED. Owner: "Emails go to institute addresses only". SMS is a new paid channel → NEEDS OWNER DECISION.
  - **Mobile notifications**: no mobile app; out of scope.
- Naming: "Float to Students" / "Float to students" / "Float Posting" / "Floating..." / "Floated to students" / "Not visible to students yet" / "Open Posting" vs "Open Profile for Applications" / "Open Applications" / "Open for Applications". Recommended rename (UI text only; keep the route names and the `posting.float` audit action).
- Conflict: see bullets above.

**B34. Sending progress modal** — "Sending Emails ...", "Publishing new Notice ...", "Sending Mobile Notifications ...", "Close".
- Ours: PARTIAL. The API answers "Posting floated. Eligible students are being notified." (`AdminPostingController.php:204`) and the mail job runs queued or immediately; the dialog just closes. Delivery status per mail is in `email_logs`, not shown here.
- Priority Low. Conflict: none.

**B35. Open-state panel** — "Eligible students can now apply for job profile - <title>. The application deadline is set to 05:00 PM on 07 Oct 2026", buttons "Change Application Deadline" and "Send Reminder Email".
- Change Application Deadline: IMPLEMENTED — Overview "Settings" card "Application deadline (IST)" + "Save" (`overviewtab.jsx:154-161, 180-182`; `AdminPostingController@update`, 219-261, audited `posting.update`), plus reopen via "Edit eligibility" (D104). Naming: no dedicated "Change Application Deadline" button.
- Send Reminder Email: NOT IMPLEMENTED. Owner: "no deadline reminders and no digest"; "deadline reminder mails" are excluded. A manual one-click reminder is still a reminder mail → NEEDS OWNER DECISION.

**B36. Activity feed on the profile** — "Open For Applications a few seconds ago", "Submitted a few seconds ago".
- Ours: PARTIAL. Every action is audit-logged (`posting.float`, `posting.update`, `posting.close`, `posting.reopen`, `posting.cancel`, `posting.eligibility_update`, `posting.notify`, round actions), but the posting page has no activity list and the Audit Log page filters only by admin, action prefix and date (`AdminAuditLogController.php:19-40`), not by posting.
- What to build: an "Activity" panel on the posting page reading audit rows for that posting.
- Priority Medium. Conflict: none.

**B37. Recent Job Profiles / Recently Visited Placements (sidebar)**.
- Ours: NOT IMPLEMENTED. Priority Low. Conflict: none.

**B38. Check Eligibility (cycle job list)** — button with a funnel icon; not clicked.
- Ours: NOT IMPLEMENTED as a cycle-level tool. Per drive we have the "Eligible" tab and student-side reasons. Purpose uncertain (see Uncertain). Priority Low.

---

## 3. Gap summary

| Superset name | What to build | Priority | Conflicts |
|---|---|---|---|
| Add New Job / New Job Profile (B1) | Admin creates a job profile (and, if needed, the company) directly inside a cycle, without a company JNF/INF | High | Changes how drives originate; confirm with owner |
| Schedule For Later (B33) | Open applications automatically at a chosen time (store a scheduled open time; job sends E2 then) | Medium | None |
| Date of Visit / Process (B12) | Visit date on the posting, shown in lists and calendar | Medium | None |
| Attached Documents (B26) | Upload/remove PDFs (JD, company deck) on a posting; students download | Medium | None |
| Activity (B36) | Per-posting activity panel from audit rows | Medium | None |
| Stages: venue + more types (B25) | `venue` on rounds ("Add venue & schedule"); add Online test, Take Home Assignment | Medium | None |
| Eligibility score grid (B23b) | Per-programme 10th/12th; UG/PG/Diploma marks; % vs CGPA | Medium | New academic data must be CDC-imported |
| Allowed previous education (B23c) | M.Tech/PhD prior-degree rule | Medium | Needs previous-degree data |
| Job Profile Source (B3) | Record PPO / external offers alongside campus drives | Medium | NEEDS OWNER DECISION (blocking effect) |
| Status wording (B24) | Show "Accepting Applications" / "Closed For Applications" by deadline | Medium | None (display only) |
| Additional Questions help text (B20) | `help_text` per question, shown to students | Low | None |
| Acknowledgement penalties (B20) | Automatic block after skipping a process | Low | NEEDS OWNER DECISION (penalty system skipped) |
| Category tiers (B5) | Levels with max offers / max attempts | Low | NEEDS OWNER DECISION (no dream-offer rule) |
| Withdrawal Options (B27) | Cycle-level no-withdrawal + per-profile override | Low | NEEDS OWNER DECISION (explicitly excluded) |
| Send Reminder Email (B35) | Manual reminder to eligible non-applicants | Low | NEEDS OWNER DECISION (no deadline reminders) |
| Share job applications progress with company (B33) | Make company visibility of applicants a per-drive toggle | Low | NEEDS OWNER DECISION (company may export any time) |
| SMS on opening (B33) | SMS channel | Low | NEEDS OWNER DECISION (institute email only) |
| Send Applicant List to company (B32) | Admin action that emails the applicant export to the company | Low | None |
| Readiness checklist (B11) | "Steps to publish" on the float card | Low | None |
| Copy link (B15) | Copy the student URL of a posting | Low | None |
| Communication Log (B29) | Per-posting mail log (needs posting id on email logs) | Low | None |
| Sending progress (B34) | Progress/summary after floating (counts mailed) | Low | None |
| Mute communication to students (B13) | Per-posting mail suppression | Low | Confirm with owner (mail rules) |
| Collaborators (B14) | Watchers per posting/cycle | Low | NEEDS OWNER DECISION if it restricts admins |
| Job Function (B4), Sector tag (B19) | Per-job taxonomy | Low | None |
| Salary flags (B6) | "No stipend", "announced later", interval, equity | Low | None |
| Import job details of another job (B8) | Admin copy from another posting | Low | None |
| Additional Information disclaimer (B18) | Standard CDC notice on every student job page | Low | None |
| Age / Category / Work experience / Attendance / Education gap / Exam score criteria (B23) | Extra eligibility filters | Low | Category: ask owner (sensitive) |
| Conflict resolution ANY/ALL (B23a) | Multi-degree handling | Low | None |
| Flexible mode (B9) | Partial shortlisting mode | Low | Meaning unclear |
| Auto-fill with AI (B2), TalentLens (B17) | AI extraction / match score | Low | NEEDS OWNER DECISION (new service/package) |
| Check Eligibility (B38), Recent lists (B37) | Cycle eligibility checker, recent items | Low | None |

---

## 4. Rename list

Only UI text. Never rename routes, columns, enum values, response keys or audit action names (standing rule).

| Where it appears in our UI/code | Our current name | Superset name |
|---|---|---|
| Admin top bar / drawer (`adminshell.tsx:49`) | Postings | Job Profiles |
| Admin postings list title (`app/admin/postings/page.jsx:66`) | Job Postings | Job Profiles |
| Admin postings list subtitle/empty text (`postings/page.jsx:67,109`) | "posting(s) floated to students" / "Float to Students" | job profiles opened for applications / Open for Applications |
| Posting detail back link (`app/admin/postings/[id]/page.jsx:73`) | All Postings | Job Profiles (or the cycle name, as Superset's breadcrumb) |
| Company nav (`components/company/companyshell.tsx:44`) | Drives | Job Profiles |
| Student nav (`studentshell.jsx:44`) | Job Profiles | Job Profiles (already matches) |
| Float card button (`floatdialog.jsx:169`) | Float to Students | Open Profile for Applications |
| Float dialog title (`floatdialog.jsx:176`) | Float to students | Open Applications |
| Float dialog submit (`floatdialog.jsx:250`) | Float Posting / Floating... | Open for Applications |
| Float card state (`floatdialog.jsx:140, 155`) | Floated to students / Not visible to students yet | Open For Applications / Submitted (not open yet) |
| Float card link (`floatdialog.jsx:149`) | Open Posting | (view job profile) |
| Float dialog confirm tick (`floatdialog.jsx:241`) | I understand eligible students are notified immediately. | A notice will be created and all eligible students will be notified (wording) |
| Float dialog / Overview deadline field (`floatdialog.jsx:200`, `overviewtab.jsx:156`) | Application deadline (IST) | Set Application Deadline / Change Application Deadline |
| Overview lifecycle button (`overviewtab.jsx:226`) | Cancel posting | (no Superset equivalent seen) — "Cancel job profile" if renaming the noun |
| Admin posting status chip (`lib/format.js` titleCase of `open`) | Open | Accepting Applications (Closed For Applications once the deadline passes) |
| Student posting card (`postingcard.jsx:65`) | In process | In Process |
| Student posting card (`postingcard.jsx:65`) | Applications closed | Closed For Applications |
| Float dialog section (`floatdialog.jsx:232`) | Application questions | Additional Questions |
| Posting tab (`postings/[id]/page.jsx:33`) | Questions | Additional Questions |
| Question builder add button (`questionbuilder.jsx:154`) | Add question | Add Additional Question |
| Question builder field (`questionbuilder.jsx:76`) | Question N | Question Title |
| Question builder field (`questionbuilder.jsx:82-83`) | Type | Answer Type |
| Question builder checkbox (`questionbuilder.jsx:123`) | Required | This question is mandatory |
| Question type label (`questionbuilder.jsx:24`) | Choose one | Dropdown : Single (exact list of Superset types not visible) |
| Posting tab (`postings/[id]/page.jsx:32`) | Rounds | Stages |
| Rounds button/dialog (`roundstab.jsx:163, 168`) | Add round / Edit round | Add New Stage |
| Round type (`roundstab.jsx:50`, `JobPosting.php:35`) | Other | Any Other Round |
| Round types (`roundstab.jsx:41-47`) | Resume Shortlisting, Written Test, Group Discussion, Technical Interview, HR Interview | Resume shortlisting, Written test, Group discussion, Technical interview, HR interview (case only; do not change) |
| Edit eligibility dialog (`editeligibilitydialog.jsx:194`, header button `postings/[id]/page.jsx:79`) | Edit eligibility | Eligibility Criteria → Edit (courses part: Applicable Courses / Edit Courses / Set Course Eligibility) |
| Eligibility batch field (`editeligibilitydialog.jsx:226`) | Graduating batch | Allowed Batches ("2027 Passout Batch") |
| Float dialog / Overview (`floatdialog.jsx:207`, `overviewtab.jsx:163`) | Offer category | Category |
| Overview details (`overviewtab.jsx:124`) | Type: Full Time / Internship | Position Type |
| Overview details (`overviewtab.jsx:130`) | Compensation | Cost-to-Company (CTC) / Salary/Stipend |
| Overview details (`overviewtab.jsx:120`) | Cycle | Created for <placement> |
| Overview stats (`overviewtab.jsx:89, 92`) | Eligible students / Applied | Application Progress: X applied out of Y eligible |
| Cycle postings tab columns (`cyclepostings.jsx:60-64`) | Company · Role / Form / Status / Applied / Deadline | Company / Profile / Date of Visit / Deadline / Status |
| Cycle page tab (`placement-cycles/[id]/page.jsx:323`) | Postings | Job Profiles |
| Cycle page stat (`placement-cycles/[id]/page.jsx:230`) | Postings floated | (job profiles) |
| Admin nav / cycles page (`adminshell.tsx:48`, `placement-cycles/page.jsx:217`) | Cycles / Placement Cycles | Placements (button "Add placement process", link "Edit Placement") |
| Company JNF wizard (`jnfformpro.tsx:828, 852, 877, 932`) | Job Title / Profile Name; Place of Posting; Expected Hires; Required Skills | Job Profile Title; Job Location; Expected Number Of Hires; Skills Required |
| Audit log page (`adminshell.tsx:64`) | Audit Log | Activity / Communication Log (per job profile) |
| Glossary / code comments | drive, posting, float | job profile, open for applications |

Recommendation: the high-value renames are the noun ("Postings"/"Drives" → "Job Profiles", matching what students already see), the float verbs ("Float to Students" → "Open for Applications"), "Application questions" → "Additional Questions", and the "Open" status → "Accepting Applications" / "Closed For Applications". "Rounds" → "Stages" and "Cycles" → "Placements" conflict with the owner's own glossary and the professor report wording, so ask before changing.

---

## 5. Uncertain

- **Answer Type options** for Additional Questions: the dropdown was never opened. Only one existing type tag is visible ("DROPDOWN : SINGLE"); the full list (text, number, date, file, multi-select…) cannot be read from the frames.
- **Interval dropdown** on Salary/Stipend shows only "AN..." (likely "ANNUALLY"); other values not visible. "Specify Range" behaviour not shown.
- **Job Profile Source** and **Category** dropdowns on the create form were not opened; values beyond the helper text (Campus Placement, PPO, External Offer) and "Level 1 - General 1" are unknown.
- **Flexible mode for Job Profile Shortlisting**: only the helper text is visible; what "partial shortlisting" does is not shown.
- **Advanced Options** was never expanded.
- **The 9 criteria accordions** (Work Experience, Attendance, Gender, Backlog, Age, Education Gap, Resume Prerequisite, Exam Score, Allowed Student Categories) were never expanded; their fields are inferred from the titles and two chips only.
- **Unit dropdown** after "%" on the programme's own level in "Set Eligibility Criteria" (probably % vs CGPA) — not opened. The first column of the grid has no visible header. The Save button label is cut off ("Save Ch…").
- **Stage library**: the first item above "Resume shortlisting" is cut off; the gear on each stage card was not opened; what "Add venue & schedule" asks for is not shown.
- **Header icons** on the job profile (speaker/megaphone, PDF, kebab) and the "•••" next to "No Applicants" were not clicked.
- **Deadline mismatch**: the dialog at 3:00 shows 06:46 PM, 06 Oct 2026 with the calendar being opened; the result at 3:10 says 05:00 PM on 07 Oct 2026. The change happened between frames, so how the date/time picker works (and whether 05:00 PM is a default) is not visible.
- The second "Submit Now" at 2:40 succeeded, but its confirmation dialog was not captured; whether the first attempt's error came from the Sector/Job Function attribute (the toast mentions "Attribute Definition … array of strings") is a guess.
- **"Schedule For Later"** was not clicked; whether it schedules the opening, the notification, or both is not shown.
- **"Share job applications progress with company"**: what the company actually sees (counts only, or names) is not shown.
- **Check Eligibility** on the cycle job list and the small people/filter icon next to Search were not clicked.
- The group label of the first course block in Applicable Courses (above "Fuel, Minerals & Metallurgical Engineering…") scrolled off-screen (probably M.Tech or B.Tech).
- The toasts "Placement does not exist" (0:44) and the config error (2:32) look like Superset bugs and are not treated as features.
