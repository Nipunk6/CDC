# 01 · "How to configure the Superset Account" (268 s): analysis

Source: 53 keyframes in `scratchpad/kf/01_configure_account/` (t000m00s to t004m20s), all read in order.
Our code base: `/Users/admin/Desktop/CDC-main/CDC` (backend Laravel, frontend Next.js). Every "our status" claim below was checked with grep/read; paths are relative to `CDC/` unless absolute.

Privacy note: the video shows real staff names, institute email addresses and one mobile number. This document describes users only by role and designation and does not copy personal contact details.

---

## 1. Video walkthrough

The whole video is in the Superset **admin web app** (`app.joinsuperset.com/#/admin/...`), logged in as the CDC's own Account Admin. Every screen has the same chrome, described once here:

- **Top bar:** the institution name "Indian Institute of Technology Indian School of Mines Dhanbad" on the left. On the right: a global "Search students" box with a magnifier icon, a download (tray-arrow) icon, a bell, a 3x3 apps-grid icon, the account name "Career Development Centre IIT ISM Dhanbad" and a round avatar with the initials "CD".
- **Dark left sidebar:** the "superset" logo and a close "×" button, followed by:
  - **RECENT JOB PROFILES:** small chips that are truncated job-profile names, such as "CentrAlign AI…", "Founding Eng…", "C-DAC, Kolkata…", "Knowledge", "Accenture Japa…" and "Digital Cons…".
  - **RECENTLY VISITED PLACEMENTS:** two cycle links, "Full-Time Placement for 2…" and "Internship Placement || …".
  - **Navigation:** Home, My Dashboards; the group **JOBS** with Companies, Inbound Job Posts, Placements and Job Alerts (with a sparkle icon); the group **RELATIONSHIPS** with Students and CRM. The list continues below the fold and is never scrolled.
- **Chat bubble:** a blue round button at the bottom right (Intercom-style support chat).

