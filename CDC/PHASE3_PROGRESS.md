# PHASE 3 PROGRESS
Spec: PHASE3 MASTER PROMPT v1.0 (owner's notes 08-10-2026). Branch: `phase3`. Decisions: `CDC/PHASE3_DECISIONS.md`.
Gate: `php artisan test --exclude-group=qa-open` (from CDC/backend), `npm run build`, `npm run lint`, `npx tsc --noEmit` (from CDC/frontend). Restore `CDC/qa/evidence CDC/security/evidence` after test runs.

## CURRENT STATE
- Working on: P-1 Hardening
- Last completed: P-1.1 to P-1.8 (P3-D8 to P3-D15)
- Half-done: nothing
- Pending commands: none
- NEXT ACTION: P-1.9 SEC-004 (no svg company logos)

## BLOCKED / QUESTIONS FOR OWNER
- OD-16, OD-6, OD-12, OD-1 unanswered (P3-D6). Not needed for P-1 or P0.

## MILESTONE CHECKLIST
- [x] Housekeeping: 3 commits on main, branch phase3 (P3-D2)
- [x] Known open findings grouped `qa-open` (P3-D3)
- [ ] P-1 Hardening
  - [x] P-1.1 SEC-008 + QA N-1 signed forwarded IP, login 600/min per IP, per-account backoff
  - [x] P-1.2 QA daily mail-recipient cap (MailDispatchService, BCC counted, release to next day, settings used/remaining)
  - [x] P-1.3 SEC-010 per-recipient cooldown (recruiter verification link, alumni confirmation)
  - [x] P-1.4 SEC-005 safeCallbackUrl helper (both login pages)
  - [x] P-1.5 SEC-006 CSV formula escape in Phase 1 csvValue()
  - [x] P-1.6 SEC-009 baseline CSP (no unsafe-eval) + SEC-018 local pdf.js worker
  - [x] P-1.7 SEC-001 next ≥ 16.3.8, next-auth latest v5; remove axios, date-fns, @mui/x-data-grid, smalot/pdfparser
  - [x] P-1.8 SEC-011 composer update laravel/framework symfony/mime symfony/mailer
  - [ ] P-1.9 SEC-004 no svg logos
  - [ ] P-1.10 SEC-007 verification link from config; trustHosts() from env
  - [ ] P-1.11 CompanySeeder hard-coded password removed
  - [ ] P-1.12 Lows: SEC-014, SEC-015, SEC-016, SEC-019, SEC-022
  - [ ] P-1 close: tests, authorisation matrix, build/lint/tsc, npm audit, composer audit, QA E2E-1, SECURITY_REPORT §12 Re-test Results, stop and report
- [ ] P0 Foundations, login IP log, settings, scheduler
- [ ] P1 CKEditor 5 + HTML sanitisation (🛑 OD-16)
- [ ] P2 MIS data, pre-registration, secondary degrees (HTTP driver 🛑 OD-1)
- [ ] P3 Eligibility & offer rules
- [ ] P4 Notice board, archive, dashboard v2, walk-ins
- [ ] P5 Deadline reminders
- [ ] P6 Shortlist categories & attendance uploads
- [ ] P7 Juniors: events dashboard + experience survey
- [ ] P8 Mentors (🛑 OD-6)
- [ ] P9 Mail infrastructure: Zimbra pool, delivery log, tags, composer, company outreach
- [ ] P10 SCPT & SPOC + double-major analytics
- [ ] P11 UI polish + mobile
- [ ] P12 Alumni portal (🛑 OD-12)
- [ ] P13 Final QA, security regression, handover

## CHANGELOG
- 2026-10-09 · housekeeping commits 8336756, 3a9d4e2, d53ceb8 on main; branch phase3
- 2026-10-09 · 7b11a4b test: group known open findings
- 2026-10-09 · a2cca87 P-1.1 SEC-008 signed client IP, login per-IP budget and per-account backoff (P3-D8)
- 2026-10-09 · 94fde4d P-1.2 daily mail-recipient cap with next-day deferral (P3-D9)
- 2026-10-09 · 69d8808 P-1.3 SEC-010 per-recipient mail cooldown (P3-D10)
- 2026-10-09 · 7973558 P-1.4 SEC-005 safeCallbackUrl (P3-D11)
- 2026-10-09 · d54fb85 P-1.5 SEC-006 formula-safe CSV (P3-D12)
- 2026-10-09 · 97c9cf7 P-1.7 SEC-001 next 16.3.8, next-auth beta.32, unused packages removed (P3-D13)
- 2026-10-09 · afb252d P-1.8 SEC-011 Laravel 12.69.3, symfony/mime + mailer 7.4.19 (P3-D14)
- 2026-10-09 · P-1.6 SEC-009 baseline CSP + SEC-018 local pdf.js worker (P3-D15)
