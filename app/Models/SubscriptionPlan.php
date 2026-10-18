<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'price',
        'currency',
        'interval',
        'interval_count',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'interval_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function providerSubscriptions()
    {
        return $this->hasMany(ProviderSubscription::class);
    }
}