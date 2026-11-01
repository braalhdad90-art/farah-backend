<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'features',
        'price',
        'currency',
        'interval',
        'interval_count',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'interval_count' => 'integer',
        'is_active' => 'boolean',
    ];

    // فرح: نهاية فترة الباقة حسب "المدة" و"العدد" من لوحة الأدمن (مثلاً الفترة المجانية 3 أشهر).
    public function periodEndFrom(CarbonInterface $from): CarbonInterface
    {
        $count = max(1, (int) $this->interval_count);

        return $this->interval === 'year'
            ? $from->copy()->addYearsNoOverflow($count)
            : $from->copy()->addMonthsNoOverflow($count);
    }

    /** "شهر"، "شهران"، "3 أشهر"، "سنة"... */
    public function durationLabel(): string
    {
        $count = max(1, (int) $this->interval_count);
        if ($this->interval === 'year') {
            return match (true) {
                $count === 1 => 'سنة',
                $count === 2 => 'سنتان',
                $count <= 10 => "{$count} سنوات",
                default => "{$count} سنة",
            };
        }

        return match (true) {
            $count === 1 => 'شهر',
            $count === 2 => 'شهران',
            $count <= 10 => "{$count} أشهر",
            default => "{$count} شهرًا",
        };
    }

    public function providerSubscriptions()
    {
        return $this->hasMany(ProviderSubscription::class);
    }
}