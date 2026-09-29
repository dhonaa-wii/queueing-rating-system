<?php

namespace App\Console\Commands;

use App\Services\MaintenanceService;
use Illuminate\Console\Command;

/** Daily housekeeping: stale rows, leftover files, log rotation, audit retention. */
class MaintenanceRun extends Command
{
    protected $signature = 'maintenance:run';

    protected $description = 'Clear expired sessions/tokens/cache, rotate logs and apply the audit-log retention';

    public function handle(MaintenanceService $maintenance): int
    {
        $result = $maintenance->run();

        foreach ($result['items'] as $item) {
            $this->line(str_pad((string) $item['count'], 6, ' ', STR_PAD_LEFT).'  '.$item['label']);
        }

        return self::SUCCESS;
    }
}
