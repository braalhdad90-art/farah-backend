<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'type', 'title', 'body', 'resource_type', 'resource_id', 'data', 'read_at'];
    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
