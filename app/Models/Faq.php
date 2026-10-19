<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// فرح: سؤال شائع في مركز المساعدة (audience: customer | provider | all).
class Faq extends Model
{
    public const AUDIENCES = ['customer', 'provider', 'all'];

    protected $fillable = ['audience', 'question', 'answer', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
