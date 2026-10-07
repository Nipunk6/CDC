<?php

use App\Http\Controllers\AdminCompanyController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminFormReviewController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\AdminExportTemplateController;
use App\Http\Controllers\AdminOfferController;
use App\Http\Controllers\AdminReconcileController;
use App\Http\Controllers\AdminStudentCategoryController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminPostingActivityController;
use App\Http\Controllers\AdminPlacementCycleController;
use App\Http\Controllers\AdminShortlistController;
use App\Http\Controllers\AdminProgrammeBranchController;
use App\Http\Controllers\AdminBranchChangeController;
use App\Http\Controllers\AdminStudentController;
use App\Http\Controllers\AdminStudentRecordController;
use App\Http\Controllers\AdminResumeController;
use App\Http\Controllers\AdminPostingController;
use App\Http\Controllers\AdminPipelineController;
use App\Http\Controllers\AdminProposalController;
use App\Http\Controllers\AdminResultController;
use App\Http\Controllers\AdminBlockController;
use App\Http\Controllers\AdminEventController;
use App\Http\Controllers\AdminNoticeController;
use App\Http\Controllers\AdminStageMessageController;
use App\Http\Controllers\AdminSurveyController;
use App\Http\Controllers\StudentNoticeController;
use App\Http\Controllers\StudentSurveyController;
use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\EventFeedController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AlumniOutreachController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyAuthController;
use App\Http\Controllers\CompanyDashboardController;
use App\Http\Controllers\CompanyFileUploadController;
use App\Http\Controllers\CompanyInfController;
use App\Http\Controllers\CompanyJnfController;
use App\Http\Controllers\CompanyPipelineController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\EligibilityCatalogueController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AdminFormBuilderController;
use App\Http\Controllers\PolicyDocumentController;
use App\Http\Controllers\StudentBranchChangeController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\StudentResumeController;
use App\Http\Controllers\StudentPostingController;
use App\Http\Controllers\StudentApplicationController;
use Illuminate\Support\Facades\Route;

// Public branding (S8.1): the account logo and institute display name, shown before sign-in. A campus shares a few
// NAT addresses, so these use the wider per-IP bucket of the signed-file routes rather than the 60/min API one.
Route::middleware('throttle:signed-files')->group(function () {
    Route::get('/branding', [\App\Http\Controllers\BrandingController::class, 'show']);
    Route::get('/branding/logo', [\App\Http\Controllers\BrandingController::class, 'logo']);
});

Route::middleware('throttle:api')->group(function () {
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/company/register', [CompanyAuthController::class, 'register']);
    Route::post('/company/recruiter-email/verification-link', [CompanyAuthController::class, 'sendRecruiterEmailVerificationLink']);
    Route::get('/company/recruiter-email/verify', [CompanyAuthController::class, 'verifyRecruiterEmail']);
    Route::get('/company/recruiter-email/verification-status', [CompanyAuthController::class, 'recruiterEmailVerificationStatus']);

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    });
});

Route::post('/alumni-outreach', [AlumniOutreachController::class, 'store']);


Route::middleware(['auth:sanctum', 'active'])->get('/programme-branches', [EligibilityCatalogueController::class, 'programmeBranches']);

Route::middleware(['auth:sanctum', 'active', 'role:admin'])->get('/admin/ping', function () {
    return response()->json([
        'message' => 'Admin route access granted.',
    ]);
});

