# 03 — "How to verify a student profile" (Superset admin training video, 296 s)

Source frames: `kf/03_verify_profile/` (52 frames, t000m00s to t004m38s). Every frame was read.
Our code base checked: `CDC/backend` (Laravel) and `CDC/frontend` (Next.js) on branch `main` (HEAD `27ed406`).

Short version: Superset lets **students type in their own course and education details and upload marksheets**. The CDC then **verifies each section** (Overview, Address, Education, Additional Info, Course, and each education entry), **accepts or rejects pending edits**, gathers section-level rejections into a **"Resubmission List"**, and sends them back in one go with **"Ask Resubmission"**. It can also **"Mark as verified"** for the whole profile. We have nothing like this. In our portal the academic record is CDC-entered and locked for students. The only CDC verification we have is per resume (`/admin/resumes`), plus approval of branch-change requests (`/admin/branch-changes`). Most of this video conflicts with the recorded owner decision that academic fields are CDC-controlled and will auto-sync from institute records, so most items below are marked **NEEDS OWNER DECISION**.

---

## 1. Video walkthrough

Superset's admin shell, shown in every frame (to rebuild the screens):
- Purple top banner: "The new Placements app is here. Try it now" with a close X.
- Left sidebar (dark):
  - logo "superset";
  - Home, My Dashboards;
  - **JOBS**: Companies, Inbound Job Posts, Placements, Job Alerts (with a sparkle icon);
  - **RELATIONSHIPS**: Students (active, highlighted blue), CRM;
  - **ENGAGEMENT**: Notices, Surveys, Calendar;
  - **ADMIN**: Documents, TalentLens Rubrics, Admin;
  - **REPORTS** (collapsible chevron): Launchpad, New Placements App.
- Top bar:
  - college name "Indian Institute of Technology Indian School of Mines Dhanbad";
  - global "Search students" box;
  - download icon, bell, app-grid icon;
  - account name "Career Development Centre IIT ISM Dhanbad" with a "CD" avatar.

