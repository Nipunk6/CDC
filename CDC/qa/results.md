# Working results (compiled into QA_REPORT.md at the end)

Format: | ID | Result | Sev | Evidence | Notes |

## Part 1 — gates
| ID | Result | Sev | Evidence | Notes |
|---|---|---|---|---|
| G1 | PASS (info) | — | `git -C CDC status --short` → 157 entries (117 untracked, 40 modified); `git log` → Phase 2 commits only `bdd5af9` (M0), `f7c12cb` (lint), `7bcff0a` (M1). M2–M10 + D89/D90 are **uncommitted** | owner commits per memory rule |
| G2 | PASS | — | `qa/evidence/g2_g4.txt`: MySQL 9.6.0, db `iitism_placement`; `.env` DB_CONNECTION=mysql | |
| G3 | PASS | — | `qa/evidence/g2_g4.txt`: migrate:fresh --seed exit 0, all 2026_09_27_* DONE | |
| G4 | PASS | — | `qa/evidence/g4_counts.txt`: 120 students, 2 cycles, 240 enrolments, 120 resumes, 3 postings, 8 rounds, 115 applications, 98 round results, 8 offers, 8 blocks, 2 events; **0** proposals / branch changes / audit_logs / notifications | see T12.2 |
| G5 | PASS | — | `qa/evidence/g5.txt`: `php artisan test --exclude-group=qa` → 116 passed (741 assertions) | |
| G6 | PASS | — | `npx tsc --noEmit` exit 0 | |
| G7 | PASS | — | `qa/evidence/g7_lint_full.txt`: exit 0, 0 errors / 83 warnings; suppressions = the four D33 sites + two Phase 1 `exhaustive-deps` lines that exist at df7034b (`register/page.tsx:369`, `phoneinputpro.tsx:49`) | 1 Phase 2 warning: `studentshell.jsx:102` `<img>` (S5) |
| G8 | PASS | — | `qa/evidence/g8_build.txt`: `npm run build` exit 0 | |
| G9 | FAIL | S3 | `qa/evidence/g9_e2e.txt`: 18/18 failed — `browserType.launch: Executable doesn't exist` (chromium 1208, firefox 1509, webkit 2248). The 18 = 3 smoke + 3 perf specs × 3 browsers — **no Phase 2 E2E spec exists** | not installed per audit rule (no package installs) |
| G10 | PASS | — | `qa/evidence/g10_queue.txt`: dispatched SendStudentInvitation → `queue:work --once` ×2 → job DONE + mail DONE, 0 failed; laravel.log:3029-3030 `To: 23je0104@students.cdc-demo.test` / `Subject: Your IIT ISM CDC Placement Portal account` | but email_logs status stays `queued` → F-EMAILLOG |
| G11 | PASS | — | `public/storage -> storage/app/public` symlink | |
| G12 | PARTIAL | S4 | working tree == df7034b (`git diff df7034b -- backend/package*.json` = 0 lines) BUT committed HEAD still has `"@mui/x-charts": "^9.14.0"` (HEAD:CDC/backend/package.json:18) + 842-line lock; the revert is uncommitted (`M  backend/package-lock.json`, ` M backend/package.json`) | commit the revert |
| G13 | PASS | — | frontend `@mui/x-charts@7.29.1` (peer MUI ^5.15/^6/^7); `CDC/backend/node_modules/@mui` absent | |
| G14 | PASS | — | `@mui/material@6.5.0`, `react@19.2.4`, `next@16.2.1`, `@emotion/react@11.14.0` | |
| G15 | FAIL | S1 | `backend/.env.example:57` MAIL_PASSWORD non-empty (Gmail app-password format) for `test000mailer@gmail.com`, tracked since `238560d` (initial commit); `:3` fixed APP_KEY; `:73` ADMIN_PASSWORD=pa…23 (redacted); stray `a` line removed ✔; MAIL_BULK_BATCH_SIZE + COMPANY_RECRUITER_VERIFY_TTL_MINUTES documented ✔; no frontend `.env.example` (Phase 1 gap persists) | F-SECRETS |
| G16 | PASS | — | `PHASE2_PROGRESS.md:218` "php artisan queue:work must run permanently…" | |

## Findings log (draft)
- F-SECRETS [S1] tracked `.env.example` has a non-empty Gmail SMTP app password (in history since the initial commit) + a fixed APP_KEY (a deployment that copies the example without `key:generate` lets anyone forge signed resume URLs / decrypt) + weak ADMIN_PASSWORD default.
- F-E2E [S3] Playwright browsers absent; zero Phase 2 E2E specs.
- F-PKG [S4] package pollution still in HEAD; revert uncommitted.
- F-EMAILLOG [S3] queued-mode `email_logs.status` never leaves `queued` (D39) — no delivery/failure visibility for admins.

## Part 6 — permission matrix (verified by re-running `php artisan test --filter=PermissionMatrixTest`)
- 168 route/method pairs × 6 actors (guest, STU, STU_SUSP, CO_A, CO_B, ADMIN) = 1,008 cells + 36 cross-tenant probes; 0 skipped. Evidence: `qa/evidence/permission_matrix.md`, `qa/evidence/rate_limits.md`.
- **Zero unexpected 2xx** for any wrong actor (no S1 from the matrix). All 36 cross-tenant probes 404/403. Leak scan of 28 successful GETs for other-tenant markers: clean.
- 5 violations, all Phase 1 pre-existing (not regressions): (1) `GET /api/admin/policy-documents/{id}` → 500 (no `show()`; documented in PROJECT_CONTEXT §7) [S4, Phase 1]; (2–5) `PUT|PATCH /api/company/{jnfs,infs}/{id}` by another company with an invalid body → 422 instead of 404 (FormRequest validates before the ownership check → existence oracle; valid body → 404) [S4, Phase 1].
- Rate limits: `api` 60/min per user (61st → 429, Retry-After 60); login shares the guest `api` bucket per IP (61st → 429; X-Forwarded-For spoof ignored) — **no per-account login throttle** (distributed guessing not slowed) [S3]; `signed-files` 600/min/IP.
- Suspended user: 403 on every authenticated route incl. `POST /auth/logout` (tokens are revoked at suspension anyway, D44).
- `APP_DEBUG=true` in `.env` and `.env.example` → error responses carry stack traces (prod must set false; see Part 6 live check).

