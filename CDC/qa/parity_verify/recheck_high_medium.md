# Re-check of H1 and M1–M6 (verification fixes, 2026-10-07)

Independent QA re-verification of the fix session's claims (SUPERSET_PARITY_PROGRESS.md "VERIFICATION FIXES", D119–D125).
I changed no application code and no existing tests. The only file I added is the probe file
`CDC/backend/tests/Feature/ParityVerify/RecheckHighMediumTest.php` (19 tests). Evidence files were restored after every run.

## Verdicts

| Item | Verdict | Summary |
|---|---|---|
| H1 Reconcile Ineligible Students | **FIXED** | Built end to end; every guard I attacked held. Two Low observations below. |
| M1 Survey edits lose answers | **FIXED** | Questions are saved by id and keep the request order. The Audience save never sends questions. Stale, foreign or non-numeric keys get 422. Mandatory errors are reported per question. |
| M2 Allowed Student Categories | **PARTIAL** | The runtime fix is complete. The clean-up migration `2026_10_07_000037` keeps exactly the company-injected values it should drop (2 failing probes). |
| M3 Notices / surveys in SQL | **FIXED** | List and detail agree for every group type and every student. Query count is flat. Pagination and `meta` are correct. |
| M4 Scheduled label | **FIXED** | A closed or cancelled scheduled job profile reports `is_scheduled: false` in the detail and list payloads, and "open now" refuses it. |
| M5 Enrolled-list download | **FIXED** | The export equals the list for the status, literal-search, batch and CGPA filters. Filters are audited, kept in the URL, and passed to the template export as well. |
| M6 Quick-view drawer | **FIXED** | Wired into the Progress Grid, Applicants, Eligible, Shortlist for Offer and the stage page. It is admin-only and links to the full page. ESLint is clean. |

No regressions: the full suite has 645 tests with 14 failures, exactly the documented baseline (details below).

---

## H1: Reconcile Ineligible Students (FIXED)

### Code evidence

**Service** (`backend/app/Services/ReconcileService.php`)
- `ineligible()` (:34-52) takes `PipelineService::pool()` and flushes the eligibility memo. It keeps only the pool members for which `EligibilityService::check()` returns not eligible, and returns check's own `reasons`. There is no second rule set.
- `rejectable()` (:55-71) excludes:
  - applications that are not `applied` (withdrawn);
  - students with an offer on this job profile;
  - students with a published decision in this stage.
- `reject()` (:81-129):
  - re-computes the candidate list on the server;
  - locks the existing row (:98-102);
  - overwrites a draft row, or creates a new one, as published `rejected`;
  - sets the remark at :113 to `"No longer eligible: " . implode(' ', reasons)`, truncated to 255 characters.

**Controller** (`backend/app/Http/Controllers/AdminReconcileController.php`)
- `show` (:30-46).
- `export` (:48-74): `tableWorkbook` with an IST footer; audited as `stage.reconcile_report` at :53.
- `reject` (:79-120):
  - `confirm` must be accepted (:86);
  - refused for a cancelled job profile or while applications are open (:122-132);
  - 422 when nothing is written (:95);
  - audit `stage.reconcile` with `before` (application ids) and `after` (rows with reasons, skipped) at :102-110;
  - mails by the written row ids only (:113, `dispatchResultMails(..., 'reconcile_regret')`).
- A round of another job profile gets 404 (:134-137).

**Routes:** `backend/routes/api.php:198-200`, inside the admin group.

**Mail:** `PipelineService::dispatchResultMails` (:293) and `notifyResults` (:364ff.) pass `kind` through. Delivery uses `sendBulk` with `job_posting_id` and `kind`.

**UI**
- `frontend/components/admin/posting/reconciledialog.jsx`:
  - all rows ticked by default (:46);
  - "All students in this stage are eligible." (:94);
  - "Download report" calls `${base}/export` (:154);
  - a confirm step with the count (:140-145, :162-165);
  - POSTs `{application_ids, confirm: true}` to the real route (:62).
