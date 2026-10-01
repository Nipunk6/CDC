#!/usr/bin/env zsh
# Part 6.1 — SQL injection probes on search/filter/sort parameters (live, local). Prints status + time per payload.
source /Users/admin/Desktop/CDC-main/CDC/qa/api.sh >/dev/null
qa_login ADMIN_A qa-admin-a@cdc.qa.test QaAdminA@2026 >/dev/null; qa_login STU_A 26QA0001 QaStudent@2026 >/dev/null; qa_login CO_A hr@alpha.qa.test QaCompanyA@2026 >/dev/null
url() { python3 -c "import urllib.parse,sys;b,k,v=sys.argv[1:4];print(b+('&' if '?' in b else '?')+k+'='+urllib.parse.quote(v))" "$1" "$2" "$3"; }
probe() { local who=$1 base=$2 param=$3; for p in "'" '"' ')' "' -- " "%" "_" "' AND SLEEP(2)-- " "1) OR SLEEP(2)#" "1 OR 1=1"; do
  out=$(curl -s -o /tmp/claude-sqli.txt -w '%{http_code} %{time_total}' "http://127.0.0.1:8000/api$(url "$base" "$param" "$p")" -H 'Accept: application/json' -H "Authorization: Bearer $(cat $QA_TOK/$who)")
  printf "%-6s %-46s %-24s -> %s %s\n" "$who" "$base" "$param=$p" "$out" "$(grep -o 'SQLSTATE[^"]*' /tmp/claude-sqli.txt | head -c 60)"; done; }
probe ADMIN_A /admin/students search
probe ADMIN_A /admin/companies search
probe ADMIN_A /admin/audit-logs action
probe ADMIN_A "/admin/postings?placement_cycle_id=3" status
probe ADMIN_A /admin/resumes status
probe STU_A /student/postings search
probe STU_A /student/postings type
probe STU_A /student/calendar month
probe CO_A /company/jnfs status
