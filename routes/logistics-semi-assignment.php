<?php

use App\Http\Controllers\Logistics\LogisticsSemiAutomaticAssignmentController;
use App\Http\Middleware\EnsureApprovedLogistics;
use Illuminate\Support\Facades\Route;

Route::prefix('logistics')
    ->name('logistics.')
    ->middleware([
        'auth',
        EnsureApprovedLogistics::class,
    ])
    ->group(function () {
        Route::get('/delivery-suggestions', [
            LogisticsSemiAutomaticAssignmentController::class,
            'index',
        ])->name('semi-assignment.index');

        Route::post('/delivery-suggestions/preview', [
            LogisticsSemiAutomaticAssignmentController::class,
            'preview',
        ])->name('semi-assignment.preview');

        Route::post('/delivery-suggestions/confirm', [
            LogisticsSemiAutomaticAssignmentController::class,
            'confirm',
        ])->name('semi-assignment.confirm');
    });
