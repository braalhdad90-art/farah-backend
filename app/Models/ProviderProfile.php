<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderProfile extends Model
{
    /** @use HasFactory<\Database\Factories\ProviderProfileFactory> */
    use HasFactory;

    protected $table = 'provider_profiles';

    protected $fillable = [
        'user_id',
        'city_id',
        'business_name',
        'category_id',
        'phone',
        'bio',
        'description',
        'cover_image',
        'identity_document_path',
        'commercial_register_path',
        'commercial_register_number',
        'payment_instructions',
        'payout_bank_name',
        'payout_account_number',
        'payout_iban',
        'status',
        'registration_status',
        'submitted_at',
        'escalated_at',
        'decided_at',
        'decision_reason',
        'is_featured',
        'rating',
        'working_hours',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'rating' => 'float',
        'working_hours' => 'array',
        'submitted_at' => 'datetime',
        'escalated_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'provider_id', 'user_id');
    }
}
