<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'enabled',
        'new_orders',
        'offers',
        'promotions',
        'reminders',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'new_orders' => 'boolean',
        'offers' => 'boolean',
        'promotions' => 'boolean',
        'reminders' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