- Stage page button: `app/admin/postings/[id]/rounds/[roundId]/page.jsx:306-307`; dialog mounted at :567-578.
- Communication Log label: `communicationtab.jsx:19`.
- Activity: `AdminPostingActivityController` includes audits whose subject is a PostingRound (:139-142).

**Decision and docs:** D119 records that D103(d) is overridden only when the admin runs this. `CDC_PORTAL_CONTEXT.md:225` is updated.

**Company never sees the remark.** `CompanyPipelineController` does not expose `remark` (it only returns result and attendance at :94). My probe confirms it.

### Probes (all pass)

| Probe | Checks |
|---|---|
| `test_h1_guest_is_401_and_a_round_of_another_job_profile_is_404` | Guest gets 401 on all 3 routes. A foreign round gets 404 on all 3 routes. Nothing is written. |
| `test_h1_debarment_and_suspended_enrolment_are_listed_but_a_non_applicable_internship_block_is_not` | A debarment and a suspended enrolment are listed, with reasons identical to `check()`. An `internships_only` block on a full-time profile is not listed. The remark text is exact. `confirm: 'yes'` is accepted. |
| `test_h1_withdrawn_eligible_and_ineligible_mixed_only_the_ineligible_is_rejected_and_mailed_once` | Selected: a withdrawn application, an eligible student, an ineligible student and an unknown id. Only the ineligible student is written. 3 are skipped. One `RoundResultMail` is sent, with only that student in BCC. One `email_logs` row is written, with `kind = reconcile_regret` and `job_posting_id` set. |
| `test_h1_a_draft_row_is_overwritten_and_a_later_stage_publish_does_not_mail_the_student_again` | A draft "selected" row is overwritten as published rejected. A later stage publish with `reject_remaining` sends no second mail to that student. |
| `test_h1_later_stage_pool_is_reconciled_there_and_not_in_the_published_earlier_stage` | After stage 1 is published, a newly blocked student appears only in stage 2 (`pool_count` 2). The stage 1 published "selected" row stays untouched. One mail is sent. |
| `test_h1_company_never_sees_the_reconcile_remark_or_block_reason` | The company's postings, detail and applicants JSON contain neither "No longer eligible" nor the block remark. |
| `test_h1_cancelled_job_profile_refuses_and_report_has_ist_footer_string_reasons_and_audit` | The footer reads "Downloaded on … IST". The Roll Number, Name and Reasons cells are explicit strings, including `+SUM(A1)` and a `=cmd\|calc` block remark. The audit records the count and the admin. A cancelled job profile gets 422 and no round mail. |
| `test_h1_audit_records_ids_and_reasons_before_and_after` | `before.application_ids`, `after.rejected[].reasons` equal to what the GET showed, and `after.posting_id`. The row appears in `/activity`. |

### Low observations (not spec violations)

**H1-a (Low): a double-submitted confirm can return a 500.** The candidate list is computed outside the transaction. `lockForUpdate()` locks only an *existing* row (`ReconcileService.php:98-102`). If two confirms arrive together for a student with no row yet, both run `create()`. The unique index `(application_id, posting_round_id)` then throws `UniqueConstraintViolationException`, and the second request gets a 500.
- No duplicate row or duplicate mail is possible.
- The dialog disables the button while busy.
- This is the same class as the baseline T3_6c and T5_8_x tests. It is not probed, because SQLite cannot reproduce it.

**H1-b (Low, performance): the check runs per student.** `ineligible()` calls `check()` per pool member: about one block query per student, plus one category query when categories are set. That is roughly N small queries on every dialog open, every export and every confirm. It is acceptable at the current scale (a few hundred students), but worth knowing.

---

## M1: Survey edits lose answers (FIXED)

### Code evidence

`backend/app/Http/Controllers/AdminSurveyController.php`
- `update` (:104-168) syncs questions only when `questions` is sent.
- It refuses with 422 once there are responses (:135-136).
- `cleanQuestions` (:371-401) `ksort`s the items, restoring the request order.
- `syncQuestions` (:403-419):
  - updates a row whose id is sent and belongs to this survey;
  - creates a row for a missing, foreign or duplicate id;
  - deletes only the rows left out.

