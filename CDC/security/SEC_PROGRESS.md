# SECURITY AUDIT — progress / memory file

## CURRENT STATE / NEXT ACTION
- **State:** Parts 0–3 done. Static reviews returned (authz: no BOLA/mass-assignment found statically; injection: leads = Phase 1 CSV formula injection, callbackUrl open redirect, recruiter link Host header, SVG logo, policy-doc url, stripHtml double-decode, import formula evaluation/memory) — each must be proven live before reporting.
- **State:** Parts 0–11 done. Remaining Part 6 and Parts 7–10 decided by code/config review plus ordinary requests (owner direction 2026-10-01). Report: CDC/SECURITY_REPORT.md.
- **Next action:** STOP — wait for the owner's "approved, fix" before Part 12. No code changes. Owner said 2026-10-01: continue from Part 5; signed-link edge cases are out of scope.

## Rules of engagement — confirmed 2026-10-01 14:18 IST
- Target = working tree on top of commit `7bcff0a` (Phase 2 M2–M10 + QA Part 10 fixes are **uncommitted**; the audit covers the working tree as it runs locally).
- Frontend `http://localhost:3000` (Next 16 dev server, `.env.local`: `NEXTAUTH_URL=http://127.0.0.1:3000`, `NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api`).
- Backend `http://127.0.0.1:8000` (`php artisan serve`, pid 6248, child `php -S` pid 6272). **Effective mailer = `log`** (process env `MAIL_MAILER=log` on both pids, verified with `ps eww`). Note: `backend/.env` itself still says `MAIL_MAILER=smtp` with a real Gmail account — left untouched; every artisan/queue command in this audit is prefixed `MAIL_MAILER=log`; PHPUnit uses `MAIL_MAILER=array` (phpunit.xml).
- Database: local MySQL 9.6 at `127.0.0.1:3306`, db `iitism_placement` (tests: in-memory SQLite).
- Accounts used: only QA/demo accounts on `.qa.test` / `.cdc-demo.test` domains (see `CDC/qa/actors.md`). The seeded `admin@iitism.ac.in` is never logged into and never mailed (log mailer anyway).
- No request leaves the machine except `composer audit` / `npm audit` (explicitly allowed, advisory lookups). No scanner downloads (gitleaks/semgrep/ZAP/trivy/docker absent → manual checks).
- Secrets are shown redacted (first 2 + last 2 chars) everywhere.
- Parts 1–10: no application code changes; only `CDC/security/**`, `CDC/backend/tests/Feature/Security/**` and the report.

## PARTS
- [x] Part 0 rules of engagement
- [x] Part 1 recon & attack surface
- [x] Part 2 secrets, config, supply chain
- [x] Part 3 authentication
- [x] Part 4 sessions & tokens (signed-link edge cases dropped by owner direction)
- [x] Part 5 authorisation
- [x] Part 6 injection
- [x] Part 7 uploads & storage
- [x] Part 8 SSRF / CORS / redirects / headers
- [x] Part 9 privacy, logging, abuse
- [x] Part 10 deployment checklist
- [x] Part 11 report → stop for owner

