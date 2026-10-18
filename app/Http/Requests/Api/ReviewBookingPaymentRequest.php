<?php

namespace App\Http\Requests\Api;

class ReviewBookingPaymentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:confirmed,rejected'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
