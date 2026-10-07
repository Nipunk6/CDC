# 09 — Superset "How to make a survey" (148 s) vs our CDC portal

Source frames: `scratchpad/kf/09_survey/` (23 frames, t000m00s → t001m44s). All 23 frames were read in order. No frame after 1:44 was kept, so the screen did not change from 1:44 to the end of the video (2:28). The presenter never clicks **Publish Survey**, never opens **Add Audience**, and never opens **View Report** on screen.

---

## 1. Video walkthrough

**0:00 — Starting point: Superset admin home, `app.joinsuperset.com/#/admin/placements`.**
- Top bar: institute name "Indian Institute of Technology Indian School of Mines Dhanbad", a "Search students" box (magnifier icon), a download icon, a bell, an apps-grid icon, the org label "Career Development Centre IIT ISM Dhanbad" and the avatar "CD". A blue chat bubble sits bottom-right (support widget).
- The page is titled "Placements". On the left is an illustration (calendar icon), the heading "Placements" and the blurb "Placement Cycles help you manage distinct recruitment phases—like **Final Year Placements** and **Pre-Final Year Internships**—with dedicated timelines, policies, and teams. Each cycle is a self-contained process tailored to a specific student batch and purpose.", followed by the button "+ Add placement process".
- The middle column has "Search Placements" and the current cycles, each card showing a name, a date range and a gear icon:
  - "Full-Time Placement for 2026-27 (2027 Pass Out Batch)", June 2026 - June 2027;
  - "Internship Placement || 2026-2027 Session || 2028 Pass out batch", April 2026 - August 2027.
- Below them, "Previous Placements":
  - "Internship Placement Cycle for 2029 Graduating Batch [DRAFT]", March 2026 - February 2026, with a yellow icon;
  - "Full-Time Placement for 2025-26 (2026 Pass Out Batch)";
  - "Internship Placement for 2025-26 (2027 Batch)";
  - and more below.
- The right column, "Recently Visited", lists the same cycles plus "FT Placement for 2023-2024 (2024 passouts)" and "Full-Time Placement for 2024-25 (2025 Pass Out Batch)".
- Sidebar, top part: "RECENT JOB PROFILES" (chips: CentrAlign AI…, Founding Eng…, C-DAC, Kolkata…, Knowledge, Accenture Japa…, Digital Cons…) and "RECENTLY VISITED PLACEMENTS" (Full-Time Placement for 2…, Internship Placement || …). Lower down: ENGAGEMENT (Notices, Surveys, Calendar), ADMIN (Documents, TalentLens Rubrics, Admin) and REPORTS (collapsible chevron) containing Launchpad.

**0:08 — The sidebar is scrolled to show the middle section.**
- Visible items: "Placements" (highlighted and partly cut off at the top), "Job Alerts" with an orange sparkle icon, then RELATIONSHIPS (Students, CRM), then ENGAGEMENT (Notices, Surveys, Calendar), then ADMIN (Documents…).
- A hover status bar shows `app.joinsuperset.com/#/talent-intelligence/rubrics`, the route behind TalentLens Rubrics.

**0:12 — The presenter clicks Engagement → Surveys.**
- The URL becomes `#/a/surveys`. The hover status bar shows `#/a/notices` for Notices.
- The page title is "Survey Forms", with the primary button "+ Create New Survey" at the top right (purple).
- Filter bar: "Status" dropdown (default "All"), "Type" dropdown (default "All") and "Search" with the placeholder "Start typing survey name...".
- A skeleton loader (5 rows) shows while the list loads.

**0:14 — The survey list loads.** Each card shows a form icon, the survey title, an action row and a status on the right.
- Action row: "👁 View form | 🗑 Delete (red) | View Report (dashboard icon) | Clone (copy icon, olive)".
- Status: a green check and "Published".
- Visible surveys:
  - "Microsoft || Intern to PPO offer || Acceptance or Rejection"
  - "Zscaler || Internship Opportunity || 2028 Graduating Batch"
  - "ICICI BANK || PPO OFFER || CONSENT SUBMISSION"
  - "Godrej PPO"

**0:24 — "+ Create New Survey" opens a modal.**
- The modal has a yellow header band with a monitor/checklist icon and a close "×" at the top right.
- Title: "Create a new form". Subtitle: "Create deceivingly simple, yet insanely powerful surveys".
- Field "Name of the form": a text input with the placeholder "e.g Recruiter feedback form".
- Field "Objective of the survey / Welcome text": a rich-text editor whose toolbar has Bold, Italic, Underline, bulleted list, numbered list, superscript, subscript, link and unlink.
- Buttons: "Create" (light blue, disabled while the name is empty) and "Cancel".

