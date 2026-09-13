<?php
// Paste into routes/web.php (adjust to Route::resource/controllers once you wire real logic)

use Illuminate\Support\Facades\Route;

// Root now serves the login page — this is what makes the login page the landing page.
Route::get('/', fn () => view('buyer.auth.login'))->name('login');
Route::get('/register', fn () => view('buyer.auth.register'))->name('register');

// The shop itself moved to /home since / is taken by login.
Route::get('/home',    fn () => view('buyer.home'))->name('home');
Route::get('/product/{slug}', fn ($slug) => view('buyer.products.show'));
Route::get('/category/{slug}', function ($slug) {
    $category = \App\Support\BuyerCategories::find($slug);
    abort_if(!$category, 404);
    return view('buyer.category.show', [
        'category' => $category,
        'products' => \App\Support\BuyerProducts::forCategory($slug),
    ]);
});
Route::get('/cart',    fn () => view('buyer.cart'));
Route::get('/orders',  fn () => view('buyer.orders.index'));
Route::get('/chat',    fn () => view('buyer.chat'));
Route::get('/account', fn () => view('buyer.account'));
Route::get('/search', function () {
    $query = request('q', '');
    return view('buyer.search', [
        'query' => $query,
        'products' => \App\Support\BuyerProducts::search($query),
    ]);
});

// POST handlers for the auth forms — wire these to real controllers, then send the user
// to route('home') on success instead of just returning a view:
// Route::post('/register', [RegisteredBuyerController::class, 'store']);
// Route::post('/login',    [AuthenticatedSessionController::class, 'store']);

// -----------------------------------------------------------------------------------
// IMPORTANT — this is the quick version for a project with no auth wired up yet.
// Right now anyone can still type /home, /cart, etc. straight into the address bar
// and skip login entirely, since nothing is actually checking whether they're signed in.
//
// Once you build real authentication (Laravel Breeze/Fortify, or your own guard),
// swap the block above for something like this instead, so guests are FORCED
// through login rather than just defaulting there:
//
// Route::get('/', fn () => redirect()->route('login'));
//
// Route::middleware('auth')->group(function () {
//     Route::get('/home',    fn () => view('buyer.home'))->name('home');
//     Route::get('/cart',    fn () => view('buyer.cart'));
//     Route::get('/orders',  fn () => view('buyer.orders.index'));
//     Route::get('/chat',    fn () => view('buyer.chat'));
//     Route::get('/account', fn () => view('buyer.account'));
// });
//
// That "auth" middleware is what actually redirects a not-logged-in visitor back to
// /login if they try to reach a protected page directly — the current version above
// just puts login at the front door, it doesn't lock the other doors yet.
// -----------------------------------------------------------------------------------