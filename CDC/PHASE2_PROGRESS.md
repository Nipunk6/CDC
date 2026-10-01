# PHASE 2 PROGRESS
Spec: PHASE2_IMPLEMENTATION_SPEC.md (v1.0). Stack: Laravel12+MySQL / Next16+JS(.jsx new files only) / MUI.

## CURRENT STATE
- Working on: Phase 2 complete (M0–M10) + final audit fixes
- Last completed: D88 final cross-cutting audit follow-ups
- Half-done: nothing
- Pending commands: none. MySQL is at migrate:fresh --seed + Phase2DemoSeeder + qa-admin@example.test (local QA admin). Run `npx playwright install chromium` before `npm run test:e2e`.
- Nothing from Phase 2 after M1 is committed — the owner commits manually.
- NEXT ACTION: Owner review of the handover section below; commit when satisfied.

## BLOCKED / QUESTIONS FOR OWNER
- Q1 (lint) — CLOSED 2026-09-27: owner kept the D14 policy, approved deleting the six root `test-*.js` scratch scripts, asked for the Phase 1 lint errors to be fixed (20 → 4, see D30/D31), then decided not to refactor the last four `react-hooks/set-state-in-effect` sites: they are suppressed with a pointer to D33 and tracked there as a deferred hardening item. **`npm run lint` now exits 0 (0 errors, 82 warnings).**
- Q2 (`.env.example` mail password) — ANSWERED 2026-09-27: owner accepts it as is; no change.
- Note (not a question): the build failed at baseline on 57 Phase 1 TS errors, not the 2 the spec lists; fixed type-only per the spec's method (D13). `next start` also failed at baseline with Auth.js `UntrustedHost`; fixed with `trustHost: true` (D17).

## UI REGRESSION — root cause (2026-09-27, feature work halted)

Diagnosed against baseline `df7034b` (last Phase 1 commit) → `HEAD 7bcff0a`. M0/M1 are committed, so `git diff HEAD` shows nothing; every comparison below is `git diff df7034b..HEAD`.

### The regression: one nav item overflowed the fixed single-row AppBar
`components/admin/adminshell.tsx` renders the desktop nav as a **non-wrapping** `<Stack direction="row">` with `display: { xs: "none", lg: "flex" }` inside `<Container maxWidth="xl">`; below `lg` it is hidden and the existing Drawer takes over. Phase 1 showed a super-admin **9** nav buttons + Sign Out. M1.3 appended a 10th item, `Placement Cycles` — also the longest new label — making 11 elements in one row. At `lg` (~1152px of container width) the row no longer fits, so `Button` labels wrap to two lines and the absolutely-positioned Notifications `<Badge>` collides with its neighbour. The seeded admin is a super admin, so they see the worst case (Manage Admins present).
**Cause is the permitted nav-array addition alone — no styling, theme, font, spacing or layout change anywhere.**

### Confirmed NOT the cause
- `lib/theme.ts`, `app/globals.css`, `app/themeregistry.tsx`, `app/layout.tsx` — **byte-identical to Phase 1**, never edited.
- `components/admin/adminshell.tsx` — carries exactly two permitted edits: the `handleSignOut` token-revoke and the one nav entry (plus the two `onClick` swaps that the revoke requires). Nothing else.
- `components/company/companyshell.tsx` — carries only the `handleSignOut` token-revoke. **No nav change**, so `/company` is visually identical to Phase 1.
- **Frontend dependency versions unchanged.** `CDC/frontend/package.json` is byte-identical to Phase 1; `@mui/material`/`@mui/icons-material` 6.5.x, `@emotion/react` 11.14.0, `@emotion/styled` 11.14.1, `react`/`react-dom` 19.2.4, `next` 16.2.1. `package-lock.json` differs by exactly **one** line (`- "dev": true,` on the `fsevents` entry) and that line was already modified before Phase 2 began — it appears in the opening `git status` of the session. No `rm -rf node_modules` / `npm ci` is warranted.
- New M1 pages are not stylistically divergent: same maroon gradient header string as `app/admin/jnfs/page.tsx`, same Card/Chip/Stack idioms, theme tokens only, and the single `alpha("#fff", 0.1)` they use is the same call `app/admin/jnfs/page.tsx` already makes. No hardcoded hex, no fonts, no CSS classes, no `styled()`.

### A separate real defect found while diagnosing (not visual)
`@mui/x-charts@9.14.0` was installed into **`CDC/backend`** instead of `CDC/frontend`. `CDC/backend/package.json` now carries a bogus `"dependencies": { "@mui/x-charts": "^9.14.0" }` and it is committed; the package sits in `CDC/backend/node_modules`. Root cause: the M0.4 composer and npm installs were run as two **parallel background shell commands** and their working directories raced — the same fault that later produced `Could not open input file: artisan`. Consequences: **M0.4 is not actually complete** (the frontend never received x-charts), and x-charts **9.x targets MUI 7**, so it is the wrong major for this MUI 6.5 app regardless. Nothing imports it (charts are M10), so it has had **zero** effect on the UI.
**Process fix:** never run two `cd`-ing commands in parallel; use `git -C <repo>` and absolute paths; package changes only via `npm` in `CDC/frontend`, only for spec-named packages, and logged in PHASE2_DECISIONS.md.

### Resolution applied 2026-09-27 (owner-approved)
- **Nav** (D34): label `Placement Cycles` → `Cycles`; `whiteSpace: nowrap` on nav buttons; removed `overlap="circular"` from the Notifications badge (the actual cause of the badge collision); desktop row hands over to the existing Drawer below `xl` instead of `lg` — both toggles moved together. Measurement showed the Phase 1 AppBar was **already** over-subscribed (super admin needed ~1487px vs 1392px at 1440px; normal admin overflowed at 1280px), so restoring Phase 1 verbatim would not have produced a clean row either.
- **Global justify removed** (D35): `typography.allVariants.textAlign` in `lib/theme.ts` and `body { text-align: justify }` in `app/globals.css`. Owner-approved Phase 1 visual change.
- **Backend depollution** (D36): `CDC/backend/package.json` + `package-lock.json` byte-identical to `df7034b` again; `CDC/backend/node_modules/@mui` deleted; x-charts deferred to M10; M0.4 reopened above.
- **NOT verified in a browser** (D38): this session has no browser automation (no Chrome extension, no built-in browser, no computer-use; `WebFetch` refuses localhost), so the owner's Step 1 baseline screenshots and Step 5 multi-width check could not be run. The nav numbers are arithmetic, and the ≥`xl` case is within estimate error. **Owner confirmation required at 1536px+; fallback is condensing `Alumni Outreach`→`Alumni` and `Policy Documents`→`Policies` (needs approval, changes Phase 1 nav text).**