`backend/app/Services/SurveyAnswerService.php:44-50`: any `answers` or `files` key that is not a question of this survey throws 422 `errors.survey = "This survey was updated — please reload it"`. A missing mandatory answer gives `answers.<id>` "This question is mandatory." (:75-78).

Builder, `app/admin/surveys/[id]/page.jsx`:
- `save({withQuestions})` (:339-386) sends ids.
- The Audience tab's `saveSettings` calls `save({ withQuestions: false })` (:394-402).
- Returned ids are synced back by index (:373-377).

Student page, `app/student/surveys/[id]/page.jsx`:
- reads `errors.survey` and shows the stale message (:88-93) with a Reload button (:116);
- keeps answers to questions that still exist (:22, :43);
- maps per-question errors (:94-102).
- `lib/studentapi.js` gives both JSON and multipart errors a `payload`.

### Probes (all pass)

| Probe | Checks |
|---|---|
| `test_m1_mixed_new_and_existing_questions_keep_the_request_order` | Request `[new, id2, new, id1]` is saved in that order. Kept ids are unchanged. The response order equals the DB order, so the builder's index mapping is safe. |
| `test_m1_unknown_foreign_and_non_numeric_answer_keys_are_422_and_store_nothing` | A foreign survey's question id, `"abc"` and `"0"` each get 422 `errors.survey`, and nothing is stored. A Static Text id is accepted and ignored. |
| `test_m1_question_edit_is_refused_once_answered_and_audience_save_keeps_ids` | Once answered, a question edit gets 422. An audience/settings save leaves the ids and text unchanged. |

### Low observation

**M1-a (Low, UX).** Suppose the CDC *adds* a mandatory question while a student has the form open. The submit then gets 422 `answers.<newId>` "This question is mandatory." for a question the stale page does not render. The student sees a generic error with no question highlighted and no Reload button. No data is lost and nothing is stored. Treating a mandatory error for an unknown question like `errors.survey` would close this. Not required by the fix prompt.

---

## M2: Allowed Student Categories (PARTIAL)

### Runtime fix: correct

- `JobPosting::ADMIN_ONLY_KEYS` and `withoutAdminOnlyKeys` are at `backend/app/Models/JobPosting.php:186-191`.
- Both are used in:
  - the `eligibilityRules()` fallback (:179);
  - `AdminPostingController::snapshot()` (:697-703);
  - preview (:118-121) and open (:212-214), where the admin's validated value is the only source;
  - `PostingEligibilityService::apply` (:204), which never writes the key into `form_data`;
  - company JNF and INF store, update and autosave (`CompanyJnfController.php:87,151,253`, `CompanyInfController.php:87,151,253`).
- The existing probes pass (S0S8 ×2, VerifyFixLeadTest).
- My probes `test_m2_company_inf_store_and_update_strip_the_key` (INF store and update, which the fixer did not test) and `test_m2_rules_fallback_and_eligibility_edit_never_take_categories_from_the_form` pass. The second covers two cases:
  - a null-snapshot fallback ignores a key in `form_data`;
  - Edit eligibility of another key keeps the CDC's categories and never writes them into `form_data`.

### Finding M2-1 (Medium, spec gap; likely nil practical impact): the clean-up migration keeps the company-injected values

**Where:** `backend/database/migrations/2026_10_07_000037_strip_company_supplied_student_categories.php:39-54`.

**The rule.** The migration keeps a snapshot value when it equals the latest `posting.float` audit's `after.allowed_student_categories`, or the latest `posting.eligibility_update` audit's `after.criteria.allowedStudentCategories`.

**Why the float audit is not admin input.** It is `EligibilityService::categoryIds($posting->eligibility_snapshot)` (`AdminPostingController.php:239`), read back from the stored snapshot.
- Before the fix, the snapshot was `snapshot($form) + [admin value]`, and PHP `+` lets the company's `form_data` value win.
- So for every job profile opened through the API, the float audit records the company's value.
- This line is unchanged by the fix. The verifier's line numbers (:118-121, :212-214, :697-702) still match the current file, so nothing above :239 moved.

