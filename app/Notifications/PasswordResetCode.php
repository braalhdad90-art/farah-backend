<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// فرح: إيميل "نسيت كلمة المرور" فيه رمز من 4 أرقام يكتبه المستخدم في التطبيق.
class PasswordResetCode extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly int $minutes,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('رمز إعادة تعيين كلمة المرور - فرح')
            ->greeting('مرحبًا '.($notifiable->name ?? ''))
            ->line('تلقينا طلبًا لإعادة تعيين كلمة المرور لحسابك. رمز التحقق هو:')
            ->line("**{$this->code}**")
            ->line("ينتهي الرمز خلال {$this->minutes} دقيقة. تجاهل هذه الرسالة إذا لم تطلب إعادة التعيين.")
            ->salutation('فريق فرح');
    }
}