## RULES (binding from 2026-09-27)
1. **Phase 1 visual files are frozen.** `components/admin/adminshell.tsx`, `components/company/companyshell.tsx`, `lib/theme.ts`, `app/globals.css`, `app/layout.tsx`, `app/themeregistry.tsx`, `components/forms/shared/*`, and every pre-existing Phase 1 page. Permitted edits only: nav-array entries for new pages; the logout token-revoke; type-only fixes; the four `eslint-disable` comments (D33); and any change the owner explicitly approves in writing (so far: D34 nav rendering, D35 global justify).
2. **Nav additions are nav-array entries only** — never a new nav design, component or layout system. Labels stay short; the AppBar has a hard width budget (see D34 arithmetic).
3. **New pages must be visually indistinguishable in style from Phase 1**: `lib/theme.ts` tokens only, the same maroon gradient page header, the same Card/Chip/Stack/Grid2 idioms, the same spacing scale. No new fonts, no new colour values, no CSS classes, no `styled()`, no second layout system.
4. **Browser check at 390px and 1440px is part of every milestone's Definition of Done** from now on, alongside `migrate:fresh --seed`, `php artisan test`, `tsc --noEmit`, `npm run lint` and `npm run build`. If browser tooling is unavailable in a session, the milestone is **not** done — say so explicitly rather than inferring the result (see D38).
5. **No parallel `cd`-ing shell commands.** Always absolute paths and `git -C <repo>`. Package changes only via `npm` in `CDC/frontend` or `composer` in `CDC/backend`, only for spec-named packages, each logged in PHASE2_DECISIONS.md. Never install or modify packages through Python or any indirect mechanism (D37).

## MILESTONE CHECKLIST
- [x] M0 Foundations
  - [x] M0.1 PHASE2_PROGRESS.md + PHASE2_DECISIONS.md + CLAUDE.md notices (frontend appended, backend created)
  - [x] M0.2 MySQL env (already `mysql`/`iitism_placement`; DB existed), `.env`+`.env.example` stray `a` removed + `COMPANY_RECRUITER_VERIFY_TTL_MINUTES=30` added, `storage:link`, `migrate:fresh --seed` OK
  - [x] M0.3a `POST /auth/admin/register` route + `AuthController@registerAdmin` deleted
  - [x] M0.3b `StoreJnfRequest`/`StoreInfRequest`: `status` ∈ {draft, submitted}, `admin_remarks` removed; stripped in store/update/autosave of both company controllers
  - [x] M0.3c `config/sanctum.php` expiration 7 days; adminshell + companyshell call `POST /auth/logout` (token revoke) before `signOut()`
  - [x] M0.3d `app/api/proxy-pdf/route.ts`: `auth()` required (401), origin must equal app origin or `NEXT_PUBLIC_API_URL` origin (else 400)
  - [x] M0.3e Company `DELETE /company/jnfs|infs/{id}`: 422 when floated (`Jnf::isFloated()` / `Inf::isFloated()`, table-guarded until M5)
  - [x] **M0.4 completed in M10** (D79): `@mui/x-charts@^7.29.1` installed in `CDC/frontend`. Original note: — `allowJs` (was already true) and `phpoffice/phpspreadsheet` ^5 are done, but **`@mui/x-charts` was never installed in the frontend**: it went into `CDC/backend` by mistake and has been reverted (D36). Deferred to M10, where it must be installed in `CDC/frontend` at a MUI-6.5-compatible major (9.x targets MUI 7 — wrong for this app).
  - [x] M0.5 Migrations C1 (`2026_09_27_000001`), C16 (`…000002`), C17 (`…000003`); models `PortalSetting`, `AuditLog`; `SettingsService`, `AuditService`; `config/programmes.php` (8/52, generated from TSX); `App\Support\ProgrammeCatalogue`; `EnsureUserIsActive` aliased `active` and applied to every `auth:sanctum` group; `PortalSettingSeeder`; `User.is_active` fillable/cast + `studentProfile()` hasOne
  - [x] M0.6 `types/next-auth.d.ts` (role union ×3 + `rollNo`), `auth.ts` (`StudentOnlyError`, `roll_no` passthrough, `trustHost`), `proxy.ts` (`/student` gate + matcher + fail-closed session), `AuthController@login`/`forgotPassword` accept `email` OR `roll_no` + 403 suspended, login page student variant (Roll Number field, student feature copy)
  - [x] M0 Acceptance: `migrate:fresh --seed` on MySQL ✔ (`users.role` = enum('admin','company','student')); `php artisan test` 25/25 ✔; admin login unchanged (live curl 200 + token, dashboard 200, logout → token 401) ✔; `POST /api/auth/admin/register` → 404 ✔; company `status: accepted` → 422 ✔ (test); proxy-pdf no session → 401 ✔ (live `next start`); `/student` unauth → `/auth/login/student?callbackUrl=/student` ✔; `tsc --noEmit` 0 errors ✔; `npm run build` ✔; lint: 0 new errors (baseline 25 pre-existing, see Q1)
- [x] M1 Placement cycles
  - [x] M1.1 C2 migration (`2026_09_27_000004`) + `PlacementCycle` model + `AdminPlacementCycleController` (index with enrolled/postings/offers counts, store, show, update, close); `allowed_programmes` validated against `ProgrammeCatalogue`; every write audited (`cycle.create|update|close`); 8 routes under `['auth:sanctum','active','role:admin']`
  - [x] M1.2 C4 migration (`2026_09_27_000005`) + `CycleEnrollment` model + `POST .../enroll` (pasted `roll_nos` OR uploaded xlsx/csv → `{enrolled, already_enrolled, errors:[{row, roll_no, reason}]}`), `DELETE .../enroll/{studentProfile}`, `GET .../enrollments` (search + 50/page); `SpreadsheetImportService` added
  - [x] M1.3 `app/admin/placement-cycles/page.jsx` (card list, counts, Add-cycle dialog with programme/batch builder, close action) + `app/admin/placement-cycles/[id]/page.jsx` (Overview / Enrolled Students with bulk-enrol + error report + search + pagination / Postings placeholder); "Placement Cycles" added to adminshell nav
  - [x] M1 Acceptance: cycle created live ✔; non-catalogue programme → 422 ✔; pasted roll list → per-row error report (unknown + duplicate rows both reported) ✔; CSV upload reports real spreadsheet row numbers (2, 3 — header skipped) ✔; enrollments list returns `meta` ✔; update + close ✔, second close → 422 ✔; `audit_logs` holds cycle.create/update/close with user + ip ✔; company user → 403 ✔; Phase 1 admin dashboard + JNF queue still 200 ✔; `migrate:fresh --seed` ✔; `php artisan test` 40/40 ✔; `tsc` 0 errors ✔; `npm run build` ✔ (routes `/admin/placement-cycles`, `/admin/placement-cycles/[id]`); `npm run lint` exits 0 (0 errors; the last 4 suppressed per D33)
