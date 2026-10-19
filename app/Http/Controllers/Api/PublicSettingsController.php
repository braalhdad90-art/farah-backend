<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;

class PublicSettingsController extends Controller
{
    public function __invoke()
    {
        $settings = AppSetting::whereIn('key', ['platform_name', 'support_email', 'support_phone', 'bank_name', 'bank_account_number', 'bank_iban'])
            ->pluck('value', 'key');

        return response()->json([
            'status' => true,
            'data' => [
                'platform_name' => $settings['platform_name'] ?? config('app.name'),
                'support_email' => $settings['support_email'] ?? null,
                'support_phone' => $settings['support_phone'] ?? null,
                // فرح: حساب المنصة لاستلام اشتراكات المزودين. يعبّيه الأدمن من: إعدادات المنصة.
                'bank_transfer' => [
                    'bank_name' => $settings['bank_name'] ?? null,
                    'account_number' => $settings['bank_account_number'] ?? null,
                    'iban' => $settings['bank_iban'] ?? null,
                ],
            ],
        ]);
    }
}
