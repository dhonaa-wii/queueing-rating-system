<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One in-app restore. Lives outside the data a restore replaces (see
 * BackupService::DATA_EXCLUDED), which is what lets it remember where it got
 * to while the tables around it are being dropped and recreated.
 */
class BackupRestore extends Model
{
    public const RUNNING = 'RUNNING';

    public const COMPLETED = 'COMPLETED';

    public const FAILED = 'FAILED';

    protected $fillable = [
        'backup_id',
        'source_file_name',
        'status',
        'token_hash',
        'triggered_by',
        'triggered_by_username',
        'safety_backup_id',
        'state',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isRunning(): bool
    {
        return $this->status === self::RUNNING;
    }

    public function phase(): string
    {
        return $this->state['phase'] ?? 'safety';
    }

    /** Whole-run progress: each phase owns a slice of the bar. */
    public function progressPercent(): int
    {
        if ($this->status === self::COMPLETED) {
            return 100;
        }

        $state = $this->state ?? [];

        return match ($state['phase'] ?? 'safety') {
            'safety' => (int) round(($state['safety_progress'] ?? 0) * 0.2),
            'apply' => 20 + (int) round(min(1, ($state['pos'] ?? 0) / max(1, $state['sql_bytes'] ?? 1)) * 65),
            'cleanup' => 86,
            'files' => 90,
            'finish' => 95,
            default => 0,
        };
    }

    public function phaseLabel(): string
    {
        return match ($this->phase()) {
            'safety' => 'Taking a safety backup',
            'apply' => 'Restoring the database',
            'cleanup' => 'Removing tables the backup does not have',
            'files' => 'Restoring uploaded files',
            'finish' => 'Updating the database structure',
            default => 'Working',
        };
    }
}
