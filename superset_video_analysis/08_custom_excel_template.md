# 08 — "How to make a custom excel template" (Superset admin training, 251 s)

Source: 70 keyframes in `scratchpad/kf/08_custom_excel_template/` (t000m00s … t003m58s), all read in order.
Recorded by CDC IIT (ISM) Dhanbad on app.joinsuperset.com (admin account "Career Development Centre IIT ISM Dhanbad").
Our code checked: `CDC/backend` (routes/api.php, ExportService, controllers, migrations, models) and `CDC/frontend` (app/admin, app/company, components/admin, lib).

---

## 1. Video walkthrough

**0:00 – Placements list** (`#/admin/placements`)
- Superset left sidebar groups are visible: RECENT JOB PROFILES, RECENTLY VISITED PLACEMENTS, Home, My Dashboards, JOBS (Companies, Inbound Job Posts, Placements, Job Alerts), RELATIONSHIPS (Students, CRM).
- The centre column lists the placement cycles: "Full-Time Placement for 2026-27 (2027 Pass Out Batch)", "Internship Placement || 2026-2027 Session || 2028 Pass out batch", and under "Previous Placements" the older cycles. The right column is "Recently Visited".

**0:10–0:12 – Cycle page** (`#/admin/placements/<cycle-uuid>?search=(length:50,order:…)`)
- Header: "Full-Time Placement for 2026-27 (2027 Pass Out Batch)", with a ⋮ menu and an Excel icon at the top right.
- Yellow banner: "10 students are waiting on a No Objection Certificate." with a "Review Requests" link.
- Left panel: "Jun 2026 - Jun 2027", "Edit Placement", "1901 Enrolled Students out of 2044 eligible students", and "Important Links":
  - Dashboard, Placement Matrix Report, Placement Absentees Report, Student Placements Report, Student Opportunities Report, Download Job Profiles List, Student Offers Report, Application Preferences Report;
  - List : Job Offers, List : Students Placed, List : Students Not Placed.
- Also on the left: Collaborators and "Enrollment Deadline 11 Aug 2026, 18:00".
- Job table columns: Company | Profile | Date of Visit | Deadline | Status. Filters: Status (All), Search "Start typing …", "+ Add New Job", "Check Eligibility".
- The presenter clicks the Siemens EDA row.

**0:14–0:18 – Job profile page** (`#/admin/placements/<cycle>/companies/<company-uuid>/job…`)
- Title "Student Intern (Software Profile)/(Digital Profile)", subtitle "Siemens EDA India Private Limited · Bangalore/Noida/Hyderabad/Kolkata", breadcrumb "Placements / Full-Time Placement for 2026-27 (2027 Pass Out Batch) / Student Intern …".
- Chips: "Internship", "Intern + Performance Based PPO", "Mute communication to students".
- Top-right actions: "Trigger placement story", a speaker icon, a PDF icon and ⋮.
- Left section nav: Collaborators, Summary, Details, Additional Info, TalentLens, Job Profile Tags, Additional Questions, Applicable Courses, Eligibility, Stages, Attached Documents, Category, Withdrawal Options, Advanced Options, Communication Log.
- Right panel: a split button "136 Applicants" with a "•••" part, "Application Progress 136 applied out of 352 eligible", and "The process is complete! The final list of selected students is here".
- **0:18** The presenter opens "•••". The dropdown reads:
  - **Applicants**: View Applicant List; + Add Applicants Manually
  - **Download Applicants**: Excel - Default Template; Excel - Custom Template
  - **Download Eligible List**: Excel - Default Template; Excel - Custom Template
- The narration point: the "Custom Template" options need a template to exist first.

**0:44–0:52 – Navigating to the templates**
- The presenter scrolls the sidebar past ENGAGEMENT (Notices, Surveys, Calendar) and ADMIN (Documents, TalentLens Rubrics, Admin) to **REPORTS**: Reports, Custom Reports, **Excel Templates**, Email Logs, Launchpad, New Placements App.
- They click **Excel Templates** (`#/a/templates`).

**0:52–0:58 – Excel Templates list** (`#/a/templates`)
- Page title "Excel Templates (i)". The card header is "Available Templates" with "+ Add New" on the right.
- Each row shows an Excel file icon, the template name, "✎ Edit" and a copy (duplicate) icon.
- Existing templates: Standard, standard, Data With all Sem CGPA, IIT ISM DHANBAD, IIT(ISM)Dhanbad, bhuvi, bhuvneshwari, DOC, Amdocs, PO, LTI, Doc Cell, Domain, Department, Extra Question.
- The presenter scrolls through the list, then clicks "+ Add New".

**1:00–1:02 – "Add Excel Template" modal**
- Fields:
  - **Template Name**, a text input with placeholder "Ex. Wipro Format, Standard Format";
  - **Template Type**, a read-only grey field showing `STUDENT_LIST`.
