| Method | Path | Middleware | Allowed | Controller@method | Object params | File upload | Sends email/notification |
|---|---|---|---|---|---|---|---|
| GET | `/api/admin/alumni-outreach` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AlumniOutreachController@index` | — | — | — |
| GET | `/api/admin/audit-logs` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminAuditLogController@index` | — | — | — |
| GET | `/api/admin/blocks` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminBlockController@index` | — | — | — |
| POST | `/api/admin/blocks` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminBlockController@store` | — | — | — |
| DELETE | `/api/admin/blocks/{placementBlock}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminBlockController@destroy` | placementBlock | — | — |
| GET | `/api/admin/branch-changes` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminBranchChangeController@index` | — | — | — |
| PATCH | `/api/admin/branch-changes/{branchChangeRequest}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminBranchChangeController@update` | branchChangeRequest | — | yes |
| GET | `/api/admin/calendar` | api, throttle:api, auth:sanctum, active, role:admin | admin | `CalendarController@admin` | — | — | — |
| GET | `/api/admin/companies` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminCompanyController@index` | — | — | — |
| GET | `/api/admin/companies/{company}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminCompanyController@show` | company | — | — |
| PUT | `/api/admin/companies/{company}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminCompanyController@update` | company | — | — |
| GET | `/api/admin/dashboard` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminDashboardController@index` | — | — | — |
| GET | `/api/admin/dashboard/cycle/{placementCycle}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminAnalyticsController@cycle` | placementCycle | — | — |
| GET | `/api/admin/dashboard/overview` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminAnalyticsController@overview` | — | — | — |
| GET | `/api/admin/events` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminEventController@index` | — | — | — |
| POST | `/api/admin/events` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminEventController@store` | — | — | — |
| DELETE | `/api/admin/events/{campusEvent}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminEventController@destroy` | campusEvent | — | — |
| PUT | `/api/admin/events/{campusEvent}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminEventController@update` | campusEvent | — | — |
| POST | `/api/admin/events/{campusEvent}/publish` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminEventController@publish` | campusEvent | — | yes |
| GET | `/api/admin/infs` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@infQueue` | — | — | — |
| GET | `/api/admin/infs/{inf}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@showInf` | inf | — | — |
| GET | `/api/admin/infs/{inf}/csv` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@downloadInfCsv` | inf | — | — |
| PATCH | `/api/admin/infs/{inf}/form-data` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@editInfFormData` | inf | — | yes |
| POST | `/api/admin/infs/{inf}/notes` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@addInfNote` | inf | — | yes |
| PATCH | `/api/admin/infs/{inf}/remarks/latest` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@updateLatestInfRemark` | inf | — | yes |
| PATCH | `/api/admin/infs/{inf}/status` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@updateInfStatus` | inf | — | yes |
| GET | `/api/admin/jnfs` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@jnfQueue` | — | — | — |
| GET | `/api/admin/jnfs/{jnf}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@showJnf` | jnf | — | — |
| GET | `/api/admin/jnfs/{jnf}/csv` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@downloadJnfCsv` | jnf | — | — |
| PATCH | `/api/admin/jnfs/{jnf}/form-data` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@editJnfFormData` | jnf | — | yes |
| POST | `/api/admin/jnfs/{jnf}/notes` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@addJnfNote` | jnf | — | yes |
| PATCH | `/api/admin/jnfs/{jnf}/remarks/latest` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@updateLatestJnfRemark` | jnf | — | yes |
| PATCH | `/api/admin/jnfs/{jnf}/status` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminFormReviewController@updateJnfStatus` | jnf | — | yes |
| GET | `/api/admin/manage-admins` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminManagementController@index` | — | — | — |
| POST | `/api/admin/manage-admins` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminManagementController@store` | — | — | yes |
| DELETE | `/api/admin/manage-admins/{user}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminManagementController@destroy` | user | — | — |
| GET | `/api/admin/ping` | api, throttle:api, auth:sanctum, active, role:admin | admin | `Closure` | — | — | — |
| GET | `/api/admin/placement-cycles` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPlacementCycleController@index` | — | — | — |
| POST | `/api/admin/placement-cycles` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPlacementCycleController@store` | — | — | — |
| GET | `/api/admin/placement-cycles/{placementCycle}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPlacementCycleController@show` | placementCycle | — | — |
| PATCH | `/api/admin/placement-cycles/{placementCycle}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPlacementCycleController@update` | placementCycle | — | — |
| PATCH | `/api/admin/placement-cycles/{placementCycle}/close` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPlacementCycleController@close` | placementCycle | — | — |
| POST | `/api/admin/placement-cycles/{placementCycle}/enroll` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPlacementCycleController@enroll` | placementCycle | yes | — |
| DELETE | `/api/admin/placement-cycles/{placementCycle}/enroll/{studentProfile}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPlacementCycleController@unenroll` | placementCycle, studentProfile | — | — |
| GET | `/api/admin/placement-cycles/{placementCycle}/enrollments` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPlacementCycleController@enrollments` | placementCycle | — | — |
| GET | `/api/admin/placement-cycles/{placementCycle}/students/export` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPlacementCycleController@exportStudents` | placementCycle | — | — |
| GET | `/api/admin/policy-documents` | api, throttle:api, auth:sanctum, active, role:admin | admin | `PolicyDocumentController@index` | — | — | — |
| POST | `/api/admin/policy-documents` | api, throttle:api, auth:sanctum, active, role:admin | admin | `PolicyDocumentController@store` | — | yes | yes |
| DELETE | `/api/admin/policy-documents/{policy_document}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `PolicyDocumentController@destroy` | policy_document | — | yes |
| GET | `/api/admin/policy-documents/{policy_document}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `PolicyDocumentController@show` | policy_document | — | — |
| PUT|PATCH | `/api/admin/policy-documents/{policy_document}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `PolicyDocumentController@update` | policy_document | yes | yes |
| GET | `/api/admin/postings` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@index` | — | — | — |
| POST | `/api/admin/postings` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@store` | — | — | yes |
| GET | `/api/admin/postings/for-form` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@forForm` | — | — | — |
| GET | `/api/admin/postings/preview-eligibility` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@previewEligibility` | — | — | — |
| GET | `/api/admin/postings/{jobPosting}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@show` | jobPosting | — | — |
| PATCH | `/api/admin/postings/{jobPosting}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@update` | jobPosting | — | — |
| GET | `/api/admin/postings/{jobPosting}/applications` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@applications` | jobPosting | — | — |
| POST | `/api/admin/postings/{jobPosting}/applications/{application}/remove-from-process` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPipelineController@removeFromProcess` | jobPosting, application | — | yes |
| PATCH | `/api/admin/postings/{jobPosting}/cancel` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@cancel` | jobPosting | — | — |
| PATCH | `/api/admin/postings/{jobPosting}/close` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@close` | jobPosting | — | — |
| GET | `/api/admin/postings/{jobPosting}/eligible` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@eligible` | jobPosting | — | — |
| GET | `/api/admin/postings/{jobPosting}/export` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@export` | jobPosting | — | — |
| GET | `/api/admin/postings/{jobPosting}/pipeline` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPipelineController@show` | jobPosting | — | — |
| PATCH | `/api/admin/postings/{jobPosting}/reopen` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@reopen` | jobPosting | — | — |
| GET | `/api/admin/postings/{jobPosting}/results/prepare` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminResultController@prepare` | jobPosting | — | — |
| POST | `/api/admin/postings/{jobPosting}/results/publish` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminResultController@publish` | jobPosting | — | yes |
| POST | `/api/admin/postings/{jobPosting}/rounds` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@storeRound` | jobPosting | — | — |
| POST | `/api/admin/postings/{jobPosting}/rounds/reorder` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@reorderRounds` | jobPosting | — | — |
| DELETE | `/api/admin/postings/{jobPosting}/rounds/{postingRound}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@destroyRound` | jobPosting, postingRound | — | — |
| PATCH | `/api/admin/postings/{jobPosting}/rounds/{postingRound}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPostingController@updateRound` | jobPosting, postingRound | — | — |
| POST | `/api/admin/postings/{jobPosting}/rounds/{postingRound}/addendum` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPipelineController@addendum` | jobPosting, postingRound | — | — |
| POST | `/api/admin/postings/{jobPosting}/rounds/{postingRound}/attendance` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPipelineController@attendance` | jobPosting, postingRound | — | — |
| POST | `/api/admin/postings/{jobPosting}/rounds/{postingRound}/publish` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPipelineController@publish` | jobPosting, postingRound | — | yes |
| POST | `/api/admin/postings/{jobPosting}/rounds/{postingRound}/readd/{application}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPipelineController@readd` | jobPosting, postingRound, application | — | yes |
| POST | `/api/admin/postings/{jobPosting}/rounds/{postingRound}/results` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPipelineController@results` | jobPosting, postingRound | yes | — |
| DELETE | `/api/admin/postings/{jobPosting}/rounds/{postingRound}/results/{application}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPipelineController@destroyDraft` | jobPosting, postingRound, application | — | — |
| DELETE | `/api/admin/postings/{jobPosting}/rounds/{postingRound}/waitlist/{application}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPipelineController@removeFromWaitlist` | jobPosting, postingRound, application | — | yes |
| POST | `/api/admin/postings/{jobPosting}/rounds/{postingRound}/waitlist/{application}/promote` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminPipelineController@promoteFromWaitlist` | jobPosting, postingRound, application | — | yes |
| GET | `/api/admin/programme-branches` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminProgrammeBranchController@index` | — | — | — |
| POST | `/api/admin/programme-branches` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminProgrammeBranchController@store` | — | — | yes |
| PATCH | `/api/admin/programme-branches/status` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminProgrammeBranchController@updateExistingStatus` | — | — | yes |
| DELETE | `/api/admin/programme-branches/{programmeBranch}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminProgrammeBranchController@destroy` | programmeBranch | — | yes |
| GET | `/api/admin/proposals` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminProposalController@index` | — | — | — |
| PATCH | `/api/admin/proposals/{shortlistProposal}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminProposalController@update` | shortlistProposal | — | yes |
| GET | `/api/admin/resumes` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminResumeController@index` | — | — | — |
| PATCH | `/api/admin/resumes/{resume}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminResumeController@update` | resume | — | yes |
| GET | `/api/admin/resumes/{resume}/file` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminResumeController@file` | resume | — | — |
| GET | `/api/admin/settings` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminSettingsController@index` | — | — | — |
| PATCH | `/api/admin/settings` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminSettingsController@update` | — | — | — |
| GET | `/api/admin/students` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminStudentController@index` | — | — | — |
| POST | `/api/admin/students` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminStudentController@store` | — | — | yes |
| POST | `/api/admin/students/academics/import` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminStudentController@academicBulkUpdate` | — | yes | — |
| GET | `/api/admin/students/academics/template` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminStudentController@academicsTemplate` | — | — | — |
| POST | `/api/admin/students/import` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminStudentController@bulkImport` | — | yes | yes |
| GET | `/api/admin/students/import/template` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminStudentController@importTemplate` | — | — | — |
| GET | `/api/admin/students/{studentProfile}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminStudentController@show` | studentProfile | — | — |
| PATCH | `/api/admin/students/{studentProfile}` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminStudentController@update` | studentProfile | — | yes |
| GET | `/api/admin/students/{studentProfile}/photo` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminStudentController@photo` | studentProfile | — | — |
| PATCH | `/api/admin/students/{studentProfile}/reactivate` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminStudentController@reactivate` | studentProfile | — | — |
| POST | `/api/admin/students/{studentProfile}/resend-invitation` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminStudentController@resendInvitation` | studentProfile | — | yes |
| PATCH | `/api/admin/students/{studentProfile}/suspend` | api, throttle:api, auth:sanctum, active, role:admin | admin | `AdminStudentController@suspend` | studentProfile | — | — |
| POST | `/api/alumni-outreach` | api, throttle:api | PUBLIC | `AlumniOutreachController@store` | — | — | yes |
| POST | `/api/auth/company/recruiter-email/verification-link` | api, throttle:api | PUBLIC | `CompanyAuthController@sendRecruiterEmailVerificationLink` | — | — | yes |
| GET | `/api/auth/company/recruiter-email/verification-status` | api, throttle:api | PUBLIC | `CompanyAuthController@recruiterEmailVerificationStatus` | — | — | — |
| GET | `/api/auth/company/recruiter-email/verify` | api, throttle:api | PUBLIC | `CompanyAuthController@verifyRecruiterEmail` | — | — | — |
| POST | `/api/auth/company/register` | api, throttle:api | PUBLIC | `CompanyAuthController@register` | — | yes | yes |
| POST | `/api/auth/forgot-password` | api, throttle:api | PUBLIC | `AuthController@forgotPassword` | — | — | yes |
| POST | `/api/auth/login` | api, throttle:api, throttle:login | PUBLIC | `AuthController@login` | — | — | — |
| POST | `/api/auth/logout` | api, throttle:api, auth:sanctum, active | any authenticated | `AuthController@logout` | — | — | — |
| GET | `/api/auth/notifications` | api, throttle:api, auth:sanctum, active | any authenticated | `NotificationController@index` | — | — | — |
| PATCH | `/api/auth/notifications/read-all` | api, throttle:api, auth:sanctum, active | any authenticated | `NotificationController@markAllAsRead` | — | — | — |
| PATCH | `/api/auth/notifications/{notification}/read` | api, throttle:api, auth:sanctum, active | any authenticated | `NotificationController@markAsRead` | notification | — | — |
| POST | `/api/auth/reset-password` | api, throttle:api | PUBLIC | `AuthController@resetPassword` | — | — | — |
| GET | `/api/auth/user` | api, throttle:api, auth:sanctum, active | any authenticated | `AuthController@user` | — | — | — |
| GET | `/api/company/dashboard` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyDashboardController@index` | — | — | — |
| GET | `/api/company/events` | api, throttle:api, auth:sanctum, active, role:company | company | `EventFeedController@company` | — | — | — |
| GET | `/api/company/infs` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyInfController@index` | — | — | — |
| POST | `/api/company/infs` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyInfController@store` | — | — | yes |
| POST | `/api/company/infs/autosave` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyInfController@autosave` | — | — | — |
| DELETE | `/api/company/infs/{inf}` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyInfController@destroy` | inf | — | — |
| GET | `/api/company/infs/{inf}` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyInfController@show` | inf | — | — |
| PUT|PATCH | `/api/company/infs/{inf}` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyInfController@update` | inf | — | yes |
| POST | `/api/company/infs/{inf}/duplicate` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyInfController@duplicate` | inf | — | — |
| POST | `/api/company/infs/{inf}/request-edit-access` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyInfController@requestEditAccess` | inf | — | yes |
| GET | `/api/company/jnfs` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyJnfController@index` | — | — | — |
| POST | `/api/company/jnfs` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyJnfController@store` | — | — | yes |
| POST | `/api/company/jnfs/autosave` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyJnfController@autosave` | — | — | — |
| DELETE | `/api/company/jnfs/{jnf}` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyJnfController@destroy` | jnf | — | — |
| GET | `/api/company/jnfs/{jnf}` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyJnfController@show` | jnf | — | — |
| PUT|PATCH | `/api/company/jnfs/{jnf}` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyJnfController@update` | jnf | — | yes |
| POST | `/api/company/jnfs/{jnf}/duplicate` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyJnfController@duplicate` | jnf | — | — |
| POST | `/api/company/jnfs/{jnf}/request-edit-access` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyJnfController@requestEditAccess` | jnf | — | yes |
| GET | `/api/company/ping` | api, throttle:api, auth:sanctum, active, role:company | company | `Closure` | — | — | — |
| GET | `/api/company/policy-documents` | api, throttle:api, auth:sanctum, active, role:company | company | `PolicyDocumentController@getForCompany` | — | — | — |
| GET | `/api/company/postings` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyPipelineController@index` | — | — | — |
| GET | `/api/company/postings/{jobPosting}` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyPipelineController@show` | jobPosting | — | — |
| GET | `/api/company/postings/{jobPosting}/applicants` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyPipelineController@applicants` | jobPosting | — | — |
| GET | `/api/company/postings/{jobPosting}/export` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyPipelineController@export` | jobPosting | — | — |
| GET | `/api/company/postings/{jobPosting}/proposals` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyPipelineController@proposals` | jobPosting | — | — |
| POST | `/api/company/postings/{jobPosting}/rounds/{postingRound}/proposals` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyPipelineController@storeProposal` | jobPosting, postingRound | — | yes |
| GET | `/api/company/profile` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyProfileController@show` | — | — | — |
| PUT | `/api/company/profile` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyProfileController@update` | — | — | — |
| POST | `/api/company/profile/logo` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyProfileController@updateLogo` | — | yes | — |
| POST | `/api/company/uploads` | api, throttle:api, auth:sanctum, active, role:company | company | `CompanyFileUploadController@store` | — | yes | — |
| GET | `/api/programme-branches` | api, throttle:api, auth:sanctum, active | any authenticated | `\EligibilityCatalogueController@programmeBranches` | — | — | — |
| GET | `/api/resumes/signed/{resume}` | api, throttle:signed-files, signed | PUBLIC | `\AdminResumeController@signed` | resume | yes | — |
| GET | `/api/student/applications` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentApplicationController@index` | — | — | — |
| PATCH | `/api/student/applications/{application}` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentApplicationController@update` | application | — | — |
| POST | `/api/student/applications/{application}/withdraw` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentApplicationController@withdraw` | application | — | — |
| GET | `/api/student/branch-change` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentBranchChangeController@index` | — | — | — |
| POST | `/api/student/branch-change` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentBranchChangeController@store` | — | — | — |
| GET | `/api/student/calendar` | api, throttle:api, auth:sanctum, active, role:student | student | `CalendarController@student` | — | — | — |
| GET | `/api/student/dashboard` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentDashboardController` | — | — | — |
| GET | `/api/student/events` | api, throttle:api, auth:sanctum, active, role:student | student | `EventFeedController@student` | — | — | — |
| GET | `/api/student/postings` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentPostingController@index` | — | — | — |
| GET | `/api/student/postings/{jobPosting}` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentPostingController@show` | jobPosting | — | — |
| POST | `/api/student/postings/{jobPosting}/apply` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentApplicationController@apply` | jobPosting | — | yes |
| GET | `/api/student/profile` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentProfileController@show` | — | — | — |
| PATCH | `/api/student/profile` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentProfileController@update` | — | — | — |
| GET | `/api/student/profile/photo` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentProfileController@photo` | — | — | — |
| POST | `/api/student/profile/photo` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentProfileController@uploadPhoto` | — | yes | — |
| GET | `/api/student/resumes` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentResumeController@index` | — | — | — |
| POST | `/api/student/resumes` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentResumeController@store` | — | yes | — |
| DELETE | `/api/student/resumes/{resume}` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentResumeController@destroy` | resume | — | — |
| PATCH | `/api/student/resumes/{resume}` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentResumeController@update` | resume | — | — |
| GET | `/api/student/resumes/{resume}/file` | api, throttle:api, auth:sanctum, active, role:student | student | `StudentResumeController@file` | resume | — | — |

Totals: 167 api routes · public 9 · authenticated 158 (admin 102, company 30, student 20, any-role 6)
