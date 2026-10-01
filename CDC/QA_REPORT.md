# Phase 2 QA Report — 2026-10-01

Auditor: Claude (QA role, per the owner's "PHASE 2 — FULL QA AUDIT PROMPT v1.0"). Scope: Parts 1–9. **No application code was changed during the audit**; only `CDC/qa/**`, `CDC/backend/tests/Feature/QA/**` and this report were written. Nothing was committed.
Working evidence: `CDC/qa/results.md` (row-by-row log), `CDC/qa/evidence/*`, `CDC/qa/screenshots/*`, QA tests in `CDC/backend/tests/Feature/QA/` (group `qa`).

---

## 1. Verdict

> **Update 2026-10-01 (Part 10 fix phase):** S1, all three S2 findings, the S3 findings and the owner-decided items are fixed and re-tested; S4/S5 findings stay open unless the owner approves them. See **§11. Re-test results**. The text below is the original audit verdict.

**GO-WITH-CONDITIONS.** The core of Phase 2 holds up under hostile testing. On permissions and isolation:
- 1,008 route × actor cells and 36 cross-tenant probes found **zero** wrong-actor successes.
- Students never see applicant counts.
- Company contact data never leaks.
- The eligibility engine matched a pre-written oracle for every actor, live on MySQL and in tests.
- Exports are formula-safe, and signed resume links can't be tampered with.

Every performance target passed comfortably at about 2,800 students and 6,000 applications. All Phase 1 flows still work.

The conditions before a live season:
- **F-001 (S1):** a real-looking Gmail SMTP app password is committed in the tracked `backend/.env.example`, and has been since the first commit. That file also ships a fixed `APP_KEY`, `APP_DEBUG=true` and a weak `ADMIN_PASSWORD`. Rotate the password and replace all four with placeholders.
- **F-002 (S2):** the waitlist **"Move to next round"** action (my own D90 change from this morning) records the student as having *cleared* that round. In the live run, the final Results console then listed **only** the moved waitlistee, pre-ticked for a Full-Time offer, while the two genuine finalists were absent. One click would have issued a wrong offer.
- **F-003 (S2):** when an admin approves a company's waitlist proposal, students left off it are immediately and silently published as "not selected": no regret mail, no notification, no audit row.
- **F-004 (S2, owner decision):** postings must match their cycle's type, and blocks are per cycle. So the matrix's "internships only" scope never applies in practice, and a student who **accepted a PPO** in the internship cycle can still apply to full-time drives (reproduced live).

Everything else is S3–S5 or awaits an owner decision.

**Counts (183 test rows):** PASS **147** · FAIL **5** · PARTIAL **21** · NOT-BUILT **0** · BLOCKED **1** · NEEDS-OWNER-DECISION **9**
**Findings by severity (48 consolidated findings, F-001…F-048):** S1 **1** · S2 **3** · S3 **12** · S4 **24** · S5 **8**
**Automated tests:** `php artisan test` → 262 passed, 20 failed, 1 incomplete (4,780 assertions). The original suite (`--exclude-group=qa`) passes 116 of 116. The 20 failures are deliberate QA tests that reproduce the findings below.

> **Source-of-truth gap:** `PHASE2_REQUIREMENTS_QUESTIONS.md` (the owner's answered questionnaire, ranked #1 authority) does not exist anywhere on disk, in git history or in the `_xfer` archives. Its answers were taken from spec Part B (which states it is the questionnaire's answers), the owner quotes inside the QA prompt, and the owner's chat instructions (D89 BCC mail, D90 unranked waitlist). Please supply the file if you want the "spec vs answer" checks re-run against your own words.

---

## 2. S1 / S2 findings

### F-001 [S1] Secrets and unsafe defaults in the tracked `backend/.env.example`
- Test: G15 · Requirement: QA G15 ("no real secrets"), spec M0.2
- Expected: no real secrets; placeholders only.
- Actual:
  - `backend/.env.example:57`: `MAIL_PASSWORD` has a non-empty value in Gmail app-password format, for `MAIL_USERNAME=test000mailer@gmail.com`. It has been in git history since `238560d`, the initial commit.
  - `:3`: a fixed `APP_KEY=base64:…`. A deployment that copies the example without running `key:generate` uses a public key, which lets anyone forge signed resume URLs (`resumes.signed`) and decrypt app data.
  - `:4`: `APP_DEBUG=true`. Verified live: with debug on, a 500 returns the full stack trace and file paths; with `APP_DEBUG=false` it returns only `{"message":"Server Error"}`.
  - `:73`: `ADMIN_PASSWORD=pa…23 (redacted)`, which is the bootstrap super admin's password.
- Evidence: `qa/evidence/g11_g16.txt`; `git log -S"test000mailer" -- CDC/backend/.env.example` → `238560d`.
- Reproduction: open the file; check `git log`.
- Suggested fix:
  - Revoke that Gmail app password in the Google account now.
  - Blank `MAIL_PASSWORD` and `APP_KEY`, set `APP_DEBUG=false`, and use a placeholder admin password in the example.
  - Purging git history is optional once the credential is revoked.
- Note: not a Phase 2 regression. It predates Phase 2, but the gate is in scope.

### F-002 [S2] Waitlist "Move to next round" marks the student as having cleared that round, and the Results console pre-ticks them for an offer
- Test: T4.4c (D90 behaviour), E2E-1, CR-01 · Requirement: Q4.4 + owner chat ("any one can be pushed … to interview")
- Expected: a moved waitlistee *takes part in* the next round and is decided there like everyone else.
- Actual:
  - `components/admin/posting/waitlisttab.jsx:69-77` writes `result:"selected"` into the next round.
  - In this data model `selected` in round R means "cleared R". `PipelineService::pool()` (:67-80) admits only previous-round `selected`, so "mark everyone else not selected" never touches the moved student.
  - Publishing that round announces "You cleared {round}" (`PipelineService.php:295`).
  - If the next round is the final one, `results/page.jsx:77` pre-ticks them for an offer.
- Evidence (live): `qa/screenshots/CR-01-live-waitlist-move-pre-ticked-offer.jpg`. On QA-JNF, after Group Discussion was published (0001 and 0004 selected, 0005 waitlisted), "Move to Technical Interview" for 0005 made the Results console show **only 26QA0005, pre-ticked, Full-Time ₹18,00,000, block = everything**. The real finalists 0001 and 0004 did not appear. The database showed `Technical Interview:26QA0005=selected(draft)`.
- Reproduction:
  1. Publish a non-final round with one student waitlisted.
  2. Waitlist tab → Move to the next round.
  3. If that round is final, open Results & Offers: the moved student is pre-ticked. Otherwise, publish that round with "mark everyone else": the moved student gets "You cleared …".
- Suggested fix: write `pending` (or a dedicated "promoted" state) in the next round, and make `pool()` include previous-round waitlistees who have a row there. Also re-check `rejectedEarlier()` at publish time (CR-13).

### F-003 [S2] Approving a company waitlist proposal silently publishes removals
- Test: T4.4a, CR-03 · Requirement: Q4.2/Q4.3 (only admin publishes; rejected applicants get a regret mail), M6.5 ("approve … still requires the separate publish")
- Expected: approval writes drafts. Anyone taken off a published waitlist is told, through a separate publish with a regret mail.
- Actual: `AdminProposalController.php:126-132` calls `PipelineService::removeFromWaitlist`, which sets `result=rejected, published_at=now()` immediately (`PipelineService.php:255-256`) and dispatches no mail. The student's trail flips to "not selected" with no E4 mail, no in-app notice and no `waitlist.remove` audit row. The admin's own removal path does mail (`AdminPipelineController.php:344-346`).
- Evidence: `tests/Feature/QA/S4PipelineTest::test_T4_4a_…` fails ("a regret mail must go out … 0 is identical to 1").
- Reproduction:
  1. Publish a waitlist {A, B}.
  2. The company proposes waitlist {A}.
  3. The admin approves.
  4. B is now published "rejected", with no mail and no audit row.
- Suggested fix: after commit, call `dispatchResultMails(..., false)` for the removed rows and write the audit row. Or keep removals as drafts until the admin publishes.

### F-004 [S2 · NEEDS-OWNER-DECISION] Blocking scopes cannot work across the cycle-type split
- Tests: T5.2a, T5.2b, T5.2f, E2E-2 · Requirement: B4 matrix + Q5.3 (blocks are per cycle) + M5.3 (posting type must match cycle type)
- Expected (B4):
  - `intern` blocks only internship postings.
  - `intern_performance_ppo` ("final-years inside an FT cycle") blocks only internships.
  - `intern_ppo` (PPO accepted) blocks everything.
- Actual:
  - `AdminPostingController.php:148-155` refuses an INF in a full-time cycle and a JNF in an internship cycle. Each cycle therefore holds one posting type, and the `internships_only` scope never has anything to act on.
  - An `intern` offer in an internship cycle blocks everything in that cycle.
  - `intern_performance_ppo` in an FT cycle blocks nothing that exists.
  - Live result: **26QA0015 accepted a PPO (`intern_ppo`) in the internship cycle and is still eligible for a full-time JNF in the FT cycle.**
- Evidence: `qa/evidence/e2e2_ppo.txt`; the S5 tests had to move postings between cycles with direct DB updates to exercise the scopes at all.
- Owner decision needed:
  - (a) allow INFs inside full-time cycles, which matches B4's "final-years inside an FT cycle" wording; or
  - (b) make PPO-accepted/full-time blocks span all open cycles of the same academic year; or
  - (c) accept the current behaviour and let the admin block manually.
- Note: the QA prompt's E2E-2 step ("a second INF and a JNF floated into a fulltime cycle") cannot be performed as written.

---

## 3. Decisions needed from the owner

| # | Topic | Reading A | Reading B | What the code does | Recommendation |
|---|---|---|---|---|---|
| D-1 | F-004 blocking across cycles | B4/Q5.2 matrix as written | Q5.3 per-cycle blocks + M5.3 type-matched cycles | Types can't mix; blocks are per cycle → `internships_only` moot; PPO-accepted student still eligible for FT | Pick (a) or (b) above before the season |
| D-2 | Visibility (T3.2) | Owner: *"if any student's branch is eligible but … not eligible because he has already accepted any offer or his cgpa is low then he should be able to at least see this … with the reason"* — implies other-branch students may not need to see it | Spec B2: "students see ALL postings floated in cycles they're enrolled in" | Shows all postings; 26QA0006 (Mechanical) sees QA-JNF with "Your branch is not eligible." | Keep (transparent) unless you prefer hiding other-branch drives |
| D-3 | Invitation link lifetime (T1.3b, D41) | 60 min, same as password reset | Longer life for invitations only | 60 min; `POST /admin/students/{id}/resend-invitation` exists + Forgot password by roll works | Separate broker with ~7 days for invitations (3,000 bulk-imported students won't all act within an hour) |
| D-4 | Phase 1 admin actions audit (TA.1) | "Every admin mutation audited" (closing note) | Phase 1 predates the rule | Phase 2: 40/40 audited. Phase 1: 0/17 (form status/notes/remarks/**form-data edits of floated forms**, manage-admins incl. hard delete, branches, policy docs, company PUT) | Add `AuditService` calls to those 17 routes |
| D-5 | Branch change with live applications (T1.6b) | Re-evaluate/flag existing applications | Leave them | New eligibility uses the new branch; existing applications stay `applied`, unflagged, editable | Show a warning on approval and flag affected live applications |
| D-6 | "Eligible – Applied / Not applied" grid (T4.8, CR-10) | Admin grid lists eligible students who did not apply | Applicants only | Applicants only; only an eligible *count* exists | Add an "eligible, not applied" list/export per posting |
| D-7 | Admin top bar (T11.4, D85) | Phase 1 nav row from 1200 px | Drawer below 1600 px (developer decision) | Nav row only ≥1600 px; 1024–1599 px show title + hamburger | Accept, or restore the row from 1200 px with shorter labels |
| D-8 | Deleting old resumes (T2.5, D50) | B5: allowed after the posting completes | C9: `resume_id restrictOnDelete` | Replace allowed; **delete** refused while any application references it | Accept (keeps history) or soft-delete |
| D-9 | Suspended student with an open session (T1.8, D44) | B7: 403 "Account suspended. Contact CDC." on every request | D44 revokes tokens → 401 "Unauthenticated." | 401; the student UI shows a bare "Unauthenticated." banner with no redirect | Keep tokens and let the middleware return the 403 message, or redirect to login with that message |
| D-10 | Analytics details (T9.1) | — | — | "Companies completed vs ongoing" counts **postings**; CTC stats include `ppo_offered` offers whose students aren't counted "placed"; overview has no cross-cycle totals | Decide each |
| D-11 | Naive deadline strings (T3.6b) | Treat "2027-01-10T18:00" as IST | Treat as UTC | UTC (23:30 IST) — UI always sends `Z`, so only API clients are affected | Interpret naive strings as `Asia/Kolkata` (and fix F-005) |
| D-12 | Round reorder after publish (CR-15) | B3: "add/remove/reorder rounds … at any time" | D75(h): freeze after publish (pools depend on order) | Reorder refused after any publish | Accept the freeze (safer) |

---

## 4. Full test table

Legend: Sev only for non-PASS. "Evidence" = test method / file / screenshot; full detail in `qa/results.md`.

### Part 1 — gates
| Test | Req | Result | Sev | Evidence | Notes |
|---|---|---|---|---|---|
| G1 | repo | PASS | — | 157 status entries; Phase 2 commits only M0/M1 | M2–M10, D89, D90 uncommitted (by design) |
| G2 | Q0.1 | PASS | — | `qa/evidence/g2_g4.txt` MySQL 9.6.0 `iitism_placement` | |
| G3 | M0.2 | PASS | — | migrate:fresh --seed exit 0 | |
| G4 | M10.4 | PASS | — | `qa/evidence/g4_counts.txt` | proposals/branch-changes/audit/notifications 0 → T12.2 |
| G5 | DoD | PASS | — | 116 passed (741 assertions) | |
| G6 | DoD | PASS | — | tsc exit 0 | |
| G7 | DoD/D33 | PASS | — | lint 0 errors / 83 warnings; only D33 + 2 Phase 1 suppressions | |
| G8 | DoD | PASS | — | build exit 0 | |
| G9 | M10.5 | FAIL | S3 | `qa/evidence/g9_e2e.txt` 18/18 "Executable doesn't exist" | F-014 |
| G10 | Q0.5 | PASS | — | `qa/evidence/g10_queue.txt` | F-015 |
| G11 | M0.2 | PASS | — | symlink present | |
| G12 | D36 | PARTIAL | S4 | tree == df7034b; HEAD still has x-charts in backend package.json | F-038 |
| G13 | M0.4 | PASS | — | frontend x-charts 7.29.1; backend none | |
| G14 | stack | PASS | — | MUI 6.5.0 / React 19.2.4 / Next 16.2.1 | |
| G15 | M0.2 | FAIL | S1 | `.env.example` | F-001 |
| G16 | M10.5 | PASS | — | PHASE2_PROGRESS.md:218 | |

### Section 0 — foundation
| Test | Req | Result | Sev | Evidence | Notes |
|---|---|---|---|---|---|
| T0.1 | Q0.1a | PASS | — | MySQL runs; only spec-mandated ENUM alter is driver-specific; SQLite suite green | |
| T0.2 | Q0.2a | PASS | — | no new .ts/.tsx (reviewer); `allowJs:true` | |
| T0.3 | Q0.3a | PASS | — | cycles created via API (201), audited `cycle.create`; close in `AdminPlacementCycleTest`; UI pages load | |
| T0.4a | Q0.4 | PASS | — | `S0FoundationTest::test_T0_4a_…` 404 | |
| T0.4b | Q0.4 | PASS | — | `test_T0_4b_*` all 422; autosave ignores status | `status:null` → 500 (Phase 1) F-040 |
| T0.4c | Q0.4 | PASS | — | `test_T0_4c_…` | |
| T0.4d | Q0.4 | PASS | — | 6 d → 200, 8 d → 401 (3 roles) | |
| T0.4e | Q0.4 | PASS | — | API test + live UI sign-out deleted token id 25 | |
| T0.4f | Q0.4 | PASS | — | no session 401; metadata/foreign/non-PDF 400; signed resume 200 | |
| T0.4g | M0.3e | PASS | — | `test_T0_4g_…` | |
| T0.5a | Q0.5 | PASS | — | persisted + audited | |
| T0.5b | Q0.5 | PASS | — | job queued; float HTTP 0.055 s live; email_logs rows | status stays `queued` F-015 |
| T0.5c | Q0.5 | PASS | — | `test_T0_5c_…` sent inline | |
| T0.5d | Q0.5 | PASS | — | Part 7 P1–P3 | |
| T0.6 | Q0.6 | PASS | — | private `resumes/{ROLL}/{slot}_{uuid}.pdf`, no public copy | |

### Section 1 — student accounts
| Test | Req | Result | Sev | Evidence | Notes |
|---|---|---|---|---|---|
| T1.1a | Q1.1 | PASS | — | `test_T1_1a_…` | |
| T1.1b | Q1.1 | PASS | — | CSV/BOM/xlsx tests + live 16-actor import (dry run wrote nothing) | |
| T1.1c | Q1.1 | PARTIAL | S3 | "06-05-04" stored as **2006-05-04**; pwd "maybe" → false | F-006, F-037 |
| T1.2 | Q1.2b | PARTIAL | S4 | roll login case-insensitive, identical failure msg; cross-portal attempts blocked but specific messages never shown | F-025 (Phase 1) |
| T1.3a | Q1.3b | PASS | — | live E1: username + set-password link, no password | |
| T1.3b | Q1.3b | NEEDS-OWNER-DECISION | S3 | 60-min links; resend exists + audited | F-011 / D-3 |
| T1.4a | Q1.4 | PARTIAL | S4 | student sees past cycles; admin detail lacks blocks history | F-021 |
| T1.4b | Q1.4 | PASS | — | `test_T1_4b_…` | |
| T1.5 | Q1.5 | PASS | — | audit before/after + E8 | |
| T1.6a | Q1.6 | PASS | — | 409 on 2nd pending; approve audited (admin B) + E8; reject needs remark | |
| T1.6b | Q1.6 | NEEDS-OWNER-DECISION | S3 | existing applications untouched | F-013 / D-5 |
| T1.6c | Q1.6 | PASS | — | service + audit; non-academic untouched | no E8 mail F-031 |
| T1.7 | Q1.7 | PASS | — | flag set (live 3 apps), banner on dashboard/detail, admin sees it, clears on approval (live 6→5) | |
| T1.8 | Q1.8 | PARTIAL | S4 | login 403 ✔, audited ✔, excluded from E2 ✔; open session → 401 | F-020 / D-9 |

### Section 2 — resumes
| Test | Req | Result | Sev | Evidence | Notes |
|---|---|---|---|---|---|
| T2.1 | Q2.1 | PASS | — | live via real PHP (1.9 MB ok, 2 MB+1 422, PNG-as-pdf 422, docx 422, `.PDF` ok) + test | |
| T2.1b | Q2.1 | PASS | — | slots 0/9/-1/"abc" → 422 | |
| T2.2 | Q2.2 | PASS | — | remark required, old file deleted, E7 both | |
| T2.3 | Q2.3 | PARTIAL | S4 | flag in overview/applicants/pipeline/export, **not on results console** | F-022 |
| T2.4 | Q2.4 | PASS | — | empty/200-char → 422 | |
| T2.5 | Q2.5a | NEEDS-OWNER-DECISION | S4 | lock ok, withdrawn doesn't lock; delete after completion refused | F-036 / D-8 |
| T2.6 | Q2.6 | PASS | — | paginated; inline preview (live); audit per decision | |
| T2.7 | — | PASS | — | guest 401; cross-student 404; company only via signed URLs; tamper/swap/expiry 403 | |

### Section 3 — visibility, eligibility, applying
| Test | Req | Result | Sev | Evidence | Notes |
|---|---|---|---|---|---|
| T3.1a | Q3.1a | PASS | — | live (invisible before float; dialog "7 of 15") + guards test | |
| T3.1b | — | PASS | — | snapshot keeps float-time cutoff | admin edit side-issue F-007 |
| T3.2 | Q3.2 | NEEDS-OWNER-DECISION | — | other-branch student sees posting with reason | D-2 |
| T3.3 | Q3.3 | PASS | — | live oracle 12/12 (`qa/evidence/t3_3_oracle_live.txt`) + 13 tests | |
| T3.3b | Q3.3 | PASS | — | 7.00 ok, 6.99 no; "7","7.0","7.00"," 7.0 " | |
| T3.4a | Q3.4 | PARTIAL | S3 | wizard/preview/CSV ✔; admin edit of per-branch caps silently dropped | F-007 |
| T3.4b | Q3.4 | PASS | — | legacy boolean semantics | |
| T3.4c | Q3.3 | PASS | — | 10th/12th enforced (live 0008, 0016) | |
| T3.5 | Q3.5b | PASS | — | 4 types live; validation; no company route | |
| T3.6 | Q3.6 | PASS | — | tests + live after-deadline 422 ×3 | |
| T3.6b | Q3.6 | PARTIAL | S3 | UI path exact at 18:00 IST live; API offset path +5h30 | F-005 |
| T3.6c | — | PARTIAL | S4 | live 3-way race clean (1×201, 2×409); forced race window → 500 | F-017 |
| T3.7 | Q3.7 | PASS | — | test + live E2E-3 | |
| T3.8 | Q3.8 | PASS | — | test + live raw JSON scan | |
| T3.9 | Q3.9 | PASS | — | not built | |

### Section 4 — pipeline
| Test | Req | Result | Sev | Evidence | Notes |
|---|---|---|---|---|---|
| T4.1a | Q4.1 | PASS | — | live 3 rounds, last final | |
| T4.1b | Q4.1 | PASS | — | add/remove/reorder; remove with results 422; audited | reorder after publish refused D-12 |
| T4.2a | Q4.2 | PASS | — | 15 admin endpoints 403 for company; E10 live to 4 admins | |
| T4.2b | Q4.2 | PARTIAL | S4 | unknown/withdrawn reported; duplicate not reported + counted twice (live) | F-018 |
| T4.2c | Q4.2 | PASS | — | live approve-as-draft; reject needs remark | |
| T4.3 | Q4.3 | PASS | — | live: 4 shortlisted + 2 regrets; drafts invisible in raw JSON | |
| T4.4a | Q4.4 | FAIL | S2 | `S4PipelineTest::test_T4_4a_…` | F-003 |
| T4.4b | Q4.4/D90 | PASS | — | owner-approved (chat): no ranks anywhere, reorder route gone | |
| T4.4c | Q4.4/D90 | FAIL | S2 | live CR-01 repro | F-002 |
| T4.5 | Q4.5 | PASS | — | test + live E2E-4 (confirm required, company notified, audited) | re-add ignores offers/blocks F-035 |
| T4.5b | Q4.5 | PASS | — | addendum flagged, notified, no duplicate regrets | same-second re-publish re-mails F-008 |
| T4.6 | Q4.6 | PASS | — | live attendance + 403 for others | editable after publish F-033 |
| T4.7 | Q4.7 | PASS | — | live: drafts show `result:null, published:false` | |
| T4.8 | req 20 | PARTIAL | S3 | no eligible-not-applied students | F-009 / D-6 |

### Section 5 — results, offers, blocking
| Test | Req | Result | Sev | Evidence | Notes |
|---|---|---|---|---|---|
| T5.1 | Q5.1 | PASS | — | live JNF→Full-Time/all, INF→intern/internships_only | |
| T5.2a | Q5.2 | NEEDS-OWNER-DECISION | S2 | scope logic passes only with DB-moved postings | F-004 |
| T5.2b | Q5.2 | NEEDS-OWNER-DECISION | S2 | blocks all in its cycle; does not block FT cycle (live 0015) | F-004 |
| T5.2c | Q5.2 | PASS | — | no block; labels "PPO offered (not accepted)" vs "PPO accepted" | |
| T5.2d | Q5.2 | PASS | — | test + live (0001 blocked for FT JNF) | |
| T5.2e | Q5.2 | PASS | — | exists, blocks all | |
| T5.2f | Q5.2 | NEEDS-OWNER-DECISION | S2 | only observable with DB-moved postings | F-004 |
| T5.2g | Q5.2 | PASS | — | no decline route | |
| T5.2h | Q5.2 | PASS | — | row kept inactive, audited, eligible again | |
| T5.2i | Q5.6 | PASS | — | scope override honoured + audited | |
| T5.3 | Q5.3 | PASS | — | test + live (0001 blocked in cycle 3, eligible in cycle 4) | |
| T5.4 | Q5.4 | PASS | — | M.Tech CTC via name normalisation; live ₹18,00,000 / ₹60,000 prefilled | |
| T5.5 | Q5.5 | PASS | — | not built | |
| T5.6 | Q3.3 | PASS | — | live debar (scope forced `all`), reason shown, unblockable | |
| T5.7 | Q5.1 | PASS | — | live side effects; atomic rollback test | |
| T5.8 | — | PARTIAL | S4 | sequential double publish clean; concurrent loser → 500 (no duplicates) | F-017 |

### Section 6 — notifications & email
| Test | Req | Result | Sev | Evidence | Notes |
|---|---|---|---|---|---|
| T6.1 | E1 | PASS | — | test + live | |
| T6.2 | E2/Q6.1 | PASS | — | live = exact eligible set (7) | |
| T6.3 | E3 | PASS | — | re-sent on re-apply (recorded) | |
| T6.4 | E4 | PASS | — | test + live | |
| T6.5 | E5 | PASS | — | test + live | |
| T6.6 | E6 | PASS | — | all/branches/applicants | |
| T6.7 | E7 | PASS | — | remark included | |
| T6.8 | E8 | PASS | — | profile + branch decisions | |
| T6.9 | E9 | PASS | — | live to hr@beta with replacement text | |
| T6.10 | E10 | PASS | — | live admins on submit; company on decision | |
| T6.11 | Q6.1 | PASS | — | no reminders, no schedule | |
| T6.12 | Q6.2 | PASS | — | never personal_email (queued + sync) | |
| T6.13 | B6 | FAIL | S5 | E1 has no in-app row | F-041 |
| T6.14 | — | PASS (noted) | — | Phase 2 titles land in "Admin Actions" tab | F-046 |
| T6.15 | — | PASS | — | frontend_url links, no localhost | |

### Sections 7–10
| Test | Req | Result | Sev | Evidence | Notes |
|---|---|---|---|---|---|
| T7.1 | Q7.1 | PASS | — | minimal + full + edit | offset `starts_at` F-005 |
| T7.2 | Q7 | PASS | — | audience counts | `all` includes students in no cycle |
| T7.3 | Q7.2 | PASS | — | no RSVP; drafts invisible | |
| T7.4 | — | PASS | — | own company only | |
| T8.1a | Q8.1 | PASS | — | links +30 d, logged-out PDF, tamper 403, day 31 403; live 500-row export | |
| T8.1b | — | PASS | — | all columns; `= + - @` stored as text | |
| T8.2a | Q8.2 | PASS | — | own pre-deadline ok; foreign 404 | |
| T8.2b | Q10.2 | PASS | — | exact field allow-list; contact only with toggle | |
| T8.3 | Q8.3 | PARTIAL | S4 | only additive columns, **but inserted mid-row** (21 JNF / 10 INF columns shift) | F-019 |
| T8.4 | — | PASS | — | 207 students across chunk boundary | |
| T9.1 | Q9.1 | PASS | — | all blocks present | D-10 |
| T9.1b | Q9.1 | PASS | — | independent computation matches (even-count median, 0-offer cycle) | |
| T9.1c | — | PASS | — | Phase 1 controller byte-identical | |
| T9.2 | Q9.2 | PASS | — | denominator = all enrolments (incl. suspended) | |
| T9.3a | Q9.3 | PASS | — | test + live dashboard | |
| T9.3b | Q9.3 | PASS | — | nudge rules | |
| T9.3c | Q9.3 | PASS | — | IST month bucketing; relevance | |
| T9.4 | Q9.4 | PASS | — | not built | |
| T10.1 | Q10.1 | PASS | — | own floated only (+ live UI) | |
| T10.2 | — | PASS | — | IDOR sweep + matrix: all 404 | |
| T10.3 | — | PASS | — | no drafts (+ live raw JSON) | |
| T10.4 | Q10.2 | PASS | — | = T8.2b; live UI no phone | |

### Section 11 — UI / UX
| Test | Result | Sev | Evidence | Notes |
|---|---|---|---|---|
| T11.1 | PASS | — | 9 student pages + login | |
| T11.2 | PASS | — | permanent/temporary drawer; badge; logout revokes token | |
| T11.3 | PARTIAL | S4 | 390/768/1440 clean on 26 pages; `/student/postings` at 1024 overflows to 1177 px | F-023 |
| T11.4 | NEEDS-OWNER-DECISION | S4 | nav row only ≥1600 px | F-024 / D-7 |
| T11.5 | PASS | — | no justify | |
| T11.6 | PARTIAL | S5 | 11 hard-coded hex colours in Phase 2 JSX | F-044 |
| T11.7 | PASS | — | preview reused; no contacts in DOM/payload | |
| T11.8 | PARTIAL | S4 | empty states and error Alerts render (e.g. dead token → Alert, no crash); throttled-network skeletons and backend-down states not exercised | |
| T11.9 | PASS | — | fresh tab: 0 console errors on all checked pages | |
| T11.10 | PARTIAL | S5 | logical tab order, labelled inputs; icon button without accessible name | F-045 |

### Section 12, admin-is-god, E2E, security, performance, regression
| Test | Result | Sev | Evidence | Notes |
|---|---|---|---|---|
| T12.1 | PASS | — | Phase2DemoSeederTest + G4 counts | |
| T12.2 | PARTIAL | S5 | proposals/branch changes/audit/notifications screens empty after seeding | F-047 |
| T12.3 | PASS | — | idempotent; no mail | |
| TA.1 | NEEDS-OWNER-DECISION | S3 | Phase 2 40/40 audited; Phase 1 0/17 | F-012 / D-4 |
| TA.2 | PASS | — | test + live audit UI (QA Admin A/B) | |
| TA.3 | PASS | — | append-only | |
| TA.4 | PASS | — | admin can do everything directly | |
| TA.5 | PASS | — | permission matrix | |
| E2E-1 | PARTIAL | S2 | full season run (browser for key steps); CR-01 reproduced | F-002 |
| E2E-2 | PARTIAL | S2 | PPO variants; cross-cycle gap | F-004 |
| E2E-3 | PASS | — | placed-elsewhere cascade + replacement | |
| E2E-4 | PASS | — | re-add protocol + audit story | |
| E2E-5 | PASS | — | 390×844 student journey (apply via API) | |
| SEC-1 matrix | PASS | — | 0 wrong-actor 2xx | |
| SEC-2 rate limit / brute force | PARTIAL | S3 | 60/min per user/IP; no per-account login throttle | F-016 |
| SEC-3 upload traversal / spoofed MIME | PASS | — | generated names; MIME sniffed | |
| SEC-4 formula injection | PASS | — | text cells | |
| SEC-5 signed URL scope | PASS | — | id swap 403 | |
| SEC-6 CORS | PASS | — | foreign origin not allowed | |
| SEC-7 error leakage (prod config) | PASS | — | APP_DEBUG=false → generic | example ships true (F-001) |
| P1–P9 | PASS ×9 | — | see §7 | |
| R8-1 … R8-8 | PASS ×8 | — | see §8 | |
| R8-baseline | BLOCKED | — | authenticated baseline pages can't load data (CORS :3001) | public pages compared |

---

## 5. Original 20 requirements — traceability

| R# | Requirement (condensed) | Status | Failing / open tests |
|---|---|---|---|
| R1 | Admin creates student accounts | PARTIAL | T1.1c (DOB 2-digit year misread) |
| R2 | Student gets username + set-password invite | PARTIAL | T1.3b (60-min links — owner decision) |
| R3 | Academic fields admin/system controlled | PASS | — |
| R4 | Up to 8 labelled resumes | PASS | — |
| R5 | Admin verifies resumes; pending allowed but flagged | PARTIAL | T2.3 (flag missing on results console) |
| R6 | Approved JNF/INF visible after float | PASS (D-2 open) | T3.2 decision |
| R7 | Eligible students emailed on float | PASS | — |
| R8 | Withdraw/edit before deadline | PARTIAL | T3.6b API offset path (UI path PASS) |
| R9 | Result announcement marks block/no block | **FAIL** | T5.2a/b/f (F-004), T4.4c (F-002), T5.8 race |
| R10/R11 | CDC rules (matrix + admin control) | PARTIAL | F-004 |
| R12 | Company shortlists via portal, admin posts | PASS | — |
| R13 | Waitlist | **FAIL** | T4.4a (F-003), T4.4c (F-002) |
| R14 | Process to add candidates after announcement | PASS | (T4.5b same-second re-publish S3) |
| R15 | Excel download with profiles + resumes | PARTIAL | T8.3 (Phase 1 CSV column shift) |
| R16 | All notifications also by email | PASS | (T6.13 E1 in-app missing — S5) |
| R17 | Admin dashboard stats | PASS | (D-10 decisions) |
| R18 | Student dashboard with history + status | PASS | — |
| R19 | Events + notification | PASS | — |
| R20 | Per-student per-company stage tracking | PARTIAL | T4.8 (eligible-not-applied) |

---

## 6. Permission matrix (Part 6)

`tests/Feature/QA/PermissionMatrixTest.php` → `qa/evidence/permission_matrix.md`:
- **Coverage:** 168 route/method pairs × 6 actors (guest, student, suspended student, company A, company B, admin) = **1,008 cells**, plus **36 cross-tenant probes** (student → other student's resume/application/notification; company B → company A's posting, round, proposal, export, forms). Nothing was skipped.
- **Result: no wrong actor ever received a 2xx.** All 36 cross-tenant probes returned 404/403, and a leak scan of 28 successful responses was clean.
- **Five violations, all pre-existing in Phase 1 (F-040):**
  - `GET /admin/policy-documents/{id}` returns 500, because the controller has no `show()`.
  - `PUT`/`PATCH /company/{jnfs,infs}/{id}` from another company with an invalid body returns 422 instead of 404 (an existence oracle). With a valid body it correctly returns 404.
- **Rate limits** (`qa/evidence/rate_limits.md`):
  - API: 60 requests per minute per user; the 61st gets 429 with `Retry-After: 60`.
  - Login: 60 per minute per IP. A spoofed `X-Forwarded-For` header doesn't get around it, but there is no per-account throttle (F-016).
  - Signed resume links: 600 per minute per IP.
- **Suspended users** get 403 everywhere, including logout.

## 7. Performance (Part 7) — live MySQL, fixture `qa/scale_3000.xlsx` + `qa/seed_scale.php`

| ID | Measure | Result | Target |
|---|---|---|---|
| P1 | 3,000-row xlsx import | dry run 5.16 s · real 18.13 s · no memory/time errors (2,756 valid; 244 correctly rejected) | dry < 30 s ✔ |
| P2 | Float to 2,756 eligible (queued) | 0.055 s HTTP | < 3 s ✔ |
| P3 | Same in sync mode | 1.22 s; 2,756 `sent` logs (≈28 BCC messages) | record ✔ |
| P4 | `/student/postings` (32 postings) | 33–38 ms, 20 queries (3-posting student: 25) | < 500 ms, constant ✔ |
| P5 | Admin students p1 / p58 / search | 17 / 35 / 21 ms (12 queries) | < 500 ms ✔ |
| P6 | Pipeline 500 applicants × 4 rounds | API 83–85 ms, 31 queries (6 applicants: 30); UI render ≈0.5 s | < 2 s ✔ |
| P7 | 500-row applicant export | 0.54 s, valid workbook, signed link column | < 15 s ✔ |
| P8 | Analytics, 2,756-student cycle | 143–146 ms, 14 queries | < 2 s ✔ |
| P9 | Bulk enrol 2,756 rolls | 1.10 s | < 10 s ✔ |
| (worker) | 2,756 invitations + float jobs | 677 s for 5,792 job/mail completions, 0 failed | — |

## 8. Phase 1 regression (Part 8)

`qa/phase1_regression.sh` → `qa/evidence/part8_phase1_api.txt`. Every Phase 1 flow passes:
- company registration with email verification and logo upload;
- company login, dashboard, profile update and logo replacement;
- JNF duplicate, delete draft, and edit-access request (including the 409 on a second request);
- every admin review transition: mark/unmark draft, notes, granting edit access with remarks, editing the latest remark, resubmission, admin form-data edit with its email to the company, accept, reject (remarks required), CSV download;
- admin companies, branch manager, policy documents and manage-admins (403 for a normal admin, 200 for the super admin);
- alumni outreach list and form, notifications, company password reset.

In the browser:
- I ran the full JNF wizard: batch dialog, autosave, the policy-PDF read gate, preview and submit. The INF wizard renders correctly.
- I served a worktree at `df7034b` on :3001. The landing, recruiter-login, alumni and registration pages have identical text and height; the only difference is the approved justify → start change.
- On the admin dashboard the only difference is the nav: drawer versus row (D-7).

Two baseline limitations: authenticated pages there couldn't load data because CORS only allows :3000, and the worktree has since been removed.

## 9. Code review findings (Part 5 — independent subagent, each verified by me)

| ID | Verdict | Sev | Summary (see `qa/results.md` for file:line) |
|---|---|---|---|
| CR-01 | CONFIRMED LIVE | S2 | waitlist move = "cleared next round" → F-002 |
| CR-02 | CONFIRMED | S4 | approved→rejected resume doesn't re-flag applications → F-026 |
| CR-03 | CONFIRMED | S2 | waitlist proposal approval publishes silently → F-003 |
| CR-04 | CONFIRMED | S3 | replacement invite dead-ends on completed drives → F-010 |
| CR-05 | CONFIRMED | S4 | final publish can offer a published-rejected row (API) → F-027 |
| CR-06 | CONFIRMED | S4 | unblock not transactional; FT flags may stay → F-028 |
| CR-07 | CONFIRMED (code) | S4 | double event publish race → F-029 |
| CR-08 | CONFIRMED (test) | S4 | double-submit/publish race → 500 → F-017 |
| CR-09 | CONFIRMED | S4 | no way to suspend an enrolment → F-030 |
| CR-10 | NEEDS-OWNER-DECISION | S4 | no eligible-students list → D-6 |
| CR-11 | CONFIRMED | S4 | academic sync sends no E8 email → F-031 |
| CR-12 | CONFIRMED (measured OK) | S4 | N+1 on dashboard/events (37 queries; fast at scale) |
| CR-13 | CONFIRMED (code) | S4 | publish doesn't re-check rejected-earlier → F-032 |
| CR-14 | CONFIRMED | S4 | attendance editable on published rows → F-033 |
| CR-15 | CONFIRMED | S4 | reorder after publish refused vs B3 → D-12 |
| CR-16 | PLAUSIBLE | S5 | deadline extend can reopen mid-round → F-048 |
| CR-17 | CONFIRMED LIVE | S3 | offset datetimes stored wall-clock → F-005 |
| CR-18 | CONFIRMED | S5 | signed link serves the slot's current file → F-042 |
| CR-19 | CONFIRMED | S5 | dead/duplicate code → F-048 |
| CR-20 | CONFIRMED | S5 | redundant index on job_postings → F-048 |
| CR-21 | CONFIRMED | S5 | silent 5,000-row import cap; label rename → misleading 409 → F-048 |

Clean areas the review and I checked:
- There is one eligibility implementation, and `check()` and the SQL audience agree.
- `BlockingPolicy` matches the B4 matrix.
- Transactions are in place everywhere except unblock.
- No mass-assignment holes.
- Ownership is checked in every student and company method.
- No Company or contact data leaks to students.
- Mail goes only to institute emails, with no reminder mails.
- Uploads go to the private disk; signed routes are protected.
- Inputs are validated.
- No new TypeScript files, no `dangerouslySetInnerHTML`, no TODO comments left behind.
- Migrations: only the approved stub was edited.

### Consolidated finding index
| ID | Sev | Title |
|---|---|---|
| F-001 | S1 | Secrets/unsafe defaults in tracked `.env.example` |
| F-002 | S2 | Waitlist "Move" = cleared next round; offer pre-ticked |
| F-003 | S2 | Waitlist proposal approval publishes removals silently |
| F-004 | S2 | Cycle-type split makes block scopes moot (owner decision) |
| F-005 | S3 | Offset datetimes stored as wall-clock (+5h30) on API path |
| F-006 | S3 | Import misreads two-digit-year dates of birth |
| F-007 | S3 | Admin per-branch eligibility edits (CGPA/backlogs/caps) silently dropped |
| F-008 | S3 | Same-second re-publish re-mails the whole round |
| F-009 | S3 | No eligible-but-not-applied view (req 20) |
| F-010 | S3 | Replacement invitation impossible on completed drives |
| F-011 | S3 | 60-minute invitation links (owner decision) |
| F-012 | S3 | Phase 1 admin mutations not audited (owner decision) |
| F-013 | S3 | Branch change ignores existing applications (owner decision) |
| F-014 | S3 | No Phase 2 E2E specs; Playwright browsers absent |
| F-015 | S3 | Queued `email_logs` never leave `queued` |
| F-016 | S3 | No per-account login throttling |
| F-017 | S4 | Concurrent double apply/publish → 500 |
| F-018 | S4 | Results upload: duplicates counted twice, not reported |
| F-019 | S4 | Phase 1 CSV: new columns inserted mid-row |
| F-020 | S4 | Suspended open session → 401 + bare "Unauthenticated." (owner decision) |
| F-021 | S4 | Admin student detail lacks blocks history |
| F-022 | S4 | Unverified flag missing on results console |
| F-023 | S4 | `/student/postings` overflows at 1024 px |
| F-024 | S4 | Admin nav hidden below 1600 px (owner decision) |
| F-025 | S4 | Cross-portal login messages never shown (Phase 1) |
| F-026 | S4 | Rejecting an approved resume doesn't re-flag |
| F-027 | S4 | Final publish bypasses Re-add (API) |
| F-028 | S4 | Unblock not atomic; FT flags may stay |
| F-029 | S4 | Event publish race (double E6) |
| F-030 | S4 | Enrolment cannot be suspended |
| F-031 | S4 | Academic sync sends no E8 email |
| F-032 | S4 | Publish doesn't re-check rejected-earlier |
| F-033 | S4 | Attendance editable on published rows |
| F-034 | S4 | Rounds can't be reordered after publish (owner decision) |
| F-035 | S4 | Re-add ignores existing offers/blocks |
| F-036 | S4 | Resume delete after completion refused (owner decision) |
| F-037 | S4 | Unknown `pwd` values silently become "no" |
| F-038 | S4 | Backend package pollution still in committed HEAD |
| F-039 | S4 | EligibilityService block cache stale under long-lived workers |
| F-040 | S4 | Phase 1 pre-existing: policy-doc show 500; cross-tenant 422 oracle; `status:null` 500 |
| F-041 | S5 | E1 has no in-app notification |
| F-042 | S5 | Signed link serves the slot's current file |
| F-043 | S5 | Copy: pool count in publish dialog; "Totalbacklogis"; "accepted a PPO" on ppo_offered blocks |
| F-044 | S5 | Hard-coded hex colours in Phase 2 JSX |
| F-045 | S5 | Password toggle without accessible name (Phase 1 login) |
| F-046 | S5 | Phase 2 notification titles land in "Admin Actions" tab |
| F-047 | S5 | Seeder leaves proposals/branch-changes/audit/notifications screens empty |
| F-048 | S5 | Hygiene: CR-16/19/20/21, orphan tokens on cross-portal login, orphan resume files after `migrate:fresh`, no AuditLog immutability guard, 404-vs-403 resume id, login timing side-channel, `<img>` lint warning |

---

## 10. What was NOT tested and why

- **The owner's questionnaire file.** It is missing (see §1), so I used spec Part B and the owner's quotes in the prompt.
- **Playwright runs (G9).** The browser binaries aren't installed, and the audit rules forbid installing them. Browser checks were done in the in-app browser instead.
- **Real SMTP delivery.** Everything ran with `MAIL_MAILER=log` on purpose, since `.env` holds real Gmail credentials. Recipients were verified through `email_logs`, the mail log file and Laravel's mail fakes. The log transport hides Bcc headers, so BCC recipient lists were checked in the tests.
- **INF wizard full submit in the UI.** Only rendering was checked. The QA INFs were created through the company API using the same `form_data` shape the wizard produces.
- **The baseline side-by-side for authenticated pages.** Backend CORS blocked the :3001 origin; the public pages were compared.
- **Loading skeletons under a throttled network, and pages with the backend stopped (T11.8).** I didn't stop the shared backend mid-audit.
- **Simultaneous browser sessions for several actors.** Roles were switched in one tab through NextAuth's endpoints.
- **Octane or other long-lived worker behaviour (F-039).** Inferred from code; this environment uses PHP-FPM-style workers.

### Environment state left behind (local only)
- **Local MySQL now contains QA data:**
  - QA cycles 3, 4 and 5 (5 is "SCALE FT 2026-27"), and 2,772 extra students (`26QA####`, `27SC####` on `.qa.test` domains).
  - QA companies, postings 4–41, and admins `qa-admin-a/-b@cdc.qa.test` and `qa-super@cdc.qa.test` (passwords in `qa/actors.md`).
  - `mail_mode` is back to `queued`. A clean demo can be restored with `migrate:fresh --seed` plus `Phase2DemoSeeder`.
- **Test files and scripts:**
  - QA tests in `backend/tests/Feature/QA/` (group `qa`). Run the original suite with `php artisan test --exclude-group=qa`; the QA suite is expected to fail until the findings are fixed.
  - Scripts: `qa/api.sh`, `qa/phase1_regression.sh`, `qa/seed_scale.php`, `qa/scale_3000.xlsx`.
- **No commits were made.**

---

## 11. Re-test results (Part 10 fix phase, 2026-10-01)

The owner answered D-1 … D-12 on 2026-10-01; the answers are recorded as **D91–D102** in `PHASE2_DECISIONS.md`. Fixes went S1 → S2 → S3 → owner-decided items, each with a regression test in `backend/tests/Feature/QA/` (group `qa`) written to fail first. S4/S5 findings were not approved, so they stay open. One exception, F-032, was fixed together with F-002 because it is the same publish path. Nothing was committed (standing rule); §11.7 maps files to findings so the owner can commit one finding at a time.

### 11.1 Finding status

| Finding | Sev | Status | Regression tests / evidence |
|---|---|---|---|
| F-001 | S1 | **FIXED** | `FixF001EnvExampleTest`. **Owner action still needed:** the Gmail app password is still in git history (commits `238560d`…`bdd5af9`). Revoke it in the Google account. Removing the file from history alone does not help. |
| F-002 | S2 | **FIXED** | `FixF002WaitlistPromotionTest` (move promotes within the waitlisted round; guards; console never pre-ticks); `PipelineTest`, `S4PipelineTest::T4_4b_T4_4c_D90…` updated; live E2E-1 step 5 (the CR-01 reproduction, re-run in the browser) |
| F-003 | S2 | **FIXED** | `S4PipelineTest::T4_4a…` (removals mailed + audited, audit count 3), `SAAuditCoverageTest` |
| F-004 | S2 | **FIXED** (owner rule D91) | `FixF004BlockingTest` (11: complete vs internships-only, accepted PPO blocks FT, float category, carry-forward on enrolment, lifted block not revived, backfill command); `S5ResultsBlockingTest` T5.1–T5.3, T5.7, T5.8 rewritten to the rule; `ResultsAndBlocksTest`; live E2E-1 steps 2 and 8 |
| F-005 | S3 | **FIXED** (D101) | `S3…T3_6b_*` (5, incl. naive = IST), `S7…T7_1x`, `S9…T9_3cx`, `FixF005TimezoneTest` (past IST deadline refused, round schedule, event start); live: deadline stored 18:29Z for 23:59 IST |
| F-006 | S3 | **FIXED** | `S1…T1_1c_two_digit_year_text_date_is_not_silently_misread` (now rejected with a reason) |
| F-007 | S3 | **FIXED** | `S3…T3_1b_admin_per_branch_cgpa_edit_is_actually_saved`, `FixF007BranchCutoffEditTest` (3) |
| F-008 | S3 | **FIXED** | `S4PipelineTest::T4_5b_x_second_publish_in_same_second_does_not_remail_earlier_results` |
| F-009 | S3 | **FIXED** (D97) | `S4…T4_8` (now reads the Eligible list), `FixF009EligibleNotAppliedTest` (2); browser: Eligible tab |
| F-010 | S3 | **FIXED** | `FixF010ReplacementOnCompletedDriveTest` (completed drive → replacement request → approve → Re-add → offer) |
| F-011 | S3 | **FIXED** (D94) | `FixF011InviteExpiryTest` (3), `S1…T1_3b` updated to the 7-day rule |
| F-012 | S3 | **FIXED** (D95) | `SAAuditCoverageTest::TA_1b_phase1_admin_mutating_routes_are_audited` now asserts 17/17; live Part 8 run wrote `form.*`, `branch.*`, `policy.*` rows |
| F-013 | S3 | **WONTFIX (owner, D96)** | — |
| F-014 | S3 | **FIXED** | `frontend/tests/e2e/student-portal.spec.js` (`npm run test:e2e:student`), 4/4; G9 below. The bundled Playwright browsers are still missing; see N-3. |
| F-015 | S3 | **FIXED** | `FixF015EmailLogStatusTest` (2); live: this run's rows went queued → sent when the log-mailer worker ran |
| F-016 | S3 | **FIXED** | `FixF016LoginThrottleTest`, `PermissionMatrixTest::rate_limiters…` (report text updated). See N-1. |
| F-020 | S4 | **FIXED** (owner decision 9, D99) | `S1…T1_8_suspended_student_holding_a_token_gets_the_suspended_message`; Chrome check with an open session showed the "Account suspended" screen (student reactivated afterwards) |
| F-024 | S4 | **FIXED** (owner decision 7, D98) | browser: header measured at 375 / 1024 / 1100 / 1280 / 1440 px with no overflow; grouped drawer |
| F-032 | S4 | **FIXED** (with F-002) | `FixF002WaitlistPromotionTest::publish_never_publishes_a_later_result_for_someone_rejected_earlier` (fails with the guard removed) |
| F-034 | S4 | **WONTFIX (owner, decision 12)** | — |
| F-036 | S4 | **WONTFIX (owner, decision 8)** | — |
| D-2 (T3.2) | — | **DONE** (owner decision 2, D93) | `S3…T3_2…`, `S3…T3_3_oracle_other_branch`, `FixD2BranchVisibilityTest` (2: board/detail/calendar/dashboard hidden; already-applied still shown) |
| D-10 (T9.1) | — | **DONE** (owner decision 10, D100) | `S9DashboardsTest` (CTC/branch averages from accepted offers only; `drives_*` keys) |
| F-017, F-018, F-019, F-021, F-022, F-023, F-025–F-031, F-033, F-035, F-037–F-048 | S4/S5 | **OPEN** | not approved for this phase |

QA tests still failing are deliberate reproductions of open S4/S5 findings: `PermissionMatrixTest::every_api_route…` and `T0_4b` (F-040), `T1_1c_unrecognised_pwd…` (F-037), `T1_4a` (F-021), `T3_6c` and `T5_8_x` (F-017), `T4_2b` (F-018), `T6_13` (F-041), `T8_3b` (F-019).

### 11.2 Gates (re-run)

| Gate | Result | Evidence |
|---|---|---|
| G3 `migrate:fresh --seed` | PASS. Run on a throwaway database (`iitism_placement_qa_retest`, dropped afterwards) so the dev database kept its QA fixtures. | `qa/evidence/retest_g3_g4.txt` |
| G4 demo seeder | PASS: 120 students, 8 offers, **16** blocks (each offer reaches both cycles under D91), 0 mails/jobs | same |
| G5 tests | PASS: original suite 116/116. Full suite 307 passed / 9 failed, all 9 open S4/S5 (§11.1). Before Part 10 it was 262 passed / 20 failed / 1 incomplete. | `qa/evidence/retest_g5.txt` |
| G6 `tsc --noEmit` | PASS (exit 0) | `qa/evidence/retest_g6_g7.txt` |
| G7 lint | PASS: 0 errors, 83 warnings (same as before) | same |
| G8 `npm run build` | PASS (exit 0) | `qa/evidence/retest_g8.txt` |
| G9 e2e | **PASS** 7/7 (`tests/e2e/*`), run in the installed Google Chrome (`channel: chrome`, throwaway config) because the bundled browsers are not installed | `qa/evidence/retest_g9_e2e.txt` |
| G15 `.env.example` | PASS | `FixF001EnvExampleTest` |

### 11.3 Permission matrix

170 route/method pairs × 6 actors = 1,020 cells plus 36 cross-tenant probes. The only **5 violations are the pre-existing Phase 1 F-040 rows**, unchanged. The new routes (`…/waitlist/{application}/promote` and `…/eligible`) are admin-only: 401 for guests, 403 for students, suspended students and companies. A suspended student with a token now gets 403 "Account suspended", not 401 (D99). Evidence: `qa/evidence/permission_matrix.md`.

### 11.4 Part 8 Phase 1 regression

All **40/40** rows match on the live MySQL and log-mailer stack (`qa/evidence/retest_part8_phase1_api.txt`).

The audit's own evidence file had 3 mismatches that §8 did not mention: profile PUT 422, edited-mail check 0, and branch delete 405. All three were **defects in the script**, not in the app:
- the profile payload was missing the required contact blocks;
- the script grepped for "edited", but the mail subject is "JNF Updated by Admin: …";
- it used the wrong JSON path for the custom branch id, so the DELETE hit `/programme-branches/`.

The script is fixed, and the company password step can now be re-run. The flows themselves pass.

### 11.5 E2E-1 (re-run)

**PASS** (`qa/evidence/retest_e2e1.txt`). Float with offer category and IST deadline (browser) → backfill → 4 applicants → Aptitude publish → **Waitlist "Move" of the last waitlistee (browser)**. The student is promoted inside the Aptitude round, the next round is empty and the Results console pre-ticks nobody, so the CR-01 symptom is gone. GD → TI drafts → **Results console publish (browser)**: 2 Full-Time offers and 4 block rows (Full-Time and Internship cycles for each student), 1 application flagged as placed elsewhere, and offer mails carrying the new block note. The student's internship board shows "Blocked: accepted a Full-Time offer."

### 11.6 New observations from the fix phase

- **N-1 (NEEDS-OWNER-DECISION, S3): logins all come from the Next.js server's IP.** NextAuth's `authorize()` calls `POST /api/auth/login` from the server, so in production every student's login shares one source IP. The per-IP `api` bucket then allows **60 logins per minute portal-wide**, and 60 failed attempts by anyone lock everyone out for a minute. The new per-account limiter (F-016) doesn't change this.
  - Option (a): give `/auth/login` its own much larger per-IP budget (e.g. 600/min) and rely on the per-account limit.
  - Option (b): have Next.js forward the client IP with a shared secret, and trust only that.
  - Not changed, because changing it wasn't approved.
- **N-2: blocks created before D91.** Older offer blocks cover only their own cycle. Run `php artisan placement:extend-offer-blocks` once on any database that holds offers from before this change. It is idempotent and audited, and also sets the placed-elsewhere flags. On local dev it added 11 rows and flagged 2 applications.
- **N-3: Playwright browsers.** Playwright 1.58 expects browser build 1208; the local cache has 1217/1243. Run `npx playwright install` once (a download, left to the owner). Until then, the specs run against the installed Chrome.

### 11.7 Files by finding (all uncommitted)

| Finding / decision | Files |
|---|---|
| F-001 | `backend/.env.example` |
| F-002, F-032 | `backend/app/Services/PipelineService.php` (`promoteFromWaitlist`, publish guard), `backend/app/Http/Controllers/AdminPipelineController.php`, `backend/routes/api.php` (promote route), `frontend/components/admin/posting/waitlisttab.jsx` |
| F-003 | `backend/app/Http/Controllers/AdminProposalController.php` |
| F-008 | `backend/app/Jobs/SendRoundResultMails.php`, `PipelineService::dispatchResultMails`, `AdminPipelineController`, `AdminResultController` |
| F-004 / D91 | `backend/app/Services/BlockingPolicy.php`, `backend/app/Http/Controllers/AdminResultController.php`, `backend/app/Http/Controllers/AdminPlacementCycleController.php` (carry-forward), `backend/routes/console.php` (backfill), `backend/database/migrations/2026_09_27_000018_add_offer_type_to_job_postings.php`, `backend/app/Models/JobPosting.php`, `backend/app/Http/Controllers/AdminPostingController.php` (offer_type), `backend/app/Support/PostingPresenter.php`, `backend/database/seeders/Phase2DemoSeeder.php`, `frontend/lib/offerpolicy.js`, `frontend/components/shared/blockingrules.jsx`, `frontend/components/admin/floatdialog.jsx`, `frontend/components/admin/posting/overviewtab.jsx`, `frontend/app/admin/postings/[id]/results/page.jsx`, `frontend/components/student/postingcard.jsx`, `frontend/app/student/postings/[id]/page.jsx`, `frontend/components/student/applypanel.jsx` |
| F-005 / D101 | `backend/app/Support/Ist.php`, `AdminPostingController` (deadline, rounds), `backend/app/Http/Controllers/AdminEventController.php`, `frontend/lib/format.js`, `floatdialog.jsx`, `overviewtab.jsx`, `frontend/components/admin/posting/roundstab.jsx`, `frontend/app/admin/events/page.jsx`, `frontend/components/shared/eventcard.jsx`, `frontend/app/student/notifications/page.jsx` |
| F-006 | `backend/app/Services/StudentAccountService.php` |
| F-007 | `backend/app/Http/Controllers/AdminFormReviewController.php` (`flattenBranchRules`) |
| F-009 / D97 | `AdminPostingController::eligible`, `routes/api.php`, `frontend/components/admin/posting/eligibletab.jsx`, `frontend/app/admin/postings/[id]/page.jsx` |
| F-010 | `backend/app/Http/Controllers/CompanyPipelineController.php`, `frontend/app/company/postings/[id]/page.jsx` |
| F-011 / D94 | `backend/config/auth.php`, `backend/database/migrations/2026_10_01_000019_create_student_invite_tokens_table.php`, `StudentAccountService`, `backend/app/Http/Controllers/AuthController.php`, `backend/resources/views/emails/student-invitation.blade.php` |
| F-012 / D95 | `AdminFormReviewController`, `backend/app/Http/Controllers/AdminManagementController.php`, `AdminProgrammeBranchController.php`, `AdminCompanyController.php`, `PolicyDocumentController.php` |
| F-014 | `frontend/tests/e2e/student-portal.spec.js`, `frontend/package.json` (script) |
| F-015 | `backend/app/Services/MailDispatchService.php`, `backend/app/Models/EmailLog.php`, `backend/app/Providers/AppServiceProvider.php` (listeners), `backend/database/migrations/2026_10_01_000020_add_message_ref_to_email_logs.php` |
| F-016 | `AppServiceProvider` (`login` limiter), `routes/api.php` |
| D93 (decision 2) | `backend/app/Services/EligibilityService.php` (`offersBranch`), `backend/app/Http/Controllers/StudentPostingController.php`, `CalendarController.php`, `StudentDashboardController.php` |
| F-020 / D99 | `backend/app/Http/Controllers/AdminStudentController.php`, `frontend/lib/studentapi.js`, `frontend/components/student/studentshell.jsx` |
| F-024 / D98 | `frontend/components/admin/adminshell.tsx` |
| D100 (decision 10) | `backend/app/Http/Controllers/AdminAnalyticsController.php`, `frontend/app/admin/analytics/page.jsx` |
| Tests | new: `backend/tests/Feature/QA/Fix{F001,F002,F004,F005,F007,F009,F010,F011,F015,F016,D2}*Test.php`; updated: `tests/Feature/{PipelineTest,ResultsAndBlocksTest,EventsCalendarTest,Phase2DemoSeederTest}.php`, `tests/Feature/QA/{S1StudentAccounts,S3EligibilityApply,S4Pipeline,S5ResultsBlocking,S9Dashboards,SAAuditCoverage,PermissionMatrix}Test.php` |
| QA tooling | `qa/phase1_regression.sh` (script defects), `qa/evidence/retest_*` |

**Local environment after Part 10:** two new migrations were applied to the dev MySQL (`…000019`, `…000020`), and `placement:extend-offer-blocks` was run on it. E2E-1 created posting #42 (completed, 2 offers). The QA student 26QA0016 was suspended and reactivated for the session check. No real email was sent: the backend server and every worker ran with `MAIL_MAILER=log`, and the queue is empty.
