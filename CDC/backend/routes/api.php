<?php

use App\Http\Controllers\AdminCompanyController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminFormReviewController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\AdminPlacementCycleController;
use App\Http\Controllers\AdminProgrammeBranchController;
use App\Http\Controllers\AdminBranchChangeController;
use App\Http\Controllers\AdminStudentController;
use App\Http\Controllers\AdminResumeController;
use App\Http\Controllers\AdminPostingController;
use App\Http\Controllers\AdminPipelineController;
use App\Http\Controllers\AdminProposalController;
use App\Http\Controllers\AdminResultController;
use App\Http\Controllers\AdminBlockController;
use App\Http\Controllers\AdminEventController;
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
use App\Http\Controllers\PolicyDocumentController;
use App\Http\Controllers\StudentBranchChangeController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\StudentResumeController;
use App\Http\Controllers\StudentPostingController;
use App\Http\Controllers\StudentApplicationController;
use Illuminate\Support\Facades\Route;

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
    Route::get('/placement-cycles/{placementCycle}/enrollments', [AdminPlacementCycleController::class, 'enrollments']);
    Route::get('/placement-cycles/{placementCycle}/students/export', [AdminPlacementCycleController::class, 'exportStudents']);
    Route::post('/placement-cycles/{placementCycle}/enroll', [AdminPlacementCycleController::class, 'enroll']);
    Route::delete('/placement-cycles/{placementCycle}/enroll/{studentProfile}', [AdminPlacementCycleController::class, 'unenroll']);

    Route::get('/students', [AdminStudentController::class, 'index']);
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
    Route::get('/postings/{jobPosting}/applications', [AdminPostingController::class, 'applications']);
    Route::get('/postings/{jobPosting}/eligible', [AdminPostingController::class, 'eligible']);
    Route::get('/postings/{jobPosting}/eligibility/preview', [AdminPostingController::class, 'previewEligibilityChange']);
    Route::patch('/postings/{jobPosting}/eligibility', [AdminPostingController::class, 'updateEligibility']);
    Route::get('/postings/{jobPosting}/export', [AdminPostingController::class, 'export']);
    Route::post('/postings/{jobPosting}/rounds', [AdminPostingController::class, 'storeRound']);
    Route::post('/postings/{jobPosting}/rounds/reorder', [AdminPostingController::class, 'reorderRounds']);
    Route::patch('/postings/{jobPosting}/rounds/{postingRound}', [AdminPostingController::class, 'updateRound']);
    Route::delete('/postings/{jobPosting}/rounds/{postingRound}', [AdminPostingController::class, 'destroyRound']);

    Route::get('/postings/{jobPosting}/pipeline', [AdminPipelineController::class, 'show']);
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

    Route::get('/blocks', [AdminBlockController::class, 'index']);
    Route::post('/blocks', [AdminBlockController::class, 'store']);
    Route::delete('/blocks/{placementBlock}', [AdminBlockController::class, 'destroy']);

    Route::get('/events', [AdminEventController::class, 'index']);
    Route::post('/events', [AdminEventController::class, 'store']);
    Route::put('/events/{campusEvent}', [AdminEventController::class, 'update']);
    Route::delete('/events/{campusEvent}', [AdminEventController::class, 'destroy']);
    Route::post('/events/{campusEvent}/publish', [AdminEventController::class, 'publish']);
    Route::get('/calendar', [CalendarController::class, 'admin']);

    Route::get('/proposals', [AdminProposalController::class, 'index']);
    Route::patch('/proposals/{shortlistProposal}', [AdminProposalController::class, 'update']);

    Route::get('/settings', [AdminSettingsController::class, 'index']);
    Route::patch('/settings', [AdminSettingsController::class, 'update']);

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
    Route::post('/postings/{jobPosting}/apply', [StudentApplicationController::class, 'apply']);
    Route::get('/applications', [StudentApplicationController::class, 'index']);
    Route::patch('/applications/{application}', [StudentApplicationController::class, 'update']);
    Route::post('/applications/{application}/withdraw', [StudentApplicationController::class, 'withdraw']);

    Route::get('/events', [EventFeedController::class, 'student']);
    Route::get('/calendar', [CalendarController::class, 'student']);

    Route::get('/branch-change', [StudentBranchChangeController::class, 'index']);
    Route::post('/branch-change', [StudentBranchChangeController::class, 'store']);
});
});

// Signed, login-free resume links for companies and Excel exports (spec B5 / Q8.1). Outside the
// `throttle:api` group so previews and exports do not exhaust the per-IP API budget (D60).
Route::middleware(['throttle:signed-files', 'signed'])->get('/resumes/signed/{resume}', [AdminResumeController::class, 'signed'])->name('resumes.signed');
