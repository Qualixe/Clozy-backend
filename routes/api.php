<?php

use App\Http\Controllers\AboutPageController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BkashController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryGridBannerController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\HeroSlideController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NewArrivalsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PhoneVerificationController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PromoBannerController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoSectionController;
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

// Opened straight from the verification email — no bearer token attached,
// the signed URL itself proves identity.
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');

// This app has no Blade "please verify your email" page to redirect to —
// Laravel's `verified` middleware still needs a route by this name to exist
// for requests it doesn't detect as JSON (e.g. a bare `fetch()` with no
// explicit Accept header), so give it one that just answers in JSON too,
// rather than crashing with a RouteNotFoundException.
Route::get('/email/verify', function () {
    return response()->json(['message' => 'Your email address is not verified.'], 403);
})->middleware('auth:sanctum')->name('verification.notice');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::post('/me/avatar', [AuthController::class, 'updateAvatar']);
    Route::post('/me/phone/confirm', [AuthController::class, 'confirmPhone']);
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1');
});

// The signed-in customer's own order history — any logged-in *and verified*
// account, no permission check (it's scoped to their own data, not a
// dashboard feature).
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/account/orders', [OrderController::class, 'myOrders']);
    Route::get('/account/orders/{id}', [OrderController::class, 'myOrder']);
});

// ---------------------------------------------------------------------------
// Storefront — public reads, and guest checkout
// ---------------------------------------------------------------------------

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);

Route::get('/menus/handle/{handle}', [MenuController::class, 'showByHandle']);

Route::get('/policies/published', [PolicyController::class, 'publicIndex']);
Route::get('/policies/slug/{slug}', [PolicyController::class, 'showBySlug']);

Route::get('/faqs/published', [FaqController::class, 'publicIndex']);

Route::get('/hero-slides', [HeroSlideController::class, 'index']);

Route::get('/new-arrivals', [NewArrivalsController::class, 'index']);

Route::get('/video-section', [VideoSectionController::class, 'index']);

Route::get('/promo-banner', [PromoBannerController::class, 'index']);

Route::get('/category-grid-banner', [CategoryGridBannerController::class, 'index']);

Route::get('/about-page', [AboutPageController::class, 'index']);

Route::get('/settings', [SettingsController::class, 'show']);

// Uploaded images — public so they render on the storefront for anonymous
// visitors (product/category photos), same as any other static asset.
Route::get('/media/file/{filename}', [MediaController::class, 'serve']);

Route::post('/orders', [OrderController::class, 'store']);
Route::post('/orders/track', [OrderController::class, 'track']);

// Checkout's fake-order-protection step — see PhoneVerificationController.
Route::post('/checkout/phone/send-code', [PhoneVerificationController::class, 'sendCode']);
Route::post('/checkout/phone/verify-code', [PhoneVerificationController::class, 'verifyCode']);

// bKash payment gateway — see BkashController.
Route::post('/orders/{id}/bkash/create-payment', [BkashController::class, 'create']);
Route::get('/checkout/bkash/callback', [BkashController::class, 'callback'])->name('bkash.callback');

Route::post('/discounts/validate', [DiscountController::class, 'validateCode']);

Route::post('/subscribers', [SubscriberController::class, 'store'])->middleware('throttle:5,1');

Route::post('/products/{product}/reviews', [ReviewController::class, 'store']);

Route::post('/chat', [ChatController::class, 'send']);

// ---------------------------------------------------------------------------
// Dashboard — permission-gated (see RolesAndPermissionsSeeder for the full
// role → permission mapping)
// ---------------------------------------------------------------------------