| Time | What happens on screen |
|---|---|
| 0:00 | **Students landing page.** URL `app.joinsuperset.com/#/students`. Page header is "Colleges and Students". At the top right of the header are three icon buttons: a person/pin icon with a dot (purpose unclear), "Invitations" with an envelope icon, and an upload icon. A blue info banner reads: "You have profile update requests pending for approval. **Click to view requests.**" (the second sentence is a link). Below it is a search box with the placeholder "Search student with name, roll number, email, or mobile number...". Then comes the college card: logo, "Indian Institute of Technology Indian School of Mines Dhanbad", "13217 students registered", "total 12848 students invited". |
| 0:18 | The presenter types `25mb0041` into the search box. The result line reads "Found 1 student matching '25mb0041'". The table has the columns **Name, College, Identification Number, Mobile, Verified**. The row is Harsh Suri, the institute name, 25MB0041, 8964005644, and an **orange warning-triangle icon** in Verified (meaning not verified). The name is a blue link. |
| 0:34 | **Student profile page.** URL `/#/a/colleges/IITISMD_63/students/<uuid>`. The left card has a decorative header, a round photo, "Harsh Suri", "**2027 Passout Batch \| 25MB0041**", "**Superset Id: 7122209**", "3rd Semester, MBA (Semester)" and "Department of Management Studies". Below that are big numbers with captions: **8.54 CGPA**, **4 Applications**, **1 Offers**. The right side has the tabs **OVERVIEW \| ABOUT \| ACADEMIC DETAILS \| PROFILE \| RESUMES & DOCUMENTS**, with OVERVIEW active. The Overview tab shows:<br>- the section "Placements" with an (i) icon;<br>- the links "Download Placement Report" and "Download Eligibility Report", each with a document icon;<br>- the text "Harsh has participated in the following placement cycles".<br>Each cycle row has a checkbox-like icon, the cycle name with an external-link icon, a status, two expanders ("Show Applications", "Show Attendance") and three icon buttons on the right: a green trophy (pencil on the placed row), a gear and a list. The rows are:<br>- "Superset Placements 2020-2024": **Enrolled**;<br>- "Internship Placement for 2025-26 (2027 Batch)": **Placed** (green) "( Intern at Savera National Trust )";<br>- "Full-Time Placement for 2026-27 (2027 Pass Out Batch)": **Enrolled**.<br>After the cycles come the section "Events" with the expander "Show Event Attendance", then the section "Notes" with a pencil, "... Write notes about this student", and a comment input next to a CD avatar. |
| 0:36 | Once the page has loaded, two buttons appear at the top right of the profile: **"Mark as verified"** (green, check-circle icon) and **"Ask Resubmission"** (red outline, x-circle icon). The tab bar moves down to make room. |
| 1:08 | The **ABOUT** tab is open, at the "Overview" section, with an "Edit" link (pencil) at the right. Its rows are:<br>- Name: Harsh Suri;<br>- Contact No.: +91 8964005644, with a green check-circle (verified);<br>- Email: 25MB0041@iitism.ac.in, with a green check;<br>- Personal Email: harshsuri…@gmail.com, with a green check;<br>- Date of Birth: "18 October 2003 (22 years)";<br>- Gender: Male;<br>- Category: General;<br>- Student Categories: (empty);<br>- WorkEx Month Override: (empty).<br>At the bottom right of the section is a green badge "**Verified by Career Development Centre IIT ISM Dhanbad**" and a **"Reject"** button with an x icon. The "Superset Id" value 7122209 in the left card is highlighted (the presenter selected it). |
| 1:10 | The presenter scrolls to the bottom of ABOUT. These rows belong to a custom-question block, probably "Additional Info":<br>- "Paid or Unpaid Internship": Paid;<br>- "Monthly Stipend Amount (₹)": 20000;<br>- "Internship Mentor Name": Chetan Almekar;<br>- "Internship Mentor Designation": Co-Founder;<br>- "Internship Mentor Official Email ID": (email link);<br>- "Internship Mentor Contact Number": 9923630010;<br>- a long declaration, "I hereby declare that the information given by me in the superset is true, complete and correct … liable for disciplinary action.": Yes.<br>Under it is the section **"Important actions"**:<br>- the row "Unfreeze student profile" with the button **"Freeze Student"** (red outline);<br>- the row "Delete this student" with the button **"Delete Student"** (solid red). |
| 1:30 | The presenter scrolls back up through ABOUT:<br>- the Overview section's Verified badge and Reject button;<br>- the section **"Address"**, with "Permanent Address" and "Current Address" (multi-line);<br>- the section **"Education"** (Edit link), with "Total Student Education Gap: 0";<br>- the section **"Additional Info"** (Edit link), with "What is your father name?", "Aadhar Number", "Do you belong to EWS-General: No", and a cut-off "Do you belong to PWD…" row. |
| 1:32 | The **ACADEMIC DETAILS** tab is open, at the section "Education" with **"+ Add Education"** at the right. There are three cards. Each has the institution name, the board or university, the degree and specialisation, the year range, and on the right a big number with a caption (CGPA and/or Score):<br>- "Gyan Ganga Institute of Technology and Science", "Rajiv Gandhi Proudyogiki Vishwavidyalaya", "Bachelor of Technology (Hons.), Computer Science & Business Systems", 2021 — 2025, **8.61 /10 CGPA**, **86.1% Score**;<br>- "St. Gabriel's Senior Secondary School", CBSE, 12th, 2020 — 2021, **89.16% Score**;<br>- "St. Gabriel's Senior Secondary School", CBSE, 10th, 2018 — 2019, **95.33% Score**.<br>Each card has the links "Edit" and "Show Marksheet" (eye icon), and at the bottom right "Reject" plus the green "Verified by Career Development Centre IIT ISM Dhanbad" badge. A small circular icon sits at the top right of each card. |
| 1:38 | The presenter clicks "Show Marksheet" on 10th. A **left-side drawer** opens over the sidebar with the title "Harsh Suri", the subtitle "**Attachment for Education : 10th**", a close X, and an embedded PDF viewer (sidebar toggle, search, page "1 of 1", zoom − / +, annotation tools, ≫). It shows a DigiLocker-verified CBSE marksheet. |
| 1:44–1:46 | The presenter closes the drawer and opens "Show Marksheet" on 12th: "Attachment for Education : 12th", the CBSE Senior School Certificate (DigiLocker Verified). |
| 1:50–2:20 | The presenter closes the drawer and opens "Show Marksheet" on the B.Tech card: "Attachment for Education : Bachelor of Technology (Hons.)". For about one frame the old 12th PDF is still showing, then an **image** of a provisional degree certificate (phone photo) appears. The image viewer has **round "+" and "−" zoom buttons** at the bottom right. The presenter zooms in and pans to read the CGPA line ("…secured Cumulative Grade Point Average (CGPA) of 8.61 on a 10 point scale … First Division With Honours") and the registrar's signature. This checks the typed value 8.61 against the proof. |
| 2:22 | The presenter hovers over "Reject" on the B.Tech card. Tooltip: **"Ask Re-Submission"**. |
| 2:26 | **Reject modal** (title "Reject"): "Please provide the reason for rejecting student education details. This will help the student rectify the details and re-submit*" (required). The textarea placeholder is "Mention the reason of rejection so that student can resolve the issues and resubmit." The buttons are **"Add to Resubmission List"** (light red, apparently disabled until text is entered) and "Cancel". |
| 2:40 | After the presenter adds it: a yellow strip at the top of the profile says "You have un-submitted changes. Click **Ask Resubmission** to submit. **View Details**". The "Ask Resubmission" button now has a red **count badge "1"**. "Mark as verified" turns pale (it looks disabled). The B.Tech card's Reject button now looks pressed or outlined. |
| 2:50–2:52 | The presenter scrolls to the top of ACADEMIC DETAILS. There is a section **"Course"** above "Education". The orange note "**There are changes pending review for this course**" sits above the MBA card, which shows:<br>- "MBA", "MBA (Semester)", "Branch: General", "Department: Department of Management Studies", "2027 Passout Batch \| 25MB0041";<br>- new dates "Jun 2025 — May 2027", with the old "~~Jul 2025 — Jun 2027~~" struck through below;<br>- 8.54 CGPA, plus a struck-through "~~0~~" above 85.4% Score (old value against new).<br>The links are "Edit", "Show Term-wise Details" (double-chevron) and "Show Marksheet". The buttons are **"Reject"** (outline), **"Reject Changes"** (solid red) and **"Accept Changes"** (solid green). |
| 3:00–3:04 | The presenter clicks Edit on the course. The **"Update Course Details"** modal opens with these fields:<br>- **Course Group*** (hint "Select your Course Group"; dropdown, initially "Select an option");<br>- **Batch*** (hint "Select your batch. Your batch is usually the year you are expected to complete your current course."; value "2027 Passout Batch");<br>- **Current Semester*** (dropdown, value 3);<br>- **Score*** 8.54, with a "CGPA" unit addon, next to **Percentage Equivalent*** 85.4, with a "%" addon;<br>- **Course Start Date*** "June 2025" and **Course End Date*** "May 2027" (month pickers with calendar buttons; hint under end date "Please enter expected graduation date");<br>- **Lateral Entry**, a checkbox "I am a lateral entry student in this course.";<br>- **Institute Roll Number / University ID Number / USN*** 25MB0041;<br>- **Highlights/Notes** (hint "You can mention your class/department/university ranks or other highlights, if any");<br>- a **Performance** grid with the columns CGPA, SGPA and "Backlog Details" (Total, Ongoing), and the rows "Semester 1" and "Semester 2" (CGPA/SGPA filled with about 8.5, backlog cells empty);<br>- **Total Backlog details**, a checkbox "I have backlog(s)" with an orange hint "(Check this box if you have any past or ongoing backlog)";<br>- **Attendance in Training Sessions** [%] and **Attendance in Classroom** [%].<br>The buttons are Cancel and Save. |
| 3:10 | The presenter picks Course Group = "**Single Degree**". A **Course*** field appears with a red hint, "Choose your course carefully. If you have a specialization in course, select the course with corresponding specialization", and the placeholder "Select a course you are enrolled in". It is a searchable dropdown. Examples: "B.Tech in Mathematics & Computing , Department of Engineering", "B.Tech in Electronics & Communication Engineering , Department of Engineering", "B.Tech in Minerals & Metallurgical Engineering …", "B.Tech in Mechanical Engineering - Mining Machinery Engineering, …", "B.Tech in Fuel, Minerals & Metallurgical Engineering - Mineral Engineering, …", "B.Tech in Engineering Physics …", "B.Tech in Mining Engineering …", "B.Tech in Geological Engineering …". A "+ Add Term" link is visible further down. |
| 3:12 | The presenter types "mba". The options are "MBA (Semester) in Business Analytics , Department of Management Studies" and "MBA (Semester) in MBA , Department of Management Studies". While the course is unset, Score shows only a "%" unit. |
| 3:14 | The presenter selects "MBA (Semester) in MBA , Department of Management Studies". **Current Semester resets to "Select current..." with a red border** (it is required again). Score is back to 8.54 CGPA and 85.4 %. |
| 3:20–3:24 | The presenter scrolls down and presses **Save**. The modal shows a red error box: "**There are changes pending for approval. Please approve or reject them first**". The admin cannot edit a record while the student's change to it is pending. |
| 3:30–3:48 | The presenter cancels the modal and opens "Show Marksheet" on the course. The drawer is titled "Harsh Suri" / "**Document for MBA**". For a moment it still shows the old degree-certificate image, then a 3-page PDF: the IIT (ISM) "MIS Registration Slip" (admission number, course, branch, photo, admission details, personal details, parent details), a notes page, and a "Hostel Allotment Form". The presenter pages through all 3 pages. (So the "marksheet" slot accepts any document the student uploads.) |
| 3:50–3:54 | The presenter clicks **"Reject Changes"**. A modal opens: "Reject changes for **MBA (Semester), General, Department of Management Studies**", with the orange sub-text "Student will receive an email about reject along with notes entered below". It has a **Notes** textarea (placeholder "Specify reasons to reject the changes") with the counter "1000 characters left", and the buttons Cancel and **Reject Changes** (disabled until notes are typed). |
| 4:10 | After the rejection, the course card has gone back to the **last verified values**: "Jul 2025 — Jun 2027", CGPA **NA**, Score **NA**. The "pending review" note is gone, and the card shows "Reject" plus the green "Verified by Career Development Centre IIT ISM Dhanbad" badge again. The presenter then clicks "Reject" on the course. |
| 4:12 | Reject modal: "Please provide the reason for rejecting student **course** details. This will help the student rectify the details and re-submit*", with the same placeholder and the buttons "Add to Resubmission List" and Cancel. |
| 4:22 | The "Ask Resubmission" badge now reads **2**. The yellow "un-submitted changes" strip is still there. |
| 4:26 | The presenter clicks **"Ask Resubmission"**. A modal opens (titled "Reject"): "**You are submitting the following sections for resubmission**". It lists:<br>- "Changes required in **Education**": 1, "Kindly upload your Graduation marksheet in pdf format";<br>- "Changes required in **Course**": 2, "Kindly upload the current semester marksheet".<br>Below the list: "Please provide the reason for rejecting student details. This will help the student rectify the details and re-submit*", with a textarea and the same placeholder. The buttons are **"Clear all"** (blue, left), **"Add to Resubmission List"** (red) and Cancel. |
| 4:36 | After submission, the strip turns orange-red with an error icon: "**Student profile has been rejected. Waiting for re-submission** View Details". "Ask Resubmission" is now solid red with no badge. "Mark as verified" is active again (solid green). |
| 4:38 | Toast at the top right (green): "**Success!** Request sent to student for modifications." The Course section now shows:<br>- a red-bordered strip "**This section needs changes**";<br>- a comment bubble: CD avatar, "Career Development Centre IIT ISM Dhanbad", "wrote a few seconds ago", and the quote "Kindly upload the current semester marksheet";<br>- the card itself has a light-red tint;<br>- at the bottom right, "Reject" and "**Confirm Correctness**" (check icon) instead of the Verified badge.<br>The left card now says "**1st Semester**, MBA (Semester)" and the CGPA number is blank, because the reverted course record had no CGPA. The video ends here. |

