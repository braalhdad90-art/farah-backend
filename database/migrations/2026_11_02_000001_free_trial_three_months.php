<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// فرح: الفترة المجانية للمزود 3 أشهر. بعدها تتعدّل من لوحة الأدمن ← باقات الاشتراك (المدة والعدد).
// الاسم والوصف يتغيّران فقط إذا كانا القيم الأصلية (ما عدّلهم الأدمن).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscription_plans')->where('slug', 'free-trial')
            ->update(['interval' => 'month', 'interval_count' => 3, 'updated_at' => now()]);
        DB::table('subscription_plans')->where('slug', 'free-trial')
            ->where('name', 'تجربة مجانية شهر واحد')
            ->update(['name' => 'تجربة مجانية 3 أشهر']);
        DB::table('subscription_plans')->where('slug', 'free-trial')
            ->where('description', 'جرب المنصة مجاناً لمدة شهر كامل.')
            ->update(['description' => 'جرب المنصة مجاناً لمدة 3 أشهر.']);
    }

    public function down(): void
    {
        DB::table('subscription_plans')->where('slug', 'free-trial')
            ->update(['interval_count' => 1]);
    }
};
