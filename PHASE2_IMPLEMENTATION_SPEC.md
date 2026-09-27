# PHASE 2 IMPLEMENTATION SPEC — IIT (ISM) CDC Placement Portal: Student Side
### The complete, binding instruction file for Claude Code. Version 1.0 — 2026-09-27

---

# PART A — HOW YOU (CLAUDE CODE) MUST OPERATE

You are implementing Phase 2 (the Student side) of an existing, working placement portal. This file is the **single source of truth**. Everything you need to decide has already been decided and is written here. Follow it exactly.

## A1. Absolute rules — read before every session

1. **NO ASSUMPTIONS. NO HALLUCINATIONS.** If this spec does not answer a question and the existing code does not answer it, **STOP**, write the question into `PHASE2_PROGRESS.md` under `## BLOCKED / QUESTIONS FOR OWNER`, and tell the user. Never invent a requirement, an endpoint, a field, or a behaviour.
2. **Read before you write.** Before creating or editing any file, read the existing neighbouring code (the equivalent Phase 1 controller/page/component) and copy its patterns exactly. `PROJECT_CONTEXT.md` in the repo root describes every Phase 1 file — consult it constantly.
3. **Never break Phase 1.** The company/admin JNF-INF flows must keep working after every single milestone. Never edit an already-run migration; new schema = new migration files. Never rename existing routes, columns, or response keys. The ONE approved Phase 1 behaviour change is the backlog-numbers change in Milestone M4 (spec'd fully there) and the security fixes in M0.
4. **Tech stack is locked:**
   - Backend: PHP 8.2, Laravel 12, Sanctum bearer tokens. Business logic in Controllers; cross-cutting concerns in `app/Services/*Service.php`. No Repositories/Actions/DTO layers — the codebase doesn't use them.
   - Database: **MySQL ONLY** (database name `iitism_placement`). Never rely on SQLite behaviour. Set `DB_CONNECTION=mysql` in `.env`. Tests keep running on in-memory SQLite as configured in `phpunit.xml` — that is fine and must keep passing.
   - Frontend: Next.js 16 App Router (params are Promises — use `use(params)`), React 19, MUI 6, NextAuth v5 beta.
   - **All NEW frontend files are JavaScript** — `.js` / `.jsx`, never `.ts`/`.tsx`. Existing `.tsx` files stay TypeScript. The only TS files you may edit are: `types/next-auth.d.ts`, `auth.ts`, `proxy.ts`, `next.config.ts`, plus the minimal Phase 1 files that Milestones M0/M4 name explicitly. Add `"allowJs": true` to `tsconfig.json` in M0.
   - Styling: MUI `sx` prop only. Theme from `lib/theme.ts` (maroon `#7B1113` primary, navy secondary). No Tailwind classes, no CSS modules.
   - Files: components in **all-lowercase filenames** with PascalCase default exports (Phase 1 convention: `jnfformpro.tsx` → `JnfFormPro`). Routes: `app/<area>/<resource>/[id]/page.jsx`. 2-space indent JS, 4-space PHP.
   - API conventions: JSON keys `snake_case`; success `{message, <resource>}`, collections `{<resources>: [...]}` unpaginated (match Phase 1) EXCEPT the new student-facing list endpoints which MUST be paginated (spec'd per endpoint); error `{message}`; write `message` strings for end users, not developers.
5. **Read the Next.js 16 docs first.** `CDC/frontend/AGENTS.md` and `CLAUDE.md` order you to read `node_modules/next/dist/docs/` before writing frontend code, because Next 16 has breaking changes vs your training data. Obey this in your first frontend session. Middleware lives in `proxy.ts`, not `middleware.ts`.
6. **Admin is god.** Every capability in this system is ultimately controlled by admins. Every admin write action MUST create an `audit_logs` row (M0 builds this) recording which admin did what — there are multiple admins and the owner needs to see who changed what.
7. **Work only inside `CDC/`** (`CDC/backend`, `CDC/frontend`). Ignore the stale duplicated docs at the repo root, `conclave/`, and `CDC/ml/` — do not touch them.
8. Run commands from the right folders: backend commands from `CDC/backend`, frontend from `CDC/frontend`. The repo-root `package.json` scripts are broken; the working monorepo scripts are in `CDC/package.json`.

## A2. The memory system — how you remember context across sessions

Claude Code sessions end and restart. You MUST maintain these files in the repo root of `CDC/` so any future session can resume with zero context loss:

**1. `CDC/PHASE2_PROGRESS.md`** — create it in M0 from the template below. Rules:
- **At the START of every session:** read `PHASE2_IMPLEMENTATION_SPEC.md` (this file) Part A + the milestone you're on, `PHASE2_PROGRESS.md`, and `PROJECT_CONTEXT.md` §11 (conventions). Then continue from the `NEXT ACTION` line.
- **After completing each numbered task:** flip its checkbox to `[x]` and add one line to the `## CHANGELOG` (date · task id · files touched).
- **Before ENDING any session (or when stopping for any reason):** update the `## CURRENT STATE` block: what milestone/task you are in, exactly what you finished, exactly what is half-done (file + what remains in it), and a precise `NEXT ACTION:` line a fresh session can execute immediately. Also record any un-run commands (pending migrations, `npm install` needed). **This is mandatory — the user will read this file to know where you stopped.**

Template:
```markdown
# PHASE 2 PROGRESS
Spec: PHASE2_IMPLEMENTATION_SPEC.md (v1.0). Stack: Laravel12+MySQL / Next16+JS(.jsx new files only) / MUI.

## CURRENT STATE
- Working on: M<0-10> task <n.n>
- Last completed: <task id + one line>
- Half-done: <file paths + what remains, or "nothing">
- Pending commands: <e.g. "php artisan migrate not yet run for 2026_xx_xx_create_offers_table">
- NEXT ACTION: <one imperative sentence>

## BLOCKED / QUESTIONS FOR OWNER
- (none)

## MILESTONE CHECKLIST
- [ ] M0 Foundations  … (copy every task id from the spec as you start each milestone)
...

## CHANGELOG
- 2026-09-27 · M0.1 · created PHASE2_PROGRESS.md
```

**2. `CDC/PHASE2_DECISIONS.md`** — append-only log. Whenever the spec forces a micro-decision it doesn't literally spell out (e.g. an exact column length, an exact MUI component choice), record it here in one line so it stays consistent later. Never contradict an earlier entry.

**3. Update `CDC/frontend/CLAUDE.md` and `CDC/backend/` (create `CDC/backend/CLAUDE.md`)** in M0, appending: "Phase 2 in progress. Before any work read /PHASE2_IMPLEMENTATION_SPEC.md Part A, /PHASE2_PROGRESS.md. New frontend code is JavaScript (.jsx). DB is MySQL only. Admin actions must be audit-logged."

## A3. Definition of done (applies to every milestone)

A milestone is done only when: all its tasks are checked; `php artisan migrate:fresh --seed` succeeds on MySQL; `php artisan test` passes; `npm run lint` and `npm run build` pass in `CDC/frontend`; the milestone's **Acceptance checks** all pass when you exercise them (use `php artisan tinker` / curl / the browser); Phase 1 smoke still works (company can log in, open a JNF, admin can open the review queue); and `PHASE2_PROGRESS.md` is updated. Fix the two known Phase 1 strict-mode TS errors ONLY if `npm run build` actually fails on them (they are in `app/company/jnf/[id]/page.tsx`, `app/company/inf/[id]/page.tsx`, `app/admin/jnfs/[id]/page.tsx` — `companyProfile?.about` and `graduating_batch` reads; fix by adding the missing optional keys to the local types, nothing else).

---

# PART B — THE PRODUCT: LOCKED DECISIONS

These came from the owner's answered questionnaire. They are final. Cited as (Q n.n) throughout.

## B1. Core concepts

- **Placement Cycle** (Q0.3): everything hangs off cycles, like Superset. A cycle = name, type (`fulltime` | `internship`), date range, status (`open`/`closed`), allowed programmes+batches. Admin creates cycles; admin enrols students into cycles (bulk); accepted JNFs/INFs get floated INTO a cycle.
- **Job Posting (a "float")** (Q3.1): an accepted JNF or INF becomes student-visible only when an admin explicitly floats it into a cycle, setting the **application deadline** at float time. Floating fires eligible-student emails (Q6.3 instant).
- **Student**: created by admin only (single form + bulk Excel/CSV, Q1.1). Username for login = **roll number** (Q1.2). Invitation = random password + password-reset link email, the Phase 1 admin-invite pattern (Q1.3). Academic fields (CGPA, backlogs, branch, etc.) are admin/system-controlled; students can never edit them (Q1.4/1.5). CGPA/backlogs will later auto-sync from an institute DB — build the update path as a distinct service so the sync can plug in later (Q1.6); until then admin bulk re-upload updates them.
- **Blocking** (Q5.2/5.3): per-cycle, driven by the offer matrix in B4. Admin can always override/unblock (admin is god).
- **Audit** (owner's closing note): every admin mutation writes `audit_logs` (who, what, before→after).

## B2. Eligibility rule (server-side, single implementation)

A student S is eligible for posting P (whose form data F = the JNF/INF `form_data`) iff ALL of:
1. S is enrolled and `active` in P's placement cycle.
2. S's user account is active (not suspended, Q1.8).
3. S has NO active `placement_block` in that cycle **that blocks this posting type** (see B4 — some blocks only block internships).
4. S is not on the debarment list (a block with reason `debarred`) (Q3.3).
5. F's eligibility matrix contains S's `programme` AND S's `branch` with `selected = true`.
6. S's `current_cgpa` ≥ that branch row's `cgpa` (parse as float; the stored value is a string like "7.0").
7. Backlogs (Q3.4): if the branch row has numeric `maxOngoingBacklogs` / `maxTotalBacklogs` (added in M4): S's `ongoing_backlogs` ≤ maxOngoing AND `total_backlogs` ≤ maxTotal (blank/null = unlimited). LEGACY forms have only boolean `backlogsAllowed`: `true` → unlimited; `false` → require S ongoing = 0 AND total = 0.
8. Gender: F.`genderFilter` is `all` or equals S's gender.
9. Batch: S's `graduating_batch` equals F.`graduatingBatch` (string-compare the year).
10. 10th/12th (Q3.3): if F has `minTenthPercent` / `minTwelfthPercent` (new optional fields, M4): S's values must be ≥ them (null = no bar).

**Display rule (Q3.2):** students see ALL postings floated in cycles they're enrolled in. Ineligible students see the posting with an explicit reason list ("CGPA below cutoff (6.8 < 7.0)", "Blocked: accepted a Full-Time offer", "Your branch is not eligible") and a disabled Apply. The apply endpoint re-runs the full check server-side and 422s with the same reasons — client state is never trusted.
**Never show applicant counts to students (Q3.8).**

Implement ONCE as `app/Services/EligibilityService.php`:
```php
final class EligibilityService {
    /** @return array{eligible: bool, reasons: string[]} — reasons are student-facing sentences */
    public function check(StudentProfile $student, JobPosting $posting): array;
    /** Returns eligible StudentProfile query builder for a posting — used for float emails + exports */
    public function eligibleStudentsQuery(JobPosting $posting): Builder;
}
```
Every caller (student list badge, apply endpoint, float-time email audience, admin "eligible list" view, exports) MUST go through this service. No second implementation anywhere.

## B3. Application & pipeline state machines

**Application** (per student per posting): `applied` → (`withdrawn` ↔ re-apply allowed while deadline open, Q3.6) → frozen at deadline. Student picks one of their up-to-8 resumes at apply time and answers the posting's questions (Q3.5b). Student may change the attached resume or edit answers until the deadline. If the chosen resume is not `approved`, the application gets `used_unverified_resume = true`, the student sees a persistent banner "Get your resume verified ASAP", and the flag is visible to admin everywhere in that drive; the flag clears automatically if that resume later becomes approved (Q1.7, Q2.3).

**Rounds** (Q4.1): floating copies the form's enabled `selectionRounds` into `posting_rounds` rows (name, type, order). Admin can add/remove/reorder rounds on a posting at any time afterwards.

**Per-round result** per application (your req 20): `attendance` (`yes`/`no`/null, admin-marked only, Q4.6) and `result` (`pending`/`selected`/`rejected`/`waitlisted` + `waitlist_rank`). Nothing is student-visible until an admin **publishes** that round (Q4.3). On publish: selected+waitlisted students get result email+notification; rejected applicants of that round get a regret email (Q4.3). Students see their own full trail (Q4.7): Eligible → Applied → per round: Appeared? → Selected/Not/Waitlist → Final.

**Shortlist entry** (Q4.2): two paths, both land as drafts that ONLY admin can publish:
- Company (for their own posting) selects/uploads a proposed shortlist per round → `shortlist_proposals` row, status `pending` → admin reviews → approve (applies results as unpublished) or reject with remark. Companies can NEVER publish to students.
- Admin directly: paste roll numbers or upload Excel/CSV per round → validated against that posting's applicants (report unknown/non-applicant roll numbers) → sets unpublished results → admin publishes.

**Waitlist** (Q4.4): company and admin can add/remove/reorder waitlisted candidates (any order, not forced by rank). After a round completes, the system AUTO-SUGGESTS promotions from the waitlist in rank order (creates a draft promotion set); admin reviews and publishes. Promotion to final-selected triggers the same offer/blocking flow as a normal selection.

**Addendum** (Q4.5): adding candidates after a round's results are published goes through a distinct "Addendum" action (company proposes or admin creates). Flagged `is_addendum = true`, logged in the posting's history, added students notified on publish. Re-adding a student who was already rejected in that round: **admin only**, via a separate confirm dialog ("Re-add previously rejected candidate — the company will be notified"), which sends the company a notification email automatically.

**Placed-elsewhere protocol** (Q3.7): when a student receives a blocking offer (B4), all their OTHER live applications get `placed_elsewhere_flag = true` (visible to admin in every pipeline view). Admin can then, per application, click "Remove from process": sets result `rejected` with remark "Selected elsewhere via CDC", notifies that company by email (default ON, admin can untick), and the email invites the company to request replacement candidates (which arrives as an addendum/waitlist proposal).

## B4. Offer types & blocking matrix (Q5.2 — FINAL, hard-code as defaults; admin can override at announcement and can unblock anyone anytime)

| `offer_type` | Meaning | Default block created (scope = that placement cycle only, Q5.3) |
|---|---|---|
| `intern` | Internship selection | Blocks further **internship** postings in this cycle. Does NOT block full-time postings. |
| `intern_ppo` | PPO **accepted** after internship | Blocks **everything** in this cycle. |
| `ppo_offered` | PPO offered but NOT accepted | **No block.** |
| `fulltime` | Full-time selection | Blocks **everything** in this cycle. |
| `intern_fulltime` | Intern + Full-time combined offer (new type — owner insisted it exists) | Blocks **everything** in this cycle. |
| `intern_performance_ppo` | Intern + performance-based PPO (final-years inside an FT cycle) | Blocks further **internship** postings in this cycle; still eligible for ALL full-time postings. |

No "dream offer" second-offer rules (Q5.2). No student decline flow — students cannot decline an offer in the portal (Q5.2); only admin can change outcomes. `debarred` is an admin-set block reason available independently of offers (Q3.3).

**Result announcement flow (Q5.1):** admin opens the posting's final round → marks final selected/waitlist → for each selected student picks `offer_type` (defaults sensibly from posting type: JNF→`fulltime`, INF→`intern`), CTC/stipend prefilled from the form's programme-wise salary/stipend for that student's programme with manual override (Q5.4), and sees the auto-suggested block (from the matrix) with an override toggle → clicks **Publish results** → offers + blocks + placed-elsewhere flags created, emails+notifications sent, dashboards update.

## B5. Resumes (Q2.x)

Up to **8** per student, **PDF only, max 2 MB**, student-given label per slot. States: `pending` → `approved` | `rejected` (admin remark required on reject); re-upload into a slot resets it to `pending` and clears the old file. A resume attached to any application whose posting deadline has not passed OR whose pipeline is still running is **locked** — cannot be replaced or deleted (Q2.5). Admin verifies per-resume in a queue page modelled on the JNF review queue (Q2.6), with inline PDF preview (reuse `pdfviewer.tsx` via the existing proxy route once it's secured in M0). Students CAN apply with a pending/rejected resume but the application is flagged (B3).

Storage: local disk (Q0.6), **private** — `storage/app/private/resumes/{roll_no}/{slot}_{uuid}.pdf`. NEVER the public disk (Phase 1 puts logos on public; resumes are sensitive — private disk + streamed download only). Access: (a) authenticated owner-student; (b) any admin; (c) company only via **signed URLs** (Q8.1): `URL::temporarySignedRoute('resumes.signed', now()->addDays(30), [...])` streaming the PDF without login. Excel exports embed these signed links.

## B6. Emails & notifications (Q6.x) — institute email only, instant, no deadline reminders

Every row = email (Blade template in `resources/views/emails/`, Mailable in `app/Mail/`, matching Phase 1's inline-styled maroon-header pattern) + in-app `PortalNotification`. **All student-facing bulk sends go through the new `MailDispatchService`** which reads the admin-controlled `mail_mode` setting (Q0.5): `queued` (default; `Mail::to(...)->queue(...)` on the database queue) or `sync`. Admin switches this on the new Settings page — NOT via `.env`. ~3000 students max per cycle: chunk recipient loops by 100 inside a queued Job.

| # | Trigger | Recipients |
|---|---|---|
| E1 | Student account created | that student (roll no + set-password link) |
| E2 | Job floated | eligible students only (via `EligibilityService::eligibleStudentsQuery`) |
| E3 | Application submitted | that student (confirmation, posting + resume name) |
| E4 | Round result published | selected/waitlisted: result mail; rejected in that round: regret mail |
| E5 | Final result published | selected: offer mail (type, company); others in final round: regret |
| E6 | Event announced | event's audience |
| E7 | Resume approved / rejected | that student (remark included on reject) |
| E8 | Profile changed by admin / branch change decided | that student |
| E9 | Removed-from-process (placed elsewhere) | the affected company (with replacement invitation) |
| E10 | Company shortlist proposal submitted / decided | admins on submit; company on decide |

## B7. Everything else, locked

- **Application questions** (Q3.5b): at float time (editable until deadline) admin builds per-posting questions: types `text`, `mcq_single`, `mcq_multi`; each required/optional; options array for MCQ. Students answer at apply; answers shown to admin & included in exports.
- **Branch change requests** (Q1.6): student submits (requested branch + reason) → admin approves (profile branch updated, audit-logged, E8) or rejects with remark.
- **Suspension** (Q1.8): admin toggles `users.is_active`; inactive users get 403 `{"message":"Account suspended. Contact CDC."}` on login and on every authenticated request (middleware).
- **Exports** (Q8): Admin: any posting → Excel (`phpoffice/phpspreadsheet`) of applicants: all profile columns + answers + per-round statuses + flags + **signed resume hyperlink** column; also full-cycle student export. Company: same export for THEIR postings, any time (Q8.2), but columns limited to: roll no, name, programme, branch, batch, CGPA, backlogs, 10th/12th, answers, round statuses, resume link (Q10.2) — contact details (phone/personal email) included ONLY if admin flipped that posting's `share_contact_details` toggle. Keep Phase 1's one-row CSV untouched (Q8.3).
- **Company portal additions** (Q10.1): for their accepted+floated forms only: applicant list & counts, export button, propose shortlist/waitlist per round, propose addendum, see published (not draft) round outcomes, see their events. Companies never see unpublished results or other companies' data.
- **Events** (Q7): fields — title (required), type (`ppt`/`workshop`/`webinar`/`other`), optional company link, starts_at (required), optional venue/meeting link, optional rich-text description, audience (`all` | programme+branch list | applicants of a posting). Announce-only, no RSVP. Publishing sends E6.
- **Dashboards** (Q9): Admin — per-cycle stats + all-cycle overview: totals by programme/branch/batch, placed/unplaced + % (denominator = ALL enrolled students, Q9.2), companies completed vs ongoing, offers by type, highest/average/median/lowest CTC, stipend stats, branch-wise placement table, applications-over-time chart, gender split. Use `@mui/x-charts` (install it; MUI-native). You have free rein on layout — make it genuinely useful. Student — profile/resume status card, applications with live round trail, **eligible-jobs-you-haven't-applied nudge**, upcoming deadlines & events. **Calendar** (Q9.3): a month calendar page for BOTH admin and student showing deadlines + events + scheduled round dates (student sees only their own relevant items).
- **Student UI** (Q11): Superset-style **left sidebar** shell (this is a deliberate departure from Phase 1's top AppBar — build a new `studentshell.jsx` with a permanent Drawer on desktop, temporary on mobile), maroon theme, polished and fully responsive (students are on phones). Pages: Dashboard · Job Profiles · My Applications · My Resumes · Events · Calendar · Notifications · Profile. Job detail page reuses `JnfPreview`/`InfPreview` from `components/forms/shared/formpreview.tsx` in `readOnly` mode (import TSX from JSX works fine) with company contact blocks stripped.
- **Seeder** (Q12.3): realistic demo data — see M10.
- **Out of scope — do NOT build:** job alerts/preferences (Q3.9), credit-score system (Q5.5), deadline reminder mails (Q6.1), season report PDF (Q9.4), per-cycle withdraw-prohibit toggle (Q3.6), S3, SSO, student self-signup, RSVP.

---

# PART C — DATABASE SCHEMA (MySQL). One migration file per table, dated sequentially.

General: every FK `constrained()` with explicit `cascadeOnDelete`/`nullOnDelete` as noted; every enum listed exactly; `timestamps()` everywhere; index anything used in WHERE below.

**C1 `ALTER users`** — migration `add_student_role_and_is_active_to_users`:
`DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','company','student') NOT NULL DEFAULT 'company'")` (MySQL-only is fine now; inside `if (DB::getDriverName() === 'mysql')`, with an `else` branch no-op so SQLite tests still run — SQLite has no real enums so nothing is needed there). Add `is_active` boolean default true.

**C2 `placement_cycles`**: `name` string; `type` enum(`fulltime`,`internship`); `starts_on` date; `ends_on` date; `status` enum(`open`,`closed`) default `open`, indexed; `allowed_programmes` json (array of `{programme, batches:[years]}`); `description` text null; `created_by` FK users nullOnDelete.

**C3 `student_profiles`** (REPLACE the empty untracked stub migration `2026_09_21_162407_create_student_profiles_table.php` — fresh DB is approved (Q0.1a), so edit that file in place): `user_id` FK users cascadeOnDelete unique; `roll_no` string(30) unique; `full_name`; `institute_email` string unique; `personal_email` null; `phone` string(20) null; `programme` string; `branch` string; `graduating_batch` unsignedSmallInteger; `current_cgpa` decimal(4,2) null; `ongoing_backlogs` unsignedSmallInteger default 0; `total_backlogs` unsignedSmallInteger default 0; `gender` enum(`male`,`female`,`other`); `date_of_birth` date null; `tenth_percent` decimal(5,2) null; `twelfth_percent` decimal(5,2) null; `category` string(30) null; `pwd` boolean default false; `home_state` string null; `photo_path` string null; `linkedin_url`,`github_url` string null. Index (`programme`,`branch`,`graduating_batch`). Model `StudentProfile` (fill the empty stub class): fillable all above, casts, `belongsTo User`, `hasMany Resume, Application, Offer, PlacementBlock, CycleEnrollment, BranchChangeRequest`.

**C4 `cycle_enrollments`**: `placement_cycle_id` FK cascade; `student_profile_id` FK cascade; `status` enum(`active`,`suspended`) default `active`; unique(`placement_cycle_id`,`student_profile_id`); `enrolled_by` FK users nullOnDelete.

**C5 `resumes`**: `student_profile_id` FK cascade; `slot` unsignedTinyInteger (1–8); `label` string(60); `file_path` string; `file_size` unsignedInteger; `status` enum(`pending`,`approved`,`rejected`) default `pending`, indexed; `admin_remark` text null; `reviewed_by` FK users nullOnDelete; `reviewed_at` timestamp null; unique(`student_profile_id`,`slot`).

**C6 `job_postings`**: `postable_type` string + `postable_id` (morphs → Jnf/Inf); `placement_cycle_id` FK cascade; `application_deadline` **datetime** (not date — deadlines have times); `status` enum(`open`,`in_process`,`completed`,`cancelled`) default `open`, indexed; `share_contact_details` boolean default false; `floated_by` FK users nullOnDelete; `floated_at` timestamp; `eligibility_snapshot` json null (copy of form_data eligibility keys at float time — freeze the rules even if admin later edits the form). Unique(`postable_type`,`postable_id`) — one float per form.

**C7 `posting_questions`**: `job_posting_id` FK cascade; `question` text; `qtype` enum(`text`,`mcq_single`,`mcq_multi`); `options` json null; `required` boolean default false; `sort_order` unsignedSmallInteger.

**C8 `posting_rounds`**: `job_posting_id` FK cascade; `name` string; `round_type` string (reuse the Phase 1 round-type slugs: `ppt`,`resume`,`written_test`,`aptitude_test`,`technical_test`,`group_discussion`,`hr_interview`,`technical_interview`,`psychometric`,`medical`,`other`); `sort_order` unsignedSmallInteger; `scheduled_at` datetime null; `status` enum(`pending`,`ongoing`,`completed`) default `pending`; `is_final` boolean default false (exactly one final round per posting — enforce in controller).

**C9 `applications`**: `job_posting_id` FK cascade; `student_profile_id` FK cascade; `resume_id` FK resumes restrictOnDelete; `status` enum(`applied`,`withdrawn`) default `applied`, indexed; `used_unverified_resume` boolean default false; `placed_elsewhere_flag` boolean default false; `answers` json null (`[{question_id, answer}]`); `applied_at` timestamp; `withdrawn_at` timestamp null; unique(`job_posting_id`,`student_profile_id`).

**C10 `application_round_results`**: `application_id` FK cascade; `posting_round_id` FK cascade; `attendance` enum(`yes`,`no`) null; `result` enum(`pending`,`selected`,`rejected`,`waitlisted`) default `pending`; `waitlist_rank` unsignedSmallInteger null; `is_addendum` boolean default false; `published_at` timestamp null (null = draft, invisible to students/company); `decided_by` FK users nullOnDelete; `remark` string null; unique(`application_id`,`posting_round_id`).

**C11 `shortlist_proposals`**: `job_posting_id` FK cascade; `posting_round_id` FK cascade; `proposed_by` FK users cascade (company user or admin); `kind` enum(`shortlist`,`waitlist`,`addendum`,`replacement_request`); `payload` json (`[{roll_no, waitlist_rank?}]`); `status` enum(`pending`,`approved`,`rejected`) default `pending`; `admin_remark` text null; `decided_by` FK users nullOnDelete; `decided_at` timestamp null.

**C12 `offers`**: `application_id` FK cascade unique; `student_profile_id` FK cascade; `company_id` FK cascade; `job_posting_id` FK cascade; `placement_cycle_id` FK cascade; `offer_type` enum(`intern`,`intern_ppo`,`ppo_offered`,`fulltime`,`intern_fulltime`,`intern_performance_ppo`); `ctc_annual` unsignedBigInteger null; `stipend_monthly` unsignedInteger null; `currency` string(8) default `INR`; `announced_by` FK users nullOnDelete; `announced_at` timestamp.

**C13 `placement_blocks`**: `student_profile_id` FK cascade; `placement_cycle_id` FK cascade; `scope` enum(`all`,`internships_only`); `reason` enum(`offer`,`debarred`,`manual`); `offer_id` FK offers nullOnDelete null; `remark` string null; `active` boolean default true, indexed; `blocked_by` FK users nullOnDelete; `unblocked_by` FK users nullOnDelete null; `unblocked_at` timestamp null.

**C14 `events`**: `title`; `event_type` enum(`ppt`,`workshop`,`webinar`,`other`) default `other`; `company_id` FK nullOnDelete null; `starts_at` datetime; `venue` string null; `meeting_link` string null; `description` longText null; `audience_type` enum(`all`,`branches`,`posting_applicants`) default `all`; `audience_filter` json null (branches list or posting id); `published_at` timestamp null; `created_by` FK users nullOnDelete.

**C15 `branch_change_requests`**: `student_profile_id` FK cascade; `current_branch`; `requested_branch`; `requested_programme` string null; `reason` text; `status` enum(`pending`,`approved`,`rejected`) default `pending`; `admin_remark` text null; `decided_by` FK users nullOnDelete; `decided_at` timestamp null.

**C16 `portal_settings`**: `key` string unique; `value` json. Seed: `{mail_mode: "queued"}`. Service `app/Services/SettingsService.php`: `get(string $key, $default)`, `set(string $key, $value, User $admin)` (audit-logged), cached via `Cache::remember` 60s.

**C17 `audit_logs`**: `user_id` FK nullOnDelete (the admin); `action` string (dot-slug e.g. `student.create`, `posting.float`, `round.publish`, `block.remove`, `setting.update`); `subject_type` string null; `subject_id` unsignedBigInteger null; `before` json null; `after` json null; `ip` string(45) null; index(`user_id`), index(`subject_type`,`subject_id`), index `created_at`. Service `app/Services/AuditService.php`: `log(Request $r, string $action, ?Model $subject, ?array $before, ?array $after): void`. **Call it from every admin mutation you write.** Admin UI to browse it in M10.

**C18 `config/programmes.php`** (not a table): mirror the canonical 8-programme/59-branch catalogue EXACTLY from `CDC/frontend/components/forms/shared/eligibilitygrid.tsx:73-174` (read that file and transcribe — do not type from memory). Helper `App\Support\ProgrammeCatalogue::all()` merges it with active custom `programme_branches` rows — used to validate student programme/branch on import and in `EligibilityService`.

---

# PART D — MILESTONES. Execute strictly in order. Never start Mn+1 with Mn unfinished.

## M0 — Foundations, MySQL, security, memory files

- **M0.1** Create `CDC/PHASE2_PROGRESS.md` (template A2) and `CDC/PHASE2_DECISIONS.md`; append the Phase 2 notice to both CLAUDE.md files (A2.3).
- **M0.2** MySQL: set `.env` `DB_CONNECTION=mysql`, `DB_DATABASE=iitism_placement` (host/user/pass from existing env or ask owner via BLOCKED section if absent); create the database; `php artisan migrate --seed`; run `php artisan storage:link`. Update `.env.example` accordingly (+ add the missing `COMPANY_RECRUITER_VERIFY_TTL_MINUTES`, remove the stray `a` on line 15).
- **M0.3** Security fixes (Q0.4), each its own commit:
  a. Delete route `POST /auth/admin/register` from `routes/api.php` and the `registerAdmin` method (it is dead + public admin minting).
  b. In `StoreJnfRequest`/`StoreInfRequest`: change `status` rule to `['nullable', Rule::in(['draft','submitted'])]` and REMOVE `admin_remarks` from company-writable rules; in `CompanyJnfController@store/update` (and Inf twin) strip `admin_remarks` from `$validated` before fill. Companies may only ever set draft/submitted.
  c. `config/sanctum.php`: `'expiration' => 60 * 24 * 7` (7 days). Add a `signOut` pre-call to `POST /auth/logout` in the three shells' logout handlers (adminshell.tsx, companyshell.tsx, and later studentshell.jsx) so tokens are revoked — a small `fetch` with the bearer before `signOut()`.
  d. `app/api/proxy-pdf/route.ts`: require a signed-in session (`await auth()`, else 401) AND allow only URLs whose origin matches `NEXT_PUBLIC_API_URL`'s origin or the app's own origin. Reject everything else with 400.
  e. `DELETE /company/jnfs/{jnf}` + Inf twin: 422 unless status is `draft` **or** (`submitted`/`under_review`/`accepted`/`rejected` AND no `job_postings` row exists for it — floated forms are never deletable).
- **M0.4** Frontend base: `tsconfig.json` add `"allowJs": true`. `composer require phpoffice/phpspreadsheet`; frontend `npm i @mui/x-charts`.
- **M0.5** Migrations C1, C16, C17 + `SettingsService`, `AuditService` + `config/programmes.php` + `ProgrammeCatalogue` (C18). Alias middleware in `bootstrap/app.php`: `'active' => \App\Http\Middleware\EnsureUserIsActive::class` (403 "Account suspended. Contact CDC." when `!$user->is_active`); append `active` to every authenticated route group when you touch them.
- **M0.6** Auth plumbing for the third role, minimal TS edits:
  - `types/next-auth.d.ts`: role union → `"admin" | "company" | "student"` (3 places) + optional `rollNo`.
  - `auth.ts`: `loginType` may be `student`; on mismatch throw `StudentOnlyError` code `student_only` (mirror the existing two); pass `roll_no` through to the backend when present; `token.role` cast widened.
  - `proxy.ts`: add `isStudentRoute = pathname.startsWith("/student")`, role gate, post-login destination `role === "student" → /student`, add `"/student/:path*"` to `matcher`.
  - Backend `AuthController@login`: accept EITHER `email` OR `roll_no` (validate `required_without` each other). If `roll_no`: `$user = StudentProfile::where('roll_no', strtoupper(trim($roll)))->first()?->user`. Same 422 "Invalid credentials." on any failure (no enumeration). Also `forgotPassword`: accept `roll_no` alternative, resolve to institute email, keep the identical anti-enumeration response either way.
  - `app/auth/login/[type]/page.tsx`: currently any type ≠ admin renders recruiter. Change the minimal amount: `const variant = type === "admin" ? "admin" : type === "student" ? "student" : "recruiter"`; student variant = roll-number field (label "Roll Number") instead of email + student feature list copy. (This file is TSX — it is one of the explicitly permitted edits; keep edits surgical.)
- **Acceptance:** migrate:fresh --seed on MySQL passes; `php artisan test` green; admin/company login unchanged; `POST /api/auth/admin/register` now 404s; a company POST with `status: accepted` is rejected; proxy-pdf without session → 401.

## M1 — Placement cycles

- **M1.1** Migration C2 + model + `AdminPlacementCycleController`: `index` (with counts: enrolled students, postings, offers), `store`, `update`, `show`, `close` (status→closed). Validate `allowed_programmes` entries against `ProgrammeCatalogue`. Audit-log all writes. Routes under `role:admin`.
- **M1.2** Migration C4 + enrollment endpoints: `POST /admin/placement-cycles/{cycle}/enroll` accepting `{roll_nos: []}` OR an uploaded Excel/CSV of roll numbers → per-row report `{enrolled: n, errors: [{row, roll_no, reason}]}`; `DELETE .../enroll/{studentProfile}`; list enrolled with search+pagination.
- **M1.3** Admin UI `app/admin/placement-cycles/page.jsx` (card list like Superset's Placements screen: name, type chip, date range, status chip, counts, "Add placement cycle" dialog) and `app/admin/placement-cycles/[id]/page.jsx` (overview + enrolled-students tab with bulk-enroll upload + postings tab placeholder). Add "Placement Cycles" to `adminshell.tsx` nav (this TSX nav-array edit is permitted).
- **Acceptance:** create cycle, enrol via pasted roll list (students exist after M2 — for now test with the error report path), everything audit-logged.

## M2 — Students module

- **M2.1** Migration C3 + `StudentProfile` model (fill the stub) + relations on `User`.
- **M2.2** `AdminStudentController`:
  - `store` (single create): validates all C3 fields; programme+branch must exist in `ProgrammeCatalogue`; creates `User` (`role student`, `email = institute_email`, `password = Str::random(32)`, `is_active = true`) + profile in one `DB::transaction`; sends invitation E1 = `StudentInvitationMail` containing roll no + a password-reset link (generate via `Password::createToken` exactly like `AdminManagementController@store` does — read that method first and copy its pattern) → response 201 `{message, student}`.
  - `bulkImport`: `POST /admin/students/import` multipart Excel/CSV. Parse with PhpSpreadsheet. **Two-phase**: `?dry_run=1` returns `{valid_rows: n, errors: [{row, roll_no, field, reason}]}` without writing; real run creates all valid rows (transaction per row so one bad row doesn't kill the batch), queues invitation mails via `MailDispatchService`, returns the same report + created count. Column order: `roll_no, full_name, institute_email, programme, branch, graduating_batch, gender, current_cgpa, ongoing_backlogs, total_backlogs, tenth_percent, twelfth_percent, date_of_birth, personal_email, phone, category, pwd, home_state`. Also `GET /admin/students/import/template` → downloadable header-only xlsx.
  - `index` (search by roll/name/branch/programme/batch, **paginated** `?page` 50/page — 3000 students, do not return all), `show` (profile + enrollments + applications + offers + blocks history — the "previous cycles data" the owner wants, Q1.4), `update` (admin edits any field; audit before/after; E8), `academicBulkUpdate` (`POST /admin/students/academics/import`: Excel of `roll_no, current_cgpa, ongoing_backlogs, total_backlogs` — the future auto-sync entry point (Q1.6); implement as its own `StudentAcademicSyncService::apply(array $rows, User $admin)` so the institute-DB sync can call the same service later), `suspend`/`reactivate` (toggle `users.is_active`, audit).
- **M2.3** Student self endpoints: `GET /student/profile` (own), `PATCH /student/profile` — fillable ONLY `personal_email, phone, home_state, linkedin_url, github_url`; `POST /student/profile/photo` (jpg/png ≤1 MB, private disk, streamed via own endpoint). 403 attempts to write anything else are simply ignored by `$request->only([...])`.
- **M2.4** Branch change (C15): student `POST /student/branch-change` (one pending at a time → 409); `GET` own list; admin queue `GET /admin/branch-changes?status=pending`, `PATCH .../{id}` approve (updates profile inside transaction, audit, E8) / reject (remark required).
- **M2.5** Frontend admin: `app/admin/students/page.jsx` (paginated table, search, filters, Add Student dialog, Import dialog with dry-run preview table of errors, template download, suspend toggle per row), `app/admin/students/[id]/page.jsx` (full profile + tabs: Overview / Cycles / Applications / Offers & Blocks / Audit trail). `app/admin/branch-changes/page.jsx` queue. Nav entries in adminshell.
- **M2.6** Frontend student: create `lib/studentapi.js` — copy `lib/companyapi.ts`'s body verbatim as JS (same `getSession()` bearer + error-collapsing). Create `components/student/studentshell.jsx`: Superset-style left sidebar (permanent Drawer ≥`md`, temporary below; maroon accents; nav = Dashboard, Job Profiles, My Applications, My Resumes, Events, Calendar, Notifications, Profile; notification unread badge polling like adminshell). `app/student/layout.jsx` wraps it. Pages this milestone: `app/student/profile/page.jsx` (read-only academic block clearly marked "Synced from institute records — contact CDC for corrections", editable personal block, photo upload, branch-change request dialog + status), `app/student/notifications/page.jsx` (clone company notifications page as JSX). Placeholder `app/student/page.jsx` dashboard shell.
- **Acceptance:** bulk import 10 rows with 2 bad ones → dry-run reports exactly the 2; real import creates 8 users; invitation mail logged in `email_logs`; student sets password via link, logs in at `/auth/login/student` with ROLL NUMBER, lands on `/student`; admin sees suspend working (suspended student's login → suspended message); every admin write visible in `audit_logs`.

## M3 — Resumes

- **M3.1** Migration C5 + `Resume` model. `FileUploadService`: add `uploadResume(UploadedFile $f, StudentProfile $s, int $slot): array` → private disk path per B5.
- **M3.2** Student endpoints: `GET /student/resumes`; `POST /student/resumes` `{slot 1-8, label, file}` (pdf, max:2048; replaces slot if exists AND not locked → old file deleted, status reset `pending`, audit no [student actions need no audit, only admin]); `DELETE /student/resumes/{resume}` (locked check); `GET /student/resumes/{resume}/file` (stream own). **Locked** = `applications()->where('status','applied')->whereHas('jobPosting', fn($q) => $q->whereIn('status', ['open','in_process']))->exists()` — implement as `Resume::isLocked(): bool` and reuse.
- **M3.3** Admin: `GET /admin/resumes?status=pending` queue (paginated, with student info), `GET /admin/resumes/{resume}/file` (stream), `PATCH /admin/resumes/{resume}` `{status: approved|rejected, admin_remark required_if rejected}` → audit + E7 + **clear `used_unverified_resume` on all that student's applications using this resume when approved**.
- **M3.4** Signed streaming for companies/exports: route `GET /resumes/signed/{resume}` name `resumes.signed`, middleware `signed` ONLY (no auth), streams the PDF `inline`. Helper `Resume::signedUrl(int $days = 30): string`.
- **M3.5** Frontend: `app/student/resumes/page.jsx` — 8 slot cards (label, status chip pending/approved/rejected + remark, locked padlock with tooltip, upload/replace/delete, view inline). Admin `app/admin/resumes/page.jsx` — verification queue styled like the JNF queue, side-by-side PDF preview (reuse `pdfviewer.tsx` through the secured proxy) + approve / reject-with-remark buttons. Nav entries.
- **Acceptance:** upload 2 MB+1 byte → 422; re-upload resets to pending; approve clears application flags; signed URL opens the PDF logged-out and expires (tamper the signature → 403).

## M4 — Phase 1 form change: numeric backlogs + 10th/12th cutoffs (Q3.4, Q3.3) — the ONLY approved Phase 1 functional change

- **M4.1** `components/forms/shared/eligibilitygrid.tsx` (TSX edit, approved): alongside the existing global + per-branch `backlogsAllowed` boolean, add optional numeric inputs `maxOngoingBacklogs`, `maxTotalBacklogs` (blank = unlimited, min 0) at global level with "Apply to All Selected", and per-branch overrides; extend `BranchEligibility` type with the two optional fields. When `backlogsAllowed` is toggled false, keep the boolean (legacy semantics preserved).
- **M4.2** Both wizards (`jnfformpro.tsx`, `infformpro.tsx` — same edit twice, they are twins): add to tab 2 two optional fields `minTenthPercent`, `minTwelfthPercent` (0–100, step 0.01) stored at top level of `form_data`. Show them in `formpreview.tsx`'s eligibility section and in both admin detail pages' eligibility display + `AdminFormReviewController`'s CSV columns and `detectChangedFields` labels.
- **M4.3** Backward compatibility is rule B2.7 — old forms without the numbers keep the boolean meaning. `EligibilityService` (built in M5) implements it; nothing migrates.
- **Acceptance:** old saved JNFs render unchanged; new JNF saves the four new keys in `form_data`; admin CSV shows them.

## M5 — Floating & student job board

- **M5.1** Migrations C6, C7 + models (`JobPosting` morphTo `postable`, `belongsTo PlacementCycle`, `hasMany PostingRound, PostingQuestion, Application`).
- **M5.2** `EligibilityService` per B2, reading from `eligibility_snapshot` (fallback to live `form_data` if snapshot null). Unit-test it: 12+ cases covering every reason (branch not listed, CGPA, each backlog mode, gender, batch, 10th/12th, block scopes `all` vs `internships_only` vs posting type, suspension, not enrolled).
- **M5.3** `AdminPostingController`:
  - `store` (`POST /admin/postings`): `{form_type: jnf|inf, form_id, placement_cycle_id, application_deadline (future datetime), share_contact_details?, questions?: []}` → 422 unless the form's status is `accepted` and not already floated; cycle must be `open`; posting type must match cycle type (JNF→fulltime cycle, INF→internship — EXCEPTION: JNFs may also float in a fulltime cycle as `intern_performance_ppo`-capable, that's just offer typing, no extra rule here); snapshots eligibility keys (`eligibility`, `globalCgpa`, `globalBacklogs`, `genderFilter`, `graduatingBatch`, `minTenthPercent`, `minTwelfthPercent`) into `eligibility_snapshot`; copies enabled `selectionRounds` → `posting_rounds` (last one `is_final` by default); creates questions; audit; dispatches E2 job (`SendPostingFloatedMails`) to `EligibilityService::eligibleStudentsQuery` chunked ×100 via `MailDispatchService`.
  - `index` (per cycle, with applicant counts), `show` (posting + rounds + questions + applicant stats + eligible-count), `update` (deadline extend, questions edit until deadline, `share_contact_details`), `close`/`cancel`, round management: `POST/PATCH/DELETE /admin/postings/{p}/rounds` (add/remove/reorder/schedule; refuse removing a round that has results).
  - Admin "Float to students" UI: button on the admin JNF/INF detail page when `accepted` (small TSX addition to the two detail pages: render a `<FloatDialog />` — build the dialog itself as `components/admin/floatdialog.jsx` in JSX: cycle select, deadline datetime, contact toggle, question builder rows [type/text/options/required], eligible-count preview via a `GET /admin/postings/preview-eligibility?form_type&form_id&cycle_id` endpoint) + `app/admin/postings/page.jsx` list per cycle.
- **M5.4** `MailDispatchService` (`app/Services/MailDispatchService.php`): `send(User|string $to, Mailable $m, string $subject, string $template)` → reads `SettingsService::get('mail_mode')`; `queued` → `Mail::to(...)->queue($m)` + `email_logs` row; `sync` → delegate to existing `PortalNotificationService::sendLoggedEmail` pattern. Admin Settings page `app/admin/settings/page.jsx` + `GET/PATCH /admin/settings` (mail_mode radio; extensible key/value UI). Document in progress file: production must run `php artisan queue:work`.
- **M5.5** Student board: `GET /student/postings` — postings in the student's active cycles, paginated, each item: title, company name+logo, type, deadline, `eligibility: {eligible, reasons}`, `application: {id, status, resume_id} | null`, compensation summary. Filters `?type=&eligibility=&applied=`. `GET /student/postings/{posting}` — full detail: the underlying form's data **minus** `companyProfile` contact fields — build `app/Http/Resources/`-free manually-shaped array (Phase 1 has no Resources; shape it by hand in the controller, explicitly whitelisting keys; NEVER `->load('company')` raw — Company model serialises recruiter contacts (see PROJECT_CONTEXT §13)), + rounds, + questions, + own application w/ answers, + own published round trail.
- **M5.6** Apply endpoints (`StudentApplicationController`): `POST /student/postings/{posting}/apply` `{resume_id, answers}` → transaction: re-check deadline future, `EligibilityService` (422 with reasons), resume belongs to student, required questions answered (validate MCQ answers ∈ options); set `used_unverified_resume = resume.status !== 'approved'`; E3. `PATCH /student/applications/{application}` (change resume/answers until deadline), `POST /student/applications/{application}/withdraw` (until deadline; sets withdrawn; re-apply = new `POST` flips it back to applied, keep the same row). `GET /student/applications` — list with per-round published trail.
- **M5.7** Frontend student pages: `app/student/postings/page.jsx` (card list: logo, title, chips for type/deadline countdown/eligible-or-reason, applied tick; filters; Superset-flavoured clean cards), `app/student/postings/[id]/page.jsx` (reuse `JnfPreview`/`InfPreview` readOnly for the body; sticky apply panel: resume select showing each resume's status ["Data Resume — ⚠ pending verification"], question form, apply/withdraw/change-resume; unverified-resume warning banner per B3; reasons panel when ineligible), `app/student/applications/page.jsx` (application cards with a `Stepper` of published round trail per Q4.7).
- **Acceptance:** float an accepted JNF → exactly the eligible students receive E2 (verify `email_logs` count vs `eligibleStudentsQuery` count); ineligible student's apply → 422 with correct reasons; withdraw + re-apply works; deadline passed → apply/edit/withdraw all 422; company detail page shows applicant count only after M6's company view (not to students ever).

## M6 — Pipeline: shortlists, rounds, waitlist, addendum

- **M6.1** Migrations C10, C11 + models.
- **M6.2** `AdminPipelineController` (per posting): `GET /admin/postings/{p}/pipeline` (rounds × applicants matrix data: every application with per-round attendance/result/rank/flags — this powers the req-20 grid); `POST /admin/postings/{p}/rounds/{r}/results` accepting `{entries: [{roll_no, result, waitlist_rank?}] }` OR uploaded Excel/roll-paste (validate: applicant of this posting, not withdrawn; report unknowns) → writes DRAFT results (`published_at` null); `POST .../rounds/{r}/attendance` bulk `{roll_nos_present: [], roll_nos_absent: []}` (Q4.6); `POST .../rounds/{r}/publish` → transaction: stamp `published_at`, round status `completed`, next round `ongoing`; E4 mails (selected/waitlist vs regret) via MailDispatchService; audit. `POST .../rounds/{r}/readd/{application}` — the special re-add-rejected protocol (Q4.5): admin-only, requires `{confirm: true, remark}`, sets result back to `selected` draft flagged `is_addendum`, mails the company (E9-style notice), audit.
- **M6.3** Waitlist (Q4.4): included in results entries as `waitlisted` + rank; `POST .../rounds/{r}/waitlist/reorder` `{ordered_application_ids: []}`; **auto-suggest promotions**: when a round with waitlisted candidates is published, compute vacancies still open (form's `vacancies` minus selected) and create DRAFT `selected` results for the top-ranked waitlisted in the NEXT round (or final offer set), flagged `is_addendum=false`, and surface them in the pipeline UI as "Suggested promotions — review & publish". Admin edits/publishes as usual.
- **M6.4** Company side (`CompanyPipelineController`, `role:company`, scoped to own postings): `GET /company/postings` (their floated forms + stats), `GET /company/postings/{p}/applicants` (only after they exist; columns per Q10.2 field policy + signed resume link; include `used_unverified_resume`? NO — that flag is admin-only), `POST /company/postings/{p}/rounds/{r}/proposals` `{kind: shortlist|waitlist|addendum, entries: [{roll_no, waitlist_rank?}]}` → C11 row + notify admins (E10); `GET .../proposals` own list w/ status. Companies see ONLY published round results.
- **M6.5** Admin proposals queue: `GET /admin/proposals?status=pending`, `PATCH /admin/proposals/{id}` approve (converts payload into draft results on that round; still requires the separate publish) / reject with remark; E10 back to company.
- **M6.6** Frontend: admin `app/admin/postings/[id]/page.jsx` — tabs: Overview · Applicants (table: roll, name, branch, CGPA, resume link, ⚠ unverified flag, 🚩 placed-elsewhere flag) · **Pipeline** (the req-20 grid: sticky first column = student, one column per round showing attendance + result chips, bulk actions: upload shortlist, mark attendance, publish round — with draft/published visual distinction) · Waitlist (drag-reorder using `@hello-pangea/dnd`, already installed) · Proposals · Results. Company `app/company/postings/page.jsx` + `[id]/page.jsx` (applicants after deadline info per Q10.1 answer "at their side for accepted", export button, propose-shortlist dialog with roll picker from applicant list). Student side already renders published trail (M5.7).
- **Acceptance:** company proposes shortlist → admin sees pending proposal → approve → results are DRAFT (student sees nothing) → publish → selected get mail, others get regret mail, student trail updates; waitlist reorder persists; re-add-rejected requires the confirm protocol and mails the company; unknown roll in upload is reported not silently dropped.

## M7 — Results, offers, blocking

- **M7.1** Migrations C12, C13 + models.
- **M7.2** `AdminResultController`: `GET /admin/postings/{p}/results/prepare` — final-round selected (published or draft) with per-student: suggested `offer_type` (JNF→`fulltime`, INF→`intern`), prefilled `ctc_annual`/`stipend_monthly` from the form's `programmeSalaries`/`programmeStipends` matched to the student's programme via the same `getDisplayName()` normalisation the frontend uses (port that tiny function to PHP in `ProgrammeCatalogue`), suggested block per B4 matrix; `POST /admin/postings/{p}/results/publish` `{selections: [{application_id, offer_type, ctc_annual?, stipend_monthly?, block: bool, block_scope}]}` → transaction per student: create `offers`, create `placement_blocks` when block=true (scope from matrix/override), stamp final round published, set `placed_elsewhere_flag` on the student's other live applications in the cycle, posting status `completed`, E5 mails, audit everything. Implement matrix as `app/Services/BlockingPolicy.php`: `suggest(string $offerType): ?array{scope} ` exactly per B4 — with the matrix table pasted as a docblock.
- **M7.3** Block management: `GET /admin/blocks?cycle_id=`, `POST /admin/blocks` (manual/debarred: student, cycle, scope, remark), `DELETE /admin/blocks/{block}` = unblock (sets inactive + unblocked_by/at; NEVER hard-delete; audit; admin-is-god Q5.2). UI: within cycle page + student detail Offers & Blocks tab.
- **M7.4** Placed-elsewhere removal (B3/Q3.7): on the pipeline Applicants tab, flagged rows get "Remove from process" → dialog (checkbox "Notify company & invite replacements" default ON) → result `rejected` remark "Selected elsewhere via CDC", E9 to company, audit.
- **M7.5** Frontend: `app/admin/postings/[id]/results/page.jsx` — the announcement console: table of final selected with editable offer-type select, CTC/stipend inputs (prefilled), block toggle with auto-suggested value + scope label, one Publish button with a summary confirm ("12 offers · 11 blocks · 47 regret mails"). Student dashboard + applications page show offer outcome; student profile shows active blocks with reason ("You accepted a Full-Time offer — blocked for this cycle").
- **Acceptance:** publish FT results → offers rows correct, blocks `scope=all` created, other applications flagged, `intern_performance_ppo` blocks only internships (student still eligible for another FT posting — verify via EligibilityService test), unblock restores eligibility, everything in audit_logs.

## M8 — Events & calendar

- **M8.1** Migration C14 + `AdminEventController` CRUD + `publish` (E6 to resolved audience via MailDispatchService) + student `GET /student/events` (published, audience-filtered) + company `GET /company/events` (their own). Only `title`, `starts_at`, `event_type` required (Q7.1 "not all have to be filled").
- **M8.2** `GET /{admin|student}/calendar?month=YYYY-MM` → merged items: events, posting deadlines, scheduled round dates (student: only enrolled-cycle postings they can see; admin: everything).
- **M8.3** Frontend: `app/admin/events/page.jsx` (list + create/edit dialog: audience picker = all / programme-branch multiselect / posting select), `app/student/events/page.jsx` (cards), `app/{admin,student}/calendar/page.jsx` — build a month-grid calendar as a plain MUI component (`components/shared/monthcalendar.jsx`, JSX, no new heavy dependency): day cells with colour-dotted items, click → popover list. Nav entries.
- **Acceptance:** branch-scoped event mails only that branch; calendar shows a floated posting's deadline for an enrolled student and not for a student in no cycle.

## M9 — Exports

- **M9.1** `ExportService` (`app/Services/ExportService.php`) using PhpSpreadsheet: `applicantsWorkbook(JobPosting $p, string $audience /* admin|company */): StreamedResponse` — one sheet; header row bold maroon; columns per B7 field policy (admin = everything incl. answers, flags, contact; company = restricted set + contact only if `share_contact_details`); resume column = clickable hyperlink cell to `Resume::signedUrl(30)`; per-round status columns appended dynamically. Also `studentsWorkbook(PlacementCycle $c)` (admin only).
- **M9.2** Routes: `GET /admin/postings/{p}/export`, `GET /admin/placement-cycles/{c}/students/export`, `GET /company/postings/{p}/export` (own postings, any time per Q8.2). Wire buttons into the M6 pages using the existing `adminDownload` pattern (read `lib/adminapi.ts`'s `adminDownload` and replicate in `lib/studentapi.js`/company usage — Content-Disposition parsing included).
- **Acceptance:** company export of another company's posting → 404; company export lacks phone/personal-email unless toggle on; links in the xlsx open resumes logged-out.

## M10 — Dashboards, seeder, audit UI, final QA

- **M10.1** `AdminDashboardController` v2 (new endpoint `GET /admin/dashboard/cycle/{cycle}` — do NOT break the existing `/admin/dashboard` response): stats per B7 Dashboards + `applications_over_time` (daily counts) + offers by type + CTC quartiles (median included). Frontend `app/admin/analytics/page.jsx`: cycle switcher, stat cards, `@mui/x-charts` BarChart (applications/day), PieChart (offers by type), branch-wise table with placed-% progress bars. Free rein on polish — this is the showpiece.
- **M10.2** Student dashboard `app/student/page.jsx`: greeting, profile/resume status card, active applications mini-trail, **nudge list** "Eligible, closing soon, not applied" (query: eligible ∧ no application ∧ deadline future, ordered by deadline), upcoming calendar items, offer banner if placed.
- **M10.3** Audit log UI `app/admin/audit-logs/page.jsx`: paginated, filter by admin/action/date, before→after JSON diff viewer (simple two-column `<pre>`).
- **M10.4** Seeder `Phase2DemoSeeder` (registered but commented in `DatabaseSeeder`, like Phase 1's optional seeders): 2 cycles (FT 2026-27 open, Internship 2026-27 open); 120 students with REALISTIC data (real IIT-ISM roll format e.g. `22JE0459`, real branch names from the catalogue, CGPA normal-distributed 6.0–9.8, realistic Indian names via faker `en_IN` locale); everyone enrolled; 3 companies + 2 accepted JNFs + 1 accepted INF floated with questions; one posting taken all the way: 60 applications → OLT results → interview → final: 8 offers (mixed types incl. one `intern_performance_ppo`), blocks, 2 waitlisted, 1 addendum — so every screen has data on `migrate:fresh --seed` + uncommenting.
- **M10.5** Final QA sweep: run the full Definition-of-Done list; walk every acceptance check of M1–M9 once against seeded data; run Playwright smoke (`npm run test:e2e`); write a `## PHASE 2 COMPLETE — HANDOVER` section in `PHASE2_PROGRESS.md` listing every new env var, the queue-worker requirement, and anything deferred to Phase 3 (job alerts, credit scores, season report, institute-DB sync hookup).

---

# PART E — WHEN YOU STOP

Whenever you stop — end of a session, a blocker, an error you cannot resolve after 3 attempts, or the user interrupts — you MUST, before stopping:
1. Update `PHASE2_PROGRESS.md` → `## CURRENT STATE` with the exact stop point and `NEXT ACTION`.
2. If blocked: write the precise question under `## BLOCKED / QUESTIONS FOR OWNER` and repeat it in your final chat message.
3. Tell the user in chat, in one short block: what got done this session, where you stopped, and the single next action.

Resume protocol for every new session: read Part A → read `PHASE2_PROGRESS.md` → execute `NEXT ACTION`. Do not re-plan finished work; trust the progress file.
