<?php

use App\Http\Controllers\Logistics\CodForwardingController;
use App\Http\Middleware\EnsureApprovedLogistics;
use Illuminate\Support\Facades\Route;

Route::prefix('logistics')
    ->name('logistics.')
    ->middleware([
        'auth',
        EnsureApprovedLogistics::class,
    ])
    ->group(function () {
        Route::get('/remittance/forward-to-admin', [
            CodForwardingController::class,
            'index',
        ])->name('cod-forwarding.index');

        Route::post('/remittance/forward-to-admin', [
            CodForwardingController::class,
            'store',
        ])
            ->middleware('throttle:10,1')
            ->name('cod-forwarding.store');
    });
