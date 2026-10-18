<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminRefund extends Model
{
    protected $fillable = [
        'booking_id',
        'booking_payment_id',
        'requested_by',
        'reviewed_by',
        'amount',
        'status',
        'reason',
        'review_note',
        'external_reference',
        'reviewed_at',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment()
    {
        return $this->belongsTo(BookingPayment::class, 'booking_payment_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
