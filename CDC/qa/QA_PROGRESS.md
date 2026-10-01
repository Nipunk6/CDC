# QA PROGRESS — Phase 2 acceptance audit
Prompt: "PHASE 2 — FULL QA AUDIT PROMPT v1.0 (2026-10-01)" (pasted by the owner in chat). Rules: audit first, no app-code changes in Parts 1–9; evidence for every PASS; report in `CDC/QA_REPORT.md`.

## CURRENT STATE
- Working on: DONE — Parts 1–9 complete. Report: `CDC/QA_REPORT.md` (verdict GO-WITH-CONDITIONS; S1 1 · S2 3 · S3 12 · S4 24 · S5 8; 12 owner decisions).
- Last completed: Part 9 report compiled from `qa/results.md` (row log) + evidence.
- Half-done: nothing. No application code changed. Nothing committed.
- Environment left: local MySQL holds QA data (cycles 3/4/5, 2,772 QA/scale students, postings 4–41, QA admins in `qa/actors.md`); `mail_mode` back to `queued`; baseline worktree removed; QA tests in `backend/tests/Feature/QA` (group `qa`, 20 intentionally failing = findings).
- NEXT ACTION: wait for the owner. Part 10 (fix phase) starts ONLY after the owner says "approved, fix": S1 → S2 → S3 → owner-decided items, one finding per commit (`QA F-00n: <title>`), each with a failing regression test first.

## SAFETY
- Backend server pid 6248 runs with `MAIL_MAILER=log` (verified with `ps eww`). Every artisan/queue command in this audit is prefixed `MAIL_MAILER=log`.
- Demo/QA students use `*.cdc-demo.test` / `*.qa.test` addresses (never deliverable).
- No commits.

## PARTS
- [x] Part 1 gates
- [x] Part 2 traceability
- [x] Part 3 R1–R20
- [x] Part 4 E2E
- [x] Part 5 code review (subagent + verification)
- [x] Part 6 permission matrix
- [x] Part 7 performance
- [x] Part 8 Phase 1 regression
- [x] Part 9 report
- [x] Part 10 fix phase (owner decisions 2026-10-01) — S1, S2, S3 and owner-decided items fixed; S4/S5 open (not approved)

## LOG
- 2026-10-01 · start
- 2026-10-01 · Parts 1–9 complete; QA_REPORT.md written; stopped for owner review (no fixes started)
- 2026-10-01 · owner decisions D-1…D-12 received → D91–D102; Part 10 started
- 2026-10-01 · fixed F-001, F-002 (+F-032), F-003, F-004 (owner rule + carry-forward + backfill), F-005, F-006, F-007, F-008, F-009, F-010, F-011, F-012, F-014, F-015, F-016, F-020, F-024, D-2, D-10; WONTFIX(owner) F-013, F-034, F-036
- 2026-10-01 · re-ran G3–G9, permission matrix, Part 8 (40/40 after fixing 3 script defects), E2E-1 (PASS, browser for float / Move / Results); QA_REPORT.md §11 written; nothing committed
