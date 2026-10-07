# Re-check of LOW fixes L1–L29 (except L15): 2026-10-07

Independent verifier. No application code or existing tests were changed. The only new file is the probe file `CDC/backend/tests/Feature/ParityVerify/RecheckLowTest.php` (8 tests: 7 pass, 1 fails on purpose as a finding). Evidence files were restored after every phpunit run.

Paths: BE = `CDC/backend/`, FE = `CDC/frontend/`.

## Verdict table

| Item | Verdict | Evidence | Note |
|---|---|---|---|
| L1 Class X / XII Percentage | **PARTIAL** | FE `studentformdialog.jsx:171-172`; `app/admin/jnfs/[id]/page.tsx:683-684`; `app/admin/infs/[id]/page.tsx:651-652`; `formpreview.tsx:404,408,954,958`; `jnfformpro.tsx:1007,1017`; `infformpro.tsx:996,1006` (labels only; `minTenthPercent` keys unchanged). Export `'10th %'`/`'12th %'` still in place at `ExportService.php:52-53,165`. | Every screen is renamed. One leftover remains: the company-facing "form edited by CDC" change summary (email `FormEditedByAdminMail`, in-app notification, form-history remark and `changed_fields` in the response) still says "Minimum 10th %" / "Minimum 12th %" (`AdminFormReviewController.php:1304-1305, 1323-1324`). Probe `test_l1_form_edit_change_summary_sent_to_the_company_uses_the_renamed_labels` **fails**. Those labels are also the keys of the `form.edit` audit before/after (data, not an action name). |
| L2 Passout Batch | FIXED | `app/company/page.tsx:337,469`; `eligibilitygrid.tsx:554,558`; `graduatingbatchdialog.tsx:89,134,139`; `formpreview.tsx:386,936` | No visible "Graduating …" text is left. The remaining hits are identifiers and comments (`graduatingBatches`). |
| L3 Verified chip | FIXED | `app/student/resumes/page.jsx:187` (`approved` → "Verified") | `applypanel.jsx:36` already said "verified". |
| L4 Logout | FIXED | `studentshell.jsx:212-213` | No "Sign out"/"Sign Out" anywhere in FE. |
| L5 "opened for applications" | FIXED | `CompanyJnfController.php` / `CompanyInfController.php` messages; test `VerifyFixStudentListsTest::test_l5_…` asserts the new text | No test asserts the old text. No "floated" wording is left in BE messages or blade files. |
| L6 login copy | FIXED | `app/auth/login/[type]/page.tsx:117,127,336` | The remaining "drive" hits in FE are code comments only. |
| Export headers | UNCHANGED (OK) | Every header token in `git show HEAD:…/ExportService.php` is still present in the current file (comm diff empty); no header line was removed. Probes `S0S8::test_cycle_student_export_headers_are_the_pre_parity_headers` and `S3S4::test_p07_default_downloads_are_identical_to_head` pass. | The `AdminStudentController::FIELD_LABELS` rename ('10th %' → 'Class X Percentage') is the E8 mail summary, not an export. |
| Routes | OK | `git diff HEAD -- routes/api.php`: 125 insertions, 0 deletions | |
| L7 offer edit / hand-lifted blocks | FIXED (with a documented interpretation) | `AdminOfferController.php:206-216` (`blockingMode`); `OfferEditService.php:176-243` (`plan`, `handLiftedCycles`); dialog `offerdialogs.jsx:61-77` sends `reapply_blocking:true` only for "Bring back …" | The default still applies the new type's rules (needed by the S1S2 probes for FT→PPO offered), but a hand-lifted block never returns. New probe `test_l7_hand_lifted_blocks_survive_a_chain_of_scope_changes_with_the_api_default` (all → internships_only → all, then hand-lift the re-created block too, legacy `apply_blocking:true`, `reapply_blocking:null`) **passes**. Only `reapply_blocking:true` restores. |
| L8 "M candidates" | FIXED | `AdminShortlistController.php:180-196` (`candidates` = `pool`, `outside_pool`); `AdminPipelineController.php:58-61` (`outside_pool_count`); FE `rounds/[roundId]/page.jsx:283`, `pipelinetab.jsx:292` | Counts are taken before the search filter, so they don't change while searching. |
| L9 duplicate roll/email | FIXED | `AdminPipelineController.php:133-151` (`$seen` by application id) | New probe `test_l9_one_student_entered_four_ways…` (lower-case roll, roll, capitalised and padded institute email, personal email) passes: written 1, 3 reported, first entry wins. |
| L10 case-insensitive email | FIXED | `PipelineService.php:79-90` (`LOWER(TRIM(col))` vs lower-cased input) | `strtolower` is ASCII-only, which is fine for emails. |
| L11 template shortlist footer | FIXED | `TemplateExports.php:106-134` (`footer: true`, title row + "(Template: …)"); `ExportService.php:286,400-401` | |
| L12 [addendum] | FIXED | `ExportFieldCatalogue.php:143-148` (before " (draft)", as in `ExportService.php:107-111`) | |
| L13 Placement Matrix | FIXED | `ReportService.php:29,112,124` (`NOT_PLACING = ['ppo_offered']`) | |
| L14 Admin hub | FIXED | `app/admin/settings/page.jsx:29-30,40-41,352-355` render `BranchManager` / `ExcelTemplateLibrary`; `app/admin/programme-branches/page.tsx` redirects to `?tab=branch-manager`; `adminshell.tsx` has no Branch Manager entry | Excel Templates still has its own drawer entry (`adminshell.tsx:69`). The spec did not ask for that to be removed. |
| L16 scheduled apply → 404 | FIXED | `StudentApplicationController.php:312-319` | Matches `released()` used by the detail page (`StudentPostingController.php:142`). The original probe passes, and so does new probe `test_l16_apply_and_detail_agree_for_a_scheduled_job_profile`. |
| L17 shared status helper | FIXED | `postingcard.jsx:65`, `app/company/postings/[id]/page.jsx:159`, `app/company/postings/page.jsx:60` use `postingStatusLabel(posting)`; `lib/format.js:82-95`; BE `PostingPresenter.php:60-62`, `CompanyPipelineController.php:242-244` | New probe `test_l17_student_card_carries_any_stage_published…` passes (false → true after the first publish; no count is exposed). |
| L18 Current Course Name | FIXED | `StudentAccountService.php:100,312-327,344-395` → `ProgrammeCatalogue::resolve`; `AdminStudentController.php:392` | The error reason names the expected "Programme - Branch" form. Covered by `VerifyFixStudentListsTest` (2 tests). |
| L19 proposal mail log | FIXED | `StakeholderNotifier.php:51-63` (`$context`); `CompanyPipelineController.php:189-195` (`job_posting_id`, `kind=shortlist_proposal`); FE `communicationtab.jsx:20` label | Probe `S5S6::test_s6_every_job_profile_mail_row_carries_job_posting_id` passes. |
| L20 per-field upload errors | FIXED | FE `lib/studentapi.js:66-74` keeps `errors`/`payload`; `app/student/surveys/[id]/page.jsx:86-101` maps `answers.<id>`; BE `SurveyAnswerService.php:59-86` keys file errors as `answers.<id>` | |
| L21 duplicate first submission | FIXED | Migration `2026_10_07_000036` (nullable `single_key` + unique index, backfill of the first response only); `StudentSurveyController.php:84-108` (transaction + profile row lock + re-read of `allow_multiple`; `UniqueConstraintViolationException` → 409, stored files pruned) | Covered by `VerifyFixS7Test` (a race simulated through the `creating` hook, the backfill migration, and multi-submit storing NULL). |
| L22 Non-responders sheet | FIXED | `AdminSurveyController.php:330-334,424-431`; `ExportService.php:526+` | Audit records the count. |
| L23 attachments vs queued mail | FIXED | Notice per-send copy `AdminNoticeController.php:203-226`; stage email `AdminStageMessageController.php:62-87` (`deleteAttachmentAfter: true`); `BroadcastService.php:27-60`; `Jobs/DeleteBroadcastAttachment.php` (re-checks `queued` email_logs every 300 s, `retryUntil` 3 days, then keeps the file) | Two edge cases, Info only: if the send throws halfway, the file is left behind; files from sends before this fix are not cleaned up (documented). |
| L24 real type vs extension | FIXED | `app/Support/UploadType.php`; used at `AdminNoticeController.php:144`, `AdminStageMessageController.php:52`, `SurveyAnswerService.php:262` | Posting documents use `mimes:pdf` only and are stored as `.pdf`, so the content is a PDF; safe. |
| L25 loading states | FIXED | `app/admin/postings/page.jsx:78,128-129`; `placement-cycles/page.jsx:338`; surveys (Skeleton), notices, categories, invitations, reports, users, templates and student notices/surveys all show a progress bar before the "No …" or count text | |
| L26 unsaved guards | FIXED | Survey builder `app/admin/surveys/[id]/page.jsx:270-296,788` (beforeunload + capture-phase link click → "Leave without saving?"); template editor `app/admin/excel-templates/[id]/page.jsx:36,84-116` (pending payload flushed with `keepalive` on unmount, beforeunload while pending, "Saving…" for dirty and saving) | Info: closing the tab while a save is already in flight (not pending) gives no warning. The browser Back button is not intercepted (documented in D125h). |
| L27 MUI confirm | FIXED (as scoped) | `components/admin/engagement/confirmdialog.jsx`, used by `app/admin/surveys/page.jsx`, `surveys/[id]/page.jsx`, `notices/page.jsx`, `stageemaildialog.jsx`; none of these call `window.confirm` | Inconsistency remains (Low/Info): other new parity pages still use `window.confirm`, e.g. `excel-templates/[id]/page.jsx:149`, `student-categories/page.jsx:94`, `students/invitations/page.jsx:131`, `settings/page.jsx:96` (logo), `posting/documentstab.jsx:56`, `posting/waitlisttab.jsx:71,79`. `window.confirm` is also widespread in Phase 1 pages. |
| L28 literal % and _ | FIXED | `app/Support/Like.php`; used in `StudentDirectoryFilters.php:94`, `AdminStudentCategoryController.php:88`, `AdminPostingController.php:527` (Eligible), `AdminPostingActivityController.php:179` (Communication Log), `AdminFormBuilderController.php:45` (company picker), `AdminNoticeController.php:48`, `AdminSurveyController.php:64` | No raw `"%{$term}%"` is left in new or changed code. The remaining raw LIKEs are Phase 1 (resumes, companies, alumni, audit log), as documented in D123b. New probes for Eligible, Communication Log and the company picker pass. |
| L29 unused Image imports | FIXED | No `next/image` or `Image` import in `components/admin/adminshell.tsx` or `components/company/companyshell.tsx` | |
| Note D124 (single stage-email button) | RECORDED | `PHASE2_DECISIONS.md` D124; FE `rounds/[roundId]/page.jsx:313` | |
| Note D122g ("Attached Resume" admin-only) | FIXED | `ExportFieldCatalogue.php:84` `'audience' => 'admin'`; test `VerifyFixOffersShortlistTest::test_attached_resume_is_admin_only_in_the_field_catalogue` | |
| Migrations | OK, with a note | `git diff HEAD --stat -- database/migrations` is empty (no tracked migration edited). All 2026_10_06 files have mtimes of Oct 6, so none was edited in the fix session. New: `2026_10_07_000036` (additive column + unique index + backfill), `2026_10_07_000037` (M2 data clean-up) | `000037` is not additive schema. It rewrites `job_postings.eligibility_snapshot` and JNF/INF `form_data` and has a no-op `down()`. This is intended for M2, but irreversible. |
| Probe integrity | OK | The 5 ParityVerify probe files have mtimes of 13:43–13:46 Oct 7, before the reports (13:49–13:50); none was modified by the fix session | |

