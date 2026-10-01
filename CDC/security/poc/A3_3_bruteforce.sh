#!/usr/bin/env bash
# A3.3 — brute force against one account, then a 50-account spray from one IP (local, ≈101 requests).
API=http://127.0.0.1:8000/api/auth/login
login() { curl -s -o /dev/null -w '%{http_code}' -X POST $API -H 'Accept: application/json' -H 'Content-Type: application/json' -d "$1"; }
echo "## (a) 50 wrong passwords against 26QA0004, then the correct password"
codes=""; for i in $(seq 1 50); do codes="$codes $(login "{\"roll_no\":\"26QA0004\",\"password\":\"Wrong-$i-Pass\"}")"; done
echo "statuses:$codes" | tr ' ' '\n' | sort | uniq -c | sed 's/^/  /'
echo "  first 429 at attempt: $(echo $codes | tr ' ' '\n' | grep -n 429 | head -1 | cut -d: -f1)"
echo "  correct password right after: $(login '{"roll_no":"26QA0004","password":"QaStudent@2026"}')"
echo "## waiting 65 s for the per-IP bucket to reset"; sleep 65
echo "## (b) one wrong guess each against 50 different accounts (scale students 27SC0101..0150)"
codes=""; for i in $(seq 101 150); do codes="$codes $(login "{\"roll_no\":\"27SC0$i\",\"password\":\"Spring2026!\"}")"; done
echo "statuses:$codes" | tr ' ' '\n' | sort | uniq -c | sed 's/^/  /'
