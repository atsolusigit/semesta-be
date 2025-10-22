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
use App\Http\Controllers\ExportRiskController;
use App\Http\Controllers\HeatmapController;
use App\Http\Controllers\TrRiskHeaderEntryController;
use App\Http\Controllers\TrRiskMonthlyEntryController;
// use App\Http\Controllers\RiskTimelinePdfController;
use App\Http\Controllers\MstJabatanController;
use App\Http\Controllers\MstApprovalController;
use App\Http\Controllers\MstMonthRecommendationController;
use App\Http\Controllers\TrRcsaHeaderController;
use App\Http\Controllers\LostEventController;
use App\Http\Controllers\RencanaInvestasiController;

// ============================
//  Auth Routes (tanpa token)
// ============================

Route::post('/register', [AuthController::class, 'register']); // Registrasi user baru (status = pending)
Route::post('/login', [AuthController::class, 'login']);       // Login dan menerima JWT token
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api'); // Logout user
Route::put('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:api'); // Ganti password

// Tes protected route (cek token valid)
Route::middleware('auth:api')->get('/protected', function () {return response()->json(['message' => 'You are authenticated']);});

//  Cek user dari token sanctum (tidak dipakai JWT)
Route::get('/user', function (Request $request) {return $request->user();})->middleware('auth:sanctum');

// Cek token yang masih aktif
Route::middleware(['auth:api'])->get('/check-token', [AuthController::class, 'checkToken']);

//api role management yang baru
Route::middleware(['auth:api'])->group(function () {
    Route::get('/roles', [RoleController::class, 'index']);          // List semua role kecuali Super Admin
    Route::get('/roles/{id}', [RoleController::class, 'show']);      // Detail role
    Route::post('/roles', [RoleController::class, 'store']);         // Tambah role (Super Admin)
    Route::put('/roles/{id}', [RoleController::class, 'update']);    // Update role
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']); // Hapus role
});

// API Department Management new
Route::middleware(['auth:api'])->group(function () {
    Route::get('/departments', [MstDepartmentController::class, 'index']);       // List semua departemen (dibatasi sesuai role)
    Route::get('/departments/{id}', [MstDepartmentController::class, 'show']);   // Detail departemen
    Route::post('/departments', [MstDepartmentController::class, 'store']);      // Tambah departemen
    Route::put('/departments/{id}', [MstDepartmentController::class, 'update']); // Update departemen
    Route::delete('/departments/{id}', [MstDepartmentController::class, 'destroy']); // Hapus departemen
});

// User Management new
Route::middleware(['auth:api'])->group(function () {
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);          // List user

        // Route specific harus di atas route dengan parameter {id}
        Route::get('pending', [UserController::class, 'getPendingUsers']); // List user pending

        Route::get('{id}', [UserController::class, 'show']);        // Detail user
        Route::post('/', [UserController::class, 'store']);         // Tambah user
        Route::put('{id}', [UserController::class, 'update']);      // Update user
        Route::delete('{id}', [UserController::class, 'destroy']);  // Hapus user

        Route::post('{id}/approve', [UserController::class, 'approveUser']);
        Route::post('{id}/reject', [UserController::class, 'rejectUser']);
    });

    // Profile endpoints
    Route::get('/my-profile', [UserController::class, 'getProfile']);
    Route::post('/profile/update', [UserController::class, 'updateProfile']);
});

// API Knowledge Base Management
Route::middleware(['auth:api'])->group(function () {
    Route::get('/knowledge-base', [KnowledgeBaseController::class, 'index']);                    // List all knowledge base (semua role)
    Route::get('/knowledge-base/{id}', [KnowledgeBaseController::class, 'show']);               // Detail knowledge base (semua role)
    Route::post('/knowledge-base/track-reader/{id}', [KnowledgeBaseController::class, 'trackReader']); // Tracking pembaca (semua role)
    Route::post('/knowledge-base', [KnowledgeBaseController::class, 'store']);                  // Tambah knowledge base (role 1,2)
    Route::put('/knowledge-base/{id}', [KnowledgeBaseController::class, 'update']);             // Update knowledge base (role 1,2)
    Route::delete('/knowledge-base/{id}', [KnowledgeBaseController::class, 'destroy']);         // Hapus knowledge base (role 1)
});

