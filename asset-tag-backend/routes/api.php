<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ServerAccountController;
use Illuminate\Support\Facades\Route;


// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/asset_list_all', [AssetController::class, 'assetListAll']);
Route::get('/assets/{unique_code}/download-tag', [AssetController::class, 'downloadTag']);
Route::get('/assets/by-unique-code', [AssetController::class, 'getAssetByUniqueCode']);
Route::get('/assets/unique-code-suggestions', [AssetController::class, 'suggestUniqueCodes']);
Route::post('/assets/unique-code', [AssetController::class, 'saveUniqueCode']);

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
});

});