## Part 2 — live (MySQL + running servers) evidence
| ID | Result | Sev | Evidence | Notes |
|---|---|---|---|---|
| T0.3 (API) | PASS | — | cycles 3 "QA FT 2026-27" + 4 "QA Intern 2026-27" created via `POST /admin/placement-cycles` (201) | UI pass in Part 11 |
| T1.1b (live) | PASS | — | `qa/actors_import.csv` dry run → "16 row(s) ready… 0 error(s)", profiles stayed 120; real run → 16 created, 16 jobs, audit rows 17 (1 `student.import` + 16 `student.create`) | |
| T1.3a (live) | PASS | — | laravel.log E1 for 26qa0001: "Username (Roll Number): 26QA0001" + `http://127.0.0.1:3000/auth/student/set-password?token=…&email=…`, no password; token used via `POST /auth/reset-password` → 200 | |
| T1.8 (live) | PARTIAL | S4 | `qa/evidence/t1_8_live.txt`: login after suspension → 403 "Account suspended. Contact CDC." ✔; **existing session token → 401 "Unauthenticated."** (tokens deleted at suspension, D44) instead of spec B7's 403 suspension message; the student UI then shows a bare "Unauthenticated." banner with no redirect | |
| T5.6 (live, setup) | PASS | — | `POST /admin/blocks` reason debarred with scope `internships_only` → stored scope `all` (D84d), blocked_by=125 | |
| T3.4a (live UI) | PASS | — | company wizard (browser) saved per-branch `backlogsAllowed:true, maxOngoingBacklogs:0, maxTotalBacklogs:1`, `minTenthPercent/minTwelfthPercent "60"`; wizard Preview shows "Min 10th % 60 / Min 12th % 60" and "backlogs ≤0 ongoing, ≤1 total" per branch; `qa/screenshots/setup-01-company-jnf-submitted.jpg` | admin detail/CSV checked by S8 tests |
| T3.1a (live UI) | PASS | — | before float `GET /student/postings` (26QA0001) → 0 postings; Float dialog lists only fulltime cycles for a JNF, preview "7 of 15 enrolled students are eligible" (= oracle) `qa/screenshots/T3_1a-float-dialog-7-of-15.jpg`; after float card "apply by 08 Oct 2026, 06:00 pm" `T3_1a-floated.jpg` | guards (twice/closed/non-accepted) in S3 tests |
| T3.6b (storage+display) | PASS | — | `qa/evidence/t3_float_live.txt`: deadline entered 18:00 IST in the dialog → `job_postings.application_deadline = 2026-10-08 12:30:00` (UTC) → displayed "06:00 pm"; round date 2027-01-15 → `scheduled_at 2027-01-15 04:30:00` = 10:00 IST (D69) | enforcement moment tested below |
| T4.1a (live) | PASS | — | 3 posting_rounds Aptitude Test / Group Discussion / Technical Interview(FINAL) from 3 enabled + 4 disabled form rounds | |
| T3.5 (live create) | PASS | — | questions stored: `text*`, `text`, `mcq_single* [Bengaluru/Hyderabad]`, `mcq_multi [Go/Python/Java]` | |
| T6.2 (live) | PASS | — | E2 `email_logs` = exactly 26QA0001,0004,0005,0012,0013,0014,0015 = `eligibleStudentsQuery`; 7 in-app "New opening"; suspended/debarred/not-enrolled/ineligible got nothing; 1 queued job → 1 BCC message (To = MAIL_FROM_ADDRESS); audit `posting.float` user 125 | |
| T3.3 (live oracle) | PASS | — | `qa/evidence/t3_3_oracle_live.txt`: all 12 actors match the oracle on list badge, detail and direct API apply; ineligible apply → 422 `{"message":"You are not eligible for this posting.","reasons":[…]}` with the same reasons; not-enrolled → 404 "Posting not found."; suspended → 401 | reasons e.g. "CGPA below cutoff (6.2 < 7.0)", "Ongoing backlogs above the limit (1 > 0).", "Open to the 2027 graduating batch only.", "You are debarred from this placement cycle. (QA debarment for misconduct)" |
| T1.7 (live part) | PASS | — | 3 eligible applies with pending resumes → `used_unverified_resume=1` | clearing checked in tests |
| T3.8 (live) | PASS | — | `qa/evidence/t3_8_live.txt`: raw JSON of postings list/detail, applications, dashboard, calendar, events, profile — only own counts (`meta.total` = own postings, `resumes.total`, `active_applications`) | |
| B5/§13 leak (live) | PASS | — | posting detail (12 KB) contains none of: recruiter emails/phones, contact JSON keys, signatory, declarations, postalAddress | |

