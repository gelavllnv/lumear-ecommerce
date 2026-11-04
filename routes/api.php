<?php

use App\Http\Controllers\RiderMobileApiController;
use App\Http\Middleware\AuthenticateRiderApi;
use Illuminate\Support\Facades\Route;

Route::prefix('rider')->group(function (): void {
    Route::post('/auth/register', [RiderMobileApiController::class, 'register'])->middleware('throttle:4,1');
    Route::post('/auth/login', [RiderMobileApiController::class, 'login'])->middleware('throttle:6,1');

    Route::middleware(AuthenticateRiderApi::class)->group(function (): void {
        Route::get('/me', [RiderMobileApiController::class, 'me']);
        Route::patch('/me', [RiderMobileApiController::class, 'updateProfile']);
        Route::patch('/availability', [RiderMobileApiController::class, 'availability']);
        Route::get('/shipments', [RiderMobileApiController::class, 'shipments']);
        Route::patch('/shipments/{shipmentId}/status', [RiderMobileApiController::class, 'updateShipment']);
        Route::post('/auth/logout', [RiderMobileApiController::class, 'logout']);
    });
});
