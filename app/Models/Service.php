<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'category_id',
        'city_id',
        'title',
        'service_type',
        'description',
        'features',
        'capacity',
        'area',
        'area_unit',
        'booking_slots',
        'price',
        'pricing_unit',
        'execution_duration',
        'cancellation_policy',
        'address',
        'latitude',
        'longitude',
        'deposit_amount',
        'deposit_percentage',
        'currency',
        'image',
        'rating_avg',
        'reviews_count',
        'is_featured',
        'is_on_offer',
        'discount_percentage',
        'original_price',
        'offer_badge',
        'is_available',
        'status',
    ];

    protected $casts = [
        'features' => 'array',
        'capacity' => 'integer',
        'area' => 'float',
        'booking_slots' => 'array',
        'price' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'deposit_amount' => 'float',
        'deposit_percentage' => 'integer',
        'rating_avg' => 'float',
        'is_featured' => 'boolean',
        'is_on_offer' => 'boolean',
        'discount_percentage' => 'integer',
        'original_price' => 'float',
        'is_available' => 'boolean',
    ];

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function scopeForApprovedProviders(Builder $query): Builder
    {
        return $query->whereHas('provider', function (Builder $provider): void {
            $provider->where('status', 'active')
                ->whereHas('providerProfile', function (Builder $profile): void {
                    $profile->where('status', 'approved')
                        ->where('registration_status', 'approved');
                });
        })->where('admin_hidden', false)->where(function (Builder $category): void {
            $category->whereNull('category_id')
                ->orWhereHas('category', fn (Builder $category) => $category->where('status', 'active'));
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function images()
    {
        return $this->hasMany(ServiceImage::class)->orderBy('sort_order');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class)->where('is_visible', true);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function items()
    {
        return $this->hasMany(ServiceItem::class)->orderBy('sort_order');
    }

    public function unavailableDates()
    {
        return $this->hasMany(ServiceUnavailableDate::class);
    }
}