The PROFILE and RESUMES & DOCUMENTS tabs were never opened.

---

## 2. Superset features shown

Our evidence paths are relative to `/Users/admin/Desktop/CDC-main/CDC/`.

### F1. Student search with a verification column
- **Superset name and location:** Students (sidebar, RELATIONSHIPS) → "Colleges and Students" page → search box, URL `/#/students`.
- **What it does:**
  - Free-text search across name, roll number, email and mobile number. The placeholder is "Search student with name, roll number, email, or mobile number...".
  - The result count line reads "Found N student matching '<term>'".
  - The result columns are **Name** (link to the profile), **College**, **Identification Number**, **Mobile** and **Verified** (an icon: an orange warning triangle means not verified; the verified icon was not shown).
  - The page also has the college card ("N students registered", "total N students invited") and header actions ("Invitations", upload, and a person icon).
- **Used by:** admin.
- **Our status: PARTIAL.**
  - We have `GET /admin/students` with search and filters (`backend/app/Http/Controllers/AdminStudentController.php:66-111`; search covers `roll_no`, `full_name` and `institute_email` at lines 81-88) and the page `frontend/app/admin/students/page.jsx`.
  - The columns there are Roll no, Name, Programme, Branch, Batch, CGPA, Backlogs, Active and View (lines 205-213).
  - Missing:
    - search by **mobile number** (`phone`) and **personal email**;
    - a **Verified** column (we have no profile-verification status at all);
    - a "Found N student(s) matching 'x'" line (we show only the total in the header subtitle, line 116);
    - "registered" and "invited" counts (we know about invites from `student_invite_tokens`, but do not show counts).
  - "College" does not apply to us, since there is a single institute.
- **Naming:**

  | Ours | Superset | Rename? |
  |---|---|---|
  | "Students" (page title) | "Colleges and Students" | No (single institute) |
  | "Search roll no, name or email" | "Search student with name, roll number, email, or mobile number..." | Yes, once mobile is added |
  | "Roll no" | "Identification Number" | Optional; keep "Roll no", which is CDC vocabulary |
  | "Active" | (none) | — |
  | (none) | "Mobile" | Add as a column; we label the field "Phone" |
  | (none) | "Verified" | Add a column if F7 is built |

- **Conflict check:** none for the search changes. The Verified column depends on F7 (NEEDS OWNER DECISION).

### F2. "Profile update requests pending for approval" banner
- **Superset name and location:** a blue info banner at the top of Students: "You have profile update requests pending for approval. Click to view requests."
- **What it does:** tells the admin that students have submitted profile edits waiting for CDC approval, and links to the request list.
- **Used by:** admin (the requests come from students).
- **Our status: PARTIAL.**
  - Our only student-initiated change needing approval is a **branch change**: `GET/PATCH /admin/branch-changes` (`backend/routes/api.php:162-163`), page `frontend/app/admin/branch-changes/page.jsx` titled "Branch Change Requests" (line 82).
  - Resumes pending verification are a second queue (`frontend/app/admin/resumes/page.jsx`).
  - Missing: neither queue is surfaced as a banner on the Students page or the admin dashboard. `AdminDashboardController.php:71` counts only pending JNF/INF reviews.
- **Naming:**

  | Ours | Superset | Rename? |
  |---|---|---|
  | "Branch Change Requests" | "profile update requests" | Keep ours unless F8 is built |

  Banner wording, if built: "You have N branch change request(s) and N resume(s) pending for approval. Click to view requests."
- **Conflict check:** a banner for our *existing* queues has no conflict (recommended, Low effort). A general "profile update request" flow conflicts; see F8.

### F3. Student profile header card
- **Superset name and location:** left card on the student profile (`/#/a/colleges/<college>/students/<uuid>`).
- **What it shows:**
  - photo and name;
  - "<YYYY> Passout Batch | <roll>";
  - "Superset Id: <n>";
  - "<n>th Semester, <Course> (Semester)";
  - department;
  - big stats: **CGPA**, **Applications** (count) and **Offers** (count).
- **Used by:** admin.
- **Our status: PARTIAL.**
  - `frontend/app/admin/students/[id]/page.jsx`:
    - the PageHeader title is the name;
    - the subtitle is `roll · branch · batch` (lines 134-158);
    - the photo is an Avatar inside the Overview tab (lines 187-191);
    - the applications count appears only in a tab label (line 180).
  - Missing:
    - a persistent summary card with **CGPA / Applications / Offers** counts;
    - **current semester** (no such column: `database/migrations/2026_09_21_162407_create_student_profiles_table.php` has no semester field);
    - **department** (we use programme + branch);
    - "Passout Batch" wording.
