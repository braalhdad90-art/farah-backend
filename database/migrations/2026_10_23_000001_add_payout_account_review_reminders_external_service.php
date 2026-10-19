<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // فرح: "حساب استلام الارباح" للمزود: حيث يحوّل العميل العربون وتحوّل المنصة المستحقات.
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->string('payout_bank_name', 120)->nullable()->after('payment_instructions');
            $table->string('payout_account_number', 60)->nullable()->after('payout_bank_name');
            $table->string('payout_iban', 60)->nullable()->after('payout_account_number');
        });

        // فرح: تذكير المزود قبل انتهاء مهلة مراجعة إثبات الدفع وبعدها (مرة واحدة لكل حجز).
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('review_reminded_at')->nullable()->after('provider_responded_at');
            $table->timestamp('review_overdue_notified_at')->nullable()->after('review_reminded_at');
        });

        // فرح: الحجز الخارجي المرتبط بخدمة يحجز تاريخها ويمنع التعارض.
        Schema::table('external_bookings', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('provider_id')->constrained('services')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('external_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['review_reminded_at', 'review_overdue_notified_at']);
        });
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropColumn(['payout_bank_name', 'payout_account_number', 'payout_iban']);
        });
    }
};
