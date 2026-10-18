<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingPayment extends Model
{
    use HasFactory;

    protected $fillable = ['booking_id', 'user_id', 'provider_id', 'reference', 'amount', 'method', 'proof_path', 'status', 'customer_note', 'review_note', 'reviewed_by', 'reviewed_at'];

    protected $casts = ['amount' => 'float', 'reviewed_at' => 'datetime'];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function customer() { return $this->belongsTo(User::class, 'user_id'); }
    public function provider() { return $this->belongsTo(User::class, 'provider_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
