#!/usr/bin/env bash
# QA Part 8 — Phase 1 regression at API level (local, MAIL_MAILER=log backend). Prints "STEP | expected | got".
source /Users/admin/Desktop/CDC-main/CDC/qa/api.sh
LOG=/Users/admin/Desktop/CDC-main/CDC/backend/storage/logs/laravel.log
code() { tail -1 | awk '{print $2}'; }
chk() { printf "%-62s | exp %-7s | got %s\n" "$1" "$2" "$3"; }

# The registered QA company's password; step 7 resets it to the same value, so the script can be re-run.
CO_G_PW="${CO_G_PW:-QaCompanyG@2027}"
qa_login CO_G cdc-qa-audit-hr@iana.org "$CO_G_PW" >/dev/null
chk "2 company login (registered company)" "token" "$( [ -s $QA_TOK/CO_G ] && echo ok || echo FAIL)"
chk "2 GET /company/dashboard" 200 "$(qa CO_G GET /company/dashboard | code)"
# The Phase 1 profile form requires every contact block, so send the current profile back with a new name.
PROFILE=$(qa CO_G GET /company/profile | head -1 | jq -c '.company // . | {name: "Gamma Reg QA", website, sector, hr_name, hr_designation, hr_email, hr_phone, head_name: .head_talent_contact.name, head_designation: .head_talent_contact.designation, head_email: .head_talent_contact.email, head_mobile: .head_talent_contact.mobile, poc1_name: .primary_contact.name, poc1_designation: .primary_contact.designation, poc1_email: .primary_contact.email, poc1_mobile: .primary_contact.mobile}')
chk "2 PUT /company/profile" 200 "$(qa CO_G PUT /company/profile "$PROFILE" | code)"
chk "2 POST /company/profile/logo (replace)" 200 "$(qa_upload CO_G /company/profile/logo "company_logo=@/private/tmp/claude-501/-Users-admin-Desktop-CDC-main/bcafc58e-c392-4f81-bfb3-9e0d7d5cca37/scratchpad/logo.png" | code)"

# 4. duplicate / delete draft / request edit access
DUP=$(qa CO_A POST /company/jnfs/3/duplicate | head -1 | jq -r '.id // .jnf.id')
chk "4 duplicate JNF 3 -> new draft id" "id" "$DUP"
chk "4 duplicate cleared batch/declarations" "''/false" "$(cd /Users/admin/Desktop/CDC-main/CDC/backend && MAIL_MAILER=log php artisan tinker --execute="\$f=App\\Models\\Jnf::find($DUP)->form_data; echo json_encode(\$f['graduatingBatch']??null).'/'.json_encode(\$f['declarations']['aipc']??null);" | tail -1)"
chk "4 DELETE draft (duplicate)" 200 "$(qa CO_A DELETE /company/jnfs/$DUP | code)"
FD=$(cd /Users/admin/Desktop/CDC-main/CDC/backend && MAIL_MAILER=log php artisan tinker --execute='echo json_encode(App\Models\Jnf::find(3)->form_data);' | tail -1)
SUB=$(qa CO_A POST /company/jnfs "$(jq -nc --arg fd "$FD" '{job_title:"Regression Submitted",job_description:"<p>r</p>",status:"submitted",form_data:$fd}')" | head -1 | jq -r '.jnf.id')
chk "4 company submits JNF" "id" "$SUB"
chk "4 request edit access (submitted)" 200 "$(qa CO_A POST /company/jnfs/$SUB/request-edit-access '{"reason":"Need to fix CTC"}' | code)"
chk "4 request edit access again" 409 "$(qa CO_A POST /company/jnfs/$SUB/request-edit-access '{"reason":"again"}' | code)"