| Time | What the presenter does / what is on screen |
|---|---|
| 00:00 | On **Placements** (`/#/admin/placements`). The page has three columns. **Left column:** a calendar illustration, the heading "Placements" and the explainer "Placement Cycles help you manage distinct recruitment phases—like **Final Year Placements** and **Pre-Final Year Internships**—with dedicated timelines, policies, and teams. Each cycle is a self-contained process tailored to a specific student batch and purpose.", then the outlined button "+ Add placement process". **Middle column:** a "Search Placements" input, then the current cycles as cards. Each card has a blue calendar icon, the cycle name, a month range and a gear icon. The cards are "Full-Time Placement for 2026-27 (2027 Pass Out Batch)", June 2026 - June 2027, and "Internship Placement \|\| 2026-2027 Session \|\| 2028 Pass out batch", April 2026 - August 2027. Below them is the sub-heading "Previous Placements": "Internship Placement Cycle for 2029 Graduating Batch **[DRAFT]**" (yellow/brown icon, March 2026 - February 2026), "Full-Time Placement for 2025-26 (2026 Pass Out Batch)" (May 2025 - May 2026) and "Internship Placement for 2025-26 (2027 Batch)" (May 2025 - May 2026). **Right column:** "Recently Visited", a list of six cycles with date ranges and gear icons. It includes "FT Placement for 2023-2024 (2024 passouts)" (May 2023 - December 2024) and "Full-Time Placement for 2024-25 (2025 Pass Out Batch)" (May 2024 - May 2025). |
| 00:08 | Clicks the account name/avatar at the top right. A dropdown opens with **Account**, **Settings**, a divider and **Logout**. |
| 00:10 | Opens the **Admin** area (`/#/admin/accounts`). The page title is "Admin". A grey vertical tab list sits on the left; the tabs are in section 2.1. The **ACCOUNT** tab is selected and shows the institution name as a heading, "Account Logo (i)", a framed square institute logo, and the help text "Click on the image above to upload a new account logo / The logo should preferably be a square image for best fit and look across interfaces." |
| 00:14 | Hovers the logo. A grey overlay appears with an upload icon and "Change Logo". |
| 00:16 | **COLLEGE** tab (`/#/admin/colleges`). The page is headed "Colleges" with "+ Add College" at the top right. There is one row: the logo, the institution name, the code "IITISMD_63" and "⚙ Settings". |
| 00:26 | **USERS** tab (`/#/admin/users`). The middle pane has the tabs **USERS \| STUDENTS**, a "+ Add User" button, a kebab menu (⋮) and a "Search" box with the placeholder "Search by name or email…". Below is a scrolling list of users, each with an initials avatar, name, designation and email. The designations seen are Chairperson (CDC), Student Admin, Faculty (many departmental accounts of the form `tp_<dept>@…`), Chairperson, Placement Officer and Assistant Professor. The right pane says "Select a user to view permissions". |
| 00:28 | Clicks the CDC account; the URL becomes `?u=<uuid>`. The detail card shows the name, "Chairperson (CDC)", the email, the chips **ACCOUNT ADMIN** and **CRM ADMIN**, a button "👁‍🗨 Disable Public Profile" (eye-slash icon), and the buttons **Edit** and **Delete this User** (red). Below the card: "Account Admins have access to everything". |
| 00:30–00:32 | Scrolls the user list to the end and back to the top. |
| 00:36 | Clicks a user with the designation **Student Admin**. The chip is **ACCOUNT USER** and there is a "👁 Make Visible to Employers" button. Below the card are padlock-headed sections. **Access Control** has the collapsible rows Global Access, Placement Cycle Level Access, College Level Access and Department Level Access. **Communication Control** has Notice Board. **Data Control** has Excel Templates and Inbound Job Posts. |
| 00:40 | Scrolls a little; the same sections are shown. |
| 00:42 | Clicks a departmental **Faculty** user (designation "Faculty"). The chip is **ACCOUNT USER** and the sections are the same as at 00:36. |
| 00:44 | Clicks an **Assistant Professor** user. The chip is **FACULTY MEMBER** and a large person-with-graduation-cap icon appears at the top right of the card. This role's sections are Global Access, College Level Access and Department Level Access under Access Control (**no Placement Cycle Level Access**), Notice Board, Excel Templates and Inbound Job Posts. |
| 00:52–00:54 | Expands **Global Access**. It lists toggles: "Can the user **view Dashboards tab**?" (on, green), "Can the user **view Reports tab**?" (on), then the sub-heading **Job Profiles** with "Can the user **access Progress Grid for Job Profile**?" (on), "Can the user **view placement stats tab**?" (on) and "Can the user **send communications for Job Profile**?" (off, red). |
| 00:58 | Expands **College Level Access**. On the left is the institution name as a link. On the right are checkboxes: "View Students" (ticked and greyed, so it cannot be changed), "Invite Students", "Edit Student Profiles" and "Approve/Reject change requests", all unticked. |
| 01:00 | Expands **Department Level Access**. "Access Level" has three radios: **Read** (selected), **Read and Verify** and **Read and Write**. Below is "Allow Access to the following courses" with a tree: All Colleges › institution › Department (for example "Department of Humanities and Social Sciences", "Department of Management Studies", "Department of Science & Technology") › sub-group with a code (for example "Digital Humanities & Social Sciences (HSS)", "General (Gen)", "Business Analytics (BA)") › course (for example "MA - Humanities and Social Sciences", "MBA (Semester) - MBA", "MBA (Semester) - Business Analytics"). |
| 01:02–01:04 | Scrolls the tree. Every node has a checkbox and every node shown is ticked; groups have collapse carets. The groups seen are Management Studies (MS), Mathematics & Computing (MnC), Electronics Engineering (ECE), Computer Science & Engineering (CSE), Physics (PH), Applied Geophysics (AGP), Applied Geology (AGL) and Department of Science & Technology. The courses seen include "B.Tech - Mechanical Engineering - Mining Machinery Engineering", "M.Tech (Integrated) - Mathematics & Computing", "Dual Degree - B.Tech + M.Tech. - Computer Science & Engineering", "M.Sc. (Tech., 3 years) - Applied Geology" and "M.Tech. - Material Science and Technology". |
| 01:06 | Expands **Notice Board** under Communication Control. It shows "Can View Notice Tab?" and "Can User Create Notice on Notice Board?". The toggles are cut off because the page is scrolled sideways. |
| 01:08 | Expands **Excel Templates** under Data Control. It shows "Can user create new Excel Templates?". **Inbound Job Posts** stays collapsed. |
| 01:10 | Scrolls the course tree again. Further groups are Physics (PH) with B.Tech - Engineering Physics, Chemical Engineering (CHE), Petroleum Engineering (PE) and Electrical Engineering (EE) with M.Tech. - Electrical Engineering - Intelligent Control and Instrumentation. |
| 01:12–01:18 | Scrolls back to the top of the Assistant Professor's card and Global Access (same toggle states as at 00:54). |
| 01:22 | Clicks **Edit**. An **Edit User** modal opens with these fields: **Role\*** (dropdown, value FACULTY MEMBER); **Name\*** (three inputs: first, "Middle Name" placeholder, last); **Alias** with the help text "If set, User name alias will be used in studentapp communications like noticeboard." and the placeholder "user name alias"; **Mobile\*** (still loading); **Email ID\*** (greyed, read-only); **Designation\*** (text, "Assistant Professor"). The buttons are **Cancel** and **Edit User** (blue). |
| 01:24 | Opens the Role dropdown. Its options are **ACCOUNT ADMIN**, **ACCOUNT USER** and **FACULTY MEMBER**. Mobile has now loaded as a country-flag picker (India) with "+91 …". |
| 01:30 | Closes the modal without saving. |
| 01:34–01:42 | Scrolls the user detail and then the user list. |
| 01:48 | Opens the **STUDENTS** tab of Users. It lists two student users, each with a photo, name and institute email (roll-number style, for example `23JE0044@…`). The selected student shows the chip **STUDENT** and **Access Control › Global Access** with toggles, all off (red). Each of the first four ends "…where the user is added as a collaborator?": "Can the user **add applicants** to job profiles…", "Can the user **remove applicants** to job profiles…", "Can the user **update status** of job profiles…" and "Can the user **publish list of offers** of job profiles…". Then come "Can the user **create custom events**?", "Can the user **view Dashboards tab**?" and "Can the user **view Companies tab**?". |
| 01:56 | Scrolls the same toggle list. It continues with "**view Reports tab**", "**view Documents tab**" and "**view Placement Student Lists**", then the sub-heading **Job Profiles** with "**Mute Communications for Job Profile**", "**access Progress Grid for Job Profile**", "**download Excel lists for Job Profile**" and "**send communications for Job Profile**". All are off. |
| 02:02 | Below that are **Placement Cycle Level Access** (collapsed) and **College Level Access** (expanded, "View Students" ticked). |
| 02:08 | **INVITATION CONFIGURATION** tab (`/#/admin/invitation-configuration`). It shows "Formal Letter Head (i)", an empty illustration, and the text "You have not uploaded any letter head yet. Click on the image above to upload a letter head. The letter head must be of be **800px × 155px** for the best fit". |
| 02:16 | **CUSTOM FIELDS** tab (`/#/admin/custom-fields`). The intro text reads "You can add custom fields to Superset items like Student Registration Form, Company Job Post Submission Form, Student offer Form etc to capture additional information. This information can provide additional information and be important to your workflow and team." There are three sections, each with an (i) icon and a "+ Add Custom Field" row: **Student Registration Form**, **Job Submission Form For Companies** and **Student Job Offer Fields for Account Users**. The lists are still loading in this frame. |
| 02:18–02:38 | The Student Registration Form list loads; the table is in section 2.8. Each row has the field label, a type chip (**TEXT FIELD** or **DROPDOWN : SINGLE**) and three icons: a pencil (edit), a red padlock and a red trash can. The list ends with "+ Add Custom Field" and then the next section heading. |
| 02:48 | **ENROLLMENT CONFIGURATION** tab. The URL is spelt `/#/admin/enrolment-configuration`. It shows "Mandatory Documents (i)" with the help text "Check the programs for which uploading the document is mandatory for the students.(None is mandatory by default)". The checkboxes are Current/Ongoing course, Under Graduate, Post Graduate, Class 10th, Class 12th and Diploma, all ticked, followed by a **Save** button. |
| 02:54 | **RESUME TAGS** tab (`/#/admin/resume-tags`). Heading "Resume Tags (i)", "+ Add Resume Tag", and the empty state "No resume tags configured." A side panel titled "What are Resume tags?" reads "Here you can configure resume tags that are used for specifying which resume is applicable for applying to a job profile." |
| 03:00–03:04 | **STUDENT CATEGORIES** tab (`/#/admin/student-categories`). Heading "Student Categories for Placement (i)". Each category is a card with a title, a description, and pencil and trash icons; the list is in section 2.11. A side panel explains the feature (see 2.11). No add button is visible. |
| 03:08 | **REMINDER RULES** tab (`/#/admin/reminder-rules`); see 2.12. |
| 03:46 | **FEE PAYMENTS** tab (`/#/admin/fee-payments`). Heading "Fee Payments" with two chevron rows, **Record Payments** and **Check Payments**. Neither is opened. |
| 03:56 | **STUDENT PROFILE SECTION TAGS** tab (`/#/admin/student-profile-tags`). Heading "Student Profile Section Tags (i)", "+ Add Tag", empty state "No student profile tags configured." Side panel: "Here you can configure student profile section tags that can be attached to a section of the students profile to enable tracking and impose restrictions." |
| 04:04 | **STUDENT EXAM MASTER** tab (`/#/admin/student-exam-masters`). Heading "Student Exam Master (i)", "+ Add Exam", empty state "No student exams added." There is no side panel. |
| 04:10 | **NOC TEMPLATES** tab (`/#/admin/noc-templates`). Heading "No Objection Certificate Templates" with the subtitle "These are the certificates your institution issues. They are maintained by Superset — to change the wording, please contact support." The table columns are **Name \| Maintained For \| Last Updated**. The one row is "No Objection Certificate", a grey chip "Superset default", "14 Aug 2026" and an eye-icon "View" link. |
| 04:16 | **COMMUNICATION GROUPS** tab (`/#/admin/communication-groups`). Heading "Communication Groups", "+ Add Group", empty state "No communication groups configured." Side panel: "Here you can configure named groups of account users (e.g. "Marketing Team", "Faculty Core") to quickly notify them together from any job profile notification." |
| 04:20 | **DATA ADDITION REQUESTS** tab (`/#/admin/data-addition-requests`). Heading "Data Addition Requests", "+ New Request", subtitle "Request a value that is missing from the master list. Once approved, it can be selected wherever that data is used.", empty state "No data addition requests raised yet." The video ends here. |