- [x] M2 Students module
  - [x] M2.1 C3 schema in the stub migration; C15 `2026_09_27_000006_create_branch_change_requests_table`; `StudentProfile` (fillable, casts, `SELF_EDITABLE`, hidden `photo_path` + `has_photo`), `BranchChangeRequest`, `StudentProfileFactory`
  - [x] M2.2 `AdminStudentController` (index 50/page + filters, show + audit trail, store + E1, update + E8 + before/after audit, suspend/reactivate (+token revoke), resend-invitation, bulkImport with `?dry_run=1`, import + academics templates, academicBulkUpdate) + `StudentAccountService` + `StudentAcademicSyncService` + `MailDispatchService` (D39) + `StudentInvitationMail`/`StudentProfileUpdatedMail` on a shared maroon layout
  - [x] M2.3 `StudentProfileController` (show, PATCH only the 5 personal fields, photo upload/stream on the private disk)
  - [x] M2.4 `StudentBranchChangeController` (submit, 409 on second pending, list) + `AdminBranchChangeController` (queue 50/page, approve updates profile in a transaction + audit + E8, reject requires remark)
  - [x] M2.5 `app/admin/students/page.jsx`, `app/admin/students/[id]/page.jsx`, `app/admin/branch-changes/page.jsx`, `components/admin/studentformdialog.jsx`, `components/admin/spreadsheetimportdialog.jsx`; nav entries (drawer, D40)
  - [x] M2.6 `lib/studentapi.js`, `components/student/studentshell.jsx`, `app/student/layout.jsx`, `app/student/page.jsx` (placeholder), `app/student/profile/page.jsx`, `app/student/notifications/page.jsx`
  - [x] M2 Acceptance: live MySQL run — 10-row CSV with 2 bad rows → dry-run reported exactly rows 4 and 7; real import created 8 users; 8 `email_logs` rows (queued) + 8 jobs, `queue:work` delivered them (log mailer); set password via the invitation link → roll-number login 200 with `student_profile`; suspend → login 403 "Account suspended. Contact CDC."; reactivate; `audit_logs` has student.import/suspend/reactivate. Browser: roll-number login lands on /student with the sidebar; /student/profile at 375px has no horizontal scroll; /admin/students and /admin/students/1 render at 1440px. `php artisan test` 51/51; `npm run build` OK; lint 0 errors.
- [x] M3 Resumes
  - [x] M3.1 C5 `2026_09_27_000007_create_resumes_table` + `Resume` model (`isLocked()`, `signedUrl()`, `previewUrl()`) + `FileUploadService::uploadResume()` (private disk `resumes/{roll}/{slot}_{uuid}.pdf`)
  - [x] M3.2 `StudentResumeController` (index, store/replace → pending + old file deleted, label rename, destroy with lock check, own stream)
  - [x] M3.3 `AdminResumeController` (paginated queue + counts + search, stream, approve/reject-with-remark → audit + E7 `ResumeReviewedMail` + clears `used_unverified_resume` on approval)
  - [x] M3.4 `GET /api/resumes/signed/{resume}` (`resumes.signed`, `signed` middleware only)
  - [x] M3.5 `app/student/resumes/page.jsx` (8 slot cards), `app/admin/resumes/page.jsx` (queue + proxied iframe preview, D49); nav entry
  - [x] M3 Acceptance: 2 MB+1 byte → 422 "must be 2 MB or smaller"; reject → re-upload → `pending` with remark cleared and old file deleted; approve via the browser UI; signed URL streams `application/pdf` logged-out (200), tampered → 403, expired (test, +31 days) → 403; files only under `storage/app/private/resumes/`. `php artisan test` 57/57. Browser: admin queue with PDF preview at 1440px; student resumes at 375px, no horizontal scroll.
- [x] M4 Phase 1 form change (numeric backlogs + 10th/12th)
  - [x] M4.1 `eligibilitygrid.tsx`: optional per-branch `maxOngoingBacklogs`/`maxTotalBacklogs` (+ global inputs feeding "Apply to All Selected"), disabled/cleared when backlogs are off (D57)
  - [x] M4.2 `jnfformpro.tsx` + `infformpro.tsx`: `minTenthPercent`/`minTwelfthPercent` on tab 2; `formpreview.tsx` shows them; both admin detail pages edit + display them; `AdminFormReviewController` CSV columns + change labels (D58)
  - [x] M4.3 nothing migrates; `EligibilityService` (M5) implements B2.7 legacy semantics
  - [x] M4 Acceptance: feature test — company draft with the new keys stores them verbatim in `form_data`; accepted JNF CSV contains `min_tenth_percent`, `min_twelfth_percent`, `70.5` and `ongoing ≤ 1, total ≤ 2`; a legacy form without the keys still renders (`GET /admin/jnfs/{id}`) and exports. Browser: admin JNF detail shows Min 10th 80 / Min 12th 75; company wizard tab 2 shows the per-branch caps (disabled on the "NO" branch) and the two cutoff fields populated from saved data. `php artisan test` 61/61, `tsc` 0 errors.
- [x] M2 audit follow-ups (background audit agent): bulk-import throughput + per-student audit (D54/D55), student set-password page (D53), catalogue re-check only on change, atomic pending branch change (D56), N+1 in academic sync, store() backlog default, audit tab includes branch-change rows, import dialog resets pagination.
- [x] M5 Floating & student job board
  - [x] M5.1 C6 `…000008_create_job_postings_table`, C7 `…000009_create_posting_questions_table`, C8 `…000010_create_posting_rounds_table`, C9 `…000011_create_applications_table` (C8/C9 pulled forward, D62); models `JobPosting` (snapshot keys, round labels, `eligibilityRules()`, `compensationFor()`), `PostingQuestion`, `PostingRound`, `Application`; `Jnf/Inf::jobPosting()` + real `isFloated()` (D3 closed); `Resume::isLocked()` now a relation query
  - [x] M5.2 `EligibilityService` (check + `eligibleStudentsQuery`, D63) + `EligibilityServiceTest` (13 cases incl. query/check agreement)
  - [x] M5.3 `AdminPostingController` (float with all guards + snapshot + rounds + questions + audit + E2 job; index; for-form; preview-eligibility; show with stats; update deadline/questions/contact toggle; close/reopen/cancel; round add/update/delete/reorder with one-final rule); `components/admin/floatdialog.jsx` + `questionbuilder.jsx` inserted into both admin JNF/INF detail pages; `app/admin/postings/page.jsx` + `[id]/page.jsx` (Overview/Applicants/Rounds/Questions tabs); cycle page Postings tab filled
  - [x] M5.4 `MailDispatchService` (built in M2, D39) + `AdminSettingsController` + `app/admin/settings/page.jsx`; `SendPostingFloatedMails` job (chunk 100). **Production must run `php artisan queue:work`.**
  - [x] M5.5 `StudentPostingController` (board 20/page with filters, detail) + `App\Support\PostingPresenter` (whitelisted payloads, D64)
  - [x] M5.6 `StudentApplicationController` (apply/re-apply, edit resume+answers, withdraw, list with trail) + E3 `ApplicationSubmittedMail`
  - [x] M5.7 `app/student/postings/page.jsx`, `[id]/page.jsx` (PostingPreview + sticky ApplyPanel), `app/student/applications/page.jsx` (RoundTrail stepper)
  - [x] M5 Acceptance (live, MySQL): FT cycle with 8 enrolled; float of accepted JNF via the browser → preview said 6/8 eligible; after `queue:work` exactly those 6 institute emails have E2 `email_logs` rows (the student with an ongoing backlog and the one below the 10th cutoff excluded) = `eligibleStudentsQuery()->count()`; INF floated from its own detail page into the internship cycle (7/8 — no 10th cutoff). Student board on 375px: both cards with countdown + eligibility chips, no horizontal scroll; applying without the required answer shows the server message; applying succeeds and the progress stepper appears. Ineligible student: board reasons + apply 422 "Ongoing backlogs above the limit (1 > 0)." Deadline-passed apply/edit/withdraw 422 covered by `PostingFlowTest` (9 tests). No applicant counts in any student payload (asserted). `php artisan test` 84/84, `npm run build` OK, lint 0 errors.
