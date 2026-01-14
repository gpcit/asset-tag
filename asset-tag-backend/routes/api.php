<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();

})->middleware('auth:sanctum');

// User registration
Route::post('/register', [AuthController::class, 'register']);

// User login
Route::post('/login', [AuthController::class, 'login']);