**0:26 — The presenter clicks into the name field.** A dark autocomplete list appears. It is almost certainly the browser's own form history, not Superset's. It shows earlier survey names, which reveal the CDC's naming convention "Company || Purpose":
- "VISA || Internship to PPO offer…"
- "AMAZON || Interview For Jan t…"
- "Walmart || Internship to PPO o…"
- "Zepto || Internship to PPO Off…"
- "I'mBesideYou || Mandatory Re…"
- "OIL INDIA || Accommodation…"

**0:28 — Leaving the field empty shows validation.** The input gets a red border and "Required field" in red appears below it.

**0:44 — The presenter types the name** (partial "Bajaj A…"). The text is centred in the input. "Create" turns solid blue. The Objective/Welcome text is left empty.

**0:56 — The presenter clicks Create.**
- A green toast appears at the top right: "Success! New survey added."
- The URL changes to `#/a/surveys/d507dfcd-b8ad-466d-b7b7-bcb36b5f8958/template`, so surveys use UUID ids and the builder is the `template` sub-route. The list is still visible behind it while the page loads.

**0:58–1:02 — The survey builder (Template tab) opens.**
- Header: a back chevron "<", the title "Bajaj Auto || PPO Consent", and a "Publish Survey" button at the top right (pale green, apparently disabled while the form has no controls).
- Tabs: "**Template**" (active, underlined) and "**Audience**".
- The left panel is titled "**Toolbar**" and holds 11 controls in a 2-column icon grid, scrollable (fully seen at 1:10 and 1:38):
  1. Multiple options, single answer
  2. Multiple options, multiple answers
  3. Text Answer
  4. Yes/No
  5. Rating (star icon)
  6. Dropdown
  7. Static Text
  8. Rich Text
  9. File Upload
  10. Sequence
  11. Date (calendar icon)
- The right canvas has a header card containing:
  - an amber clock icon with "The draft is not published";
  - a "👁 Preview" button at the top right;
  - a handshake illustration;
  - the survey title "Bajaj Auto || PPO Consent" with a pencil icon for inline rename.
- Empty state (illustration): "There are no sections to show! Select a control from toolbox to add to the form."

**1:10 — The toolbar is scrolled to show the bottom controls:** File Upload, Sequence, Date.

**1:12 — The presenter clicks "Yes/No".**
- A question card appears, headed "**1 Question | Yes/No**".
- On its left edge is a dark up/down arrow pair for reordering. On its right are "🗑 Delete" (red) and a blue gear icon (per-question settings; it was not opened).
- Below the card: a box with the placeholder "Type some concluding contents here..." (closing text) and a full-width blue "**Save Form**" button.
- "Publish Survey" is now solid green (enabled).

**1:14 — The card expands into edit mode.**
- A "Type question..." text area.
- A checkbox "**This question is mandatory**".
- A link "⊕ **Include a help text**".
- A preview of the answer buttons: "Yes" (blue) and "No" (red).

**1:22 — The presenter types "Do you accept the PPO" and ticks "This question is mandatory".**

**1:24–1:26 — The card collapses to summary mode** (1:24 shows the transition, with both views stacked).
- The summary reads "1 Question | Yes/No", then "Do you accept the PPO*" (the asterisk marks it mandatory), then small "Yes"/"No" chips.
- The cursor moves to "Preview". It is not visible whether "Save Form" was clicked before Preview.