- **Naming:**

  | Ours | Superset | Rename? |
  |---|---|---|
  | "Graduating batch" (field label, also on the student profile at `app/student/profile/page.jsx:40`) | "Passout Batch" (shown as "2027 Passout Batch") | Optional. "Passout Batch" is common Indian usage, and changing the display label is safe. Keep the column name `graduating_batch` (no route or column renames, per owner rule §10). |
  | "Roll number" | "Identification Number" / "Institute Roll Number / University ID Number / USN" | Keep "Roll number" |

- **Conflict check:** "Current semester" would be a new academic field. The owner says academics are CDC-controlled and will sync, so it is fine only if the CDC or the sync sets it. Adding it is NEEDS OWNER DECISION, but low risk.

### F4. Overview tab: placement cycles, applications, attendance, reports, events, notes
- **Superset name and location:** profile → **OVERVIEW** tab.
- **Fields and controls shown:**
  - Section "Placements" with an (i) icon.
  - **"Download Placement Report"** and **"Download Eligibility Report"** links.
  - The sentence "<First name> has participated in the following placement cycles".
  - Per cycle:
    - the name (with an external link to the cycle);
    - a status: "**Enrolled**" or "**Placed** ( Intern at <Company> )";
    - the expanders "**Show Applications**" and "**Show Attendance**";
    - icon buttons: trophy or pencil (offer?), gear (enrolment settings?), list (details?).
  - Section "Events" with "**Show Event Attendance**".
  - Section "**Notes**": "Write notes about this student", a free-text admin note thread.
- **Used by:** admin.
- **Our status: PARTIAL** (overall). Per item:
  - **Cycles list: PARTIAL.**
    - Our "Cycles (n)" tab (`app/admin/students/[id]/page.jsx:207-245`) lists cycle, type, cycle status, enrolment status and enrolled-on date. Enrolment status is only `active`/`suspended` (`database/migrations/2026_09_27_000005_create_cycle_enrollments_table.php:18`).
    - There is no per-cycle "Placed (… at Company)" status. Offers are on a separate "Offers & Blocks" tab (lines 288-328).
  - **Show Applications (per cycle): PARTIAL.** The "Applications (n)" tab (lines 247-286) lists all applications flat, not grouped by cycle.
  - **Show Attendance: NOT IMPLEMENTED on the student page.** Round attendance exists in the pipeline (`AdminPipelineController.php:145-172`, values yes/no) but `AdminStudentController::show` (lines 113-165) does not return it.
  - **Download Placement Report / Eligibility Report (per student): NOT IMPLEMENTED.** We have cycle and posting exports only (`routes/api.php:91`, `:125`). The eligibility engine `EligibilityService::check()` could produce a per-student "eligible / not eligible + reasons" report across all drives.
  - **Show Event Attendance: NOT IMPLEMENTED.** Events are announce-only (owner decision §3, "announce only, with no RSVP").
  - **Notes: NOT IMPLEMENTED for students.** Admin notes exist only for JNF/INF (`POST /admin/jnfs/{jnf}/notes`, `AdminFormReviewController.php:582-601`). We have an "Audit trail" tab (lines 330-362), but it is not a notes thread.
- **Naming:**

  | Ours | Superset | Rename? |
  |---|---|---|
  | Tab "Overview" (holds profile fields) | "OVERVIEW" (holds cycles/applications/notes); profile fields live under "ABOUT" | Restructuring would be needed to match; low value |
  | Tab "Cycles (n)" | "Placements" section | Optional |
  | Enrolment status "Active"/"Suspended" | "Enrolled" / "Placed" | Consider showing "Enrolled" for active enrolments and a derived "Placed (<offer type> at <company>)" |
  | Tab "Applications (n)" | "Show Applications" | — |
  | (none) | "Download Placement Report", "Download Eligibility Report" | Add if built |
  | (none) | "Notes" / "Write notes about this student" | Add if built |

- **Conflict check:**
  - **Event attendance** conflicts with "announce only, no RSVP" → **NEEDS OWNER DECISION**. Attendance is not RSVP, but it is still new event tracking.
  - Round attendance on the student page: no conflict.
  - Notes: no conflict (an admin-only internal note; must be audit-logged).
  - Per-student reports: no conflict.

### F5. About tab: personal "Overview" section with contact-verified ticks
- **Superset name and location:** profile → **ABOUT** → section "Overview" (with "Edit").
- **Fields:**
  - Name;
  - Contact No. (country code + number, green check = verified);
  - Email (institute, green check);
  - Personal Email (green check);
  - Date of Birth, shown with the age "(22 years)";
  - Gender;
  - Category;
  - **Student Categories**;
  - **WorkEx Month Override**.
- **Section controls:** a green badge "Verified by <CDC name>" and "Reject" (tooltip "Ask Re-Submission").
- **Used by:** admin (the student fills it in).
- **Our status: PARTIAL.**
  - Name, institute email, personal email, phone, DOB, gender and category exist: `student_profiles` columns, shown in `app/admin/students/[id]/page.jsx:39-59` and editable by the admin through `StudentFormDialog` and `PATCH /admin/students/{id}` (`AdminStudentController.php:189-245`, E8 mail at 478-505).
  - Missing:
    - **contact verification ticks** (no OTP or email verification of `phone`/`personal_email`; students edit them freely in `StudentProfileController.php:26-46`);
    - the **age** display;
    - **Student Categories** (multi-tag);
    - **WorkEx Month Override** (work-experience months);
    - the **per-section Verified badge / Reject**.
- **Naming:**

  | Ours | Superset | Rename? |
  |---|---|---|
  | "Phone" | "Contact No." | Optional |
  | "Institute email" | "Email" | Keep ours (clearer) |
  | "Personal email" | "Personal Email" | Capitalisation only; our UI uses sentence case consistently, so keep |
  | "Date of birth" | "Date of Birth" | Same |
  | "PwD" | "Do you belong to PWD" (Additional Info) | Keep |

- **Conflict check:**
  - Contact verification (OTP) affects only self-editable fields. No conflict, but it needs an SMS provider. Mail goes to institute addresses only, so OTP to a personal email would also need an owner OK → **NEEDS OWNER DECISION**.
  - WorkEx and Student Categories are new data the CDC would control: low priority.

### F6. About tab: Address, Education gap, Additional Info, custom questions, declaration
- **Superset name and location:** ABOUT → sections "Address", "Education", "Additional Info", then custom fields.
- **Fields:**
  - **Address:** Permanent Address, Current Address.
  - **Education:** "Total Student Education Gap" (number).
  - **Additional Info**, institute-defined questions:
    - "What is your father name?";
    - "Aadhar Number";
    - "Do you belong to EWS-General";
    - "Do you belong to PWD…".
  - **Internship-related custom fields:**
    - "Paid or Unpaid Internship";
    - "Monthly Stipend Amount (₹)";
    - "Internship Mentor Name";
    - "Internship Mentor Designation";
    - "Internship Mentor Official Email ID";
    - "Internship Mentor Contact Number".
  - A **declaration** checkbox: "I hereby declare that the information given by me in the superset is true, complete and correct …" (Yes).
  - Each section has "Edit" and (the Overview section has) a Verified badge with Reject.