# 5. admin review flows
chk "5 GET /admin/jnfs (default queue)" 200 "$(qa ADMIN_B GET /admin/jnfs | code)"
chk "5 GET /admin/jnfs?status=all" 200 "$(qa ADMIN_B GET '/admin/jnfs?status=all' | code)"
chk "5 GET /admin/infs?status=accepted" 200 "$(qa ADMIN_B GET '/admin/infs?status=accepted' | code)"
chk "5 GET /admin/jnfs/{submitted}" 200 "$(qa ADMIN_B GET /admin/jnfs/$SUB | code)"
chk "5 reject without remarks" 422 "$(qa ADMIN_B PATCH /admin/jnfs/$SUB/status '{"status":"rejected"}' | code)"
chk "5 grant edit access (under_review + remarks)" 200 "$(qa ADMIN_B PATCH /admin/jnfs/$SUB/status '{"status":"under_review","admin_remarks":"Please fix CTC"}' | code)"
chk "5 edit own latest remark" 200 "$(qa ADMIN_B PATCH /admin/jnfs/$SUB/remarks/latest '{"remark":"Please fix CTC (edited)"}' | code)"
chk "5 company resubmits (under_review -> submitted)" 200 "$(qa CO_A PUT /company/jnfs/$SUB "$(jq -nc --arg fd "$FD" '{job_title:"Regression Submitted",job_description:"<p>r2</p>",status:"submitted",form_data:$fd}')" | code)"
chk "5 admin form-data edit (change title)" 200 "$(qa ADMIN_B PATCH /admin/jnfs/$SUB/form-data "$(echo "$FD" | jq -c '{form_data: (. + {jobTitle:"Regression Edited Title"})}')" | code)"
chk "5 FormEditedByAdminMail logged to company" ">0" "$(grep -c 'Subject: JNF Updated by Admin: Regression Submitted' $LOG)"
chk "5 accept" 200 "$(qa ADMIN_B PATCH /admin/jnfs/$SUB/status '{"status":"accepted"}' | code)"
chk "5 CSV download accepted" 200 "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/api/admin/jnfs/$SUB/csv -H "Authorization: Bearer $(cat $QA_TOK/ADMIN_B)")"
DR=$(qa CO_A POST /company/jnfs/3/duplicate | head -1 | jq -r '.id // .jnf.id')
chk "5 mark draft for review" 200 "$(qa ADMIN_B PATCH /admin/jnfs/$DR/status '{"status":"under_review"}' | code)"
chk "5 marked draft stays 'draft' for company" draft "$(qa CO_A GET /company/jnfs/$DR | head -1 | jq -r '.jnf.status')"
chk "5 draft note" 200 "$(qa ADMIN_B POST /admin/jnfs/$DR/notes '{"note":"QA note"}' | code)"
chk "5 unmark (draft)" 200 "$(qa ADMIN_B PATCH /admin/jnfs/$DR/status '{"status":"draft"}' | code)"

# 6. admin misc
chk "6 GET /admin/companies" 200 "$(qa ADMIN_B GET /admin/companies | code)"
chk "6 GET /admin/companies/{id}" 200 "$(qa ADMIN_B GET /admin/companies/4 | code)"
chk "6 POST programme-branches (custom)" 201 "$(qa ADMIN_B POST /admin/programme-branches '{"programme_name":"M.Tech (2 Year) - GATE","branch_name":"QA Quantum Engineering"}' | code)"
PB=$(qa ADMIN_B GET /admin/programme-branches | head -1 | jq -r '.items[] | select(.branch_name=="QA Quantum Engineering") | .id' | head -1)
chk "6 DELETE programme-branches custom" 200 "$(qa ADMIN_B DELETE /admin/programme-branches/$PB | code)"
PD=$(qa ADMIN_B POST /admin/policy-documents '{"title":"QA Link Doc","type":"link","url":"https://iana.org","is_visible_jnf":true,"is_visible_inf":false}' | head -1 | jq -r '.document.id')
chk "6 create policy doc (link)" "id" "$PD"
chk "6 DELETE policy doc" 200 "$(qa ADMIN_B DELETE /admin/policy-documents/$PD | code)"
chk "6 manage-admins as normal admin" 403 "$(qa ADMIN_B GET /admin/manage-admins | code)"
chk "6 manage-admins as super" 200 "$(qa SUPER GET /admin/manage-admins | code)"
chk "6 GET /admin/alumni-outreach" 200 "$(qa ADMIN_B GET /admin/alumni-outreach | code)"
chk "6 GET /auth/notifications (admin)" 200 "$(qa ADMIN_B GET /auth/notifications | code)"

# 7. password reset (company)
chk "7 forgot-password (company email)" 200 "$(qa guest POST /auth/forgot-password '{"email":"cdc-qa-audit-hr@iana.org"}' | code)"
RT=$(grep -oE 'reset-password\?token=[0-9a-f]+&amp;email=cdc-qa-audit-hr' $LOG | tail -1 | sed -E 's/.*token=([0-9a-f]+).*/\1/')
chk "7 reset-password with emailed token" 200 "$(qa guest POST /auth/reset-password "{\"token\":\"$RT\",\"email\":\"cdc-qa-audit-hr@iana.org\",\"password\":\"$CO_G_PW\",\"password_confirmation\":\"$CO_G_PW\"}" | code)"
chk "7 login with new password" 200 "$(curl -s -o /dev/null -w '%{http_code}' -X POST http://127.0.0.1:8000/api/auth/login -H 'Accept: application/json' -H 'Content-Type: application/json' -d "{\"email\":\"cdc-qa-audit-hr@iana.org\",\"password\":\"$CO_G_PW\"}")"

# 8. public
chk "8 POST /alumni-outreach (public)" 201 "$(qa guest POST /alumni-outreach '{"full_name":"Qa Alumnus","email":"qa-alumnus@iana.org","country_code":"+91","phone_number":"9876543210","graduation_year":2015,"programme":"B.Tech","department":"CSE","current_organization":"Org","current_designation":"Eng","city":"Pune","country":"India","linkedin_url":"NA","willing_to_mentor":true,"willing_to_refer":false,"message":"<p>Happy to help</p>"}' | code)"