**Counts (28 items: L1–L14, L16–L29):** 27 FIXED, 1 PARTIAL (L1), 0 NOT FIXED, 0 REGRESSION. Both owner notes are recorded.

## New findings

### Low
- **R-L1. Company-facing change summary still says "Minimum 10th %" / "Minimum 12th %".**
  - Where: `BE app/Http/Controllers/AdminFormReviewController.php:1304-1305` (JNF) and `:1323-1324` (INF), in `detectChangedFields()`.
  - What the company sees: these labels go into `FormEditedByAdminMail`, the company's in-app notification ("Fields changed: …"), the `FormStatusHistory` remark, and the `changed_fields` response.
  - Fix: rename the labels to "Minimum Class X Percentage" / "Minimum Class XII Percentage".
  - Caveat: the labels also serve as keys in the `form.edit` audit before/after. That is audit data, not an action name, but older rows will keep the old keys.
  - Probe: `RecheckLowTest::test_l1_form_edit_change_summary_sent_to_the_company_uses_the_renamed_labels` (FAILS).

### Info
- **I-1 (L27 consistency).** The MUI confirm replaced `window.confirm` only on survey Delete/Archive, notice Delete and stage-email send. Other new parity pages still use the native dialog (listed in the table).
- **I-2 (L23).** An exception in the middle of `toAudience` leaves the per-send file on disk; nothing cleans it up later.
- **I-3 (L26).** Closing the tab while an autosave request is already in flight gives no warning; only a pending, debounced save does.
- **I-4.** Migration `2026_10_07_000037` is an irreversible data rewrite (M2 scope, not an L item).
- **I-5 (L14).** "Excel Templates" is both a drawer entry and a hub tab. This is allowed by the spec; noted for consistency with the Branch Manager change.

