# PHASE 2 — Student Portal: Requirements Questionnaire

**Project:** IIT (ISM) Dhanbad CDC Placement Portal — Phase 2 (Student side, Superset-inspired)
**Stack (locked):** Laravel 12 + Sanctum backend · Next.js 16 frontend · **MySQL** for the DB · **JavaScript (not TypeScript) for all NEW frontend code** · MUI theme (maroon/navy) kept from Phase 1.

**How to answer:** Every question has a recommended default marked ⭐. If you agree, just write `agree` on the `Answer:` line. If not, write your choice / your own answer. Questions marked 🔴 are **blocking** — the data model or core flow cannot be finalized without them. Questions marked 🟡 affect a feature but not the foundation. Short free-text answers are fine everywhere.

Sources used to draft this: your 20 requirements, the Phase 1 code audit (PROJECT_CONTEXT.md), the Superset admin-panel video you sent, the public Superset student portal, and the official IIT (ISM) CDC rules PDF ("Rules for Students registered in CDC for Internships/Placements").

---

## Section 0 — Foundation decisions (must be settled first)

**0.1 🔴 MySQL migration.** You said the DB must now be fully MySQL. The current code defaults to SQLite and there may be real Phase 1 data (companies, JNFs/INFs) in a SQLite file on the machine that runs it.
- (a) ⭐ Fresh MySQL database (`iitism_placement`), re-run all migrations + seeders; Phase 1 data, if any, is test data and can be discarded.
- (b) There is real Phase 1 data that must be migrated from SQLite into MySQL.
- Answer:a

**0.2 🔴 JavaScript for new frontend code.** Phase 1 frontend is TypeScript with `strict: true`. Next.js supports mixing: old `.tsx` files stay untouched, all new student-side files are written as `.js`/`.jsx` (tsconfig gets `allowJs: true`). Two files, however, **must** be edited to add the student role and they are TS today: `types/next-auth.d.ts`, `auth.ts`, `proxy.ts`.
- (a) ⭐ Mix: keep existing `.tsx` as-is, write all new pages/components in `.jsx`, make the minimal TS edits in those 3 shared files.
- (b) Convert the whole frontend to JavaScript (large, risky, touches every Phase 1 file).
- Answer:a

**0.3 🔴 Placement Cycles — adopt Superset's core concept or not?** In the video, *everything* in Superset hangs off a **Placement Cycle** ("Full-Time Placement for 2026-27 (2027 Pass Out Batch)", "Internship Placement 2026-27 (2028 Batch)…") with its own allowed programs/batches, policy rules, and OPEN/CLOSED status. Phase 1 has no cycle concept — each JNF/INF just carries a `graduatingBatch`. This is the single biggest architectural decision of Phase 2.
- (a) ⭐ Introduce a `placement_cycles` table (name, type FT/Internship, start–end, status, allowed programmes+batches, per-cycle policy fields). Admin assigns each accepted JNF/INF to a cycle; students enrol into cycles they're eligible for. This matches Superset and makes stats (req 17), blocking (req 9), and rules per season clean.
- (b) Skip cycles for now: treat the JNF/INF `graduatingBatch` + form type as an implicit cycle. Faster to build, harder to do per-season policy/stats later.
- Answer:a