Route::middleware(['auth:sanctum', 'active', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/manage-admins', [AdminManagementController::class, 'index']);
    Route::post('/manage-admins', [AdminManagementController::class, 'store']);
    Route::patch('/manage-admins/{user}', [AdminManagementController::class, 'update']);
    Route::delete('/manage-admins/{user}', [AdminManagementController::class, 'destroy']);

    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/dashboard/overview', [AdminAnalyticsController::class, 'overview']);
    Route::get('/dashboard/cycle/{placementCycle}', [AdminAnalyticsController::class, 'cycle']);
    Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);
    Route::get('/alumni-outreach', [AlumniOutreachController::class, 'index']);

    Route::get('/placement-cycles', [AdminPlacementCycleController::class, 'index']);
    Route::post('/placement-cycles', [AdminPlacementCycleController::class, 'store']);
    Route::get('/placement-cycles/{placementCycle}', [AdminPlacementCycleController::class, 'show']);
    Route::patch('/placement-cycles/{placementCycle}', [AdminPlacementCycleController::class, 'update']);
    Route::patch('/placement-cycles/{placementCycle}/close', [AdminPlacementCycleController::class, 'close']);
    Route::patch('/placement-cycles/{placementCycle}/publish', [AdminPlacementCycleController::class, 'publish']);
    Route::get('/placement-cycles/{placementCycle}/enrollments', [AdminPlacementCycleController::class, 'enrollments']);
    Route::patch('/placement-cycles/{placementCycle}/enrollments/{enrollment}', [AdminPlacementCycleController::class, 'updateEnrollment']);
    Route::get('/placement-cycles/{placementCycle}/students/export', [AdminPlacementCycleController::class, 'exportStudents']);
    Route::post('/placement-cycles/{placementCycle}/enroll', [AdminPlacementCycleController::class, 'enroll']);
    Route::delete('/placement-cycles/{placementCycle}/enroll/{studentProfile}', [AdminPlacementCycleController::class, 'unenroll']);

    Route::get('/students', [AdminStudentController::class, 'index']);
    Route::get('/students/export', [AdminStudentController::class, 'export']);
    Route::get('/students/pending-requests', [AdminStudentController::class, 'pendingRequests']);
    // S5 Send Invitations (before /students/{studentProfile})
    Route::get('/students/invitations', [\App\Http\Controllers\AdminStudentInvitationController::class, 'index']);
    Route::post('/students/invitations/resend', [\App\Http\Controllers\AdminStudentInvitationController::class, 'resend']);
    Route::post('/students/invitations/revoke', [\App\Http\Controllers\AdminStudentInvitationController::class, 'revoke']);
    Route::post('/students', [AdminStudentController::class, 'store']);
    Route::get('/students/import/template', [AdminStudentController::class, 'importTemplate']);
    Route::post('/students/import', [AdminStudentController::class, 'bulkImport']);
    Route::get('/students/academics/template', [AdminStudentController::class, 'academicsTemplate']);
    Route::post('/students/academics/import', [AdminStudentController::class, 'academicBulkUpdate']);
    Route::get('/students/{studentProfile}', [AdminStudentController::class, 'show']);
    Route::patch('/students/{studentProfile}', [AdminStudentController::class, 'update']);
    Route::get('/students/{studentProfile}/photo', [AdminStudentController::class, 'photo']);
    Route::patch('/students/{studentProfile}/suspend', [AdminStudentController::class, 'suspend']);
    Route::patch('/students/{studentProfile}/reactivate', [AdminStudentController::class, 'reactivate']);
    Route::post('/students/{studentProfile}/resend-invitation', [AdminStudentController::class, 'resendInvitation']);
    Route::post('/students/{studentProfile}/revoke-invitation', [\App\Http\Controllers\AdminStudentInvitationController::class, 'revokeOne']);
    // Superset parity S4.5: student page notes, reports and "Mark all as verified".
    Route::get('/students/{studentProfile}/notes', [AdminStudentRecordController::class, 'notes']);
    Route::post('/students/{studentProfile}/notes', [AdminStudentRecordController::class, 'storeNote']);
    Route::delete('/students/{studentProfile}/notes/{note}', [AdminStudentRecordController::class, 'destroyNote']);
    Route::get('/students/{studentProfile}/placement-report', [AdminStudentRecordController::class, 'placementReport']);
    Route::get('/students/{studentProfile}/eligibility-report', [AdminStudentRecordController::class, 'eligibilityReport']);
    Route::post('/students/{studentProfile}/resumes/verify-all', [AdminStudentRecordController::class, 'verifyAllResumes']);

    Route::get('/resumes', [AdminResumeController::class, 'index']);
    Route::get('/resumes/{resume}/file', [AdminResumeController::class, 'file']);
    Route::patch('/resumes/{resume}', [AdminResumeController::class, 'update']);

    Route::get('/postings', [AdminPostingController::class, 'index']);
    Route::post('/postings', [AdminPostingController::class, 'store']);
    Route::get('/postings/for-form', [AdminPostingController::class, 'forForm']);
    Route::get('/postings/preview-eligibility', [AdminPostingController::class, 'previewEligibility']);
    Route::get('/postings/{jobPosting}', [AdminPostingController::class, 'show']);
    Route::patch('/postings/{jobPosting}', [AdminPostingController::class, 'update']);
    Route::patch('/postings/{jobPosting}/close', [AdminPostingController::class, 'close']);
    Route::patch('/postings/{jobPosting}/reopen', [AdminPostingController::class, 'reopen']);
    Route::patch('/postings/{jobPosting}/cancel', [AdminPostingController::class, 'cancel']);
    Route::post('/postings/{jobPosting}/open-now', [AdminPostingController::class, 'openNow']);
    Route::get('/postings/{jobPosting}/documents', [AdminPostingActivityController::class, 'documents']);
    Route::post('/postings/{jobPosting}/documents', [AdminPostingActivityController::class, 'storeDocument']);
    Route::get('/postings/{jobPosting}/documents/{postingDocument}', [AdminPostingActivityController::class, 'downloadDocument']);
    Route::delete('/postings/{jobPosting}/documents/{postingDocument}', [AdminPostingActivityController::class, 'destroyDocument']);
    Route::get('/postings/{jobPosting}/activity', [AdminPostingActivityController::class, 'activity']);
    Route::get('/postings/{jobPosting}/communications', [AdminPostingActivityController::class, 'communications']);
    Route::post('/postings/{jobPosting}/send-applicant-list', [AdminPostingActivityController::class, 'sendApplicantList']);
    Route::get('/postings/{jobPosting}/applications', [AdminPostingController::class, 'applications']);
    Route::get('/postings/{jobPosting}/eligible', [AdminPostingController::class, 'eligible']);
    Route::get('/postings/{jobPosting}/eligibility/preview', [AdminPostingController::class, 'previewEligibilityChange']);
    Route::patch('/postings/{jobPosting}/eligibility', [AdminPostingController::class, 'updateEligibility']);
    Route::get('/postings/{jobPosting}/export', [AdminPostingController::class, 'export']);
    Route::get('/postings/{jobPosting}/eligible/export', [AdminPostingController::class, 'exportEligible']);
    Route::get('/student-categories', [AdminStudentCategoryController::class, 'index']);
    Route::post('/student-categories', [AdminStudentCategoryController::class, 'store']);
    Route::patch('/student-categories/{studentCategory}', [AdminStudentCategoryController::class, 'update']);
    Route::delete('/student-categories/{studentCategory}', [AdminStudentCategoryController::class, 'destroy']);
    Route::get('/student-categories/{studentCategory}/students', [AdminStudentCategoryController::class, 'members']);
    Route::post('/student-categories/{studentCategory}/students', [AdminStudentCategoryController::class, 'assign']);
    Route::delete('/student-categories/{studentCategory}/students/{studentProfile}', [AdminStudentCategoryController::class, 'unassign']);
    Route::get('/students/{studentProfile}/categories', [AdminStudentCategoryController::class, 'forStudent']);
    Route::get('/reports', [AdminReportController::class, 'index']);
    Route::get('/placement-cycles/{placementCycle}/reports/{report}', [AdminReportController::class, 'download']);
    Route::get('/export-templates', [AdminExportTemplateController::class, 'index']);
    Route::get('/export-templates/fields', [AdminExportTemplateController::class, 'fields']);
    Route::post('/export-templates', [AdminExportTemplateController::class, 'store']);
    Route::get('/export-templates/{exportTemplate}', [AdminExportTemplateController::class, 'show']);
    Route::patch('/export-templates/{exportTemplate}', [AdminExportTemplateController::class, 'update']);
    Route::post('/export-templates/{exportTemplate}/duplicate', [AdminExportTemplateController::class, 'duplicate']);
    Route::delete('/export-templates/{exportTemplate}', [AdminExportTemplateController::class, 'destroy']);
    Route::post('/postings/{jobPosting}/rounds', [AdminPostingController::class, 'storeRound']);
    Route::post('/postings/{jobPosting}/rounds/reorder', [AdminPostingController::class, 'reorderRounds']);
    Route::patch('/postings/{jobPosting}/rounds/{postingRound}', [AdminPostingController::class, 'updateRound']);
    Route::delete('/postings/{jobPosting}/rounds/{postingRound}', [AdminPostingController::class, 'destroyRound']);

    Route::get('/postings/{jobPosting}/pipeline', [AdminPipelineController::class, 'show']);
    Route::get('/postings/{jobPosting}/rounds/{postingRound}/shortlist', [AdminShortlistController::class, 'show']);
    Route::get('/postings/{jobPosting}/rounds/{postingRound}/shortlist/export', [AdminShortlistController::class, 'export']);
    Route::get('/postings/{jobPosting}/rounds/{postingRound}/reconcile', [AdminReconcileController::class, 'show']);
    Route::get('/postings/{jobPosting}/rounds/{postingRound}/reconcile/export', [AdminReconcileController::class, 'export']);
    Route::post('/postings/{jobPosting}/rounds/{postingRound}/reconcile', [AdminReconcileController::class, 'reject']);
    Route::post('/postings/{jobPosting}/rounds/{postingRound}/results', [AdminPipelineController::class, 'results']);
    Route::post('/postings/{jobPosting}/rounds/{postingRound}/attendance', [AdminPipelineController::class, 'attendance']);
    Route::post('/postings/{jobPosting}/rounds/{postingRound}/publish', [AdminPipelineController::class, 'publish']);
    Route::delete('/postings/{jobPosting}/rounds/{postingRound}/waitlist/{application}', [AdminPipelineController::class, 'removeFromWaitlist']);
    Route::post('/postings/{jobPosting}/rounds/{postingRound}/waitlist/{application}/promote', [AdminPipelineController::class, 'promoteFromWaitlist']);
    Route::post('/postings/{jobPosting}/rounds/{postingRound}/addendum', [AdminPipelineController::class, 'addendum']);
    Route::post('/postings/{jobPosting}/rounds/{postingRound}/readd/{application}', [AdminPipelineController::class, 'readd']);
    Route::delete('/postings/{jobPosting}/rounds/{postingRound}/results/{application}', [AdminPipelineController::class, 'destroyDraft']);

    Route::post('/postings/{jobPosting}/applications/{application}/remove-from-process', [AdminPipelineController::class, 'removeFromProcess']);
    Route::get('/postings/{jobPosting}/results/prepare', [AdminResultController::class, 'prepare']);
    Route::post('/postings/{jobPosting}/results/publish', [AdminResultController::class, 'publish']);
    Route::post('/postings/{jobPosting}/offers/ctc-upload', [AdminOfferController::class, 'uploadCtcs']);
    Route::get('/offers/{offer}/preview', [AdminOfferController::class, 'preview']);
    Route::patch('/offers/{offer}', [AdminOfferController::class, 'update']);
    Route::post('/offers/{offer}/revoke', [AdminOfferController::class, 'revoke']);

    Route::get('/blocks', [AdminBlockController::class, 'index']);
    Route::post('/blocks', [AdminBlockController::class, 'store']);
    Route::delete('/blocks/{placementBlock}', [AdminBlockController::class, 'destroy']);

    Route::get('/events', [AdminEventController::class, 'index']);
    Route::post('/events', [AdminEventController::class, 'store']);
    Route::put('/events/{campusEvent}', [AdminEventController::class, 'update']);
    Route::delete('/events/{campusEvent}', [AdminEventController::class, 'destroy']);
    Route::post('/events/{campusEvent}/publish', [AdminEventController::class, 'publish']);

    // Engagement (Superset parity S7): notices, stage emails, surveys. Companies have no route here (B3).
    Route::post('/audiences/preview', [AdminNoticeController::class, 'previewAudience']);
    Route::get('/notices', [AdminNoticeController::class, 'index']);
    Route::post('/notices', [AdminNoticeController::class, 'store']);
    Route::get('/notices/{notice}', [AdminNoticeController::class, 'show']);
    Route::put('/notices/{notice}', [AdminNoticeController::class, 'update']);
    Route::delete('/notices/{notice}', [AdminNoticeController::class, 'destroy']);
    Route::post('/notices/{notice}/publish', [AdminNoticeController::class, 'publish']);
    Route::get('/notices/{notice}/attachment', [AdminNoticeController::class, 'attachment']);
    Route::post('/notices/{notice}/attachment', [AdminNoticeController::class, 'uploadAttachment']);
    Route::delete('/notices/{notice}/attachment', [AdminNoticeController::class, 'destroyAttachment']);
    Route::get('/postings/{jobPosting}/rounds/{postingRound}/message-audience', [AdminStageMessageController::class, 'audience']);
    Route::post('/postings/{jobPosting}/rounds/{postingRound}/email', [AdminStageMessageController::class, 'email']);
    Route::get('/surveys', [AdminSurveyController::class, 'index']);
    Route::post('/surveys', [AdminSurveyController::class, 'store']);
    Route::get('/surveys/{survey}', [AdminSurveyController::class, 'show']);
    Route::put('/surveys/{survey}', [AdminSurveyController::class, 'update']);
    Route::delete('/surveys/{survey}', [AdminSurveyController::class, 'destroy']);
    Route::post('/surveys/{survey}/publish', [AdminSurveyController::class, 'publish']);
    Route::post('/surveys/{survey}/clone', [AdminSurveyController::class, 'clone']);
    Route::get('/surveys/{survey}/report', [AdminSurveyController::class, 'report']);
    Route::get('/surveys/{survey}/export', [AdminSurveyController::class, 'export']);
    Route::get('/surveys/{survey}/responses/{surveyResponse}/files/{surveyQuestion}', [AdminSurveyController::class, 'file']);
    Route::get('/calendar', [CalendarController::class, 'admin']);

    Route::get('/proposals', [AdminProposalController::class, 'index']);
    Route::patch('/proposals/{shortlistProposal}', [AdminProposalController::class, 'update']);

    Route::get('/settings', [AdminSettingsController::class, 'index']);
    Route::patch('/settings', [AdminSettingsController::class, 'update']);
    Route::post('/settings/logo', [AdminSettingsController::class, 'uploadLogo']);
    Route::delete('/settings/logo', [AdminSettingsController::class, 'deleteLogo']);

    Route::get('/branch-changes', [AdminBranchChangeController::class, 'index']);
    Route::patch('/branch-changes/{branchChangeRequest}', [AdminBranchChangeController::class, 'update']);

    Route::get('/programme-branches', [AdminProgrammeBranchController::class, 'index']);
    Route::post('/programme-branches', [AdminProgrammeBranchController::class, 'store']);
    Route::patch('/programme-branches/status', [AdminProgrammeBranchController::class, 'updateExistingStatus']);
    Route::delete('/programme-branches/{programmeBranch}', [AdminProgrammeBranchController::class, 'destroy']);

    Route::get('/jnfs', [AdminFormReviewController::class, 'jnfQueue']);
    Route::get('/jnfs/{jnf}', [AdminFormReviewController::class, 'showJnf']);
    Route::get('/jnfs/{jnf}/csv', [AdminFormReviewController::class, 'downloadJnfCsv']);
    Route::patch('/jnfs/{jnf}/status', [AdminFormReviewController::class, 'updateJnfStatus']);
    Route::patch('/jnfs/{jnf}/remarks/latest', [AdminFormReviewController::class, 'updateLatestJnfRemark']);
    Route::post('/jnfs/{jnf}/notes', [AdminFormReviewController::class, 'addJnfNote']);
    Route::patch('/jnfs/{jnf}/form-data', [AdminFormReviewController::class, 'editJnfFormData']);

    Route::get('/infs', [AdminFormReviewController::class, 'infQueue']);
    Route::get('/infs/{inf}', [AdminFormReviewController::class, 'showInf']);
    Route::get('/infs/{inf}/csv', [AdminFormReviewController::class, 'downloadInfCsv']);
    Route::patch('/infs/{inf}/status', [AdminFormReviewController::class, 'updateInfStatus']);
    Route::patch('/infs/{inf}/remarks/latest', [AdminFormReviewController::class, 'updateLatestInfRemark']);
    Route::post('/infs/{inf}/notes', [AdminFormReviewController::class, 'addInfNote']);
    Route::patch('/infs/{inf}/form-data', [AdminFormReviewController::class, 'editInfFormData']);

    Route::get('/companies', [AdminCompanyController::class, 'index']);
    Route::get('/companies/{company}', [AdminCompanyController::class, 'show']);
    Route::put('/companies/{company}', [AdminCompanyController::class, 'update']);

    // "Add New Job" (S6.1): the CDC fills the JNF/INF wizard for a company, picking or creating it.
    Route::get('/form-builder/companies', [AdminFormBuilderController::class, 'companies']);
    Route::post('/form-builder/companies', [AdminFormBuilderController::class, 'storeCompany']);
    Route::prefix('/form-builder/{company}')->whereNumber('company')->group(function () {
        Route::get('/profile', [AdminFormBuilderController::class, 'profile']);
        Route::get('/policy-documents', [AdminFormBuilderController::class, 'policyDocuments']);
        Route::post('/jnfs/autosave', [AdminFormBuilderController::class, 'autosaveJnf']);
        Route::post('/jnfs', [AdminFormBuilderController::class, 'storeJnf']);
        Route::put('/jnfs/{jnf}', [AdminFormBuilderController::class, 'updateJnf']);
        Route::post('/infs/autosave', [AdminFormBuilderController::class, 'autosaveInf']);
        Route::post('/infs', [AdminFormBuilderController::class, 'storeInf']);
        Route::put('/infs/{inf}', [AdminFormBuilderController::class, 'updateInf']);
    });

    Route::apiResource('/policy-documents', PolicyDocumentController::class);
});

