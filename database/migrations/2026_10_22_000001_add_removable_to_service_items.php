<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // فرح: مفتاح كل عنصر في "محتويات الباقة": مشمول (false) أو قابل للإزالة (true).
        Schema::table('service_items', function (Blueprint $table) {
            $table->boolean('removable')->default(false)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('service_items', function (Blueprint $table) {
            $table->dropColumn('removable');
        });
    }
};
