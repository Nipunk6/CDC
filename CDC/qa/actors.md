# QA actors (local MySQL `iitism_placement`, local dev only)

All addresses use the reserved `.qa.test` TLD (undeliverable). Backend runs with `MAIL_MAILER=log`.
Passwords below are throwaway test values created for this audit (local DB only).

| Actor | Login | Password | How created |
|---|---|---|---|
| ADMIN_A | qa-admin-a@cdc.qa.test | QaAdminA@2026 | tinker (users row, role admin, not super) |
| ADMIN_B | qa-admin-b@cdc.qa.test | QaAdminB@2026 | tinker |
| SUPER_ADMIN | qa-super@cdc.qa.test | QaSuper@2026 | tinker (is_super_admin = true) |
| COMPANY_A | hr@alpha.qa.test (Alpha Systems QA) | QaCompanyA@2026 | tinker (Company + User) — real registration flow is exercised in Part 8 |
| COMPANY_B | hr@beta.qa.test (Beta Labs QA) | QaCompanyB@2026 | tinker |
| students | roll number | QaStudent@2026 (set via the invitation/reset flow or tinker) | **admin bulk import** (`qa/actors_import.csv`) |

## Test postings (built in the company wizard as COMPANY_A, accepted by ADMIN_A, floated by ADMIN_A)
- **QA-JNF** (fulltime cycle "QA FT 2026-27"): B.Tech CSE + ECE; CGPA 7.0; backlogs allowed with max ongoing 0 / max total 1; gender all; min 10th 60, min 12th 60; batch 2027; rounds OLT (aptitude_test), GD (group_discussion), Technical Interview (technical_interview) + one disabled round.
- **QA-INF** (internship cycle "QA Intern 2026-27"): same branches/cutoffs, gender all.
- **QA-INF-F**: copy of QA-INF with gender **female**.

## Eligibility oracle (written BEFORE testing). Base student = B.Tech CSE, batch 2027, CGPA 8.5, 0/0 backlogs, male, 10th 90, 12th 88, enrolled+active in both QA cycles.

| Roll | Actor | Deviation from base | QA-JNF verdict | QA-INF-F verdict | Expected reason |
|---|---|---|---|---|---|
| 26QA0001 | STU_ELIGIBLE | — | ELIGIBLE | not eligible | INF-F: gender |
| 26QA0002 | STU_LOWCGPA | CGPA 6.20 | not eligible | not eligible | "CGPA below cutoff (6.20 < 7.00)" (+gender for INF-F) |
| 26QA0003 | STU_BACKLOG | ongoing 1, total 2 | not eligible | not eligible | ongoing 1 > 0 and total 2 > 1 |
| 26QA0004 | STU_TOTALBACKLOG_ONLY | ongoing 0, total 1 | ELIGIBLE | not eligible (gender) | total 1 ≤ 1 |
| 26QA0005 | STU_FEMALE | gender female | ELIGIBLE | ELIGIBLE | — |
| 26QA0006 | STU_OTHERBRANCH | Mechanical Engineering | not eligible | not eligible | branch not eligible |
| 26QA0007 | STU_WRONGBATCH | batch 2028 | not eligible | not eligible | batch |
| 26QA0008 | STU_LOW10TH | 10th 55 | not eligible | not eligible | 10th 55 < 60 |
| 26QA0009 | STU_NOTENROLLED | enrolled in no cycle | not visible / not eligible | not visible | not enrolled |
| 26QA0010 | STU_SUSPENDED | suspended by ADMIN_A | 403 everywhere | 403 | account suspended |
| 26QA0011 | STU_DEBARRED | debarred block (ADMIN_A) in QA FT cycle | not eligible | eligible? NO — gender | debarred |
| 26QA0012 | STU_PLACED_FT | (gets FT offer via flow, Part 4) | — | — | — |
| 26QA0013 | STU_INTERN_ONLY | (gets intern offer via flow) | — | — | — |
| 26QA0014 | STU_INTERN_PERF_PPO | (intern_performance_ppo via flow) | — | — | — |
| 26QA0015 | STU_PPO_OFFERED | (ppo_offered via flow) | — | — | — |
| 26QA0016 | STU_FEMALE_2 | female, 12th 59 | not eligible | not eligible | 12th 59 < 60 |
