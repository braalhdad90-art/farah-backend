<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // فرح: المزايا اللي اختار العميل إزالتها من الخدمة (من removable_features تبع المزود) عند الحجز.
        Schema::table('bookings', function (Blueprint $table) {
            $table->json('removed_features')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('removed_features');
        });
    }
};
