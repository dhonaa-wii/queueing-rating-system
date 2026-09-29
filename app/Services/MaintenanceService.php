<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\BackupRestore;
use App\Models\MaintenanceSetting;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Routine housekeeping for a host with no shell: clears out rows and files that
 * only ever accumulate, rotates the application log, and applies the audit-log
 * retention. run() does all of it (cron calls it once a day through
 * maintenance:run); preview() reports what run() would remove, using the same
 * queries so the page can never promise something different.
 */
class MaintenanceService
{
    private const FAILED_JOB_DAYS = 30;

    private const LEFTOVER_FILE_HOURS = 24;

    public function __construct(private BackupService $backups) {}

    /**
     * Do everything, record the result on the settings row and return it.
     *
     * @return array{ran_at: string, items: array<int, array{label: string, count: int}>}
     */
    public function run(): array
    {
        $items = [];

        foreach ($this->cleanupTargets() as $target) {
            $items[] = ['label' => $target['label'], 'count' => (int) ($target['query'])()->delete()];
        }

        $items[] = ['label' => 'Leftover temporary backup files', 'count' => $this->removeLeftoverFiles()];

        $logs = $this->rotateLogs();
        $items[] = ['label' => 'Log file rotated', 'count' => $logs['rotated'] ? 1 : 0];
        $items[] = ['label' => 'Old log files deleted', 'count' => $logs['deleted']];

        $items[] = ['label' => 'Audit entries past retention', 'count' => $this->pruneAudit()];

        $result = ['ran_at' => now()->toIso8601String(), 'items' => $items];

        MaintenanceSetting::current()->update(['last_run_at' => now(), 'last_run' => $result]);

        return $result;
    }

    /**
     * What run() would remove right now.
     *
     * @return array<int, array{label: string, count: int}>
     */
    public function preview(): array
    {
        $items = [];

        foreach ($this->cleanupTargets() as $target) {
            $items[] = ['label' => $target['label'], 'count' => (int) ($target['query'])()->count()];
        }

        $items[] = ['label' => 'Leftover temporary backup files', 'count' => count($this->leftoverFiles())];
        $items[] = ['label' => 'Audit entries past retention', 'count' => $this->expiredAuditQuery()?->count() ?? 0];

        return $items;
    }

    // ------------------------------------------------------------------
    // Rows that only accumulate
    // ------------------------------------------------------------------

