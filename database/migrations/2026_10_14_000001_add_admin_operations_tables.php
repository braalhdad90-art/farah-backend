<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->boolean('is_visible')->default(true);
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_reason')->nullable();
            $table->index(['is_visible', 'created_at']);
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->boolean('admin_hidden')->default(false);
            $table->index(['admin_hidden', 'status']);
        });

        Schema::create('admin_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_payment_id')->nullable()->constrained('booking_payments')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('pending');
            $table->text('reason');
            $table->text('review_note')->nullable();
            $table->string('external_reference', 120)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['booking_id', 'status']);
        });

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('admin_refunds');

        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex(['admin_hidden', 'status']);
            $table->dropColumn('admin_hidden');
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropIndex(['is_visible', 'created_at']);
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn(['is_visible', 'moderated_at', 'moderation_reason']);
        });
    }
};
