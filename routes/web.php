<?php

use App\Http\Controllers\Dev\LetterPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

if (app()->environment(['local', 'testing'])) {
    Route::prefix('dev/letters')->group(function () {
        Route::get('/surat-pengantar', [LetterPreviewController::class, 'show']);
        Route::get('/surat-pengantar/reference', [LetterPreviewController::class, 'reference']);
        Route::get('/surat-pengantar/compare', [LetterPreviewController::class, 'compare']);
    });
}