- **Used by:** the student fills it in; the admin verifies and edits.
- **Our status: NOT IMPLEMENTED.** The only related columns are `home_state`, `category` and `pwd` (migration `2026_09_21_162407_create_student_profiles_table.php`). There is no address, guardian name, Aadhaar, EWS flag, education gap, custom profile questions or declaration. Our only custom-question builder is per drive (`posting_questions`, application questions), not per profile.
- **Naming:** none of these exist. "Home state" is the closest to Address.
- **Conflict check:**
  - These are not academic fields, so the "academics locked" rule does not directly apply.
  - Students can currently edit only six fields (`StudentProfile::SELF_EDITABLE`, `backend/app/Models/StudentProfile.php:15`, plus photo), so adding student-editable fields changes owner policy → **NEEDS OWNER DECISION**.
  - **Aadhaar storage** is sensitive personal data (UIDAI rules). Flag strongly before building.
  - The internship-mentor fields look like a per-cycle data capture that Superset implements as profile questions; that would be better as a drive/offer question.
  - A declaration checkbox is low risk.

### F7. Profile-level "Mark as verified" and the verification status
- **Superset name and location:** the button "**Mark as verified**" (green) at the top right of every profile tab. The list's "Verified" column reflects it.
- **What it does:**
  - Marks the whole student profile as verified by the CDC.
  - It looks disabled while there are un-submitted section rejections (2:40), and is enabled again after the resubmission request is sent (4:36).
- **Used by:** admin.
- **Our status: NOT IMPLEMENTED.**
  - No profile verification field exists. A grep for `is_verified|verified_at|profile_verified` finds only recruiter-email and `users.email_verified_at`.
  - The only CDC verification is per resume: `PATCH /admin/resumes/{resume}` with status `approved|rejected` (`AdminResumeController.php:82-150`), page "Resume Verification" (`app/admin/resumes/page.jsx:126`).
- **Naming:**

  | Ours | Superset | Rename? |
  |---|---|---|
  | Resume "Approve" button, statuses `pending/approved/rejected` (`app/admin/resumes/page.jsx:231`, migration `2026_09_27_000007_create_resumes_table.php`) | "Mark as verified" / "Verified by <CDC>" | Optional: rename the resume "Approve" button to "Mark as verified" and the status chip "Approved" to "Verified" (display only, keep the enum values). The page title "Resume Verification" already uses Superset's vocabulary. |

- **Conflict check:** in our model the CDC itself enters the profile data (§3 Students: "academic fields are locked… will auto-sync"), so "verifying" it is largely meaningless unless students can supply data. Building a verified flag only for the student-editable fields, or as a CDC checklist, is possible but is a product change → **NEEDS OWNER DECISION**.

### F8. Pending student changes: "Accept Changes" / "Reject Changes" (with old vs new diff)
- **Superset name and location:** ACADEMIC DETAILS → a Course (or other section) card with "There are changes pending review for this course".
- **What it shows:**
  - new values, with the old values **struck through** (dates, score);
  - the buttons **"Accept Changes"** (green) and **"Reject Changes"** (red).
- **The Reject Changes modal:**
  - title "Reject changes for <Course>, <Branch>, <Department>";
  - "Student will receive an email about reject along with notes entered below";
  - a **Notes** field (max 1000 characters, counter "N characters left", placeholder "Specify reasons to reject the changes");
  - Cancel and Reject Changes.
  - Rejecting reverts the card to the last verified values.
- **Rule seen:** while a change is pending, an admin Edit + Save fails with "There are changes pending for approval. Please approve or reject them first".
- **Used by:** the student submits; the admin decides.
- **Our status: NOT IMPLEMENTED for profile data.**
  - The closest analogue is the **branch-change request**: the student submits from `/student/profile`, and the admin approves or rejects with a remark at `/admin/branch-changes` (`AdminBranchChangeController`, table `branch_change_requests` with `status pending/approved/rejected` and `admin_remark`, migration `2026_09_27_000006`). One pending request at a time (D56).
  - We have no old/new diff display, no generic change-request model and no email-with-notes for profile changes (E8 fires only when the *admin* edits, `AdminStudentController.php:478-505`).
- **Naming:**

  | Ours | Superset | Rename? |
  |---|---|---|
  | Branch change: "Approve"/"Reject", "Remark (sent to the student)" (`app/admin/branch-changes/page.jsx:196`) | "Accept Changes" / "Reject Changes", "Notes" | Optional: rename the buttons to "Accept Changes" / "Reject Changes" and the remark label to "Notes" for consistency |

- **Conflict check:** **NEEDS OWNER DECISION.** Letting students propose changes to course, CGPA, semester, dates or backlogs directly contradicts "academic fields are locked… CGPA and backlogs will auto-sync from the institute database" (CDC_PORTAL_CONTEXT §2.3, §3 Students). The branch-change flow is the owner-approved exception. Do not build a general version without approval.

### F9. Per-section "Reject" (Ask Re-Submission) → "Add to Resubmission List"
- **Superset name and location:** the "Reject" button on each section or card (tooltip "**Ask Re-Submission**"):
  - ABOUT/Overview;
  - Course;
  - each Education entry;
  - (likely Address and Additional Info too).
- **The modal:**
  - title "Reject";
  - the text "Please provide the reason for rejecting student <education|course> details. This will help the student rectify the details and re-submit*" (required);
  - placeholder "Mention the reason of rejection so that student can resolve the issues and resubmit.";
  - buttons "**Add to Resubmission List**" and "Cancel".
- **What it does:** stages a rejection (a draft) per section. A yellow strip appears ("You have un-submitted changes. Click Ask Resubmission to submit. View Details"), and the "Ask Resubmission" button shows a count badge.
- **Used by:** admin.
- **Our status: NOT IMPLEMENTED.** Rejection exists only per resume, with an immediate remark: dialog "Reject resume", field "What should the student fix?" (`app/admin/resumes/page.jsx:273-300`). There is no staging or batching.
- **Naming:**

  | Ours | Superset | Rename? |
  |---|---|---|
  | "Reject resume" / "What should the student fix?" | "Reject" / "Ask Re-Submission" / "Mention the reason of rejection so that student can resolve the issues and resubmit." | Optional for the resume dialog: placeholder or helper "Mention the reason of rejection so the student can fix it and re-upload." |

- **Conflict check:** **NEEDS OWNER DECISION**. It only makes sense if students own and can resubmit the rejected data. That is true for resumes and personal fields, but not for academics.

