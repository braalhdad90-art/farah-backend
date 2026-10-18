<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_on_offer')->default(false)->after('is_featured');
            $table->unsignedTinyInteger('discount_percentage')->nullable()->after('is_on_offer');
            $table->decimal('original_price', 10, 2)->nullable()->after('discount_percentage');
            $table->string('offer_badge')->nullable()->after('original_price');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'is_on_offer',
                'discount_percentage',
                'original_price',
                'offer_badge',
            ]);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};