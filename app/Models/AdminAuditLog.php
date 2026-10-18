<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'admin_audit_logs';

    protected $guarded = [
        'id',
        'created_at',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            if (empty($log->created_at)) {
                $log->created_at = now();
            }
        });

        static::updating(function (): void {
            throw new \RuntimeException('Audit log records are immutable.');
        });

        static::deleting(function (): void {
            throw new \RuntimeException('Audit log records are immutable.');
        });
    }

    public function setUpdatedAt($value): never
    {
        throw new \RuntimeException('Audit log records are immutable.');
    }

    public function setDeletedAt($value): never
    {
        throw new \RuntimeException('Audit log records are immutable.');
    }

    public function actor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
