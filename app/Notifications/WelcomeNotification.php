<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// فرح: إيميل الترحيب بعد التسجيل؛ نص مختلف للعميل والمزود.
class WelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ?string $trialLength = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('أهلاً بك في فرح 🎉')
            ->greeting('مرحبًا '.($notifiable->name ?? ''));

        if (($notifiable->user_type ?? 'customer') === 'provider') {
            $mail->line('سعداء بانضمامك إلى فرح كمقدّم خدمة.')
                ->line('الخطوات القادمة:')
                ->line('1. أكمل بيانات متجرك ووثائق التوثيق من التطبيق.')
                ->line('2. يراجع فريق المنصة طلبك ونبلغك بالإيميل عند اعتماده.');
            if ($this->trialLength) {
                $mail->line("3. بعد الاعتماد تبدأ فترتك المجانية ({$this->trialLength}) وتنشر خدماتك للعملاء.");
            }
        } else {
            $mail->line('سعداء بانضمامك إلى فرح، منصتك لتنظيم مناسبتك.')
                ->line('- تصفّح الخدمات واختر التاريخ والفترة ثم أرسل طلب الحجز.')
                ->line('- بعد قبول مقدّم الخدمة، ادفع العربون من "حجوزاتي" وارفع إشعار التحويل.')
                ->line('- لأي سؤال تجد الإجابات في "مركز المساعدة" داخل التطبيق.');
        }

        return $mail->salutation('فريق فرح');
    }
}
