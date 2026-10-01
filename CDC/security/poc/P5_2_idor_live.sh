#!/usr/bin/env zsh
# Part 5.2 — targeted IDOR probes on the live local API (one benign request each). Expect 403/404 everywhere.
source /Users/admin/Desktop/CDC-main/CDC/qa/api.sh >/dev/null
qa_login STU_A 26QA0001 QaStudent@2026 >/dev/null; qa_login CO_A hr@alpha.qa.test QaCompanyA@2026 >/dev/null; qa_login CO_B hr@beta.qa.test QaCompanyB@2026 >/dev/null
row() { printf "%-8s %-7s %-62s -> %s\n" "$1" "$2" "$3" "$(qa $1 $2 "$3" "${4:-}" | tail -1 | awk '{print $2}')"; }
echo "## STUDENT_A (26QA0001) against STUDENT_B (26QA0005) objects: resume 125, application 118, notification 31"
row STU_A GET /student/resumes/125/file
row STU_A PATCH /student/resumes/125 '{"label":"x"}'
row STU_A DELETE /student/resumes/125
row STU_A PATCH /student/applications/118 '{"resume_id":125}'
row STU_A POST /student/applications/118/withdraw
row STU_A PATCH /auth/notifications/31/read
row STU_A GET '/admin/students/125'
row STU_A GET '/admin/students/125/photo'
echo "## COMPANY_B (Beta) against COMPANY_A (Alpha) objects: posting 42, round 124, JNF 40"
row CO_B GET /company/postings/42
row CO_B GET /company/postings/42/applicants
row CO_B GET /company/postings/42/export
row CO_B GET /company/postings/42/proposals
row CO_B POST /company/postings/42/rounds/124/proposals '{"kind":"addendum","entries":[{"roll_no":"26QA0005"}]}'
row CO_B GET /company/jnfs/40
row CO_B DELETE /company/jnfs/40
row CO_B POST /company/jnfs/40/duplicate
echo "## method spoofing: POST + _method"
row CO_B POST '/company/jnfs/40?_method=DELETE'
row STU_A POST '/student/resumes/125?_method=DELETE'
