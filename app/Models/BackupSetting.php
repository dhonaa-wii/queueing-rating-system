<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class BackupSetting extends Model
{
    public const OFF = 'OFF';

    public const DAILY = 'DAILY';

    public const WEEKLY = 'WEEKLY';

    protected $fillable = [
        'frequency',
        'run_time',
        'keep_count',
        'archive_password',
        'updated_by',
    ];

    protected $hidden = ['archive_password'];

    protected function casts(): array
    {
        return [
            'archive_password' => 'encrypted',
        ];
    }

    /** The one row that matters; created with defaults on first use. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'frequency' => self::OFF,
            'run_time' => '02:00',
            'keep_count' => 5,
        ]);
    }

    /**
     * Whether a scheduled backup should start now: the slot for today has
     * arrived (WEEKLY: Sundays only) and nothing completed since that slot.
     * A missed slot is picked up on the next tick, so a cron that was down
     * over the exact minute still gets its backup.
     */
    public function isDue(?CarbonInterface $lastCompletedAt, ?CarbonInterface $now = null): bool
    {
        if ($this->frequency === self::OFF) {
            return false;
        }

        $now ??= now();

        if ($this->frequency === self::WEEKLY && ! $now->isSunday()) {
            return false;
        }

        [$hour, $minute] = array_map('intval', explode(':', $this->run_time));
        $slot = $now->copy()->setTime($hour, $minute);

        if ($now->lt($slot)) {
            return false;
        }

        return $lastCompletedAt === null || $lastCompletedAt->lt($slot);
    }
}
