# CDC Placement Portal - Complete Technical Explanation (Easy Language)

Prepared on: 20 April 2026
Project folder: CDC

## 1) What this project does
This is a full placement portal for IIT ISM Career Development Centre (CDC).
It has two main user roles:
- Company users: register company, fill JNF and INF forms, upload files, track status.
- Admin users (CDC): review company forms, accept/reject, manage companies, manage programme branches, view alumni outreach submissions.

It also supports:
- Password reset by email.
- In-app notifications.
- Email logging.
- PDF to form auto-fill using local AI (Ollama model).

## 2) Tech stack (simple)
- Backend: Laravel 12 (PHP 8.2), Sanctum token auth, REST APIs.
- Frontend: Next.js 16 + React 19 + TypeScript + MUI.
- Database: SQLite by default for local; MySQL/MariaDB also supported.
- AI parsing: smalot/pdfparser + Ollama local model (qwen2.5).
- Testing: Laravel Feature tests + Playwright smoke/performance tests.

## 3) High-level architecture
Frontend (Next.js) sends API calls to Backend (Laravel).
Backend validates input, updates database, sends notifications/emails, and returns JSON.

Main flow:
1. Login from frontend.
2. Backend returns Sanctum token.
3. Frontend stores token in NextAuth session.
4. Frontend sends Bearer token on protected API calls.
5. Backend role middleware allows or blocks routes.

## 4) Top-level project folders (what each contains)
- CDC/backend: Laravel application (all server logic).
- CDC/frontend: Next.js application (all UI pages/components).
- CDC/docs: API, developer, user and production docs.
- CDC/ml: local AI setup scripts, prompts, and downloaded model files.

Extra:
- CDC/README.md: project overview and setup.
- CDC/PROJECT_STATUS.md: implementation progress tracking.

------------------------------------------------------------
## 5) Backend deep dive (Laravel)
------------------------------------------------------------

### 5.1 Backend entry and configuration files
- backend/bootstrap/app.php
  - Registers web/api routes.
  - Adds role middleware alias.
  - Returns JSON 401 for unauthenticated API access.

- backend/routes/api.php
  - Main API route map.
  - Groups routes by auth, admin, company, alumni, ML.
  - Applies throttle:api to all routes.

- backend/routes/web.php
  - Browser web routes (non-API).

- backend/config/database.php
  - DB engines and connection setup.
  - Default DB is sqlite from env.

- backend/config/auth.php
  - Auth guard/provider configuration.

- backend/config/sanctum.php
  - Sanctum token auth config and stateful domains.

- backend/config/cors.php
  - Allows frontend origins and credentials for API access.

- backend/config/mail.php
  - SMTP/log mailer configuration.

- backend/composer.json
  - PHP packages and scripts.
  - Key dependencies: laravel/framework, laravel/sanctum, smalot/pdfparser.

### 5.2 Backend middleware
- backend/app/Http/Middleware/RoleMiddleware.php
  - Checks user role for admin/company routes.
  - Returns 401 if unauthenticated, 403 if wrong role.

### 5.3 Main backend controllers (file by file)

Auth and user:
- backend/app/Http/Controllers/AuthController.php
  - Admin registration.
  - Login and logout.
  - Current user endpoint.
  - Forgot/reset password flow with safe error handling.

- backend/app/Http/Controllers/CompanyAuthController.php
  - Company registration with many profile fields.
  - Recruiter email verification flow.
  - Logo upload during registration.
  - Creates company + company user.
  - Notifies admins and writes email logs.

Company portal APIs:
- backend/app/Http/Controllers/CompanyDashboardController.php
  - Company dashboard stats and recent JNF/INF.

- backend/app/Http/Controllers/CompanyProfileController.php
  - View/update full company profile.
  - Update company logo.

- backend/app/Http/Controllers/CompanyJnfController.php
  - JNF CRUD.
  - Autosave drafts.
  - Duplicate form.
  - Submit form and status history.
  - Edit-access request flow + email + notification.

- backend/app/Http/Controllers/CompanyInfController.php
  - Same as JNF controller, but for internship form (INF).

- backend/app/Http/Controllers/CompanyFileUploadController.php
  - Upload support files for forms.

Admin portal APIs:
- backend/app/Http/Controllers/AdminDashboardController.php
  - Global counters, pending reviews, recent submissions.