## Part 5 — independent code review (fresh general-purpose subagent, read-only), each item re-verified by me
| ID | Verdict | Sev | Verified evidence | Summary |
|---|---|---|---|---|
| CR-01 | CONFIRMED (code; live repro pending E2E) | S2 | `waitlisttab.jsx:69-77` writes `result:"selected"` into the NEXT round; `PipelineService::pool()` (:67-80) only admits previous-round `selected`; `publish()` publishes every draft; `notifyResults` :295 "You cleared {round}" | **D90 implementation bug:** "Move to <next round>" marks the student as having CLEARED the next round instead of entering it; reject_remaining never touches them; publishing announces "You cleared …" (final round: pre-ticked for an offer). Owner asked "pushed … to interview" = take part in it. |
| CR-02 | CONFIRMED | S4 | `AdminResumeController.php:110-113` only clears on approve | approved→rejected resume does not re-set `used_unverified_resume` |
| CR-03 | CONFIRMED | S3 | `AdminProposalController.php:126-131` → `PipelineService::removeFromWaitlist` sets `published_at=now()`; no `dispatchResultMails` on this path | approving a company waitlist proposal publishes removals immediately (not draft) and sends no regret mail |
| CR-04 | CONFIRMED | S3 | `AdminResultController.php:316` flags `completed` postings; E9 text invites a "Replacement request"; `CompanyPipelineController.php:141-142` 422s on `completed` | replacement invitation dead-ends on completed drives |
| CR-05 | CONFIRMED | S4 | `AdminResultController.php:169-174` accepts any final-round row incl. published `rejected` | API-only bypass of the Re-add protocol at final publish |
| CR-06 | CONFIRMED | S4 | `AdminBlockController.php:97-116` no `DB::transaction`; `$stillBlocked` counts `internships_only` blocks | unblock not atomic; FT flags may stay when an internships-only block remains |
| CR-07 | CONFIRMED (code) | S4 | `AdminEventController.php:81-85` check-then-update without lock | concurrent publish can send E6 twice |
| CR-08 | CONFIRMED (code; live race test below) | S4 | `StudentApplicationController` eligibility/deadline checks outside the txn; lockForUpdate on a non-existent row | double-submit → possible 500 instead of clean 4xx |
| CR-09 | CONFIRMED | S4 | only `status` filter at `AdminPlacementCycleController.php:138`; no write path to `cycle_enrollments.status='suspended'` | "suspend the enrolment instead" (D88c) is impossible; EligibilityService's enrolment-suspended reason unreachable |
| CR-10 | NEEDS-OWNER-DECISION | S4 | `eligibleStudentsQuery` used only for counts + E2 | no admin "eligible students" list/export for a posting (B2 lists it as a caller) |
| CR-11 | CONFIRMED (D43) | S4 | `StudentAcademicSyncService.php:102-116` no mail | academic bulk sync sends no E8 email (in-app only) |
| CR-12 | CONFIRMED (code) | S4 | `PostingPresenter.php:103-110`, `EventFeedController.php:25`, `CalendarController.php:49`, `StudentDashboardController.php:63,70,91`, `AdminEventController.php:44` | N+1 queries (measured in Part 7) |
| CR-13 | CONFIRMED (code) | S4 | `PipelineService::publish` doesn't re-check `rejectedEarlier()` | a moved draft can be announced after the student was rejected/removed in round N |
| CR-14 | CONFIRMED | S4 | `AdminPipelineController.php:166-175` `firstOrNew…save()` no published check | attendance on published rows silently editable |
| CR-15 | CONFIRMED | S4 | `AdminPostingController.php:403-405` | rounds can't be reordered after any publish — spec B3 says "at any time" (D75h) |
| CR-16 | PLAUSIBLE | S5 | `AdminPostingController@update` can extend a passed deadline while drafts exist | reopens applications mid-round |
| CR-17 | CONFIRMED LIVE | S3 | PATCH `application_deadline:"2026-10-09T18:00:00+05:30"` → stored `2026-10-09 18:00:00` (should be 12:30 UTC); `Carbon::parse` at `AdminPostingController.php:163,231` (+ events/rounds) never `->utc()` | API clients sending offsets get a +5h30 shift; the UI sends `Z` so the UI path is correct |
| CR-18 | CONFIRMED (code) | S5 | `Resume.php:88-91` signs by id; slot file replaced in place | company's 30-day link can show a newer resume than the one applied with |
| CR-19 | CONFIRMED | S5 | listed dead/duplicate code | cleanup |
| CR-20 | CONFIRMED | S5 | `…000008_create_job_postings_table.php:16,26` | redundant index |
| CR-21 | CONFIRMED | S5 | `SpreadsheetImportService.php:59-61` silent 5000-row cap; label rename bumps updated_at → misleading 409; E1 60-min links | misc |
- Clean areas (reviewer + my spot checks): single eligibility implementation; BlockingPolicy = B4 matrix; transactions (except CR-06); no mass assignment holes; ownership checks in every student/company method; no Company/contact leaks; no applicant counts to students; mail recipients E1–E10 (except CR-03/CR-11); no personal_email sends; no reminders; signed route + private disk; validation ranges; no new .ts/.tsx; no `dangerouslySetInnerHTML`; no TODO/console.log; migrations (only the approved stub edited).
| T3.6c (live MySQL, 4 PHP workers) | PASS | — | 3 concurrent `POST /student/postings/4/apply` for 26QA0012 → `req2 201, req1 409, req3 409`; exactly 1 application row; no new DB errors in laravel.log | CR-08 not reproduced live |
| T2.1 (live, real PHP limits: upload_max_filesize=2M) | PASS | — | `qa/evidence/t2_1_live.txt`: 1.9 MB PDF 201; 2 MB+1 422 "The resume must be 2 MB or smaller."; `UPPER.PDF` 201; real PNG renamed `.pdf` 422 "The resume must be a PDF."; `.docx` 422 | |
| Part 6 path traversal (live) | PASS | — | upload with client filename `../../../../public/evil.pdf` → stored `resumes/26QA0013/7_<uuid>.pdf` (private disk); nothing under `public/` | |
| T0.6 (live) | PASS | — | files under `storage/app/private/resumes/{ROLL}/{slot}_{uuid}.pdf`; `storage/app/public/resumes` does not exist | orphaned dirs from older runs (e.g. `22JE0001`) survive `migrate:fresh` (S5) |

