<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('capacity')->nullable()->after('features');
            $table->decimal('area', 10, 2)->nullable()->after('capacity');
            $table->string('area_unit', 20)->default('m²')->after('area');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['capacity', 'area', 'area_unit']);
        });
    }
};