- [x] M3/M4 audit follow-ups: PDF proxy hardening (D59, HIGH), stale-approve 409 + slot upload lock + signed-file limiter (D60), M4 range validation + caps on preview chips (D61), dialog-local errors, queue page clamp, preview-link refresh, blob URL release.
- [x] M6 Pipeline
  - [x] M6.1 C10 `…000012_create_application_round_results_table`, C11 `…000013_create_shortlist_proposals_table`; models `ApplicationRoundResult`, `ShortlistProposal`; relations on Application/PostingRound/JobPosting
  - [x] M6.2 `AdminPipelineController` (grid data, draft results from JSON / roll paste / xlsx with unknown + previous-round reporting, attendance, publish with reject-remaining + E4 mails, re-add protocol + company notice, draft delete) on `PipelineService` (D70)
  - [x] M6.3 waitlist ranks + reorder; auto-suggested promotions into the next round — **superseded by D90 (owner): waitlists are unranked, no reorder, no auto-suggestions; any waitlisted candidate can be moved on**
  - [x] M6.4 `CompanyPipelineController` (own postings only, 404 otherwise; applicants with Q10.2 fields, signed resume links, published outcomes only, contact only when shared; proposals + E10 to admins) + `StakeholderNotifier` + `PortalNoticeMail`
  - [x] M6.5 `AdminProposalController` (queue 50/page, approve → drafts, reject with remark, E10 back to company)
  - [x] M6.6 admin posting tabs Pipeline (sticky grid, dashed drafts vs filled published, per-round menu: results / attendance / addendum / publish, re-add dialog, suggestions banner), Waitlist (@hello-pangea/dnd), Proposals; `app/admin/proposals/page.jsx`; company `app/company/postings/page.jsx` + `[id]/page.jsx` (applicants, round chips, propose dialog with roll picker, my proposals) + nav "Drives"; `lib/companydownload.js`
  - [x] M6 Acceptance (live): company proposed a shortlist in the browser → admin Proposals tab approved it → pipeline showed 3 dashed drafts, student trail still unpublished → Publish with "mark everyone else" → "3 selected, 0 waitlisted, 3 not selected"; next round went ongoing. Tests (`PipelineTest`, 7): proposal→approve→draft invisible to student/company→publish→selected mail + regret mail + trail/company view update; unknown roll reported (company proposal 422, admin upload listed); waitlist reorder persists; re-add needs confirm+remark, mails the company, audit `round.readd`; final round refused; promotions suggested in rank order; company isolation and contact sharing.
- [x] M6 QA audit follow-ups (3 HIGH + 12): D75 — regret mails after attendance, queued chunked E4 job, applied-only publishing, round order, suggestion/empty-publish guards, addendum forcing, earlier-round re-add rule, waitlist removal + company full-waitlist proposals, proposal/open guards + dedupe, structure freeze, stale status UI, trail waitlist stop + Eligible step; tests +4.
- [x] M5 QA audit follow-ups: floated form guard (D66), closed cycle stops applications (D67), batch fallback + PhD exemption + rounding parity + block memo (D68), IST round dates, nested MCQ sanitising, apply panel for applied-but-ineligible, UI fixes (completed label, cancelled link, deadline validation, final checkbox) (D69); tests +4.
- [x] M7 Results, offers, blocking
  - [x] M7.1 C12 `…000014_create_offers_table`, C13 `…000015_create_placement_blocks_table` (index name D72); models `Offer` (types + labels), `PlacementBlock` (`message()`); relations on StudentProfile/Application
  - [x] M7.2 `AdminResultController` (prepare with suggested type/CTC/stipend/block per student + waitlist promotions + active-block warnings; publish per D73) + `BlockingPolicy` (matrix docblock) + E5 `OfferMail`
  - [x] M7.3 `AdminBlockController` (list by cycle/student, add manual/debarred, lift) + `StudentBlocksPanel` on the student page and a Blocks tab on the cycle page
  - [x] M7.4 remove-from-process (D74) + E9 notice; "Remove from process" button on flagged rows of the posting Applicants tab
  - [x] M7.5 `app/admin/postings/[id]/results/page.jsx` (announcement console with type select, prefilled CTC/stipend, auto-suggested block + override, promote-from-waitlist, confirm summary "N offers · N blocks · N regret mails"); "Results & Offers" button on the posting page; student applications page shows the offer, student profile shows active blocks with reason
  - [x] M7 Acceptance: live — pushed the JNF through rounds 2–3, final draft 2 selected + 1 waitlisted; console showed 5 vacancies / 1 open place; switching one offer to Intern + performance-based PPO auto-switched its block to "internships only"; promoted the waitlisted student; publish → 3 offers, 3 blocks (all / internships_only / all), posting completed, 7 audit rows; student sees "Offer: Full-Time ₹18,00,000 p.a." and the profile block sentence (375px, no overflow). Tests (`ResultsAndBlocksTest`, 3): FT publish creates offers/blocks/flags/mails, regret to the rest of the final pool, blocked student ineligible for another posting ("Blocked: accepted a Full-Time offer."), PPO-offered not blocked, no double offer; `intern_performance_ppo` blocks only internships and the student stays eligible for another FT posting; debar + lift restores eligibility and keeps the row; remove-from-process mails the company once. `php artisan test` 98/98.
