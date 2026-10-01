#!/usr/bin/env bash
# QA helper: source this file, then `qa_login NAME email-or-roll password`, `qa NAME METHOD /path [json]`.
# Tokens live in $QA_TOK (scratch dir), never in the repo.
QA_API="${QA_API:-http://127.0.0.1:8000/api}"
QA_TOK="${QA_TOK:-/private/tmp/claude-501/-Users-admin-Desktop-CDC-main/bcafc58e-c392-4f81-bfb3-9e0d7d5cca37/scratchpad/qa_tokens}"
mkdir -p "$QA_TOK"

qa_login() { # name identifier password
  local name="$1" id="$2" pw="$3" body
  if [[ "$id" == *@* ]]; then body=$(jq -nc --arg e "$id" --arg p "$pw" '{email:$e,password:$p}');
  else body=$(jq -nc --arg r "$id" --arg p "$pw" '{roll_no:$r,password:$p}'); fi
  local out; out=$(curl -s -X POST "$QA_API/auth/login" -H 'Accept: application/json' -H 'Content-Type: application/json' -d "$body")
  local tok; tok=$(echo "$out" | jq -r '.token // empty')
  if [[ -n "$tok" ]]; then echo "$tok" > "$QA_TOK/$name"; echo "login $name ok"; else echo "login $name FAILED: $out"; fi
}

qa() { # name METHOD path [json]  -> prints "HTTP <code>" then body
  local name="$1" method="$2" urlp="$3" data="${4:-}"
  local auth=(); [[ "$name" != "guest" ]] && auth=(-H "Authorization: Bearer $(cat "$QA_TOK/$name")")
  if [[ -n "$data" ]]; then
    curl -s -o /dev/stdout -w '\nHTTP %{http_code} %{time_total}s\n' -X "$method" "$QA_API$urlp" -H 'Accept: application/json' -H 'Content-Type: application/json' "${auth[@]}" -d "$data"
  else
    curl -s -o /dev/stdout -w '\nHTTP %{http_code} %{time_total}s\n' -X "$method" "$QA_API$urlp" -H 'Accept: application/json' "${auth[@]}"
  fi
}

qa_upload() { # name path field=@file [extra -F args...]
  local name="$1" urlp="$2"; shift 2
  local args=(); for f in "$@"; do args+=(-F "$f"); done
  curl -s -w '\nHTTP %{http_code} %{time_total}s\n' -X POST "$QA_API$urlp" -H 'Accept: application/json' -H "Authorization: Bearer $(cat "$QA_TOK/$name")" "${args[@]}"
}