---

## 2. Superset features shown

Our admin portal has no settings area with tabs. The nearest thing is `frontend/app/admin/settings/page.jsx`, page title "Portal Settings", which has one card, "Student email delivery" (`mail_mode`). `backend/app/Services/SettingsService.php:14-16` stores only `mail_mode`, served by `GET/PATCH /admin/settings` (`backend/routes/api.php:159-160`).

### 2.1 Admin settings area ("Admin", reached from the account menu → Settings/Account)
- **Superset:** a page titled "Admin" with a left vertical tab list, in this order: ACCOUNT, COLLEGE, USERS, INVITATION CONFIGURATION, CUSTOM FIELDS, ENROLLMENT CONFIGURATION, RESUME TAGS, STUDENT CATEGORIES, REMINDER RULES, FEE PAYMENTS, STUDENT PROFILE SECTION TAGS, STUDENT EXAM MASTER, NOC TEMPLATES, COMMUNICATION GROUPS, DATA ADDITION REQUESTS. It is reached from the top-right account dropdown (Account / Settings / Logout). Each tab is its own route under `/#/admin/<slug>`. Used by the Account Admin.
- **Our status: PARTIAL.**
  - A Settings page exists, `frontend/app/admin/settings/page.jsx:55`, titled "Portal Settings" and holding only the mail-mode radio.
  - Users are a separate page, "Manage Admins" (`frontend/app/admin/manage-admins/page.tsx`, super admin only, nav item added in `frontend/components/admin/adminshell.tsx:89`).
  - The programme/branch master is a separate "Branch Manager" page (`adminshell.tsx:63` → `frontend/app/admin/programme-branches/page.tsx:179` "Programme Branch Manager").
  - Missing: one tabbed settings hub, and an account menu in the top bar (ours shows a "Sign Out" button only).
- **Naming:** our drawer item "Settings" and page title "Portal Settings" correspond to Superset's account-menu "Settings" and page title "Admin". Recommendation: keep "Settings" as the nav label and make it the hub; rename the page title "Portal Settings" to "Settings" or "Admin". "Manage Admins" should move under it as the "Users" tab.
- **Conflict check:** none.

### 2.2 Account: Account Logo
- **Superset:** ACCOUNT tab with the institution name, "Account Logo (i)", a click-to-upload logo with a "Change Logo" hover overlay, and the help text "Click on the image above to upload a new account logo / The logo should preferably be a square image for best fit and look across interfaces." Used by the admin; the logo appears across the interfaces for students, recruiters and staff.
- **Our status: NOT IMPLEMENTED.** The logo is a hard-coded static file: `frontend/components/admin/adminshell.tsx:226-228` (`/images/centenary-badge.png`) and `frontend/components/student/studentshell.jsx:118`. The title text is hard-coded: "IIT ISM CDC - Admin Portal" / "CDC Admin" (`adminshell.tsx:243-247`). The email header is hard-coded text: `backend/resources/views/emails/layouts/portal.blade.php:13-15`, "IIT (ISM) Dhanbad / Career Development Centre". No setting key exists for the logo or name.
- **Naming:** none of ours; Superset's term is "Account Logo".
- **Conflict check:** none. **Priority: Low** for a single-institute deployment.

### 2.3 College
- **Superset:** COLLEGE tab headed "Colleges", with "+ Add College" and rows of logo, college name, college code (for example "IITISMD_63") and "⚙ Settings". It supports several colleges under one account.
- **Our status: NOT IMPLEMENTED, and not needed.** The portal serves one institute; there is no `colleges` table (see `backend/database/migrations/`).
- **Naming:** n/a.
- **Conflict check:** none. **Priority: Low** (skip unless the owner wants multi-campus).

### 2.4 Users: staff user directory
- **Superset:** USERS tab with the sub-tabs **USERS \| STUDENTS**, "+ Add User", a kebab menu (contents not shown) and the search "Search by name or email…". The list shows an avatar, name, designation and email. Selecting a user opens a detail card with:
  - the name, designation and email;
  - a role chip (ACCOUNT ADMIN / ACCOUNT USER / FACULTY MEMBER / STUDENT) plus "CRM ADMIN" when it applies;
  - a public-profile toggle button (2.7);
  - **Edit** and **Delete this User**.
  
  An Account Admin's card says only "Account Admins have access to everything". The deep link is `?u=<uuid>`.
