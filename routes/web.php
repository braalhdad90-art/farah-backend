<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminFinanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth:web', 'admin.idle', 'admin']);

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminController::class, 'loginForm'])->name('admin.login');
    Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login.submit');
});

Route::prefix('admin')->name('admin.')->middleware(['auth:web', 'admin.idle', 'admin'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::middleware('admin:ops.moderator,system.admin')->group(function () {
        Route::get('/providers', [AdminController::class, 'providers'])->name('providers');
        Route::get('/providers/pending', [AdminController::class, 'providersPending'])->name('providers.pending');
        Route::get('/providers/{provider}', [AdminController::class, 'providerDetail'])->name('provider.detail');
        Route::get('/providers/{provider}/documents/{document}', [AdminController::class, 'providerDocument'])
            ->middleware('signed')
            ->name('provider.document');
        Route::post('/providers/{provider}/approve', [AdminController::class, 'providerApprove'])->name('provider.approve');
        Route::post('/providers/{provider}/reject', [AdminController::class, 'providerReject'])->name('provider.reject');
        Route::post('/providers/{provider}/suspend', [AdminController::class, 'providerSuspend'])->name('provider.suspend');
        Route::post('/providers/{provider}/reactivate', [AdminController::class, 'providerReactivate'])->name('provider.reactivate');
        Route::get('/reviews', [AdminController::class, 'reviews'])->name('reviews');
        Route::post('/reviews/{review}/action', [AdminController::class, 'reviewAction'])->name('reviews.action');
        Route::get('/content/services', [AdminController::class, 'moderatedServices'])->name('content.services');
        Route::post('/content/services/{service}/visibility', [AdminController::class, 'moderateService'])->name('content.services.visibility');
        Route::get('/export/bookings.csv', [AdminController::class, 'exportBookingsCsv'])->name('export.bookings');
    });
    Route::middleware('admin:system.admin')->group(function () {
        Route::get('/team', [AdminController::class, 'team'])->name('team');
        Route::post('/team', [AdminController::class, 'createAdmin'])->name('team.create');
        Route::post('/team/{user}/role', [AdminController::class, 'updateAdminRole'])->name('team.update_role');
        Route::get('/categories', [AdminController::class, 'categories'])->name('categories');
        Route::post('/categories', [AdminController::class, 'createCategory'])->name('categories.create');
        Route::put('/categories/{category}', [AdminController::class, 'updateCategory'])->name('categories.update');
        Route::get('/plans', [AdminController::class, 'subscriptionPlans'])->name('plans');
        Route::put('/plans/{plan}', [AdminController::class, 'updateSubscriptionPlan'])->name('plans.update');
        // فرح: إدارة الأسئلة الشائعة لمركز المساعدة في التطبيق.
        Route::get('/faqs', [AdminController::class, 'faqs'])->name('faqs');
        Route::post('/faqs', [AdminController::class, 'createFaq'])->name('faqs.create');
        Route::put('/faqs/{faq}', [AdminController::class, 'updateFaq'])->name('faqs.update');
        Route::delete('/faqs/{faq}', [AdminController::class, 'deleteFaq'])->name('faqs.delete');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
        Route::get('/audit-log', [AdminController::class, 'auditLog'])->name('audit-log');
    });
    Route::middleware('admin:finance.reviewer,system.admin')->prefix('finance')->name('finance.')->group(function () {
        Route::get('/payments', [AdminFinanceController::class, 'index'])->name('payments');
        Route::get('/payments/{payment}/proof', [AdminFinanceController::class, 'proof'])
            ->middleware('signed')
            ->name('payment.proof');
        Route::get('/subscriptions', [AdminFinanceController::class, 'subscriptionPayments'])->name('subscriptions');
        Route::get('/subscriptions/{payment}', [AdminFinanceController::class, 'showSubscriptionPayment'])->name('subscription.show');
        Route::get('/subscriptions/{payment}/proof', [AdminFinanceController::class, 'subscriptionProof'])
            ->middleware('signed')
            ->name('subscription.proof');
        Route::post('/subscriptions/{payment}/review', [AdminFinanceController::class, 'reviewSubscriptionPayment'])->name('subscription.review');
        Route::get('/refunds', [AdminFinanceController::class, 'refunds'])->name('refunds');
        Route::post('/refunds/{booking}', [AdminFinanceController::class, 'createRefund'])->name('refunds.create');
        Route::post('/refunds/{refund}/decision', [AdminFinanceController::class, 'refundDecision'])->name('refunds.decision');
        Route::get('/export/payments.csv', [AdminController::class, 'exportPaymentsCsv'])->name('export.payments');
        Route::get('/export/subscriptions.csv', [AdminController::class, 'exportSubscriptionsCsv'])->name('export.subscriptions');
        Route::get('/payouts', [AdminFinanceController::class, 'payouts'])->name('payouts');
        Route::post('/payouts', [AdminFinanceController::class, 'createPayout'])->name('payouts.create');
    });
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
});