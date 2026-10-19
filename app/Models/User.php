<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'pending_email',
        'password',
        'phone',
        'user_type',
        'status',
        'avatar',
        'bio',
        'cover_image',
        'last_login_at',
        'is_online',
        'city_id',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'pending_email',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = strtolower(trim((string) $value));
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function providerProfile()
    {
        return $this->hasOne(ProviderProfile::class, 'user_id');
    }

    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    public function isAdmin(): bool
    {
        return $this->user_type === 'admin' || $this->hasAnyRole(['ops.moderator', 'finance.reviewer', 'system.admin']);
    }

    public function isProvider(): bool
    {
        return $this->user_type === 'provider';
    }

    public function isCustomer(): bool
    {
        return $this->user_type === 'customer';
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'provider_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function providerBookings()
    {
        return $this->hasMany(Booking::class, 'provider_id');
    }

    public function providerSubscriptions()
    {
        return $this->hasMany(ProviderSubscription::class, 'provider_id');
    }

    public function subscriptionPayments()
    {
        return $this->hasMany(SubscriptionPayment::class, 'provider_id');
    }

    public function activeProviderSubscription()
    {
        return $this->hasOne(ProviderSubscription::class, 'provider_id')
            ->whereIn('status', ['trialing', 'active'])
            ->where('current_period_end', '>', now())
            ->latestOfMany();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function notificationSetting()
    {
        return $this->hasOne(NotificationSetting::class);
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class)->latest();
    }
}
