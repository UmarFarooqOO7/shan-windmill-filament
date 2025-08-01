<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{AuthController,TeamController};

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-otp-reset', [AuthController::class, 'verifyOtpAndReset']);


Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');

Route::post('/email/verify/resend', [AuthController::class, 'resendVerificationEmail'])
    ->middleware(['auth:sanctum']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// teams
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/teams', [TeamController::class, 'index']);
});