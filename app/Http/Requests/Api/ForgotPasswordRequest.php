<?php

namespace App\Http\Requests\Api;

class ForgotPasswordRequest extends AuthApiRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'الرجاء إدخال بريد إلكتروني صالح.',
            'email.exists' => 'هذا البريد غير مسجل.',
        ];
    }
}