// ===================== HEAT LABEL =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/HeatLabel', [HeatmapLabelController::class, 'index']);                        // List all heat labels (semua role)
    Route::post('/HeatLabel', [HeatmapLabelController::class, 'store']);                       // Tambah heat label (role 1,2)
    Route::put('/HeatLabel/{type}/{id}', [HeatmapLabelController::class, 'update']);           // Update heat label (role 1,2)
    Route::delete('/HeatLabel/{type}/{id}', [HeatmapLabelController::class, 'destroy']);       // Hapus heat label (role 1)
});

// ===================== HEATMAP RISK RANGE =====================
Route::middleware(['auth:api'])->prefix('heatmap-risk-range')->group(function () {
    Route::get('/', [HeatmapRiskRangeController::class, 'index']);                             // List all risk ranges (semua role)
    Route::post('/', [HeatmapRiskRangeController::class, 'store']);                            // Tambah risk range (role 1,2)
    Route::put('/{id}', [HeatmapRiskRangeController::class, 'update']);                        // Update risk range (role 1,2)
    Route::delete('/{id}', [HeatmapRiskRangeController::class, 'destroy']);                    // Hapus risk range (role 1)
});

// ===================== HEATMAP =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/heatmap', [MstHeatmapController::class, 'index']);                            // List all heatmap (semua role)
    Route::get('/heatmap-data', [MstHeatmapController::class, 'getHeatmapData']);              // Get heatmap data (semua role)
    Route::get('/heatmap-detail', [MstHeatmapController::class, 'getHeatmapDetailData']);      // Get heatmap detail data (semua role)
    Route::get('/heatmap/{id}', [MstHeatmapController::class, 'show']);                        // Detail heatmap (semua role)
    Route::post('/heatmap', [MstHeatmapController::class, 'store']);                           // Tambah heatmap (role 1,2)
    Route::put('/heatmap/{id}', [MstHeatmapController::class, 'update']);                      // Update heatmap (role 1,2)
    Route::delete('/heatmap/{id}', [MstHeatmapController::class, 'destroy']);                  // Hapus heatmap (role 1)
});

// ============================
//  Page & Role-Page Access Management
// ============================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->group(function () {
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

// ===================== RISK CODE =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/risk-code', [MstRiskCodeController::class, 'index']);                          // List all risk codes (semua role)
    Route::get('/risk-code/{id}', [MstRiskCodeController::class, 'show']);                      // Detail risk code (semua role)
    Route::post('/risk-code', [MstRiskCodeController::class, 'store']);                         // Tambah risk code (role 1,2)
    Route::put('/risk-code/{id}', [MstRiskCodeController::class, 'update']);                    // Update risk code (role 1,2)
    Route::delete('/risk-code/{id}', [MstRiskCodeController::class, 'destroy']);                // Hapus risk code (role 1)
});

// ===================== OPTION =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/option', [MstOptionController::class, 'index']);                               // List all options (semua role)
    Route::get('/option/{id}', [MstOptionController::class, 'show']);                           // Detail option (semua role)
    Route::post('/option', [MstOptionController::class, 'store']);                              // Tambah option (role 1,2)
    Route::put('/option/{id}', [MstOptionController::class, 'update']);                         // Update option (role 1,2)
    Route::delete('/option/{id}', [MstOptionController::class, 'destroy']);                     // Hapus option (role 1)
});

// ===================== RISK HEADER =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/risk-header', [TrRiskHeaderController::class, 'index']);                            // List all risk headers (semua role)
    Route::get('/risk-headers/pending-approval', [TrRiskHeaderController::class, 'getPendingApproval']); // List pending approval (semua role)
    Route::get('/risk-headers/rejected', [TrRiskHeaderController::class, 'getRejectedData']);        // List rejected data (semua role)
    Route::get('/tasks/monitoring', [TrRiskHeaderController::class, 'getTaskRealisasiMonitoring']);  // List task monitoring (semua role)
    Route::get('/risk-header/{id}', [TrRiskHeaderController::class, 'show']);                        // Detail risk header (semua role)
    Route::get('/risk-monitoring', [TrRiskHeaderController::class, 'monitoring']);                   // Risk monitoring (semua role)
    Route::post('/risk-header', [TrRiskHeaderController::class, 'store']);                           // Tambah risk header (role 1,2,3)
    Route::post('/risk-headers/{id}/submit', [TrRiskHeaderController::class, 'submit']);             // Submit risk header (role 1,2,3)
    Route::put('/risk-header/{id}', [TrRiskHeaderController::class, 'update']);                      // Update risk header (role 1,2,3)
    Route::patch('/risk-headers/{id}/approve', [TrRiskHeaderController::class, 'approveRiskHeader']); // Approve risk header (role 1,2)
    Route::patch('/risk-headers/{id}/reject', [TrRiskHeaderController::class, 'rejectRiskHeader']);  // Reject risk header (role 1,2)
    Route::delete('/risk-header/{id}', [TrRiskHeaderController::class, 'destroy']);                  // Hapus risk header (role 1)
    Route::patch('/risk-headers/{id}/approve-menrisk', [TrRiskHeaderController::class, 'approveMenrisk']); // Approve menrisk (role 4)
    Route::patch('/risk-headers/{id}/reject-menrisk', [TrRiskHeaderController::class, 'rejectMenrisk']);   // Reject menrisk (role 4)
    Route::patch('/risk-headers/{id}/approve-vpmenrisk', [TrRiskHeaderController::class, 'approveVpMenrisk']); // Approve VP MenRisk (role 6)
    Route::patch('/risk-headers/{id}/reject-vpmenrisk', [TrRiskHeaderController::class, 'rejectVpMenrisk']);   // Reject VP MenRisk (role 6)
});

