<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\KnowledgeBaseReaderController;
use App\Http\Controllers\MstDepartmentController;
use App\Http\Middleware\RoleAccessMiddleware;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\HeatmapLabelController;
use App\Http\Controllers\HeatmapRiskRangeController;
use App\Http\Controllers\MstHeatmapController;
use App\Http\Controllers\MstRiskCodeController;
use App\Http\Controllers\MstOptionController;
use App\Http\Controllers\TrRiskHeaderController;
use App\Http\Controllers\TrRiskMonthlyController;
use App\Http\Controllers\TrMitigationMonthlyController;
use App\Http\Controllers\TrRiskMonthlyUploadController;

// ============================
//  Auth Routes (tanpa token)
// ============================

Route::post('/register', [AuthController::class, 'register']); // Registrasi user baru (status = pending)
Route::post('/login', [AuthController::class, 'login']);       // Login dan menerima JWT token
// Route::middleware('auth:api')->get('/profile', [AuthController::class, 'profile']);   // Cek profile user

// ============================
//  Auth Routes (dengan token)
// ============================

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api'); // Logout user
Route::put('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:api'); // Ganti password

// Tes protected route (cek token valid)
Route::middleware('auth:api')->get('/protected', function () {
    return response()->json(['message' => 'You are authenticated']);
});

//  Cek user dari token sanctum (tidak dipakai JWT)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Cek token yang masih aktif
Route::middleware(['auth:api'])->get('/check-token', [AuthController::class, 'checkToken']);

// ============================
//  Super Admin Only (role_id = 1)
// ============================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,6'])->group(function () {
    Route::delete('/users/{id}', [UserController::class, 'destroy']); // Hapus user
    Route::delete('/knowledge-base/{id}', [KnowledgeBaseController::class, 'destroy']);
    Route::delete('/departments/{id}', [MstDepartmentController::class, 'destroy']);

});


// ============================
//  User Approval (Admin & Super Admin)
// ============================

// Lihat daftar user pending (tanpa parameter ID)
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,6'])->get('/users/pending', [UserController::class, 'getPendingUsers']);

// Proses setujui / tolak user
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,6'])->group(function () {
    Route::post('/users/{id}/approve', [UserController::class, 'approveUser']);
    Route::post('/users/{id}/reject', [UserController::class, 'rejectUser']);
});


// ============================
//  User, Knowledge, Department (Admin & Super Admin)
// ============================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,6,7'])->group(function () {
    Route::get('/users/dropdowns', [UserController::class, 'dropdownData']);
    //  Users
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);

    //  Knowledge Base
    Route::post('/knowledge-base', [KnowledgeBaseController::class, 'store']);
    Route::put('/knowledge-base/{id}', [KnowledgeBaseController::class, 'update']);

    //  Departmentss
    Route::get('/departments', [MstDepartmentController::class, 'index']);
    Route::get('/departments/{id}', [MstDepartmentController::class, 'show']);
    Route::post('/departments', [MstDepartmentController::class, 'store']);
    Route::put('/departments/{id}', [MstDepartmentController::class, 'update']);

});


// ============================
//  Role Management (Super Admin Only)
// ============================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,6'])->group(function () {
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
});


// ============================
//  Page & Role-Page Access Management
// ============================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,6'])->group(function () {
    // Halaman
    Route::get('page', [PageController::class, 'index']);
    Route::post('/page-with-role', [PageController::class, 'storeWithRoles']);
    // Route::post('page', [PageController::class, 'store']);

    // Hak Akses Role ke Halaman
    Route::get('role-page', [RolePageController::class, 'index']);
    Route::post('role-page', [RolePageController::class, 'storeAccess']);
    Route::get('role-page/{id}', [RolePageController::class, 'show']);
    Route::put('role-page/{id}', [RolePageController::class, 'update']);
    Route::delete('role-page/{id}', [RolePageController::class, 'destroy']);
});


