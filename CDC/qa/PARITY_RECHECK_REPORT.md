# Superset Parity: QA Re-check After Fixes (2026-10-07)

This is a re-run of the full QA after the fix round (`SUPERSET_PARITY_FIX_PROMPT.md`). The original findings are in `PARITY_VERIFICATION_REPORT.md`. The detailed re-check reports are in `parity_verify/recheck_high_medium.md` and `parity_verify/recheck_low.md`.

## 1. Verdict

**The fix round worked.** The missing feature (Reconcile) is built and works, 5 of the 6 medium bugs are fully fixed, and 27 of the 28 low items are fixed. **No new failing tests and no regressions.**

Three small things remain:
- the M2 clean-up rule (no real data affected);
- one rename leftover (L1);
- L15, which waits for the owner's decision.

## 2. Automated results

| Check | Result |
|---|---|
| Full backend suite (before the re-check probes were added) | **645 tests, 14 failures. All 14 are pre-existing, deliberate baseline tests**: QA/security findings from before this work. One former baseline failure (T4_2b) now passes. **No new failures.** |
| Original 128 verification probes | **128 / 128 pass** |
| Probe integrity | Every probe file was compared with the verifiers' own write and edit history. **The fix session did not change any probe.** |
| Re-check probes added now | 27 new probes: 24 pass, 3 fail. The failures are the items in section 4. |
| Permission matrix (every route × every role) | Only the known pre-existing rows; nothing on any new route |
| Frontend | `npm run lint`: 0 errors (76 warnings, down from 80); `tsc --noEmit` clean; `npm run build` passes |
| Fresh install | `migrate:fresh --seed` + `Phase2DemoSeeder` on a scratch MySQL database: 62 migrations, 120 students, 8 offers, 16 blocks, 0 mails or jobs. The scratch database was dropped afterwards. |
| Migrations | Additive only. No old migration was edited. Both new ones (`000036`, `000037`) are applied on the local database. |

## 3. Fix status

| Item | Status | How it was confirmed |
|---|---|---|
| H1 Reconcile Ineligible Students | **FIXED** | Code and probes: reasons equal `check()` (blocks, debarment, suspended enrolment); published rejected rows; one BCC regret batch logged as `reconcile_regret`; running it again does nothing; offer holders, withdrawn and out-of-pool students are skipped; guest 401, student/company 403/404; Excel is formula-safe with an IST footer. Live: the dialog listed the right student with "Blocked: accepted a Full-Time offer." (not confirmed, to keep the data unchanged) |
| M1 Survey answers lost | **FIXED** | Question ids stay stable across saves; stale, foreign and non-numeric answer keys return 422 with a reload message |
| M2 Company-set Allowed Student Categories | **PARTIAL** | The live code path is fixed: the value is taken only from admin input and stripped from company form data. The one-off clean-up migration `000037` can keep a company value whenever an audit row "matches" it, because the audit row read the value after the company's value had already won. **Local data: 0 job profiles carry categories and 0 company forms carry the key, so nothing is affected.** |
| M3 Notices/surveys disappearing | **FIXED** | Audience is filtered in SQL, with pagination and flat query counts. An old notice is still visible among 350 others. |
| M4 "Scheduled to Open" on a cancelled profile | **FIXED** | Code and probe |
| M5 Enrolled-list download ignores filters | **FIXED** | Code and probe. Live: `?placement_status=placed` → "8 filtered students", which equals the 8 offers in that cycle |
| M6 Quick-view drawer | **FIXED** | Wired into all 5 lists. Live: the Progress Grid name opens the drawer with "Open student page" |
| L1 Renames (10th/12th %) | **PARTIAL** | Screens are fixed. The change summary a company receives when the CDC edits its JNF/INF still says "Minimum 10th %" / "Minimum 12th %" (`AdminFormReviewController.php:1304-1305, 1323-1324`). These strings are also keys in `form.edit` audit rows, so they need a careful fix. |
| L2–L14, L16–L29 | **FIXED** | Code and probes. Live: L14 (the old Branch Manager URL redirects into the Admin hub tab), L25 ("Loading job profiles…"), L27 (MUI "Delete this draft?" dialog) |
| L15 Resend to all pending vs Revoked | **WAITING FOR OWNER** | Blocked by design: the recommended "exclude Revoked" option changes an expectation in a verifier probe, which may only be changed with the owner's OK |

## 4. Remaining items

- **M2 clean-up rule (Medium on paper, no real impact).** Add a new migration that drops `allowedStudentCategories` from any job profile whose JNF/INF form still carried that key. The CDC can then re-set categories through Edit eligibility. Probes: `RecheckHighMediumTest` (2).
- **L1 company change-summary labels (Low).** Show "Minimum Class X / XII Percentage" in the email, notification and history text. Keep the old audit keys readable, or map them, so old audit rows still display. Probe: `RecheckLowTest::test_l1_…`.
- **L15 (owner decision).** Exclude Revoked from "Resend to all pending" (recommended; the probe expectation at `S5S6VerifyTest.php:263` changes from 2 to 1), or keep including them and show the count.
- **Info only:**
  - Some other new pages still use the browser's native confirm box: Excel template delete, student categories, invitations, the logo, documents and On Hold.
  - Two Reconcile confirms sent at the same instant can give the second one a 500. No duplicate row or mail, and the button is disabled while busy.
  - Reconcile runs one eligibility check per student in the stage.
  - When a mandatory question is added while a student has the form open, the student sees a generic error instead of the reload prompt. Nothing is stored.
  - An attachment from a failed send is not cleaned up.

## 5. Notes
- Live checks created one draft survey, which was deleted again. Reconcile was opened but not confirmed. No other data changed. The backend log has no errors. The only console error comes from the test tool's injected script.
- Nothing is committed.

## 6. Final fixes (2026-10-07, after the owner's answer)
Done following `SUPERSET_PARITY_FINAL_FIX_PROMPT.md`. Every remaining item is now fixed:

| Item | Status | Proof |
|---|---|---|
| L15: "Resend to all pending" vs Revoked | **FIXED** (owner: exclude Revoked, D126) | Revoked students are skipped and reported. An explicit selection still re-invites and un-revokes them. The page count shows Sent only (2874 on the local data, matching the database). Regression test plus the owner-approved probe update. |
| M2: clean-up rule | **FIXED** (D127) | `000037` drops a snapshot value that equals the company form's value and keeps any other value. Both `RecheckHighMediumTest::test_m2_cleanup_*` probes pass. The fixer's test fixture was corrected. |
| L1: change-summary labels | **FIXED** (D128) | "Minimum Class X / XII Percentage". `RecheckLowTest::test_l1_*` passes. |

**Final result:**
- **Backend suite:** 673 tests, **14 failures**. These are exactly the pre-existing baseline tests, which reproduce earlier QA and security findings on purpose. All ParityVerify probes pass.
- **Frontend:** `npm run lint` has 0 errors; `npm run build` passes. No backend log errors.
- **Not committed.**
