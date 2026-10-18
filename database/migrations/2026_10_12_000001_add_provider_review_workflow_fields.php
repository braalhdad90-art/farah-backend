<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table): void {
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('escalated_at')->nullable()->index();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table): void {
            $table->dropColumn(['submitted_at', 'escalated_at', 'decided_at', 'decision_reason']);
        });
    }
};