// ===================== MITIGATION MONTHLY =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/mitigation-monthly', [TrMitigationMonthlyController::class, 'index']);               // List all mitigation monthly (semua role)
    Route::get('/mitigation-monthly/{id}', [TrMitigationMonthlyController::class, 'show']);           // Detail mitigation monthly (semua role)
    Route::post('/mitigation-monthly', [TrMitigationMonthlyController::class, 'store']);              // Tambah mitigation monthly (role 1,2)
    Route::put('/mitigation-monthly/{id}', [TrMitigationMonthlyController::class, 'update']);         // Update mitigation monthly (role 1,2)
    Route::delete('/mitigation-monthly/{id}', [TrMitigationMonthlyController::class, 'destroy']);     // Hapus mitigation monthly (role 1)
});

// ===================== MONTHLY UPLOAD =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/risk-monthly-upload', [TrRiskMonthlyUploadController::class, 'index']);              // List all monthly uploads (semua role)
    Route::get('/risk-monthly-upload/{id}', [TrRiskMonthlyUploadController::class, 'show']);          // Detail monthly upload (semua role)
    Route::post('/risk-monthly-upload', [TrRiskMonthlyUploadController::class, 'store']);             // Tambah monthly upload (role 1,2)
    Route::put('/risk-monthly-upload/{id}', [TrRiskMonthlyUploadController::class, 'update']);        // Update monthly upload (role 1,2)
    Route::delete('/risk-monthly-upload/{id}', [TrRiskMonthlyUploadController::class, 'destroy']);    // Hapus monthly upload (role 1)
    Route::post('/risk-monthly-upload/temp', [TrRiskMonthlyUploadController::class, 'deleteTempFile']); // Hapus temp file (role 1,2)
});