**1:30 — Preview mode (the respondent's view).**
- The toolbar is hidden and the canvas is full width.
- An "✎ Edit form" button sits at the top right.
- Content:
  - the title "Bajaj Auto || PPO Consent" in bold, centred, with a divider below;
  - the legend "* - Mandatory questions";
  - the question with a red asterisk, "Do you accept the PPO", and two outlined buttons, "Yes" and "No".

**1:36 — The presenter clicks "Edit form"** and is back in the builder with the collapsed question card.

**1:38 — The toolbar is scrolled to the bottom again** (Text Answer, Yes/No, Rating, Dropdown, Static Text, Rich Text, File Upload, Sequence, Date).

**1:40 — The presenter clicks the "Audience" tab.** The URL becomes `#/a/surveys/<uuid>/audience`. A settings card holds three toggles, all OFF (red) by default:
- "**Make this survey public**": "Any Superset user with valid credentials will be able to submit a response to this survey."
- "**Allow multiple submission**": "If enabled, a survey audience member will be able to submit multiple responses for this survey."
- "**Allow edits before deadline**": "If enabled, a survey audience member will be able to re-open and update their submitted response any time before the submission deadline."

Below the card is the section "**Target Audience for this survey**" with a "**+ Add Audience**" link at the right, and a dashed info box: "Surveys can be taken for all the audience groups using a common link. Use the button for copying this link." with a "📋 **Copy Link**" button.

**1:44 — The presenter turns ON "Make this survey public"** (the toggle turns green).
- The "Target Audience for this survey" section and "+ Add Audience" disappear.
- The info box now reads "This is a public survey and can be taken using a common URL. Use the button for copying this link." with "Copy Link".

**1:44–2:28 — No further screen change was captured.** "Publish Survey" was not clicked on camera, or clicking it produced no visible change; we cannot tell which.

### Full Superset admin sidebar, as far as the frames show it
- (Top) **RECENT JOB PROFILES**: dynamic chips of recently opened job profiles.
- **RECENTLY VISITED PLACEMENTS**: dynamic list of cycles.
- (A section whose header is scrolled out of view): **Placements** (`#/admin/placements`), **Job Alerts** ✦ (marked new or AI). Other items may sit above Placements; they were not visible.
- **RELATIONSHIPS**: **Students**, **CRM**.
- **ENGAGEMENT**: **Notices** (`#/a/notices`), **Surveys** (`#/a/surveys`), **Calendar**.
- **ADMIN**: **Documents**, **TalentLens Rubrics** (`#/talent-intelligence/rubrics`), **Admin**.
- **REPORTS** (collapsible): **Launchpad**.

Route prefixes seen: `#/admin/...` (placements), `#/a/...` (surveys, notices), `#/talent-intelligence/...`.

---

## 2. Superset features shown

### F1. Surveys module: list page ("Survey Forms")
- **Superset name and path:** sidebar ENGAGEMENT → **Surveys**; page title "**Survey Forms**"; route `#/a/surveys`.
- **What it does:** lists every survey the CDC has created. Each card shows the title and status ("Published" with a green check; a draft status presumably also exists). Card actions: **View form**, **Delete**, **View Report**, **Clone**.
- **Controls:**
  - "Status" dropdown (default All; the other options were not shown);
  - "Type" dropdown (default All; options not shown, so Superset classifies surveys by type);
  - "Search: Start typing survey name...";
  - "+ Create New Survey";
  - a skeleton loader while loading.
- **Who uses it:** CDC admins. The surveys visible show the real uses at ISM:
  - PPO acceptance or rejection (Microsoft, ICICI, Godrej, Bajaj Auto, VISA, Walmart, Zepto);
  - interest in an off-portal internship opportunity (Zscaler, 2028 batch);
  - interview scheduling (Amazon);
  - mandatory registration (I'mBesideYou);
  - accommodation details (OIL INDIA);
  - the placeholder suggests a "Recruiter feedback form" too.
- **Our status: NOT IMPLEMENTED.**
  - There are no survey, form or consent tables, routes or pages. A grep for `survey|questionnaire|feedback form` in `CDC/backend/app`, `routes`, `database/migrations`, `CDC/frontend/app`, `components` and `lib` finds nothing. The only "consent" hits are the JNF/INF declaration `consentLogo` (e.g. `frontend/components/forms/shared/declarationchecklist.tsx:58`), which is unrelated.
  - The admin nav (`frontend/components/admin/adminshell.tsx:47-65`) has no Surveys entry. The student nav (`frontend/components/student/studentshell.jsx:43-50`) has no Surveys or Forms entry.
- **Naming:** we have nothing to rename. If built, use Superset's names: nav item "Surveys", page title "Survey Forms", button "Create New Survey", and card actions "View form / Delete / View Report / Clone".
- **Conflict check:** none for the list itself. See F5 and F7 for the conflicts in use.

### F2. Create-survey modal ("Create a new form")
- **Superset name and path:** Surveys → "+ Create New Survey" → modal "**Create a new form**", subtitle "Create deceivingly simple, yet insanely powerful surveys".
- **Fields:**
  - "**Name of the form**": required. Shows "Required field" on blur when empty. Placeholder "e.g Recruiter feedback form".
  - "**Objective of the survey / Welcome text**": optional rich text (B, I, U, bullets, numbering, superscript, subscript, link, unlink).
  - Buttons "Create" (disabled until a name is given) and "Cancel"; close ×.
- **Result:** the survey is created as a draft, the toast "Success! New survey added." appears, and the builder opens at `/a/surveys/<uuid>/template`.
- **Our status: NOT IMPLEMENTED.**
  - Closest pattern: the event dialog in `frontend/app/admin/events/page.jsx`, which uses the Phase 1 `RichTextEditor` (line 292) and stores the description as HTML but always displays it as plain text via `stripHtml` (D76).
  - A survey's welcome text would follow the same rule unless the owner wants rich rendering.
- **Naming:** use "Name of the form" and "Objective of the survey / Welcome text".
- **Conflict check:** none. The rich-text display rule is a D76-style choice (plain-text display) to keep.

### F3. Survey builder: "Template" tab with a "Toolbar" of 11 controls
- **Superset name and path:** Survey → tab **Template**; left panel "**Toolbar**"; route `/a/surveys/<uuid>/template`.
- **Controls (exact labels):**

  | # | Toolbar label | Meaning (inferred where not demonstrated) |
  |---|---|---|
  | 1 | Multiple options, single answer | radio |
  | 2 | Multiple options, multiple answers | checkboxes |
  | 3 | Text Answer | free text |
  | 4 | Yes/No | two-button Yes/No (demonstrated) |
  | 5 | Rating | star rating; scale size not shown |
  | 6 | Dropdown | single select |
  | 7 | Static Text | non-question display text |
  | 8 | Rich Text | either a formatted display block or a rich-text answer; not demonstrated |
  | 9 | File Upload | respondent uploads a file; limits not shown |
  | 10 | Sequence | likely "rank or order these options"; not demonstrated |
  | 11 | Date | date picker |

- **Question card**, demonstrated with Yes/No:
  - header "N Question | <Type>";
  - up/down reorder arrows on the left edge;
  - "Delete";
  - a gear (per-question settings, not opened);
  - in edit mode: "Type question...", the checkbox "**This question is mandatory**", "⊕ **Include a help text**", and a preview of the answer controls;
  - in collapsed mode: the question text plus "*" when mandatory, and the answer chips.
- **Form-level parts:**
  - a header card with draft state "**The draft is not published**";
  - "**Preview**";
  - the title with a pencil for inline rename;
  - a closing-text box "**Type some concluding contents here...**";
  - a "**Save Form**" button;
  - the empty state "There are no sections to show! Select a control from toolbox to add to the form."
  - Superset's wording "sections" suggests each control is a section.
- **Our status: PARTIAL**, through the per-drive application question builder only.
  - Backend:
    - table `posting_questions` (`backend/database/migrations/2026_09_27_000009_create_posting_questions_table.php`) with `question`, `qtype` enum `text|mcq_single|mcq_multi`, `options` JSON, `required`, `sort_order`;
    - validation in `backend/app/Http/Controllers/AdminPostingController.php:667-689` (max 20 questions, max 20 options, MCQ needs ≥2 distinct options);
    - sync at `AdminPostingController.php:637-662`;
    - questions are frozen after the deadline (`AdminPostingController.php:231-233`).
  - Frontend:
    - `frontend/components/admin/questionbuilder.jsx`, with type labels "Text answer / Choose one / Choose many" (line 24), "Required" checkbox (line 123), Move up/down (lines 126-139), "Remove question" (line 140) and "Add question" (line 154);
    - used in `components/admin/floatdialog.jsx:237` and `components/admin/posting/questionstab.jsx:47`.
  - **Missing compared with Superset:**
    1. A standalone form that is not tied to a drive. Ours exist only inside a `job_posting`.
    2. These control types: Yes/No, Rating, Dropdown, Static Text, Rich Text, File Upload, Sequence, Date.
    3. Help text per question.
    4. Welcome text and concluding text.
    5. Per-question settings (gear).
    6. A respondent-view Preview.
    7. Inline title rename.
    8. A draft/published state for the form.
    9. A limit above 20 questions, if needed.
- **Naming mismatches:**
  - "Text answer" vs Superset "Text Answer";
  - "Choose one" vs "Multiple options, single answer";
  - "Choose many" vs "Multiple options, multiple answers";
  - "Required" vs "This question is mandatory";
  - "Remove question" vs "Delete";
  - "Add question" (button) vs Superset's toolbar of clickable controls.
  - Recommendation: if one shared form engine is built for both surveys and application questions, adopt Superset's type labels in both. Otherwise the drive "Questions" tab can keep its shorter labels: they are clearer on phones and only internal admin text.
- **Conflict check:** the File Upload control means storing student uploads on the local disk (owner decision: local storage, no S3). That is acceptable, but the owner should set the type and size limits. The resume rule is PDF only, ≤2 MB.

### F4. Preview / respondent view
- **Superset name and path:** builder header "**Preview**" → full-width respondent view with "**Edit form**" to return.
- **Shows:** the bold centred title, the legend "**\* - Mandatory questions**", each question with a red asterisk when mandatory, and Yes/No as two outlined buttons. This is the closest the video gets to "how students see it". The real student-side page, submit button and confirmation were not shown.
- **Our status: NOT IMPLEMENTED** for surveys.
  - The nearest equivalent is the student apply panel, `frontend/components/student/applypanel.jsx:173-210`. It renders text, radio and checkbox questions with " *" on required ones.
  - The admin has no "preview as student" for drive questions either.
- **Naming:** Superset says "Preview" and "Edit form"; we have nothing to rename.
- **Conflict check:** none.

### F5. Audience tab: response settings
- **Superset name and path:** Survey → tab **Audience** (`/a/surveys/<uuid>/audience`), a settings card with three toggles, all off by default:
  1. "**Make this survey public**": any Superset user with valid credentials can respond, the target audience section is hidden, and the info box says "This is a public survey and can be taken using a common URL."
  2. "**Allow multiple submission**": one audience member may submit several responses.
  3. "**Allow edits before deadline**": the respondent can re-open and update a submission until the submission deadline. This implies surveys have a **submission deadline**. Where it is set was not shown; possibly at publish time or in the gear settings.
- **Our status: NOT IMPLEMENTED** for surveys.
  - The analogous rule exists for drive applications only. A student can change answers or withdraw until the deadline (`backend/app/Http/Controllers/StudentApplicationController.php:137-177`, `update`).
  - The `applications.answers` JSON column is in `backend/database/migrations/2026_09_27_000011_create_applications_table.php:22`.
- **Naming:** use Superset's toggle labels.
- **Conflict check:**
  - "Make this survey public" (any logged-in user) — **NEEDS OWNER DECISION** on which roles may answer a public survey: students only, or also recruiters. The placeholder "Recruiter feedback form" suggests Superset lets recruiters answer. Our model: recruiters see only their own drives and never receive student-facing publications.
  - Must a public survey still require login? Superset says "valid credentials", so yes. The owner should confirm that no anonymous, unauthenticated surveys are allowed.

### F6. Audience tab: target audience and common link
- **Superset name and path:** Audience tab → "**Target Audience for this survey**" with "**+ Add Audience**". The note says one survey can have several audience groups, all answering through one common link ("**Copy Link**").
- **Shown:** only the empty state. The Add Audience picker was never opened, so the filter options (batch, programme, branch, cycle, job profile applicants, offer holders…) are **unknown** from this video.
- **Our status: PARTIAL**, through event audiences only.
  - `events.audience_type` is an enum `all|branches|posting_applicants` with `audience_filter` JSON (`backend/database/migrations/2026_09_27_000016_create_events_table.php`).
  - Validation is in `backend/app/Http/Controllers/AdminEventController.php:113-136`.
  - The resolver is `backend/app/Models/CampusEvent.php:66-91` (`audienceQuery()`: every active student, programme/branch pairs, or live applicants of one posting).
  - **Missing:**
    - an audience model attached to a survey;
    - several audience groups per survey (events allow one type);
    - targeting by cycle or batch, and by offer holders. A "PPO consent" survey would naturally target students holding an `intern_ppo`/`ppo_offered` offer from that company, or the applicants or selected students of a drive;
    - a copyable deep link for students (needs a student route such as `/student/surveys/<id>`).
- **Naming:** our events UI says "audience" too; Superset adds "Target Audience" and "Add Audience". No rename needed for events.
- **Conflict check:**
  - **Mail.** Announcing a survey to its audience would be a new broadcast. It should follow the bulk-mail rule (BCC batches of 100, institute addresses only, sent at once).
  - **Reminders.** "Deadline reminder mails" are on the owner's excluded list (CDC_PORTAL_CONTEXT §11), so do not add survey reminder mails without the owner's approval.
  - **Copy Link.** Sharing a common link on WhatsApp or by personal email is fine only if the link requires login. It must never carry personal data in the URL (privacy rule).

### F7. Publish flow
- **Superset name and path:** builder header "**Publish Survey**". It is disabled while the survey is empty and becomes enabled once a control exists. Draft state: "The draft is not published"; list status "Published". There is also a "**Save Form**" button, so saving the draft and publishing are separate steps.
- **Shown:** the button was not clicked on camera, so any confirmation dialog, notification options or deadline prompt is **unknown**.
- **Our status: NOT IMPLEMENTED** for surveys.
  - The draft-then-Publish pattern exists for events: `POST /admin/events/{campusEvent}/publish` (`backend/routes/api.php:153`) and `AdminEventController.php:80-97`. It stamps `published_at`, refuses a re-publish, sends E6 through a queued job and writes the audit row `event.publish`.
  - A survey publish should reuse this pattern, including an audit row (owner rule: every admin write is audit-logged).
- **Naming:** use "Publish Survey"; it matches our event wording ("Publish").
- **Conflict check (important): "PPO Consent".** This video's survey asks students "Do you accept the PPO" (Yes/No). The owner's decisions say:
  - "Students cannot decline an offer in the portal; once selected, they accept." (§3 Results);
  - "PPO offered but not accepted: no block", with the offer type set by the admin on the Results console (`backend/app/Models/Offer.php:15-22`: `intern_ppo` = "PPO accepted (after internship)", `ppo_offered` = "PPO offered (not accepted)").

  Collecting PPO acceptance or rejection from students through a survey is a way for students to decline an offer in the portal. Wiring a "No" answer to the offer type or to blocking would change the blocking rules.

  **NEEDS OWNER DECISION** on three points:
  - (a) whether surveys may collect PPO consent at all;
  - (b) if so, whether answers stay plain information for the CDC, which then updates the offer on the Results console by hand (safest, "admin is god"), or should change `offers.offer_type` and blocks automatically;
  - (c) whether a consent survey should be launched from the offer or drive, pre-targeted to that company's PPO holders.

### F8. Survey list actions: View form, Delete, View Report, Clone
- **Superset name and path:** survey card in "Survey Forms".
  - **View form** opens the builder or preview.
  - **Delete** removes the survey; there may be a confirmation, not shown.
  - **View Report** is the responses view. It was never opened, so columns, charts and the export format are **unknown**.
  - **Clone** copies a survey as a template. The ISM naming shows the same PPO-consent form reused per company.
- **Our status: NOT IMPLEMENTED** for surveys.
  - Duplicate exists only for company JNF/INF forms: `POST /jnfs/{jnf}/duplicate` and `POST /infs/{inf}/duplicate` (`backend/routes/api.php:211,224`).
  - For responses, the closest pieces are:
    - drive answers per applicant in the admin dialog (`frontend/components/admin/posting/applicantstab.jsx:137-160`);
    - one Excel column per question in the applicant export (`backend/app/Services/ExportService.php:87-90`, formula-safe per D87).
  - A survey report should reuse `ExportService`: one sheet, a row per response, roll number, name, programme, branch, then `Q{n}:` columns.
- **Naming mismatches:** ours "Duplicate" (company JNF/INF) vs Superset "Clone". Ours: no "report". Superset "View Report".
- **Conflict check:**
  - Deleting a published survey that has responses is a hard delete of student data. Suggest following the blocks precedent ("never hard-deleted") or allowing delete only for drafts. **NEEDS OWNER DECISION.**
  - The "students never see counts" rule applies: the response count must be admin-only.

### F9. Survey "Type" and "Status" filters
- **Superset name and path:** Survey Forms filter bar, "Status" (All…) and "Type" (All…).
- **Shown:** only "All". The other options are unknown; "Type" may separate survey forms from other kinds such as feedback.
- **Our status: NOT IMPLEMENTED.**
- **Naming:** none.
- **Conflict check:** none.

### F10. (Context only) Superset admin navigation and naming
This video does not demonstrate these, but the sidebar is visible and matters for naming. Ours is in `frontend/components/admin/adminshell.tsx:47-65`: primary bar Dashboard, Cycles, Postings, Students, JNF Reviews, INF Reviews, More. The More drawer has three groups:
- **Placement:** Proposals, Resumes, Branch Changes, Events, Calendar, Analytics;
- **Company forms:** Companies, Policy Documents, Alumni Outreach;
- **Administration:** Notifications, Branch Manager, Audit Log, Settings, plus Manage Admins for super admins.

| Superset (section → item) | Ours | Match / note |
|---|---|---|
| (top section) **Placements** (page "Placements", blurb calls them "Placement Cycles", button "+ Add placement process") | "Cycles" nav; page heading "Placement Cycles" (`app/admin/placement-cycles/page.jsx:217`) | Mismatch. Nav was shortened from "Placement Cycles" to "Cycles" for width (D34). Renaming the nav to "Placements" fits the width budget; the page heading can stay "Placement Cycles", which Superset's blurb also uses. |
| "Recent Job Profiles" / job profile pages | Admin "Postings" nav; page title "Job Postings" (`app/admin/postings/page.jsx:66`); the student side already says "Job Profiles" (`studentshell.jsx:44`) | Mismatch on the admin side. Superset calls them **Job Profiles**. Our admin and student sides are inconsistent. |
| **Job Alerts** | none (CDC_PORTAL_CONTEXT §3 "Job alerts and preferences: later") | Not built; planned later. |
| RELATIONSHIPS → **Students** | "Students" | Same. |
| RELATIONSHIPS → **CRM** | "Companies" (Company forms group) | Mismatch, and probably a different scope (a CRM implies company contacts and outreach). Not verified from this video. |
| ENGAGEMENT → **Notices** (`#/a/notices`) | Admin "Notifications" is an in-app inbox only (`NotificationController` has just index/markAsRead/markAllAsRead, `backend/routes/api.php:57-59`). There is no admin-composed broadcast notice. | Mismatch; probably a feature gap (a notice board). Not shown in this video. |
| ENGAGEMENT → **Surveys** | none | Gap (this document). |
| ENGAGEMENT → **Calendar** | "Events" + "Calendar" (two items) | Superset shows no separate Events item in the sidebar; events probably live under Calendar. Not verifiable here. |
| ADMIN → **Documents** | "Policy Documents" | Possible match (unclear whether Superset Documents is the same thing). |
| ADMIN → **TalentLens Rubrics** | none | Not shown; out of scope here. |
| ADMIN → **Admin** | "Settings", "Manage Admins", "Branch Manager", "Audit Log" | Grouping difference. |
| REPORTS → **Launchpad** | "Analytics", "Dashboard" | Mismatch; Launchpad contents are unknown. |
| Section names: (unseen top), RELATIONSHIPS, ENGAGEMENT, ADMIN, REPORTS | Drawer groups: Placement, Company forms, Administration | Mismatch. If Surveys (and later Notices) are added, an "Engagement" group (Events, Calendar, Surveys, Notices) would mirror Superset. |

Superset also has a global "Search students" box in the top bar. We have none in the admin bar; it is a possible future item and was not demonstrated.

---

## 3. Gap summary

| Superset name | What to build | Priority | Conflicts |
|---|---|---|---|
| Surveys → Survey Forms (list) | Admin page `/admin/surveys`: list with Status/Type filters, search, "Create New Survey", and card actions View form / Delete / View Report / Clone; nav item "Surveys" | High | None (Delete: see below) |
| Create a new form (modal) | Name (required) + Objective/Welcome text (rich text, shown as plain text per D76); creates a draft | High | None |
| Template → Toolbar (11 controls) | Standalone form engine (`surveys`, `survey_questions`) with types Multiple options single/multiple, Text Answer, Yes/No, Dropdown, Date (High); Rating, Static Text, Rich Text (Medium); File Upload, Sequence (Low). Help text, mandatory flag, reorder, delete, concluding text, Save Form | High (core types) / Medium / Low | File Upload: owner to set type/size limits (local disk) |
| Preview / respondent view | Admin preview, plus a real student page `/student/surveys/[id]` with submit and confirmation; a student nav entry ("Surveys" or "Forms") | High | Student nav change must stay phone-friendly |
| Audience → Make this survey public | "Any signed-in user can respond" toggle | Medium | NEEDS OWNER DECISION: which roles (students only, or recruiters too); login always required |
| Audience → Allow multiple submission | Toggle; several responses per respondent | Low | None |
| Audience → Allow edits before deadline | Toggle plus a survey submission deadline (IST, D101) | Medium | None (mirrors the application rule) |
| Target Audience / + Add Audience | Several audience groups per survey, reusing `CampusEvent::audienceQuery` (all / programme-branch / posting applicants) plus cycle, batch and offer holders of a company | High | None |
| Copy Link | Common deep link to the student survey page (login-gated) | Medium | No personal data in the URL |
| Publish Survey | Draft → published, `published_at`, audit row; optional announcement mail (E-new) in BCC batches | High | No reminder mails (excluded list) unless the owner approves |
| View Report | Admin responses table plus Excel export via `ExportService` (formula-safe, `Q{n}:` columns), responder and non-responder lists | High | Counts admin-only |
| Clone | Copy a survey (questions + settings, not responses) as a new draft | Medium | None |
| Delete | Delete a draft; for published surveys with responses, archive or soft-delete | Medium | NEEDS OWNER DECISION (hard delete of student responses) |
| PPO consent use case ("Do you accept the PPO") | Possibly link a survey to an offer or drive; answers inform the CDC | High (CDC uses it often) | NEEDS OWNER DECISION: conflicts with "students cannot decline an offer in the portal"; must not change `offer_type` or blocks automatically without approval |
| Notices (sidebar only) | Admin-composed broadcast notice board | Medium (not shown in this video) | Mail rules (BCC batches, institute email) |

---

## 4. Rename list

| Where it appears in our UI/code | Our current name | Superset name |
|---|---|---|
| Admin top bar / drawer (`components/admin/adminshell.tsx:48`) | Cycles | Placements |
| Admin cycles page heading (`app/admin/placement-cycles/page.jsx:217`) | Placement Cycles | Placements (Superset's blurb also says "Placement Cycles"; keep as subtitle) |
| Admin top bar / drawer (`adminshell.tsx:49`) | Postings | Job Profiles |
| Admin postings page title (`app/admin/postings/page.jsx:66`) | Job Postings | Job Profiles |
| Admin drawer (`adminshell.tsx:59`) | Companies | CRM (scope differs; rename only if built out as a CRM) |
| Admin drawer (`adminshell.tsx:62`) | Notifications (an inbox) | Notices is a different feature in Superset (broadcast); keep "Notifications" for the inbox |
| Admin drawer (`adminshell.tsx:54-55`) | Events + Calendar | Calendar (Superset sidebar has no separate Events) |
| Admin drawer (`adminshell.tsx:60`) | Policy Documents | Documents (only if the scope matches; uncertain) |
| Admin drawer (`adminshell.tsx:56`) | Analytics | Reports → Launchpad (uncertain) |
| Admin drawer group names (`adminshell.tsx`, `group:` values) | Placement / Company forms / Administration | (top) / Relationships / Engagement / Admin / Reports |
| Question builder type labels (`components/admin/questionbuilder.jsx:24`) | Text answer / Choose one / Choose many | Text Answer / Multiple options, single answer / Multiple options, multiple answers |
| Question builder checkbox (`questionbuilder.jsx:123`) | Required | This question is mandatory |
| Question builder tooltip (`questionbuilder.jsx:140`) | Remove question | Delete |
| Company JNF/INF action (`backend/routes/api.php:211,224`, company UI) | Duplicate | Clone (the survey wording; JNF/INF is a separate feature, so optional) |
| (new) survey feature | — | Surveys / Survey Forms / Create New Survey / Template / Audience / Toolbar / Publish Survey / Save Form / Preview / Edit form / View form / View Report / Clone |

Note: renames are UI labels only. Owner rule §10: never rename existing routes, columns or response keys, and ask the owner before any product-behaviour change. Width: the D34/D98 top-bar budget was measured with "Cycles" (6 chars). "Placements" (10 chars) and "Job Profiles" (12 chars vs "Postings", 8) add about 56 px at roughly 7 px per character, so re-check the 1024 px layout.

---

## 5. Uncertain

- **Video end.** No frames after 1:44 (video 2:28). Whether "Publish Survey" was clicked, and any confirmation, deadline prompt or notification option it shows, is unknown.
- **Add Audience** was never opened, so the audience filter options are unknown.
- **View Report** was never opened, so the response view, charts and export format are unknown.
- **Status and Type dropdowns:** only "All" is visible; their option lists are unknown.
- **Question gear icon** (per-question settings) was not opened.
- **Toolbar controls** not demonstrated: Rating (scale), Dropdown, Static Text vs Rich Text (display block or answer type), File Upload (types and limits), Sequence (ranking or something else), Date. Their behaviour is inferred from labels only.
- **Submission deadline:** the Audience text mentions one, but no deadline field appears in any frame.
- **Save Form:** not visible whether it was clicked before Preview, or whether Publish auto-saves.
- **Autocomplete dropdown at 0:26:** it looks like the browser's form history, not a Superset suggestion feature. Treat the list only as evidence of the CDC's naming convention ("Company || Purpose").
- **Sidebar:** the section header above "Placements", and any items above it, were scrolled out of view in every frame. "Job Alerts" has an orange sparkle (new or AI badge). The contents of CRM, Notices, Calendar, Documents, TalentLens Rubrics, Admin and Launchpad are not shown in this video.
- **Student view:** the student-facing survey page (where students find surveys, notification or email, submission confirmation) is not shown. The admin "Preview" is the only respondent view.
- **"Make this survey public"** says "Any Superset user with valid credentials". Whether that includes recruiters, or students of other institutes on Superset, cannot be told from the frames.
