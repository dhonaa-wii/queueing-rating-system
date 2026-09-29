<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Services\RestoreService;
use Illuminate\Console\Command;

/**
 * The scheduler's entry point (every minute from cron): finish a restore that
 * was left part-way through, otherwise continue a backup that is part-way
 * through, otherwise start one if the configured schedule says it is due. Does
 * nothing — and costs a couple of queries — the rest of the time.
 */
class BackupTick extends Command
{
    protected $signature = 'backup:tick';

    protected $description = 'Continue a running restore or backup, or start a scheduled backup when due';

    public function handle(BackupService $backups, RestoreService $restores): int
    {
        // A restore closes the site, so it goes first: if the browser that
        // started it was closed, this is what reopens the site.
        if ($restore = $restores->tick()) {
            $this->info("Restore #{$restore->id}: {$restore->status} ({$restore->progressPercent()}%)");

            return $restore->status === 'FAILED' ? self::FAILURE : self::SUCCESS;
        }

        $backup = $backups->tick();

        if ($backup === null) {
            return self::SUCCESS;
        }

        $this->info("Backup #{$backup->id}: {$backup->status} ({$backup->progressPercent()}%)");

        return $backup->status === 'FAILED' ? self::FAILURE : self::SUCCESS;
    }
}
