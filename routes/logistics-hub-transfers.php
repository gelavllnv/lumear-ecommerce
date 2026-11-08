<?php

use App\Http\Controllers\Logistics\LogisticsHubTransferController;
use App\Http\Middleware\EnsureApprovedLogistics;
use Illuminate\Support\Facades\Route;

Route::prefix('logistics')
    ->name('logistics.')
    ->middleware(['auth', EnsureApprovedLogistics::class])
    ->group(function () {
        Route::get(
            '/hub-transfers',
            [LogisticsHubTransferController::class, 'index']
        )->name('transfers.index');

        Route::post(
            '/hub-transfers/tasks/{id}/dispatch',
            [LogisticsHubTransferController::class, 'dispatch']
        )
            ->whereNumber('id')
            ->name('transfers.dispatch');

        Route::post(
            '/hub-transfers/tasks/{id}/receive',
            [LogisticsHubTransferController::class, 'receiveTask']
        )
            ->whereNumber('id')
            ->name('transfers.receive-task');

        Route::post(
            '/hub-transfers/{id}/receive',
            [LogisticsHubTransferController::class, 'receive']
        )
            ->whereNumber('id')
            ->name('transfers.receive');
    });