## Part 2 — automated QA tests (re-run and verified by me): `tests/Feature/QA/S3EligibilityApplyTest.php`, `S6NotificationsTest.php` → 6 failed, 45 passed (921 assertions)
| ID | Result | Sev | Evidence (test) | Notes |
|---|---|---|---|---|
| T3.1a | PASS | — | `test_T3_1a_separate_float_step_and_guards` (+ live) | non-accepted/twice/closed cycle/past deadline/type-mismatch all 422 |
| T3.1b | PASS (snapshot) | — | `test_T3_1b_snapshot_frozen_when_admin_raises_cutoff_after_float` | eligibility keeps the float-time snapshot |
| T3.1b-side / F-ADMINEDIT | FAIL | S3 | `test_T3_1b_admin_per_branch_cgpa_edit_is_actually_saved`: "No changes detected." expected '9.0' got '7.0' | `AdminFormReviewController.php:1080-1082` + `detectChangedFields` compares only *selected* branches → admin edits to per-branch CGPA / backlog flag / **M4 caps** are silently dropped. Early-return pre-exists at df7034b:1060 but Phase 2 added the cap fields to that edit card (D58) |
| T3.2 | PASS + NEEDS-OWNER-DECISION | — | `test_T3_2_visibility_…` + live (26QA0006 sees posting "Your branch is not eligible.") | spec B2 "see ALL postings" vs owner text implying branch-ineligible students may not need to see it |
| T3.3 / T3.3b / T3.4b / T3.4c | PASS | — | `test_T3_3_oracle_*` (13 tests), `test_T3_3b_cgpa_boundary_and_cutoff_parsing` ("7","7.0","7.00"," 7.0 "; 7.00 ok, 6.99 not), `test_T3_4b_legacy_boolean_backlogs`, `test_T3_4c_oracle_low_12th` | |
| T3.5 | PASS | — | `test_T3_5_questions_…`, `test_T3_5_no_company_route_can_add_questions` | answers in admin view + export Q1–Q4 columns |
| T3.6 | PASS | — | `test_T3_6_edit_withdraw_reapply_until_deadline_then_frozen`, `test_T3_6_no_per_cycle_withdraw_prohibit_toggle` + live `qa/evidence/t3_6b_enforcement_live.txt` | |
| T3.6b | PARTIAL | S3 | UI path PASS live (deadline 11:20:06 IST: apply 11:18:51 → 201; apply/withdraw/edit 11:20:12 → 422 "The application deadline has passed."). API path FAIL: `test_T3_6b_ist_offset_deadline_*` (3 tests) — `+05:30` input stored as wall-clock (expected 12:30, got 18:00; apply at 18:01 IST → 201) = CR-17 | naive strings ("2027-01-10T18:00") treated as UTC (NEEDS-OWNER-DECISION); API serialises UTC `…Z` |
| T3.6c | PARTIAL | S4 | sequential + live 3-way race clean (409); `test_T3_6c_double_submit_race_window_is_a_clean_4xx_not_500` → 500 `SQLSTATE[23000] UNIQUE` when the race window is forced | `StudentApplicationController.php:89-120` doesn't catch the unique violation (= CR-08) |
| T3.7 | PASS | — | `test_T3_7_placed_elsewhere_flag_and_remove_from_process` | default notify ON, E9 to Company B only, audit; notify=false → no mail |
| T3.8 | PASS | — | `test_T3_8_students_never_see_applicant_counts` (6 endpoints, key + value scan) + live | |
| T3.9 | PASS | — | `test_T3_9_job_alerts_and_preferences_not_built` | |
| T6.1–T6.12 | PASS | — | `test_T6_1_…` … `test_T6_12_no_mail_ever_goes_to_a_personal_email(_in_sync_mode)`; E2 BCC = eligible set; E4 5 sel/2 wl/3 rej → 10 email_logs + 10 in-app; E6 all/branches/live applicants; E9 replacement text; E10 admins on submit, company on decision; no reminders; never personal_email | E3 is re-sent on re-apply after withdraw (recorded) |
| T6.13 | FAIL | S5 | `test_T6_13_in_app_notification_for_every_student_facing_trigger`: "E1 account created" has no in-app row | `StudentAccountService.php:198` mail only |
| T6.15 | PASS | — | `test_T6_15_templates_use_frontend_url_and_no_hardcoded_localhost` | |
| Part 6 error leakage | PASS (prod config) | S3 (example) | temp server `APP_DEBUG=false` → 500 body `{"message":"Server Error"}`; dev server (`APP_DEBUG=true`) → full trace | `.env.example` ships `APP_DEBUG=true` |
| Part 6 CORS | PASS | — | preflight Origin `https://evil.example` → no `Access-Control-Allow-Origin`; `http://localhost:3000` → allowed | |

## Part 2 — automated QA tests (re-run and verified by me): `S0FoundationTest.php`, `S1StudentAccountsTest.php`, `S2ResumesTest.php` → 5 failed, 44 passed (1270 assertions)
| ID | Result | Sev | Evidence (test) | Notes |
|---|---|---|---|---|
| T0.4a | PASS | — | `test_T0_4a_public_admin_register_is_removed` (404, no route, no method) | |
| T0.4b | PASS | — | `test_T0_4b_company_cannot_create_…`, `…put_a_privileged_status…`, `…autosave…` (autosave ignores status → 200, status unchanged) | |
| T0.4b-extra | FAIL | S4 (Phase 1 pre-existing) | `test_T0_4b_extra_blank_status_is_a_validation_error_not_a_server_error`: `"status": null`/`""` on company JNF/INF store/update → 500 (enum constraint) | `StoreJnfRequest.php:34` nullable + `CompanyJnfController.php:81,145` `input('status', default)`; same at df7034b |
| T0.4c / T0.4d / T0.4e / T0.4g | PASS | — | `test_T0_4c_…`, `test_T0_4d_tokens_expire_after_seven_days` (6d 200, 8d 401, 3 roles), `test_T0_4e_logout_revokes_…` (3 roles), `test_T0_4g_floated_forms_are_undeletable_…` | |
| T0.5a / T0.5b / T0.5c | PASS | — | `test_T0_5a_…` (persisted + audited), `test_T0_5b_…` (job + queued mailables), `test_T0_5c_sync_mode_sends_inline_and_logs_sent` | queued email_logs never leave `queued` (F-EMAILLOG) |
| T0.6 | PASS | — | `test_T0_6_resumes_are_stored_on_the_private_disk_only` + live | |
| T1.1a / T1.1b | PASS | — | `test_T1_1a_…`, `test_T1_1b_bulk_import_csv_dry_run_then_real_run`, `…csv_with_utf8_bom`, `…xlsx`, `test_T1_1b_template_download_matches_importer_column_order` | dry run writes nothing (users/profiles/audit/email_logs/tokens/jobs unchanged); rows 10–13 reported with row+roll+reason; blank skipped; trailing header reported |
| T1.1c | PARTIAL | S3 | listed cases PASS (lower-case roll → upper; trim; `8,5` rejected "must be a number"; `8.50` ok; pwd Yes/TRUE/1; serial + d/m/Y dates). FAIL `test_T1_1c_two_digit_year_text_date_is_not_silently_misread`: "06-05-04" (6 May 2004) stored as **2006-05-04**; FAIL `test_T1_1c_unrecognised_pwd_value_is_not_silently_coerced`: pwd "maybe" → false (S4) | `StudentAccountService.php:218-241`, `:113-116` — silent DOB corruption on import |
| T1.2 | PASS | — | `test_T1_2_login_with_roll_number_case_insensitive_and_no_enumeration` | unknown roll and wrong password → identical 422 "Invalid credentials."; cross-portal guard is frontend-only (auth.ts); unknown roll skips bcrypt → timing side channel (S5, code only) |
| T1.3a | PASS | — | `test_T1_3a_…` + live | |
| T1.3b | PASS + NEEDS-OWNER-DECISION | S3 | `test_T1_3b_invite_link_expiry_and_resend_invitation`: token back-dated 61 min → 422; `POST /admin/students/{id}/resend-invitation` exists, audited (`student.invite_resend`), invalidates old token; forgot-password by roll works | 60-min invitation window vs ~3000 bulk-imported students (D41) |
| T1.4a | PARTIAL | S4 | student side PASS (past-cycle applications/offers/trail); FAIL `test_T1_4a_…`: `GET /admin/students/{id}` has no blocks history | `AdminStudentController.php:113-164`; UI blocks panel uses `/admin/blocks?student_profile_id=` (workaround) |
| T1.4b | PASS | — | `test_T1_4b_student_cannot_edit_academic_or_identity_fields` | |
| T1.5 | PASS | — | `test_T1_5_admin_edits_cgpa_and_branch_audited_and_student_mailed` | |
| T1.6a | PASS | — | `test_T1_6a_branch_change_flow` (409 second pending; approve by admin B audited + E8; reject needs remark) | |
| T1.6b | NEEDS-OWNER-DECISION | S3 | `test_T1_6b_…`: new eligibility uses the new branch; existing old-branch applications stay `applied`, unflagged, editable | `AdminBranchChangeController.php:84-89` |
| T1.6c | PASS | — | `test_T1_6c_…` (2 tests) | no E8 mail on sync (CR-11) |
| T1.7 | PASS | — | `test_T1_7_pending_resume_application_flag_lifecycle` + live | rejecting an approved resume doesn't re-flag (CR-02) |
| T1.8 | PARTIAL | S4 | PASS: login 403 suspended msg, reactivate, audited, excluded from E2/eligibility; FAIL `test_T1_8_suspended_student_holding_a_token_gets_the_suspended_message` → 401 "Unauthenticated." | D44 vs spec B7 wording (+ live) |
| T2.1 / T2.1b / T2.2 / T2.4 / T2.6 / T2.7 | PASS | — | `test_T2_1_pdf_only_max_2mb_with_real_mime_sniffing` (real temp files: 2 MB exact ok, 2 MB+1, docx, PNG-as-pdf, text-as-pdf rejected, no orphan files), `test_T2_1b_max_eight_slots`, `test_T2_2_…` (old file deleted, remark cleared, E7 both), `test_T2_4_…` (empty/blank/200/61 chars → 422, 60 ok), `test_T2_6_…` (55 pending → 50+5 pages, 1 audit per decision), `test_T2_7_*` (guest 401, cross-student 404, company only via signed URL, tamper/swap/expiry → 403) | valid signature + non-existent id → 404 (S5) |
| T2.5 | PASS + NEEDS-OWNER-DECISION | S4 | `test_T2_5_…` (3 tests): locked while open/in_process; withdrawn doesn't lock; replace allowed after completion; **delete** after completion/withdrawal refused (D50, `restrictOnDelete` per spec C9 vs B5 "allowed after it completes") | |

