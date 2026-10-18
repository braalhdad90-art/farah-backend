<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->index(['status', 'category_id', 'city_id'], 'services_browse_index');
            $table->index(['provider_id', 'status'], 'services_provider_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('services_browse_index');
            $table->dropIndex('services_provider_status_index');
        });
    }
};