### F10. "Ask Resubmission" (batch send to the student)
- **Superset name and location:** the red-outline button "**Ask Resubmission**" (with a pending count badge) at the top right of the profile.
- **The modal:**
  - "You are submitting the following sections for resubmission";
  - a numbered list grouped as "Changes required in <Section>" with each reason;
  - a required overall reason: "Please provide the reason for rejecting student details. This will help the student rectify the details and re-submit*";
  - the buttons "**Clear all**", "Add to Resubmission List" (acts as submit) and "Cancel".
- **The result:**
  - toast "Success! Request sent to student for modifications.";
  - profile strip "**Student profile has been rejected. Waiting for re-submission**" with "View Details";
  - each rejected section shows "**This section needs changes**", a comment bubble (author = CDC account, relative time "wrote a few seconds ago", quoted reason) and buttons "Reject" and "**Confirm Correctness**".
  - The student presumably gets an email or notification (implied by the toast; not shown).
- **Profile states seen:**
  - verified (the list shows a check, not seen);
  - not verified (orange triangle);
  - un-submitted changes (yellow strip);
  - rejected / waiting for re-submission (orange-red strip);
  - per section: Verified, pending changes, or needs changes.
- **Used by:** admin; the student then resubmits.
- **Our status: NOT IMPLEMENTED.**
- **Naming:** none of these exist.
- **Conflict check:** **NEEDS OWNER DECISION** (same reason as F8 and F9).

### F11. "Confirm Correctness"
- **Superset name and location:** a button on a section marked "This section needs changes" (4:38).
- **What it does:** likely lets the admin clear the needs-changes state and confirm the existing data as correct (overriding the request). Inferred; the button was not clicked.
- **Our status: NOT IMPLEMENTED.**
- **Conflict check:** tied to F10.

### F12. Academic Details: Course record (the current programme) with full fields
- **Superset name and location:** ACADEMIC DETAILS → section "Course" (card) and the "**Update Course Details**" modal (via Edit).
- **Fields:**
  - Course Group* (seen: "Single Degree");
  - Course* (searchable "<Degree> in <Specialisation> , <Department>");
  - Batch* ("<YYYY> Passout Batch");
  - Current Semester*;
  - Score* (with a CGPA unit);
  - Percentage Equivalent* (%);
  - Course Start Date* and Course End Date* (month/year; "Please enter expected graduation date");
  - Lateral Entry ("I am a lateral entry student in this course.");
  - Institute Roll Number / University ID Number / USN*;
  - Highlights/Notes;
  - Performance per term (Semester n: CGPA, SGPA, Backlog Total, Backlog Ongoing; "+ Add Term");
  - Total Backlog details ("I have backlog(s)");
  - Attendance in Training Sessions (%);
  - Attendance in Classroom (%).
- **Validations seen:**
  - required asterisks;
  - changing Course resets Current Semester to required;
  - Save is blocked while student changes are pending.
- **Card display:** course name, "<Course> (Semester)", "Branch: …", "Department: …", "<batch> | <roll>", "Mon YYYY — Mon YYYY", CGPA, Score; links "Show Term-wise Details" and "Show Marksheet".
- **Used by:** the student fills it in; the admin edits and verifies.
- **Our status: PARTIAL.**
  - We store `programme`, `branch`, `graduating_batch`, `current_cgpa`, `ongoing_backlogs` and `total_backlogs` (migration `2026_09_21_162407…`).
  - The CDC edits them through `StudentFormDialog` (`components/admin/studentformdialog.jsx`, title "Edit <roll>" at line 104) → `PATCH /admin/students/{id}`. Bulk updates go through "Update Academics" (`app/admin/students/page.jsx:127-129, 286-295`; `POST /admin/students/academics/import`, `AdminStudentController.php:399-435`, columns roll_no, current_cgpa, ongoing_backlogs, total_backlogs).
  - Missing:
    - Course Group (single, dual or integrated degree);
    - current semester;
    - course start and end dates;
    - lateral entry;
    - percentage equivalent;
    - highlights;
    - **term-wise CGPA/SGPA and backlogs** ("Show Term-wise Details");
    - attendance in training and classroom;
    - a course document ("Show Marksheet").
- **Naming:**

  | Ours | Superset | Rename? |
  |---|---|---|
  | "Programme" | "Course Group" + "Course" (Superset merges degree, specialisation and department into "Course") | Keep "Programme" / "Branch"; they match IIT vocabulary and the JNF matrix |
  | "Branch" | "Branch" | Same |
  | "Graduating batch" | "Batch" (value "2027 Passout Batch") | Optional display rename (see F3) |
  | "CGPA" (`current_cgpa`) | "Score" (unit CGPA) / card caption "CGPA" | Keep "CGPA" |
  | "Ongoing backlogs" / "Total backlogs" | "Backlog Details: Ongoing / Total", "Total Backlog details" | Keep |
  | "Roll number" | "Institute Roll Number / University ID Number / USN" | Keep |
  | Student page section "Academic record" (`app/student/profile/page.jsx:232`) | "Academic Details" (tab) | Optional rename to "Academic Details" |

- **Conflict check:**
  - Adding CDC-controlled columns (semester, course dates, lateral entry, term-wise grades) is compatible with the CDC-controlled model if only the CDC or the sync writes them. Still a schema and product change → NEEDS OWNER DECISION (Medium).
  - Letting the **student** fill them in (as Superset does) **contradicts the owner decision** → NEEDS OWNER DECISION.
  - Attendance in training and classroom has no current source. Owner input needed.

### F13. Academic Details: prior Education entries (10th, 12th, UG) with "Add Education"
- **Superset name and location:** ACADEMIC DETAILS → section "Education" → "+ Add Education".
- **Each entry has:**
  - institution;
  - board or university;
  - degree / specialisation or class level (10th / 12th);
  - start–end years;
  - CGPA "x /10" and/or Score "%";
  - "Edit" and "Show Marksheet";
  - a per-entry "Reject" and "Verified by <CDC>" badge.
- **Used by:** the student adds entries; the admin verifies or edits.
- **Our status: PARTIAL.**
  - We store only `tenth_percent` and `twelfth_percent` (no board, school, year or proof) and no prior UG/PG record for PG students.
  - These come only from the CDC (`StudentAccountService::IMPORT_COLUMNS`, `backend/app/Services/StudentAccountService.php:24-28`) and are shown read-only to students (`app/student/profile/page.jsx:43-44`).
  - Eligibility uses them (min 10th/12th %, CDC_PORTAL_CONTEXT §4).
  - Missing: a structured education history (school, board, years, UG CGPA for MBA/M.Tech students), "Add Education" and per-entry documents.
- **Naming:**

  | Ours | Superset | Rename? |
  |---|---|---|
  | "10th %" | Education entry "10th", caption "Score" | Keep "10th %" |
  | "12th %" | "12th", "Score" | Keep "12th %" |

- **Conflict check:** 10th and 12th are listed among the academic fields that are locked for students ("marks"). Student-entered education history **contradicts** this → **NEEDS OWNER DECISION**. A CDC-entered version with no student edit is compatible.