## Part 2 — automated QA tests (re-run and verified by me): `S4PipelineTest.php`, `S5ResultsBlockingTest.php` → 5 failed, 28 passed (1050 assertions)
| ID | Result | Sev | Evidence (test) | Notes |
|---|---|---|---|---|
| T4.1a / T4.1b | PASS | — | `test_T4_1a_…` (+ live), `test_T4_1b_…` (remove round with results → 422, audited) | |
| T4.2a | PASS | — | `test_T4_2a_…`: 15 admin endpoints → 403 for company; E10 to both admins; students see nothing | |
| T4.2b | PARTIAL | S4 | paste mode PASS; FAIL `test_T4_2b_admin_upload_xlsx_reports_every_bad_row`: duplicate roll not reported, counted twice ("4 draft result(s) saved"), conflicting duplicate silently last-wins | `AdminPipelineController.php:440-476` (no dedupe), `PipelineService.php:141` |
| T4.2c / T4.3 | PASS | — | `test_T4_2c_…`, `test_T4_3_…` (no draft value in raw student/company JSON before publish; 10 email_logs; BCC per student; next round ongoing) | |
| T4.4a | FAIL | S2 | `test_T4_4a_…`: student removed from a PUBLISHED waitlist via an approved company waitlist proposal flips to "rejected" with **no E4 regret, no in-app notice, no `waitlist.remove` audit** (expected 1 mail, got 0) | = CR-03 (`AdminProposalController.php:126-132`) |
| T4.4b / T4.4c | APPROVED-BY-OWNER (D90) | — | `test_T4_4b_T4_4c_D90_…`: no rank in 7 payloads, reorder route 404/405, no auto-promotion, admin can move any waitlisted as a draft without warning | BUT see CR-01 (moved = "cleared next round") |
| T4.5 / T4.5b | PASS | — | `test_T4_5_…` (confirm+remark required, company mailed, audit; company can only propose), `test_T4_5b_…` | |
| T4.5b-x | FAIL | S3 | `test_T4_5b_x_second_publish_in_same_second_does_not_remail_earlier_results`: re-publish in the same second re-mails everyone stamped that second (regret count 2, expected 1) | E4 rows selected by `published_at` (second resolution): `PipelineService.php:176`, `AdminPipelineController.php:225`, `SendRoundResultMails.php:35` — double-click on Publish |
| T4.6 / T4.7 | PASS | — | `test_T4_6_…`, `test_T4_7_…` (round 2 draft shows pending; no leak in applications/detail/dashboard) | |
| T4.8 | PARTIAL | S3 | Applied / Appeared / Selected-Not per round PASS; FAIL: eligible-but-not-applied students absent from `GET /admin/postings/{p}/pipeline` | owner listed "Eligible – Applied / Not Applied" (req 20); only a count exists (CR-10) |
| T5.1, T5.2a–i, T5.3–T5.8 | PASS | — | `test_T5_1_…`, `test_T5_2a…2i_…`, `test_T5_3_blocks_are_per_cycle`, `test_T5_4_…` (M.Tech CTC 1,800,000 via name normalisation; manual 1,950,000 persists; INF stipend per programme), `test_T5_5_…`, `test_T5_6_…`, `test_T5_7_…` (side effects + atomic rollback on injected failure), `test_T5_8_…` | ppo_offered labelled "PPO offered (not accepted)" vs "PPO accepted (after internship)" — clear |
| T5.8-x | FAIL | S4 | `test_T5_8_x_concurrent_double_publish_loser_gets_clean_4xx_not_500` → 500 (unique `offers.application_id` holds, no duplicate) | check outside txn `AdminResultController.php:151-165` (= CR-08) |
| **F-CYCLE-MIX** | NEEDS-OWNER-DECISION | S2 | `AdminPostingController.php:148-155` refuses INF→fulltime and JNF→internship cycles (spec M5.3); blocks are per cycle (Q5.3) | ⇒ the B4 `internships_only` scope can never matter in practice: `intern` in an internship cycle blocks everything there; `intern_performance_ppo` ("final-years inside an FT cycle") blocks nothing that exists; an `intern_ppo` on an INF does not block the FT cycle. The QA prompt's E2E-2 ("a second INF and a JNF floated into a fulltime cycle") is impossible. Tests had to move postings between cycles via DB to exercise the scope. |
| (observation) | — | S4 | `EligibilityService.php:228` caches active blocks per service instance; controller instances persist on Route objects → under Octane/long-lived workers a new block is ignored until restart | PHP-FPM / artisan serve unaffected |
| (observation) | — | S5 | `EligibilityService.php:266-271`: block on a `ppo_offered` offer tells the student "Blocked: accepted a PPO offer." | |

