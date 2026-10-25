<?php

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CourierWebhookController;
use App\Http\Controllers\Api\V1\Customer\AccountController;
use App\Http\Controllers\Api\V1\Customer\AuthController;
use App\Http\Controllers\Api\V1\Customer\WishlistController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\LandingPageController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductQuestionController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SitemapController;
use App\Http\Controllers\Api\V1\TrackingController;
use App\Http\Middleware\AuthenticateCustomer;
use App\Http\Middleware\SetApiLocale;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(SetApiLocale::class)->group(function () {
    Route::get('/health', fn () => [
        'status' => 'ok',
        'app' => config('app.name'),
    ]);

    Route::get('/home', HomeController::class);
    Route::get('/settings', SettingsController::class);
    Route::get('/menus', MenuController::class);
    Route::get('/sitemap', SitemapController::class);
    Route::get('/tracking', [TrackingController::class, 'config']);
    Route::post('/tracking/events', [TrackingController::class, 'event'])->middleware('throttle:60,1,tracking');
    Route::get('/pages', [PageController::class, 'index']);
    Route::get('/pages/{slug}', [PageController::class, 'show']);
    Route::get('/landing-pages', [LandingPageController::class, 'index']);
    Route::get('/landing-pages/experiments', [LandingPageController::class, 'experiments']);
    Route::post('/landing-pages/{slug}/view', [LandingPageController::class, 'view'])->middleware('throttle:60,1,landing-view');
    Route::get('/landing-pages/{slug}', [LandingPageController::class, 'show']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->middleware('throttle:120,1,suggest');
    Route::get('/products/cross-sells', [ProductController::class, 'crossSells']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::get('/products/{slug}/related', [ProductController::class, 'related']);
    Route::get('/products/{slug}/reviews', [ReviewController::class, 'index']);
    Route::get('/products/{slug}/questions', [ProductQuestionController::class, 'index']);
    Route::post('/products/{slug}/questions', [ProductQuestionController::class, 'store'])->middleware('throttle:5,1,questions');

    Route::get('/checkout/options', [CheckoutController::class, 'options']);
    Route::post('/cart/quote', [CheckoutController::class, 'quote'])->middleware('throttle:60,1,quote');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:10,1,orders');
    Route::post('/orders/track', [OrderController::class, 'track'])->middleware('throttle:10,1,track');
    Route::get('/orders/{number}', [OrderController::class, 'show']);
    Route::post('/checkout/draft', [CheckoutController::class, 'draft'])->middleware('throttle:30,1,draft');
    Route::get('/checkout/recover/{token}', [CheckoutController::class, 'recover'])->middleware('throttle:30,1,recover');

    // Customer accounts. Called by the storefront's server, which keeps the token in an httpOnly cookie.
    Route::prefix('customer')->group(function () {
        Route::post('/otp', [AuthController::class, 'sendOtp'])->middleware('throttle:otp');
        Route::post('/verify', [AuthController::class, 'verify'])->middleware('throttle:otp-verify');

        Route::middleware(AuthenticateCustomer::class)->group(function () {
            Route::get('/', [AccountController::class, 'show']);
            Route::put('/', [AccountController::class, 'update']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/orders', [AccountController::class, 'orders']);
            Route::post('/addresses', [AccountController::class, 'storeAddress']);
            Route::put('/addresses/{address}', [AccountController::class, 'updateAddress'])->whereNumber('address');
            Route::delete('/addresses/{address}', [AccountController::class, 'destroyAddress'])->whereNumber('address');
            Route::get('/reviewable', [ReviewController::class, 'reviewable']);
            Route::post('/reviews', [ReviewController::class, 'store'])->middleware('throttle:10,1,reviews');
            Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:10,1,orders');
            Route::get('/points', [AccountController::class, 'points']);
            Route::get('/wishlist', [WishlistController::class, 'index']);
            Route::post('/wishlist/sync', [WishlistController::class, 'sync']);
            Route::put('/wishlist/{product}', [WishlistController::class, 'store'])->whereNumber('product');
            Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->whereNumber('product');
        });
    });

    Route::post('/webhooks/courier/{courier}', CourierWebhookController::class)->middleware('throttle:240,1,courier-webhook');
});