// ===================== RISK MONTHLY =====================
Route::middleware(['auth:api'])->group(function () {
    // Basic CRUD operations
    Route::get('/risk-monthly', [TrRiskMonthlyController::class, 'index']);                                    // List all monthly data (role 1,2,3)

    // Recommendation functions (letakkan SEBELUM /{id})
    Route::get('/risk-monthly/{headerId}/recommendations', [TrRiskMonthlyController::class, 'getRecommendationMonths']);   // Get bulan rekomendasi by header_id
    Route::post('/risk-monthly/{id}/save-note', [TrRiskMonthlyController::class, 'saveNoteRecommendation']);    // Save note rekomendasi
    Route::post('/risk-monthly/{id}/submit-recommendation', [TrRiskMonthlyController::class, 'submitRecommendation']); // Submit rekomendasi
    Route::post('/risk-monthly/{id}/approve-recommendation', [TrRiskMonthlyController::class, 'approveRecommendation']); // Approve rekomendasi
    Route::post('/risk-monthly/{id}/reject-recommendation', [TrRiskMonthlyController::class, 'rejectRecommendation']); // Reject rekomendasi

    // Approval workflow
    Route::get('/risk-monthly/pending-approvals', [TrRiskMonthlyController::class, 'getPendingApprovals']);   // Pending approvals (role 1,2,3)
    Route::get('/risk-monthly/rejected', [TrRiskMonthlyController::class, 'getRejectedData']);               // Rejected data (role 1,2,3)
    Route::put('/risk-monthly/{id}/approve', [TrRiskMonthlyController::class, 'approveRiskMonthly']);        // Approve monthly (role 1,2)
    Route::put('/risk-monthly/{id}/reject', [TrRiskMonthlyController::class, 'rejectRiskMonthly']);          // Reject monthly (role 1,2)

    // Data updates
    Route::put('/risk-monthly/{id}/quantitative', [TrRiskMonthlyController::class, 'updateQuantitative']);
    Route::put('/risk-monthly/{id}/update-residual', [TrRiskMonthlyController::class, 'updateResidual']);
    Route::put('/risk-monthly/{id}/update-residual-and-finalize', [TrRiskMonthlyController::class, 'updateResidualAndFinalize']);

    // File operations
    Route::post('/risk-monthly/{id}/upload', [TrRiskMonthlyController::class, 'uploadDocument']);

    // Header-based operations
    Route::get('/risk-monthly/header/{headerId}', [TrRiskMonthlyController::class, 'getByHeader']);
    Route::put('/risk-monthly/bulk-quantitative/{headerId}', [TrRiskMonthlyController::class, 'bulkUpdateQuantitative']);
    Route::post('/risk-monthly/header/{headerId}/finalize-all', [TrRiskMonthlyController::class, 'finalizeAll']);
    Route::get('/risk-monthly/header/{headerId}/follow-up-status', [TrRiskMonthlyController::class, 'checkFollowUpStatus']);
    Route::get('/risk-monthly/header/{headerId}/statistics', [TrRiskMonthlyController::class, 'getStatistics']);

    // Individual finalize
    Route::post('/risk-monthly/{id}/finalize', [TrRiskMonthlyController::class, 'finalize']);

    // Show & Delete
    Route::get('/risk-monthly/{id}', [TrRiskMonthlyController::class, 'show']);
    Route::delete('/risk-monthly/{id}', [TrRiskMonthlyController::class, 'destroy']);
});


// ===================== EXPORT FILE =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/export-risk/{format}', [ExportRiskController::class, 'export'])
        ->where(['format' => 'pdf|excel'])
        ->middleware(RoleAccessMiddleware::class . ':1,2,3'); // Export risk file (role 1,2,3)

    Route::get('/export-risk/{id}/preview', [ExportRiskController::class, 'preview'])
        ->where('id', '[0-9]+')
        ->middleware(RoleAccessMiddleware::class . ':1,2,3'); // Preview export risk (role 1,2,3)

    // Export Lost Event (hanya role 1, 4, dan 5)
    Route::get('/export-lost-event/{format}', [ExportRiskController::class, 'exportLostEvent'])
        ->where(['format' => 'pdf|excel'])
        ->middleware(RoleAccessMiddleware::class . ':1,4,5');
});

// ===================== RISK TIMELINE PDF =====================
// Route::middleware(['auth:api'])->group(function () {
//     Route::get('/risk-timeline/download-pdf/{headerId}', [RiskTimelinePdfController::class, 'downloadTimelinePdf'])
//         ->name('api.risk.timeline.pdf');                                                            // Download timeline PDF (role 1,2,3)
// });

// ===================== MST JABATAN =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/jabatan', [MstJabatanController::class, 'index']);                                 // List all jabatan (semua role)
    Route::get('/jabatan/{id}', [MstJabatanController::class, 'show']);                             // Detail jabatan (semua role)
    Route::post('/jabatan', [MstJabatanController::class, 'store']);                                // Tambah jabatan (role 1,2)
    Route::put('/jabatan/{id}', [MstJabatanController::class, 'update']);                           // Update jabatan (role 1,2)
    Route::delete('/jabatan/{id}', [MstJabatanController::class, 'destroy']);                       // Hapus jabatan (role 1)
});

// ===================== MST APPROVAL =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/approval', [MstApprovalController::class, 'index']);                               // List all approval (semua role)
    Route::get('/approval/{id}', [MstApprovalController::class, 'show']);                           // Detail approval (semua role)
    Route::post('/approval', [MstApprovalController::class, 'store']);                              // Tambah approval (role 1,2)
    Route::put('/approval/{id}', [MstApprovalController::class, 'update']);                         // Update approval (role 1,2)
    Route::delete('/approval/{id}', [MstApprovalController::class, 'destroy']);                     // Hapus approval (role 1)
});

// ===================== MST MONTH RECOMMENDATION =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/month-recommendation', [MstMonthRecommendationController::class, 'index']);                          // List all month recommendations (semua role)
    Route::post('/month-recommendation', [MstMonthRecommendationController::class, 'store']);                         // Tambah month recommendation (role 1,2)
    Route::put('/month-recommendation/{id}', [MstMonthRecommendationController::class, 'update']);                    // Update month recommendation (role 1,2)
    Route::delete('/month-recommendation/{id}', [MstMonthRecommendationController::class, 'destroy']);                // Hapus month recommendation (role 1)
});

