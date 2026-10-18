<?php

namespace App\Http\Requests\Api;

class DeleteAccountRequest extends AuthApiRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['nullable', 'string', 'required_without:google_id_token'],
            'google_id_token' => ['nullable', 'string', 'required_without:current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required_without' => 'أدخل كلمة المرور الحالية أو أعد التحقق عبر Google.',
            'google_id_token.required_without' => 'أدخل كلمة المرور الحالية أو أعد التحقق عبر Google.',
        ];
    }
}