- [x] M8 Events & calendar
  - [x] M8.1 C14 `…000016_create_events_table`; `CampusEvent` (audience query, D76); `AdminEventController` (list upcoming/past with audience counts, create/update/delete, publish → E6 job) ; `EventFeedController` (student audience list, company own list); `EventAnnouncedMail`, `SendEventAnnouncements`
  - [x] M8.2 `CalendarController` admin + student (D77)
  - [x] M8.3 `app/admin/events/page.jsx` (dialog with company picker, RichTextEditor, audience = all / programme-branch multiselect / posting), `app/student/events/page.jsx`, `components/shared/{eventcard,monthcalendar}.jsx`, `app/{admin,student}/calendar/page.jsx`, company Drives page lists own events; admin drawer entries Events + Calendar
  - [x] M8 Acceptance: tests (`EventsCalendarTest`, 3) — a Mining-branch event mails only the Mining student (1 mail), CSE student sees no event, publish twice 422, bad branch 422, audit `event.publish`; a floated posting's deadline appears in the enrolled student's calendar with a link and not in a student with no cycle; company sees only its own published events. Live: CSE-branch PPT published → 8 E6 `email_logs` after `queue:work`; student calendar at 375px shows the event dot and both deadlines, no horizontal scroll; admin calendar at 1440px shows labelled items. `php artisan test` 105/105, `npm run build` OK.
- [x] M9 Exports
  - [x] M9.1 `ExportService::applicantsWorkbook($posting, 'admin'|'company')` + `studentsWorkbook($cycle)` (D78)
  - [x] M9.2 routes `GET /admin/postings/{p}/export`, `GET /admin/placement-cycles/{c}/students/export`, `GET /company/postings/{p}/export`; buttons on the admin posting page, the cycle page and the company drive page
  - [x] M9 Acceptance: tests (`ExportTest`, 3) — admin sheet has contact, flags, answers, round and resume-link columns and the hyperlink opens logged-out (200); company sheet lacks phone/personal email/flags until sharing is turned on, then shows the phone; another company's posting export → 404; cycle export lists the enrolled student with 1 live application. Live: admin export `acme-analytics-software-engineer-applicants.xlsx` (6 rows), first hyperlink fetched without auth → 200 `application/pdf`; company export of its own posting → 200. `php artisan test` 108/108.
- [x] M10 Dashboards, seeder, audit UI, final QA
  - [x] M10.1 `AdminAnalyticsController` (overview + per-cycle, D80) + `app/admin/analytics/page.jsx` with `@mui/x-charts` (D79)
  - [x] M10.2 `StudentDashboardController` + `app/student/page.jsx` (greeting, offers banner, blocks, resume card, counts, nudges, recent trails, next 14 days) (D81)
  - [x] M10.3 `AdminAuditLogController` + `app/admin/audit-logs/page.jsx` (D83)
  - [x] M10.4 `Phase2DemoSeeder` (D82) + `Phase2DemoSeederTest`; registered commented in `DatabaseSeeder`
  - [x] M10.5 Final QA sweep + handover (see section below)

## KNOWN TRANSIENTS (close in the named milestone)
- `AdminPlacementCycleController::relatedCount()` returns 0 for `job_postings` (M5) and `offers` (M7) until those tables exist.
- `Jnf::isFloated()` / `Inf::isFloated()` use `Schema::hasTable('job_postings')` until M5 adds the real relation (D3).

