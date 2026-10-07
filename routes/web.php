<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;


/*
|--------------------------------------------------------------------------
| BUYER AUTHENTICATION ROUTES
|--------------------------------------------------------------------------
*/

// Buyer Login / Landing Page
Route::get('/', function () {

    return view('buyer.auth.login');

})->name('login');


// Buyer Registration
Route::get('/register', function () {

    return view('buyer.auth.register');

})->name('register');


/*
|--------------------------------------------------------------------------
| BUYER SHOP ROUTES
|--------------------------------------------------------------------------
*/

// Buyer Home
Route::get('/home', function () {

    return view('buyer.home');

})->name('home');


// Product Details
Route::get('/product/{slug}', function ($slug) {

    return view('buyer.products.show');

});


// Product Category
Route::get('/category/{slug}', function ($slug) {

    $category =
        \App\Support\BuyerCategories::find($slug);


    abort_if(!$category, 404);


    return view('buyer.category.show', [

        'category' => $category,

        'products' =>
            \App\Support\BuyerProducts::forCategory(
                $slug
            ),

    ]);

});


// Buyer Cart
Route::get('/cart', function () {

    return view('buyer.cart');

});


// Buyer Orders
Route::get('/orders', function () {

    return view('buyer.orders.index');

});


// Buyer Chat
Route::get('/chat', function () {

    return view('buyer.chat');

});


// Buyer Account
Route::get('/account', function () {

    return view('buyer.account');

});


// Buyer Search
Route::get('/search', function () {

    $query =
        request('q', '');


    return view('buyer.search', [

        'query' => $query,

        'products' =>
            \App\Support\BuyerProducts::search(
                $query
            ),

    ]);

});


/*
|--------------------------------------------------------------------------
| LOGISTICS / SORTING CENTER ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('logistics')
    ->name('logistics.')
    ->group(function () {


        /*
        |--------------------------------------------------------------------------
        | Logistics Authentication
        |--------------------------------------------------------------------------
        */

        Route::get('/login', function () {

            return view(
                'logistics.auth.login'
            );

        })->name('login');



        Route::get('/register', function () {

            return view(
                'logistics.auth.register'
            );

        })->name('register');



        Route::get('/pending', function () {

            return view(
                'logistics.auth.pending'
            );

        })->name('pending');



        /*
        |--------------------------------------------------------------------------
        | Logistics Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', function () {

            return view(
                'logistics.dashboard'
            );

        })->name('dashboard');

        Route::get('/pickup-requests', function () {

            return view(
                'logistics.pickup.index'
            );

        })->name('pickup.index');


        /*
        |--------------------------------------------------------------------------
        | PHILIPPINE ADDRESS API
        |--------------------------------------------------------------------------
        |
        | Browser
        |    ↓
        | Laravel
        |    ↓
        | PSGC Cloud
        |
        */



        /*
        |--------------------------------------------------------------------------
        | Provinces
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/address/provinces',
            function () {

                try {

                    $response =
                        Http::timeout(15)
                            ->acceptJson()
                            ->get(
                                'https://psgc.cloud/api/v2/provinces'
                            );


                    if ($response->failed()) {

                        return response()->json(
                            [
                                'success' => false,

                                'message' =>
                                    'Unable to load provinces.',
                            ],
                            500
                        );

                    }


                    return response()->json(
                        $response->json()
                    );


                } catch (\Exception $exception) {

                    return response()->json(
                        [
                            'success' => false,

                            'message' =>
                                'Address service is unavailable.',

                            'error' =>
                                $exception->getMessage(),
                        ],
                        500
                    );

                }

            }
        )->name('address.provinces');



        /*
        |--------------------------------------------------------------------------
        | Cities / Municipalities
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/address/provinces/{province}/cities-municipalities',
            function ($province) {

                try {

                    $response =
                        Http::timeout(15)
                            ->acceptJson()
                            ->get(
                                'https://psgc.cloud/api/v2/provinces/'
                                . urlencode($province)
                                . '/cities-municipalities'
                            );


                    if ($response->failed()) {

                        return response()->json(
                            [
                                'success' => false,

                                'message' =>
                                    'Unable to load cities and municipalities.',
                            ],
                            500
                        );

                    }


                    return response()->json(
                        $response->json()
                    );


                } catch (\Exception $exception) {

                    return response()->json(
                        [
                            'success' => false,

                            'message' =>
                                'Address service is unavailable.',

                            'error' =>
                                $exception->getMessage(),
                        ],
                        500
                    );

                }

            }
        )->name(
            'address.cities-municipalities'
        );



        /*
        |--------------------------------------------------------------------------
        | Barangays
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/address/cities-municipalities/{municipality}/barangays',
            function ($municipality) {

                try {

                    $response =
                        Http::timeout(15)
                            ->acceptJson()
                            ->get(
                                'https://psgc.cloud/api/v2/cities-municipalities/'
                                . urlencode($municipality)
                                . '/barangays'
                            );


                    if ($response->failed()) {

                        return response()->json(
                            [
                                'success' => false,

                                'message' =>
                                    'Unable to load barangays.',
                            ],
                            500
                        );

                    }


                    return response()->json(
                        $response->json()
                    );


                } catch (\Exception $exception) {

                    return response()->json(
                        [
                            'success' => false,

                            'message' =>
                                'Address service is unavailable.',

                            'error' =>
                                $exception->getMessage(),
                        ],
                        500
                    );

                }

            }
        )->name('address.barangays');


    });


/*
|--------------------------------------------------------------------------
| DEVELOPMENT NOTE
|--------------------------------------------------------------------------
|
| Buyer and Logistics authentication are still temporary.
|
| Current Logistics modules:
|
| ✓ Login
| ✓ Registration
| ✓ Philippine address API
| ✓ Pending Approval
| ✓ Dashboard
|
| Next modules:
|
| - Pickup Requests
| - Incoming Parcels
| - Parcel Sorting
| - Delivery Assignment
| - Delivery Monitoring
| - Rider Applications
| - Rider Management
| - Messages
| - Reports
| - Account Management
|
*/