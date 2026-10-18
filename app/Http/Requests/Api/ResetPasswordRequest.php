<?php

namespace App\Http\Requests\Api;

class ResetPasswordRequest extends AuthApiRequest
{
    public function rules(): array
    {
        return [
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|confirmed|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'رمز إعادة ضبط كلمة المرور مطلوب.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'الرجاء إدخال بريد إلكتروني صالح.',
            'email.exists' => 'هذا البريد غير مسجل.',
            'password.required' => 'كلمة المرور الجديدة مطلوبة.',
            'password.min' => 'كلمة المرور الجديدة يجب أن تتكون من 8 أحرف على الأقل.',
            'password.confirmed' => 'تأكيد كلمة المرور لا يطابق.',
        ];
    }
}
