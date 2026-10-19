<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_by'];

    // فرح: مهل الخدمة (SLA) التي يضبطها الأدمن من إعدادات المنصة.
    public const SLA_DEFAULTS = [
        'provider_review_sla_hours' => 24,
        'payment_due_hours' => 24,
    ];

    public static function hours(string $key): int
    {
        $value = (int) static::whereKey($key)->value('value');

        return $value > 0 ? $value : static::SLA_DEFAULTS[$key];
    }
}
