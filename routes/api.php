<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{AuthController,TeamController};
use App\Http\Controllers\Api\{LeadController,ChatController};

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

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // teams
    Route::get('/teams', [TeamController::class, 'index']);
    Route::get('/teams/{team}', [TeamController::class, 'show']);
    // leads
    Route::apiResource('leads', LeadController::class);

    // chat
    Route::get('/chat/teams', [ChatController::class, 'chattedTeams']);
    Route::get('/chat/users', [ChatController::class, 'chattedUsers']);
    Route::get('chat/all_users', [ChatController::class, 'getAllUsers']);
    Route::get('chat/all_teams', [ChatController::class, 'getAllTeams']);

    Route::get('/chat/team/{teamId}', [ChatController::class, 'teamChat']);
    Route::get('/chat/user/{userId}', [ChatController::class, 'userChat']);

    Route::post('/chat/{chatId}/send_message', [ChatController::class, 'sendMessage']);
    Route::delete('chat/message/{id}', [ChatController::class, 'deleteMessage']);
    Route::delete('/chat/{chatId}/delete', [ChatController::class, 'deleteChat']);

    Route::post('chat/notifications/{id}/mark-read', [ChatController::class, 'markNotificationAsRead']);
    Route::post('/chat/markAllNotificationsAsRead', [ChatController::class, 'markAllNotificationsAsRead']);
});