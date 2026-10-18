<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('service_type', 30)->default('service')->after('title');
            $table->string('pricing_unit', 50)->default('per_booking')->after('price');
            $table->string('execution_duration', 100)->nullable()->after('pricing_unit');
            $table->text('cancellation_policy')->nullable()->after('execution_duration');
            $table->text('address')->nullable()->after('city_id');
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('price');
            $table->unsignedTinyInteger('deposit_percentage')->nullable()->after('deposit_amount');
        });

        Schema::create('service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->text('payment_instructions')->nullable()->after('commercial_register_path');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('reference', 40)->nullable()->unique()->after('id');
            $table->string('workflow_status', 40)->default('provider_pending')->after('status');
            $table->decimal('selected_price', 10, 2)->default(0)->after('total_price');
            $table->decimal('deposit_amount', 10, 2)->default(0)->after('selected_price');
            $table->decimal('remaining_amount', 10, 2)->default(0)->after('deposit_amount');
            $table->json('service_snapshot')->nullable()->after('remaining_amount');
            $table->text('payment_instructions')->nullable()->after('service_snapshot');
            $table->text('provider_note')->nullable()->after('payment_instructions');
            $table->text('cancellation_reason')->nullable()->after('provider_note');
            $table->timestamp('expires_at')->nullable()->after('cancellation_reason');
            $table->timestamp('payment_due_at')->nullable()->after('expires_at');
            $table->timestamp('confirmed_at')->nullable()->after('payment_due_at');
            $table->timestamp('completed_at')->nullable()->after('confirmed_at');
            $table->index(['service_id', 'booking_date', 'booking_time']);
            $table->index(['provider_id', 'workflow_status']);
            $table->index(['user_id', 'workflow_status']);
        });

        DB::table('bookings')->where('status', 'confirmed')->update(['workflow_status' => 'confirmed']);
        DB::table('bookings')->where('status', 'completed')->update(['workflow_status' => 'completed']);
        DB::table('bookings')->where('status', 'cancelled')->update(['workflow_status' => 'cancelled']);

        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference', 50)->unique();
            $table->decimal('amount', 10, 2);
            $table->string('method', 40)->default('bank_transfer');
            $table->string('proof_path', 2048);
            $table->string('status', 30)->default('submitted');
            $table->text('customer_note')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'status']);
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('title');
            $table->text('body');
            $table->string('resource_type', 80)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->unique()->after('service_id')->constrained('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
        });
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('booking_payments');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['service_id', 'booking_date', 'booking_time']);
            $table->dropIndex(['provider_id', 'workflow_status']);
            $table->dropIndex(['user_id', 'workflow_status']);
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'workflow_status', 'selected_price', 'deposit_amount', 'remaining_amount', 'service_snapshot', 'payment_instructions', 'provider_note', 'cancellation_reason', 'expires_at', 'payment_due_at', 'confirmed_at', 'completed_at']);
        });
        Schema::table('provider_profiles', fn (Blueprint $table) => $table->dropColumn('payment_instructions'));
        Schema::dropIfExists('service_items');
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'pricing_unit', 'execution_duration', 'cancellation_policy', 'address', 'latitude', 'longitude', 'deposit_amount', 'deposit_percentage']);
        });
    }
};