    /** @return array<int, array{label: string, query: Closure(): Builder}> */
    private function cleanupTargets(): array
    {
        return [
            [
                'label' => 'Expired sign-in sessions',
                'query' => fn () => DB::table('sessions')
                    ->where('last_activity', '<', now()->subMinutes((int) config('session.lifetime'))->timestamp),
            ],
            [
                'label' => 'Expired password reset tokens',
                'query' => fn () => DB::table('password_reset_tokens')
                    ->where('created_at', '<', now()->subMinutes((int) config('auth.passwords.users.expire', 60))),
            ],
            [
                'label' => 'Used or expired room QR tokens',
                'query' => fn () => DB::table('terminal_access_tokens')->where(function ($query) {
                    $cutoff = now()->subDay();
                    $query->where('expires_at', '<', $cutoff)
                        ->orWhere('used_at', '<', $cutoff)
                        ->orWhere('revoked_at', '<', $cutoff);
                }),
            ],
            [
                'label' => 'Expired cache entries',
                'query' => fn () => DB::table('cache')->where('expiration', '<', time()),
            ],
            [
                'label' => 'Expired cache locks',
                'query' => fn () => DB::table('cache_locks')->where('expiration', '<', time()),
            ],
            [
                'label' => 'Failed jobs older than '.self::FAILED_JOB_DAYS.' days',
                'query' => fn () => DB::table('failed_jobs')->where('failed_at', '<', now()->subDays(self::FAILED_JOB_DAYS)),
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Leftover files from an interrupted backup or restore
    // ------------------------------------------------------------------

    /** @return string[] absolute paths */
    private function leftoverFiles(): array
    {
        $disk = $this->backups->disk();
        $cutoff = now()->subHours(self::LEFTOVER_FILE_HOURS)->timestamp;
        $found = [];

        $runningBackups = Backup::where('status', Backup::RUNNING)->pluck('id')->all();
        foreach (glob($disk->path('backups/tmp').'/*.sql') ?: [] as $file) {
            $id = (int) basename($file, '.sql');
            if (! in_array($id, $runningBackups, true) && filemtime($file) < $cutoff) {
                $found[] = $file;
            }
        }

        if (! BackupRestore::where('status', BackupRestore::RUNNING)->exists()) {
            foreach (glob($disk->path('backups/restore').'/*', GLOB_ONLYDIR) ?: [] as $directory) {
                if (filemtime($directory) < $cutoff) {
                    $found[] = $directory;
                }
            }
        }

        return $found;
    }

    private function removeLeftoverFiles(): int
    {
        $files = $this->leftoverFiles();

        foreach ($files as $path) {
            is_dir($path) ? File::deleteDirectory($path) : @unlink($path);
        }

        return count($files);
    }

    // ------------------------------------------------------------------
    // Application log
    // ------------------------------------------------------------------

    /**
     * Size of the live log and of the rotated ones beside it.
     *
     * @return array{size: int, max_bytes: int, rotated_count: int, rotated_bytes: int}
     */
    public function logStatus(): array
    {
        $rotated = $this->rotatedLogs();

        return [
            'size' => is_file($this->logPath()) ? (int) filesize($this->logPath()) : 0,
            'max_bytes' => MaintenanceSetting::current()->log_max_mb * 1024 * 1024,
            'rotated_count' => count($rotated),
            'rotated_bytes' => (int) array_sum(array_map('filesize', $rotated)),
        ];
    }

    /**
     * Move laravel.log aside once it passes the size limit (compressing it when
     * zlib is there) and delete rotated files older than the retention. The
     * `daily` channel makes its own dated files, which the same sweep ages out.
     *
     * @return array{rotated: bool, deleted: int}
     */
    public function rotateLogs(): array
    {
        $settings = MaintenanceSetting::current();
        $path = $this->logPath();
        $rotated = false;

        if (is_file($path) && filesize($path) > $settings->log_max_mb * 1024 * 1024) {
            $target = dirname($path).'/laravel-'.now()->format('Ymd-His').'.log';

            // Copy then truncate rather than rename: the file may be open in
            // another process, and on Windows a rename would simply fail.
            if (copy($path, $target)) {
                file_put_contents($path, '');
                $rotated = true;

                if (function_exists('gzopen')) {
                    $this->compress($target);
                }
            }
        }

        $cutoff = now()->subDays($settings->log_keep_days)->timestamp;
        $deleted = 0;

        foreach ($this->rotatedLogs() as $file) {
            if (filemtime($file) < $cutoff && @unlink($file)) {
                $deleted++;
            }
        }

        return ['rotated' => $rotated, 'deleted' => $deleted];
    }

    private function compress(string $file): void
    {
        $in = fopen($file, 'rb');
        $out = gzopen($file.'.gz', 'wb6');

        if (! $in || ! $out) {
            return;
        }

        while (! feof($in)) {
            gzwrite($out, fread($in, 1024 * 512));
        }

        fclose($in);
        gzclose($out);
        @unlink($file);
    }

    private function logPath(): string
    {
        return storage_path('logs/laravel.log');
    }

    /** @return string[] */
    private function rotatedLogs(): array
    {
        return array_merge(
            glob(storage_path('logs/laravel-*.log')) ?: [],
            glob(storage_path('logs/laravel-*.log.gz')) ?: [],
        );
    }

    // ------------------------------------------------------------------
    // Audit log retention
    // ------------------------------------------------------------------

    private function expiredAuditQuery(): ?Builder
    {
        $days = MaintenanceSetting::current()->audit_retention_days;

        return $days === null
            ? null
            : DB::table('audit_logs')->where('created_at', '<', now()->subDays($days));
    }

    /** Delete audit entries past the retention, and leave a note that it happened. */
    public function pruneAudit(): int
    {
        $query = $this->expiredAuditQuery();
        if ($query === null) {
            return 0;
        }

        $days = MaintenanceSetting::current()->audit_retention_days;
        $deleted = 0;

        // In slices, so a large backlog never becomes one enormous DELETE.
        do {
            $ids = (clone $query)->orderBy('id')->limit(2000)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : DB::table('audit_logs')->whereIn('id', $ids)->delete();
        } while ($ids->count() === 2000);

        if ($deleted > 0) {
            AuditLog::create([
                'action' => 'AUDIT_LOG_PRUNED',
                'entity_type' => AuditLog::class,
                'new_values' => ['deleted' => $deleted, 'older_than_days' => $days],
            ]);
        }

        return $deleted;
    }
}
