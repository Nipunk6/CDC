# PHASE 2 PROGRESS
Spec: PHASE2_IMPLEMENTATION_SPEC.md (v1.0). Stack: Laravel12+MySQL / Next16+JS(.jsx new files only) / MUI.

## CURRENT STATE
- Working on: M1 complete → next is M2 task 2.1
- Last completed: M1 acceptance checks (all green — see CHANGELOG 2026-09-27 M1-ACC)
- Half-done: nothing
- Pending commands: none. MySQL `iitism_placement` is at `migrate:fresh --seed` state (admin user + policy docs + mail_mode only; no cycles/enrolments left behind).
- M0 is committed and pushed (origin/main `2ee8413`, authored by the CDC account). M1 is **uncommitted** — the owner commits manually.
- NEXT ACTION: Start M2.1 — fill in the C3 schema inside the existing stub migration `database/migrations/2026_09_21_162407_create_student_profiles_table.php` (all columns per spec C3 + index on programme/branch/graduating_batch) and complete the `StudentProfile` model (fillable, casts, `belongsTo User`, `hasMany` Resume/Application/Offer/PlacementBlock/CycleEnrollment/BranchChangeRequest as those arrive). Then `php artisan migrate:fresh --seed`. Removing the stub's emptiness also clears the three KNOWN TRANSIENTS below that depend on `student_profiles.roll_no`.

## BLOCKED / QUESTIONS FOR OWNER
- Q1 (lint) — CLOSED 2026-09-27: owner kept the D14 policy, approved deleting the six root `test-*.js` scratch scripts, asked for the Phase 1 lint errors to be fixed (20 → 4, see D30/D31), then decided not to refactor the last four `react-hooks/set-state-in-effect` sites: they are suppressed with a pointer to D33 and tracked there as a deferred hardening item. **`npm run lint` now exits 0 (0 errors, 82 warnings).**
- Q2 (`.env.example` mail password) — ANSWERED 2026-09-27: owner accepts it as is; no change.
- Note (not a question): the build failed at baseline on 57 Phase 1 TS errors, not the 2 the spec lists; fixed type-only per the spec's method (D13). `next start` also failed at baseline with Auth.js `UntrustedHost`; fixed with `trustHost: true` (D17).

## MILESTONE CHECKLIST
- [x] M0 Foundations
  - [x] M0.1 PHASE2_PROGRESS.md + PHASE2_DECISIONS.md + CLAUDE.md notices (frontend appended, backend created)
  - [x] M0.2 MySQL env (already `mysql`/`iitism_placement`; DB existed), `.env`+`.env.example` stray `a` removed + `COMPANY_RECRUITER_VERIFY_TTL_MINUTES=30` added, `storage:link`, `migrate:fresh --seed` OK
  - [x] M0.3a `POST /auth/admin/register` route + `AuthController@registerAdmin` deleted
  - [x] M0.3b `StoreJnfRequest`/`StoreInfRequest`: `status` ∈ {draft, submitted}, `admin_remarks` removed; stripped in store/update/autosave of both company controllers
  - [x] M0.3c `config/sanctum.php` expiration 7 days; adminshell + companyshell call `POST /auth/logout` (token revoke) before `signOut()`
  - [x] M0.3d `app/api/proxy-pdf/route.ts`: `auth()` required (401), origin must equal app origin or `NEXT_PUBLIC_API_URL` origin (else 400)
  - [x] M0.3e Company `DELETE /company/jnfs|infs/{id}`: 422 when floated (`Jnf::isFloated()` / `Inf::isFloated()`, table-guarded until M5)
  - [x] M0.4 `allowJs` (was already true), `phpoffice/phpspreadsheet` ^5 installed, `@mui/x-charts` installed
  - [x] M0.5 Migrations C1 (`2026_09_27_000001`), C16 (`…000002`), C17 (`…000003`); models `PortalSetting`, `AuditLog`; `SettingsService`, `AuditService`; `config/programmes.php` (8/52, generated from TSX); `App\Support\ProgrammeCatalogue`; `EnsureUserIsActive` aliased `active` and applied to every `auth:sanctum` group; `PortalSettingSeeder`; `User.is_active` fillable/cast + `studentProfile()` hasOne
  - [x] M0.6 `types/next-auth.d.ts` (role union ×3 + `rollNo`), `auth.ts` (`StudentOnlyError`, `roll_no` passthrough, `trustHost`), `proxy.ts` (`/student` gate + matcher + fail-closed session), `AuthController@login`/`forgotPassword` accept `email` OR `roll_no` + 403 suspended, login page student variant (Roll Number field, student feature copy)
  - [x] M0 Acceptance: `migrate:fresh --seed` on MySQL ✔ (`users.role` = enum('admin','company','student')); `php artisan test` 25/25 ✔; admin login unchanged (live curl 200 + token, dashboard 200, logout → token 401) ✔; `POST /api/auth/admin/register` → 404 ✔; company `status: accepted` → 422 ✔ (test); proxy-pdf no session → 401 ✔ (live `next start`); `/student` unauth → `/auth/login/student?callbackUrl=/student` ✔; `tsc --noEmit` 0 errors ✔; `npm run build` ✔; lint: 0 new errors (baseline 25 pre-existing, see Q1)