- **Our status: PARTIAL.**
  - `GET/POST/DELETE /admin/manage-admins` (`backend/routes/api.php:75-77`) is handled by `backend/app/Http/Controllers/AdminManagementController.php`. It is super-admin only (lines 21, 34, 66), takes name and email only (lines 38-41), and sends a set-password link (line 53).
  - Delete is a hard delete (line 82), cannot remove a super admin or yourself (lines 74-80), and is audited (lines 56, 83).
  - The UI, `frontend/app/admin/manage-admins/page.tsx`, is a table (Name, Email, Role, Actions) with "Add Admin" and "Delete"; the modal `addadminmodal.tsx` ("Add New Admin") has the fields Name and Email Address.
  - **Missing:** a search box; an edit action; the fields designation, alias, mobile and first/middle/last name; a master-detail layout; a per-user permissions view; a STUDENTS sub-tab; any role other than admin and super admin.
  - The `users` table has no designation, mobile or alias columns (`backend/app/Models/User.php:27-35` fillable: name, email, password, role, is_super_admin, is_active, company_id).
- **Naming:**

  | Ours | Superset |
  |---|---|
  | Page "Manage Admins" | "Users" |
  | Button "Add Admin" | "+ Add User" |
  | Modal "Add New Admin" | "Edit User" / Add User |
  | Role label "Super Admin" | "ACCOUNT ADMIN" |
  | Role label "Admin" | "ACCOUNT USER" |
  | Button "Delete" | "Delete this User" |
  | Field "Email Address" | "Email ID" |
  | Field "Name" | "Name" (first / middle / last) |

  Recommendation: rename the page and buttons to "Users" / "Add User"; renaming the role labels only matters if 2.5 is built.
- **Conflict check:** the user directory and its fields do not conflict with anything. **Priority: Medium.**

### 2.5 Staff roles: ACCOUNT ADMIN / ACCOUNT USER / FACULTY MEMBER (Edit User modal)
- **Superset:** the **Edit User** modal has these fields:
  - **Role\***, a dropdown with ACCOUNT ADMIN, ACCOUNT USER and FACULTY MEMBER;
  - **Name\***, as first, "Middle Name" and last;
  - **Alias**, "If set, User name alias will be used in studentapp communications like noticeboard.", placeholder "user name alias";
  - **Mobile\***, a country-code picker plus number;
  - **Email ID\***, read-only once created;
  - **Designation\***, free text such as "Assistant Professor", "Faculty", "Placement Officer", "Chairperson (CDC)" or "Student Admin".
  
  The buttons are Cancel and Edit User; required fields carry an asterisk. A FACULTY MEMBER card has a graduation-cap icon and no "Placement Cycle Level Access" section. Departmental placement coordinators (`tp_<dept>@…` accounts) are ACCOUNT USERs with the designation "Faculty".
- **Our status: NOT IMPLEMENTED.** `users.role` is the enum admin/company/student (`backend/database/migrations/2026_09_27_000001_add_student_role_and_is_active_to_users.php:16`) plus the boolean `is_super_admin` (`2026_05_01_000000_...:15`). Every admin route sits behind one `role:admin` gate (`backend/routes/api.php:74`), so every admin has full power. Super admin only adds admin management.
- **Naming:** ours "Super Admin" / "Admin" vs Superset "Account Admin" / "Account User"; Superset also has "Faculty Member".
- **Conflict check: NEEDS OWNER DECISION.** CDC_PORTAL_CONTEXT §2.3 fixes "three roles (plus super admin)" with "Admin is god: every capability is ultimately admin-controlled". A restricted staff tier (Account User / Faculty Member) adds roles and lowers some admins below "god". That is a product change the owner must approve (§10 "Ask the owner before any product-behaviour change"). **Priority: Medium** if approved; departmental faculty access is a real need at IIT ISM, since the `tp_<dept>` accounts exist in Superset today.

### 2.6 Per-user permissions (Access Control / Communication Control / Data Control)
- **Superset:** padlock-headed groups of collapsible panels on the user card. The panels shown, with the exact labels on screen:
  - **Global Access**, toggles (exact wording):
    - Faculty Member / Account User: "Can the user view Dashboards tab?", "Can the user view Reports tab?"; then, under the **Job Profiles** sub-heading: "Can the user access Progress Grid for Job Profile?", "Can the user view placement stats tab?", "Can the user send communications for Job Profile?".
    - Student user (STUDENTS tab), with a longer list:
      - add applicants to job profiles where the user is added as a collaborator;
      - remove applicants (same wording);
      - update status of job profiles (same);
      - publish list of offers of job profiles (same);
      - create custom events;
      - view Dashboards tab; view Companies tab; view Reports tab; view Documents tab;
      - view Placement Student Lists;
      - under **Job Profiles**: Mute Communications for Job Profile, access Progress Grid for Job Profile, download Excel lists for Job Profile, send communications for Job Profile.
    - Toggle colours: green = on, red = off.
  - **Placement Cycle Level Access** (Account User and Student; never expanded on screen).
  - **College Level Access:** per college, the checkboxes "View Students" (always on and disabled), "Invite Students", "Edit Student Profiles" and "Approve/Reject change requests".
  - **Department Level Access:** "Access Level" radios **Read / Read and Verify / Read and Write**, plus "Allow Access to the following courses", a checkbox tree of All Colleges › College › Department › sub-group (code) › course.
  - **Communication Control › Notice Board:** "Can View Notice Tab?" and "Can User Create Notice on Notice Board?".
  - **Data Control › Excel Templates:** "Can user create new Excel Templates?".
  - **Data Control › Inbound Job Posts:** not expanded.
  - **Account Admin:** "Account Admins have access to everything" and no panels.
- **Our status: NOT IMPLEMENTED.** There is no permission table or column. All `/admin/*` routes share `['auth:sanctum','active','role:admin']` (`backend/routes/api.php:74`); there is no per-programme or per-branch scoping of admin data; there is no notice board (no route or page; grep for "notice" finds none in routes or student pages); there are no user-defined Excel templates (`backend/app/Services/ExportService.php` has fixed column sets, D78).
  - The nearest analogues: our course tree corresponds to `ProgrammeCatalogue` (programme › branch, `backend/config/programmes.php`). "Read and Verify" corresponds to our resume verification (`AdminResumeController`) and profile edits. "Approve/Reject change requests" corresponds to our Branch Changes queue (`frontend/app/admin/branch-changes/page.jsx`, `AdminBranchChangeController`). These exist but are open to every admin.