- backend/app/Http/Controllers/AdminFormReviewController.php
  - Admin review queues for JNF/INF.
  - View detailed form + status history.
  - Update statuses and remarks.
  - Export accepted forms to CSV.
  - Add notes/edit form data paths.

- backend/app/Http/Controllers/AdminCompanyController.php
  - Company list/search/detail/update.
  - Submission counts by status.

- backend/app/Http/Controllers/AdminProgrammeBranchController.php
  - Manage programme-branch catalogue.
  - Add/delete custom branches.
  - Enable/disable existing branch states.

Other:
- backend/app/Http/Controllers/NotificationController.php
  - List notifications.
  - Mark single/all notifications read.

- backend/app/Http/Controllers/EligibilityCatalogueController.php
  - Returns active programme-branch options used in forms.

- backend/app/Http/Controllers/AlumniOutreachController.php
  - Public alumni outreach form submission.
  - Admin listing of submitted outreach records.

- backend/app/Http/Controllers/Api/MlController.php
  - PDF extraction endpoint.
  - Reads PDF text and calls Ollama to map into form fields.

### 5.4 Backend service classes
- backend/app/Services/PortalNotificationService.php
  - Central helper for in-app notifications.
  - Sends email and writes sent/failed status in email_logs.

- backend/app/Services/FileUploadService.php
  - Stores file on public disk.
  - Returns path/url/name/size/mime metadata.

### 5.5 Backend models (database objects in code)
- backend/app/Models/User.php
  - User account, role, company relation.
  - Sanctum token support.
  - Custom password reset email sender.

- backend/app/Models/Company.php
  - Company profile data.
  - Has logo URL accessor.
  - Relations to users, JNFs, INFs.

- backend/app/Models/Jnf.php
  - Job Notification Form.
  - Includes form_data JSON and status fields.

- backend/app/Models/Inf.php
  - Internship Notification Form.
  - Includes form_data JSON and status fields.

- backend/app/Models/FormStatusHistory.php
  - Audit log of status transitions (who changed what and when).

- backend/app/Models/PortalNotification.php
  - In-app notifications table model.

- backend/app/Models/EmailLog.php
  - Email delivery audit logs.

- backend/app/Models/ProgrammeBranch.php
  - Programme-branch catalogue entries.

- backend/app/Models/RecruiterEmailVerification.php
  - Recruiter email verification tokens and status.

- backend/app/Models/AlumniOutreachSubmission.php
  - Alumni outreach form records.

### 5.6 Backend mail templates and mail classes
Mail classes:
- backend/app/Mail/NewCompanyRegistrationMail.php
- backend/app/Mail/FormSubmittedMail.php
- backend/app/Mail/FormStatusChangedMail.php
- backend/app/Mail/FormEditedByAdminMail.php
- backend/app/Mail/EditAccessRequestedMail.php
- backend/app/Mail/PasswordResetLinkMail.php
- backend/app/Mail/RecruiterEmailVerificationMail.php

Blade email templates:
- backend/resources/views/emails/new-company-registration.blade.php
- backend/resources/views/emails/form-submitted.blade.php
- backend/resources/views/emails/form-status-changed.blade.php
- backend/resources/views/emails/form-edited-by-admin.blade.php
- backend/resources/views/emails/edit-access-requested.blade.php
- backend/resources/views/emails/password-reset-link.blade.php
- backend/resources/views/emails/recruiter-email-verification-link.blade.php

### 5.7 Database migrations (what each migration does)
Core tables:
- 0001_01_01_000000_create_users_table.php
  - users, password_reset_tokens, sessions.
- 0001_01_01_000001_create_cache_table.php
  - cache and cache_locks.
- 0001_01_01_000002_create_jobs_table.php
  - jobs, job_batches, failed_jobs.

Auth and account structure:
- 2026_03_30_000011_add_role_to_users_table.php
  - Adds role enum: admin/company.
- 2026_03_30_000012_create_companies_table.php
  - Creates companies table and adds users.company_id FK.
- 2026_03_30_000013_create_personal_access_tokens_table.php
  - Sanctum token table.

Main business forms:
- 2026_03_30_000021_create_jnfs_table.php
  - JNF core columns + status.