- [x] M1 Placement cycles
  - [x] M1.1 C2 migration (`2026_09_27_000004`) + `PlacementCycle` model + `AdminPlacementCycleController` (index with enrolled/postings/offers counts, store, show, update, close); `allowed_programmes` validated against `ProgrammeCatalogue`; every write audited (`cycle.create|update|close`); 8 routes under `['auth:sanctum','active','role:admin']`
  - [x] M1.2 C4 migration (`2026_09_27_000005`) + `CycleEnrollment` model + `POST .../enroll` (pasted `roll_nos` OR uploaded xlsx/csv → `{enrolled, already_enrolled, errors:[{row, roll_no, reason}]}`), `DELETE .../enroll/{studentProfile}`, `GET .../enrollments` (search + 50/page); `SpreadsheetImportService` added
  - [x] M1.3 `app/admin/placement-cycles/page.jsx` (card list, counts, Add-cycle dialog with programme/batch builder, close action) + `app/admin/placement-cycles/[id]/page.jsx` (Overview / Enrolled Students with bulk-enrol + error report + search + pagination / Postings placeholder); "Placement Cycles" added to adminshell nav
  - [x] M1 Acceptance: cycle created live ✔; non-catalogue programme → 422 ✔; pasted roll list → per-row error report (unknown + duplicate rows both reported) ✔; CSV upload reports real spreadsheet row numbers (2, 3 — header skipped) ✔; enrollments list returns `meta` ✔; update + close ✔, second close → 422 ✔; `audit_logs` holds cycle.create/update/close with user + ip ✔; company user → 403 ✔; Phase 1 admin dashboard + JNF queue still 200 ✔; `migrate:fresh --seed` ✔; `php artisan test` 40/40 ✔; `tsc` 0 errors ✔; `npm run build` ✔ (routes `/admin/placement-cycles`, `/admin/placement-cycles/[id]`); `npm run lint` exits 0 (0 errors; the last 4 suppressed per D33)
- [ ] M2 Students module
  - [ ] M2.1 C3 schema into the stub migration + full `StudentProfile` model + `User` relations
  - [ ] M2.2 `AdminStudentController` (store + invitation E1, bulkImport with `?dry_run=1`, import template, paginated index, show, update, `StudentAcademicSyncService`, suspend/reactivate)
  - [ ] M2.3 Student self endpoints (profile read, limited PATCH, photo upload)
  - [ ] M2.4 C15 branch-change requests (student submit/list, admin queue/approve/reject)
  - [ ] M2.5 Admin frontend (students list + import dialog with dry-run preview, student detail tabs, branch-changes queue)
  - [ ] M2.6 Student frontend (`lib/studentapi.js`, `studentshell.jsx`, `app/student/layout.jsx`, profile page, notifications page, dashboard placeholder)
  - [ ] M2 Acceptance
- [ ] M3 Resumes
- [ ] M4 Phase 1 form change (numeric backlogs + 10th/12th)
- [ ] M5 Floating & student job board
- [ ] M6 Pipeline
- [ ] M7 Results, offers, blocking
- [ ] M8 Events & calendar
- [ ] M9 Exports
- [ ] M10 Dashboards, seeder, audit UI, final QA

## KNOWN TRANSIENTS (close in the named milestone)
- Cycle enrolment cannot match anybody until M2.1 gives `student_profiles` a `roll_no` column: `AdminPlacementCycleController::studentDirectoryReady()` returns false, so every row lands in the error report (which is exactly what the M1 acceptance check exercises). Enrolment search filters are skipped for the same reason.
- `AdminPlacementCycleController::relatedCount()` returns 0 for `job_postings` (M5) and `offers` (M7) until those tables exist.
- Roll-number login/forgot-password query `student_profiles.roll_no`, which only exists after M2.1 replaces the C3 stub migration (D16). No student accounts can exist before M2, so unreachable in practice.
- `Jnf::isFloated()` / `Inf::isFloated()` use `Schema::hasTable('job_postings')` until M5 adds the real relation (D3).
- Login response does not yet eager-load `studentProfile` (so `session.user.rollNo` is null until M2 adds `->with('studentProfile')` in `AuthController@login`).

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
