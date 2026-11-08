<?php

use App\Http\Controllers\Logistics\LogisticsReturnController;
use App\Http\Middleware\EnsureApprovedLogistics;
use Illuminate\Support\Facades\Route;

Route::prefix('logistics')
    ->name('logistics.')
    ->middleware([
        'auth',
        EnsureApprovedLogistics::class,
    ])
    ->group(function () {
        Route::get(
            '/returns',
            [LogisticsReturnController::class, 'index']
        )->name('returns.index');

        Route::post(
            '/returns/{id}/schedule',
            [LogisticsReturnController::class, 'schedule']
        )
            ->whereNumber('id')
            ->name('returns.schedule');

        Route::post(
            '/returns/{id}/receive',
            [LogisticsReturnController::class, 'receive']
        )
            ->whereNumber('id')
            ->name('returns.receive');

        Route::post(
            '/returns/{id}/hand-to-seller',
            [LogisticsReturnController::class, 'handToSeller']
        )
            ->whereNumber('id')
            ->name('returns.hand-to-seller');

        Route::get(
            '/returns/report',
            [LogisticsReturnController::class, 'report']
        )->name('returns.report');
    });