## Part 2 — automated QA tests (re-run and verified by me): `S7EventsTest`, `S8ExportsTest`, `S9DashboardsTest`, `S10CompanyTest`, `SAAuditCoverageTest` → 3 failed, 1 incomplete, 28 passed (781 assertions)
| ID | Result | Sev | Evidence (test) | Notes |
|---|---|---|---|---|
| T7.1 | PASS | — | `test_T7_1_only_title_starts_at_and_event_type_are_required_full_create_and_edit` | edit is PUT (PATCH → 405) |
| T7.1x | FAIL | S4 | `test_T7_1x_starts_at_with_ist_offset_is_stored_as_the_same_instant`: `+05:30` starts_at stored as wall-clock (5h30 late) | = CR-17 (`AdminEventController.php:53` + update); UI sends Z |
| T7.2 / T7.3 / T7.4 | PASS | — | `test_T7_2a/2b/2c_*` (all = active accounts, suspended excluded; branches; posting_applicants excludes withdrawn), `test_T7_3_…` (no RSVP, drafts invisible), `test_T7_4_…` | `all` includes students enrolled in no cycle (recorded) |
| T8.1a / T8.1b | PASS | — | `test_T8_1a_…` (link = export+30d, opens logged out, tamper sig/expiry/id → 403, day 29 ok, day 31 403), `test_T8_1b_…` (profile/answers/round statuses/flags; `= + - @` stored as text) | |
| T8.2a / T8.2b | PASS | — | `test_T8_2a_…` (own posting pre-deadline 200; foreign 404), `test_T8_2b_…` (exact allowed field set; phone/personal email only with share toggle; no withdrawn, no drafts) | |
| T8.3 | PARTIAL | S4 | `test_T8_3a_…` PASS (nothing removed/renamed, only 3 approved columns added); FAIL `test_T8_3b_phase1_csv_original_columns_keep_their_positions`: new columns inserted before `graduating_batch` → 21 JNF / 10 INF original columns shift right | `AdminFormReviewController.php:265-267, 447-449`; breaks positional consumers |
| T8.4 | PASS | — | `test_T8_4_…` (207 students across the 200-row chunk boundary) | |
| T9.1 | PASS + NEEDS-OWNER-DECISION | — | `test_T9_1_…` | overview has no cross-cycle programme/branch/batch totals; "companies completed vs ongoing" counts postings, not companies (`AdminAnalyticsController.php:88-89`) |
| T9.1b | PASS | — | independent computation: placed 4/10 = 40.0 %, even-count median 1,350,000, avg 1,425,000, hi 2,000,000, lo 1,000,000; USD excluded from money stats; 0-offer cycle → 0 %, null medians | CTC stats include `ppo_offered` offers whose students are not "placed" (owner to decide) |
| T9.1c | PASS | — | `AdminDashboardController.php` byte-identical to df7034b; keys match | |
| T9.2 | PASS (recorded) | — | denominator = every enrolment row incl. suspended accounts/enrolments | |
| T9.3a / T9.3b / T9.3c | PASS | — | dashboard, nudge (eligible ∧ not applied ∧ open, by deadline; excludes blocked/withdrawn/expired/cancelled), calendar IST month bucketing both edges | |
| T9.3cx | FAIL | S4 | `test_T9_3cx_…` | = CR-17 (deadline) |
| T9.4 | PASS (not built) | — | `test_T9_4_season_report_is_not_built` | |
| T10.1 / T10.2 / T10.3 | PASS | — | `test_T10_1_…`, `test_T10_2_idor_sweep_…` (6 routes, 7 foreign-id attempts → 404, no data), `test_T10_3_…` | |
| TA.1 | PASS (Phase 2) / NEEDS-OWNER-DECISION (Phase 1) | S3 | `qa/evidence/audit_coverage.md`: 57 admin mutating routes; **Phase 2 40/40 audited** (ADMIN_A, action, ip, actor, before/after); **Phase 1 0/17** (manage-admins POST/DELETE, programme-branches ×3, jnfs/infs status/remarks/notes/form-data ×8, PUT companies, policy-documents ×3) | owner's closing rule "every admin mutation audited"; `form-data` edits floated forms students see; admin hard-delete leaves no record |
| TA.2 / TA.3 / TA.4 | PASS | — | `test_TA_2_…` (two admins distinguished; filters admin/action/IST date), `test_TA_3_…` (no edit/delete path; actor snapshot survives admin delete), `test_TA_4_…` (admin can do everything a company proposes + override records) | AuditLog model has no immutability guard (S5) |