## CHANGELOG
- 2026-09-27 · M0.1 · created PHASE2_PROGRESS.md, PHASE2_DECISIONS.md; frontend/CLAUDE.md appended; backend/CLAUDE.md created
- 2026-09-27 · M0.2 · backend/.env, backend/.env.example (stray `a` removed, TTL var added); `php artisan storage:link`
- 2026-09-27 · M0.3a · routes/api.php, app/Http/Controllers/AuthController.php
- 2026-09-27 · M0.3b · app/Http/Requests/StoreJnfRequest.php, StoreInfRequest.php, app/Http/Controllers/CompanyJnfController.php, CompanyInfController.php
- 2026-09-27 · M0.3c · config/sanctum.php, frontend/components/admin/adminshell.tsx, frontend/components/company/companyshell.tsx
- 2026-09-27 · M0.3d · frontend/app/api/proxy-pdf/route.ts
- 2026-09-27 · M0.3e · CompanyJnfController.php, CompanyInfController.php (destroy), app/Models/Jnf.php, app/Models/Inf.php (`isFloated()`)
- 2026-09-27 · M0.4 · backend/composer.json+lock (phpspreadsheet), frontend/package.json+lock (@mui/x-charts)
- 2026-09-27 · M0.5 · database/migrations/2026_09_27_000001…000003, app/Models/PortalSetting.php, AuditLog.php, app/Services/SettingsService.php, AuditService.php, config/programmes.php, app/Support/ProgrammeCatalogue.php, app/Http/Middleware/EnsureUserIsActive.php, bootstrap/app.php, routes/api.php (`active`), database/seeders/PortalSettingSeeder.php, DatabaseSeeder.php, app/Models/User.php, app/Models/StudentProfile.php (user() only), database/factories/UserFactory.php (`is_active`)
- 2026-09-27 · M0.6 · frontend/types/next-auth.d.ts, auth.ts, proxy.ts, app/auth/login/[type]/page.tsx; backend AuthController.php (roll_no login, suspension 403, friendly validation messages)
- 2026-09-27 · M0-TS · type-only fixes so `next build` passes (D13): frontend/app/admin/jnfs/[id]/page.tsx, app/admin/infs/[id]/page.tsx, app/company/jnf/[id]/page.tsx, app/company/inf/[id]/page.tsx
- 2026-09-27 · M0-TEST · tests/Feature/Phase2FoundationsTest.php (10 tests: register 404, status lockdown, admin_remarks strip, draft delete, login validation, suspension login+middleware, active unaffected, SettingsService+audit, ProgrammeCatalogue)
- 2026-09-27 · M0-ACC · acceptance run: MySQL migrate:fresh --seed, php artisan test 25/25, tsc 0, next build OK, live curl smoke (backend :8099, frontend `next start` :3999) all as expected
- 2026-09-27 · M1.1 · database/migrations/2026_09_27_000004_create_placement_cycles_table.php, app/Models/PlacementCycle.php, app/Http/Controllers/AdminPlacementCycleController.php, routes/api.php
- 2026-09-27 · M1.2 · database/migrations/2026_09_27_000005_create_cycle_enrollments_table.php, app/Models/CycleEnrollment.php, app/Services/SpreadsheetImportService.php, AdminPlacementCycleController (enroll/unenroll/enrollments)
- 2026-09-27 · M1.3 · frontend/app/admin/placement-cycles/page.jsx, frontend/app/admin/placement-cycles/[id]/page.jsx, frontend/components/admin/adminshell.tsx (nav entry)
- 2026-09-27 · M1-TEST · tests/Feature/AdminPlacementCycleTest.php (15 tests: create+audit, catalogue validation, date order, batch years, index counts, update before/after, close twice, company 403, unknown-roll report, duplicate report, CSV upload rows, enroll validation, paginated enrollments, unenroll 404+success+audit, FK cascade)
- 2026-09-27 · M1-PERF · AdminPlacementCycleController: batched roll-number resolution (2 queries per batch, chunked at 500) replacing 2 queries per row; memoised Schema::hasTable probes (D28)
- 2026-09-27 · M1-ACC · acceptance run: migrate:fresh --seed, php artisan test 40/40, tsc 0, next build OK, live curl walkthrough of all 8 endpoints + Phase 1 smoke
- 2026-09-30 · M2.1 · migrations 2026_09_21_162407 (C3 filled), 2026_09_27_000006 (C15); app/Models/StudentProfile.php, BranchChangeRequest.php; database/factories/StudentProfileFactory.php; AdminPlacementCycleController (M1 transient guard removed)
- 2026-09-30 · M2.2 · app/Http/Controllers/AdminStudentController.php; app/Services/StudentAccountService.php, StudentAcademicSyncService.php, MailDispatchService.php; app/Mail/StudentInvitationMail.php, StudentProfileUpdatedMail.php; resources/views/emails/{layouts/portal,partials/button,student-invitation,student-profile-updated}.blade.php; ProgrammeCatalogue::resolve; routes/api.php
- 2026-09-30 · M2.3 · app/Http/Controllers/StudentProfileController.php (stub filled); AuthController (login eager-loads studentProfile)
- 2026-09-30 · M2.4 · app/Http/Controllers/StudentBranchChangeController.php, AdminBranchChangeController.php
- 2026-09-30 · M2.5 · frontend app/admin/students/page.jsx, app/admin/students/[id]/page.jsx, app/admin/branch-changes/page.jsx, components/admin/studentformdialog.jsx, spreadsheetimportdialog.jsx, studentblockspanel.jsx (placeholder until M7), components/admin/adminshell.tsx (nav, D40)
- 2026-09-30 · M2.6 · frontend lib/studentapi.js, lib/format.js, lib/adminupload.js, lib/usecatalogue.js, components/shared/pageheader.jsx, components/student/studentshell.jsx, app/student/{layout,page}.jsx, app/student/profile/page.jsx, app/student/notifications/page.jsx
- 2026-09-30 · M2-TEST · tests/Feature/AdminStudentTest.php (11 tests); AdminPlacementCycleTest uses the factory
- 2026-09-30 · M2-ACC · live acceptance (see checklist) + browser check at 375px and 1440px
- 2026-09-30 · M3.1 · database/migrations/2026_09_27_000007_create_resumes_table.php, app/Models/Resume.php, app/Services/FileUploadService.php, StudentProfile::resumes()
- 2026-09-30 · M3.2 · app/Http/Controllers/StudentResumeController.php, routes/api.php
- 2026-09-30 · M3.3 · app/Http/Controllers/AdminResumeController.php, app/Mail/ResumeReviewedMail.php, resources/views/emails/resume-reviewed.blade.php
- 2026-09-30 · M3.4 · routes/api.php (`resumes.signed`)
- 2026-09-30 · M3.5 · frontend app/student/resumes/page.jsx, app/admin/resumes/page.jsx, components/admin/adminshell.tsx (nav)
- 2026-09-30 · M3-TEST · tests/Feature/ResumeTest.php (6 tests)
- 2026-09-30 · M4.1 · frontend components/forms/shared/eligibilitygrid.tsx
- 2026-09-30 · M4.2 · frontend components/forms/jnfformpro.tsx, infformpro.tsx, shared/formpreview.tsx, app/admin/jnfs/[id]/page.tsx, app/admin/infs/[id]/page.tsx; backend AdminFormReviewController.php (CSV + labels)
- 2026-09-30 · M4-TEST · tests/Feature/EligibilityFormFieldsTest.php (2 tests)
- 2026-09-30 · M2-AUDIT · backend app/Jobs/SendStudentInvitation.php, StudentAccountService, AdminStudentController, StudentBranchChangeController, AdminBranchChangeController, StudentAcademicSyncService, app/Models/User.php (student reset URL); frontend app/auth/student/set-password/page.jsx, app/student/profile/page.jsx, app/admin/students/page.jsx; tests (+2)
- 2026-10-01 · M5.1 · migrations 2026_09_27_000008…000011; app/Models/JobPosting.php, PostingQuestion.php, PostingRound.php, Application.php; Jnf/Inf (jobPosting relation); Resume::isLocked; StudentProfile::applications
- 2026-10-01 · M5.2 · app/Services/EligibilityService.php; tests/Feature/EligibilityServiceTest.php
- 2026-10-01 · M5.3 · app/Http/Controllers/AdminPostingController.php; ProgrammeCatalogue::displayName/sameProgramme; frontend components/admin/floatdialog.jsx, questionbuilder.jsx, posting/{overview,applicants,rounds,questions}tab.jsx, cyclepostings.jsx; app/admin/postings/page.jsx, [id]/page.jsx; app/admin/{jnfs,infs}/[id]/page.tsx (FloatDialog insert); app/admin/placement-cycles/[id]/page.jsx
- 2026-10-01 · M5.4 · app/Http/Controllers/AdminSettingsController.php; app/Jobs/SendPostingFloatedMails.php; app/Mail/PostingFloatedMail.php; frontend app/admin/settings/page.jsx
- 2026-10-01 · M5.5/5.6 · app/Support/PostingPresenter.php; app/Http/Controllers/StudentPostingController.php, StudentApplicationController.php; app/Mail/ApplicationSubmittedMail.php; views posting-floated, application-submitted
- 2026-10-01 · M5.7 · frontend components/student/{postingcard,applypanel,roundtrail}.jsx, components/shared/postingpreview.jsx, app/student/postings/page.jsx, [id]/page.jsx, app/student/applications/page.jsx
- 2026-10-01 · M5-TEST · tests/Feature/PostingFlowTest.php (9 tests)
- 2026-10-01 · M3M4-AUDIT · frontend app/api/proxy-pdf/route.ts; backend AdminResumeController, StudentResumeController, AppServiceProvider (signed-files limiter), routes/api.php; frontend admin/student resumes pages, eligibilitygrid.tsx, jnfformpro.tsx, infformpro.tsx, formpreview.tsx, shared/index.ts; tests ResumeTest (+1)
- 2026-10-01 · M6.1 · migrations 2026_09_27_000012, 000013; app/Models/ApplicationRoundResult.php, ShortlistProposal.php
- 2026-10-01 · M6.2/6.3 · app/Services/PipelineService.php, app/Http/Controllers/AdminPipelineController.php, app/Mail/RoundResultMail.php + view
- 2026-10-01 · M6.4/6.5 · app/Services/StakeholderNotifier.php, app/Mail/PortalNoticeMail.php + view, app/Http/Controllers/CompanyPipelineController.php, AdminProposalController.php, routes/api.php
- 2026-10-01 · M6.6 · frontend components/admin/posting/{pipelinetab,waitlisttab}.jsx, components/admin/proposalslist.jsx, app/admin/proposals/page.jsx, app/admin/postings/[id]/page.jsx, app/company/postings/page.jsx, [id]/page.jsx, lib/companydownload.js, components/company/companyshell.tsx (nav), components/admin/adminshell.tsx (nav)
- 2026-10-01 · M6-TEST · tests/Feature/PipelineTest.php (7 tests)
- 2026-10-01 · M5-AUDIT · AdminFormReviewController (floatedFormGuard), JobPosting::acceptsApplications, EligibilityService, PostingPresenter, AdminPostingController, StudentApplicationController, eager-loads; frontend applypanel, postingcard, applications page, overviewtab, roundstab; tests (+4)
- 2026-10-01 · M7.1 · migrations 2026_09_27_000014, 000015; app/Models/Offer.php, PlacementBlock.php
- 2026-10-01 · M7.2 · app/Http/Controllers/AdminResultController.php, app/Services/BlockingPolicy.php, app/Mail/OfferMail.php + view; PipelineService::notifyResults public
- 2026-10-01 · M7.3/7.4 · app/Http/Controllers/AdminBlockController.php, AdminPipelineController::removeFromProcess, routes/api.php, StudentApplicationController::offerFor, StudentProfileController (active_blocks), AdminStudentController (offers)
- 2026-10-01 · M7.5 · frontend app/admin/postings/[id]/results/page.jsx, app/admin/postings/[id]/page.jsx, components/admin/posting/removefromprocess.jsx, components/admin/studentblockspanel.jsx, app/admin/placement-cycles/[id]/page.jsx, app/student/applications/page.jsx
- 2026-10-01 · M7-TEST · tests/Feature/ResultsAndBlocksTest.php (3 tests)
- 2026-10-01 · M6-AUDIT · app/Services/PipelineService.php, app/Jobs/SendRoundResultMails.php, AdminPipelineController (publish order/preview, attendance guard, waitlist removal), AdminProposalController, CompanyPipelineController, AdminPostingController (reopen/reorder/delete guards), AdminResultController (pending rows, queued regrets), PostingRound::proposals, routes; frontend roundstab, pipelinetab, waitlisttab, roundtrail, posting page; tests PipelineTest (+4)
- 2026-10-01 · M8.1 · migration 2026_09_27_000016; app/Models/CampusEvent.php; app/Http/Controllers/AdminEventController.php, EventFeedController.php; app/Mail/EventAnnouncedMail.php + view; app/Jobs/SendEventAnnouncements.php
- 2026-10-01 · M8.2 · app/Http/Controllers/CalendarController.php; routes/api.php
- 2026-10-01 · M8.3 · frontend components/shared/monthcalendar.jsx, eventcard.jsx; app/admin/events/page.jsx, app/admin/calendar/page.jsx, app/student/events/page.jsx, app/student/calendar/page.jsx, app/company/postings/page.jsx; components/forms/shared/index.ts (export stripHtml); adminshell nav
- 2026-10-01 · M8-TEST · tests/Feature/EventsCalendarTest.php (3 tests)
- 2026-10-01 · M9 · app/Services/ExportService.php; AdminPostingController::export, AdminPlacementCycleController::exportStudents, CompanyPipelineController::export; routes/api.php; frontend app/admin/postings/[id]/page.jsx, app/admin/placement-cycles/[id]/page.jsx; tests/Feature/ExportTest.php (3 tests)
- 2026-10-01 · M10.1 · app/Http/Controllers/AdminAnalyticsController.php, routes; frontend package.json/lock (@mui/x-charts 7.29.1), app/admin/analytics/page.jsx
- 2026-10-01 · M10.2 · app/Http/Controllers/StudentDashboardController.php; frontend app/student/page.jsx
- 2026-10-01 · M10.3 · app/Http/Controllers/AdminAuditLogController.php; frontend app/admin/audit-logs/page.jsx; adminshell nav
- 2026-10-01 · M10.4 · database/seeders/Phase2DemoSeeder.php, DatabaseSeeder.php (commented entry); tests/Feature/Phase2DemoSeederTest.php
- 2026-10-01 · M7M8-AUDIT · AdminResultController (order guard, pool guard, empty publish, renumber, regret estimate, currency), AdminPipelineController::removeFromProcess (waitlist), AdminPostingController (final stays last, out_of_process), CalendarController + StudentDashboardController (cancelled rounds), AdminBlockController (debar scope, flag clearing), AdminStudentController (audit subjects, offer labels); frontend results page, studentblockspanel, monthcalendar, eventcard, posting page, student detail page; tests (+4)
- 2026-10-01 · M10.5 · fresh MySQL `migrate:fresh --seed` + Phase2DemoSeeder; full gates; browser walk; adminshell nav threshold 1600px (D85); postingcard Applied chip (D86); handover section


