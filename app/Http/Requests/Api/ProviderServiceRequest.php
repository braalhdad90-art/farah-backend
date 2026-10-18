<?php

namespace App\Http\Requests\Api;

class ProviderServiceRequest extends BaseApiRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['features', 'booking_slots', 'package_items', 'remove_image_ids'] as $field) {
            if (is_string($this->input($field))) {
                $decoded = json_decode($this->input($field), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $decoded]);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => 'nullable|exists:categories,id',
            'city_id' => 'nullable|exists:cities,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'service_type' => 'nullable|in:service,package',
            'features' => 'nullable|array',
            'features.*' => 'string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'area' => 'nullable|numeric|min:0',
            'area_unit' => 'nullable|string|max:20',
            'booking_slots' => 'nullable|array',
            'booking_slots.*.label' => 'required_with:booking_slots|string|max:100',
            'booking_slots.*.start_time' => 'required_with:booking_slots|date_format:H:i',
            'booking_slots.*.end_time' => 'required_with:booking_slots|date_format:H:i',
            'booking_slots.*.price' => 'nullable|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'pricing_unit' => 'nullable|string|max:50',
            'execution_duration' => 'nullable|string|max:100',
            'cancellation_policy' => 'nullable|string|max:5000',
            'address' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'deposit_amount' => 'nullable|numeric|min:0',
            'deposit_percentage' => 'nullable|integer|min:0|max:100',
            'currency' => 'nullable|string|max:10',
            'is_available' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:8192',
            'remove_image_ids' => 'nullable|array',
            'remove_image_ids.*' => 'integer|exists:service_images,id',
            'package_items' => 'nullable|array|max:30',
            'package_items.*.title' => 'required_with:package_items|string|max:255',
            'package_items.*.description' => 'nullable|string|max:1000',
            'package_items.*.quantity' => 'nullable|integer|min:1|max:999',
            'package_items.*.sort_order' => 'nullable|integer|min:0|max:999',
        ];
    }
}
