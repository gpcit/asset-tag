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


// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/asset_list_all', [AssetController::class, 'assetListAll']);
Route::get('/assets/{unique_code}/download-tag', [AssetController::class, 'downloadTag']);
Route::get('/assets/by-unique-code', [AssetController::class, 'getAssetByUniqueCode']);
Route::get('/assets/unique-code-suggestions', [AssetController::class, 'suggestUniqueCodes']);
Route::post('/assets/unique-code', [AssetController::class, 'saveUniqueCode']);
Route::get('/batch-tags', [BatchTagController::class, 'index']);
Route::post('/batch-tags/save', [BatchTagController::class, 'store']);
Route::delete('/batch-tags/{id}', [BatchTagController::class, 'destroy']);
Route::post('/batch-tags/{id}/mark-printed', [BatchTagController::class, 'markPrinted']);
Route::delete('/asset-histories/{id}', [AssetController::class, 'destroyHistory']);
Route::put('/asset-histories/{id}', [AssetController::class, 'updateHistory']);
// Employee
Route::get('employees', [EmployeeController::class, 'index']);      // paginated list
Route::get('employees/all', [EmployeeController::class, 'all']);   // full list for dropdowns
Route::post('employees', [EmployeeController::class, 'store']);    // create
Route::put('employees/{employee}', [EmployeeController::class, 'update']); // update
Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']); // delete
Route::patch('employees/{employee}', [EmployeeController::class, 'update']);

// Any authenticated user
Route::middleware('auth:api')->group(function () {
    Route::get('/user', [AuthController::class, 'me']); // no role middleware
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);

    // Staff + Admin routes
    Route::middleware('role:admin,staff')->group(function () {
        Route::get('/dashboard/summary', [AssetController::class, 'summary']);
        Route::apiResource('assets', AssetController::class);
        
    });

    Route::middleware('role:admin,staff')->group(function () {
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('companies', CompanyController::class);
        Route::apiResource('servers', ServerAccountController::class);
    });

    Route::middleware(['auth:api', 'role:admin'])->group(function () {
        Route::get('/users', [AuthController::class, 'index']);
        Route::patch('/users/{user}/role', [AuthController::class, 'updateRole']);
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show']);
    });
});