**Why the eligibility-update audit is not admin input either.** `PostingEligibilityService::proposed()` merges over `current()`. So a later CGPA-only edit also carries the company's list in `after.criteria`.

**Result:** the migration keeps exactly the values it was meant to remove. It only drops a value when there is no audit row at all, which is the case the fixer's test covers.

**Reproduction:** both fail.
- `RecheckHighMediumTest::test_m2_cleanup_drops_a_company_value_even_when_the_pre_fix_float_audit_echoed_it`
- `RecheckHighMediumTest::test_m2_cleanup_drops_a_company_value_carried_into_a_later_eligibility_update_audit`

Both fail with: "the company-supplied category restriction survived the clean-up".

**Safer rule:**
1. Look at the job profile's JNF/INF `form_data` *before* stripping it.
2. If `form_data` carries `allowedStudentCategories`, the pre-fix snapshot value came from the company (the left operand won), so drop the key. The admin's own choice was never recorded separately, so the CDC re-sets it through Edit eligibility.
3. If `form_data` lacks the key, the snapshot value came from the admin, so keep it.

Then add a new migration, because 000037 may already have run.

**Practical impact:** probably nil, because the company wizard never writes this key. It needs a hand-crafted API call during the one-day window between S8.4 and the fix. I could not check the dev DB without touching MySQL.

---

## M3: Notices and surveys filtered in SQL (FIXED)

### Code evidence

- `StudentNoticeController::index` (:24-43): SQL `visibleTo`, the unread count via `whereDoesntHave` over all visible notices, `withExists` read flags, 20 per page and `meta`.
- `StudentSurveyController::index` (:32-50): the same pattern, plus one `survey_responses` query for the page.
- `AudienceService::whereIncludes` (:66-106) mirrors `groupQuery`:
  - `cycle` requires an active enrolment;
  - `posting_applicants` requires status `applied`;
  - `round_results` requires published `selected`/`waitlisted`;
  - `branches` matches the programme with an empty branch;
  - `batch` and `offer_holders` are covered too.
- Scopes: `Notice::scopeVisibleTo` (:84-89) and `Survey::scopeVisibleTo` (:97-103).
- The frontend list pages use `?page=` and `meta` (`app/student/notices/page.jsx:22-26`, `app/student/surveys/page.jsx:19-22`).

### Probes (all pass)

**`test_m3_list_and_detail_agree_for_every_notice_group_type_and_student`.** Seven notices, one per group type plus a two-group notice, against seven students:
- one withdrawn;
- one with a published shortlist;
- one with a draft shortlist;
- one with a published on-hold;
- one with a suspended enrolment;
- one from another branch and batch who is not enrolled.

For every student, the list equals exactly the set of notices that `POST /read` opens (200 against 404).

**`test_m3_student_survey_list_query_count_does_not_grow_with_surveys`.** 3 surveys and 43 surveys (cycle and batch groups, some with deadlines) give the same query count after warm-up. Page 3 returns 3 items and `meta.total` 43. `page=0` gets 422. An out-of-range notices page returns empty.

---

## M4: Scheduled label (FIXED)

- `JobPosting::isScheduled()` (`backend/app/Models/JobPosting.php:92-95`) requires status `open` and a future `scheduled_open_at`.
- `postingStatusLabel` (`frontend/lib/format.js:85`) checks `p.status === "open"`.
- The scheduler only opens `open` job profiles (`routes/console.php:35-37`).
- `openNow` refuses anything that is not scheduled (`AdminPostingController.php:416-421`).

Probe `test_m4_is_scheduled_is_false_once_a_scheduled_job_profile_is_closed_or_cancelled` passes:
- the detail and list payloads report false after close and after cancel;
- `open-now` on a cancelled job profile gets 422.

---

## M5: Enrolled-list "Download as Excel" (FIXED)

### Code evidence

