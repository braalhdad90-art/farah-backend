<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationSettingController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\ProviderSubscriptionController;
use App\Http\Controllers\Api\PublicSettingsController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public catalog
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:public')->group(function () {
    Route::get('/settings', PublicSettingsController::class);
    // فرح: الأسئلة الشائعة لمركز المساعدة (يديرها الأدمن من لوحة التحكم).
    Route::get('/faqs', [FaqController::class, 'index']);
    Route::get('/cities', [CityController::class, 'index']);
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/services/compare', [ServiceController::class, 'compare']);
    Route::get('/categories/{id}/services', [ServiceController::class, 'index']);
    Route::get('/services/{id}', [ServiceController::class, 'show']);
    Route::get('/services/{id}/booking-options', [ServiceController::class, 'bookingOptions']);
});

/*
|--------------------------------------------------------------------------
| Guest authentication
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:register')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('throttle:auth')->group(function () {
    Route::post('/login', [AuthController::class, 'apilogin'])->name('login');
    Route::post('/auth/google', [AuthController::class, 'googleLogin']);
    Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');
});

Route::middleware(['signed-api', 'throttle:signed'])->group(function () {
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->name('verification.verify');
    Route::get('/email/change/verify/{id}/{hash}', [AuthController::class, 'verifyEmailChange'])
        ->name('email.change.verify');
});

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware(['account.active', 'verified'])->group(function () {
        Route::get('/user', [UserController::class, 'show']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/profile/update', [AuthController::class, 'updateProfile']);
        Route::post('/email/change-request', [AuthController::class, 'requestEmailChange']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::delete('/account', [AuthController::class, 'deleteAccount']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::get('/notification-settings', [NotificationSettingController::class, 'show']);
        Route::put('/notification-settings', [NotificationSettingController::class, 'update']);

        Route::patch('/locations/{location}/primary', [LocationController::class, 'setPrimary']);
        Route::apiResource('locations', LocationController::class);

        Route::middleware('customer')->group(function () {
            Route::get('/favorites', [FavoriteController::class, 'index']);
            Route::post('/favorites/toggle/{service_id}', [FavoriteController::class, 'toggle']);
            Route::post('/services/{id}/favorite', [FavoriteController::class, 'toggle']);
            Route::post('/services/{id}/booking-preview', [ServiceController::class, 'bookingPreview']);
            Route::post('/services/{id}/bookings', [BookingController::class, 'store']);
            Route::post('/services/{id}/reviews', [ServiceController::class, 'storeReview']);
            Route::get('/bookings', [BookingController::class, 'customerIndex']);
            Route::get('/bookings/{id}', [BookingController::class, 'show']);
            Route::get('/bookings/{id}/confirmation', [BookingController::class, 'show']);
            Route::post('/bookings/{id}/payment-proofs', [BookingController::class, 'submitPayment']);
            Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
        });

        Route::middleware('provider')->prefix('provider')->group(function () {
            Route::get('/dashboard', [ProviderController::class, 'dashboard']);
            Route::get('/verification', [ProviderController::class, 'verificationStatus']);
            Route::get('/onboarding', [ProviderController::class, 'onboarding']);
            Route::post('/onboarding', [ProviderController::class, 'saveOnboarding']);
            Route::get('/subscription-plans', [ProviderSubscriptionController::class, 'plans']);
            Route::get('/subscription', [ProviderSubscriptionController::class, 'current']);
            Route::post('/subscription', [ProviderSubscriptionController::class, 'subscribe']);
            Route::post('/subscription/cancel', [ProviderSubscriptionController::class, 'cancel']);
            Route::put('/payment-instructions', [BookingController::class, 'updatePaymentInstructions']);
            Route::get('/bookings', [BookingController::class, 'providerIndex']);
            Route::get('/bookings/{id}', [BookingController::class, 'providerShow']);
            Route::post('/bookings/{id}/decision', [BookingController::class, 'providerDecision']);
            Route::post('/bookings/{bookingId}/payments/{paymentId}/review', [BookingController::class, 'reviewPayment']);
            Route::post('/bookings/{id}/complete', [BookingController::class, 'complete']);
            Route::get('/bookings/{bookingId}/payments/{paymentId}/proof', [BookingController::class, 'downloadProof']);
            Route::get('/external-bookings', [\App\Http\Controllers\Api\ExternalBookingController::class, 'index']);
            Route::post('/external-bookings', [\App\Http\Controllers\Api\ExternalBookingController::class, 'store']);
            Route::get('/services', [ProviderController::class, 'services']);
            Route::post('/services', [ProviderController::class, 'storeService']);
            Route::post('/services/{id}', [ProviderController::class, 'updateService']);
            Route::patch('/services/{id}/visibility', [ProviderController::class, 'updateServiceVisibility']);
            Route::get('/services/{id}/availability', [ProviderController::class, 'availability']);
            Route::put('/services/{id}/availability', [ProviderController::class, 'updateAvailability']);
            Route::delete('/services/{id}', [ProviderController::class, 'destroyService']);
            Route::get('/reviews', [ProviderController::class, 'reviews']);
            Route::put('/payout-account', [ProviderController::class, 'updatePayoutAccount']);
            Route::post('/profile', [ProviderController::class, 'updateStoreProfile']);
        });
    });
});
