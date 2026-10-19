<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // فرح: التحويلات من المنصة إلى حسابات المزودين (ADM-26). تُسجَّل من: المالية ← تحويلات المزودين.
        Schema::create('provider_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('ILS');
            $table->string('method');
            $table->string('reference');
            $table->date('paid_at');
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['provider_id', 'paid_at']);
        });

        // A reviewer holds a subscription proof while reviewing it, so two reviewers never act on the same one (ADM-21).
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->foreignId('review_locked_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('review_locked_until')->nullable()->after('review_locked_by');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('review_locked_by');
            $table->dropColumn('review_locked_until');
        });
        Schema::dropIfExists('provider_payouts');
    }
};
