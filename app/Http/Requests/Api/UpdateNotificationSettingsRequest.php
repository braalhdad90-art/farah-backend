<?php

namespace App\Http\Requests\Api;

class UpdateNotificationSettingsRequest extends AuthApiRequest
{
    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'new_orders' => ['sometimes', 'boolean'],
            'offers' => ['sometimes', 'boolean'],
            'promotions' => ['sometimes', 'boolean'],
            'reminders' => ['sometimes', 'boolean'],
        ];
    }
}