- **Naming:**

  | Superset | Ours |
  |---|---|
  | "Progress Grid" | "Pipeline" tab (`frontend/app/admin/postings/[id]/page.jsx:29`) |
  | "placement stats tab" | "Placement Analytics" page (`frontend/app/admin/analytics/page.jsx:126`) |
  | "Dashboards tab" ("My Dashboards") | "Dashboard" |
  | "Reports tab" | no equivalent; our exports live on cycle/posting pages |
  | "Placement Student Lists" | none |
  | "Inbound Job Posts" | "JNF Reviews" / "INF Reviews" |
  | "change requests" | "Branch Changes" |

  Recommendation: none of these needs renaming unless the permission system is built. If it is, the toggle labels should use our own page names.
- **Conflict check: NEEDS OWNER DECISION**, for the same reason as 2.5 ("Admin is god"; only admin roles exist). It also overlaps 2.7a. **Priority: Medium** (if approved, build it together with 2.5).

### 2.7a Student users with admin-panel access ("STUDENTS" sub-tab of Users, chip "STUDENT")
- **Superset:** selected students (student placement coordinators) get logins to the admin panel with the same toggle-based permissions (add/remove applicants, update status, publish list of offers on job profiles where they are a collaborator, create custom events, view tabs, download Excel lists, send communications). In the video every toggle was off for the student shown.
- **Our status: NOT IMPLEMENTED.** A student account can only reach `/student/*` (`backend/routes/api.php:229`, `role:student`).
- **Conflict check: NEEDS OWNER DECISION. This directly conflicts with recorded rules:**
  - "Only the admin publishes to students" (§3 Shortlists);
  - "Attendance is marked by the admin only" (§3);
  - a student "can edit only personal email, phone, home state, LinkedIn, GitHub and photo" (§2.3);
  - "Admin is god".
  
  Do not build it without explicit owner approval. **Priority: Low.**

### 2.7b Public profile ("Make Visible to Employers" / "Disable Public Profile") and "CRM ADMIN"
- **Superset:** a per-staff-user button that makes the staff member's profile visible to employers. The CDC account shows "Disable Public Profile", meaning it is currently visible; other users show "Make Visible to Employers". The chip "CRM ADMIN" means the user administers Superset's CRM module (sidebar "CRM").
- **Our status: NOT IMPLEMENTED.** Recruiters see no CDC staff directory. We have no CRM; companies are handled by `AdminCompanyController` ("Companies").
- **Conflict check:** none recorded. **Priority: Low.**

### 2.8 Custom Fields
- **Superset:** CUSTOM FIELDS tab with three lists, each with "+ Add Custom Field", and three row icons: pencil edit, padlock (lock state; probably whether students can still edit the field) and trash.
  1. **Student Registration Form.** Fields shown, as label → type:

     | Label | Type |
     |---|---|
     | Aadhar Number | TEXT FIELD |
     | Do you belong to EWS-General | DROPDOWN : SINGLE |
     | Do you belong to PWD | DROPDOWN : SINGLE |
     | Degree of disability | TEXT FIELD |
     | Internship Company Name(s) | TEXT FIELD |
     | Internship Duration (Start Month, Year – End Month, Year) | TEXT FIELD |
     | Internship Job Role / Designation | TEXT FIELD |
     | Mode of Internship | DROPDOWN : SINGLE |
     | Paid or Unpaid Internship | DROPDOWN : SINGLE |
     | Monthly Stipend Amount (₹) | TEXT FIELD |
     | Internship Mentor Name | TEXT FIELD |
     | Internship Mentor Designation | TEXT FIELD |
     | Internship Mentor Official Email ID | TEXT FIELD |
     | Internship Mentor Contact Number | TEXT FIELD |
     | "I hereby declare that the information given by me in the superset is true, complete and correct … liable for disciplinary action." | DROPDOWN : SINGLE (a declaration as a dropdown) |

  2. **Job Submission Form For Companies:** extra fields on the company's job-post form (contents not seen).
  3. **Student Job Offer Fields for Account Users:** extra fields staff fill when recording a student's offer (contents not seen).
  
  The types seen are TEXT FIELD and DROPDOWN : SINGLE; other types may exist in the add dialog, which was not opened.
- **Our status: NOT IMPLEMENTED.** The closest pieces are partial analogues only:
  - Student profile fields are fixed columns (`backend/database/migrations/2026_09_21_162407_create_student_profiles_table.php`). Among them are `category` (string 30; it holds the social category GEN/OBC/SC/ST/EWS, per `Phase2DemoSeeder.php:172`) and `pwd` (boolean), which cover two of Superset's custom fields.
  - Per-drive **application questions** (`posting_questions`, qtype text/mcq_single/mcq_multi; `backend/database/migrations/2026_09_27_000009_create_posting_questions_table.php`; admin "Questions" tab) are per job profile, not per form.
  - The JNF/INF structure is fixed in `components/forms/jnfformpro.tsx` and `infformpro.tsx` (stored as `form_data` JSON).
  - Offers have fixed columns (`2026_09_27_000014_create_offers_table.php`: offer_type, ctc_annual, stipend_monthly, currency).
- **Naming:** our "Questions" tab (per drive) and Superset's "Custom Fields" (per form) are different things, so do not rename.
- **Conflict check: NEEDS OWNER DECISION** for the Student Registration Form part. Our rule is that a student can edit only personal email, phone, home state, LinkedIn, GitHub and photo (§2.3), and student-filled custom fields would widen that. Collecting **Aadhaar** numbers also raises a data-protection question. The company-form and offer-form parts conflict with nothing recorded. **Priority: Medium** (company/offer fields: Low-Medium).

