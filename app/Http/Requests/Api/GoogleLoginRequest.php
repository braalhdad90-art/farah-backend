<?php

namespace App\Http\Requests\Api;

class GoogleLoginRequest extends AuthApiRequest
{
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            'user_type' => ['nullable', 'in:customer,provider'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_token.required' => 'رمز تسجيل الدخول من Google مطلوب.',
            'user_type.in' => 'نوع الحساب غير صالح.',
        ];
    }
}
