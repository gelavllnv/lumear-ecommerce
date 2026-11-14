<?php

use App\Http\Controllers\RiderMobileApiController;
use App\Http\Controllers\ChatApiController;
use App\Http\Controllers\LumearAiController;
use App\Http\Controllers\RiderChatApiController;
use App\Http\Middleware\AuthenticateRiderApi;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\RiderTaskController;
use App\Http\Controllers\RiderAdminApiController;
use App\Http\Middleware\AuthenticateMobileApi;
use App\Http\Controllers\MobileCommerceController;
use App\Http\Controllers\MobileLogisticsController;
use App\Http\Controllers\Logistics\LogisticsDeliveryApiController;
use App\Http\Controllers\Logistics\LogisticsRiderManagementApiController;
use App\Http\Controllers\BuyerOrderController;
use App\Http\Controllers\CartController;
use App\Models\Product;
use Illuminate\Http\Request;

Route::middleware(AuthenticateMobileApi::class.':buyer')->group(function (): void {
    Route::get('/buyer/workspace', [MobileCommerceController::class, 'workspace']);
    Route::post('/cart', [MobileCommerceController::class, 'cartAdd']);
    Route::patch('/cart/{item}', [CartController::class, 'update']);
    Route::delete('/cart/{item}', [CartController::class, 'destroy']);
    Route::post('/checkout/quote', [MobileCommerceController::class, 'quote']);
    Route::post('/checkout', [MobileCommerceController::class, 'checkout']);
    Route::post('/addresses', [MobileCommerceController::class, 'saveAddress']);
    Route::patch('/addresses/{id}', [MobileCommerceController::class, 'saveAddress']);
    Route::delete('/addresses/{id}', function (Request $request, int $id) {
        $request->user()->addresses()->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    });
    Route::post('/orders/{sellerOrder}/receive', [BuyerOrderController::class, 'confirmReceipt']);
    Route::post('/wishlist/{product}', function (Request $request, Product $product) {
        abort_unless($product->is_active, 404);
        $request->user()->savedProducts()->firstOrCreate(['product_id' => $product->id]);
        return response()->json(['success' => true]);
    });
    Route::delete('/wishlist/{id}', function (Request $request, int $id) {
        $request->user()->savedProducts()->where('product_id', $id)->delete();
        return response()->json(['success' => true]);
    });
    Route::post('/notifications/{id?}', function (Request $request, ?string $id = null) {
        $request->user()->notifications()->when($id, fn ($q) => $q->whereKey($id))->update(['read_at' => now()]);
        return response()->json(['success' => true]);
    });
    Route::patch('/me', function (Request $request) {
        $request->user()->update($request->validate(['name' => ['required', 'string', 'max:255']]));
        return response()->json(['success' => true]);
    });
});

Route::prefix('chat')->middleware(AuthenticateMobileApi::class.':any')->group(function (): void {
    Route::get('/threads', [ChatApiController::class, 'threads']);
    Route::post('/threads', [ChatApiController::class, 'openSeller']);
    Route::get('/threads/{conversation}/messages', [ChatApiController::class, 'messages']);
    Route::post('/threads/{conversation}/messages', [ChatApiController::class, 'send']);
});

