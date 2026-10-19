<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderPayout extends Model
{
    protected $fillable = [
        'provider_id',
        'booking_id',
        'amount',
        'currency',
        'method',
        'reference',
        'paid_at',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
