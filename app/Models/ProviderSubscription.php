<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderSubscription extends Model
{
    protected $fillable = [
        'provider_id',
        'subscription_plan_id',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'canceled_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    protected $appends = ['is_active'];

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['trialing', 'active'], true)
            && $this->current_period_end?->isFuture();
    }
}