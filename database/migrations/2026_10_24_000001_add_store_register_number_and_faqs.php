<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // فرح: "رقم السجل التجاري (إن وجد)" في شاشة بيانات المتجر (رقم نصي، غير ملف السجل المرفوع عند التسجيل).
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->string('commercial_register_number', 50)->nullable()->after('commercial_register_path');
        });

        // فرح: الأسئلة الشائعة لمركز المساعدة. يعدّلها الأدمن من لوحة التحكم: الأسئلة الشائعة.
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('audience', 20)->default('all'); // customer | provider | all
            $table->string('question', 255);
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['audience', 'is_active', 'sort_order']);
        });

        // فرح: أسئلة المزود الأولى تشرح خطوات موجودة فعلاً في التطبيق، والأدمن يقدر يعدّلها أو يحذفها.
        $now = now();
        DB::table('faqs')->insert(collect([
            ['كيف يمكنني تغيير كلمة المرور؟', 'من تبويب "حسابي" اختر "تغيير كلمة المرور"، أدخل كلمة المرور الحالية ثم الجديدة واضغط "حفظ التغيرات".'],
            ['كيف أستلم دفعات العملاء؟', 'أضف بيانات حسابك من "حسابي" ← "حساب استلام الارباح". يحوّل العميل العربون على هذا الحساب ويرفع إشعار التحويل، ثم تراجعه أنت من تفاصيل الطلب.'],
            ['كيف أسجّل حجزاً تم خارج التطبيق؟', 'من تبويب "الطلبات" اضغط "إضافة حجز خارجي" واختر الخدمة والتاريخ، فيُغلق هذا اليوم للحجز داخل التطبيق.'],
            ['متى تظهر خدماتي للعملاء؟', 'تظهر بعد اعتماد حسابك من فريق المنصة، وتفعيل اشتراكك، ونشر الخدمة من "خدماتي".'],
        ])->map(fn ($faq, $i) => [
            'audience' => 'provider',
            'question' => $faq[0],
            'answer' => $faq[1],
            'sort_order' => $i + 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropColumn('commercial_register_number');
        });
    }
};
