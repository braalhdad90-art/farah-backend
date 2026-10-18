<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProviderRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تحديث طلب توثيق مزود الخدمة')
            ->greeting('مرحباً '.$notifiable->name)
            ->line('لم تتم الموافقة على طلب توثيق حسابك حالياً.')
            ->line('سبب الرفض: '.$this->reason)
            ->line('يمكنك تحديث بياناتك وإعادة إرسال الطلب من التطبيق.');
    }
}
