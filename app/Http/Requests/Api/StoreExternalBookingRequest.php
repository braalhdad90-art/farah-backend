<?php

namespace App\Http\Requests\Api;

class StoreExternalBookingRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'service_name' => ['required', 'string', 'max:255'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'booking_time' => ['nullable', 'date_format:H:i'],
            'total_price' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,confirmed,cancelled'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
