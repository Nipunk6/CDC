<?php

use App\Http\Controllers\AdminCompanyController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminFormReviewController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\AdminProgrammeBranchController;
use App\Http\Controllers\AlumniOutreachController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyAuthController;
use App\Http\Controllers\CompanyDashboardController;
use App\Http\Controllers\CompanyFileUploadController;
use App\Http\Controllers\CompanyInfController;
use App\Http\Controllers\CompanyJnfController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\EligibilityCatalogueController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PolicyDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function () {
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
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
    Route::get('/alumni-outreach', [AlumniOutreachController::class, 'index']);

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

    Route::post('/infs/autosave', [CompanyInfController::class, 'autosave']);
    Route::post('/infs/{inf}/duplicate', [CompanyInfController::class, 'duplicate']);
    Route::post('/infs/{inf}/request-edit-access', [CompanyInfController::class, 'requestEditAccess']);
    Route::apiResource('infs', CompanyInfController::class);
});
});
