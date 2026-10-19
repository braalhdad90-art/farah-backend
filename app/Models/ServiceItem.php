<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceItem extends Model
{
    use HasFactory;

    protected $fillable = ['service_id', 'title', 'description', 'quantity', 'removable', 'sort_order'];

    protected $casts = ['removable' => 'boolean'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
