<?php

namespace App\Http\Requests\Api;

class LocationRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'label' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city_id' => 'nullable|exists:cities,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }
}