## PHASE 2 COMPLETE — HANDOVER

**State at handover (2026-10-01):** all milestones M0–M10 are implemented, audited (plus a final cross-cutting security/route audit, D87–D88) (a background QA pass after every milestone; every confirmed finding fixed and logged as D-entries) and verified.

### Verification gates (last run)
- `php artisan migrate:fresh --seed` on MySQL `iitism_placement` ✔, then `php artisan db:seed --class=Phase2DemoSeeder` ✔ (0 mails/jobs queued).
- `php artisan test` → **116 passed** (735 assertions), in-memory SQLite.
- `npm run lint` → 0 errors (83 warnings, all pre-existing Phase 1 kinds + `<img>` in the shells); `npx tsc --noEmit` ✔; `npm run build` ✔.
- Browser (built-in browser, `next dev`): every milestone was exercised live at 375px and 1440px (see each milestone's Acceptance line). Final walk on the seeded data: admin analytics (both cycles), SDE drive pipeline + results console, admin JNF queue + JNF detail (Phase 1), company dashboard + JNF view (Phase 1) + Drives page on a phone, student applications + job board on a phone. Admin nav verified at 1536px and 1600px for a super admin (D85).
- **Playwright smoke (`npm run test:e2e`) did not run:** the installed `@playwright/test` expects browser build `chromium_headless_shell-1208`, which is not on this machine, and downloading it was not authorised overnight. Run `npx playwright install chromium` once, then `npm run test:e2e:smoke`. The three routes it covers (`/`, `/auth/login`, `/company/register`) return 200 (checked with curl).

### Things production needs
1. **Queue worker:** `php artisan queue:work` must run permanently (Supervisor/systemd) while Settings → "Student email delivery" is **Queued** (default). Every Phase 2 student email (invitations, new openings, round results, offers, events) goes through `MailDispatchService`; with no worker nothing is delivered. "Immediate" mode works without a worker but sends inside the admin's request (slow for big sends).
2. **PHP upload limits:** `upload_max_filesize ≥ 6M`, `post_max_size ≥ 8M` (spreadsheet imports accept 5 MB; the dev machine had 2M) (D51).
3. **Scheduler:** not required (no deadline reminders by design).
4. **New env vars:** none. Existing `FRONTEND_URL` is used for every email link; `APP_URL` must be the public API origin (signed resume links are built from it and the PDF proxy only accepts that origin).
5. **Storage:** resumes and student photos live on the private `local` disk (`storage/app/private/…`); back it up. `php artisan storage:link` is still needed for Phase 1 public files.
6. **Auth:** set `NEXTAUTH_URL`/`AUTH_URL` to the public frontend origin (D17).

### Demo data
`Phase2DemoSeeder` (commented in `DatabaseSeeder`) — 2 cycles, 120 students, 3 companies, 3 drives (one completed end-to-end with 8 offers, blocks, waitlist, addendum), events. Local-only logins (demo student emails are `<roll>@students.cdc-demo.test`, never deliverable): students `<roll no>` / `Student@2026` (e.g. `23JE0114` holds an offer), companies `hr@nimbus.demo` / `Company@2026` (also `hr@vertex.demo`, `hr@helix.demo`).

### Local QA artefacts to be aware of
- The dev DB contains `qa-admin@example.test` (normal admin, created for browser checks; delete it or run `migrate:fresh`).
- `CDC-main/.claude/launch.json` (outside `CDC/`) was added so the desktop app can start `next dev` for browser checks; it is tooling only — delete if unwanted.
- Nothing after M1 is committed (the owner commits).

### Deferred to Phase 3 (per spec "out of scope")
Job alerts/preferences, credit-score system, deadline reminder mails, season report PDF, per-cycle withdraw-prohibit toggle, S3, SSO, student self-signup, RSVP, and the **institute-DB academic sync hookup** — its entry point already exists: call `App\Services\StudentAcademicSyncService::apply($rows, $admin)`.

### Owner decisions worth a look (all recorded in PHASE2_DECISIONS.md)
- D23/D67: cycle `allowed_programmes` is descriptive; closing a cycle stops new applications.
- D41 → D94: invitation links now last 7 days (own `invites` broker); forgot-password links keep 60 minutes.
- D53: students set passwords on a new `/auth/student/set-password` page (Phase 1 reset page left untouched).
- D59: company logos still accept SVG (Phase 1); the PDF proxy no longer serves anything but PDFs, so this is no longer exploitable through the frontend — consider dropping SVG from logo uploads anyway.
- D68: PhD students are exempt from the graduating-batch rule (the form never asks a PhD batch).
- D70/D75/D84: pipeline rules (results only after applications close, rounds publish in order, re-add protocol, final round always last).
- D58: company JNF/INF detail pages (Phase 1) do not show the new 10th/12th cutoffs; the wizard's own preview does.
- 2026-10-01 · FINAL-AUDIT · ExportService (explicit-string headers + cells, R{n}/Q{n} columns, INR-only best pay), AdminAnalyticsController (enrolled-only placed, INR stats, filled days), AdminPlacementCycleController (unenroll guard), AdminAuditLogController (IST dates), migration 2026_09_27_000017 + AuditService/AuditLog (actor snapshot), PipelineService/CompanyPipelineController (no withdrawal leak), StudentDashboardController + CalendarController (live applications only), config/cors.php (Content-Disposition), Phase2DemoSeeder (idempotent, dated rounds); frontend analytics page, audit-log page, student detail audit tab, student dashboard, job board URL filters; tests (+2, seeder re-run)
- 2026-10-01 · BULK-MAIL · D89: MailDispatchService::sendBulk (BCC batches, MAIL_BULK_BATCH_SIZE), SendPostingFloatedMails/SendEventAnnouncements/SendRoundResultMails + PipelineService::notifyResults switched to it, PostingFloatedMail/EventAnnouncedMail un-personalised, RoundResultMail name nullable, config/mail.php + .env.example; tests updated (+1) — 116 passing
- 2026-10-01 · NO-WAITLIST-RANK · D90: migration 000012 (no `waitlist_rank`), ApplicationRoundResult, PipelineService (no renumber/suggestPromotions; waitlist result mails are BCC batches), AdminPipelineController (no reorder endpoint/rank input; previous-round waitlisted can be moved on without a warning), AdminProposalController, AdminResultController (`open_places`), CompanyPipelineController, PostingPresenter, ExportService, RoundResultMail + view, routes, Phase2DemoSeeder (no ranks; demo emails on reserved `.cdc-demo.test`); frontend waitlisttab (plain list + "Move to <next round>"), pipelinetab, results page, company drive page, proposalslist, roundtrail; tests rewritten — 116 passing

## QA PART 10 — FIX PHASE (2026-10-01)

Owner decisions on the QA report (D-1…D-12) → D91–D102. Fixed with regression tests in `backend/tests/Feature/QA/Fix*Test.php`: F-001, F-002 (+F-032), F-003, F-004 (blocking follows the student: complete vs internships-only, offer category at float, carry-forward on enrolment, backfill command), F-005 (IST everywhere), F-006, F-007, F-008, F-009 (Eligible tab), F-010, F-011 (7-day invites), F-012 (Phase 1 audit), F-014 (student-portal e2e spec), F-015 (email_logs sent/failed), F-016 (per-account login limiter), F-020 (suspended screen), F-024 (admin nav), D-2 (branch-hidden drives), D-10 (analytics). WONTFIX (owner): F-013, F-034, F-036. S4/S5 otherwise open. Details and the file → finding map: `QA_REPORT.md` §11.

- 2026-10-01 · QA-P10 · gates re-run: original suite 116/116; full suite 307 passed / 9 failed (all open S4/S5 reproductions); tsc ✔; lint 0 errors; build ✔; e2e 7/7 in the installed Chrome; permission matrix 5 violations (pre-existing F-040 only); Part 8 40/40; E2E-1 PASS live. Migrations `2026_10_01_000019` (student_invite_tokens) and `…000020` (email_logs.message_ref) applied to the dev DB.

### Added to "Things production needs"
7. **Once, if any offers exist from before D91:** `php artisan placement:extend-offer-blocks` (extends old offer blocks to every open cycle the student is enrolled in; idempotent, audited).
8. **Playwright:** `npx playwright install` once (the cached browser builds do not match Playwright 1.58), then `npm run test:e2e:student` with `E2E_*` QA credentials.
9. **Owner decision pending (N-1 in QA_REPORT §11.6):** logins reach Laravel from the Next.js server's IP, so the per-IP `api` bucket caps logins portal-wide at 60/min.

