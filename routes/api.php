<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\HeroSlideController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Clozy API is up and running',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// ---------------------------------------------------------------------------
// Auth — public
// ---------------------------------------------------------------------------

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
});

// ---------------------------------------------------------------------------
// Storefront — public reads, and guest checkout
// ---------------------------------------------------------------------------

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);

Route::get('/menus/handle/{handle}', [MenuController::class, 'showByHandle']);

Route::get('/hero-slides', [HeroSlideController::class, 'index']);

Route::get('/settings', [SettingsController::class, 'show']);

// Uploaded images — public so they render on the storefront for anonymous
// visitors (product/category photos), same as any other static asset.
Route::get('/media/file/{filename}', [MediaController::class, 'serve']);

Route::post('/orders', [OrderController::class, 'store']);
Route::post('/orders/track', [OrderController::class, 'track']);

Route::post('/discounts/validate', [DiscountController::class, 'validateCode']);

Route::post('/products/{product}/reviews', [ReviewController::class, 'store']);

// ---------------------------------------------------------------------------
// Dashboard — admin or editor only
// ---------------------------------------------------------------------------

Route::middleware(['auth:sanctum', 'role:admin,editor'])->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{id}/edit', [ProductController::class, 'edit']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);

    Route::post('/analytics/ai-insights', [AnalyticsController::class, 'aiInsights']);

    Route::get('/menus', [MenuController::class, 'index']);
    Route::post('/menus', [MenuController::class, 'store']);
    Route::get('/menus/{id}', [MenuController::class, 'show']);
    Route::put('/menus/{id}', [MenuController::class, 'update']);
    Route::delete('/menus/{id}', [MenuController::class, 'destroy']);

    Route::get('/media', [MediaController::class, 'index']);
    Route::post('/media', [MediaController::class, 'store']);
    Route::delete('/media/{id}', [MediaController::class, 'destroy']);

    Route::put('/hero-slides', [HeroSlideController::class, 'update']);

    Route::get('/settings/admin', [SettingsController::class, 'adminShow']);
    Route::put('/settings', [SettingsController::class, 'update']);

    Route::get('/sms/logs', [SmsController::class, 'logs']);
    Route::get('/sms/recipients', [SmsController::class, 'recipients']);
    Route::post('/sms/promotional', [SmsController::class, 'sendPromotional']);

    Route::get('/discounts', [DiscountController::class, 'index']);
    Route::post('/discounts', [DiscountController::class, 'store']);
    Route::put('/discounts/{id}', [DiscountController::class, 'update']);
    Route::delete('/discounts/{id}', [DiscountController::class, 'destroy']);

    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::patch('/reviews/{id}/status', [ReviewController::class, 'updateStatus']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
});

// ---------------------------------------------------------------------------
// Dashboard — admin only
// ---------------------------------------------------------------------------

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});