- Buttons: Cancel and "+ Create".
- The presenter types "Test" and clicks Create.

**1:04–1:06 – Template editor** (`#/a/templates/40d385b2-bca0-4e03-9f37-f493a9328537`)
- Breadcrumb "Custom Excel Templates / Test". Card title "Excel Templates (i)". Save-state indicator at the top right: "✓ All changes saved".
- Template title "Test" with the hint "Click on title to edit" (inline rename).
- Table header: **Column Key** | **Display Name in Report**.
- A new template starts with one row: ≡ (drag handle) | "Name" | text input "Name" | red trash icon.
- Add row: "+" | a searchable select, "Choose a field to add".
- Below the table: the heading **"Placement Cycle Specific Information"**, then the label **"Add Placement Cycle Section"** with a select ("Select an Option") and a **"+ Add Placement"** button (pale blue, looks disabled until a cycle is chosen).

**1:20–1:24 – Adding fields**
- Opening the field select shows a search box and a scrolling list. It starts with "Year of passing 12th / Class 12th Year", "Year of passing 10th / Class 10th Year", "Xth Score Type / Class 10th Score Type", and so on (full list in section 2, F3).
- The presenter types "ro". The results are "Roll No / Registration Number" and "Q: Internship Job Role / Designation". They pick Roll No.
- A row "Roll No" is added with the display name "Roll No". The indicator switches to "ⓘ There are unsaved changes!", then "◌ Saving", then "✓ All changes saved" (autosave, no Save button needed).

**1:26–1:32 – Renaming a column**
- The presenter searches "xth". Results: Xth Score Type / Class 10th Score Type; Xth Score Total / Class 10th Score Total; Xth Score / Class 10th Score; Xth percentage / Class 10th Percentage; Xth Board / Class 10th Board.
- They pick "Xth percentage". The row's Column Key reads "Xth percentage", and the presenter changes the Display Name to **"Class 10 %"**.

**1:32–1:42 – A CGPA field**
- The presenter types "cu". Results begin "Extra-Curricular 5 - Title", "Extra-Curricular 5 - Description", "Extra-Curricular 5 - Start Date", "Extra-Curricular 5 - End Date", "Extra-Curricular 4 - Title", …
- They try "current cgp" and get "No results match current cgp" (there is no field called CGPA).
- They pick **"Current Term Score"** and set its Display Name to **"Current Course Score"**.

**1:42–1:50 – Browsing the rest of the field list**
- The presenter scrolls the full list. Fields already added are no longer offered: "Xth percentage" is missing between "Xth Score" and "Xth Board".
- They show the 12th fields, then Work Experience 4 … 1 (8 sub-fields each). The list closes.

**1:52–1:54 – Bottom of the editor**
- Below the cycle section there is a horizontal rule, a red **trash (delete template)** button and a green **"Template Saved"** button (disabled-looking, showing the saved state).
- They scroll back to the top. The final template is Name→"Name", Roll No→"Roll No", Xth percentage→"Class 10 %", Current Term Score→"Current Course Score".
- They do not use the "Placement Cycle Specific Information" section.

**2:00–2:12 – Back to the job profile**
- Placements → the FT 2026-27 cycle → Siemens EDA job → "136 Applicants" → "•••" → under "Download Applicants" they click **"Excel - Custom Template"**.

**2:14–2:20 – "Select Custom Template" modal**
- Label "Select template", a searchable select ("Select an Option") listing every template; "Test" is now at the end of the list.
- Buttons: Cancel and **"Download Excel File"** (green; faded until a template is chosen).
- The presenter selects "Test" and clicks Download Excel File.

**2:22–2:26 – Asynchronous generation**
- A download icon animates in the top bar.
- Blue toast: **"Download Queued! Please wait while the system is generating the report. You'll be notified by email once the report is ready to download."**
- The presenter opens the top-bar **downloads tray**. Each item shows an Excel icon and a title, then either a "Download" button with "Generated N minutes ago", or a status line. Earlier items:
  - "Applicant Resumes Shortlisted For Student Intern (Software Profile)/(digital Profile) (stage 4) At Siemens Eda India Private Limited"
  - "Applicant Resumes Shortlisted … (stage 3) …"
  - "Shortlist For Student Intern …(stage 3) At Siemens Eda India Private Limited (Template: Iit(ism)dhanbad)"
  - "Applicant List For Digital Consultant At Accenture Japan Ltd. 2026 (Template: Iit(ism)dhanbad)"
  - A "See All" link at the bottom.

**2:28 – The job is running**
- New top item: "Applicant List For Student Intern (Software Profile)/(digital Profile) At Siemens Eda India Private Limited 2026", status **"WORKING | Started a few seconds ago"**, a checkbox **"Email me this report."** and "✕ Cancel".