- 2026_03_30_000022_create_infs_table.php
  - INF core columns + status.
- 2026_04_07_000001_add_form_data_to_jnfs_infs_tables.php
  - Adds form_data JSON to both JNF and INF.
- 2026_04_18_000001_add_edit_access_request_fields_to_forms.php
  - Adds edit_access_requested_at and reason to JNF/INF.

Audit, notifications, email logs:
- 2026_03_30_000023_create_form_status_histories_table.php
  - Creates status history audit table.
- 2026_03_30_000024_create_notifications_table.php
  - Creates in-app notifications table.
- 2026_03_30_000025_create_email_logs_table.php
  - Creates email_logs with sent/failed states.

Performance indexes:
- 2026_03_31_000101_add_performance_indexes.php
  - Adds indexes on frequently filtered columns.

Company profile expansion:
- 2026_04_10_000001_add_registration_profile_fields_to_companies_table.php
  - Adds detailed registration/contact/logo fields.
- 2026_04_10_000002_add_company_profile_extended_fields.php
  - Adds category, turnover, LinkedIn, tags, description fields.

Programme branches:
- 2026_04_17_000001_create_programme_branches_table.php
- 2026_04_17_000002_add_is_active_to_programme_branches_table.php
- 2026_04_17_000003_upgrade_programme_branches_custom_split.php
  - Manage custom vs existing branch states and unique constraints.

Recruiter email verification:
- 2026_04_17_000004_create_recruiter_email_verifications_table.php

Alumni outreach:
- 2026_04_19_000001_create_alumni_outreach_submissions_table.php
- 2026_04_19_000002_add_general_comments_to_alumni_outreach_submissions_table.php
- 2026_04_19_000003_add_split_phone_fields_to_alumni_outreach_submissions_table.php

### 5.8 Backend seeders
- backend/database/seeders/DatabaseSeeder.php
  - Runs all main seeders.
- backend/database/seeders/AdminUserSeeder.php
  - Creates admin test data.
- backend/database/seeders/CompanySeeder.php
  - Creates company sample data.
- backend/database/seeders/FormSeeder.php
  - Creates sample JNF/INF and related data.

### 5.9 Backend tests
Feature tests:
- backend/tests/Feature/AdminAccessControlTest.php
  - Role protection between admin and company routes.
- backend/tests/Feature/AdminFormReviewWorkflowTest.php
  - Status update workflow + audit + notification + email log.
- backend/tests/Feature/AdminQueueFilterTest.php
  - Queue filters for JNF/INF statuses.
- backend/tests/Feature/AuthEdgeCasesTest.php
  - Invalid login, notification ownership, forgot-password behavior.
- backend/tests/Feature/CompanyRegistrationNotificationTest.php
  - Admin notification and email log on company registration.
- backend/tests/Feature/NotificationApiTest.php
  - Notification list/read APIs.

------------------------------------------------------------
## 6) Frontend deep dive (Next.js)
------------------------------------------------------------

### 6.1 Frontend entry/config files
- frontend/package.json
  - Scripts: dev, build, lint, e2e tests, perf tests.

- frontend/next.config.ts
  - Security headers (X-Frame-Options, HSTS, etc.).
  - allowedDevOrigins for local development.

- frontend/auth.ts
  - NextAuth credentials provider.
  - Calls backend /api/auth/login.
  - Stores role/companyId/accessToken in JWT/session.

- frontend/proxy.ts
  - Route-level access control.
  - Redirects based on login state and role.

- frontend/app/api/auth/[...nextauth]/route.ts
  - Exposes NextAuth handlers.

- frontend/app/layout.tsx
  - Global app layout.
  - Wraps app in ThemeRegistry.

- frontend/app/ThemeRegistry.tsx
  - MUI theme provider setup.

- frontend/lib/theme.ts
  - Theme color/font/style setup.

### 6.2 Frontend API utility files
- frontend/lib/companyApi.ts
  - Company-side API calls with bearer token.
  - File upload and company logo upload helpers.

- frontend/lib/adminApi.ts
  - Admin-side API calls with bearer token.
  - CSV file download helper.

### 6.3 Frontend routes (page files)

Public pages:
- frontend/app/page.tsx
  - Main landing page.
- frontend/app/auth/login/page.tsx
  - Login UI.
