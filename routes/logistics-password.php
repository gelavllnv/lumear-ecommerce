<?php

use App\Http\Controllers\Logistics\LogisticsPasswordResetController;
use Illuminate\Support\Facades\Route;

Route::prefix('logistics')
    ->name('logistics.')
    ->group(function () {
        Route::get('/forgot-password', [
            LogisticsPasswordResetController::class,
            'requestForm',
        ])->name('password.request');

        Route::post('/forgot-password', [
            LogisticsPasswordResetController::class,
            'sendLink',
        ])
            ->middleware('throttle:5,1')
            ->name('password.email');

        Route::get('/reset-password/{token}', [
            LogisticsPasswordResetController::class,
            'resetForm',
        ])->name('password.reset');

        Route::post('/reset-password', [
            LogisticsPasswordResetController::class,
            'reset',
        ])
            ->middleware('throttle:10,1')
            ->name('password.update');
    });