// ============================
//  Knowledge Base - Public Viewer (semua role)
// ============================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3,6,7,8'])->group(function () {
    Route::get('/knowledge-base', [KnowledgeBaseController::class, 'index']); // List all
    Route::get('/knowledge-base/{id}', [KnowledgeBaseController::class, 'show']); // Detail
    Route::post('/knowledge-base/track-reader/{id}', [KnowledgeBaseController::class, 'trackReader']); // Tracking pembaca
});


// ============================
//  Create/Update Knowledge (Admin & Super Admin)
// ============================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,6,7'])->group(function () {
    Route::post('/knowledge-base', [KnowledgeBaseController::class, 'store']);
    Route::post('/knowledge-base/{id}', [KnowledgeBaseController::class, 'update']);
});


// ============================
//  Delete Knowledge (Super Admin Only)
// ============================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,6'])->group(function () {
    Route::delete('/knowledge-base/{id}', [KnowledgeBaseController::class, 'destroy']);
});

Route::middleware(['auth:api'])->group(function () {
    Route::get('/my-profile', [UserController::class, 'getProfile']);
    Route::post('/profile/update', [UserController::class, 'updateProfile']);
});

Route::middleware(['auth:api'])->group(function () {
    Route::post('/risk-header', [RiskHeaderController::class, 'store']);
});

// ============================
//  Upload files
// ============================
Route::prefix('/upload')->group(function () {
    Route::post('/single', [UploadController::class, 'singleUpload']);
    Route::post('/multiple', [UploadController::class, 'multipleUpload']);
});

// ===================== HEAT LABEL =====================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/HeatLabel', [HeatmapLabelController::class, 'index']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->post('/HeatLabel', [HeatmapLabelController::class, 'store']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->put('/HeatLabel/{type}/{id}', [HeatmapLabelController::class, 'update']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1'])->delete('/HeatLabel/{type}/{id}', [HeatmapLabelController::class, 'destroy']);

// ===================== HEATMAP RISK RANGE =====================
Route::prefix('heatmap-risk-range')->group(function () {
    Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/', [HeatmapRiskRangeController::class, 'index']);
    Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->post('/', [HeatmapRiskRangeController::class, 'store']);
    Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->put('/{id}', [HeatmapRiskRangeController::class, 'update']);
    Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1'])->delete('/{id}', [HeatmapRiskRangeController::class, 'destroy']);
});

// ===================== HEATMAP =====================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/heatmap', [MstHeatmapController::class, 'index']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->post('/heatmap', [MstHeatmapController::class, 'store']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/heatmap/{id}', [MstHeatmapController::class, 'show']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->put('/heatmap/{id}', [MstHeatmapController::class, 'update']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1'])->delete('/heatmap/{id}', [MstHeatmapController::class, 'destroy']);

// ===================== RISK CODE =====================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/risk-code', [MstRiskCodeController::class, 'index']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->post('/risk-code', [MstRiskCodeController::class, 'store']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/risk-code/{id}', [MstRiskCodeController::class, 'show']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->put('/risk-code/{id}', [MstRiskCodeController::class, 'update']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1'])->delete('/risk-code/{id}', [MstRiskCodeController::class, 'destroy']);

// ===================== OPTION =====================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/option', [MstOptionController::class, 'index']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->post('/option', [MstOptionController::class, 'store']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/option/{id}', [MstOptionController::class, 'show']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->put('/option/{id}', [MstOptionController::class, 'update']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1'])->delete('/option/{id}', [MstOptionController::class, 'destroy']);

// ===================== RISK HEADER =====================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/risk-header', [TrRiskHeaderController::class, 'index']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->post('/risk-header', [TrRiskHeaderController::class, 'store']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/risk-header/{id}', [TrRiskHeaderController::class, 'show']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->put('/risk-header/{id}', [TrRiskHeaderController::class, 'update']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1'])->delete('/risk-header/{id}', [TrRiskHeaderController::class, 'destroy']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->get('/risk-monitoring', [TrRiskHeaderController::class, 'monitoring']);