**0.4 🟡 Fixing Phase 1 security holes while building Phase 2.** The audit found serious ones that directly affect a student portal: public `POST /api/auth/admin/register` (anyone can mint an admin), companies able to set their own `status`/`admin_remarks` (a company could self-accept and appear to students), never-expiring tokens, and the unauthenticated PDF proxy. 
- (a) ⭐ Fix these as part of Phase 2 (they're small and Phase 2 is unsafe without them).
- (b) Log them; fix later.
- Answer:a

**0.5 🟡 Email volume.** Reqs 7 & 16 mean one JNF approval can trigger thousands of student emails. Phase 1 sends all mail synchronously via Gmail SMTP (blocks the request; Gmail caps ~500–2000 mails/day).
- (a) ⭐ Move student bulk mail to Laravel's queue (database driver already configured, worker already in `composer dev`) and confirm you can get an institutional SMTP account (e.g. cdc@iitism.ac.in relay) with a higher limit.
- (b) Keep Gmail + synchronous for now (works only for small pilots).
- Answer, and roughly how many students will be on the portal (e.g. ~1,500? ~6,000?): for now can admin have option select how mails will be sent give option for both and around 3000 students will be there at any cycle at max so keep this in mind but admin has last right not entirely thorguh env

**0.6 🟡 Where will this run in production (server/hosting known yet)?** Affects file storage for up to 8 resumes × N students (disk vs S3), and the mail choice above.
- Answer: for now disk as to test but not implement any other for now

---

## Section 1 — Student accounts (your reqs 1–3)

**1.1 🔴 How does the admin create accounts?** Creating 1000+ students one-by-one through a form is impractical.
- (a) ⭐ Both: single "Add student" form **and** bulk Excel/CSV upload (roll no, name, institute email, programme, branch, batch, CGPA, backlogs, gender, …) with a preview + per-row error report before committing.
- (b) Only single-student form.
- (c) Only bulk upload.
- Answer: a

**1.2 🔴 What is the student's login username?** 
- (a) ⭐ Institute email (`rollno@iitism.ac.in` style).
- (b) Roll number.
- (c) Either.
- Answer: b

**1.3 🟡 Temp password flow (req 2).** Email with username + temporary password is sent on creation.
- (a) ⭐ Random temp password emailed; student is **forced to change it on first login**.
- (b) Like Phase 1 admin invites: random password + a password-reset link as the invitation (student sets own password via the link; nothing temporary to type).
- Answer: b

**1.4 🔴 Student profile fields.** Please confirm/edit this list — it drives the `student_profiles` table. Proposed:
`roll_no (unique), full_name, institute_email, personal_email, phone, programme, branch, graduating_batch (year), current_cgpa, ongoing_backlogs, total_backlogs, gender, date_of_birth, category?, 10th %, 12th %, profile_photo, city/home_state?, PWD status?`
- Which of these do you NOT want, and what's missing (e.g. semester-wise SGPA, gap years, JEE/GATE rank)?
- Answer: actually what will happen as of now is a student will be filling form and can sit in intern cycle and placement cycle and their cgpa will be syc with our inhouse db so it will be prefilled student cant change it any how so solve this as i think only unique no is good as we can store he/she has participated in how many placement / internscycle and he will be able to see its preve data also there

**1.5 🔴 Who edits what on the profile (req 3)?** Proposed split — **admin-only fields:** roll no, programme, branch, batch, CGPA, backlogs, gender, name, institute email (i.e. everything eligibility depends on). **Student-editable:** personal email, phone, photo, address, skills/links (LinkedIn/GitHub).
- (a) ⭐ As proposed.
- (b) Students can *submit* changes to locked fields, admin approves them (Superset's "Student Data Verification" model).
- Answer: wee are planing to make a resume verification step which will verify student resume but keep in 2nd part for now else is fine as proposed

**1.6 🟡 CGPA/backlog updates over time.** These change each semester. How will admin update them — re-upload a bulk Excel that matches on roll no ⭐, edit per student, or both?
- Answer: actually department and all will not be changed but it will auto sinc so keep it seperate for now we will tell you just add an option to change branch as some student may do int mtech or double major so they can request admin for branch change but cgpa and backlog will be auto sinc from iitism

**1.7 🟡 Profile completeness gate.** Superset can require photo/resume before enrolling. Should a student be blocked from applying until profile is complete + at least one **approved** resume exists? ⭐ yes / no
- Answer:no , just mark that student has applied form an unverified resume and show a banner to student to get it verified asap and if verified at any point remove it

**1.8 🟡 Can admin deactivate/suspend a student account (separate from placement-blocking in Section 6)?** ⭐ yes
- Answer: yes they can 

---

## Section 2 — Resumes (your reqs 4–5)

**2.1 🟡 Format & size.** ⭐ PDF only, max 2 MB per file. Agree, or allow doc/docx too?
- Answer: pdf only 2 mb

**2.2 🔴 Verification workflow.** Proposed states: `pending → approved / rejected (with admin remark)`. Student sees the remark, fixes, re-uploads (new upload of that slot goes back to `pending`).
- (a) ⭐ As proposed.
- (b) Simpler: uploaded resumes are visible immediately; admin can retroactively reject.
- Answer: a

**2.3 🟡 While a resume is pending, can the student use it to apply?** ⭐ No — only `approved` resumes can be attached to an application.
- Answer: yes but that mark thing should be visible to admin all time in that perticular drive

**2.4 🟡 Resume labels.** Student names each of the up-to-8 resumes ("SDE Resume", "Core Resume", "Data Resume") ⭐, or auto-named Resume 1–8?
- Answer: student name each of the up to 8 resumes

**2.5 🟡 Locking after use.** If a resume was used in an application, can the student still replace/delete that resume file?
- (a) ⭐ No — a resume attached to any active application is locked (Superset behaves this way); the applied version is what the company sees.
- (b) Yes, replacement updates it everywhere.
- Answer: a

**2.6 🟡 Should admin resume-verification be per-resume or per-student (approve all 8 at once)?** ⭐ per-resume, with a queue page like the JNF review queue.
- Answer: a

---

## Section 3 — Job visibility, eligibility & applying (your reqs 6–8)

**3.1 🔴 Does `accepted` = visible to students, or is there a separate "publish/float" step?** In the video, floating a job to students is deliberate. Recommended: after accepting a JNF/INF, admin gets a **"Float to students"** action where they set the **application deadline** (the existing `application_deadline` column is unused today!) and confirm the eligibility snapshot; only then it becomes visible + eligible-branch emails go out (req 7).
- (a) ⭐ Separate float step with deadline set at float time.
- (b) Accepted forms appear to students immediately; deadline must then be captured during admin review.
- Answer: a

**3.2 🔴 Eligibility enforcement.** The JNF/INF carries branch-wise CGPA, backlog allowance, gender filter, batch. When a student views jobs:
- (a) ⭐ Student sees **all floated jobs in their cycle** with a clear "Eligible / Not eligible (reason)" badge, and the **Apply button is hard-blocked** server-side for ineligible students (this is what Superset does).
- (b) Ineligible students don't see the job at all.
- Answer: lets do mix of this like if any students branch is eligible but he/she is not eligible because he has already accepted any offer or his cgpa is low then he should be able to atleast see this but it should be there like they are not eligible because of this and not apply for this

**3.3 🔴 Which student fields are checked for eligibility?** Proposed: programme+branch selected in the form, CGPA ≥ branch cutoff, backlogs vs `backlogsAllowed`, gender vs `genderFilter`, batch = `graduatingBatch`, and **not placement-blocked** (Section 6). Anything else (e.g. 10th/12th %, active debarment list)?
- Answer: yes you proposed + 10th/12th %, active debarment list

**3.4 🔴 Backlog semantics.** The form has a single yes/no `backlogsAllowed` per branch, but you track backlogs as numbers, and Superset distinguishes **ongoing** vs **total** backlogs (seen in the video's Allowed Programs screen). Define the rule: if `backlogsAllowed = No`, block when ongoing backlogs > 0? total > 0? both zero required?
- Answer: keep option to add both active backlog and total backlogs and ask for both in new phase 2 and also make required changes in phase 1

**3.5 🟡 Application content.** When applying, the student:
- (a) ⭐ Picks one of their approved resumes — that's it (Superset-style one-click apply).
- (b) Also answers optional per-job questions set by admin/company (adds a form-builder — more work).
- Answer: b (admin can add some required and optional questions it is in hand of admin and can add any kind of questiosn like mcq or fill in the blank)

**3.6 🔴 Withdraw/edit before deadline (req 8).** Proposed: until the deadline, a student can **change the attached resume** or **withdraw**; after withdrawing they may re-apply while the deadline is open; at deadline everything freezes. Superset also has a per-cycle config "Prohibit students from withdrawing applications" — want that toggle too? ⭐ yes to all
- Answer: in this go as proposed not superset one 

**3.7 🟡 The official CDC rule says withdrawal from a *process* needs written notice ≥5 days before, allowed once.** Is that rule about the interview stage (handled offline / by admin) rather than the online "un-apply before deadline"? Confirm that in-portal withdrawal before deadline is free, and later-stage withdrawal is an admin-recorded action.
- Answer: yes this is maily in case when a student is selected in any process then he is blocked form any further processes even he has applied or is in an interview stage so in that case admin only has the flaged for those student in other applied jobs that admin can remove that student and that perticular company gets notified if admin want to do that and it is deffault that company is notified and asked for some new students  to  add in that companies process in place of that student as he has taken other offer 

**3.8 🟡 Should students see the number of applicants on a job? ⭐ no (admin only).**
- Answer: NOOOOOOOOOOOOOOOOOOOOOOOOOOO 

**3.9 🟡 Job Alerts / preferences.** Superset has "Job Alerts" and "Allow students to set job application preferences". In scope for Phase 2, or later? ⭐ later
- Answer: later

---

## Section 4 — Selection pipeline & round tracking (your reqs 12–14, 20)

**4.1 🔴 Where do the round names come from?** Each JNF/INF already contains `selectionRounds` (OLT, GD, Tech Interview, HR…). Proposed: when a job is floated, its enabled selection rounds become the **pipeline stages** for that job, and each application tracks per-round status exactly as you wrote: `Eligible → Applied/Not Applied → per round: Appeared? → Selected/Not Selected/Waitlisted → Final: Selected/Waitlist/Not Selected`. Admin advances students round by round.
- (a) ⭐ As proposed (rounds come from the form's selectionRounds).
- (b) Admin defines rounds manually per job after floating.
- Answer: a+ admin can add or remove any kind of round from theese selection rounds 

**4.2 🔴 How are shortlists entered (req 12)?** Companies today have portal accounts. Options:
- (a) ⭐ **Both**: company can upload/select their shortlist per round from within their portal (from the applicant list), which lands as a *pending shortlist* the **admin reviews and publishes** (matches "selected by company, posted by admin"); AND admin can do it directly by pasting/uploading roll numbers (Excel/CSV) for speed.
- (b) Companies never touch it — CDC receives shortlists by email and admin uploads roll-number lists only.
- Answer: a+ admin only uploads the roll numbers in excel file and company never gets the access to publis it to directly to students only admin can publish it directly to students 

**4.3 🟡 Until admin "publishes" a round result, students see nothing — correct? ⭐ yes.** And on publish: in-app notification + email to affected students only, or to all applicants?
- Answer: YES + regret mail for other students

**4.4 🔴 Waitlist (req 13).** Proposed: a round/final result can mark students `waitlisted` with a **rank order**; admin can later promote a waitlisted student to selected (which triggers the same result flow incl. block decision). Agree? Any auto-promotion rules?
- Answer: admin and company can both add students in waitlisted and can remove them too but only admin can publish it and also there will be an auto promotion after every interview and this will be published by admin also on rank order company can put it in any order and select in any order or randomly also

**4.5 🔴 Adding candidates after shortlist announced (req 14).** You asked for a defined process. Proposal: company (or admin) submits an **"Addendum shortlist"** for a round → it's flagged distinctly, requires admin approval, the added students are notified, and the round's history logs it as an addition (audit trail via the existing `form_status_histories` pattern). Students already rejected in that round can/cannot be added back?
- Answer: added back by admin but after informing company and through a special button and protocal

**4.6 🟡 Attendance tracking.** Your req 20 includes "Appeared for OLT – Yes/No". Who marks attendance — admin bulk-marking per round ⭐, or is a "did not appear" simply inferred from the company's list? (This also feeds any absence-penalty rule, see 6.6.)
- Answer: admin only

**4.7 🟡 Can a student see their own per-round trail (applied → OLT selected → GD … ) on their dashboard (req 18)? ⭐ yes — same data, student-scoped.**
- Answer: yes

---

## Section 5 — Results, offers & blocking (your req 9 + CDC rules)

**5.1 🔴 Result announcement flow.** Proposed: admin marks final selections for a job → for each selected student chooses offer type and **whether the student is now blocked** from future drives (your req 9) → publishes → emails+notifications go out → stats update.
- Agree? And should "blocked" be *suggested automatically* from the offer type (per 5.2's matrix) with admin able to override? ⭐ yes, auto-suggest + override.
- Answer: yes

**5.2 🔴 The blocking matrix.** From the official CDC rules ("One-Student-One-Job") and your example, please confirm/correct each row (this becomes hard-coded default behaviour):

| Offer type | Effect on future eligibility (default) |
|---|---|
| Internship only (pre-final year) | Still eligible for FT drives next season; **blocked** for further internship drives this cycle? (confirm) |
| Internship + PPO (accepted PPO) | **Blocked** from FT placement drives (CDC rule: accepting PPO bars final-year process) — but your req 9 example says Intern+PPO *may remain eligible*. **Please resolve this contradiction precisely.** |
| Internship + PPO (PPO offered but NOT accepted) | Eligible for FT drives? Blocked from that company's FT process? |
| Full-time selected | **Blocked** from all further FT drives (one-student-one-job) |
| FT selected in a "Dream/Level" category | Any second-offer exception? Superset's policy engine allowed "max 2 offers" and level-based rules — do CDC rules ever allow a second (dream) offer? If yes, define the CTC threshold/level rule. |
| Declined an offer after selection | Debarred permanently (CDC rule) — should the portal have a "debarred" state distinct from "placed-blocked"? |

- Answer (row by row): yes but in that perticular cycle as for now in final year placement cycle also has internsip
yes if ppo is acepted then blocked for all but inter+ppo is not blocked
yes no not blocked for any ft process if not accepted ppo
yes if accepted any fulltime or intern + fulltime then blocked form all activiteis ,this intern+fulltime is not mentioned as this is new keep it in mind
no dream offer or any thing but admin can any time unblock any candidate 
no option to decline if applied by student then have to accept only
also one extra rule in case of ft drive final years can have intern+performance ppo that will block form further intern opurtanities in that cycle but he is eligible for all the ft drive 

**5.3 🟡 Where does blocking apply?** Per placement cycle (blocked in FT 2026-27 but fine in a future cycle) ⭐, or globally on the student until admin unblocks?
- Answer: In perticular cycle only as ft is last proces for students 

**5.4 🟡 Offer details recorded.** For stats (req 17) each selection should store: company, job, offer type (FT/Intern/PPO), CTC (and stipend for interns), date. CTC source = the JNF's programme-wise CTC for that student's programme ⭐, or entered manually by admin at announcement?
- Answer: in JNF and an option to edit it manually by admin

**5.5 🟡 Superset also has a placement **credit-score / penalty system** (initial credit, penalties for withdrawing/absence, auto-block below threshold, N-absents auto-block, hours-blocked) — seen in your video's Additional Configurations. In scope for Phase 2, or skip? ⭐ skip for now (manual admin blocking covers it), unless CDC actively uses it.
- Answer:  skip for now 

**5.6 🟡 "Provide all rules followed by CDC":** I've drafted from the official public PDF (one-student-one-job, PPO bars final placement, decline = debarment, no-decline companies, ≥10-min-late debarment, 5-day withdrawal notice). **Please attach the current internal CDC policy document** (the repo already ships `IIT_ISM_CDC_Policy.pdf` — is that the current one?) and list any rule the portal must *enforce automatically* vs merely *display*.
- Answer: i think all rules i have mentioned and at bloking matrix above result admin has option to block students selected in that perticular drive to block in what way ft/intern +ft/ ppo/intern+performance ppo 

---

## Section 6 — Notifications & emails (your reqs 2, 7, 16)

**6.1 🔴 Exact email triggers.** Confirm this list (each = email + in-app notification): account created (username+temp password) · new job floated → **only students of eligible branches** · application confirmation · shortlist/round result published (affected students) · final result published · event announced (eligible/all?) · resume approved/rejected · profile data changed by admin? · deadline reminder (e.g. 24h before, to eligible-but-not-applied students)?
- Which to cut / add? Is the deadline-reminder wanted (⭐ yes)?
- Answer: no deadline reminder needed else all thing is fine

**6.2 🟡 Send to institute email, personal email, or both?** (Superset has a toggle "Enable job profile related emails to student's personal email".) ⭐ institute email always, personal email as an optional per-cycle toggle.
- Answer: institute email only

**6.3 🟡 Digest vs instant:** every floated job = instant email ⭐, or a daily digest option?
- Answer: at the time of posting job 
---

## Section 7 — Events (your req 19)

**7.1 🟡 Event fields.** Proposed: title, type (PPT/workshop/webinar/other), company (optional link to a company), date-time, venue/meeting link, description (rich text), audience (all students / by programme+branch / applicants of a specific job), attachments?
- Confirm/edit:  yes this make it default and editable optional things by admin and also not all this have to be filled by admin 

**7.2 🟡 Do events need RSVP/attendance marking, or announce-only? ⭐ announce-only for Phase 2.**
- Answer: announce only can be done through announcement mail also 

---

## Section 8 — Data export (your req 15)

**8.1 🔴 "Excel with profiles + attached resumes."** Excel can't embed PDFs. Options:
- (a) ⭐ Excel of applicant profiles (all profile columns + application status + a hyperlink column to each chosen resume) **plus** a one-click **ZIP of the resumes** named `ROLLNO_Name.pdf`.
- (b) Excel with links only.
- Answer: The simpler alternative that gives the identical experience: the resumes are already sitting on your own Laravel server. Put a portal link in the Excel instead — e.g. https://portal.iitism.ac.in/api/resumes/{token} — a Laravel route that streams the PDF. You can make these signed URLs (Laravel has this built in: URL::temporarySignedRoute()) so each link works without login but expires after, say, 30 days, and can even be scoped per company. Zero external dependency, no Google account, and access stays under CDC's control. Clicking a link in the Excel opens the PDF in the browser just like a Drive link would.

**8.2 🔴 Who can export what?** Admin: everything ⭐. Company: only applicants of **their own floated jobs**, only **after the deadline closes**? and only admin-approved resume + non-sensitive profile fields? Define what a company must never see (phone? personal email? category? CGPA?).
- Answer: admin and company can both export any thing related to them like company can exprot it at any time and all required data also

**8.3 🟡 Keep the existing one-row CSV JNF/INF export as-is? ⭐ yes.**
- Answer:yes

---

## Section 9 — Dashboards (your reqs 17–18)

**9.1 🔴 Admin dashboard metrics — confirm the list:** total students (by programme/branch/batch), placed / unplaced counts + %, companies completed vs ongoing drives, offers made, highest / average / median / lowest CTC, internship stats (stipend high/avg), branch-wise placement % table, applications-over-time chart (like Superset's homepage), gender split?  Scoped **per placement cycle** (if 0.3 = yes) with an all-cycles overview?
- Cut/add: yes add this and you have free hand in this make it as usefull as possible by showing charts and all thing in different cycles 

**9.2 🟡 "Placed %" denominator:** all students in the cycle, or only *registered/eligible* students? (CDC reports usually use registered.) 
- Answer: all students data we have 

**9.3 🟡 Student dashboard (req 18):** profile completeness, resume statuses, applications with live per-round status, upcoming deadlines & events, notifications. Anything else (e.g. "eligible jobs you haven't applied to" nudge ⭐)?
- Answer: yes nudge to eligible jobs they have not applied yet 

also can we have an calander thing wtith all deadlines and event in admin and student if they have any upcomming event 

**9.4 🟡 Public/committee reporting export (PDF/Excel of the season summary) — in scope? ⭐ later.**
- Answer: later we will make this in phase 2

---

## Section 10 — Company-side additions

**10.1 🔴 Summary of new company-portal capabilities implied by your reqs — confirm each:** view applicant list per floated job (after deadline? or live?) · download export (8.2) · propose round shortlists & waitlist (4.2) · propose post-announcement additions (4.5) · see event/PPT schedule? Anything else companies get in Phase 2?
- Answer: yes company is also able to see this at heir side but only for approved inf and jnf accepted side

**10.2 🟡 Should companies see student contact details before final selection? ⭐ no (roll no, name, resume, academic data only).**
- Answer:no only roll no, name, resume, academic data only which is selected by admin if he wants company to see it

---

## Section 11 — UI & navigation

**11.1 🟡 Student portal nav (Superset-inspired, our theme).** Proposed pages: Dashboard · Job Profiles (list with eligibility badges, filters, detail page reusing the existing JNF/INF preview component) · My Applications (per-round tracker) · My Resumes · Events · Notifications · Profile. Third login variant `/auth/login/student`. Colour: Phase 1 uses maroon for JNF/company and navy for INF — pick a student accent (⭐ keep maroon as primary, it's the institute colour).
- Confirm/edit: yes this all pages are required but  keep maroon as primary and also  keep our ui taste in ther with all good ui in student side be a little creative if you want here 

**11.2 🟡 Follow Superset's *look* (left sidebar, card lists) or keep Phase 1's top-AppBar shell for consistency? ⭐ keep Phase 1's shell pattern (consistent, less rework) with Superset-style content layouts.**
- Answer: keep it same as superset with our ui taste and with all good design

**11.3 🟡 Any mobile-first requirement (students will mostly use phones)? ⭐ responsive like Phase 1, no separate app.**
- Answer: yes responsive and good design also required for mobile

---

## Section 12 — Rollout & priorities

**12.1 🔴 Deadline / milestone:** when does the student side need to go live for the current season, and is there a pilot batch?
- Answer: not live by now 



**12.3 🟡 Seed/demo data for testing (fake students, one full drive) — want a seeder? ⭐ yes.**
- Answer: yes make it with as much real data as possible 

---


also keep in mind that admin is god he should have every right and only admin have only right to make changes to any things and it should be loged as there can be multiple admis that which admin has made which changes 