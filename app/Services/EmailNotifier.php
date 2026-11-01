<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

// فرح: إرسال إيميل "إضافي" (ترحيب، اعتماد، رفض) بدون ما يفشل الطلب الأصلي:
// إذا تعطل Brevo أو تأخر، التسجيل أو قرار الأدمن بيكمل عادي وبينكتب الخطأ باللوج.
class EmailNotifier
{
    public function send(?User $user, Notification $notification): bool
    {
        if (! $user || blank($user->email)) {
            return false;
        }

        try {
            $user->notify($notification);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('فشل إرسال إيميل '.class_basename($notification).' للمستخدم '.$user->id.': '.$exception->getMessage());

            return false;
        }
    }
}