## Test results
- `tests/Feature/ParityVerify` (the original 128 probes): **128/128 pass**.
- `--filter 'VerifyFix|ReportsTest|OfferEditTest|StageShortlistTest|SurveyTest|NoticeTest|AdminHubTest|StudentDirectoryTest|ExportTemplateTest|JobProfileManagementTest|StudentInvitationTest'`: **134/134 pass**.
- New `ParityVerify/RecheckLowTest.php`: 8 tests. 7 pass (L7 chain, L9 four-way duplicate, L16 detail/apply, L17 flag, L28 Eligible, L28 Communication Log, L28 company picker); 1 fails (R-L1, kept on purpose).
- Full suite: **653 tests, 15 failures**: the 14 documented baseline failures (PermissionMatrix + AuthorizationMatrix every-route, T0_4b, T1_1c, T1_4a, T3_6c, T5_8_x, T6_13, T8_3b, A3_1, A3_2, A3_4, A3_9, T4_8) plus my R-L1 probe. No other regression.
- `npx eslint` on all 112 changed or new FE js/jsx/ts/tsx files: **0 errors, 28 warnings**. All warnings are in Phase 1 `.tsx` files (admin jnfs/infs `[id]`, company dashboard, `declarationchecklist`, `formpreview`, `graduatingbatchdialog`, `selectionprocessbuilder`) and were already in HEAD (spot-checked). No warnings in new `.js`/`.jsx` files.
- `git restore CDC/qa/evidence CDC/security/evidence` was run after every phpunit run; the tree is clean for those paths.
