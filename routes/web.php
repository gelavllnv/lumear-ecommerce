<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('landing');
Route::get('/login',       fn () => view('buyer.auth.login'))->name('login');
Route::get('/register',    fn () => view('buyer.auth.register'));
Route::get('/home',        fn () => view('buyer.home'))->name('shop');
Route::get('/product/{slug}', fn ($slug) => view('buyer.products.show'));
Route::get('/cart',        fn () => view('buyer.cart'));
Route::get('/orders',      fn () => view('buyer.orders.index'));
Route::get('/chat',        fn () => view('buyer.chat'));
Route::get('/account',     fn () => view('buyer.account'));

// POST handlers for the forms above — wire these to real controllers:
// Route::post('/register', [RegisteredBuyerController::class, 'store']);
// Route::post('/login',    [AuthenticatedSessionController::class, 'store']);
