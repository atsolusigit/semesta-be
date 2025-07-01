<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\MstDepartmentController;
use App\Http\Middleware\RoleAccessMiddleware;
use App\Http\Controllers\RoleController;
use App\Models\MstRole;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RolePageController;
use App\Http\Controllers\RiskHeaderController;
use App\Http\Controllers\UploadController;

// ============================
//  Auth Routes (tanpa token)
// ============================

Route::post('/register', [AuthController::class, 'register']); // Registrasi user baru (status = pending)
Route::post('/login', [AuthController::class, 'login']);       // Login dan menerima JWT token
Route::middleware('auth:api')->get('/profile', [AuthController::class, 'profile']);   // Cek profile user

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


// ============================
//  Super Admin Only (role_id = 1)
// ============================
Route::middleware(['auth:api', RoleAccessMiddleware::class . ':1,6'])->group(function () {
    Route::delete('/users/{id}', [UserController::class, 'destroy']); // Hapus user
    Route::delete('/knowledge-base/{id}', [KnowledgeBaseController::class, 'destroy']); // Hapus knowledge base
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
    //  Users
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::get('/users/dropdowns', [UserController::class, 'dropdownData']);

    //  Knowledge Base
    Route::post('/knowledge-base', [KnowledgeBaseController::class, 'store']);
    Route::put('/knowledge-base/{id}', [KnowledgeBaseController::class, 'update']);

    //  Departments
    Route::get('/   ', [MstDepartmentController::class, 'index']);
    Route::get('/departments/{id}', [MstDepartmentController::class, 'show']);
    Route::post('/departments', [MstDepartmentController::class, 'store']);
    Route::put('/departments/{id}', [MstDepartmentController::class, 'update']);
    Route::delete('/departments/{id}', [MstDepartmentController::class, 'destroy']);
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
Route::middleware('auth:api')->group(function () {
    // Halaman
    Route::get('page', [PageController::class, 'index']);
    Route::post('page', [PageController::class, 'store']);

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