Route::middleware(['auth:sanctum', 'permission:create_products'])->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
});
Route::middleware(['auth:sanctum', 'permission:edit_products'])->group(function () {
    Route::get('/products/{id}/edit', [ProductController::class, 'edit']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'permission:create_categories'])->group(function () {
    Route::post('/categories', [CategoryController::class, 'store']);
});
Route::middleware(['auth:sanctum', 'permission:edit_categories'])->group(function () {
    Route::put('/categories/reorder', [CategoryController::class, 'reorder']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    Route::get('/categories/{id}/products', [CategoryController::class, 'products']);
    Route::put('/categories/{id}/products', [CategoryController::class, 'updateProducts']);
});

Route::middleware(['auth:sanctum', 'permission:view_orders'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
});
Route::middleware(['auth:sanctum', 'permission:manage_orders'])->group(function () {
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{id}/ship', [CourierController::class, 'send']);
    Route::get('/orders/{id}/tracking', [CourierController::class, 'refreshStatus']);
    Route::get('/courier/pathao/cities', [CourierController::class, 'pathaoCities']);
    Route::get('/courier/pathao/cities/{cityId}/zones', [CourierController::class, 'pathaoZones']);
});

Route::middleware(['auth:sanctum', 'permission:view_analytics'])->group(function () {
    Route::post('/analytics/ai-insights', [AnalyticsController::class, 'aiInsights']);
});

Route::middleware(['auth:sanctum', 'permission:create_menus'])->group(function () {
    Route::post('/menus', [MenuController::class, 'store']);
});
Route::middleware(['auth:sanctum', 'permission:view_menus|create_menus|edit_menus'])->group(function () {
    Route::get('/menus', [MenuController::class, 'index']);
    Route::get('/menus/{id}', [MenuController::class, 'show']);
});
Route::middleware(['auth:sanctum', 'permission:edit_menus'])->group(function () {
    Route::put('/menus/{id}', [MenuController::class, 'update']);
    Route::delete('/menus/{id}', [MenuController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'permission:create_cms_pages'])->group(function () {
    Route::post('/policies', [PolicyController::class, 'store']);
    Route::post('/faqs', [FaqController::class, 'store']);
});
Route::middleware(['auth:sanctum', 'permission:view_cms_pages|create_cms_pages|edit_cms_pages'])->group(function () {
    Route::get('/policies', [PolicyController::class, 'index']);
    Route::get('/policies/{id}', [PolicyController::class, 'show']);
    Route::get('/faqs', [FaqController::class, 'index']);
    Route::get('/faqs/{id}', [FaqController::class, 'show']);
});
Route::middleware(['auth:sanctum', 'permission:edit_cms_pages'])->group(function () {
    Route::put('/policies/{id}', [PolicyController::class, 'update']);
    Route::delete('/policies/{id}', [PolicyController::class, 'destroy']);

    Route::put('/faqs/reorder', [FaqController::class, 'reorder']);
    Route::put('/faqs/{id}', [FaqController::class, 'update']);
    Route::delete('/faqs/{id}', [FaqController::class, 'destroy']);

    Route::put('/about-page', [AboutPageController::class, 'update']);
});

Route::middleware(['auth:sanctum', 'permission:create_media'])->group(function () {
    Route::post('/media', [MediaController::class, 'store']);
});
Route::middleware(['auth:sanctum', 'permission:view_media|create_media|edit_media'])->group(function () {
    Route::get('/media', [MediaController::class, 'index']);
});
Route::middleware(['auth:sanctum', 'permission:edit_media'])->group(function () {
    Route::delete('/media/{id}', [MediaController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'permission:edit_theme'])->group(function () {
    Route::put('/hero-slides', [HeroSlideController::class, 'update']);
    Route::put('/new-arrivals', [NewArrivalsController::class, 'update']);
    Route::put('/video-section', [VideoSectionController::class, 'update']);
    Route::put('/promo-banner', [PromoBannerController::class, 'update']);
    Route::put('/category-grid-banner', [CategoryGridBannerController::class, 'update']);
});

Route::middleware(['auth:sanctum', 'permission:manage_settings'])->group(function () {
    Route::get('/settings/admin', [SettingsController::class, 'adminShow']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::get('/courier/balance', [CourierController::class, 'balance']);
    Route::get('/courier/pathao/stores', [CourierController::class, 'pathaoStores']);
    Route::get('/subscribers', [SubscriberController::class, 'index']);
    Route::delete('/subscribers/{id}', [SubscriberController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'permission:view_sms'])->group(function () {
    Route::get('/sms/logs', [SmsController::class, 'logs']);
    Route::get('/sms/recipients', [SmsController::class, 'recipients']);
});
Route::middleware(['auth:sanctum', 'permission:manage_sms'])->group(function () {
    Route::post('/sms/promotional', [SmsController::class, 'sendPromotional']);
});

Route::middleware(['auth:sanctum', 'permission:create_discounts'])->group(function () {
    Route::post('/discounts', [DiscountController::class, 'store']);
});
Route::middleware(['auth:sanctum', 'permission:view_discounts|create_discounts|edit_discounts'])->group(function () {
    Route::get('/discounts', [DiscountController::class, 'index']);
});
Route::middleware(['auth:sanctum', 'permission:edit_discounts'])->group(function () {
    Route::put('/discounts/{id}', [DiscountController::class, 'update']);
    Route::delete('/discounts/{id}', [DiscountController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'permission:view_reviews'])->group(function () {
    Route::get('/reviews', [ReviewController::class, 'index']);
});
Route::middleware(['auth:sanctum', 'permission:edit_reviews'])->group(function () {
    Route::patch('/reviews/{id}/status', [ReviewController::class, 'updateStatus']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'permission:create_staff'])->group(function () {
    Route::post('/users', [UserController::class, 'store']);
});
Route::middleware(['auth:sanctum', 'permission:view_staff|create_staff|edit_staff'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
});
Route::middleware(['auth:sanctum', 'permission:edit_staff'])->group(function () {
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});