### F14. Document proof viewer ("Show Marksheet" side drawer)
- **Superset name and location:** the "Show Marksheet" link on each Education and Course card.
- **The drawer:**
  - opens from the left;
  - title "<Student name>";
  - subtitle "Attachment for Education : <10th|12th|degree name>" or "Document for <Course>";
  - close X.
- **Viewers:**
  - PDFs get the browser PDF viewer (pages, zoom, annotate);
  - images get pan and round **+ / −** zoom buttons.
- **Accepted proofs seen:** DigiLocker PDFs, a phone photo of a certificate, and a 3-page institute registration slip.
- **Used by:** admin (to compare typed values with the proof).
- **Our status: NOT IMPLEMENTED.**
  - No academic document storage exists (`grep marksheet` finds nothing). Students can upload only resumes (PDF, at most 2 MB, 8 slots) and a photo.
  - The resume preview pattern (signed URL → `/api/proxy-pdf` → iframe; `app/admin/resumes/page.jsx:39-40, 257-263`; D49, D59) could be reused for PDFs. Images would need an image viewer, and the PDF proxy deliberately allows only PDFs (D59).
- **Naming:** none.
- **Conflict check:** proofs only make sense if students supply academic data (F12/F13) → **NEEDS OWNER DECISION**. An alternative that stays compatible: the CDC uploads proofs itself, though that has low value.

### F15. "Important actions": Freeze Student / Delete Student
- **Superset name and location:** ABOUT → bottom → section "Important actions".
- **Rows:**
  - "Unfreeze student profile" with the button "**Freeze Student**";
  - "Delete this student" with the button "**Delete Student**" (solid red).
- **What it does:** "Freeze" presumably locks the student's profile from further edits (and may hide it from editing during placements). "Delete" removes the student.
- **Used by:** admin.
- **Our status:**
  - **Freeze: NOT IMPLEMENTED as such, and largely moot.** Academics are always locked for students. We have **Suspend / Reactivate**, which blocks login (`PATCH /admin/students/{id}/suspend|reactivate`, `AdminStudentController.php:247-255, 446-472`; button in `app/admin/students/[id]/page.jsx:148-155`). That is a different, stronger action. A per-cycle enrolment suspension also exists (`cycle_enrollments.status`).
  - **Delete: NOT IMPLEMENTED.** No delete route exists (`routes/api.php:95-106`). Applications reference resumes with `restrictOnDelete` (D50).