- `AdminPlacementCycleController::exportStudents` (:149-164) validates `StudentDirectoryFilters::enrollmentRules()`.
- It narrows the export through the same `applyToEnrollments()` (`StudentDirectoryFilters.php:69-79`) as `enrollments()` (:171-195), for both the default workbook and templates.
- It audits `cycle.export` with `{enrolled, count, filters, template_id}`.
- Placement page (`app/admin/placement-cycles/[id]/page.jsx`):
  - filters and page live in the URL (:82-90, :145-149 `router.replace`);
  - the button path includes the API filters (:345-350);
  - `templatedownloadbutton.jsx:39` appends `&template=` correctly.
- The default headers are unchanged: the S0S8 probe `test_cycle_student_export_headers_are_the_pre_parity_headers` passes.

### Probe (passes)

`test_m5_export_honours_status_search_and_batch_filters_like_the_list` checks four filter sets: `status=suspended`, literal search `100%_`, `batches[]=2027&status=active`, and `cgpa_min=9.99`. For each, the exported roll numbers equal the listed ones, and the audit `after.filters` and `count` are correct.

---

## M6: Quick-view drawer (FIXED; code and lint review)

The name opens `StudentQuickView` in each list:

| List | File | Lines |
|---|---|---|
| Progress Grid | `components/admin/posting/pipelinetab.jsx` | :324, :402 |
| Applicants | `components/admin/posting/applicantstab.jsx` | :121, :159 |
| Eligible | `components/admin/posting/eligibletab.jsx` | :129, :155 |
| Shortlist for Offer | `app/admin/postings/[id]/results/page.jsx` | :165, :362 |
| Stage page | `app/admin/postings/[id]/rounds/[roundId]/page.jsx` | :429, :580 |

- The backend payloads carry the student `id`:
  - `AdminPipelineController.php:65` (full profile);
  - `AdminResultController.php:67`;
  - the applications list (`studentProfile:id,…`);
  - the eligible list (`only(['id', …])`).
- The drawer loads `/admin/students/{id}` (`studentquickview.jsx:53`), an admin-only route, and links to the full page (:137).
- Company views are untouched.

---

## Test results

**Fixer and probe tests**
- Command: `--filter 'ReconcileIneligibleTest|VerifyFixS7Test|VerifyFixLeadTest|VerifyFixStudentListsTest|VerifyFixOffersShortlistTest|NoticeTest|SurveyTest|ParityVerify'` (run before my file was added).
- Result: **190 tests, 2859 assertions, all pass.**

**Full suite**
- Command: `php -d memory_limit=2G vendor/bin/phpunit` (before my file).
- Result: **645 tests, 14 failures**, exactly the documented baseline:
  - PermissionMatrix and AuthorizationMatrix every-route, with the same 5 pre-existing violations (PolicyDocument show, and CO_B 422 on company JNF/INF PUT/PATCH). None involves the new routes.
  - T0_4b, T1_1c, T1_4a, T3_6c, T5_8_x, T6_13, T8_3b, A3_1, A3_2, A3_4, A3_9, T4_8.

**New probes:** `tests/Feature/ParityVerify/RecheckHighMediumTest.php` has **19 tests, 201 assertions: 17 pass, 2 fail.** Both failures are M2-1 and are kept as findings.

**ESLint:** 16 H1/M1/M3/M4/M5/M6 UI files gave **0 errors and 0 warnings**:
- `reconciledialog.jsx`, the stage page, `pipelinetab`, `applicantstab`, `eligibletab`, the results page, `studentquickview`;
- the placement page, `studentfilters.js`, `templatedownloadbutton`, `format.js`;
- the survey builder, the student survey page, the student notices and surveys pages, `communicationtab`.

**Evidence restored** with `git restore CDC/qa/evidence CDC/security/evidence` after every phpunit run.

## New findings by severity

- **Medium:** M2-1, the clean-up migration keeps company-injected `allowedStudentCategories` because both audit sources echo the snapshot. Reproduced by 2 failing probes.
- **Low:**
  - H1-a: a concurrent double confirm can return a 500 through the unique index. There is no data or mail duplication.
  - H1-b: the per-student `check()` queries in reconcile.
  - M1-a: a mandatory question added while a student's form is open gives a generic error instead of the "reload" prompt.