// ===================== LOST EVENT =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/lost-events', [LostEventController::class, 'index']); // List header < 50%
    Route::get('/lost-events/{id}', [LostEventController::class, 'show']);
    Route::get('/lost-events/detail/{id}', [LostEventController::class, 'detail']); // Detail by lost_event_id
    Route::put('/lost-events/{id}', [LostEventController::class, 'update']); // Update by lost_event_id
    Route::delete('/lost-events/{id}', [LostEventController::class, 'destroy']); // Delete by lost_event_id
});

// Export Lost Event
Route::get('/export-lost-event/{format}', [ExportRiskController::class, 'exportLostEvent'])->name('export.lost-event');
// Route untuk debug data
Route::get('/debug-risk-data', [ExportRiskController::class, 'debugRiskData']);

// Route untuk test export
Route::get('/test-export-risk/{format}', [ExportRiskController::class, 'testExport']);

// ===================== RISK HEADER ENTRY =====================
// Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/risk-header/{id}/entry', [TrRiskHeaderEntryController::class, 'index']);
// Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->post('/risk-header/{id}/entry/monthly/{monthlyId}', [TrRiskHeaderEntryController::class, 'store']);
// Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2,3'])->get('/risk-header-entry/{id}', [TrRiskHeaderEntryController::class, 'show']);
// Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->put('/risk-header-entry/{header_entry_id}/monthly/{monthlyId}', [TrRiskHeaderEntryController::class, 'update']);
// Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1'])->delete('/risk-header-entry/{id}', [TrRiskHeaderEntryController::class, 'destroy']);

// ===================== RISK MONTHLY ENTRY =====================
// Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->put('/risk-monthly-entry/{id}/quantitative', [TrRiskMonthlyEntryController::class, 'updateQuantitative']);
// Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->put('/risk-monthly-entry/{id}/residual', [TrRiskMonthlyEntryController::class, 'updateResidual']);
// Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->put('/risk-monthly-entry/{id}/residual-and-finalize', [TrRiskMonthlyEntryController::class, 'updateResidualAndFinalize']);
// Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,2'])->put('/risk-monthly-entry/{id}/bulk-update-quantitative', [TrRiskMonthlyEntryController::class, 'bulkUpdateQuantitative']);

// ===================== RCSA =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/rcsa-header', [TrRcsaHeaderController::class, 'index']);
    Route::post('/rcsa-header', [TrRcsaHeaderController::class, 'store']);
    Route::delete('/rcsa-header/{id}', [TrRcsaHeaderController::class, 'destroy']);
    Route::get('/rcsa-header/{id}', [TrRcsaHeaderController::class, 'show']);
    Route::get('/rcsa-sasaran', [TrRcsaHeaderController::class, 'sasaran']);
    Route::put('/rcsa-header/{id}', [TrRcsaHeaderController::class, 'update']);
    Route::post('/rcsa-header/{id}/submit', [TrRcsaHeaderController::class, 'submit']);
    Route::patch('/rcsa-header/{id}/approve', [TrRcsaHeaderController::class, 'approve']);
    Route::patch('/rcsa-header/{id}/reject', [TrRcsaHeaderController::class, 'reject']);
    Route::patch('/rcsa-header/{id}/is-main-risk', [TrRcsaHeaderController::class, 'updateIsMainRisk']);

});

// ===================== RENCANA INVESTASI =====================
Route::middleware(['auth:api'])->group(function () {
    Route::get('/investasi', [RencanaInvestasiController::class, 'index']);
    Route::post('/investasi', [RencanaInvestasiController::class, 'store']);
    Route::put('/investasi/{id}', [RencanaInvestasiController::class, 'update']);
    Route::get('/investasi/{id}', [RencanaInvestasiController::class, 'show']); 
    Route::patch('/investasi/{id}/approve', [RencanaInvestasiController::class, 'approve']);
    Route::patch('/investasi/{id}/reject',  [RencanaInvestasiController::class, 'reject']);
    // Route::delete('/rcsa-header/{id}', [RencanaInvestasiController::class, 'destroy']);
});


// check api
Route::get('/health-v8', function () {
    return response()->json([
        'status' => 'ok',
        'time' => now()->toDateTimeString(),
    ]);
});