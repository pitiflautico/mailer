<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\ConsentController;

Route::get('/', function () {
    return redirect('/admin');
});

// Unsubscribe routes (public)
Route::prefix('unsubscribe')->name('unsubscribe.')->group(function () {
    Route::get('/{token}', [UnsubscribeController::class, 'show'])->name('confirm');
    Route::post('/{token}', [UnsubscribeController::class, 'process'])->name('process');
    Route::post('/one-click/{token}', [UnsubscribeController::class, 'oneClick'])->name('one-click');
});

// Consent routes (public)
Route::prefix('consent')->name('consent.')->group(function () {
    Route::get('/{email}', [ConsentController::class, 'show'])->name('show');
    Route::post('/grant', [ConsentController::class, 'grant'])->name('grant');
    Route::get('/verify/{token}', [ConsentController::class, 'verify'])->name('verify');
    Route::post('/revoke', [ConsentController::class, 'revoke'])->name('revoke');
});
