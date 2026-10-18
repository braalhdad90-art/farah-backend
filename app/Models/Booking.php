<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service_id',
        'provider_id',
        'booking_date',
        'booking_time',
        'booking_slot',
        'total_price',
        'selected_price',
        'deposit_amount',
        'remaining_amount',
        'status',
        'workflow_status',
        'reference',
        'service_snapshot',
        'payment_instructions',
        'provider_note',
        'cancellation_reason',
        'expires_at',
        'payment_due_at',
        'confirmed_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'booking_date' => 'date:Y-m-d',
        'booking_time' => 'datetime:H:i',
        'total_price' => 'float',
        'selected_price' => 'float',
        'deposit_amount' => 'float',
        'remaining_amount' => 'float',
        'service_snapshot' => 'array',
        'expires_at' => 'datetime',
        'payment_due_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function payments()
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