**2:32–2:40 – Downloading**
- The item finishes ("Download · Generated a few seconds ago"). The presenter clicks Download.
- Green toast: **"Done / Download Started"**. The file comes from `greekturtle-prod.s3.ap-south-1.amazonaws.com`, named `Applicant_List_for_Student_Intern_Software_Profile_Digital_Profile_at_Siemens_EDA_India_Private_Limited_2026.xlsx` (51.2 KB).
- The browser download history also shows earlier files: `APPLICANT_RESUMES_shortlisted_for_…_Stage_3_….zip` (2.1 MB), `…Stage_4….zip` (453 KB) and `Shortlist_for_…_Stage_3_…_Template_IIT.xlsx` (11.8 KB).

**2:42–2:48 – The file in Excel** (sheet "Sheet0", font Helvetica 10)
- Rows 1–10 hold a picture ("Picture 1", a letterhead/banner area).
- Row 13 holds a bold title: "LIST OF APPLICANTS FOR STUDENT INTERN (SOFTWARE PROFILE)/(DIGITAL PROFILE) SIEMENS EDA INDIA PRIVATE LIMITED FULL-TIME".
- Row 15 is the header: teal fill, white bold text.
- The presenter selects and deletes rows 1–14, so the header ends up in row 1.

**2:58–3:58 – The columns**
- The presenter clicks through and explains the headers, left to right:
  - A **S.No.**
  - B **Superset Id**
  - C **Name**
  - D **Roll No**
  - E **Class 10 %**
  - F **Current Course Score**
  - G **College** (value `IITISMD_63`)
  - H **Applied At** (`2026 Oct 05 06:12 PM`)
  - I **Current Stage** (numbers: 1, 2, 4)
  - J **Application Status** (`REJECTED BY ADMIN`)
  - K **CTC offered**
  - L **CTC Currency**
  - M **CTC Interval**
  - N **the full text of the job's additional question** ("I acknowledge that I will be participating in all the processes of this company. In case I do not participate in any process without written approval from CDC, I will be further blocked for 5 ongoing on campus opportunities . If found violating this condition then I will be perm…"); the cells are empty in this sample
  - O **Attached Resume** (the resume's file name or label)
  - P **Resume Link** (hyperlink text "Link")
  - Q **Offer Letter** (NA)
  - R **Signed Offer Letter** (NA)
  - S **Last Edited** (`2026 Oct 05 06:19 PM`)
- Columns C–F are exactly the template's columns in the template's order, using the custom display names. Every other column is fixed and added by Superset.
- **3:28–3:30** To explain column N, the presenter goes back to the job's "Additional Questions (i)" section. The note there reads "You cannot add/edit custom questions for this job profile once it has been submitted or opened for registration." The question carries the badge "DROPDOWN : SINGLE".
- **3:34–3:58** They return to Excel and select the College, Resume Link and CTC columns while explaining them. The video ends there.

---

## 2. Superset features shown

### F1. Excel Templates list (template library)
- **Superset name / path:** "Excel Templates" in the sidebar under **REPORTS → Excel Templates**; route `#/a/templates`. The card is headed "Available Templates", with the action "+ Add New".
- **What it does:** an institute-wide library of named column layouts for student and applicant Excel downloads. Each row offers ✎ Edit (opens the editor) and a copy icon, which is presumably "duplicate template" (see Uncertain). The list has no delete; deleting happens inside the editor.
- **Rules visible:** names are not unique. "Standard" and "standard" both exist, and so do "IIT ISM DHANBAD" and "IIT(ISM)Dhanbad".
- **Who uses it:** CDC admins only.
- **Used afterwards in:** every "Excel - Custom Template" download (F5) and the templated stage shortlists (F7).
- **Our status: NOT IMPLEMENTED.** Nothing in the code is a template, a column picker or a saved layout: a grep for `excel_template|export_template|ExcelTemplate|column_key|custom template` in backend and frontend returns nothing, no migration creates a template table, and `routes/api.php` has no template route. The only "template" routes are the student import blank sheets: `GET /admin/students/import/template` (api.php:97) and `GET /admin/students/academics/template` (api.php:99).
- **Naming:** we have no equivalent. Superset calls it "Excel Templates" (the editor breadcrumb says "Custom Excel Templates"). If built, use "Excel Templates" as the page and nav name.
- **Conflict check:** none for the library itself. Every create, rename, duplicate and delete is an admin write, so each must be audit-logged (standing rule, CDC_PORTAL_CONTEXT §10).

### F2. Add Excel Template dialog
- **Superset name / path:** an "Add Excel Template" modal, opened from Excel Templates → "+ Add New".
- **Fields:**
  - **Template Name** (text; placeholder "Ex. Wipro Format, Standard Format");
  - **Template Type**, read-only, value `STUDENT_LIST`. Only one type is offered here, so other types probably exist elsewhere (see Uncertain).
- **Buttons:** Cancel, "+ Create". Create saves the template and opens its editor at `#/a/templates/<uuid>`.
- **Validations seen:** none on screen; the name is assumed required.
- **Who:** admin.
- **Our status: NOT IMPLEMENTED** (as F1).
- **Naming:** Superset "Template Name" / "Template Type"; we have nothing.
- **Conflict check:** none.

### F3. Template editor (column builder)
- **Superset name / path:** Excel Templates → ✎ Edit, or right after Create. The breadcrumb is "Custom Excel Templates / <name>" and the card title "Excel Templates (i)".
- **Controls:**
  - the title, which can be renamed in place ("Click on title to edit");
  - a two-column table, **Column Key** (the system field, fixed once added) and **Display Name in Report** (free-text header shown in the Excel file);
  - per row: a ≡ drag handle to reorder, the editable display name, and a red trash icon to remove the row;
  - an add row: "+" plus a searchable select, "Choose a field to add". Fields already used disappear from the list, and an unmatched search shows "No results match <query>";
  - a new template is pre-filled with one row, "Name";
  - autosave with a three-state indicator: "There are unsaved changes!", "Saving", "All changes saved";
  - at the bottom, a red trash button (delete template) and a green, disabled-looking "Template Saved" button;
  - the section "Placement Cycle Specific Information" → "Add Placement Cycle Section" (select a cycle) and "+ Add Placement" (see F4).
- **Field catalogue.** Every option readable in the frames, as "Superset label / alternate label". The list scrolls further than shown.
  - Identity: **Name** (default row); **Roll No / Registration Number**.
  - Class 10:
    - Year of passing 10th / Class 10th Year
    - Xth Score Type / Class 10th Score Type
    - Xth Score Total / Class 10th Score Total
    - Xth Score / Class 10th Score
    - Xth percentage / Class 10th Percentage
    - Xth Board / Class 10th Board
  - Class 12:
    - Year of passing 12th / Class 12th Year
    - XIIth Score Type / Class 12th Score Type
    - XIIth Score Total / Class 12th Score Total
    - XIIth Score / Class 12th Score
    - XIIth percentage / Class 12th Percentage
    - XIIth Board / Class 12th Board
  - Current course: **Current Term Score**. This is the CGPA field; a search for "current cgp" finds nothing.
  - Work experience: 4, 2 and 1 were seen; 3 is presumed. Each has **Start Date, Job Title, Job Function, End Date, Duration, Description, Company Sector, Company Name** (for example "Work Experience 4 Start Date" … "Work Experience 4 Company Name").
  - Extra-curriculars 5 and 4 were seen; 1–3 are presumed. Each has **Title, Description, Start Date, End Date** (for example "Extra-Curricular 5 - Title").
  - Custom profile questions, prefixed "Q:", for example **"Q: Internship Job Role / Designation"**.
  - Implied by other template names but not seen: semester-wise CGPA ("Data With all Sem CGPA"), department ("Department"), domain ("Domain").
- **Who:** admin.
- **Used afterwards in:** the F5 downloads; the template becomes the student-data block of the file.
- **Our status: NOT IMPLEMENTED.** Our columns are hard-coded closures in `CDC/backend/app/Services/ExportService.php`:
  - applicants: `applicantsWorkbook()` lines 43–121;
  - cycle students: `studentsWorkbook()` headers at lines 163–167.
  - Nothing can be added, reordered or renamed.
- **Field coverage today:**

  | Superset field | Our data | In our exports? |
  |---|---|---|
  | Name | `student_profiles.full_name` | Yes, "Name" (ExportService:45, 164) |
  | Roll No / Registration Number | `roll_no` | Yes, "Roll No" (:44, 164) |
  | Xth percentage | `tenth_percent` | Yes, "10th %" (:52, 164) |
  | XIIth percentage | `twelfth_percent` | Yes, "12th %" (:53, 164) |
  | Current Term Score | `current_cgpa` | Yes, "CGPA" (:49, 164) |
  | Year of passing 10th/12th, Score Type, Score Total, Score, Board (10th & 12th) | **not stored** (migration `2026_09_21_162407_create_student_profiles_table.php` has only `tenth_percent`/`twelfth_percent`) | No |
  | Work Experience 1–4 (8 sub-fields each) | **no model/table** (grep `work_experience` = nothing) | No |
  | Extra-Curricular 1–5 (4 sub-fields each) | **no model/table** | No |
  | Semester-wise CGPA | **not stored** | No |
  | "Q:" profile-level custom questions | **none**; we only have per-drive `posting_questions` | Drive answers only ("Q1: …", ExportService:88–94) |
  | (ours, not seen in Superset) Programme, Branch, Batch, Ongoing/Total Backlogs, Gender, DOB, Category, PwD, Home State, LinkedIn, GitHub, Institute/Personal Email, Phone | stored | Yes (fixed) |

- **Naming mismatches, ours vs Superset:**
  - "CGPA" vs "Current Term Score";
  - "10th %" vs "Xth percentage" (Class 10th Percentage);
  - "12th %" vs "XIIth percentage" (Class 12th Percentage);
  - "Roll No" vs "Roll No / Registration Number";
  - "Name" matches.
  - Recommendation: keep our shorter display labels as the default display names, but use Superset's wording for the Column Key catalogue so CDC staff recognise it.
  - Note: Superset's "Current Term Score" is vaguer than "CGPA". CDC staff themselves renamed it to "Current Course Score" in the video, so renaming our "CGPA" is not recommended. Offer both as search aliases instead.
- **Conflict check:**
  - **Formula safety (D87/D88):** custom display names are admin-typed headers and must go through `put()` as explicit strings (ExportService:231–244), as round names already do.
  - **Locked academics:** extra fields such as board, year of passing and work experience would need new profile data. Students cannot edit academic fields (§2.3), so the CDC would have to import them. A student-editable work-experience or extra-curricular section would be a product change. **NEEDS OWNER DECISION.**
  - **Company field policy (Q10.2 / D78):** see F5.

### F4. Placement Cycle Specific Information (cycle sections inside a template)
- **Superset name / path:** inside the template editor: heading "Placement Cycle Specific Information", control "Add Placement Cycle Section" (select a cycle) + "+ Add Placement".
- **What it does (inferred, not demonstrated):** adds a block of per-cycle columns, such as that cycle's offers, status or placed flag, for a chosen placement cycle. This lets a student list carry placement data from one or more named cycles.
- **Who:** admin.
- **Our status: NOT IMPLEMENTED.** The closest thing is the fixed cycle export `GET /admin/placement-cycles/{placementCycle}/students/export` (api.php:91, `AdminPlacementCycleController::exportStudents` lines 126–131, ExportService 157–225). Its cycle-specific columns, for one cycle only, are: Account, Enrolment, "Applications (live)", "Offers", "Best CTC (annual, INR)", "Best Stipend (monthly, INR)" and "Active Blocks". There is no way to pick a different cycle or several cycles.
- **Naming:** our button is "Export Students" (`app/admin/placement-cycles/[id]/page.jsx:284`); Superset has no direct equivalent label. Our columns "Offers", "Best CTC …" and "Active Blocks" have no visible Superset counterpart in this video.
- **Conflict check:** blocks are internal. Exporting cycle data to companies would conflict with "internal flags never go to companies". Admin-only use is fine.

### F5. Download Applicants / Download Eligible List with a template choice
- **Superset name / path:** Job profile page → "<N> Applicants" split button → "•••" menu:
  - **Download Applicants → Excel - Default Template / Excel - Custom Template**
  - **Download Eligible List → Excel - Default Template / Excel - Custom Template**
  - (also "View Applicant List" and "Add Applicants Manually")
- Custom Template opens the **"Select Custom Template"** modal: "Select template" (searchable select over every template) → Cancel / "Download Excel File" (disabled until a template is chosen).
- **Output layout (applicant list):**
  - a banner picture (rows 1–10);
  - a title row: "LIST OF APPLICANTS FOR <PROFILE> <COMPANY> <FULL-TIME>";
  - a header row with teal fill and white bold text;
  - then S.No., Superset Id, **[template columns in template order, with the template display names]**, College, Applied At, Current Stage, Application Status, CTC offered, CTC Currency, CTC Interval, **one column per job additional question (full question text as header)**, Attached Resume, Resume Link ("Link" hyperlink), Offer Letter, Signed Offer Letter, Last Edited.
- **File name:** `Applicant_List_for_<Profile>_at_<Company>_<Year>.xlsx`.
- **Who:** admin, in this video. Whether recruiters get templates is not shown.
- **Our status: PARTIAL.**
  - We have **one fixed "Export"** per drive for admins: button `app/admin/postings/[id]/page.jsx:81–89` → `GET /admin/postings/{jobPosting}/export` (api.php:125) → `AdminPostingController::export` (lines 360–365, audit `posting.export`) → `ExportService::applicantsWorkbook($posting,'admin')`.
  - Companies get a fixed restricted export: button `app/company/postings/[id]/page.jsx:108–122` → `GET /company/postings/{jobPosting}/export` (api.php:219) → `CompanyPipelineController::export` (lines 108–113).
  - **Missing:**
    - (a) the Default/Custom choice and the "Select Custom Template" dialog;
    - (b) **any Excel download of the Eligible List.** `GET /admin/postings/{jobPosting}/eligible` (api.php:122, `AdminPostingController::eligible` lines 399–433) returns paginated JSON only, and `components/admin/posting/eligibletab.jsx` has no download button;
    - (c) S.No. column;
    - (d) CTC Currency column in the applicant export (`offers.currency` exists but is not exported; ExportService:115–121);
    - (e) Last Edited (`applications.updated_at` exists, not exported);
    - (f) a single "Current Stage" column. We use one column per round ("R1: …", ExportService:96–113), which carries more detail;
    - (g) Offer Letter / Signed Offer Letter (no offer-letter feature exists; grep `offer_letter` = nothing);
    - (h) a title/banner row (ours puts the header in row 1, which is better for filtering; Superset users delete the banner by hand, as the presenter did).
  - **Already present:** Applied At ("Applied At (IST)", admin only), Application Status (admin only, Applied/Withdrawn), the offer CTC ("Offer CTC (annual)", "Offer Stipend (monthly)", admin only), question answers ("Q1: …"), Attached Resume ("Resume Label"), Resume Link (signed 30-day link, text "Open resume").
  - "Superset Id" and "College" have no meaning for a single-institute portal and need no equivalent.
- **Naming mismatches, ours vs Superset:**
  - button "Export" vs "Download Applicants → Excel - Default Template";
  - "Applied At (IST)" vs "Applied At";
  - "Resume Label" vs "Attached Resume";
  - link text "Open resume" vs "Link";
  - "Offer CTC (annual)" vs "CTC offered" (plus "CTC Interval"/"CTC Currency");
  - question header "Q1: <first 60 chars>" vs the full question text. Keep ours: D88 prefixes exist so equal names never merge;
  - sheet name "Applicants" vs "Sheet0" (keep ours);
  - file name `<company>-<title>-applicants.xlsx` vs `Applicant_List_for_<Profile>_at_<Company>_<Year>.xlsx`;
  - "Eligible" tab (no download) vs "Download Eligible List".
- **Conflict checks:**
  - **Company exports.** If companies can pick templates, the template catalogue must be filtered for the company audience. Companies may get only roll no, name, academics, answers, published outcomes and the resume link (spec Q10.2, D78, CDC_PORTAL_CONTEXT §3 Exports). Contact columns are allowed only when `share_contact_details` is on (ExportService:68–74). `used_unverified_resume` and `placed_elsewhere_flag` must never reach them (§4 Visibility). Fields such as Gender, DOB, Category, PwD, Home State, work experience or Last Edited are not in the company field set today, so offering them to companies is **NEEDS OWNER DECISION**. Safest default: templates are admin-only, or a company-side template silently drops non-whitelisted fields.
  - **Eligible List export to companies:** students who are eligible but did not apply have never consented to being shared. An admin-only Eligible List export is fine; a company one is **NEEDS OWNER DECISION** (not recommended).
  - **Offer Letter / Signed Offer Letter:** that would be an offer-letter upload feature. The owner said students cannot decline offers and nothing about letters, so it is a new product feature. **NEEDS OWNER DECISION.**
  - **Formula safety:** every template value and header must keep using `put()` explicit strings (D87/D88).

### F6. Background report generation and Downloads tray
- **Superset name / path:**
  - top-bar download icon → tray with items ("Applicant List For … (Template: <name>)", "Shortlist For … (stage N) …", "Applicant Resumes Shortlisted For … (stage N) …"), each "WORKING | Started …" or "Download / Generated N minutes ago";
  - an **"Email me this report."** checkbox and "✕ Cancel" while working;
  - "See All";
  - toasts: "Download Queued! Please wait while the system is generating the report. You'll be notified by email once the report is ready to download." and "Done / Download Started".
  - Files are served from S3.
- **Who:** admin.
- **Our status: NOT IMPLEMENTED.** Exports stream synchronously (`ExportService::stream()` lines 271–280, `response()->streamDownload`). The frontend downloads the blob directly (`lib/adminapi.ts` `adminDownload` lines 31+, `lib/companydownload.js`). There is no export job, no stored report file, no downloads list and no "email me" option. D78 notes cycle exports are already written in chunks of 200, and QA passed at about 2,800 students and 6,000 applications, so synchronous export is adequate at our scale.
- **Naming:** none of ours.
- **Conflict check:**
  - Storage is local disk with no S3 (§3 Foundation), so generated reports would need private local storage with expiry, which is feasible.
  - "Email me this report" would send mail with a download link. Mail goes to institute addresses; admins are not students, so the policy is unclear. **NEEDS OWNER DECISION**, Low priority.

### F7. Stage-wise templated shortlist and resume ZIP (seen only in the downloads tray)
- **Superset name / path:** tray items "Shortlist For <profile>(stage 3) At <company> (Template: Iit(ism)dhanbad)" (xlsx) and "Applicant Resumes Shortlisted For <profile> (stage 3/4) At <company>" (ZIP, 2.1 MB / 453 KB). They were produced earlier from the job's Stages section, not demonstrated here.
- **What it does:** downloads one stage's shortlist using a chosen template, and a ZIP of that stage's resumes.
- **Our status:** the per-stage shortlist export is **NOT IMPLEMENTED**. Our single applicant export carries every round as a column (ExportService:96–113), so a filter in Excel gives the same list, but there is no per-round download. The resume ZIP is **deliberately not built**: the owner chose a signed resume link column "instead of a ZIP of resumes" (CDC_PORTAL_CONTEXT §3 Exports, spec Q8.1).
- **Naming:** our rounds are "Rounds"/"Pipeline"; Superset uses "Stages". This is a cross-video naming point; see the Rename list.
- **Conflict check:** the resume ZIP contradicts the owner's link-instead-of-ZIP decision. **NEEDS OWNER DECISION**; do not build silently. A per-round templated shortlist download has no conflict.

### F8. Cycle-level "Important Links" reports (context only, seen in passing)
- **Superset name / path:** Placements → cycle → left panel "Important Links":
  - Dashboard, Placement Matrix Report, Placement Absentees Report, Student Placements Report, Student Opportunities Report, Download Job Profiles List, Student Offers Report, Application Preferences Report;
  - List : Job Offers, List : Students Placed, List : Students Not Placed.
  - Also a sidebar REPORTS group: Reports, Custom Reports, Excel Templates, Email Logs.
- **Our status: PARTIAL, outside this video's scope.** We have the cycle "Export Students" workbook (one fixed sheet), Analytics (`app/admin/analytics`) and the Audit Log, but none of the named reports. The email logs table exists (`email_logs`) but has no admin page in the nav (`components/admin/adminshell.tsx:47–65`).
- These belong to other videos' analyses and are listed only so they are not lost.

---

## 3. Gap summary

| Superset name | What to build | Priority | Conflicts |
|---|---|---|---|
| Excel Templates (REPORTS → Excel Templates), list + "+ Add New" + duplicate | New `export_templates` table (name, type, ordered columns JSON [{key, label}], created_by) via a new migration; admin CRUD routes; admin page "Excel Templates" in the More drawer; audit-log every write | High | None (audit every write) |
| Add Excel Template dialog (Template Name, Template Type) | Name (required) + type (`STUDENT_LIST` first; maybe `APPLICANT_LIST`) | High | None |
| Template editor: Column Key / Display Name in Report, drag reorder, delete row, searchable field picker, used fields hidden, rename title, delete template, autosave state | Field catalogue registry in the backend (key → label, aliases, value resolver, audience: admin/company); editor page; formula-safe headers | High | D87/D88: headers and values as explicit strings |
| Excel - Custom Template on Download Applicants | "Select Custom Template" dialog on the admin drive page; `?template=<id>` on `/admin/postings/{id}/export`; template columns replace the fixed profile block; status/answers/rounds/offer/resume link stay fixed | High | None for admin |
| Download Eligible List (Default + Custom Template) | Excel export of `eligibleStudentsQuery()` with an Applied/Not applied column, from the Eligible tab | High | Admin only; company access NEEDS OWNER DECISION |
| Custom template for companies | Let the company export pick from a company-safe field subset | Low | **NEEDS OWNER DECISION** (Q10.2 field set, share-contact rule, internal flags) |
| Missing applicant-export columns: S.No., CTC Currency, Last Edited | Add to the default admin export | Medium | None (admin only) |
| Placement Cycle Specific Information (cycle sections in a template) | Per-cycle column groups (offers, best CTC, blocks, enrolment) for a chosen cycle in a student-list template | Medium | Blocks are internal: admin-only |
| Extra profile fields: 10th/12th year, board, score type/total/score, semester CGPA | New student_profile columns + bulk import + catalogue entries | Medium | Academics are CDC-controlled (import only), consistent with locked fields |
| Work Experience 1–4, Extra-Curricular 1–5, "Q:" profile questions | New profile sections + catalogue entries | Low | **NEEDS OWNER DECISION** (new student-editable data) |
| Offer Letter / Signed Offer Letter columns | An offer-letter upload feature | Low | **NEEDS OWNER DECISION** (new feature) |
| Download queue + Downloads tray + "Email me this report." | Queued export jobs, stored private files, a tray UI, optional email | Low | Local disk only; email policy NEEDS OWNER DECISION |
| Stage shortlist download with template | Per-round export (selected/waitlisted of round N) with a template | Medium | None |
| Applicant Resumes ZIP per stage | ZIP of resumes | Low | **Conflicts** with the owner's "signed link instead of ZIP" decision: NEEDS OWNER DECISION |
| Title/banner rows in the export | Not recommended (users delete them by hand) | n/a | None |

---

## 4. Rename list

| Where it appears in our UI/code | Our current name | Superset name |
|---|---|---|
| Admin drive page button (`app/admin/postings/[id]/page.jsx:88`) | Export | Download Applicants → Excel - Default Template (split menu with Excel - Custom Template) |
| Company drive page button (`app/company/postings/[id]/page.jsx:122`) | Export | Download Applicants (Excel - Default Template) |
| Admin drive tab (`app/admin/postings/[id]/page.jsx:28`, `components/admin/posting/eligibletab.jsx`) | Eligible (no download) | Download Eligible List (menu entry); Superset's on-page label is "Check Eligibility" |
| Cycle page button (`app/admin/placement-cycles/[id]/page.jsx:284`) | Export Students | no exact equivalent (Superset: Excel icon / "Important Links" reports) |
| Export header (ExportService:49, 164) | CGPA | Current Term Score (CDC's own template renames it "Current Course Score"; keep "CGPA", add an alias) |
| Export header (ExportService:52, 164) | 10th % | Xth percentage / Class 10th Percentage (CDC renamed it "Class 10 %") |
| Export header (ExportService:53, 164) | 12th % | XIIth percentage / Class 12th Percentage |
| Export header (ExportService:44) | Roll No | Roll No / Registration Number (output header "Roll No"; matches) |
| Export header (ExportService:79) | Applied At (IST) | Applied At |
| Export header (ExportService:80) | Resume Label | Attached Resume |
| Resume link cell text (ExportService:140) | Open resume | Link |
| Export header (ExportService:118) | Offer CTC (annual) | CTC offered (+ CTC Currency, CTC Interval) |
| Export header (ExportService:119) | Offer Stipend (monthly) | CTC offered with CTC Interval = monthly (Superset uses one CTC column + an interval) |
| Export headers (ExportService:96–113) | R1: <round name> … (one per round) | Current Stage (single numeric column); Superset calls rounds "Stages" |
| Export headers (ExportService:88–94) | Q1: <question, 60 chars> | <full question text> (keep ours per D88) |
| Sheet name (ExportService:125) | Applicants | Sheet0 (keep ours) |
| File name (ExportService:151) | `<company>-<title>-applicants.xlsx` | `Applicant_List_for_<Profile>_at_<Company>_<Year>.xlsx` |
| Admin nav (`components/admin/adminshell.tsx:47–65`) | (no Reports group; Analytics, Audit Log) | REPORTS: Reports, Custom Reports, Excel Templates, Email Logs |
| Glossary / pipeline UI | Round(s) | Stage(s) |
| Glossary / UI | Drive / Posting | Job Profile |
| Glossary / UI | Cycle | Placement (Placement Cycle) |

Renaming UI labels is allowed. Never rename existing routes, columns or response keys (§10 rule). Changing export headers alters files people may already rely on, so confirm with the owner first.

---

## 5. Uncertain

- **The copy icon** on each Excel Templates row is assumed to be "duplicate template"; it is never clicked and has no tooltip in the frames.
- **"Placement Cycle Specific Information" / "+ Add Placement"** is never used, so which columns a cycle section adds is inferred, not seen.
- **Template Type** shows only `STUDENT_LIST`, read-only. Whether other types exist (an applicant list, for instance) and why the same template works for both "Download Applicants" and "Download Eligible List" is not shown.
- **Work Experience 3 and Extra-Curricular 1–3** were scrolled past and not seen; they are presumed from the 1/2/4 and 4/5 patterns. The full catalogue is longer than what was visible: the list scrolls well beyond the frames, and fields such as department, branch, email, phone, gender and semester CGPAs are implied by template names ("Department", "Domain", "Data With all Sem CGPA") but not seen.
- **"Q: Internship Job Role / Designation"** looks like a profile-level custom question, but it could be a job additional question.
- **The dropdown label for "Current Term Score"** (any "/ alias" part) was not captured; only the resulting row was seen.
- **Who else can use templates.** Whether recruiters can use custom templates, and whether Superset filters fields per audience, is not shown.
- **Empty answer column.** Column N (the additional-question answers) is empty for every visible row even though it is a required dropdown. The reason is unknown (possibly unanswered, or exported elsewhere).
- **Download Eligible List output.** Its columns, for either template type, were not shown.
- **Banner picture content** (rows 1–10 of the downloaded sheet) is not legible; it is presumably the institute letterhead.
- **Narration.** Audio was not available; the narration is inferred from on-screen actions only.
