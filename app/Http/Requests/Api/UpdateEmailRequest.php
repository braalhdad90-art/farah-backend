<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class UpdateEmailRequest extends AuthApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'current_password' => ['nullable', 'string', 'required_without:google_id_token'],
            'google_id_token' => ['nullable', 'string', 'required_without:current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني الجديد مطلوب.',
            'email.email' => 'الرجاء إدخال بريد إلكتروني صالح.',
            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل.',
            'current_password.required_without' => 'أدخل كلمة المرور الحالية أو أعد التحقق عبر Google.',
            'google_id_token.required_without' => 'أدخل كلمة المرور الحالية أو أعد التحقق عبر Google.',
        ];
    }
}
