# PHASE 2 PROGRESS
Spec: PHASE2_IMPLEMENTATION_SPEC.md (v1.0). Stack: Laravel12+MySQL / Next16+JS(.jsx new files only) / MUI.

## CURRENT STATE
- Working on: M0 complete → next is M1 task 1.1
- Last completed: M0 acceptance checks (all green — see CHANGELOG 2026-09-27 M0-ACC)
- Half-done: nothing
- Pending commands: none. (MySQL `iitism_placement` is at `migrate:fresh --seed` state as of M0; `storage:link` done; `composer require phpoffice/phpspreadsheet` + `npm i @mui/x-charts` installed.)
- Nothing has been committed — the owner commits manually (owner rule: never commit without an explicit ask). M0.3 "each its own commit" was therefore NOT done as separate commits; the changes are staged-able as one M0 set.
- NEXT ACTION: Start M1.1 — create migration `2026_09_27_000004_create_placement_cycles_table.php` (C2), `PlacementCycle` model, `AdminPlacementCycleController` (index/store/update/show/close, validate `allowed_programmes` via `App\Support\ProgrammeCatalogue`, audit via `App\Services\AuditService`), routes under the `['auth:sanctum','active','role:admin']` admin group in `routes/api.php`.

## BLOCKED / QUESTIONS FOR OWNER
- Q1 (lint baseline) — ANSWERED 2026-09-27: owner keeps the "no NEW lint errors in files Phase 2 creates or touches" policy (D14) and approved deleting the six root `test-*.js` scratch scripts (done, `git rm`, staged). Remaining pre-existing lint errors: 20, all in Phase 1 files Phase 2 does not edit.
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
- [ ] M1 Placement cycles
  - [ ] M1.1 C2 migration + `PlacementCycle` + `AdminPlacementCycleController` (index/store/update/show/close) + routes
  - [ ] M1.2 C4 migration + enrol endpoints (roll list or Excel/CSV, per-row report) + unenrol + enrolled list (search+pagination)
  - [ ] M1.3 Admin UI `app/admin/placement-cycles/page.jsx` + `[id]/page.jsx`; nav entry in adminshell
  - [ ] M1 Acceptance
- [ ] M2 Students module
- [ ] M3 Resumes
- [ ] M4 Phase 1 form change (numeric backlogs + 10th/12th)
- [ ] M5 Floating & student job board
- [ ] M6 Pipeline
- [ ] M7 Results, offers, blocking
- [ ] M8 Events & calendar
- [ ] M9 Exports
- [ ] M10 Dashboards, seeder, audit UI, final QA

## KNOWN TRANSIENTS (close in the named milestone)
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
