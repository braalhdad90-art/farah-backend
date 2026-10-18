<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->string('registration_status', 20)->default('draft')->after('status');
            $table->string('identity_document_path', 2048)->nullable()->after('cover_image');
            $table->string('commercial_register_path', 2048)->nullable()->after('identity_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'registration_status',
                'identity_document_path',
                'commercial_register_path',
            ]);
        });
    }
};