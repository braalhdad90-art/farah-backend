<?php

namespace App\Http\Requests\Api;

class CancelBookingRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:1000']];
    }
}
