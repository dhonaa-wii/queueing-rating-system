<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\BackupRestore;
use App\Models\BackupSetting;
use App\Models\MaintenanceSetting;
use App\Support\MaintenanceMode;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * The Backup & Maintenance page's health tab: a short list of things that, when
 * they go wrong on shared hosting, do so silently — cron not running, backups
 * quietly overdue, the disk filling up. Everything is read on demand; nothing is
 * stored, so it can't go stale.
 *
 * Each check is [label, status, detail] with status ok / warning / problem; the
 * overall status is the worst of them.
 */
class HealthService
{
    public const OK = 'ok';

    public const WARNING = 'warning';

    public const PROBLEM = 'problem';

    /** Cache key the scheduler's heartbeat task writes every minute. */
    public const HEARTBEAT_KEY = 'scheduler:heartbeat';

    public function __construct(private MaintenanceService $maintenance) {}

    /**
     * @return array{overall: string, checks: array<int, array{label: string, status: string, detail: string}>}
     */
    public function report(): array
    {
        $checks = [
            $this->scheduler(),
            $this->backups(),
            $this->restore(),
            $this->disk(),
            $this->migrations(),
            $this->maintenanceRun(),
            $this->logFile(),
            $this->maintenanceMode(),
            $this->environment(),
            $this->database(),
        ];

        $checks = array_values(array_filter($checks));

        $overall = self::OK;
        foreach ($checks as $check) {
            if ($check['status'] === self::PROBLEM) {
                $overall = self::PROBLEM;
                break;
            }
            if ($check['status'] === self::WARNING) {
                $overall = self::WARNING;
            }
        }

        return ['overall' => $overall, 'checks' => $checks];
    }

    /** @return array{label: string, status: string, detail: string} */
    private function check(string $label, string $status, string $detail): array
    {
        return ['label' => $label, 'status' => $status, 'detail' => $detail];
    }

    private function scheduler(): array
    {
        $beat = Cache::get(self::HEARTBEAT_KEY);

        if ($beat === null) {
            return $this->check('Scheduler', self::PROBLEM, 'Never seen running. Automatic backups and maintenance need the cron job that runs schedule:run every minute.');
        }

        $age = now()->timestamp - (int) $beat;

        return $age > 600
            ? $this->check('Scheduler', self::PROBLEM, 'Last ran '.now()->subSeconds($age)->diffForHumans().'. The cron job may have stopped.')
            : $this->check('Scheduler', self::OK, 'Running (last tick '.now()->subSeconds($age)->diffForHumans().').');
    }

    private function backups(): array
    {
        $last = Backup::where('status', Backup::COMPLETED)->orderByDesc('completed_at')->first();
        $latest = Backup::whereIn('status', [Backup::COMPLETED, Backup::FAILED])->orderByDesc('id')->first();
        $settings = BackupSetting::current();

        if (! $last) {
            return $this->check('Backups', self::WARNING, 'No backup has been taken yet.');
        }

        $age = $last->completed_at->diffForHumans();

        if ($latest && $latest->status === Backup::FAILED && $latest->id > $last->id) {
            return $this->check('Backups', self::WARNING, "The most recent attempt failed. Last good backup {$age}.");
        }

        if ($settings->frequency === BackupSetting::OFF) {
            return $last->completed_at->lt(now()->subDays(7))
                ? $this->check('Backups', self::WARNING, "Automatic backups are off and the last backup was {$age}.")
                : $this->check('Backups', self::OK, "Last backup {$age}. Automatic backups are off.");
        }

        $allowed = $settings->frequency === BackupSetting::WEEKLY ? 9 * 24 : 36;

        return $last->completed_at->lt(now()->subHours($allowed))
            ? $this->check('Backups', self::PROBLEM, "Overdue: the last backup was {$age}.")
            : $this->check('Backups', self::OK, "Last backup {$age}.");
    }

    private function restore(): ?array
    {
        $last = BackupRestore::orderByDesc('id')->first();

        if (! $last) {
            return null;
        }

        if ($last->status === BackupRestore::FAILED) {
            return $this->check('Restore', self::PROBLEM, 'The last restore failed: '.($last->error_message ?: 'unknown error'));
        }

        return null;
    }

    private function disk(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        if ($free === false || ! $total) {
            return $this->check('Disk space', self::WARNING, 'Could not be read on this host.');
        }

        $detail = Backup::formatBytes((int) $free).' free of '.Backup::formatBytes((int) $total).'.';
        $percent = $free / $total * 100;

        $status = ($free < 100 * 1024 * 1024 || $percent < 3) ? self::PROBLEM
            : (($free < 500 * 1024 * 1024 || $percent < 10) ? self::WARNING : self::OK);

        return $this->check('Disk space', $status, $detail);
    }

    private function migrations(): array
    {
        $pending = $this->pendingMigrations();

        return $pending > 0
            ? $this->check('Database structure', self::PROBLEM, "{$pending} migration(s) have not been applied. Run php artisan migrate.")
            : $this->check('Database structure', self::OK, 'Up to date.');
    }

    private function pendingMigrations(): int
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
        $ran = $migrator->getRepository()->getRan();

        return count(array_diff($files, $ran));
    }

    private function maintenanceRun(): array
    {
        $settings = MaintenanceSetting::current();

        if (! $settings->last_run_at) {
            return $this->check('Routine maintenance', self::WARNING, 'Has not run yet.');
        }

        return $settings->last_run_at->lt(now()->subDays(3))
            ? $this->check('Routine maintenance', self::WARNING, 'Last ran '.$settings->last_run_at->diffForHumans().'.')
            : $this->check('Routine maintenance', self::OK, 'Last ran '.$settings->last_run_at->diffForHumans().'.');
    }

    private function logFile(): array
    {
        $log = $this->maintenance->logStatus();
        $detail = 'Application log is '.Backup::formatBytes($log['size']).'.';

        return $log['size'] > $log['max_bytes'] * 3
            ? $this->check('Log file', self::WARNING, $detail.' It is well past its limit; rotation may not be running.')
            : $this->check('Log file', self::OK, $detail);
    }

    private function maintenanceMode(): ?array
    {
        $state = MaintenanceMode::state();

        if ($state === null) {
            return null;
        }

        return $this->check('Maintenance mode', self::WARNING, 'On since '.now()->parse($state['since'])->diffForHumans().'. Only Super Admins can use the system.');
    }

    private function environment(): array
    {
        $missing = [];
        foreach (['zip' => ZipArchive::class] as $extension => $class) {
            if (! class_exists($class)) {
                $missing[] = $extension;
            }
        }

        if ($missing) {
            return $this->check('Server', self::PROBLEM, 'Missing PHP extension: '.implode(', ', $missing).'. Backups need it.');
        }

        if (app()->isProduction() && config('app.debug')) {
            return $this->check('Server', self::PROBLEM, 'Debug mode is on in production. Set APP_DEBUG=false.');
        }

        return $this->check('Server', self::OK, 'PHP '.PHP_VERSION.' · Laravel '.app()->version().' · '.config('app.env').'.');
    }

    private function database(): array
    {
        $row = DB::selectOne(
            'SELECT COALESCE(SUM(data_length + index_length), 0) AS bytes, COUNT(*) AS tables FROM information_schema.tables WHERE table_schema = DATABASE()'
        );

        return $this->check('Database', self::OK, Backup::formatBytes((int) $row->bytes).' across '.$row->tables.' tables.');
    }
}
