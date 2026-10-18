<?php

namespace App\Http\Requests\Api;

class UpdateProviderAvailabilityRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'block_dates' => 'nullable|array|max:366',
            'block_dates.*.date' => 'required_with:block_dates|date_format:Y-m-d|after_or_equal:today',
            'block_dates.*.reason' => 'nullable|string|max:500',
            'unblock_dates' => 'nullable|array|max:366',
            'unblock_dates.*' => 'date_format:Y-m-d',
        ];
    }
}
