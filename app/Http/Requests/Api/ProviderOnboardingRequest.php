<?php

namespace App\Http\Requests\Api;

class ProviderOnboardingRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'business_name' => 'required|string|max:150',
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string|max:5000',
            'city_id' => 'required|exists:cities,id',
            'address' => 'required|string|max:1000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'identity_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:8192',
            'commercial_register' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:8192',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
        ];
    }
}