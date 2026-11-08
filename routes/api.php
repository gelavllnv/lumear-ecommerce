<?php

use App\Http\Controllers\Logistics\CodForwardingBridgeController;
use App\Http\Middleware\EnsureRemittanceBridgeToken;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/cod-forwardings')
    ->name('internal.cod-forwardings.')
    ->middleware([
        'throttle:60,1',
        EnsureRemittanceBridgeToken::class,
    ])
    ->group(function () {
        Route::get('/', [
            CodForwardingBridgeController::class,
            'index',
        ])->name('index');

        Route::get('/{reference}', [
            CodForwardingBridgeController::class,
            'show',
        ])->name('show');

        Route::post('/{reference}/complete', [
            CodForwardingBridgeController::class,
            'complete',
        ])->name('complete');

        Route::post('/{reference}/reject', [
            CodForwardingBridgeController::class,
            'reject',
        ])->name('reject');
    });