## Part 2 §11 — UI (browser, fresh tab, scrollWidth vs innerWidth + console errors)
| ID | Result | Sev | Evidence | Notes |
|---|---|---|---|---|
| T11.1 | PASS | — | all student pages exist and load: /student, /student/postings, /student/postings/4, /student/applications, /student/resumes, /student/events, /student/calendar, /student/notifications, /student/profile; login at /auth/login/student | |
| T11.2 | PASS | — | permanent drawer (`.MuiDrawer-docked`) at ≥768, temporary drawer at 390; active item highlight; unread badge (2); UI sign-out revoked token id 25 (`personal_access_tokens` row gone) | |
| T11.3 | PARTIAL | S4 | no horizontal scroll at 390 on 9 student + 15 admin + 2 company pages; 768/1440 clean; **`/student/postings` at 1024×768 scrollWidth 1177** — the filter bar (search + 4 selects in one row beside the permanent drawer) pushes the "Status" select off-screen (`qa/screenshots/T11_3-student-postings-1024-overflow.jpg`) | |
| T11.4 | NEEDS-OWNER-DECISION | S4 | at 1024/1280/1440 the admin AppBar shows only the title + hamburger (all nav in the drawer); desktop row only from 1600 px (D85). Phase 1 showed the row from 1200 px (lg). No wrapping anywhere. | D34 (xl) was owner-approved; D85 (1600) was the developer's own call |
| T11.5 | PASS | — | `grep justify` in `app/globals.css` / `lib/theme.ts` → none (D35) | |
| T11.7 | PASS | — | student job detail renders the JNF preview; no recruiter email/phone/signatory text in the DOM (`hasContact:false`) or payload | |
| T11.9 | PASS (fresh tab) | — | `read_console_messages onlyErrors` after every admin/student/company page → "No console logs." Old tab-1 history contained a stale HMR `ReferenceError: eligibilityNumbersValid` from 01:29 last night (file unchanged since; wizard works) | |
| T1.2 (UI) | PASS + S4 (Phase 1) | S4 | lower-case roll "26qa0001" logs in; admin email in the student form → "Invalid roll number or password."; student credentials in the recruiter form → rejected but with generic "Invalid email or password." — the page checks `result.error` for `admin_only/recruiter_only/student_only`, next-auth v5 puts the code in `result.code`, so the specific cross-portal messages never show (same at df7034b:141); recruiter_only copy would say "This account is an administrator account" for a student | backend issues a Sanctum token before the frontend rejects the role → orphan token per failed cross-portal login (S5) |
| Part 5 #11 (live) | PASS | — | session token not in localStorage/sessionStorage (only `cdc_read_guidelines_jnf_3`) | |
| T10 UI | PASS | — | /company/postings lists the 3 QA drives; /company/postings/4 shows "4 applicant(s)", round chips, no unverified/placed flags, no phone (share off); company nav has "Drives" | |

## Part 4 — end-to-end scenarios (live MySQL, browser for the key steps; screenshots in qa/screenshots/)
| ID | Result | Sev | Evidence | Notes |
|---|---|---|---|---|
| E2E-1 | PARTIAL | S2 (CR-01) | cycle via API; 16 students by **bulk import**; enrolment; set password via the E1 token; resumes (API); **company JNF via the real wizard (browser)**; admin accept (API); **float via Float dialog (browser)** with 4 questions → E2 to exactly 7 eligible; 6 applied (2+ with pending resumes, flagged), 1 withdrew, 1 changed resume; **resume approved in the admin queue (browser)** → unverified count 6→5; **close applications (browser)**; **company proposed OLT shortlist (browser)** → E10 to 4 admins; **admin approved as draft (browser)** → student/company raw JSON show no draft; **publish with "mark everyone else" (browser)** → 4 shortlisted + 2 regret mails; **GD attendance (browser)**; **GD results CSV upload (browser)** — unknown roll reported, duplicate counted twice (T4.2b); GD published 2 sel / 1 wl / 1 rej; **Waitlist → "Move to Technical Interview" (browser) reproduced CR-01: the Results console then listed ONLY the moved waitlistee, pre-ticked for a Full-Time offer + block, while the two students who actually cleared GD were absent** (`CR-01-live-waitlist-move-pre-ticked-offer.jpg`); recovered by entering TI results (API); **final publish (browser)**: 2 FT offers @ ₹18,00,000 prefilled, 2 `all` blocks, 1 regret, 1 placed-elsewhere flag, posting completed, E5 mails | analytics/export checked in tests (S8/S9) |
| E2E-2 | PARTIAL | S2 (F-CYCLE-MIX) | `qa/evidence/e2e2_ppo.txt`: INF finals: 26QA0012 `intern` (internships_only), 26QA0013 `ppo_offered` (no block), 26QA0015 `intern_ppo` (all). Fresh INF (cycle 4) + fresh JNF (cycle 3): 0012 INF ✗ "Blocked from internships…", JNF ✓; 0013 ✓/✓; **0015 INF ✗ but FT JNF ✓** (PPO accepted yet still eligible for full-time drives); 0001 (FT offer in cycle 3) JNF ✗, INF ✓; T5.3 per-cycle scope live ✓ | the prompt's "second INF and a JNF floated into a fulltime cycle" is impossible (API refuses mixed types) |
| E2E-3 | PASS | — | 0004 FT offer on Alpha → flag on Beta posting 7 (admin applicants: 🚩 Placed elsewhere); **Remove from process (browser)**, notify ticked by default → E9 "Candidate withdrawn from your process" + replacement invitation to hr@beta, audit `application.remove_placed_elsewhere` by ADMIN_A; CO_B `replacement_request` (26QA0005) → approved as draft → published → 0005 "Shortlisted: Beta Labs QA", row `is_addendum=1` "From company replacement request", company "Your replacement request was accepted" | dialog copy "Totalbacklogis placed elsewhere" (missing space, S5) |
| E2E-4 | PASS | S4 (obs.) | re-add without confirm → 422; ADMIN_B with confirm+remark → draft + company notified; audit UI shows the full story with admin names (QA Admin A/B) and readable before→after JSON (`E2E-4-audit-story.jpg`, `E2E-4-audit-readd-before-after.jpg`) | re-add does not check that 26QA0004 already holds an FT offer + `all` block (S4) |
| E2E-5 | PASS (partial UI) | — | 390×844: roll-number login (lower-case), dashboard, job board, ineligible reasons, posting detail with answers + unverified banner, calendar, applications trail — no horizontal scroll, no console errors (`T11-student-dashboard-390.jpg`) | apply was done via API for the actors; the apply panel was inspected, not clicked |
| T5.3 (live) | PASS | — | 26QA0001 with an FT `all` block in cycle 3 → INF posting 5 (cycle 4) `{"eligible":true}` | |
| T5.1 / T5.4 (live) | PASS | — | JNF console: Full-Time, CTC 1800000 prefilled, block "Everything in this cycle"; INF prepare: intern, stipend 60000, internships_only | |
| T4.3 (live) | PASS | — | after the UI publish: 4 "Shortlisted: Alpha Systems QA — Aptitude Test" + 2 "Update on your application" (0013, 0014); withdrawn 0015 got nothing | |
| (copy) | — | S5 | publish dialog "Mark everyone else in this round's pool (6) as not selected" shows the whole pool size, not the 2 who would be marked | |

