<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();

})->middleware('auth:sanctum');

// User registration
Route::post('/register', [AuthController::class, 'register']);

// User login
Route::post('/login', [AuthController::class, 'login']);


Route::apiResource('categories', CategoryController::class);

Route::apiResource('companies', CompanyController::class);

Route::get('/assets', [AssetController::class, 'index']);
Route::get('/assets/by-unique-code', [AssetController::class, 'getAssetByUniqueCode']);
Route::get('/assets/unique-code-suggestions', [AssetController::class, 'suggestUniqueCodes']);



Route::get('/assets/{unique_code}/download-tag', [AssetController::class, 'downloadTag']);

Route::apiResource('assets', AssetController::class);

Route::get('/dashboard/summary', [AssetController::class, 'summary']);

Route::post('/assets/unique-code', [AssetController::class, 'saveUniqueCode']);