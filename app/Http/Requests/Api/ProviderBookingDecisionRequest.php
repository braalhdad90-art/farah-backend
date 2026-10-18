<?php

namespace App\Http\Requests\Api;

class ProviderBookingDecisionRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:accepted,rejected'],
            'deposit_amount' => ['required_if:decision,accepted', 'nullable', 'numeric', 'min:0'],
            'provider_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