### 2.9 Enrollment Configuration: Mandatory Documents
- **Superset:** "Mandatory Documents (i)", with "Check the programs for which uploading the document is mandatory for the students.(None is mandatory by default)". The checkboxes are Current/Ongoing course, Under Graduate, Post Graduate, Class 10th, Class 12th and Diploma (all ticked for IIT ISM), followed by **Save**. Students must upload a marksheet or document for each ticked education level before enrolment completes.
- **Our status: NOT IMPLEMENTED.** Students upload only resumes (`resumes` table) and a photo (D45). A grep for marksheet, diploma or transcript in the backend and the student/admin pages finds nothing. Academic values are admin-entered or synced and locked (§3 Students).
- **Naming:** Superset spells the route "enrolment" and the tab "ENROLLMENT". We use "enrolment" in code (`cycle_enrollments`) and "Enrol" in the UI; no change needed.
- **Conflict check: NEEDS OWNER DECISION.** Academic data is meant to come from the institute database (§3 "CGPA and backlogs will auto-sync"), so student-uploaded proof documents are a new student obligation. **Priority: Low.**

### 2.10 Resume Tags
- **Superset:** "Resume Tags (i)", "+ Add Resume Tag", empty state "No resume tags configured." The help text reads "Here you can configure resume tags that are used for specifying which resume is applicable for applying to a job profile." Admins define the tags; students tag their resumes; a job profile can require a tag.
- **Our status: PARTIAL.** Students label each resume with free text (`resumes.label`, string 60, `backend/database/migrations/2026_09_27_000007_create_resumes_table.php`; validated `required|string|max:60` in `StudentResumeController.php:40,114`), up to 8 per student. **Missing:** an admin-defined tag list, tagging resumes from that list, and a per-drive "allowed resume tag" restriction on apply.
- **Naming:** ours "label" ("My Resumes" page) vs Superset "Resume Tag". Keep "label" for the free name; add "Tag" only if this is built.
- **Conflict check:** none recorded (it would add an apply-time restriction, which the owner should confirm). **Priority: Low.**

### 2.11 Student Categories for Placement
- **Superset:** "Student Categories for Placement (i)". Cards have a title, the description "This category will be assigned to students having minor in <X>" (or "major in CSE"), and pencil and trash icons. The categories seen are:
  - Minor in Data Science
  - Double Major in CSE
  - Minor in Computational Fluid Dynamics
  - Minor in Electrical Technology
  - Minor in Embedded System Design
  - Minor in Environmental Management
  - Minor in Exploration Geology (partly hidden)
  - Minor in Exploration Geophysics
  - Minor in Finance
  - Minor in High Energy Physics
  - Minor in Infrastructure Engineering
  - Minor in Manufacturing
  - Minor in Marketing
  - more below
  
  The side panel "What are student categories?" says: "Student categories are used to attribute or tag students if they belong to a particular group after some college specific evaluation. These categories can be used as part of eligibility criteria in placement policy. You can assign allowed student categories for one or more placement categories." It also has an "IMPORTANT NOTE!": "For your account, multiple student categories can be allotted to single student and hence you need not create combinations of your own. We suggest creating these categories only in the presence of your Customer Success Manager (from Superset)…" No add button is visible.
- **Our status: NOT IMPLEMENTED.** `backend/app/Services/EligibilityService.php` has no category criterion. Our only "Category" field is the student's social category (`student_profiles.category`, label "Category" in `frontend/components/admin/studentformdialog.jsx:163`, `frontend/app/student/profile/page.jsx:48`, `AdminStudentController.php:49`), which is not used in eligibility. Double majors are currently handled as a programme/branch (`config/programmes.php:18` "B.Tech … Double Major …") and through branch-change requests (§3 Students).
- **Naming clash:** our "Category" (GEN/OBC/SC/ST/EWS) would be confused with Superset's "Student Categories". Recommend renaming our label to **"Social Category"** or **"Reservation Category"**, and using "Student Category" only for this feature.
- **Conflict check:** no recorded decision forbids it. It overlaps the owner's choice to treat double majors through branch change, so the owner should choose: tags (multi-valued, eligibility filter) versus branch. **Priority: Medium** (minors are a real eligibility input at IIT ISM).

### 2.12 Reminder Rules
- **Superset:** "Reminder Rules (i)" with two sections:
  - **Reminders for Job Application Deadlines:** "Before **2** hours" with a green alarm icon and a grey padlock (the system default, locked), then "Before **2** hours" with a pencil (an editable custom reminder), then "+ Add Reminder".
  - **Reminders for Events:** "Before **2** hours" (alarm + padlock), then "+ Add Reminder".
  
  The side panel "What are Reminder Rules?" says: "Superset sends reminders to students for important events at default intervals. However, if you wish to add custom reminders, you can add them here. Please note that you can add up to two reminders for different channels of communication." A warning triangle adds: "There will be additional charges for usage of SMS and Emails."
- **Our status: NOT IMPLEMENTED, deliberately.**
- **Conflict check: NEEDS OWNER DECISION. This conflicts** with §3 Mail, "Emails … are sent instantly, with no deadline reminders and no digest", and with §11, "Excluded unless the owner asks: deadline reminder mails". Event reminders are also absent; events are "announce only" (§3 Events). Do not build silently. **Priority: Low.**

### 2.13 Fee Payments
- **Superset:** "Fee Payments" with two rows, **Record Payments** and **Check Payments** (contents not shown). It records student placement or registration fees.
- **Our status: NOT IMPLEMENTED.** There are no payment tables or routes.
- **Conflict check:** no recorded decision. It is a new financial process, so **NEEDS OWNER DECISION** on whether CDC collects any fee through the portal. **Priority: Low.**

### 2.14 Student Profile Section Tags
- **Superset:** "Student Profile Section Tags (i)", "+ Add Tag", empty state "No student profile tags configured." The side panel says tags "can be attached to a section of the students profile to enable tracking and impose restrictions" (for example, locking a section). Not used by IIT ISM (empty).
- **Our status: NOT IMPLEMENTED.** Our profile lock is a fixed rule: academic fields locked, a few personal fields editable (`backend/app/Http/Controllers/StudentProfileController.php:31-34`).
- **Conflict check:** none. **Priority: Low** (CDC has not used it in Superset).

### 2.15 Student Exam Master
- **Superset:** "Student Exam Master (i)", "+ Add Exam", empty state "No student exams added." It is presumably a master list of competitive exams (GATE, CAT, GRE…) that students can record on their profile. Not used by IIT ISM (empty).
- **Our status: NOT IMPLEMENTED.**
- **Conflict check:** none. **Priority: Low.**