Route::middleware(['auth:sanctum', 'active', 'role:company'])->get('/company/ping', function () {
    return response()->json([
        'message' => 'Company route access granted.',
    ]);
});

Route::middleware(['auth:sanctum', 'active', 'role:company'])->prefix('company')->group(function () {
    Route::get('/dashboard', [CompanyDashboardController::class, 'index']);

    Route::get('/profile', [CompanyProfileController::class, 'show']);
    Route::put('/profile', [CompanyProfileController::class, 'update']);
    Route::post('/profile/logo', [CompanyProfileController::class, 'updateLogo']);

    Route::get('/policy-documents', [PolicyDocumentController::class, 'getForCompany']);

    Route::post('/uploads', [CompanyFileUploadController::class, 'store']);

    Route::post('/jnfs/autosave', [CompanyJnfController::class, 'autosave']);
    Route::post('/jnfs/{jnf}/duplicate', [CompanyJnfController::class, 'duplicate']);
    Route::post('/jnfs/{jnf}/request-edit-access', [CompanyJnfController::class, 'requestEditAccess']);
    Route::apiResource('jnfs', CompanyJnfController::class);

    Route::get('/events', [EventFeedController::class, 'company']);
    Route::get('/postings', [CompanyPipelineController::class, 'index']);
    Route::get('/postings/{jobPosting}', [CompanyPipelineController::class, 'show']);
    Route::get('/postings/{jobPosting}/applicants', [CompanyPipelineController::class, 'applicants']);
    Route::get('/postings/{jobPosting}/export', [CompanyPipelineController::class, 'export']);
    Route::get('/postings/{jobPosting}/proposals', [CompanyPipelineController::class, 'proposals']);
    Route::post('/postings/{jobPosting}/rounds/{postingRound}/proposals', [CompanyPipelineController::class, 'storeProposal']);

    Route::post('/infs/autosave', [CompanyInfController::class, 'autosave']);
    Route::post('/infs/{inf}/duplicate', [CompanyInfController::class, 'duplicate']);
    Route::post('/infs/{inf}/request-edit-access', [CompanyInfController::class, 'requestEditAccess']);
    Route::apiResource('infs', CompanyInfController::class);
});

