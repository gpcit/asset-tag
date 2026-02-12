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
// PUBLIC ROUTES - Only things that don't need tracking
// =====================================================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:api')->group(function () {
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
// Public asset lookup (for barcode scanners, public displays, etc.)
Route::get('/asset_list_all', [AssetController::class, 'assetListAll']);
Route::get('/assets/{unique_code}/download-tag', [AssetController::class, 'downloadTag']);
Route::get('/assets/by-unique-code', [AssetController::class, 'getAssetByUniqueCode']);

// =====================================================
// AUTHENTICATED ROUTES - All user actions are tracked
// =====================================================
Route::middleware('auth:api')->group(function () {
    
    // Basic auth routes
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);

    // =====================================================
    // STAFF + ADMIN ROUTES - Most operations happen here
    // =====================================================
    Route::middleware('role:admin,staff')->group(function () {
        
        // Dashboard
        Route::get('/dashboard/summary', [AssetController::class, 'summary']);
        
        // Asset unique code routes - MUST be before assets/{asset}
        Route::prefix('assets')->group(function () {
            Route::get('unique-code-suggestions', [AssetController::class, 'suggestUniqueCodes']);
            Route::post('unique-code', [AssetController::class, 'saveUniqueCode']);
        });
        
        // Asset Histories
        Route::delete('/asset-histories/{id}', [AssetController::class, 'destroyHistory']);
        Route::put('/asset-histories/{id}', [AssetController::class, 'updateHistory']);
        
        // Assets (full CRUD) - MUST come after specific routes
        Route::apiResource('assets', AssetController::class);
        
        // Batch Tags - IMPORTANT: Specific routes BEFORE {id} routes
        Route::get('/batch-tags', [BatchTagController::class, 'index']);
        Route::post('/batch-tags/save', [BatchTagController::class, 'store']);
        Route::delete('/batch-tags/delete-printed', [BatchTagController::class, 'deletePrinted']);
        Route::post('/batch-tags/{id}/mark-printed', [BatchTagController::class, 'markPrinted']);
        Route::delete('/batch-tags/{id}', [BatchTagController::class, 'destroy']);
        
        // Categories (full CRUD)
        Route::apiResource('categories', CategoryController::class);
        
        // Companies (full CRUD)
        Route::apiResource('companies', CompanyController::class);
        
        // Employees (full CRUD)
        Route::get('employees', [EmployeeController::class, 'index']);
        Route::get('employees/all', [EmployeeController::class, 'all']);
        Route::post('employees', [EmployeeController::class, 'store']);
        Route::put('employees/{employee}', [EmployeeController::class, 'update']);
        Route::patch('employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']);
        
        // Server Accounts (full CRUD)
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