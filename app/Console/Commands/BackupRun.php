<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Console\Command;

/** Take a backup right now from the command line and stay until it is finished. */
class BackupRun extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Create a full backup now (database + uploaded files)';

    public function handle(BackupService $backups): int
    {
        $backup = $backups->start('MANUAL', null);

        while ($backup->isRunning()) {
            $backup = $backups->step($backup, 20);
            $this->line("  {$backup->progressPercent()}%");
        }

        if ($backup->status === Backup::FAILED) {
            $this->error('Backup failed: '.$backup->error_message);

            return self::FAILURE;
        }

        $size = number_format($backup->size_bytes / 1048576, 2);
        $this->info("Backup complete: {$backup->file_name} ({$size} MB, {$backup->row_count} rows)");

        return self::SUCCESS;
    }
}
