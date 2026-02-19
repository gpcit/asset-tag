<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ServerAccountController;
use App\Http\Controllers\BatchTagController;
use App\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

// =====================================================
// PUBLIC ROUTES
// =====================================================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/asset_list_all', [AssetController::class, 'assetListAll']);
Route::get('/assets/{control_number}/download-tag', [AssetController::class, 'downloadTag']);

// =====================================================
// AUTHENTICATED ROUTES
// =====================================================
Route::middleware('auth:api')->group(function () {

    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/me', [AuthController::class, 'me']);

    // =====================================================
    // STAFF + ADMIN ROUTES
    // =====================================================
    Route::middleware('role:admin,staff')->group(function () {

        // Dashboard
        Route::get('/dashboard/summary', [AssetController::class, 'summary']);

        // ✅ IMPORTANT: All specific /assets/* routes MUST come before apiResource
        Route::get('/assets/by-unique-code', [AssetController::class, 'getAssetByUniqueCode']);
        Route::get('/assets/unique-code-suggestions', [AssetController::class, 'suggestUniqueCodes']);
        Route::post('/assets/{id}/generate-code', [AssetController::class, 'generateControlNumber']);

        // Asset Histories
        Route::delete('/asset-histories/{id}', [AssetController::class, 'destroyHistory']);
        Route::put('/asset-histories/{id}', [AssetController::class, 'updateHistory']);

        // ✅ apiResource MUST come AFTER all specific /assets/* routes
        Route::apiResource('assets', AssetController::class);

        // Batch Tags - specific routes BEFORE {id}
        Route::get('/batch-tags', [BatchTagController::class, 'index']);
        Route::post('/batch-tags/save', [BatchTagController::class, 'store']);
        Route::delete('/batch-tags/delete-printed', [BatchTagController::class, 'deletePrinted']);
        Route::post('/batch-tags/{id}/mark-printed', [BatchTagController::class, 'markPrinted']);
        Route::delete('/batch-tags/{id}', [BatchTagController::class, 'destroy']);

        // Categories
        Route::apiResource('categories', CategoryController::class);

        // Companies
        Route::apiResource('companies', CompanyController::class);

        // Employees
        Route::get('employees', [EmployeeController::class, 'index']);
        Route::get('employees/all', [EmployeeController::class, 'all']);
        Route::post('employees', [EmployeeController::class, 'store']);
        Route::put('employees/{employee}', [EmployeeController::class, 'update']);
        Route::patch('employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']);

        // Server Accounts
        Route::apiResource('servers', ServerAccountController::class);
    });

    // =====================================================
    // ADMIN-ONLY ROUTES
    // =====================================================
    Route::middleware('role:admin')->group(function () {

        // User management
        Route::get('/users', [AuthController::class, 'index']);
        Route::patch('/users/{user}/role', [AuthController::class, 'updateRole']);

        // Activity logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show']);
    });
});