<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailChange extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $verificationUrl,
        private readonly string $name,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تأكيد تغيير البريد الإلكتروني - فرحتنا')
            ->greeting("مرحبًا {$this->name}")
            ->line('تلقينا طلبًا لتغيير البريد الإلكتروني لحسابك.')
            ->action('تأكيد البريد الإلكتروني الجديد', $this->verificationUrl)
            ->line('سينتهي رابط التأكيد خلال 60 دقيقة. تجاهل الرسالة إذا لم تطلب هذا التغيير.');
    }
}
