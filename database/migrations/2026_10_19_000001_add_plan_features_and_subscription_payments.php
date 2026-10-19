<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // مميزات كل باقة: قائمة من {name, included}. تُعدَّل من لوحة الأدمن ← باقات الاشتراك.
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->json('features')->nullable()->after('description');
        });

        // إشعارات التحويل البنكي لاشتراكات المزودين، يراجعها الأدمن من: المالية ← اشتراكات المزودين.
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->foreignId('provider_subscription_id')->nullable()->constrained('provider_subscriptions')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('proof_path');
            $table->string('status')->default('submitted');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'status']);
        });

        $features = [
            'صفحة مزود الخدمة',
            'اضافة خدمات وباقات',
            'استقبال حجوزات',
            'لوحة تحكم اساسية',
            'اعلانات مميزة',
            'الاولوية في نتائج البحث',
        ];
        $plans = [
            'monthly' => 4, // first four included, the rest shown as not included
            'yearly' => count($features),
        ];
        foreach ($plans as $slug => $includedCount) {
            DB::table('subscription_plans')->where('slug', $slug)->update([
                'features' => json_encode(array_map(
                    fn (string $name, int $i) => ['name' => $name, 'included' => $i < $includedCount],
                    $features,
                    array_keys($features)
                ), JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }
};
