<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

class NotificationService
{
    public function send(User|int $recipient, string $type, string $title, string $body, ?string $resourceType = null, ?int $resourceId = null, array $data = []): AppNotification
    {
        return AppNotification::create([
            'user_id' => $recipient instanceof User ? $recipient->id : $recipient,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'data' => $data,
        ]);
    }
}