- frontend/app/auth/forgot-password/page.tsx
  - Forgot password UI.
- frontend/app/auth/reset-password/page.tsx
  - Reset password UI.
- frontend/app/alumni/page.tsx
  - Alumni outreach form.

Company portal pages:
- frontend/app/company/layout.tsx
  - Company shell wrapper.
- frontend/app/company/page.tsx
  - Company dashboard.
- frontend/app/company/register/page.tsx
  - Company registration page.
- frontend/app/company/profile/page.tsx
  - Company profile edit page.
- frontend/app/company/submissions/page.tsx
  - Combined submissions list.
- frontend/app/company/notifications/page.tsx
  - Company notifications page.
- frontend/app/company/jnf/new/page.tsx
  - Create JNF.
- frontend/app/company/jnf/[id]/page.tsx
  - View JNF.
- frontend/app/company/jnf/[id]/edit/page.tsx
  - Edit JNF.
- frontend/app/company/inf/new/page.tsx
  - Create INF.
- frontend/app/company/inf/[id]/page.tsx
  - View INF.
- frontend/app/company/inf/[id]/edit/page.tsx
  - Edit INF.

Admin portal pages:
- frontend/app/admin/layout.tsx
  - Admin shell wrapper.
- frontend/app/admin/page.tsx
  - Admin dashboard.
- frontend/app/admin/jnfs/page.tsx
  - JNF review queue.
- frontend/app/admin/jnfs/[id]/page.tsx
  - JNF detail review.
- frontend/app/admin/infs/page.tsx
  - INF review queue.
- frontend/app/admin/infs/[id]/page.tsx
  - INF detail review.
- frontend/app/admin/companies/page.tsx
  - Company list/management.
- frontend/app/admin/companies/[id]/page.tsx
  - Company detail/management.
- frontend/app/admin/programme-branches/page.tsx
  - Branch manager page.
- frontend/app/admin/alumni-outreach/page.tsx
  - Alumni outreach submissions page.
- frontend/app/admin/notifications/page.tsx
  - Admin notifications page.

Error handling:
- frontend/app/error.tsx
  - Route-level error boundary.

### 6.4 Reusable UI components (important)
Portal shells and auth:
- frontend/components/admin/AdminShell.tsx
- frontend/components/company/CompanyShell.tsx
- frontend/components/auth/AuthTopNav.tsx

Form components:
- frontend/components/forms/JnfForm.tsx
- frontend/components/forms/JnfFormPro.tsx
- frontend/components/forms/InfForm.tsx
- frontend/components/forms/InfFormPro.tsx

Shared form building blocks:
- frontend/components/forms/shared/JdPdfUploader.tsx
  - Uploads PDF and receives AI-extracted fields.
- frontend/components/forms/shared/EligibilityGrid.tsx
- frontend/components/forms/shared/SalaryGrid.tsx
- frontend/components/forms/shared/StipendGrid.tsx
- frontend/components/forms/shared/SelectionProcessBuilder.tsx
- frontend/components/forms/shared/SkillsTagInput.tsx
- frontend/components/forms/shared/CurrencySelector.tsx
- frontend/components/forms/shared/DeclarationChecklist.tsx
- frontend/components/forms/shared/FormPreview.tsx
- frontend/components/forms/shared/FormSection.tsx
- frontend/components/forms/shared/index.ts

### 6.5 Frontend tests
- frontend/tests/e2e/smoke.spec.ts
  - Basic route reachability tests.
- frontend/tests/performance/navigation.perf.spec.ts
  - Navigation performance thresholds.

------------------------------------------------------------
## 7) Database design in easy words
------------------------------------------------------------

Main entities:
- users: login users (admin/company role).
- companies: company profile and contacts.
- jnfs: job forms submitted by companies.
- infs: internship forms submitted by companies.
- form_status_histories: timeline of every status change.
- notifications: in-app alerts per user.
- email_logs: sent/failed email tracking.
- programme_branches: eligibility catalogue used in forms.
- recruiter_email_verifications: recruiter email verification tokens.
- alumni_outreach_submissions: alumni interest form data.

Important relationships:
- One company has many users.
- One company has many JNFs and INFs.
- Each JNF/INF has many status history records.
- One user has many notifications and email logs.

