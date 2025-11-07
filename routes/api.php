<?php

use App\Http\Controllers\Api\SendMailController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Middleware\AntiSpamMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', AntiSpamMiddleware::class])->group(function () {
    Route::post('/send', [SendMailController::class, 'send'])->name('api.send');
    Route::post('/send/bulk', [SendMailController::class, 'sendBulk'])->name('api.send.bulk');

    // Compliance endpoints
    Route::prefix('compliance')->name('api.compliance.')->group(function () {
        Route::post('/export', [ComplianceController::class, 'exportData'])->name('export');
        Route::post('/delete', [ComplianceController::class, 'deleteData'])->name('delete');
        Route::get('/report/{domainId}', [ComplianceController::class, 'report'])->name('report');
    });
});

// Health check endpoint (public)
Route::get('/health', [HealthCheckController::class, 'api'])->name('api.health');