Route::middleware(['auth:sanctum', 'active', 'role:student'])->prefix('student')->group(function () {
    Route::get('/dashboard', StudentDashboardController::class);
    Route::get('/profile', [StudentProfileController::class, 'show']);
    Route::patch('/profile', [StudentProfileController::class, 'update']);
    Route::post('/profile/photo', [StudentProfileController::class, 'uploadPhoto']);
    Route::get('/profile/photo', [StudentProfileController::class, 'photo']);

    Route::get('/resumes', [StudentResumeController::class, 'index']);
    Route::post('/resumes', [StudentResumeController::class, 'store']);
    Route::patch('/resumes/{resume}', [StudentResumeController::class, 'update']);
    Route::delete('/resumes/{resume}', [StudentResumeController::class, 'destroy']);
    Route::get('/resumes/{resume}/file', [StudentResumeController::class, 'file']);

    Route::get('/postings', [StudentPostingController::class, 'index']);
    Route::get('/postings/{jobPosting}', [StudentPostingController::class, 'show']);
    Route::get('/postings/{jobPosting}/documents/{postingDocument}', [StudentPostingController::class, 'document']);
    Route::post('/postings/{jobPosting}/apply', [StudentApplicationController::class, 'apply']);
    Route::get('/applications', [StudentApplicationController::class, 'index']);
    Route::patch('/applications/{application}', [StudentApplicationController::class, 'update']);
    Route::post('/applications/{application}/withdraw', [StudentApplicationController::class, 'withdraw']);

    Route::get('/events', [EventFeedController::class, 'student']);

    Route::get('/notices', [StudentNoticeController::class, 'index']);
    Route::post('/notices/{notice}/read', [StudentNoticeController::class, 'read']);
    Route::get('/notices/{notice}/attachment', [StudentNoticeController::class, 'attachment']);
    Route::get('/surveys', [StudentSurveyController::class, 'index']);
    Route::get('/surveys/{survey}', [StudentSurveyController::class, 'show']);
    Route::post('/surveys/{survey}/responses', [StudentSurveyController::class, 'submit']);
    Route::post('/surveys/{survey}/responses/{surveyResponse}', [StudentSurveyController::class, 'update']);
    Route::get('/surveys/{survey}/responses/{surveyResponse}/files/{surveyQuestion}', [StudentSurveyController::class, 'file']);
    Route::get('/calendar', [CalendarController::class, 'student']);

    Route::get('/branch-change', [StudentBranchChangeController::class, 'index']);
    Route::post('/branch-change', [StudentBranchChangeController::class, 'store']);
});
});

// Signed, login-free resume links for companies and Excel exports (spec B5 / Q8.1). Outside the
// `throttle:api` group so previews and exports do not exhaust the per-IP API budget (D60).
Route::middleware(['throttle:signed-files', 'signed'])->get('/resumes/signed/{resume}', [AdminResumeController::class, 'signed'])->name('resumes.signed');
// "Send Applicant List" (S6.11): a 7-day signed link to the company-safe applicant export, mailed to the company.
Route::middleware(['throttle:signed-files', 'signed'])->get('/company-exports/{jobPosting}', [\App\Http\Controllers\CompanyPipelineController::class, 'signedExport'])->name('company-exports.applicants');