## Part 7 — performance at scale (live MySQL; fixture: `qa/scale_3000.xlsx` + `qa/seed_scale.php`; evidence `qa/evidence/perf.txt`)
Fixture: 3000-row xlsx through the real importer (2,756 valid created; 244 rows correctly rejected "Total backlogs cannot be fewer than ongoing backlogs" — generator noise), enrolled in "SCALE FT 2026-27" (cycle 5), 32 floated postings (30 via API floats, 2 "wide"), 6,050 applications (DB insert), 500 applicants on posting 40.
| ID | Result | Measured | Target | Notes |
|---|---|---|---|---|
| P1 | PASS | dry run **5.16 s**, real run **18.13 s**, 0 PHP memory/time errors; 2,756 `student.create` audit rows; 2,756 queued invitations | dry < 30 s | worker drained invitations at ≈3.4/s (≈13 min per 2,756; 0 failed jobs) |
| P2 | PASS | wide float to **2,756 eligible** in queued mode: HTTP **0.055 s** (201); 30 normal floats 0.028–0.057 s each | < 3 s | E2 = 1 job → BCC batches of 100 |
| P3 | PASS | same in **sync** mode: **1.22 s**; 2,756 `email_logs` status `sent` (log mailer ≈28 BCC messages) | record | real SMTP ≈ 28 sends — acceptable thanks to D89; Settings page still warns sync is for small sends |
| P4 | PASS | `GET /student/postings` (32 postings): 33–38 ms; **20 queries** (vs 25 for a 3-posting student) | < 500 ms, constant | dashboard 37 ms / 37 queries (per-item lookups, CR-12) |
| P5 | PASS | admin students index p1 17 ms (12 queries), p58 35 ms, search "Sharma" 21 ms | < 500 ms | |
| P6 | PASS | pipeline API posting 40 (500 applicants × 4 rounds) **83–85 ms, 31 queries** (posting 4 with 6 applicants: 30 queries — constant); UI grid of 500 rows rendered in ≈0.5 s incl. fetch, scroll frame 3 ms | < 2 s | |
| P7 | PASS | 500-row applicant export **0.54 s**, 86 KB, opens in openpyxl: 501 rows × 33 cols, last column hyperlink `…/api/resumes/signed/138?expires=…&signature=…` | < 15 s | |
| P8 | PASS | `/admin/dashboard/cycle/5` 143–146 ms (14 queries); overview 21 ms | < 2 s | |
| P9 | PASS | enrol 2,756 roll numbers **1.10 s** (D28 batching) | < 10 s | |

## Part 8 — Phase 1 regression (API script `qa/phase1_regression.sh` → `qa/evidence/part8_phase1_api.txt`; browser; baseline worktree df7034b on :3001)
| # | Result | Evidence |
|---|---|---|
| 1 Registration + email verification + logo | PASS | verification-link (iana.org passes `email:dns`; example.com/.org rejected — null MX) → logged link → verify 200 → status `verified:true` → multipart register with PNG logo 201 |
| 2 Company login, dashboard, profile edit, logo replace | PASS | login ok; dashboard 200; PUT profile 200 (full required set; partial → 422 lists head/poc fields, Phase 1 rule); logo replace 200 |
| 3 JNF wizard (batch dialog, autosave, 7 tabs, policy read gate, preview, submit) | PASS (browser) | full JNF wizard run (setup-01); autosave "Draft saved"; policy PDFs through the secured proxy with read-to-end gate; INF wizard smoke: 7 tabs incl. Stipend (HRA + PPO section, B.Tech row after selecting CSE) |
| 4 Duplicate / delete draft / request edit access | PASS | duplicate → new draft with batch "" and declarations false; delete draft 200; submit; request edit access 200, again 409 |
| 5 Admin review flows | PASS | queues default/all/accepted 200; reject without remarks 422; grant edit access 200; edit own latest remark 200; company resubmit 200; admin form-data edit 200 + "JNF Updated by Admin: …" mail logged to company; accept 200; CSV 200; mark draft for review 200 (company still sees `draft`); draft note 200; unmark 200 |
| 6 Companies, branches, policy docs, manage-admins, alumni, notifications | PASS | companies list/detail 200; custom branch create 201 / delete 200; policy doc (link) create/delete 200; manage-admins normal admin 403 / super 200; alumni list 200; notifications 200 |
| 7 Password reset (company) | PASS | forgot-password 200 → emailed token → reset 200 → login with new password 200 (all old tokens revoked — Phase 1 behaviour) |
| 8 Public `/` and `/alumni` | PASS | alumni POST 201; baseline vs current: `/` same height (3341 px), only change `text-align: justify → start` (approved D35); `/auth/login/recruiter`, `/alumni`, `/company/register`: identical visible text + height |
| Baseline side-by-side | PARTIAL | worktree df7034b served on :3001 (webpack; Turbopack refuses the symlinked node_modules); authenticated pages could not load data (backend CORS allows only :3000 — environment limitation). Layout of `/admin` identical except the nav: baseline shows the full Phase 1 nav row at 1440 px, current shows only the hamburger (D85, T11.4) — `P8-baseline-df7034b-admin-1440.jpg` vs `P8-current-admin-1440.jpg`. Worktree removed afterwards. |

## Final spot checks
| ID | Result | Sev | Evidence |
|---|---|---|---|
| T0.2 | PASS | — | `tsconfig.json:5 "allowJs": true`; reviewer: no `.ts/.tsx` added since df7034b, only permitted ones modified |
| T0.4f | PASS | — | no session → 401; with session: `169.254.169.254` 400, `example.com/x.pdf` 400, non-PDF own-API URL 400, signed resume URL 200 |
| T2.3 | PARTIAL | S4 | unverified flag shown in admin overview/applicants/pipeline + export; **not** on the results announcement console (`results/page.jsx`, `AdminResultController` payload has no `used_unverified_resume`) |
| T11.6 | PARTIAL | S5 | 11 hard-coded hex colours in Phase 2 JSX: `app/admin/analytics/page.jsx` (5 chart colours), `components/shared/monthcalendar.jsx` (3), `components/student/studentshell.jsx` (3) |
| T11.10 | PARTIAL | S5 | student login tab order: home link → Roll Number (labelled) → Password (labelled) → show/hide icon button (**no accessible name**) → Forgot password → Sign In → Back to Home |
| T6.14 | PASS (noted) | S5 | `app/admin/notifications/page.tsx` unchanged (Phase 1 title sets) → Phase 2 titles (e.g. "Alpha Systems QA proposed a shortlist") land in "Admin Actions" |
| Full suite | info | — | `php artisan test` → 20 failed, 1 incomplete, 262 passed (4780 assertions) = baseline 116 + 167 QA tests; the 20 failures are the findings; `--exclude-group=qa` → 116 passed |
