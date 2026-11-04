<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

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

// LOGISTICS HEAD ADMIN
use App\Http\Controllers\Logistics\LogisticsHeadAdminAuthController;
use App\Http\Controllers\Logistics\LogisticsHeadAdminDashboardController;
use App\Http\Controllers\Logistics\LogisticsHeadAdminApplicationController;

// MIDDLEWARE
use App\Http\Middleware\EnsureApprovedLogistics;
use App\Http\Middleware\EnsureLogisticsHeadAdmin;


/*
|--------------------------------------------------------------------------
| BUYER AUTHENTICATION
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('buyer.auth.login');
})->name('login');

Route::get('/register', function () {
    return view('buyer.auth.register');
})->name('register');


/*
|--------------------------------------------------------------------------
| BUYER SHOP
|--------------------------------------------------------------------------
*/

Route::get('/home', function () {
    return view('buyer.home');
})->name('home');

Route::get('/product/{slug}', function ($slug) {
    return view('buyer.products.show');
});

Route::get('/category/{slug}', function ($slug) {

    $category = \App\Support\BuyerCategories::find($slug);

    abort_if(!$category, 404);

    return view('buyer.category.show', [
        'category' => $category,
        'products' => \App\Support\BuyerProducts::forCategory($slug),
    ]);
});

Route::get('/cart', function () {
    return view('buyer.cart');
});

Route::get('/orders', function () {
    return view('buyer.orders.index');
});

Route::get('/chat', function () {
    return view('buyer.chat');
});

Route::get('/account', function () {
    return view('buyer.account');
});

Route::get('/search', function () {

    $query = request('q', '');

    return view('buyer.search', [
        'query' => $query,
        'products' => \App\Support\BuyerProducts::search($query),
    ]);
});

Route::get('/saved', function () {
    return view('buyer.saved');
})->name('buyer.saved');


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
        ])->name('logout');


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
        | PROTECTED LOGISTICS WORKSPACE
        |--------------------------------------------------------------------------
        |
        | All modules require an authenticated and approved
        | Logistics provider account.
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


            /*
            |--------------------------------------------------------------------------
            | INCOMING PARCELS
            | ORIGINAL UI
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
            | ORIGINAL UI
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
            | ORIGINAL UI
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
            | ORIGINAL UI
            |--------------------------------------------------------------------------
            */

            Route::get('/delivery-monitoring', function () {
                return view('logistics.monitoring.index');
            })->name('monitoring.index');


            /*
            |--------------------------------------------------------------------------
            | RIDER APPLICATIONS
            | ORIGINAL UI
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
            | ORIGINAL UI
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
            | ORIGINAL UI
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
            | ORIGINAL UI
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
            | ORIGINAL UI
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

        });

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
        | PHILIPPINE ADDRESS API
        |--------------------------------------------------------------------------
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
