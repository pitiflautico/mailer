<?php

use App\Http\Controllers\Api\SendMailController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/send', [SendMailController::class, 'send'])->name('api.send');
    Route::post('/send/bulk', [SendMailController::class, 'sendBulk'])->name('api.send.bulk');
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('api.health');