### 2.16 NOC Templates (No Objection Certificate Templates)
- **Superset:** "No Objection Certificate Templates", "These are the certificates your institution issues. They are maintained by Superset — to change the wording, please contact support." The table has the columns Name \| Maintained For \| Last Updated; one row: "No Objection Certificate" \| chip "Superset default" \| "14 Aug 2026" \| "View". Students request NOCs (typically for off-campus internships) and the institution issues them from the template.
- **Our status: NOT IMPLEMENTED.** There is no certificate generation; the only PDFs are policy documents and resumes.
- **Conflict check:** none recorded; this is a new student-facing flow, so it needs owner scoping. **Priority: Low-Medium** (NOCs for internships are common at IIT ISM; ask the owner).

### 2.17 Communication Groups
- **Superset:** "Communication Groups", "+ Add Group", empty state "No communication groups configured." The side panel says these are "named groups of account users (e.g. "Marketing Team", "Faculty Core") to quickly notify them together from any job profile notification." The list is of **staff**, not students.
- **Our status: NOT IMPLEMENTED.** Staff notifications go to every active admin (E10 to "every active admin", D71). There are no named staff groups.
- **Conflict check:** none. It is only useful once multiple staff roles exist (2.5). **Priority: Low.**

### 2.18 Data Addition Requests
- **Superset:** "Data Addition Requests", "+ New Request", "Request a value that is missing from the master list. Once approved, it can be selected wherever that data is used.", empty state "No data addition requests raised yet." This is the institution asking Superset to add a master-data value, such as a course.
- **Our status: IMPLEMENTED (differently, not needed).** Our admin adds missing values directly: custom branches through "Branch Manager" (`POST /admin/programme-branches`, `backend/routes/api.php:165-168`; `frontend/app/admin/programme-branches/page.tsx`). No approval step is needed because we own the master list.
- **Naming:** ours "Branch Manager" / "Programme Branch Manager" vs Superset "Data Addition Requests" (a different workflow). No rename.
- **Conflict check:** none.

### 2.19 Invitation Configuration: Formal Letter Head
- **Superset:** "Formal Letter Head (i)", an empty-state illustration, and "You have not uploaded any letter head yet. Click on the image above to upload a letter head. The letter head must be of be 800px × 155px for the best fit". It is used on formal invitation letters/emails (most likely to companies: placement invitation letters).
- **Our status: NOT IMPLEMENTED.** The email layout header is text only (`backend/resources/views/emails/layouts/portal.blade.php:13-15`). We send no formal invitation letters to companies; recruiters self-register (§2.1).
- **Naming:** n/a.
- **Conflict check:** none. **Priority: Low.**

### 2.20 Placements (placement cycles) list page (shown at 00:00, context only)
- **Superset:** sidebar "Placements", page "Placements". It has an explainer panel, "+ Add placement process", "Search Placements", current cycles versus a "Previous Placements" section, a **[DRAFT]** state, a "Month YYYY - Month YYYY" range, a ⚙ settings gear per cycle and a "Recently Visited" column. The sidebar also has "RECENTLY VISITED PLACEMENTS" and "RECENT JOB PROFILES".
- **Our status: PARTIAL.** `frontend/app/admin/placement-cycles/page.jsx` has the title "Placement Cycles" (line 217) and the button "Add Placement Cycle" (lines 225-226, 267-268). Each card shows the name, a Full Time/Internship chip and an Open/Closed chip (lines 288-299), a "start → end" date range, the counts Students / Postings / Offers, and "Close cycle". **Missing:** search; the current/previous split; a draft status (`placement_cycles.status` is the enum open/closed only, `backend/database/migrations/2026_09_27_000004_create_placement_cycles_table.php`); recently visited; recent job profiles.
- **Naming:**

  | Ours | Superset |
  |---|---|
  | nav "Cycles" (`adminshell.tsx:48`), page "Placement Cycles" | "Placements" |
  | "Add Placement Cycle" | "+ Add placement process" |
  | card stat "Postings" | "Job Profiles" |

  Our nav was shortened to "Cycles" on purpose for width (D34), so keep the nav label and consider only the page title.
- **Conflict check:** a "Draft" cycle status does not conflict. **Priority: Low.** It is probably covered in a dedicated placements video.

### 2.21 Global top bar: "Search students", downloads, apps grid, account menu
- **Superset:** a global "Search students" box on every admin page, a downloads icon (exports centre), a bell, an apps grid, and an account dropdown (Account / Settings / Logout).
- **Our status: PARTIAL.** We have the bell and "Sign Out" in the admin top bar (`adminshell.tsx`, D98), and student search exists only on the Students page ("Search roll no, name or email", `frontend/app/admin/students/page.jsx:149`). **Missing:** global student search in the top bar, a downloads centre, and an account dropdown.
- **Naming:** ours "Sign Out" vs Superset "Logout". Optional rename.
- **Conflict check:** none. **Priority: Medium** for global student search (high daily value); Low for the rest.

---

## 3. Gap summary

| Superset name | What to build | Priority | Conflicts |
|---|---|---|---|
| Admin (settings hub with tabs) | One Settings page with a left tab list hosting mail mode, Users, Branch Manager, Account logo and the future config tabs | Medium | None |
| Users (USERS tab) | Staff directory with search, master-detail card, Edit User (first/middle/last name, designation, mobile, alias), "Delete this User" | Medium | None |
| Role: Account Admin / Account User / Faculty Member | Restricted staff roles | Medium | **NEEDS OWNER DECISION**: "Admin is god", only admin + super admin exist |
| Access Control (Global / Placement Cycle / College / Department Level Access), Notice Board, Excel Templates, Inbound Job Posts permissions | Per-user permission toggles; department/course scoping with Read / Read and Verify / Read and Write | Medium | **NEEDS OWNER DECISION** (same as above) |
| STUDENTS tab (student users with admin permissions) | Student coordinators with collaborator rights | Low | **NEEDS OWNER DECISION**: conflicts with "only the admin publishes", "attendance by admin only", student edit limits |
| Make Visible to Employers / Disable Public Profile | Staff public profile shown to recruiters | Low | None recorded |
| Account Logo | Uploadable institute logo used in the shells and emails | Low | None |
| College | Multi-college support | Low (skip) | None |
| Formal Letter Head | Letterhead image (800×155) for formal letters/emails | Low | None |
| Custom Fields: Student Registration Form | Admin-defined extra profile fields (text, single dropdown), lockable | Medium | **NEEDS OWNER DECISION**: widens what students can edit; Aadhaar privacy |
| Custom Fields: Job Submission Form For Companies | Admin-defined extra JNF/INF fields | Low-Medium | None |
| Custom Fields: Student Job Offer Fields for Account Users | Extra fields on the results console/offer | Low-Medium | None |
| Mandatory Documents (Enrollment Configuration) | Required marksheet uploads per education level | Low | **NEEDS OWNER DECISION**: academics come from the institute DB |
| Resume Tags | Admin tag list, tag resumes, restrict a drive to a tag (PARTIAL: free labels exist) | Low | Confirm with owner (new apply restriction) |
| Student Categories for Placement | Multi-valued student tags (minors, double major) usable in eligibility | Medium | Overlaps the branch-change approach for double majors; owner to choose |
| Reminder Rules | Deadline/event reminders | Low | **NEEDS OWNER DECISION**: conflicts with "no deadline reminders", events "announce only" |
| Fee Payments | Record/Check Payments | Low | **NEEDS OWNER DECISION** (new financial process) |
| Student Profile Section Tags | Tags on profile sections to track/restrict | Low | None |
| Student Exam Master | Master list of exams | Low | None |
| NOC Templates | NOC certificate template + issuance | Low-Medium | Owner scoping (new student flow) |
| Communication Groups | Named staff groups for notifications | Low | Depends on staff roles (owner) |
| Placements page extras | Search, current vs previous, Draft status, recently visited | Low | None |
| Global "Search students", downloads, account menu | Top-bar global student search, downloads centre, account dropdown | Medium (search) / Low | None |

