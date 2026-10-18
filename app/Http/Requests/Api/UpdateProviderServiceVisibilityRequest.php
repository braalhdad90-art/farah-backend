<?php

namespace App\Http\Requests\Api;

class UpdateProviderServiceVisibilityRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return ['is_available' => 'required|boolean'];
    }
}
