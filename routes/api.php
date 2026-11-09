<?php

use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\MobileCommerceController;
use App\Http\Controllers\ChatApiController;
use App\Http\Controllers\BuyerOrderController;
use App\Http\Controllers\MobileLogisticsController;
use App\Http\Controllers\RiderAdminApiController;
use App\Http\Controllers\RiderChatApiController;
use App\Http\Controllers\RiderMobileApiController;
use App\Http\Controllers\RiderTaskController;
use App\Http\Controllers\Logistics\LogisticsDeliveryApiController;
use App\Http\Controllers\Logistics\LogisticsRiderManagementApiController;
use App\Http\Middleware\AuthenticateRiderApi;
use Illuminate\Support\Facades\Route;

/* Shared Laravel API for the Seller and Logistics mobile apps. */
Route::prefix('auth')->group(function (): void {
    Route::post('/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:6,1');
    Route::post('/google', [MobileAuthController::class, 'googleLogin'])
        ->middleware('throttle:10,1');
    Route::post('/forgot-password/send-code', [MobileAuthController::class, 'sendPasswordResetCode'])
        ->middleware('throttle:3,1');
    Route::post('/forgot-password/verify-code', [MobileAuthController::class, 'verifyPasswordResetCode'])
        ->middleware('throttle:6,1');
    Route::post('/forgot-password/reset', [MobileAuthController::class, 'resetForgottenPassword'])
        ->middleware('throttle:4,1');
});

Route::middleware('mobile.api:any')->group(function (): void {
    Route::post('/auth/logout', [MobileAuthController::class, 'logout']);
    Route::get('/me', [MobileCommerceController::class, 'me']);
    Route::patch('/me', [MobileCommerceController::class, 'updateMe']);

    // Shared buyer/seller app API. These are aliases used by the existing
    // Flutter client and write to the exact tables used by the web portal.
    Route::get('/addresses', [MobileCommerceController::class, 'addresses'])->middleware('mobile.api:buyer');
    Route::post('/addresses', [MobileCommerceController::class, 'saveAddress'])->middleware('mobile.api:buyer');
    Route::patch('/addresses/{id}', [MobileCommerceController::class, 'saveAddress'])->middleware('mobile.api:buyer')->whereNumber('id');
    Route::delete('/addresses/{id}', [MobileCommerceController::class, 'destroyAddress'])->middleware('mobile.api:buyer')->whereNumber('id');
    Route::post('/cart', [MobileCommerceController::class, 'cartAdd'])->middleware('mobile.api:buyer');
    Route::patch('/cart/{item}', [MobileCommerceController::class, 'updateCart'])->middleware('mobile.api:buyer')->whereNumber('item');
    Route::delete('/cart/{item}', [MobileCommerceController::class, 'destroyCart'])->middleware('mobile.api:buyer')->whereNumber('item');
    Route::get('/wishlist', [MobileCommerceController::class, 'wishlist'])->middleware('mobile.api:buyer');
    Route::post('/wishlist/{product}', [MobileCommerceController::class, 'saveProduct'])->middleware('mobile.api:buyer')->whereNumber('product');
    Route::delete('/wishlist/{product}', [MobileCommerceController::class, 'removeSavedProduct'])->middleware('mobile.api:buyer')->whereNumber('product');
    Route::get('/vouchers', [MobileCommerceController::class, 'vouchers'])->middleware('mobile.api:buyer');
    Route::post('/vouchers/{voucher}/claim', [MobileCommerceController::class, 'claimVoucher'])->middleware('mobile.api:buyer')->whereNumber('voucher');

    Route::get('/chat/threads', [ChatApiController::class, 'threads']);
    Route::post('/chat/threads', [ChatApiController::class, 'openSeller'])->middleware('mobile.api:buyer');
    Route::get('/chat/threads/{conversation}/messages', [ChatApiController::class, 'messages'])->whereNumber('conversation');
    Route::post('/chat/threads/{conversation}/messages', [ChatApiController::class, 'send'])->whereNumber('conversation');
});

Route::prefix('seller')->middleware('mobile.api:seller')->group(function (): void {
    Route::get('/workspace', [MobileCommerceController::class, 'sellerWorkspace']);
    Route::post('/orders/{sellerOrder}/{action}', [MobileCommerceController::class, 'sellerAction'])
        ->whereIn('action', ['accept', 'pack', 'prepare', 'ship', 'pickup', 'cancel']);
    Route::post('/products', [MobileCommerceController::class, 'sellerSaveProduct']);
    Route::patch('/products/{product}', [MobileCommerceController::class, 'sellerSaveProduct'])->whereNumber('product');
    Route::patch('/products/{product}/archive', [MobileCommerceController::class, 'archiveSellerProduct'])->whereNumber('product');
    Route::patch('/shop', [MobileCommerceController::class, 'updateSellerShop']);
});

Route::prefix('buyer')->middleware('mobile.api:buyer')->group(function (): void {
    Route::get('/workspace', [MobileCommerceController::class, 'workspace']);
    Route::post('/cart', [MobileCommerceController::class, 'cartAdd']);
    Route::post('/checkout/quote', [MobileCommerceController::class, 'quote']);
    Route::post('/checkout', [MobileCommerceController::class, 'checkout']);
    Route::post('/addresses', [MobileCommerceController::class, 'saveAddress']);
    Route::patch('/addresses/{id}', [MobileCommerceController::class, 'saveAddress'])
        ->whereNumber('id');
    Route::post('/orders/{sellerOrder}/cancel', [BuyerOrderController::class, 'cancel']);
});

