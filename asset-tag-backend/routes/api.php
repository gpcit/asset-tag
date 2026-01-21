<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CORS HEADERS (GLOBAL FOR API)
|--------------------------------------------------------------------------
*/
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

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
