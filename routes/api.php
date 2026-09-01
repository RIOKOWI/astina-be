<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ResidentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me/resident', [MeController::class, 'resident']);
        Route::get('/me/household', [MeController::class, 'household']);

        Route::get('/residents', [ResidentController::class, 'index']);
        Route::get('/residents/{resident}', [ResidentController::class, 'show']);

        Route::get('/households', [HouseholdController::class, 'index']);
        Route::get('/households/{household}', [HouseholdController::class, 'show']);

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
    });
});
