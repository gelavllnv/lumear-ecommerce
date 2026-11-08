<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\WaybillController;
use App\Http\Controllers\BuyerNotificationController;
use App\Http\Controllers\BuyerSavedProductController;
use App\Http\Controllers\{AccountController, AdminController, AdminTwoFactorController, AuthController, BuyerAddressController, BuyerOrderController, BuyerVoucherController, CartController, CheckoutController, GoogleOAuthController, MarketplaceController, MediaController, MessageController, SellerController, SellerFinanceController, SellerOnboardingController, SellerPromotionController, SellerReturnController, SellerStoreCategoryController, SupportController};
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

// LOGISTICS CONTROLLERS
use App\Http\Controllers\Logistics\LogisticsRegistrationController;
use App\Http\Controllers\Logistics\LogisticsAuthController;
use App\Http\Controllers\Logistics\LogisticsDashboardController;
use App\Http\Controllers\Logistics\LogisticsPickupController;
use App\Http\Controllers\Logistics\LogisticsOperationsController;
use App\Http\Controllers\Logistics\LogisticsIncomingApiController;
use App\Http\Controllers\Logistics\LogisticsSortingApiController;
use App\Http\Controllers\Logistics\LogisticsDeliveryApiController;
use App\Http\Controllers\Logistics\LogisticsRiderManagementApiController;
use App\Http\Controllers\Logistics\LogisticsMessagingApiController;
use App\Http\Controllers\Logistics\LogisticsReportsApiController;
use App\Http\Controllers\Logistics\LogisticsReportPdfController;
use App\Http\Controllers\Logistics\LogisticsAccountController;
use App\Http\Controllers\Logistics\LogisticsWaybillScannerController;
use App\Http\Controllers\Logistics\LogisticsNotificationController;
use App\Http\Controllers\Logistics\LogisticsSearchController;
use App\Http\Controllers\Logistics\LogisticsWalletController;
use App\Http\Controllers\Logistics\RiderPayrollController;
use App\Http\Controllers\Logistics\RiderRemittanceController;
use App\Http\Controllers\RiderTaskController;

// LOGISTICS HEAD ADMIN
use App\Http\Controllers\Logistics\LogisticsHeadAdminAuthController;
use App\Http\Controllers\Logistics\LogisticsHeadAdminDashboardController;
use App\Http\Controllers\Logistics\LogisticsHeadAdminApplicationController;

// MIDDLEWARE
use App\Http\Middleware\EnsureApprovedLogistics;
use App\Http\Middleware\EnsureLogisticsHeadAdmin;


