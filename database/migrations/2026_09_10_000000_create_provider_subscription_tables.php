<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('ILS');
            $table->string('interval')->nullable();
            $table->unsignedSmallInteger('interval_count')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('provider_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status')->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'status']);
        });

        DB::table('subscription_plans')->insert([
            [
                'slug' => 'free-trial',
                'name' => 'تجربة مجانية شهر واحد',
                'description' => 'جرب المنصة مجاناً لمدة شهر كامل.',
                'price' => 0,
                'currency' => 'ILS',
                'interval' => 'month',
                'interval_count' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'monthly',
                'name' => 'اشتراك شهري',
                'description' => 'كل ما تحتاجه لإدارة خدماتك باحترافية.',
                'price' => 100,
                'currency' => 'ILS',
                'interval' => 'month',
                'interval_count' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'yearly',
                'name' => 'اشتراك سنوي',
                'description' => 'أفضل قيمة لنمو أعمالك على مدار العام.',
                'price' => 175,
                'currency' => 'ILS',
                'interval' => 'year',
                'interval_count' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};