Route::prefix('logistics')->middleware('mobile.api:logistics')->group(function (): void {
    Route::get('/workspace', [MobileLogisticsController::class, 'workspace']);
    Route::get('/riders', [LogisticsRiderManagementApiController::class, 'index']);
    Route::patch('/applications/{id}', [LogisticsRiderManagementApiController::class, 'decide'])
        ->whereNumber('id');
    Route::patch('/riders/{id}', [LogisticsRiderManagementApiController::class, 'updateStatus'])
        ->whereNumber('id');
    Route::get('/deliveries', [MobileLogisticsController::class, 'deliveries']);
    Route::post('/delivery-assignment', [LogisticsDeliveryApiController::class, 'assign']);
    Route::post('/deliveries/{id}/dispatch', [LogisticsDeliveryApiController::class, 'dispatch'])
        ->whereNumber('id');
    Route::post('/tasks', [RiderTaskController::class, 'assign']);
    Route::patch('/tasks/{taskId}', [RiderTaskController::class, 'update'])
        ->whereNumber('taskId');
});

Route::prefix('rider')->group(function (): void {
    Route::post('/auth/register', [RiderMobileApiController::class, 'register'])->middleware('throttle:4,1');
    Route::post('/auth/login', [RiderMobileApiController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/auth/google', [RiderMobileApiController::class, 'googleLogin'])->middleware('throttle:10,1');
    Route::post('/auth/forgot-password/send-code', [RiderMobileApiController::class, 'sendPasswordResetCode'])->middleware('throttle:3,1');
    Route::post('/auth/forgot-password/verify-code', [RiderMobileApiController::class, 'verifyPasswordResetCode'])->middleware('throttle:6,1');
    Route::post('/auth/forgot-password/reset', [RiderMobileApiController::class, 'resetForgottenPassword'])->middleware('throttle:4,1');

    Route::middleware(AuthenticateRiderApi::class)->group(function (): void {
        Route::get('/me', [RiderMobileApiController::class, 'me']);
        Route::patch('/me', [RiderMobileApiController::class, 'updateProfile']);
        Route::patch('/me/password', [RiderMobileApiController::class, 'updatePassword']);
        Route::get('/me/documents/{type}', [RiderMobileApiController::class, 'document'])
            ->whereIn('type', ['driver_license', 'or_document', 'cr_document']);
        Route::post('/me/documents', [RiderMobileApiController::class, 'updateDocuments']);
        Route::patch('/availability', [RiderMobileApiController::class, 'availability']);
        Route::get('/shipments', [RiderMobileApiController::class, 'shipments']);
        Route::patch('/shipments/{shipmentId}/status', [RiderMobileApiController::class, 'updateShipment']);
        Route::post('/shipments/{shipmentId}/pickup-scan', [RiderMobileApiController::class, 'confirmShipmentPickupScan'])
            ->whereNumber('shipmentId');
        Route::post('/shipments/{shipmentId}/proof', [RiderMobileApiController::class, 'uploadShipmentProof'])
            ->whereNumber('shipmentId');
        Route::get('/shipments/{shipmentId}/proof', [RiderMobileApiController::class, 'shipmentProof'])
            ->whereNumber('shipmentId');
        Route::post('/shipments/{shipmentId}/delay', [RiderMobileApiController::class, 'reportShipmentDelay'])
            ->whereNumber('shipmentId');
        Route::get('/payroll', [RiderMobileApiController::class, 'payroll']);
        Route::post('/payroll/sync', [RiderMobileApiController::class, 'syncPayrollDeliveries']);
        Route::get('/remittances', [RiderMobileApiController::class, 'remittances']);
        Route::post('/remittances/sync', [RiderMobileApiController::class, 'syncCodRemittances']);
        Route::get('/remittances/{remittanceId}/receipt', [RiderMobileApiController::class, 'remittanceReceipt'])
            ->whereNumber('remittanceId');
        Route::post('/remittances', [RiderMobileApiController::class, 'submitRemittance']);
        Route::get('/notifications', [RiderMobileApiController::class, 'riderNotifications']);
        Route::get('/tasks', [RiderTaskController::class, 'index']);
        Route::patch('/tasks/{taskId}', [RiderTaskController::class, 'update'])
            ->whereNumber('taskId');
        Route::get('/chat/logistics', [RiderChatApiController::class, 'logisticsChat']);
        Route::get('/chat/logistics/messages', [RiderChatApiController::class, 'logisticsMessages']);
        Route::post('/chat/logistics/messages', [RiderChatApiController::class, 'sendLogisticsMessage']);
        Route::get('/chat', [RiderChatApiController::class, 'threads']);
        Route::get('/chat/{conversation}/messages', [RiderChatApiController::class, 'messages'])
            ->whereNumber('conversation');
        Route::post('/chat/{conversation}/messages', [RiderChatApiController::class, 'send'])
            ->whereNumber('conversation');
        Route::delete('/chat/{conversation}', [RiderChatApiController::class, 'destroy'])
            ->whereNumber('conversation');
        Route::post('/bug-reports', [RiderAdminApiController::class, 'submitReport']);
        Route::post('/auth/logout', [RiderMobileApiController::class, 'logout']);
    });
});

Route::prefix('rider/admin')->group(function (): void {
    Route::post('/auth/login', [RiderAdminApiController::class, 'login'])
        ->middleware('throttle:6,1');

    Route::middleware('mobile.api:rider_admin')->group(function (): void {
        Route::get('/riders', [RiderAdminApiController::class, 'riders']);
        Route::get('/bug-reports', [RiderAdminApiController::class, 'reports']);
        Route::patch('/bug-reports/{id}', [RiderAdminApiController::class, 'updateReport'])
            ->whereNumber('id');
        Route::get('/bug-reports/{id}/evidence/{file}', [RiderAdminApiController::class, 'evidence'])
            ->whereNumber('id');
    });
});