## FINDINGS LOG (draft ids; final numbering in the report)
- D-01 · seeded super-admin `admin@iitism.ac.in` password == `ADMIN_PASSWORD` committed in `.env.example` (commit 237afaa), weak (11 chars, `pa…23`); verified with Hash::check (no login). AdminUserSeeder seeds from env.
- D-02 · Gmail app password committed in `.env.example` (238560d) == current `.env` MAIL_PASSWORD (`jv…es`) → live credential in history.
- D-03 · Old `APP_KEY` committed (238560d, `ba…w=`) — differs from current `.env` key (Info: only matters if any deployment used it).
- D-04 · Private `local` disk has `'serve' => true` → `GET`/`PUT /storage/{path}` routes (signed). Test in Part 7.
- D-05 · CORS always allows `http://127.0.0.1:3000` + `http://localhost:3000` with credentials (also in production).
- D-06 · No CSP in next.config.ts.
- D-07 · `NEXTAUTH_SECRET` committed in SETUP_GUIDE.md (238560d; removed from HEAD) == current `.env.local` value (`de…90`, 38 chars, word-based) → session cookies forgeable/decryptable by anyone with a clone.
- D-08 · Weak admin password also hard-coded in tracked `CompanySeeder.php:45` and in `PROJECT_CONTEXT.md:408` (owner doc). (My own QA_REPORT.md:46 and qa/results.md:22 had it in clear → redacted 2026-10-01 14:5x, logged here.)
- D-09 · composer audit: 41 advisories (laravel/framework v12.56.0: HIGH CRLF in email rule; MEDIUM temporary signed URL path confusion; symfony/mime v7.4.7 HIGH CRLF in Address; guzzle/psr7/commonmark mostly unreachable). evidence/composer_audit.txt
- D-10 · npm audit (frontend, prod deps): 12 (3 critical): next 16.2.1 (proxy/middleware bypass ×5, RCE in image optimizer AVIF/next-og/windows, SSRF, DoS) → fix next@16.3.8; next-auth 5.0.0-beta.30 (fail-open on config error — mitigated by D17 fail-closed checks; verify), quill 2.0.3 low XSS via HTML export (not used). Backend npm prod: 0 (dev toolchain 8). evidence/npm_audit_*.json
- D-11 · pdf.js worker loaded from unpkg at runtime, no SRI (pdfviewer.tsx:12).
- D-12 · Unused deps: smalot/pdfparser (composer), axios/date-fns/@mui/x-data-grid (npm; axios has a high advisory).
- D-13 · Committed artefacts: conclave/index.html (5 mobile numbers, 8 iitism.ac.in emails), log_filtered.txt (dev paths + SQL errors), test-results/.last-run.json. PROJECT_STATUS.md gone.
- D-14 · `.env.example`: APP_ENV=local, LOG_LEVEL=debug, FRONTEND_URLS includes LAN IP 172.22.78.215 → copied to prod = CORS origin + debug logging. next.config allowedDevOrigins has the same IP.
- D-15 · A3.1 login timing oracle: known account +242 ms (bcrypt) vs unknown, identical bodies, non-overlapping (evidence/A3_1_login_timing.txt; test A3_1 fails).
- D-16 · A3.2 forgot-password oracle: 2nd request within 60 s → 429 only for existing accounts; unknown roll answers in ~10 ms vs ~250 ms (evidence/A3_2_enumeration.txt; test A3_2 fails). 503 on mail transport failure also only for existing accounts.
- D-17 · A3.2/A7 still present: verification-link `unique` → "The email has already been registered." (code: CompanyAuthController:184,187); verification-status public. Live test blocked by `email:rfc,dns` (would need external DNS) → code evidence.
- D-18 · A3.3: per-account limiter works (429 from attempt 11) but locks out the real owner too (victim lockout for 1 min, renewable); spray across 50 accounts from one IP: 50×422, no throttle (only 60/min/IP). evidence/A3_3_bruteforce.txt
- D-19 · A3.4: no uncompromised()/context check — 'Password1', 'Welcome123', '<Name>1', '<roll>pass' accepted (test A3_4_common fails).
- D-20 · A3.9: recruiter verification link reusable until expiry (test fails); verification URL built from request Host header (CompanyAuthController.php:205, no trustHosts) → token theft → register a company under a victim's email (needs victim click).
- (pass) A3.5 reset token single-use, email-bound, 60-min expiry, revokes Sanctum tokens; link from FRONTEND_URL even with spoofed Host/X-Forwarded-Host. A3.6 invite single-use, resend invalidates old, mail has roll + links only. A3.7 suspended 403 / deleted admin 401 / orphan company user sees nothing. A3.8 token issued for any portal but role boundary holds. A3.10 normal admin cannot manage admins; is_super_admin cannot be smuggled.
- D-21 · T4.3 `/api/auth/session` returns the Sanctum bearer (`accessToken`) to page JS (A6, by design) → any XSS on :3000 = 7-day API token. Cookie `authjs.session-token`: HttpOnly, SameSite=Lax, no Secure on http (Auth.js adds Secure/__Secure- on https). evidence/T4_3_T4_4_session.txt
- D-22 · T4.5 NextAuth JWT session 30 days vs Sanctum 7 days → admin/company UI stays "signed in" with a dead token (student shell signs out on 401).
- D-23 · T4.8 resume signed links are 30-day bearer credentials; a link handed to a company keeps working after the student withdraws (SessionTokenTest fails). Retarget/extend/unsigned/expired → 403 (pass).
- (pass) T4.1 7-day expiry enforced; T4.2 logout revokes for all roles (all three shells call /auth/logout); T4.7 unlimited tokens (Info); no tokens in local/sessionStorage (register draft excludes passwords).
- D-24 · 5.1 AuthorizationMatrixTest (8 actors: guest, STU, STU_B, STU_SUSP, CO_A, CO_B, ADMIN, SUPER): 170 route/methods, 1,360 cells + 36 probes = 1,396 requests; 0 wrong-actor 2xx, 0 leaks. 6 rows = Phase 1 F-040: GET /admin/policy-documents/{id} → 500 (no show()), company JNF/INF PUT/PATCH on another company's form → 422 before the ownership 404 (existence oracle). evidence/authorization_matrix.md
- (pass) 5.2 live IDOR (evidence/P5_2_idor_live.txt): student A → B's resume file/update/delete, application update/withdraw, notification read → 404; student → admin routes 403; company B → A's posting/applicants/export/proposals/new proposal/JNF read/delete/duplicate → 404; `_method` spoofing → 404. Company A's own applicants = exactly Q10.2 fields (no contacts with share off, no DOB/category/pwd/gender/flags/offers). 
- (pass) 5.4/5.5 MassAssignmentAndLogicTest 6/6: profile/resume/apply/company form/proposal ignore smuggled role/is_super_admin/status/flags/cgpa/branch/result; other student's resume_id 422; no applicant counts to students; re-apply loop mails only the student. Static review: every request-fed write is whitelisted; Info: User/Application/Resume keep privileged columns in $fillable (latent).
- Info/Low from static authz review (to confirm in report): company JNF show returns raw statusHistories incl. admin draft NOTEs and admin ids; students get reviewed_by/decided_by admin ids; hr_email change without re-verification; any admin can change a student's login email (+ resend invite = takeover within admin trust); route-model 404 text differs from custom 404 text (existence oracle).
- Business logic covered by QA tests: L5.1 deadline (S3 T3_6b), L5.4 races (F-017, open S4), L5.6 drafts hidden from companies (S4 tests), L5.8 floated forms stay accepted (D66), L5.10 re-add admin-only + company notified.
- (pass) .env never committed; debug tooling (telescope/horizon/ignition/debugbar) absent; /.env /.git/HEAD /vendor 404; /storage/logs/laravel.log 403 (signed storage.local route).

## LOG
- 2026-10-01 14:18 · Part 0 done.
- 2026-10-01 14:40 · Part 1 done.
- 2026-10-01 15:05 · Part 2 done.
- 2026-10-01 15:40 · Part 3 done (tests/Feature/Security/AuthenticationTest.php: 8 pass / 4 fail = findings).
- 2026-10-01 15:55 · Paused during Part 4; owner to confirm how to continue.
- 2026-10-01 · Part 4 closed (SessionTokenTest 5 pass / 1 fail = D-23). Resuming at Part 5 per owner.
- 2026-10-01 · Part 5 done.
- 2026-10-01 · Paused early in Part 6; owner to confirm how to continue.
- 2026-10-01 · Parts 6–10 by code/config review + ordinary requests (headers, CORS preflight from allowed origin); SECURITY_REPORT.md written (Critical 1, High 2, Medium 10, Low 11, Info 13). Stopped for owner.
