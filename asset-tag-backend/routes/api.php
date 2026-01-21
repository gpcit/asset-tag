<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ServerAccountController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Categories
Route::apiResource('categories', CategoryController::class);

// Companies
Route::apiResource('companies', CompanyController::class);

// Assets
Route::get('/assets/by-unique-code', [AssetController::class, 'getAssetByUniqueCode']);
Route::get('/assets/unique-code-suggestions', [AssetController::class, 'suggestUniqueCodes']);
Route::get('/asset_list', [AssetController::class, 'assetList']);
Route::get('/asset_list_all', [AssetController::class, 'assetListAll']);
Route::get('/assets/{unique_code}/download-tag', [AssetController::class, 'downloadTag']);
Route::post('/assets/unique-code', [AssetController::class, 'saveUniqueCode']);
Route::get('/dashboard/summary', [AssetController::class, 'summary']);

Route::apiResource('assets', AssetController::class);

// Server Accounts
Route::apiResource('servers', ServerAccountController::class);