Route::post('/auth/login', [MobileAuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/auth/google', [MobileAuthController::class, 'googleLogin'])->middleware('throttle:6,1');
Route::post('/auth/forgot-password/send-code', [MobileAuthController::class, 'sendPasswordResetCode'])->middleware('throttle:3,1');
Route::post('/auth/forgot-password/verify-code', [MobileAuthController::class, 'verifyPasswordResetCode'])->middleware('throttle:6,1');
Route::post('/auth/forgot-password/reset', [MobileAuthController::class, 'resetForgottenPassword'])->middleware('throttle:4,1');
Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->middleware(AuthenticateMobileApi::class.':any');
Route::prefix('logistics')->middleware(AuthenticateMobileApi::class.':logistics')->group(function (): void {
    Route::get('/workspace', [MobileLogisticsController::class, 'workspace']);
    Route::get('/riders', [LogisticsRiderManagementApiController::class, 'index']);
    Route::get('/riders/{id}/documents/{type}', [LogisticsRiderManagementApiController::class, 'riderDocument']);
    Route::patch('/applications/{id}', [LogisticsRiderManagementApiController::class, 'decide']);
    Route::get('/applications/{id}/documents/{type}', [LogisticsRiderManagementApiController::class, 'applicationDocument']);
    Route::patch('/riders/{id}', [LogisticsRiderManagementApiController::class, 'updateStatus']);
    Route::post('/delivery-assignment', [LogisticsDeliveryApiController::class, 'assign']);
    Route::post('/deliveries/{id}/dispatch', [LogisticsDeliveryApiController::class, 'dispatch']);
    Route::get('/deliveries', [LogisticsDeliveryApiController::class, 'monitoring']);
    Route::get('/tasks', [RiderTaskController::class, 'index']);
    Route::post('/tasks', [RiderTaskController::class, 'assign']);
    Route::patch('/tasks/{taskId}', [RiderTaskController::class, 'update']);
});
Route::prefix('seller')->middleware(AuthenticateMobileApi::class.':seller')->group(function (): void {
    Route::get('/workspace', [MobileCommerceController::class, 'sellerWorkspace']);
    Route::post('/orders/{sellerOrder}/{action}', [MobileCommerceController::class, 'sellerAction']);
    Route::get('/tasks', [RiderTaskController::class, 'index']);
    Route::patch('/tasks/{taskId}', [RiderTaskController::class, 'update']);
});

Route::prefix('rider')->group(function (): void {
    Route::post('/admin/login', [RiderAdminApiController::class, 'login'])->middleware('throttle:6,1');
    Route::middleware(AuthenticateMobileApi::class.':rider_admin')->prefix('admin')->group(function (): void {
        Route::get('/riders', [RiderAdminApiController::class, 'riders']);
        Route::get('/bug-reports', [RiderAdminApiController::class, 'reports']);
        Route::patch('/bug-reports/{id}', [RiderAdminApiController::class, 'updateReport']);
        Route::get('/bug-reports/{id}/evidence/{file}', [RiderAdminApiController::class, 'evidence']);
    });
    Route::get('/logistics-hubs', [RiderMobileApiController::class, 'logisticsHubs'])->middleware('throttle:60,1');
    Route::post('/auth/register', [RiderMobileApiController::class, 'register'])->middleware('throttle:4,1');
    Route::post('/auth/login', [RiderMobileApiController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/auth/google', [RiderMobileApiController::class, 'googleLogin'])->middleware('throttle:6,1');
    Route::post('/auth/forgot-password/send-code', [RiderMobileApiController::class, 'sendPasswordResetCode'])->middleware('throttle:3,1');
    Route::post('/auth/forgot-password/verify-code', [RiderMobileApiController::class, 'verifyPasswordResetCode'])->middleware('throttle:6,1');
    Route::post('/auth/forgot-password/reset', [RiderMobileApiController::class, 'resetForgottenPassword'])->middleware('throttle:4,1');

    Route::middleware(AuthenticateRiderApi::class)->group(function (): void {
        Route::post('/ai/chat', [LumearAiController::class, 'riderAsk'])->middleware('throttle:12,1');
        Route::get('/tasks', [RiderTaskController::class, 'index']);
        Route::patch('/tasks/{taskId}', [RiderTaskController::class, 'update']);
        Route::get('/me', [RiderMobileApiController::class, 'me']);
        Route::post('/bug-reports', [RiderAdminApiController::class, 'submitReport'])->middleware('throttle:10,1');
        Route::patch('/me', [RiderMobileApiController::class, 'updateProfile']);
        Route::get('/documents/{type}', [RiderMobileApiController::class, 'document']);
        Route::post('/documents', [RiderMobileApiController::class, 'updateDocuments']);
        Route::patch('/password', [RiderMobileApiController::class, 'updatePassword']);
        Route::patch('/availability', [RiderMobileApiController::class, 'availability']);
        Route::get('/shipments', [RiderMobileApiController::class, 'shipments']);
        Route::post('/shipments/{shipmentId}/proof', [RiderMobileApiController::class, 'uploadShipmentProof']);
        Route::get('/shipments/{shipmentId}/proof', [RiderMobileApiController::class, 'shipmentProof']);
        Route::get('/payroll', [RiderMobileApiController::class, 'payroll']);
        Route::post('/payroll/sync-deliveries', [RiderMobileApiController::class, 'syncPayrollDeliveries']);
        Route::post('/shipments/{shipmentId}/pickup-scan', [RiderMobileApiController::class, 'confirmShipmentPickupScan']);
        Route::post('/shipments/{shipmentId}/delay-report', [RiderMobileApiController::class, 'reportShipmentDelay'])->middleware('throttle:10,1');
        Route::get('/remittances', [RiderMobileApiController::class, 'remittances']);
        Route::get('/notifications', [RiderMobileApiController::class, 'riderNotifications']);
        Route::get('/logistics-chat', [RiderChatApiController::class, 'logisticsChat']);
        Route::get('/logistics-chat/messages', [RiderChatApiController::class, 'logisticsMessages']);
        Route::post('/logistics-chat/messages', [RiderChatApiController::class, 'sendLogisticsMessage']);
        Route::get('/chats', [RiderChatApiController::class, 'threads']);
        Route::delete('/chats/{conversation}', [RiderChatApiController::class, 'destroy']);
        Route::get('/chats/{conversation}/messages', [RiderChatApiController::class, 'messages']);
        Route::post('/chats/{conversation}/messages', [RiderChatApiController::class, 'send']);
        Route::post('/remittances/sync', [RiderMobileApiController::class, 'syncCodRemittances'])->middleware('throttle:30,1');
        Route::post('/remittances', [RiderMobileApiController::class, 'submitRemittance'])->middleware('throttle:10,1');
        Route::get('/remittances/{remittanceId}/receipt', [RiderMobileApiController::class, 'remittanceReceipt']);
        Route::patch('/shipments/{shipmentId}/status', [RiderMobileApiController::class, 'updateShipment']);
        Route::post('/auth/logout', [RiderMobileApiController::class, 'logout']);
    });
});
