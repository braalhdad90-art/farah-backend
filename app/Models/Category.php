<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'image',
        'status',
    ];


    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function providerProfiles()
    {
        return $this->hasMany(ProviderProfile::class);
    }
}