---

## 4. Rename list

| Where it appears in our UI/code | Our current name | Superset name |
|---|---|---|
| Admin drawer item `frontend/components/admin/adminshell.tsx:89`; page title `frontend/app/admin/manage-admins/page.tsx:89` | Manage Admins | Users |
| Button `manage-admins/page.tsx:97` | Add Admin | + Add User |
| Modal title `manage-admins/addadminmodal.tsx:69` | Add New Admin | Edit User / Add User |
| Field label `addadminmodal.tsx:90` | Email Address | Email ID |
| Role labels `manage-admins/page.tsx:131,135` | Super Admin / Admin | ACCOUNT ADMIN / ACCOUNT USER |
| Delete button `manage-admins/page.tsx:147` | Delete | Delete this User |
| Settings page title `frontend/app/admin/settings/page.jsx:55` | Portal Settings | Admin (reached via "Settings") |
| Student field label `frontend/components/admin/studentformdialog.jsx:163`, `frontend/app/student/profile/page.jsx:48`, `backend/app/Http/Controllers/AdminStudentController.php:49`, export header in `ExportService.php` | Category | Not a Superset rename. Rename ours to **"Social Category"** so it is not confused with Superset's "Student Categories" |
| Cycles page title `frontend/app/admin/placement-cycles/page.jsx:217` | Placement Cycles | Placements |
| Cycles nav `adminshell.tsx:48` | Cycles | Placements (keep "Cycles" for width, D34) |
| Cycles button `placement-cycles/page.jsx:226,268` | Add Placement Cycle | + Add placement process |
| Cycle card stat `placement-cycles/page.jsx:310` | Postings | Job Profiles |
| Admin nav `adminshell.tsx:49`; page title `frontend/app/admin/postings/page.jsx:66` | Postings / Job Postings | Job Profiles (the student side already says "Job Profiles", `studentshell.jsx:44`) |
| Company nav `frontend/components/company/companyshell.tsx:44` | Drives | Job Profiles |
| Posting tab `frontend/app/admin/postings/[id]/page.jsx:29` | Pipeline | Progress Grid |
| Analytics page `frontend/app/admin/analytics/page.jsx:126`; drawer `adminshell.tsx:56` | Placement Analytics / Analytics | placement stats / My Dashboards |
| Admin nav `adminshell.tsx:57-58` | JNF Reviews / INF Reviews | Inbound Job Posts |
| Drawer `adminshell.tsx:53` | Branch Changes | (Approve/Reject) change requests |
| Drawer `adminshell.tsx:63`; page `frontend/app/admin/programme-branches/page.tsx:179` | Branch Manager / Programme Branch Manager | none (Superset routes this through Data Addition Requests; no rename) |
| Resume name field (`resumes.label`, My Resumes page) | label | Resume Tag (only if 2.10 is built; otherwise keep) |
| Admin top bar button `adminshell.tsx:294` | Sign Out | Logout |

---

## 5. Uncertain

- **Notice Board toggle states** (01:06) are cut off by horizontal scroll, so on/off is unknown. The same applies to the Global Access toggle states at 01:12–01:42; only the 00:54 frame shows them (Dashboards on, Reports on, Progress Grid on, placement stats on, send communications off).
- **Inbound Job Posts** (Data Control) and **Placement Cycle Level Access** were never expanded; their options are unknown.
- **Kebab menu (⋮)** next to "+ Add User" was never opened.
- **Add User** and **Add Custom Field** dialogs were not opened. The full list of custom-field types beyond "TEXT FIELD" and "DROPDOWN : SINGLE" is unknown, as are the meaning of the padlock icon on custom-field rows (assumed: locks the field from student edits) and the dropdown options of the EWS/PWD/Mode/Paid fields.
- **Job Submission Form For Companies** and **Student Job Offer Fields for Account Users**: at 02:16 they showed only "+ Add Custom Field", but that frame was mid-load, so whether IIT ISM has fields there is unknown.
- **Student Categories:** whether there is an add button (none visible; the note suggests Superset's Customer Success Manager creates them), and the full list below "Minor in Marketing".
- **Reminder Rules:** which channel each "Before 2 hours" row uses (email/SMS/push), and the difference between the two rows other than locked versus editable.
- **Fee Payments:** what Record Payments and Check Payments contain.
- **Student Profile Section Tags** and **Student Exam Master:** purpose inferred from the side text and names only; both are empty.
- **NOC Templates:** who requests NOCs and the flow; only the template list is shown.
- **Letter head use:** "Invitation Configuration" implies invitation letters (probably to companies), but the video does not show where it is used.
- **Account vs Settings:** at 00:08 the cursor hovers near "Account"; it is unclear whether the presenter clicked "Account" or "Settings". Both appear to lead to the same Admin area (`/#/admin/accounts`).
- **Sidebar items below "CRM"** were never scrolled into view.
- **"[DRAFT]" cycle** shows the range "March 2026 - February 2026" (end before start). This is probably a data-entry error in their account, not a feature.