- **Naming:**

  | Ours | Superset | Rename? |
  |---|---|---|
  | "Suspend" / "Reactivate" | "Freeze Student" / "Unfreeze" | **Do not rename.** Different meaning (ours blocks login; Superset's locks profile edits). |

- **Conflict check:**
  - Delete conflicts with "students keep their history across cycles", the audit-log requirements and "blocks are never hard-deleted" → **NEEDS OWNER DECISION** (recommend not building, or soft-delete only).
  - Freeze: only relevant if students gain editable profile sections.

### F16. Profile tabs layout
- **Superset tabs:** OVERVIEW, ABOUT, ACADEMIC DETAILS, PROFILE, RESUMES & DOCUMENTS.
- **Our tabs:** Overview, Cycles (n), Applications (n), Offers & Blocks, Audit trail (`app/admin/students/[id]/page.jsx:177-183`).
- **Our status: PARTIAL.**
  - The admin cannot see a student's **resumes** from the student page; they are only in the global queue `/admin/resumes`. The detail payload (`AdminStudentController::show`, lines 113-165) does not include resumes.
  - Superset's "RESUMES & DOCUMENTS" tab groups them per student. "PROFILE" content is unknown (not opened).
- **Naming:**

  | Ours | Superset | Rename? |
  |---|---|---|
  | (none) | "Resumes & Documents" tab | Recommended: add a "Resumes & Documents" tab listing the student's resumes with status and inline Approve/Reject |
  | "Overview" (profile fields) | "About" | Optional; only if F4's overview content is added |

- **Conflict check:** a per-student resumes tab has no conflict (High value, Low effort).

### F17. Superset Id (a platform-wide student id)
- **Shown:** "Superset Id: 7122209" on the profile card.
- **Our status: NOT IMPLEMENTED.** Not needed: we use `roll_no` (unique) plus an internal `id`.
- **Rename:** no.
- **Conflict:** none. Skip.

### F18. Notification to the student on rejection
- **Seen:**
  - the Reject Changes modal says "Student will receive an email about reject along with notes entered below";
  - the Ask Resubmission toast says "Request sent to student for modifications".
- **Our status: PARTIAL.**
  - E7 (resume approved or rejected with the remark; `AdminResumeController.php:126-144`) and E8 (profile changed by an admin, or a branch change decided; `AdminStudentController.php:478-505`) exist as email plus in-app notification.
  - Missing: a "profile needs changes" or "changes rejected" email with section-wise reasons. This depends on F8–F10.
- **Conflict:** tied to F8–F10.

---

## 3. Gap summary

| Superset name | What to build | Priority | Conflicts |
|---|---|---|---|
| Search "…email, or mobile number" | Add `phone` and `personal_email` to the admin student search (`AdminStudentController::index`); add a "Found N matching 'x'" line | Medium | None |
| "Verified" column | Show verification status in the student list | Low | Depends on F7 (NEEDS OWNER DECISION) |
| "You have profile update requests pending for approval. Click to view requests." | Banner on the Students page and admin dashboard counting pending branch-change requests and pending resumes, linking to the queues | Medium | None for our existing queues |
| Profile card (CGPA / Applications / Offers, semester, Passout Batch) | Summary card on `/admin/students/[id]` with CGPA, application and offer counts, photo | Medium | Current semester is a new academic field (NEEDS OWNER DECISION, low risk if CDC-set) |
| Overview → per-cycle "Enrolled / Placed (… at Company)" | Show a derived placed status per cycle; group applications by cycle | Medium | None |
| "Show Attendance" | Show round attendance per application on the student page | Low | None |
| "Download Placement Report" / "Download Eligibility Report" | Per-student Excel/PDF: applications, rounds, offers; eligibility against every drive with reasons (via `EligibilityService::check`) | Medium | None |
| "Show Event Attendance" | Event attendance tracking | Low | Events are announce-only, no RSVP → NEEDS OWNER DECISION |
| "Notes" / "Write notes about this student" | Admin-only internal notes thread on the student, audit-logged | Medium | None |
| "Resumes & Documents" tab | List the student's resumes with status and inline verify/reject on the student page | High | None |
| Contact-verified ticks (Contact No., Email, Personal Email) | OTP or email verification of the phone and personal email | Low | SMS provider, mail to non-institute addresses → NEEDS OWNER DECISION |
| Student Categories, WorkEx Month Override | New CDC-controlled profile fields | Low | NEEDS OWNER DECISION |
| Address, Additional Info (father's name, Aadhaar, EWS, PWD), Education gap, custom profile questions, declaration | New profile fields and a profile-question builder | Low | New student-editable data changes the "6 self-editable fields" policy; Aadhaar is sensitive → NEEDS OWNER DECISION |
| "Mark as verified" (profile) | Profile-level verification status and audit | Low | Profile data is CDC-entered → NEEDS OWNER DECISION |
| "Accept Changes" / "Reject Changes" (pending changes with old/new diff, email notes) | Generic student change-request flow beyond branch change | Low | **Contradicts locked academics / future auto-sync** → NEEDS OWNER DECISION |
| "Reject" (Ask Re-Submission) → "Add to Resubmission List" | Per-section staged rejection | Low | Same as above → NEEDS OWNER DECISION |
| "Ask Resubmission", "This section needs changes", "Confirm Correctness", status strips | Batch resubmission request, section comment bubbles, profile states | Low | Same as above → NEEDS OWNER DECISION |
| "Update Course Details" fields (Course Group, Current Semester, course dates, Lateral Entry, Percentage Equivalent, Highlights, term-wise CGPA/SGPA/backlogs, attendance) | New academic columns or tables, written by the CDC or the sync only | Medium (semester, dates, lateral entry); Low (others) | Academic fields are CDC-controlled and sync later; student entry contradicts this → NEEDS OWNER DECISION |
| "Show Term-wise Details" | Semester-wise grade history | Low | Must come from the institute sync → NEEDS OWNER DECISION |
| Education entries + "Add Education" (school, board, years, UG CGPA for PG students) | Structured education history | Medium (UG CGPA for MBA/M.Tech is often an eligibility input) | Marks are locked for students → NEEDS OWNER DECISION; CDC-entered is fine |
| "Show Marksheet" document drawer (PDF + image zoom) | Academic proof upload and a side-drawer viewer | Low | Only meaningful with student-supplied data → NEEDS OWNER DECISION |
| "Freeze Student" | Lock profile edits | Low | Moot today (academics always locked); do not confuse with Suspend |
| "Delete Student" | Hard delete | Low | Conflicts with history, audit and never-hard-delete blocks → NEEDS OWNER DECISION (recommend no) |
| Rejection email / notification for profile sections | New mail type (E-series) | Low | Tied to F8–F10 |

---

## 4. Rename list

Only display labels. Per owner rule §10, never rename routes, columns, enum values or response keys.

| Where it appears (our UI/code) | Our current name | Superset name |
|---|---|---|
| `frontend/app/admin/students/page.jsx:149` (search field label) | "Search roll no, name or email" | "Search student with name, roll number, email, or mobile number..." (adopt once mobile search is added) |
| `frontend/app/admin/students/page.jsx:205` (column) | "Roll no" | "Identification Number" (recommend keeping "Roll no") |
| `frontend/app/admin/students/page.jsx` (no column) | (none) | "Mobile" (add a column, sourced from `phone`) |
| `frontend/app/admin/students/page.jsx` (no column) | (none) | "Verified" (only if F7 is approved) |
| `frontend/app/admin/students/[id]/page.jsx:46`, `frontend/app/student/profile/page.jsx:40` | "Graduating batch" (value "2027") | "Batch" / "2027 Passout Batch" (optional display change) |
| `frontend/app/admin/students/[id]/page.jsx:43` | "Phone" | "Contact No." (optional) |
| `frontend/app/admin/students/[id]/page.jsx:178` | Tab "Overview" (profile fields) | Profile fields are under "About"; Superset's "Overview" is placements/notes (optional restructure) |
| `frontend/app/admin/students/[id]/page.jsx:179` | Tab "Cycles (n)" | Section "Placements" (optional) |
| `frontend/app/admin/students/[id]/page.jsx:237` (enrolment chip) | "Active" / "Suspended" | "Enrolled" (plus derived "Placed") |
| `frontend/app/admin/students/[id]/page.jsx` (no tab) | (none) | "Resumes & Documents" (add the tab) |
| `frontend/app/student/profile/page.jsx:232` (section heading) | "Academic record" | "Academic Details" (optional) |
| `frontend/app/admin/resumes/page.jsx:231` (button) | "Approve" | "Mark as verified" (optional; status chip "Approved" → "Verified", display only) |
| `frontend/app/admin/resumes/page.jsx:274` (dialog title) | "Reject resume" | "Reject" with the tooltip "Ask Re-Submission" (optional) |
| `frontend/app/admin/resumes/page.jsx:286` (field label) | "What should the student fix?" | "Mention the reason of rejection so that student can resolve the issues and resubmit." (optional placeholder) |
| `frontend/app/admin/branch-changes/page.jsx` (decision buttons) | "Approve" / "Reject" | "Accept Changes" / "Reject Changes" (optional) |
| `frontend/app/admin/branch-changes/page.jsx:196` | "Remark (sent to the student)" | "Notes" ("Student will receive an email about reject along with notes entered below") (optional) |
| `frontend/app/admin/students/[id]/page.jsx:154` | "Suspend" / "Reactivate" | **Do not rename** to "Freeze Student" / "Unfreeze"; the meaning differs |

---

## 5. Uncertain

- **Verified column icon states:** only the orange warning triangle (not verified) was seen. The verified-state icon and any third state (for example "rejected") were not shown.
- **Header icon buttons on "Colleges and Students":** the person/pin icon with a dot and the upload icon. Their purpose is unknown (possibly a pending-requests indicator and a bulk upload).
- **Icons on cycle rows** (trophy, pencil, gear, list) on the Overview tab: purpose inferred, not demonstrated.
- **Small circular icon** at the top right of each Education card: purpose unknown (possibly a verification-state dot or a collapse toggle).
- **"Important actions" wording mismatch:** the row label says "Unfreeze student profile" but the button says "Freeze Student". It is unclear whether the student is currently frozen.
- **The struck-through "0"** above 85.4% on the pending course card is read as the old Score value, but it is rendered oddly (it looks like Ø).
- **The B.Tech marksheet drawer** briefly showed the previous 12th PDF before the image loaded (1:52). Read as a loading artefact, not a mislabelled document.
- **The presenter's Reject Changes notes text** (between 3:54 and 4:10) was not visible. It is unclear whether text was typed, since the button looked disabled while empty.
- **"Mark as verified" at 2:40–4:22** appears pale. Disabled state is inferred from colour only.
- **The final submit button in the Ask Resubmission modal** is labelled "Add to Resubmission List", the same label as the staging modal. Read as is; a different label may appear after text is entered.
- **The "Do you belong to PWD" row** under Additional Info is cut off at the bottom of the frame.
- **The student-facing side** (how a student edits sections, resubmits, sees "Request sent … for modifications", and gets email or in-app notices) is not shown in this video.
- **The PROFILE and RESUMES & DOCUMENTS tabs** were never opened, so their content is unknown.
- **"Confirm Correctness"** was not clicked. Its effect (clearing the needs-changes state against marking the section verified) is inferred.
- **Whether "Mark as verified"** is blocked while sections need changes, or only while rejections are un-submitted, cannot be determined from these frames.
