<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // فرح: وقت رد المزوّد على طلب الحجز، لحساب "سرعة الرد" و"نسبة الالتزام" في صفحة خدماتي.
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('provider_responded_at')->nullable()->after('expires_at');
        });

        // فرح: أسماء المميزات التي يستطيع العميل إزالتها (الباقي "مشمولة") من شاشة إضافة خدمة.
        Schema::table('services', function (Blueprint $table) {
            $table->json('removable_features')->nullable()->after('features');
            // فرح: "حفظ كمسودة" — الخدمة غير منشورة (status = inactive) حتى يفعّلها المزوّد.
            $table->boolean('is_draft')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['removable_features', 'is_draft']);
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('provider_responded_at');
        });
    }
};