// ===================== MITIGATION MONTHLY =====================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/mitigation-monthly', [TrMitigationMonthlyController::class, 'index']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->post('/mitigation-monthly', [TrMitigationMonthlyController::class, 'store']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/mitigation-monthly/{id}', [TrMitigationMonthlyController::class, 'show']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->put('/mitigation-monthly/{id}', [TrMitigationMonthlyController::class, 'update']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1'])->delete('/mitigation-monthly/{id}', [TrMitigationMonthlyController::class, 'destroy']);

// ===================== MONTHLY UPLOAD =====================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/risk-monthly-upload', [TrRiskMonthlyUploadController::class, 'index']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->post('/risk-monthly-upload', [TrRiskMonthlyUploadController::class, 'store']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/risk-monthly-upload/{id}', [TrRiskMonthlyUploadController::class, 'show']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->put('/risk-monthly-upload/{id}', [TrRiskMonthlyUploadController::class, 'update']);
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1'])->delete('/risk-monthly-upload/{id}', [TrRiskMonthlyUploadController::class, 'destroy']);

// ===================== RISK MONTHLY =====================
Route::middleware(['auth:api'])->group(function () {
    Route::middleware([RoleAccessMiddleware::class . ':1,2,3'])->get('/risk-monthly', [TrRiskMonthlyController::class, 'index']);
    Route::middleware([RoleAccessMiddleware::class . ':1,2,3'])->get('/risk-monthly/{id}', [TrRiskMonthlyController::class, 'show']);
    Route::middleware([RoleAccessMiddleware::class . ':1'])->delete('/risk-monthly/{id}', [TrRiskMonthlyController::class, 'destroy']);
    Route::middleware([RoleAccessMiddleware::class . ':1,2'])->put('/risk-monthly/{id}/quantitative', [TrRiskMonthlyController::class, 'updateQuantitative']);
    Route::middleware([RoleAccessMiddleware::class . ':1,2'])->put('/risk-monthly/{id}/update-residual', [TrRiskMonthlyController::class, 'updateResidual']);
    Route::middleware([RoleAccessMiddleware::class . ':1,2'])->put('/risk-monthly/{id}/update-residual-and-finalize', [TrRiskMonthlyController::class, 'updateResidualAndFinalize']);
    Route::middleware([RoleAccessMiddleware::class . ':1,2'])->post('/risk-monthly/{id}/upload', [TrRiskMonthlyController::class, 'uploadDocument']);
    // Route::middleware([RoleAccessMiddleware::class . ':1,2'])->put('/risk-monthly/{id}', [TrRiskMonthlyController::class, 'update']);

    // ===================== ADDITIONAL ENDPOINTS YANG DIPERLUKAN =====================

    // Endpoint untuk mengambil data monthly berdasarkan header
    Route::middleware([RoleAccessMiddleware::class . ':1,2,3'])->get('/risk-monthly/header/{headerId}', [TrRiskMonthlyController::class, 'getByHeader']);

    // Endpoint untuk bulk update quantitative data
    Route::middleware([RoleAccessMiddleware::class . ':1,2,3'])->put('/risk-monthly/bulk-quantitative/{headerId}', [TrRiskMonthlyController::class, 'bulkUpdateQuantitative']);
    // Endpoint untuk finalisasi data monthly
    Route::middleware([RoleAccessMiddleware::class . ':1,2'])->post('/risk-monthly/{id}/finalize', [TrRiskMonthlyController::class, 'finalize']);

    // Endpoint untuk finalisasi semua data monthly dalam satu header
    Route::middleware([RoleAccessMiddleware::class . ':1,2'])->post('/risk-monthly/header/{headerId}/finalize-all', [TrRiskMonthlyController::class, 'finalizeAll']);

    // Endpoint untuk cek status follow-up
    Route::middleware([RoleAccessMiddleware::class . ':1,2,3'])->get('/risk-monthly/header/{headerId}/follow-up-status', [TrRiskMonthlyController::class, 'checkFollowUpStatus']);

    // Endpoint untuk statistik data monthly
    Route::middleware([RoleAccessMiddleware::class . ':1,2,3'])->get('/risk-monthly/header/{headerId}/statistics', [TrRiskMonthlyController::class, 'getStatistics']);
});
