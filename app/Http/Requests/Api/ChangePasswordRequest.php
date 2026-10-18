<?php

namespace App\Http\Requests\Api;

class ChangePasswordRequest extends AuthApiRequest
{
    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password' => 'required|string|confirmed|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'كلمة المرور الحالية مطلوبة.',
            'new_password.required' => 'كلمة المرور الجديدة مطلوبة.',
            'new_password.min' => 'كلمة المرور الجديدة يجب أن تتكون من 8 أحرف على الأقل.',
            'new_password.confirmed' => 'تأكيد كلمة المرور الجديدة لا يطابق.',
        ];
    }
}
