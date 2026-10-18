<?php

namespace App\Http\Requests\Api;

class SubmitBookingPaymentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:bank_transfer,jawwal_pay,other'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
