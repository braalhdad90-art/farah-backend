<?php

namespace App\Http\Requests\Api;

class RegisterRequest extends AuthApiRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|confirmed|min:8',
            'user_type' => 'nullable|in:customer,provider',
            'phone' => 'nullable|string|max:45',
            'city_id' => 'nullable|exists:cities,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب.',
            'name.max' => 'الاسم طويل جدًا.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'الرجاء إدخال بريد إلكتروني صالح',
            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'كلمة المرور يجب أن تتكون من 8 أحرف على الأقل.',
            'password.confirmed' => 'تأكيد كلمة المرور لا يطابق.',
            'user_type.in' => 'نوع الحساب غير صالح. استخدم customer أو provider.',
            'city_id.exists' => 'المنطقة غير صالحة. اختر المدينة من القائمة.',
        ];
    }
}
