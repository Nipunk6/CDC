# SUPERSET PARITY PROGRESS
Plan: SUPERSET_PARITY_MASTER_PROMPT.md (repo root). Analyses: superset_video_analysis/01…09. Decisions in CDC/PHASE2_DECISIONS.md: D105 renames, D106 product decisions, D107 S0, D108 S1, D109 S2, D110 S3, D111 S4, D112 S6, D113 S8.5 Reports, D114 S8.4 Student Categories, D115 S7, D116 S8.1–S8.3, D117 S5, D118 audit coverage.

## CURRENT STATE
- Working on: VERIFICATION FIXES (2026-10-07) — see the section below. Three builders work in parallel (surveys/notices; pipeline/offers/quick view; students/import/renames); the lead does H1, M2, M4 and the remaining low items.
- Last completed: all milestones S0–S8 (2026-10-06); independent verification on 2026-10-07 found 1 missing feature, 6 medium and ~25 low issues (CDC/qa/PARITY_VERIFICATION_REPORT.md).
- Half-done: nothing.
- Pending commands: none on the dev DB. Migrations `2026_10_06_000021`–`000035` are applied to the local MySQL `iitism_placement` (no reseed; local data kept). `migrate:fresh --seed` + `Phase2DemoSeeder` were proven on a scratch database `iitism_placement_verify` (120 students, 1 Excel Template, 1 notice, 1 survey, 1 draft placement, 0 mails).
- How to run tests: `php -d memory_limit=2G vendor/bin/phpunit` from CDC/backend (artisan test runs out of memory at 128M). The suite rewrites CDC/qa/evidence/*.md and CDC/security/evidence/authorization_matrix.md; run `git restore CDC/qa/evidence CDC/security/evidence` afterwards.
- Test baseline (now 14 after the fix round: T4_2b passes): the 15 failures that existed before this work (PermissionMatrix + AuthorizationMatrix every-route, T0_4b, T1_1c, T1_4a, T3_6c, T4_2b, T5_8_x, T6_13, T8_3b, A3_1, A3_2, A3_4, A3_9, T4_8). Two QA guards were updated on purpose: T9_4 (no season report: now matches "season" only, D111) and T6_11 (no reminders: allows only the S6.2 scheduled-opening line, D117).
- Scheduler: "Schedule For Later" needs `php artisan schedule:work` locally (only with MAIL_MAILER=log — the backend .env uses SMTP) and a cron `* * * * * php artisan schedule:run` in production.
- Local demo admin: admin@cdc-demo.test / Admin@2026 (in the seeder docblock). For an API check, student 23JE0104 on the local DB was given the demo password Student@2026.
- Nothing is committed (owner commits).
- NEXT ACTION: Owner review and commit of the final fixes (L15, M2 clean-up rule, L1 labels). All parity items are done.

## BLOCKED / QUESTIONS FOR OWNER
- L15: ANSWERED 2026-10-07 — exclude Revoked (D126). Original note: BLOCKED on a probe conflict: the recommended "Exclude" option contradicts the verifiers' probe `tests/Feature/ParityVerify/S5S6VerifyTest::test_s5_bulk_resend_never_mails_accepted_students_in_any_mode` (line 263 expects "all pending" to send 2, one of them a Revoked student) and `StudentInvitationTest::test_bulk_resend_never_mails_accepted_students`. I built "Exclude", saw both fail, and reverted it, because a probe may not be changed without the owner. If the owner chooses Exclude, the probe's expected count at line 263 changes from 2 to 1 (with the owner's OK); if Include, only the confirm dialog gains the revoked count. Current code = the old behaviour (Include, without the count).
- None blocking. Items for the owner's eye (not questions): survey Type values General / PPO Consent / Feedback are our wording (the video never showed Superset's); the student pages (Notices, Surveys) were checked through the API and by the S7 builder at 375/1024, not by eye with a student login in the shared browser (the browser holds the owner's admin session, which was not signed out).

## MILESTONE CHECKLIST
- [x] Step 0: owner answers recorded (D105, D106)
- [x] S0 Approved renames (D107)
- [x] S1 Stage shortlist workspace (D108) — StageShortlistTest (6)
- [x] S2 Shortlist for Offer: edit/revoke offers, Upload CTCs (D109) — OfferEditTest (7)
- [x] S3 Excel Templates and downloads (D110) — ExportTemplateTest (5)
- [x] S4 Students directory and student page (D111) — StudentDirectoryTest (25), StudentPageTest (13)
- [x] S5 Student invitations (D117) — StudentInvitationTest (15)
- [x] S6 Job profile management incl. Add New Job (D112) — JobProfileManagementTest (7), AdminCreateJobTest (7)
- [x] S7 Notices, stage emails, Surveys (D115) — NoticeTest (9), SurveyTest (14)
- [x] S8 Admin hub (D116), Users directory (D116), Placements extras + Draft (D116), Student Categories (D114, StudentCategoryTest 4), Reports (D113, ReportsTest 2) — AdminHubTest (10)
- S9 Staff roles: DEFERRED by the owner (do not build)
- Definition of done: full suite at baseline (478 tests, only the 15 pre-existing failures; TA_1 green, D118), migrate:fresh --seed on MySQL (scratch DB), `npm run lint` 0 errors (80 warnings, down from 82), `npx tsc --noEmit` clean, `npm run build` passes; browser checks at 375/1024/1280/1440 across the milestones (lead + builders).

## VERIFICATION FIXES (2026-10-07)
Source: owner FIX PROMPT; evidence CDC/qa/PARITY_VERIFICATION_REPORT.md + CDC/qa/parity_verify/*.md; probes CDC/backend/tests/Feature/ParityVerify (128; 15 failing at start).
- [x] H1 Reconcile Ineligible Students (D119) — `app/Services/ReconcileService.php`, `app/Http/Controllers/AdminReconcileController.php`, routes `…/rounds/{r}/reconcile[/export]`, `components/admin/posting/reconciledialog.jsx`, button on the stage page; tests `ReconcileIneligibleTest` (5), probe `S1S2VerifyTest::test_reconcile_ineligible_students_action_exists`, TA_1 plan added; live check on QA data (posting 7 / stage 19) then restored.
- [x] M1 Survey edits lose answers (D125a) — `AdminSurveyController::syncQuestions`, `SurveyAnswerService` stale-key 422, builder Audience save; probe `S7VerifyTest::test_P26…`, `VerifyFixS7Test`; browser: builder leave guard + delete confirm checked at 1280.
- [x] M2 Company can't set Allowed Student Categories (D120) — `JobPosting::ADMIN_ONLY_KEYS/withoutAdminOnlyKeys`, `AdminPostingController::snapshot`, `PostingEligibilityService::apply`, company JNF/INF store/update/autosave; migration `2026_10_07_000037`; probes S0S8 ×2; `VerifyFixLeadTest` (company POST/PUT/autosave ignored; migration rule).
- [x] M3 Older notices/surveys disappear (D125b) — `AudienceService::whereIncludes`, `Notice/Survey::scopeVisibleTo`, paginated student lists; probe `S7VerifyTest::test_P29…`, `VerifyFixS7Test` (350 other notices + 1 old one).
- [x] M4 Scheduled label (D121a) — `JobPosting::isScheduled()`, `lib/format.js` postingStatusLabel; probe `S5S6VerifyTest::test_s6_cancelled_scheduled_job_profile_never_opens_or_mails`.
- [x] M5 Enrolled list download honours filters + filters in URL (D123a) — `StudentDirectoryFilters::applyToEnrollments`, `AdminPlacementCycleController::exportStudents`, placement page; `VerifyFixStudentListsTest`.
- [x] M6 Quick-view drawer in Progress Grid, Applicants, Eligible, Shortlist for Offer (D122h).
- [x] L1–L6 wording (D123d) — `VerifyFixStudentListsTest` (L5 messages).
- [x] L7 offer edit never brings back hand-lifted blocks unless `reapply_blocking: true` (D122a) — probe `test_type_change_ignores_hand_lifted_blocks…`, `VerifyFixOffersShortlistTest`.
- [x] L8 one "M candidates" definition (D122b) — probe `test_shortlist_counter_matches_the_progress_grid_pool_count`; pipeline `outside_pool_count`.
- [x] L9 roll+email duplicate merged and reported (D122c) — probe `test_same_student_by_roll_and_email…`.
- [x] L10 case-insensitive email in code (D122d) — probe `test_email_identifier_matches_a_mixed_case…`.
- [x] L11 custom shortlist footer (D122e) — probe `test_download_current_shortlist_with_a_custom_template…`.
- [x] L12 [addendum] in template stage columns (D122f) — `VerifyFixOffersShortlistTest`.
- [x] L13 Placement Matrix placing offers only (D121e) — `ReportsTest`.
- [x] L14 hub tabs render Branch Manager + Excel Templates; drawer duplicate removed; old URL redirects (D121f).
- [x] L15 Resend to all pending excludes Revoked (owner: exclude, D126) — probe S5S6 expectation changed with the owner's OK; `StudentInvitationTest::test_resend_all_pending_excludes_revoked_but_explicit_selection_unrevokes`.
- [x] Re-check follow-ups (2026-10-07, SUPERSET_PARITY_FINAL_FIX_PROMPT.md): M2 clean-up rule corrected in 000037 (D127; probes `RecheckHighMediumTest::test_m2_cleanup_*`); L1 change-summary labels (D128; probe `RecheckLowTest::test_l1_*`).
- [x] L16 apply to a scheduled job profile → 404 (D121b) — probe `test_s6_apply_to_a_scheduled_job_profile_is_404_like_its_detail`.
- [x] L17 shared status label on student card + company pages (D121c).
- [x] L18 import "Current Course Name" (D123c) — `VerifyFixStudentListsTest`.
- [x] L19 proposal mail in Communication Log (D121d) — probe `test_s6_every_job_profile_mail_row_carries_job_posting_id`.
- [x] L20–L24 surveys/notices (D125c–g) — migration `2026_10_07_000036` (single_key), `App\Support\UploadType`, `DeleteBroadcastAttachment` job, Non-responders sheet; `VerifyFixS7Test`.
- [x] L25 loading states (D121g) — Job Profiles, Placements.
- [x] L26 unsaved-change guards — template editor (D121h); survey builder (D125h), checked in the browser.
- [x] L27 MUI confirm on survey Delete / notice Delete / stage email send (D125i), checked in the browser.
- [x] L28 literal % and _ (D123b) — probe `test_p10_search_wildcards_are_literal`, `VerifyFixStudentListsTest`.
- [x] L29 unused Image imports removed (D121i).
- [x] Notes recorded: single stage-email button with audience choice (D124); "Attached Resume" admin-only (D122g).
- [x] DoD (except L15): 128/128 probes; full suite 645 tests, 14 failures — all from the 15-test baseline (T4_2b now passes thanks to L9); scratch-DB `migrate:fresh --seed` + `Phase2DemoSeeder` OK (62 migrations); `npm run lint` 0 errors, 76 warnings (down from 80, all in Phase 1 .tsx files); `npx tsc --noEmit` clean; `npm run build` OK; browser checks H1 (1280), M5 (1440), M6 (1024), L14 (375/1280), L25, L26, L27 (1280); no console errors, no local backend log errors; CDC_PORTAL_CONTEXT.md updated. Student Notices/Surveys pages verified through tests/API, not by eye (the shared browser holds the owner's admin session).

## CHANGELOG
- 2026-10-06: Created this file; owner answered Part B (D105/D106).
- 2026-10-06: S0 renames (D107); S1 (D108); S2 (D109); S3 (D110).
- 2026-10-06: S4 (D111) and S7 (D115) by builders; S6 (D112, Add New Job by a builder); S8.5 Reports (D113); S8.4 Student Categories (D114); S8.1–S8.3 by a builder (D116); S5 by a builder (D117).
- 2026-10-06: Final pass: full backend suite, scratch-DB migrate:fresh --seed, lint, tsc, build.
- 2026-10-06: TA_1 audit-coverage plans added; Add New Job autosave now audited (D118). Full suite: 15 pre-existing failures only.
- 2026-10-07: Verification fixes H1, M1–M6, L1–L14, L16–L29 done (D119–D125) by the lead and three builders; 128/128 probes; suite at baseline; L15 waits for the owner.
