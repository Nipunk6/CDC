# PHASE 3 PROGRESS
Spec: PHASE3 MASTER PROMPT v1.0 (owner's notes 08-10-2026). Branch: `phase3`. Decisions: `CDC/PHASE3_DECISIONS.md`.
Gate: `php artisan test --exclude-group=qa-open` (from CDC/backend), `npm run build`, `npm run lint`, `npx tsc --noEmit` (from CDC/frontend). Restore `CDC/qa/evidence CDC/security/evidence` after test runs.

## CURRENT STATE
- Working on: P-1 Hardening
- Last completed: housekeeping (P3-D2), qa-open grouping (P3-D3)
- Half-done: nothing
- Pending commands: none
- NEXT ACTION: P-1.1 SEC-008 + QA N-1 (signed forwarded client IP)

## BLOCKED / QUESTIONS FOR OWNER
- OD-16, OD-6, OD-12, OD-1 unanswered (P3-D6). Not needed for P-1 or P0.

## MILESTONE CHECKLIST
- [x] Housekeeping: 3 commits on main, branch phase3 (P3-D2)
- [x] Known open findings grouped `qa-open` (P3-D3)
- [ ] P-1 Hardening
  - [ ] P-1.1 SEC-008 + QA N-1 signed forwarded IP, login 600/min per IP, per-account backoff
  - [ ] P-1.2 QA daily mail-recipient cap (MailDispatchService, BCC counted, release to next day, settings used/remaining)
  - [ ] P-1.3 SEC-010 per-recipient cooldown (recruiter verification link, alumni confirmation)
  - [ ] P-1.4 SEC-005 safeCallbackUrl helper (both login pages)
  - [ ] P-1.5 SEC-006 CSV formula escape in Phase 1 csvValue()
  - [ ] P-1.6 SEC-009 baseline CSP (no unsafe-eval) + SEC-018 local pdf.js worker
  - [ ] P-1.7 SEC-001 next ≥ 16.3.8, next-auth latest v5; remove axios, date-fns, @mui/x-data-grid, smalot/pdfparser
  - [ ] P-1.8 SEC-011 composer update laravel/framework symfony/mime symfony/mailer
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
