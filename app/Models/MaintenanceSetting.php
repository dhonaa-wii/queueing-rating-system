<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceSetting extends Model
{
    protected $fillable = [
        'audit_retention_days',
        'log_max_mb',
        'log_keep_days',
        'last_run_at',
        'last_run',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'last_run_at' => 'datetime',
            'last_run' => 'array',
        ];
    }

    /** The one row that matters; created with defaults on first use. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'audit_retention_days' => 365,
            'log_max_mb' => 10,
            'log_keep_days' => 14,
        ]);
    }
}
