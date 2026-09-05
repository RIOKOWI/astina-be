<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\DueController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\LetterController;
use App\Http\Controllers\Api\LetterTypeController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MyDueBillController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ResidentController;
use App\Http\Controllers\Api\SosController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', [HealthController::class, 'check']);
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::patch('/me', [AuthController::class, 'updateMe']);
            Route::patch('/password', [AuthController::class, 'changePassword']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me/resident', [MeController::class, 'resident']);
        Route::patch('/me/resident', [MeController::class, 'updateResident']);
        Route::get('/me/household', [MeController::class, 'household']);
        Route::get('/me/documents', [MeController::class, 'documents']);
        Route::post('/me/documents/ktp', [MeController::class, 'uploadKtp']);
        Route::post('/me/documents/kk', [MeController::class, 'uploadKk']);

        Route::get('/residents', [ResidentController::class, 'index']);
        Route::get('/residents/{resident}', [ResidentController::class, 'show']);
        Route::patch('/residents/{resident}', [ResidentController::class, 'update']);

        Route::get('/households', [HouseholdController::class, 'index']);
        Route::get('/households/{household}', [HouseholdController::class, 'show']);
        Route::patch('/households/{household}', [HouseholdController::class, 'update']);

        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);

        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('/device-tokens/{token}', [DeviceTokenController::class, 'destroy']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        Route::get('/activities', [ActivityController::class, 'index']);
        Route::post('/activities', [ActivityController::class, 'store']);
        Route::get('/activities/{activity}', [ActivityController::class, 'show']);
        Route::put('/activities/{activity}', [ActivityController::class, 'update']);
        Route::delete('/activities/{activity}', [ActivityController::class, 'destroy']);
        Route::post('/activities/{activity}/read', [ActivityController::class, 'read']);
        Route::post('/activities/{activity}/attachments', [ActivityController::class, 'storeAttachment']);
        Route::delete('/activities/{activity}/attachments/{attachment}', [ActivityController::class, 'destroyAttachment']);

        Route::get('/complaints', [ComplaintController::class, 'index']);
        Route::post('/complaints', [ComplaintController::class, 'store']);
        Route::get('/complaints/{complaint}', [ComplaintController::class, 'show']);
        Route::patch('/complaints/{complaint}/status', [ComplaintController::class, 'updateStatus']);
        Route::post('/complaints/{complaint}/attachments', [ComplaintController::class, 'storeAttachment']);
        Route::delete('/complaints/{complaint}/attachments/{attachment}', [ComplaintController::class, 'destroyAttachment']);
        Route::get('/complaints/{complaint}/comments', [ComplaintController::class, 'comments']);
        Route::post('/complaints/{complaint}/comments', [ComplaintController::class, 'storeComment']);

        Route::get('/assets', [AssetController::class, 'index']);
        Route::post('/assets', [AssetController::class, 'store']);
        Route::get('/assets/{asset}', [AssetController::class, 'show']);
        Route::put('/assets/{asset}', [AssetController::class, 'update']);
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy']);
        Route::post('/assets/{asset}/movements', [AssetController::class, 'storeMovement']);
        Route::get('/assets/{asset}/movements', [AssetController::class, 'movements']);

        Route::get('/sos/alerts/active', [SosController::class, 'active']);
        Route::post('/sos/alerts', [SosController::class, 'store']);
        Route::get('/sos/alerts/{sos}', [SosController::class, 'show']);
        Route::post('/sos/alerts/{sos}/resolve', [SosController::class, 'resolve']);
        Route::post('/sos/alerts/{sos}/responses', [SosController::class, 'storeResponse']);

        // Due management (RT only)
        Route::get('/dues', [DueController::class, 'index']);
        Route::post('/dues', [DueController::class, 'store']);
        Route::get('/dues/{due}', [DueController::class, 'show']);
        Route::put('/dues/{due}', [DueController::class, 'update']);
        Route::delete('/dues/{due}', [DueController::class, 'destroy']);
        Route::post('/dues/generate-bills', [DueController::class, 'generateDueBills']);
        Route::get('/dues/{due}/due-bills', [DueController::class, 'dueBills']);

        // My due bills (warga)
        Route::get('/my/due-bills', [MyDueBillController::class, 'index']);

        // Payment
        Route::get('/my/payments', [PaymentController::class, 'myPayments']);
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::get('/payments/pending', [PaymentController::class, 'pending']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
        Route::post('/payments/{payment}/proof', [PaymentController::class, 'uploadProof']);
        Route::post('/payments/{payment}/approve', [PaymentController::class, 'approve']);
        Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject']);

        // Letter Types
        Route::get('/letter-types', [LetterTypeController::class, 'index']);
        Route::get('/letter-types/{letter_type}', [LetterTypeController::class, 'show']);

        // My Letters (warga)
        Route::get('/my/letters', [LetterController::class, 'myLetters']);

        // Letters
        Route::post('/letters', [LetterController::class, 'store']);
        Route::get('/letters/pending', [LetterController::class, 'pending']);
        Route::get('/letters/{letter}', [LetterController::class, 'show']);
        Route::get('/letters/{letter}/document', [LetterController::class, 'document']);
        Route::post('/letters/{letter}/approve', [LetterController::class, 'approve']);
        Route::post('/letters/{letter}/reject', [LetterController::class, 'reject']);
        Route::post('/letters/{letter}/sign', [LetterController::class, 'sign']);
        Route::post('/letters/{letter}/stamp', [LetterController::class, 'stamp']);

        // Finance transparency
        Route::get('/finance/summary', [FinanceController::class, 'summary']);
        Route::get('/finance/transactions', [FinanceController::class, 'transactions']);
        Route::post('/finance/transactions', [FinanceController::class, 'storeTransaction']);
    });
});
