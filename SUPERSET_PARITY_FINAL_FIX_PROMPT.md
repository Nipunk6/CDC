# FINAL FIX PROMPT: the last three parity items (2026-10-07)

This fixes the three items left by the QA re-check (`CDC/qa/PARITY_RECHECK_REPORT.md` §4). Same rules as `SUPERSET_PARITY_FIX_PROMPT.md` Part A:
- no commits;
- new frontend files in JSX;
- every admin write audited;
- `MAIL_MAILER=log` for any testing;
- restore `CDC/qa/evidence` and `CDC/security/evidence` after every test run;
- record decisions from **D126** on.

## L15: "Resend to all pending" excludes Revoked students (owner decision 2026-10-07: EXCLUDE)
- **Backend** (`AdminStudentInvitationController::resend`):
  - `all_pending` sends only to students whose invitation is **Sent**: not activated and not revoked.
  - Revoked students are skipped and counted.
  - The response and the audit row (`student.invite_resend_bulk`) report `skipped_revoked_count` next to `skipped_accepted_count`, and the message says "N revoked, skipped".
  - Re-inviting a revoked student stays possible **by explicit selection** (`student_ids`), which un-revokes them as today.
- **Frontend** (`app/admin/students/invitations/page.jsx`):
  - The "Resend to all pending (N)" count is **Sent only**, and the tooltip and confirm text say revoked students are not included.
  - When the explicit selection contains revoked students, the confirm text says "N of them were revoked; resending gives them a new link and un-revokes them".
- **Tests:**
  - **Owner-approved probe change:** `tests/Feature/ParityVerify/S5S6VerifyTest.php` line ~263, the `all_pending` expectation goes from `sent 2` to `sent 1`, because the revoked student is now excluded.
  - Add regression tests:
    - `all_pending` skips revoked students and reports the count;
    - an explicit selection still un-revokes;
    - the audit row carries `skipped_revoked_count`.

## M2: company-supplied student categories survive the clean-up
- **Cause:** migration `2026_10_07_000037` keeps a snapshot value whenever an audit row matches it. Before the fix, those audit rows echoed the company's value, because the company's form value won and the audit read it back. They prove nothing.
- **Correct rule:** before the fix, the snapshot equalled the company's value **whenever the JNF/INF form carried `allowedStudentCategories`**. So drop a posting's snapshot value when its form carried the key and the two lists match (normalised ids). Otherwise the value can only have come from the CDC, so keep it. Then strip the key from all forms, as today.
- **This must live in `000037` itself.** It is the only place that still sees the form keys, and it strips them afterwards, so a later migration could never tell the cases apart.
- **Exception to the no-edit rule:** editing a migration that has already run is normally forbidden. This one is allowed because:
  - it is a data-only, idempotent clean-up;
  - it has run only on the local dev database, and that database has 0 affected job profiles and 0 forms carrying the key (verified);
  - production has not run it yet.
  
  Record the exception as a decision.
- **Tests:**
  - The two probes `RecheckHighMediumTest::test_m2_cleanup_*` must pass.
  - The fix session's own test `VerifyFixLeadTest::test_cleanup_migration_keeps_only_admin_set_categories` encoded the faulty audit rule: its "Chosen by the CDC" fixture has the same value in the company form. Correct the fixture so the CDC's value differs from the form, or the form has no key. The test then still proves "CDC-set values are kept". Explain this in the decision.

## L1: the company change summary still says "Minimum 10th % / 12th %"
- In `AdminFormReviewController` (the JNF and INF `$scalarKeys` lists), the labels become **"Minimum Class X Percentage" / "Minimum Class XII Percentage"**.
- These labels reach the company's email, in-app notification and form history, and the `changed_fields` API response.
- Old `form.edit` audit rows keep their old wording. The audit page shows stored JSON as is, so no mapping is needed. Note this in the decision.
- **Probe:** `RecheckLowTest::test_l1_form_edit_change_summary_sent_to_the_company_uses_the_renamed_labels` must pass.

## Done when
- The full suite has **only the 14 baseline failures** (all ParityVerify probes pass).
- `npm run lint` has 0 errors and `npm run build` passes.
- The invitations page has been checked in the browser.
- The decisions and the progress file are updated, and `CDC/qa/PARITY_RECHECK_REPORT.md` notes the items as fixed.
- **No commit.**
