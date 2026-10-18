<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'customer_name',
        'customer_phone',
        'service_name',
        'booking_date',
        'booking_time',
        'total_price',
        'deposit_amount',
        'remaining_amount',
        'payment_method',
        'status',
        'notes',
        'source',
    ];

    protected $casts = [
        'booking_date' => 'date:Y-m-d',
        'booking_time' => 'datetime:H:i',
        'total_price' => 'float',
        'deposit_amount' => 'float',
        'remaining_amount' => 'float',
    ];

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }
}
