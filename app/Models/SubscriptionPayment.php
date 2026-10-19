<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'provider_id',
        'subscription_plan_id',
        'provider_subscription_id',
        'amount',
        'currency',
        'proof_path',
        'status',
        'review_locked_by',
        'review_locked_until',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $hidden = ['proof_path'];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'review_locked_until' => 'datetime',
    ];

    /** Someone other than [$userId] is reviewing this proof right now. */
    public function isLockedForOthers(int $userId): bool
    {
        return $this->review_locked_by !== null
            && $this->review_locked_by !== $userId
            && $this->review_locked_until?->isFuture();
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'review_locked_by');
    }

    public function subscription()
    {
        return $this->belongsTo(ProviderSubscription::class, 'provider_subscription_id');
    }
}
