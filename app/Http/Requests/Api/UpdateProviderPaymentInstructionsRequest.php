<?php

namespace App\Http\Requests\Api;

class UpdateProviderPaymentInstructionsRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return ['payment_instructions' => ['required', 'string', 'min:10', 'max:2000']];
    }
}
