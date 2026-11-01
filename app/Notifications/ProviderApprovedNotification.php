<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// فرح: إيميل اعتماد حساب المزود من لوحة الأدمن.
class ProviderApprovedNotification extends Notification
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
            ->subject('تم اعتماد حسابك في فرح ✅')
            ->greeting('مرحبًا '.($notifiable->name ?? ''))
            ->line('يسعدنا إبلاغك بأنه تم اعتماد حساب مقدّم الخدمة الخاص بك.');

        $this->trialLength
            ? $mail->line("تم تفعيل فترتك المجانية ({$this->trialLength})، ويمكنك الآن نشر خدماتك واستقبال الحجوزات.")
            : $mail->line('يمكنك الآن متابعة إعداد اشتراكك ونشر خدماتك من التطبيق.');

        return $mail->line('افتح التطبيق وأضف خدماتك من تبويب "خدماتي".')
            ->salutation('فريق فرح');
    }
}
