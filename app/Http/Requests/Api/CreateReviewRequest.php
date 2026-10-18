<?php

namespace App\Http\Requests\Api;

class CreateReviewRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'booking_id' => 'required|integer|exists:bookings,id',
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:2000',
        ];
    }
}
