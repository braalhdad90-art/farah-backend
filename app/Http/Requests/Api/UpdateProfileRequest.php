<?php

namespace App\Http\Requests\Api;

class UpdateProfileRequest extends AuthApiRequest
{
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:45',
            'city_id' => 'nullable|exists:cities,id',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:4096',
        ];
    }

    public function messages(): array
    {
        return [
            'city_id.exists' => 'المدينة غير صالحة. اختر المدينة من القائمة.',
            'avatar.image' => 'الملف يجب أن يكون صورة.',
            'avatar.mimes' => 'نوع الصورة غير مدعوم. استخدم jpeg أو png أو jpg أو gif أو svg.',
        ];
    }
}
