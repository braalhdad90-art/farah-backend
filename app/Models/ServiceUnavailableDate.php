<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceUnavailableDate extends Model
{
    use HasFactory;

    protected $fillable = ['service_id', 'unavailable_date', 'reason'];

    protected $casts = ['unavailable_date' => 'date:Y-m-d'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