Route::get('/', fn () => response()->view('landing')->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0'))->name('landing');
Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
Route::post('/register/buyer', [AuthController::class, 'registerBuyer'])->middleware('throttle:6,1')->name('register.buyer');
Route::post('/register/seller', [AuthController::class, 'registerSeller'])->middleware('throttle:6,1')->name('register.seller');
Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/auth/google', [GoogleOAuthController::class, 'redirect'])->middleware('throttle:10,1')->name('auth.google');
Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])->middleware('throttle:10,1')->name('auth.google.callback');
Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::patch('/account', [AccountController::class, 'update'])->middleware('throttle:6,1')->name('account.update');
});
Route::get('/email/verify', fn () => view('auth.verify-email'))->middleware('auth')->name('verification.notice');
Route::get('/seller/email-verification', [AuthController::class, 'sellerEmailVerificationNotice'])->middleware('auth')->name('seller.email-verification.notice');
Route::post('/seller/email-verification', [AuthController::class, 'verifySellerEmail'])->middleware(['auth', 'throttle:6,1'])->name('seller.email-verification.verify');
Route::post('/seller/email-verification/resend', [AuthController::class, 'resendSellerEmailCode'])->middleware(['auth', 'throttle:3,1'])->name('seller.email-verification.resend');
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) { $request->fulfill(); return redirect()->route($request->user()->isSeller() ? 'seller.status' : 'shop')->with('status', 'Email address verified.'); })->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');
Route::post('/email/verification-notification', function (Request $request) {
    try { $request->user()->sendEmailVerificationNotification(); } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $exception) { report($exception); return back()->withErrors(['email' => 'We could not send the verification email. Check the SMTP settings, then try again.']); }
    return back()->with('status', config('mail.default') === 'log' ? 'Verification email created locally. Email delivery is not configured, so no message was sent to your inbox.' : 'A new verification link was sent. Check your inbox and spam folder.');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');
Route::get('/home', [MarketplaceController::class, 'index'])->name('shop');
Route::get('/deals', [MarketplaceController::class, 'deals'])->name('deals.index');
Route::get('/product/{product:slug}', [MarketplaceController::class, 'show'])->name('products.show');
Route::get('/shop/{seller:slug}', [MarketplaceController::class, 'shop'])->name('shops.show');
Route::middleware(['auth', 'verified', 'buyer'])->group(function () {
    Route::get('/saved', [BuyerSavedProductController::class, 'index'])->name('buyer.saved');
    Route::post('/saved/{product}', [BuyerSavedProductController::class, 'store'])->name('buyer.saved.store');
    Route::delete('/saved/{product}', [BuyerSavedProductController::class, 'destroy'])->name('buyer.saved.destroy');
    Route::get('/vouchers', [BuyerVoucherController::class, 'index'])->name('buyer.vouchers.index');
    Route::get('/notifications', [BuyerNotificationController::class, 'index'])->name('buyer.notifications.index');
});
Route::get('/media/{path}', [MediaController::class, 'show'])->where('path', '.*')->name('media.show');
Route::middleware(['auth', 'verified', 'buyer'])->group(function () {
    Route::get('/cart', [CartController::class, 'index'])->name('cart');
    Route::post('/cart/{product}', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/cart/items/{item}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::post('/vouchers/{voucher}/claim', [BuyerVoucherController::class, 'claim'])->name('buyer.vouchers.claim');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout');
    Route::get('/orders', [CheckoutController::class, 'orders'])->name('orders');
    Route::post('/seller-orders/{sellerOrder}/confirm-receipt', [BuyerOrderController::class, 'confirmReceipt'])->name('buyer.orders.confirm-receipt');
    Route::post('/order-items/{orderItem}/review', [BuyerOrderController::class, 'storeReview'])->name('buyer.reviews.store');
    Route::post('/sellers/{seller}/messages', [MessageController::class, 'start'])->name('buyer.messages.start');
    Route::get('/delivery-address', [BuyerAddressController::class, 'create'])->name('buyer.address.create');
    Route::post('/delivery-address', [BuyerAddressController::class, 'store'])->middleware('throttle:6,1')->name('buyer.address.store');
});
Route::get('/chat', fn () => redirect()->route('support.index'))->name('chat');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/messages', [MessageController::class, 'inbox'])->name('messages.index');
    Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}', [MessageController::class, 'send'])->name('messages.send');
    Route::get('/help', [SupportController::class, 'index'])->name('support.index');
    Route::post('/help/ask', [SupportController::class, 'ask'])->middleware('throttle:12,1')->name('support.ask');
});
Route::middleware(['auth', 'admin'])->prefix('admin')->as('admin.')->group(function () { Route::get('/two-factor/setup', [AdminTwoFactorController::class, 'setup'])->name('2fa.setup'); Route::post('/two-factor/setup', [AdminTwoFactorController::class, 'confirm'])->name('2fa.confirm'); Route::get('/two-factor/challenge', [AdminTwoFactorController::class, 'challenge'])->name('2fa.challenge'); Route::post('/two-factor/challenge', [AdminTwoFactorController::class, 'verify'])->name('2fa.verify'); Route::middleware('admin.2fa')->group(function () { Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard'); Route::get('/reports/print', [AdminController::class, 'report'])->name('reports.print'); Route::patch('/sellers/{seller}', [AdminController::class, 'updateSeller'])->name('sellers.update'); Route::patch('/logistics/{provider}', [AdminController::class, 'updateLogistics'])->name('logistics.update'); Route::patch('/kyc/{kyc}', [AdminController::class, 'reviewKyc'])->name('kyc.review'); Route::get('/kyc/{kyc}/documents/{document}', [AdminController::class, 'downloadKycDocument'])->name('kyc.document'); Route::patch('/payouts/{payout}', [AdminController::class, 'updatePayout'])->name('payouts.update'); Route::patch('/products/{product}/moderation', [AdminController::class, 'moderateProduct'])->name('products.moderate'); }); });
Route::middleware('auth')->prefix('seller')->as('seller.')->group(function () { Route::get('/status', [SellerController::class, 'status'])->name('status'); Route::get('/onboarding', [SellerOnboardingController::class, 'show'])->name('onboarding'); Route::post('/onboarding/store-profile', [SellerOnboardingController::class, 'saveStoreProfile'])->middleware('throttle:6,1')->name('onboarding.store-profile'); Route::post('/onboarding/kyc', [SellerOnboardingController::class, 'submitKyc'])->middleware('throttle:6,1')->name('onboarding.kyc'); Route::post('/onboarding/payout-account', [SellerOnboardingController::class, 'savePayoutAccount'])->middleware('throttle:6,1')->name('onboarding.payout-account'); Route::post('/onboarding/agreement', [SellerOnboardingController::class, 'acceptAgreement'])->middleware('throttle:6,1')->name('onboarding.agreement'); Route::middleware('seller.approved')->group(function () { Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('dashboard'); Route::get('/products', [SellerController::class, 'products'])->name('products.index'); Route::get('/products/create', [SellerController::class, 'create'])->name('products.create'); Route::post('/products', [SellerController::class, 'store'])->name('products.store'); Route::get('/products/{product}/edit', [SellerController::class, 'edit'])->name('products.edit'); Route::put('/products/{product}', [SellerController::class, 'update'])->name('products.update'); Route::delete('/products/{product}', [SellerController::class, 'destroy'])->name('products.destroy'); Route::get('/inventory', [SellerController::class, 'inventory'])->name('inventory'); Route::post('/inventory/{variant}/adjust', [SellerController::class, 'adjustInventory'])->name('inventory.adjust'); Route::get('/orders', [SellerController::class, 'orders'])->name('orders'); Route::get('/orders/{sellerOrder}/waybill', [WaybillController::class, 'print'])->name('orders.waybill'); Route::patch('/orders/{sellerOrder}', [SellerController::class, 'updateOrder'])->name('orders.update'); Route::get('/messages', [MessageController::class, 'inbox'])->name('messages'); Route::get('/analytics', [SellerController::class, 'analytics'])->name('analytics'); Route::get('/earnings', [SellerFinanceController::class, 'index'])->name('earnings'); Route::get('/earnings/report', [SellerFinanceController::class, 'report'])->name('earnings.report'); Route::post('/earnings/payouts', [SellerFinanceController::class, 'requestPayout'])->middleware('throttle:6,1')->name('payouts.store'); Route::get('/reviews', [SellerController::class, 'reviews'])->name('reviews'); Route::post('/reviews/{review}/reply', [SellerController::class, 'saveReviewReply'])->name('reviews.reply'); Route::get('/settings', [SellerController::class, 'settings'])->name('settings'); Route::put('/settings', [SellerController::class, 'saveSettings'])->name('settings.update'); }); });
Route::middleware(['auth', 'seller.approved'])->prefix('seller')->as('seller.')->group(function () { Route::get('/returns', [SellerReturnController::class, 'index'])->name('returns.index'); Route::patch('/returns/{returnRequest}', [SellerReturnController::class, 'update'])->name('returns.update'); Route::get('/store-categories', [SellerStoreCategoryController::class, 'index'])->name('store-categories.index'); Route::post('/store-categories', [SellerStoreCategoryController::class, 'store'])->name('store-categories.store'); Route::put('/store-categories/{storeCategory}', [SellerStoreCategoryController::class, 'update'])->name('store-categories.update'); Route::delete('/store-categories/{storeCategory}', [SellerStoreCategoryController::class, 'destroy'])->name('store-categories.destroy'); Route::get('/promotions', [SellerPromotionController::class, 'index'])->name('promotions.index'); Route::post('/promotions/sales', [SellerPromotionController::class, 'storeSale'])->name('promotions.sales.store'); Route::post('/promotions/sales/{sale}/end', [SellerPromotionController::class, 'endSale'])->name('promotions.sales.end'); Route::post('/promotions/vouchers', [SellerPromotionController::class, 'storeVoucher'])->name('promotions.vouchers.store'); Route::post('/promotions/flash-sales', [SellerPromotionController::class, 'storeFlashSale'])->name('promotions.flash-sales.store'); Route::post('/promotions/bundles', [SellerPromotionController::class, 'storeBundle'])->name('promotions.bundles.store'); Route::patch('/promotions/{type}/{id}', [SellerPromotionController::class, 'update'])->name('promotions.update'); Route::post('/promotions/{type}/{id}/duplicate', [SellerPromotionController::class, 'duplicate'])->name('promotions.duplicate'); Route::post('/promotions/{type}/{id}/archive', [SellerPromotionController::class, 'archive'])->name('promotions.archive'); });


/*
|--------------------------------------------------------------------------
| LOGISTICS ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('logistics')
    ->name('logistics.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | LOGISTICS AUTHENTICATION
        |--------------------------------------------------------------------------
        */

        Route::get('/login', [
            LogisticsAuthController::class,
            'showLogin'
        ])->name('login');

        Route::post('/login', [
            LogisticsAuthController::class,
            'login'
        ])->name('login.submit');

        Route::post('/logout', [
            LogisticsAuthController::class,
            'logout'
        ])
            ->middleware('auth')
            ->name('logout');

        Route::get('/status', [
            LogisticsAuthController::class,
            'status'
        ])
            ->middleware('auth')
            ->name('status');


        /*
        |--------------------------------------------------------------------------
        | LOGISTICS REGISTRATION
        |--------------------------------------------------------------------------
        */

        Route::get('/register', function () {
            return view('logistics.auth.register');
        })->name('register');

        Route::get('/pending', function () {
            return view('logistics.auth.pending');
        })->name('pending');

        Route::post('/register/validate', [
            LogisticsRegistrationController::class,
            'validateStep'
        ])->name('register.validate');

        Route::post('/register/send-otp', [
            LogisticsRegistrationController::class,
            'sendOtp'
        ])->name('register.send-otp');

        Route::post('/register/verify-otp', [
            LogisticsRegistrationController::class,
            'verifyOtp'
        ])->name('register.verify-otp');

        Route::post('/register/submit', [
            LogisticsRegistrationController::class,
            'submit'
        ])->name('register.submit');

        Route::get('/municipality-availability', [
            LogisticsRegistrationController::class,
            'municipalityAvailability'
        ])->name('municipality.availability');


        /*
        |--------------------------------------------------------------------------
        | PHILIPPINE ADDRESS API
        |--------------------------------------------------------------------------
        |
        | These endpoints are used by registration forms.
        | They remain accessible before Logistics approval.
        |
        */

        Route::get('/address/provinces', function () {

            try {

                $response = Http::timeout(15)
                    ->acceptJson()
                    ->get('https://psgc.cloud/api/v2/provinces');

                if ($response->failed()) {

                    return response()->json([
                        'success' => false,
                        'message' => 'Unable to load provinces.',
                    ], 500);
                }

                return response()->json($response->json());

            } catch (\Exception $exception) {

                return response()->json([
                    'success' => false,
                    'message' => 'Address service is unavailable.',
                ], 500);
            }

        })->name('address.provinces');


        Route::get(
            '/address/provinces/{province}/cities-municipalities',
            function ($province) {

                try {

                    $response = Http::timeout(15)
                        ->acceptJson()
                        ->get(
                            'https://psgc.cloud/api/v2/provinces/'
                            . urlencode($province)
                            . '/cities-municipalities'
                        );

                    if ($response->failed()) {

                        return response()->json([
                            'success' => false,
                            'message' => 'Unable to load cities and municipalities.',
                        ], 500);
                    }

                    return response()->json($response->json());

                } catch (\Exception $exception) {

                    return response()->json([
                        'success' => false,
                        'message' => 'Address service is unavailable.',
                    ], 500);
                }

            }
        )->name('address.cities-municipalities');


        Route::get(
            '/address/cities-municipalities/{municipality}/barangays',
            function ($municipality) {

                try {

                    $response = Http::timeout(15)
                        ->acceptJson()
                        ->get(
                            'https://psgc.cloud/api/v2/cities-municipalities/'
                            . urlencode($municipality)
                            . '/barangays'
                        );

                    if ($response->failed()) {

                        return response()->json([
                            'success' => false,
                            'message' => 'Unable to load barangays.',
                        ], 500);
                    }

                    return response()->json($response->json());

                } catch (\Exception $exception) {

                    return response()->json([
                        'success' => false,
                        'message' => 'Address service is unavailable.',
                    ], 500);
                }

            }
        )->name('address.barangays');


        /*
        |--------------------------------------------------------------------------
        | PROTECTED LOGISTICS WORKSPACE
        |--------------------------------------------------------------------------
        |
        | All routes below require an authenticated and
        | approved Logistics provider account.
        |
        */

        Route::middleware([
            'auth',
            EnsureApprovedLogistics::class
        ])->group(function () {

            /*
            |--------------------------------------------------------------------------
            | DASHBOARD
            |--------------------------------------------------------------------------
            */

            Route::get('/dashboard', [
                LogisticsDashboardController::class,
                'index'
            ])->name('dashboard');


            /*
            |--------------------------------------------------------------------------
            | PICKUP REQUESTS
            |--------------------------------------------------------------------------
            */

            Route::get('/pickup-requests', [
                LogisticsPickupController::class,
                'index'
            ])->name('pickup.index');

            Route::get('/pickup-requests/{id}', [
                LogisticsPickupController::class,
                'show'
            ])
                ->whereNumber('id')
                ->name('pickup.show');

            Route::post('/pickup-requests/{id}/confirm', [
                LogisticsPickupController::class,
                'confirm'
            ])
                ->whereNumber('id')
                ->name('pickup.confirm');

            /*
            |--------------------------------------------------------------------------
            | INCOMING PARCELS
            |--------------------------------------------------------------------------
            */

            Route::get('/incoming-parcels', function () {
                return view('logistics.incoming.index');
            })->name('incoming.index');

            Route::post('/incoming-parcels/{id}/receive', [
                LogisticsOperationsController::class,
                'receive'
            ])
                ->whereNumber('id')
                ->name('ops.receive');


            /*
            |--------------------------------------------------------------------------
            | PARCEL SORTING
            |--------------------------------------------------------------------------
            */

            Route::get('/parcel-sorting', function () {
                return view('logistics.sorting.index');
            })->name('sorting.index');

            Route::post('/parcel-sorting/{id}/sort', [
                LogisticsOperationsController::class,
                'sort'
            ])
                ->whereNumber('id')
                ->name('ops.sort');


            /*
            |--------------------------------------------------------------------------
            | DELIVERY ASSIGNMENT
            |--------------------------------------------------------------------------
            */

            Route::get('/delivery-assignment', function () {
                return view('logistics.assignment.index');
            })->name('assignment.index');

            Route::post('/delivery-assignment/{id}/assign', [
                LogisticsOperationsController::class,
                'assign'
            ])
                ->whereNumber('id')
                ->name('ops.assign');


            /*
            |--------------------------------------------------------------------------
            | DELIVERY MONITORING
            |--------------------------------------------------------------------------
            */

            Route::get('/delivery-monitoring', function () {
                return view('logistics.monitoring.index');
            })->name('monitoring.index');


            /*
            |--------------------------------------------------------------------------
            | RIDER APPLICATIONS
            |--------------------------------------------------------------------------
            */

            Route::get('/rider-applications', function () {
                return view('logistics.riders.applications');
            })->name('riders.applications');

            Route::post('/rider-applications', [
                LogisticsOperationsController::class,
                'apply'
            ])->name('ops.apply');

            Route::post('/rider-applications/{id}/decide', [
                LogisticsOperationsController::class,
                'decide'
            ])
                ->whereNumber('id')
                ->name('ops.decide');


            /*
            |--------------------------------------------------------------------------
            | RIDER LIST
            |--------------------------------------------------------------------------
            */

            Route::get('/riders', function () {
                return view('logistics.riders.index');
            })->name('riders.index');

            Route::post('/riders/{id}/status', [
                LogisticsOperationsController::class,
                'riderStatus'
            ])
                ->whereNumber('id')
                ->name('ops.rider-status');


            /*
            |--------------------------------------------------------------------------
            | MESSAGES
            |--------------------------------------------------------------------------
            */

            Route::get('/messages', function () {
                return view('logistics.messages.index');
            })->name('messages.index');

            Route::post('/messages', [
                LogisticsOperationsController::class,
                'message'
            ])->name('ops.message');


            /*
            |--------------------------------------------------------------------------
            | REPORTS
            |--------------------------------------------------------------------------
            */

            Route::get('/reports', function () {
                return view('logistics.reports.index');
            })->name('reports.index');

            Route::get('/reports/export', [
                LogisticsOperationsController::class,
                'report'
            ])->name('ops.report');


            /*
            |--------------------------------------------------------------------------
            | MY ACCOUNT
            |--------------------------------------------------------------------------
            */

            Route::get('/account', function () {
                return view('logistics.account.index');
            })->name('account.index');

            Route::post('/account', [
                LogisticsOperationsController::class,
                'account'
            ])->name('ops.account');

            Route::post('/account/password', [
                LogisticsOperationsController::class,
                'password'
            ])->name('ops.password');


            /*
            |--------------------------------------------------------------------------
            | MY ACCOUNT API
            |--------------------------------------------------------------------------
            */

            Route::get('/api/account', [
                LogisticsAccountController::class,
                'show'
            ])->name('api.account.show');

            Route::put('/api/account/personal', [
                LogisticsAccountController::class,
                'updatePersonal'
            ])->name('api.account.personal');

            Route::put('/api/account/business', [
                LogisticsAccountController::class,
                'updateBusiness'
            ])->name('api.account.business');

            Route::put('/api/account/password', [
                LogisticsAccountController::class,
                'updatePassword'
            ])->name('api.account.password');

            Route::get('/api/account/activity', [
                LogisticsAccountController::class,
                'activity'
            ])->name('api.account.activity');

            Route::get('/api/account/documents/{type}', [
                LogisticsAccountController::class,
                'document'
            ])
                ->where('type', 'valid-id|business-permit')
                ->name('account.document');


            /*
            |--------------------------------------------------------------------------
            | QR WAYBILL SCANNER
            |--------------------------------------------------------------------------
            */

            Route::get('/scanner', [
                LogisticsWaybillScannerController::class,
                'index'
            ])->name('scanner.index');

            Route::post('/api/scanner/lookup', [
                LogisticsWaybillScannerController::class,
                'lookup'
            ])->name('api.scanner.lookup');


            /*
            |--------------------------------------------------------------------------
            | INCOMING PARCELS API
            |--------------------------------------------------------------------------
            */

            Route::get('/api/incoming-parcels', [
                LogisticsIncomingApiController::class,
                'index'
            ])->name('api.incoming.index');

            Route::post('/api/incoming-parcels/{id}/receive', [
                LogisticsIncomingApiController::class,
                'receive'
            ])
                ->whereNumber('id')
                ->name('api.incoming.receive');

            Route::post('/api/incoming-parcels/{id}/verify', [
                LogisticsIncomingApiController::class,
                'verify'
            ])
                ->whereNumber('id')
                ->name('api.incoming.verify');

            Route::post('/api/incoming-parcels/{id}/review', [
                LogisticsIncomingApiController::class,
                'review'
            ])
                ->whereNumber('id')
                ->name('api.incoming.review');


            /*
            |--------------------------------------------------------------------------
            | PARCEL SORTING API
            |--------------------------------------------------------------------------
            */

            Route::get('/api/sorting', [
                LogisticsSortingApiController::class,
                'index'
            ])->name('api.sorting.index');

            Route::post('/api/sorting/batches', [
                LogisticsSortingApiController::class,
                'createBatch'
            ])->name('api.sorting.batches');

            Route::post('/api/sorting/assign', [
                LogisticsSortingApiController::class,
                'assign'
            ])->name('api.sorting.assign');

            Route::post('/api/sorting/{id}/complete', [
                LogisticsSortingApiController::class,
                'complete'
            ])
                ->whereNumber('id')
                ->name('api.sorting.complete');


            /*
            |--------------------------------------------------------------------------
            | DELIVERY ASSIGNMENT API
            |--------------------------------------------------------------------------
            */

            Route::get('/api/delivery/assignment', [
                LogisticsDeliveryApiController::class,
                'assignment'
            ])->name('api.delivery.assignment');

            Route::post('/api/delivery/assign', [
                LogisticsDeliveryApiController::class,
                'assign'
            ])->name('api.delivery.assign');

            Route::post('/api/delivery/dispatch/{id}', [
                LogisticsDeliveryApiController::class,
                'dispatch'
            ])
                ->whereNumber('id')
                ->name('api.delivery.dispatch');


            /*
            |--------------------------------------------------------------------------
            | DELIVERY MONITORING API
            |--------------------------------------------------------------------------
            */

            Route::get('/api/delivery/monitoring', [
                LogisticsDeliveryApiController::class,
                'monitoring'
            ])->name('api.delivery.monitoring');

            Route::post('/api/delivery/monitoring/{id}/status', [
                LogisticsDeliveryApiController::class,
                'updateStatus'
            ])
                ->whereNumber('id')
                ->name('api.delivery.monitoring.status');


            /*
            |--------------------------------------------------------------------------
            | RIDER MANAGEMENT API
            |--------------------------------------------------------------------------
            */

            Route::get('/api/rider-management', [
                LogisticsRiderManagementApiController::class,
                'index'
            ])->name('api.riders.index');

            Route::post('/api/rider-management/applications/{id}/decide', [
                LogisticsRiderManagementApiController::class,
                'decide'
            ])
                ->whereNumber('id')
                ->name('api.riders.decide');

            Route::post('/api/rider-management/riders/{id}/status', [
                LogisticsRiderManagementApiController::class,
                'updateStatus'
            ])
                ->whereNumber('id')
                ->name('api.riders.status');


            /*
            |--------------------------------------------------------------------------
            | LOGISTICS MESSAGING API
            |--------------------------------------------------------------------------
            */

            Route::get('/api/messages', [
                LogisticsMessagingApiController::class,
                'index'
            ])->name('api.messages.index');

            Route::get('/api/messages/{contactId}', [
                LogisticsMessagingApiController::class,
                'show'
            ])
                ->whereNumber('contactId')
                ->name('api.messages.show');

            Route::post('/api/messages', [
                LogisticsMessagingApiController::class,
                'send'
            ])->name('api.messages.send');

            Route::post('/api/messages/{contactId}/read', [
                LogisticsMessagingApiController::class,
                'markRead'
            ])
                ->whereNumber('contactId')
                ->name('api.messages.read');


            /*
            |--------------------------------------------------------------------------
            | LOGISTICS REPORTS API
            |--------------------------------------------------------------------------
            */

            Route::get('/api/reports', [
                LogisticsReportsApiController::class,
                'index'
            ])->name('api.reports.index');

            Route::post('/api/reports/pdf', [
                LogisticsReportPdfController::class,
                'generate'
            ])->name('api.reports.pdf');

            Route::get('/api/reports/history', [
                LogisticsReportPdfController::class,
                'history'
            ])->name('api.reports.history');

            Route::get('/api/reports/history/{id}/download', [
                LogisticsReportPdfController::class,
                'download'
            ])
                ->whereNumber('id')
                ->name('api.reports.download');


            /*
            |--------------------------------------------------------------------------
            | LIVE LOGISTICS NOTIFICATIONS
            |--------------------------------------------------------------------------
            */

            Route::get('/api/notifications', [
                LogisticsNotificationController::class,
                'index'
            ])->name('api.notifications.index');

            Route::post('/api/notifications/read-all', [
                LogisticsNotificationController::class,
                'markAllRead'
            ])->name('api.notifications.read-all');

            Route::post('/api/notifications/{id}/read', [
                LogisticsNotificationController::class,
                'markRead'
            ])
                ->whereNumber('id')
                ->name('api.notifications.read');

            /*
            |--------------------------------------------------------------------------
            | LOGISTICS GLOBAL SEARCH API
            |--------------------------------------------------------------------------
            */

            Route::get('/api/search', [
                LogisticsSearchController::class,
                'index'
            ])->name('api.search');

            /*
            |--------------------------------------------------------------------------
            | LOGISTICS E-WALLET
            |--------------------------------------------------------------------------
            */

            Route::get('/wallet', [
                LogisticsWalletController::class,
                'index'
            ])->name('wallet.index');

            /* Rider handovers and finance use the same rider records as the
             * assignment workspace, so the web and mobile apps stay in sync. */
            Route::get('/tasks', [
                RiderTaskController::class,
                'page'
            ])->name('tasks.index');

            Route::get('/tasks/data', [
                RiderTaskController::class,
                'index'
            ])->name('tasks.data');

            Route::post('/tasks', [
                RiderTaskController::class,
                'assign'
            ])->name('tasks.store');

            Route::patch('/tasks/{taskId}', [
                RiderTaskController::class,
                'update'
            ])
                ->whereNumber('taskId')
                ->name('tasks.update');

            Route::get('/rider-payroll', [
                RiderPayrollController::class,
                'index'
            ])->name('riders.payroll.index');

            Route::post('/rider-payroll', [
                RiderPayrollController::class,
                'store'
            ])->name('riders.payroll.store');

            Route::get('/remittance', [
                RiderRemittanceController::class,
                'index'
            ])->name('remittance.index');

            Route::post('/remittance/{id}/verify', [
                RiderRemittanceController::class,
                'verify'
            ])
                ->whereNumber('id')
                ->name('remittance.verify');

            Route::post('/remittance/{id}/reject', [
                RiderRemittanceController::class,
                'reject'
            ])
                ->whereNumber('id')
                ->name('remittance.reject');

            Route::get('/remittance/{id}/receipt', [
                RiderRemittanceController::class,
                'receipt'
            ])
                ->whereNumber('id')
                ->name('remittance.receipt');

        });

    });


/*
|--------------------------------------------------------------------------
| LOGISTICS HEAD ADMIN
|--------------------------------------------------------------------------
*/

Route::prefix('logistics/head-admin')
    ->name('logistics.head-admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | HEAD ADMIN AUTHENTICATION
        |--------------------------------------------------------------------------
        */

        Route::get('/login', [
            LogisticsHeadAdminAuthController::class,
            'showLogin'
        ])->name('login');

        Route::post('/login', [
            LogisticsHeadAdminAuthController::class,
            'login'
        ])->name('login.submit');


        /*
        |--------------------------------------------------------------------------
        | PROTECTED HEAD ADMIN ROUTES
        |--------------------------------------------------------------------------
        */

        Route::middleware([
            'auth',
            EnsureLogisticsHeadAdmin::class
        ])->group(function () {

            /*
            |--------------------------------------------------------------------------
            | HEAD ADMIN DASHBOARD
            |--------------------------------------------------------------------------
            */

            Route::get('/dashboard', [
                LogisticsHeadAdminDashboardController::class,
                'index'
            ])->name('dashboard');


            /*
            |--------------------------------------------------------------------------
            | HEAD ADMIN LOGOUT
            |--------------------------------------------------------------------------
            */

            Route::post('/logout', [
                LogisticsHeadAdminAuthController::class,
                'logout'
            ])->name('logout');


            /*
            |--------------------------------------------------------------------------
            | APPLICATION MANAGEMENT
            |--------------------------------------------------------------------------
            */

            Route::get('/applications', [
                LogisticsHeadAdminApplicationController::class,
                'index'
            ])->name('applications.index');

            Route::get('/applications/{id}', [
                LogisticsHeadAdminApplicationController::class,
                'show'
            ])
                ->whereNumber('id')
                ->name('applications.show');

            Route::get('/applications/{id}/documents/{type}', [
                LogisticsHeadAdminApplicationController::class,
                'document'
            ])
                ->whereNumber('id')
                ->where('type', 'valid-id|business-permit')
                ->name('applications.document');

            Route::post('/applications/{id}/approve', [
                LogisticsHeadAdminApplicationController::class,
                'approve'
            ])
                ->whereNumber('id')
                ->name('applications.approve');

            Route::post('/applications/{id}/reject', [
                LogisticsHeadAdminApplicationController::class,
                'reject'
            ])
                ->whereNumber('id')
                ->name('applications.reject');

        });

    });
