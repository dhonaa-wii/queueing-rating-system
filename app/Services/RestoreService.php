<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\BackupRestore;
use App\Models\User;
use App\Support\MaintenanceMode;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * In-app restore of a backup made by BackupService: replaces the database and
 * the uploaded files with what the archive holds.
 *
 * Same shape as the backup and for the same reasons — pure PHP, no shell, a short
 * PHP time limit — so it is resumable: step() works for a time budget, saves its
 * position on the backup_restores row and stops. The page keeps calling it, and
 * cron's backup:tick finishes the job if the browser is closed.
 *
 * What makes it safe enough to offer:
 *  1. The archive is opened, decrypted, checksummed and extracted in start(),
 *     before anything is touched. A wrong password or a damaged file fails there.
 *  2. The site is closed (MaintenanceMode::RESTORE) for the whole run.
 *  3. A fresh backup of the current state is taken first (the "safety" phase), so
 *     a restore can itself be undone.
 *  4. Tables the restore must not touch are skipped: sessions (so people stay
 *     signed in), cache and its locks, jobs, and the backups/backup_restores
 *     tables (which hold this very run's state).
 *  5. An interrupted request is harmless. If a step dies part-way, the next one
 *     rewinds to the start of the table it was in and redoes it (every table is
 *     DROP + CREATE + INSERT, so repeating one is exact).
 *
 * The step endpoint is authenticated by a per-run token, not the session: the
 * users table is rewritten during the run, so a request that has to look a user
 * up in it would fail at exactly the wrong moment.
 */
class RestoreService
{
    /** Matches the leading statement of a dump line and captures its table. */
    private const TABLE_STATEMENT = '/^(?:DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO) `((?:[^`]|``)+)`/';

    public function __construct(private BackupService $backups) {}

    public function running(): ?BackupRestore
    {
        return BackupRestore::where('status', BackupRestore::RUNNING)->latest('id')->first();
    }

    public function verifyToken(BackupRestore $restore, ?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($restore->token_hash, hash('sha256', $token));
    }

    // ------------------------------------------------------------------
    // Start
    // ------------------------------------------------------------------

    /**
     * Validate and unpack the archive, close the site and create the run. Throws
     * a RuntimeException with a user-facing message if the backup can't be used.
     *
     * @return array{0: BackupRestore, 1: string} the run and its raw step token
     */
    public function start(Backup $backup, ?User $user, ?string $password): array
    {
        if ($this->running()) {
            throw new RuntimeException('A restore is already in progress.');
        }
        if (Backup::where('status', Backup::RUNNING)->exists()) {
            throw new RuntimeException('A backup is running. Wait for it to finish before restoring.');
        }
        if (! $backup->isCompleted() || ! $backup->relativePath() || ! $this->backups->disk()->exists($backup->relativePath())) {
            throw new RuntimeException('That backup file is not available.');
        }

        $zip = new ZipArchive;
        if ($zip->open($this->backups->disk()->path($backup->relativePath())) !== true) {
            throw new RuntimeException('That file is not a readable backup archive.');
        }

        try {
            $manifest = $this->readManifest($zip, $password);
            $this->assertCompatible($manifest);

            $restore = BackupRestore::create([
                'backup_id' => $backup->id,
                'source_file_name' => $backup->file_name,
                'status' => BackupRestore::RUNNING,
                'token_hash' => 'pending',
                'triggered_by' => $user?->id,
                'triggered_by_username' => $user?->username,
                'started_at' => now(),
            ]);

            try {
                $sqlBytes = $this->extract($zip, $manifest, $restore);
            } catch (Throwable $e) {
                $this->removeWorkDir($restore);
                $restore->delete();

                throw $e;
            }
        } finally {
            $zip->close();
        }

        $token = Str::random(48);

        $restore->update([
            'token_hash' => hash('sha256', $token),
            'state' => [
                'phase' => 'safety',
                'safety_backup_id' => null,
                'safety_progress' => 0,
                'sql_bytes' => $sqlBytes,
                'pos' => 0,
                'table_pos' => 0,
                'busy' => false,
                'statements' => 0,
                'dump_tables' => array_keys($manifest['tables'] ?? []),
                'files' => $manifest['files'] ?? [],
                'previous_maintenance' => MaintenanceMode::state(),
            ],
        ]);

        MaintenanceMode::enable(MaintenanceMode::RESTORE, null, $user?->username);

        return [$restore, $token];
    }

    /** @return array<string, mixed> */
    private function readManifest(ZipArchive $zip, ?string $password): array
    {
        $stat = $zip->statName('manifest.json');
        if ($stat === false) {
            throw new RuntimeException('That file is not a backup made by this system.');
        }

        // 0 is ZipArchive::EM_NONE; anything else on the manifest is encryption.
        $encrypted = ($stat['encryption_method'] ?? 0) !== 0;

        if ($encrypted) {
            if ($password === null || $password === '') {
                throw new RuntimeException('This backup is password-protected. Enter its archive password.');
            }
            $zip->setPassword($password);
        }

        $raw = $zip->getFromName('manifest.json');
        if ($raw === false) {
            throw new RuntimeException($encrypted ? 'The archive password is incorrect.' : 'The backup archive is damaged.');
        }

        $manifest = json_decode($raw, true);
        if (! is_array($manifest) || empty($manifest['database_sha256']) || ! is_array($manifest['tables'] ?? null)) {
            throw new RuntimeException('The backup archive is damaged.');
        }

        return $manifest;
    }

    /**
     * A backup from a newer version of the application would restore a schema the
     * code does not know how to run. (An older one is fine: the run finishes by
     * migrating forward.)
     *
     * @param  array<string, mixed>  $manifest
     */
    private function assertCompatible(array $manifest): void
    {
        $latest = $manifest['latest_migration'] ?? null;
        if ($latest === null) {
            return;
        }

        $known = array_map(fn ($file) => basename($file, '.php'), glob(database_path('migrations/*.php')) ?: []);

        if (! in_array($latest, $known, true)) {
            throw new RuntimeException("This backup was made by a newer version of the application (it has migration {$latest}). Update the application first.");
        }
    }

    /**
     * Unpack database.sql (verifying its checksum as it streams out) and the
     * uploaded files into the run's work folder. Returns the SQL size.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function extract(ZipArchive $zip, array $manifest, BackupRestore $restore): int
    {
        $dir = $this->workPath($restore);
        File::ensureDirectoryExists($dir.'/files');

        $stat = $zip->statName('database.sql');
        if ($stat === false) {
            throw new RuntimeException('The backup archive is damaged.');
        }

        $free = @disk_free_space($dir);
        if ($free !== false && $free < $stat['size'] * 1.2) {
            throw new RuntimeException('Not enough free disk space to unpack this backup.');
        }

        $in = $zip->getStream('database.sql');
        if ($in === false) {
            throw new RuntimeException('The backup archive is damaged.');
        }

        $out = fopen($dir.'/database.sql', 'wb');
        $hash = hash_init('sha256');
        $bytes = 0;

        while (! feof($in)) {
            $chunk = fread($in, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            hash_update($hash, $chunk);
            fwrite($out, $chunk);
            $bytes += strlen($chunk);
        }

        fclose($in);
        fclose($out);

        if (! hash_equals($manifest['database_sha256'], hash_final($hash))) {
            throw new RuntimeException('The backup failed its integrity check and was not restored.');
        }

        foreach ($manifest['files'] ?? [] as $file) {
            $this->assertSafePath($file);

            $stream = $zip->getStream('files/public/'.$file);
            if ($stream === false) {
                throw new RuntimeException('The backup archive is damaged.');
            }

            $target = $dir.'/files/'.$file;
            File::ensureDirectoryExists(dirname($target));
            $handle = fopen($target, 'wb');
            stream_copy_to_stream($stream, $handle);
            fclose($handle);
            fclose($stream);
        }

        return $bytes;
    }

    private function assertSafePath(string $file): void
    {
        if ($file === '' || str_contains($file, '..') || str_starts_with($file, '/') || str_contains($file, '\\')) {
            throw new RuntimeException('The backup archive contains an unsafe file path.');
        }
    }

    // ------------------------------------------------------------------
    // Step
    // ------------------------------------------------------------------

    /**
     * Work on a run for up to $budgetSeconds. Safe to call repeatedly and from
     * overlapping requests: a second caller simply gets the current state back.
     */
    public function step(BackupRestore $restore, int $budgetSeconds = 20): BackupRestore
    {
        $lock = Cache::lock('restore-step', $budgetSeconds + 60);
        if (! $lock->get()) {
            return $restore->refresh();
        }

        try {
            $restore->refresh();
            if (! $restore->isRunning()) {
                return $restore;
            }

            @set_time_limit($budgetSeconds + 60);
            $deadline = microtime(true) + $budgetSeconds;

            try {
                while ($restore->isRunning() && microtime(true) < $deadline) {
                    match ($restore->phase()) {
                        'safety' => $this->safety($restore, $deadline),
                        'apply' => $this->apply($restore, $deadline),
                        'cleanup' => $this->cleanup($restore),
                        'files' => $this->files($restore),
                        default => $this->finish($restore),
                    };

                    $restore->refresh();
                }
            } catch (Throwable $e) {
                $this->fail($restore, $e);
            }
        } finally {
            $lock->release();
        }

        return $restore->refresh();
    }

    /** Cron's entry point: carry on a run in progress. Null when there is none. */
    public function tick(int $budgetSeconds = 50): ?BackupRestore
    {
        $restore = $this->running();

        return $restore ? $this->step($restore, $budgetSeconds) : null;
    }

    /** Take a backup of what is about to be replaced. */
    private function safety(BackupRestore $restore, float $deadline): void
    {
        $state = $restore->state;

        $safety = $state['safety_backup_id'] ? Backup::find($state['safety_backup_id']) : null;

        if (! $safety) {
            $safety = $this->backups->start('PRE_RESTORE', $restore->triggered_by);
            $state['safety_backup_id'] = $safety->id;
            $restore->update(['safety_backup_id' => $safety->id, 'state' => $state]);
        }

        $safety = $this->backups->step($safety, max(1, (int) ceil($deadline - microtime(true))));

        if ($safety->status === Backup::FAILED) {
            throw new RuntimeException('Could not take a safety backup first: '.($safety->error_message ?: 'unknown error').' Nothing was changed.');
        }

        if ($safety->status === Backup::COMPLETED) {
            $state['phase'] = 'apply';
            $state['safety_progress'] = 100;
        } else {
            $state['safety_progress'] = $safety->progressPercent();
        }

        $restore->update(['state' => $state]);
    }

    /** Replay database.sql, one statement per line, skipping the kept tables. */
    private function apply(BackupRestore $restore, float $deadline): void
    {
        $state = $restore->state;
        $path = $this->workPath($restore).'/database.sql';

        $this->prepareSession();

        // A previous step died mid-flight: redo the table it was in from its start.
        if (! empty($state['busy'])) {
            $state['pos'] = $state['table_pos'];
        }
        $state['busy'] = true;
        $restore->update(['state' => $state]);

        $excluded = BackupService::DATA_EXCLUDED;
        $handle = fopen($path, 'rb');
        fseek($handle, $state['pos']);
        $finished = false;
        $sinceSave = 0;
        $table = null;

        try {
            while (microtime(true) < $deadline) {
                $start = ftell($handle);
                $line = fgets($handle);

                if ($line === false) {
                    $finished = true;
                    break;
                }

                $sql = rtrim($line, "\r\n");

                if ($sql === '' || str_starts_with($sql, '--')) {
                    $state['pos'] = ftell($handle);

                    continue;
                }

                if (preg_match(self::TABLE_STATEMENT, $sql, $m)) {
                    $table = str_replace('``', '`', $m[1]);

                    if (in_array($table, $excluded, true)) {
                        $state['pos'] = ftell($handle);

                        continue;
                    }

                    if (str_starts_with($sql, 'DROP TABLE')) {
                        $state['table_pos'] = $start;
                    }
                }

                try {
                    DB::unprepared($sql);
                } catch (Throwable $e) {
                    throw new RuntimeException('Restore stopped'.($table ? " while restoring table {$table}" : '').': '.Str::limit($e->getMessage(), 300), 0, $e);
                }

                $state['pos'] = ftell($handle);
                $state['statements']++;

                // Position and table start are saved together, so the pair is always consistent.
                if (++$sinceSave >= 25) {
                    $restore->update(['state' => $state]);
                    $sinceSave = 0;
                }
            }
        } finally {
            fclose($handle);
        }

        $state['busy'] = false;
        if ($finished) {
            $state['pos'] = $state['sql_bytes'];
            $state['phase'] = 'cleanup';
        }

        $restore->update(['state' => $state]);
    }

    /** Drop tables the backup never had — they belong to migrations newer than it. */
    private function cleanup(BackupRestore $restore): void
    {
        DB::reconnect();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        $inDump = $restore->state['dump_tables'];

        foreach ($this->baseTables() as $table) {
            if (! in_array($table, $inDump, true) && ! in_array($table, BackupService::DATA_EXCLUDED, true)) {
                DB::statement('DROP TABLE `'.str_replace('`', '``', $table).'`');
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->advance($restore, 'files');
    }

    /** Make the uploaded files match the backup: what it held, and nothing else. */
    private function files(BackupRestore $restore): void
    {
        $public = Storage::disk('public');
        $source = $this->workPath($restore).'/files/';

        foreach (BackupService::FILE_DIRECTORIES as $directory) {
            $public->deleteDirectory($directory);
        }

        foreach ($restore->state['files'] as $file) {
            $stream = fopen($source.$file, 'rb');
            $public->put($file, $stream);
            fclose($stream);
        }

        $this->advance($restore, 'finish');
    }

    /** Bring the schema up to what this version of the code expects, then reopen. */
    private function finish(BackupRestore $restore): void
    {
        // Fresh connection: drops the relaxed session settings the replay used.
        DB::reconnect();

        // A backup from an older version has fewer tables and an older migrations
        // table; running forward is what turns it into a working database.
        Artisan::call('migrate', ['--force' => true]);

        $state = $restore->state;
        $previous = $state['previous_maintenance'] ?? null;

        $this->removeWorkDir($restore);

        if (is_array($previous) && ($previous['mode'] ?? null) === MaintenanceMode::MAINTENANCE) {
            MaintenanceMode::enable(MaintenanceMode::MAINTENANCE, $previous['message'] ?? null, $previous['by'] ?? null);
        } else {
            MaintenanceMode::disable();
        }

        $restore->update([
            'status' => BackupRestore::COMPLETED,
            'completed_at' => now(),
            'state' => array_merge($state, ['phase' => 'done']),
        ]);

        // Written after the replay: an entry made before it would have been wiped
        // along with the rest of audit_logs.
        try {
            AuditLog::create([
                'user_id' => $restore->triggered_by && User::whereKey($restore->triggered_by)->exists() ? $restore->triggered_by : null,
                'action' => 'BACKUP_RESTORED',
                'entity_type' => Backup::class,
                'entity_id' => $restore->backup_id,
                'new_values' => [
                    'file_name' => $restore->source_file_name,
                    'restored_by' => $restore->triggered_by_username,
                    'safety_backup_id' => $restore->safety_backup_id,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function advance(BackupRestore $restore, string $phase): void
    {
        $state = $restore->state;
        $state['phase'] = $phase;
        $restore->update(['state' => $state]);
    }

    private function fail(BackupRestore $restore, Throwable $e): void
    {
        report($e);

        $this->removeWorkDir($restore);

        // Leave the site closed rather than serving a half-restored database, but
        // let a Super Admin back in (if the users table survived) to look at it.
        MaintenanceMode::enable(
            MaintenanceMode::MAINTENANCE,
            'A restore did not finish and the system has been closed until an administrator reviews it.',
            $restore->triggered_by_username,
        );

        $restore->update([
            'status' => BackupRestore::FAILED,
            'error_message' => mb_substr($e->getMessage(), 0, 1000),
            'completed_at' => now(),
        ]);

        // Best effort: the database may be the very thing that broke.
        try {
            AuditLog::create([
                'user_id' => $restore->triggered_by && User::whereKey($restore->triggered_by)->exists() ? $restore->triggered_by : null,
                'action' => 'BACKUP_RESTORE_FAILED',
                'entity_type' => Backup::class,
                'entity_id' => $restore->backup_id,
                'new_values' => ['file_name' => $restore->source_file_name, 'error' => mb_substr($e->getMessage(), 0, 300)],
            ]);
        } catch (Throwable) {
            // Nothing more can be done; backup_restores already has the error.
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function prepareSession(): void
    {
        DB::statement('SET NAMES utf8mb4');
        DB::statement("SET time_zone = '+00:00'");
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::statement('SET UNIQUE_CHECKS = 0');
        DB::statement("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
    }

    /** @return string[] */
    private function baseTables(): array
    {
        return array_map(
            fn ($row) => (string) array_values((array) $row)[0],
            DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')
        );
    }

    private function workPath(BackupRestore $restore): string
    {
        return $this->backups->disk()->path('backups/restore/'.$restore->id);
    }

    private function removeWorkDir(BackupRestore $restore): void
    {
        File::deleteDirectory($this->workPath($restore));
    }
}