Why this schema is good:
- Clear separation of users, companies, forms, and logs.
- Auditability through form_status_histories and email_logs.
- Scalability improved by indexes on status and dates.

------------------------------------------------------------
## 8) Core business workflows (step by step)
------------------------------------------------------------

### 8.1 Company registration flow
1. Company fills registration form on frontend.
2. Backend validates details and uploads logo.
3. Backend creates company + user.
4. Admins receive in-app notification.
5. Email log entry is created for notification emails.

### 8.2 Login flow
1. User enters email/password.
2. Frontend auth.ts calls backend login API.
3. Backend returns token and user role.
4. Frontend stores role + token in session.
5. proxy.ts redirects user to admin or company dashboard.

### 8.3 JNF/INF submission flow
1. Company creates draft (autosave supported).
2. Company submits final form.
3. Backend stores status and status history.
4. Admin sees form in review queue.

### 8.4 Admin review flow
1. Admin opens form detail.
2. Admin sets status (under_review/accepted/rejected).
3. Backend writes audit history.
4. Company gets in-app notification.
5. Email status update is attempted and logged.

### 8.5 Edit access request flow
1. Company requests edit access for submitted form.
2. Backend records request time and reason.
3. Admins receive email + notification.
4. Request is visible in admin review context.

### 8.6 Forgot password flow
1. User submits email.
2. Backend sends reset link mail if account exists.
3. User opens frontend reset page from email link.
4. Backend verifies token and updates password.

### 8.7 AI PDF extraction flow
1. Company uploads JD PDF from form.
2. Frontend calls backend ML endpoint.
3. Backend parses PDF text.
4. Backend sends extracted text to Ollama with prompts.
5. Structured fields are returned to frontend and auto-filled.

------------------------------------------------------------
## 9) ML module (what is inside ml folder)
------------------------------------------------------------

- ml/README.md
  - ML setup explanation.

- ml/setup.bat and ml/setup.sh
  - One-time setup scripts.

- ml/prompts/extract_company.txt
- ml/prompts/extract_job.txt
- ml/prompts/extract_selection.txt
  - Prompt templates for extracting structured fields.

- ml/models/manifests and ml/models/blobs
  - Downloaded local Ollama model files (qwen2.5).

Important note:
- AI runs locally (no cloud dependency after model download).

------------------------------------------------------------
## 10) Documentation folder guide
------------------------------------------------------------

- docs/API.md
  - API endpoint summary.
- docs/DEVELOPER_GUIDE.md
  - Setup, architecture, validation commands.
- docs/USER_GUIDE.md
  - End-user usage instructions.
- docs/IMPLEMENTATION.md
  - Roadmap/checklist style plan.
- docs/PRODUCTION_CHECKLIST.md
  - Production readiness checks.

------------------------------------------------------------
## 11) Security and reliability features already present
------------------------------------------------------------

- Role-based access control (admin/company) on backend and frontend.
- API rate limiting via throttle:api.
- CORS configured to allowed frontend origins.
- Secure headers configured in next.config.ts.
- Password reset flow with safe non-enumerating message.
- Email send success/failure tracking in email_logs.
- Audit trail via form_status_histories.

------------------------------------------------------------
## 12) What to say in your presentation (simple speaking points)
------------------------------------------------------------

1. We built a full-stack portal with separate admin and company experiences.
2. Backend is API-first Laravel with role-protected endpoints and clear domain controllers.
3. Frontend is Next.js with clean route grouping and reusable form components.
4. Database is normalized around users, companies, forms, audit logs, and notifications.
5. Workflow supports draft to submit to review with complete status history.
6. System includes both in-app notifications and email logs for traceability.
7. It has practical testing on backend APIs and frontend route smoke/performance.
8. It also includes local AI PDF extraction to speed up form filling.

------------------------------------------------------------
## 13) Quick command reference (for demo)
------------------------------------------------------------

Backend:
- cd CDC/backend
- composer install
- php artisan migrate
- php artisan serve

Frontend:
- cd CDC/frontend
- npm install
- npm run dev

Tests:
- Backend tests: php artisan test
- Frontend smoke: npm run test:e2e:smoke
- Frontend perf: npm run test:perf

This document is designed as your complete technical cheat-sheet for viva and project presentation.
