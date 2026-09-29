<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    public const RUNNING = 'RUNNING';

    public const COMPLETED = 'COMPLETED';

    public const FAILED = 'FAILED';

    protected $fillable = [
        'status',
        'trigger',
        'triggered_by',
        'file_name',
        'size_bytes',
        'checksum_sha256',
        'is_encrypted',
        'table_count',
        'row_count',
        'state',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'is_encrypted' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by')->withTrashed();
    }

    public function isRunning(): bool
    {
        return $this->status === self::RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    /** Path of the finished archive, relative to the `local` disk. */
    public function relativePath(): ?string
    {
        return $this->file_name ? 'backups/'.$this->file_name : null;
    }

    /**
     * Human-readable size. Laravel's Number::fileSize() needs the intl
     * extension, which shared hosts frequently don't ship.
     */
    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return round($bytes / (1024 ** $power), $power === 0 ? 0 : 2).' '.$units[$power];
    }

    /** Who or what made it, for the "By" column. */
    public function originLabel(): string
    {
        return match ($this->trigger) {
            'SCHEDULED' => 'Scheduled',
            'PRE_RESTORE' => 'Before a restore',
            default => $this->triggeredBy?->username ?? 'System',
        };
    }

    /** 0–100, from how far through the tables the dump has got. */
    public function progressPercent(): int
    {
        if ($this->isCompleted()) {
            return 100;
        }

        $state = $this->state ?? [];
        $total = count($state['tables'] ?? []);
        if ($total === 0) {
            return 0;
        }

        if (($state['phase'] ?? 'dump') === 'package') {
            return 95;
        }

        return (int) min(94, floor(($state['table_index'] ?? 0) / $total * 94));
    }
}
