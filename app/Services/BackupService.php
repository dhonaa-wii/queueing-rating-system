<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\BackupRestore;
use App\Models\BackupSetting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Full-system backup: a database dump plus the uploaded files, packed into one
 * zip (optionally AES-encrypted).
 *
 * Built for shared hosting: no mysqldump/exec, no queue worker, and a short PHP
 * time limit. So the dump is pure PHP and *resumable* — every call to step()
 * works for a time budget, saves its position on the backups row and stops.
 * The UI keeps calling step() until the run finishes; the scheduler does the
 * same from cron. An interrupted request costs nothing: the next step truncates
 * the partial file back to the last saved byte and carries on.
 *
 * The dump writes exactly one statement per line (newlines inside string values
 * are escaped) so a restore can read it line by line without a SQL parser.
 */
class BackupService
{
    private const CHUNK_ROWS = 300;

    private const MAX_STATEMENT_BYTES = 524288;

    /**
     * Tables whose structure is kept but whose rows are not worth backing up.
     * A restore leaves these tables completely alone, too: sessions so people
     * stay signed in, cache and its locks because the restore itself is holding
     * one, and backups/backup_restores because they hold the run's own state.
     */
    public const DATA_EXCLUDED = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'backups', 'backup_restores'];

    /** Uploaded files (public disk) that travel with the database. */
    public const FILE_DIRECTORIES = ['evaluation-letterhead'];

    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * Begin a run. Only one may be in progress: asking for a second returns the
     * one already running.
     */
    public function start(string $trigger, ?int $userId): Backup
    {
        $running = Backup::where('status', Backup::RUNNING)->latest('id')->first();
        if ($running) {
            return $running;
        }

        // The restore's own safety backup is the one run allowed alongside it.
        if ($trigger !== 'PRE_RESTORE' && BackupRestore::where('status', BackupRestore::RUNNING)->exists()) {
            throw new RuntimeException('A restore is in progress. Try again once it has finished.');
        }

        $this->prepareDirectory();

        // The settings row is created on first read; make sure it exists before
        // the dump walks past that table, so a backup always carries it.
        BackupSetting::current();

        $tables = array_map(
            fn ($row) => (string) array_values((array) $row)[0],
            DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')
        );
        sort($tables);

        $backup = Backup::create([
            'status' => Backup::RUNNING,
            'trigger' => $trigger,
            'triggered_by' => $userId,
            'started_at' => now(),
            'state' => [
                'phase' => 'dump',
                'tables' => $tables,
                'table_index' => 0,
                'table_started' => false,
                'cursor' => null,
                'bytes' => 0,
                'rows' => 0,
                'counts' => [],
                'meta' => null,
            ],
        ]);

        $header = "-- Queueing Rating System backup\n"
            .'-- Created: '.now()->toDateTimeString().' ('.config('app.timezone').")\n"
            ."SET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\n"
            ."SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

        file_put_contents($this->sqlPath($backup), $header);
        $state = $backup->state;
        $state['bytes'] = strlen($header);
        $backup->update(['state' => $state]);

        return $backup;
    }

    /**
     * Work on a run for up to $budgetSeconds. Safe to call repeatedly and from
     * overlapping requests: a second caller simply gets the current state back.
     */
    public function step(Backup $backup, int $budgetSeconds = 20): Backup
    {
        $lock = Cache::lock('backup-step', $budgetSeconds + 60);
        if (! $lock->get()) {
            return $backup->refresh();
        }

        try {
            $backup->refresh();
            if (! $backup->isRunning()) {
                return $backup;
            }

            @set_time_limit($budgetSeconds + 30);
            $deadline = microtime(true) + $budgetSeconds;

            try {
                while ($backup->isRunning() && microtime(true) < $deadline) {
                    $phase = $backup->state['phase'] ?? 'dump';

                    if ($phase === 'dump') {
                        $this->dumpSlice($backup, $deadline);
                    } else {
                        $this->package($backup);
                    }

                    $backup->refresh();
                }
            } catch (Throwable $e) {
                $this->fail($backup, $e);
            }
        } finally {
            $lock->release();
        }

        return $backup->refresh();
    }

    /**
     * Scheduler entry point: continue a run in progress, otherwise start one if
     * the schedule says it is due. Returns null when there was nothing to do.
     */
    public function tick(int $budgetSeconds = 50): ?Backup
    {
        $running = Backup::where('status', Backup::RUNNING)->latest('id')->first();

        if (! $running) {
            $lastCompleted = Backup::where('status', Backup::COMPLETED)->max('completed_at');
            $due = BackupSetting::current()->isDue($lastCompleted ? \Illuminate\Support\Carbon::parse($lastCompleted) : null);

            // A failed attempt must not turn into a retry every minute.
            $recentAttempt = Backup::where('started_at', '>', now()->subMinutes(30))->exists();

            if (! $due || $recentAttempt || BackupRestore::where('status', BackupRestore::RUNNING)->exists()) {
                return null;
            }

            $running = $this->start('SCHEDULED', null);
        }

        return $this->step($running, $budgetSeconds);
    }

    /** Remove a backup's file and record (also cancels one still running). */
    public function delete(Backup $backup): void
    {
        $this->removeFiles($backup);
        $backup->delete();
    }

    /** Keep the newest N finished backups; drop older ones and stale failures. */
    public function prune(): int
    {
        $keep = max(1, BackupSetting::current()->keep_count);

        // The backup being restored, and the safety copy taken before it, must
        // survive the prune that the safety copy's own completion triggers.
        $protected = BackupRestore::where('status', BackupRestore::RUNNING)
            ->get(['backup_id', 'safety_backup_id'])
            ->flatMap(fn ($restore) => [$restore->backup_id, $restore->safety_backup_id])
            ->filter()
            ->all();

        $expired = Backup::where('status', Backup::COMPLETED)
            ->whereNotIn('id', $protected)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->get();

        foreach ($expired as $backup) {
            $this->delete($backup);
        }

        Backup::where('status', Backup::FAILED)->where('created_at', '<', now()->subDays(14))->delete();

        return $expired->count();
    }

    public function totalSizeBytes(): int
    {
        return (int) Backup::where('status', Backup::COMPLETED)->sum('size_bytes');
    }

    // ------------------------------------------------------------------
    // Dump
    // ------------------------------------------------------------------

    private function dumpSlice(Backup $backup, float $deadline): void
    {
        $state = $backup->state;
        $tables = $state['tables'];

        if ($state['table_index'] >= count($tables)) {
            $state['phase'] = 'package';
            $backup->update(['state' => $state]);

            return;
        }

        $pdo = DB::connection()->getPdo();
        $table = $tables[$state['table_index']];
        $path = $this->sqlPath($backup);

        // Drop anything written after the last saved position (an interrupted step).
        $handle = fopen($path, 'c+b');
        ftruncate($handle, $state['bytes']);
        fseek($handle, 0, SEEK_END);

        try {
            if (! $state['table_started']) {
                $create = DB::selectOne('SHOW CREATE TABLE '.$this->ident($table));
                $createSql = str_replace(["\r\n", "\n"], ' ', (string) array_values((array) $create)[1]);

                $out = '-- '.$table."\nDROP TABLE IF EXISTS ".$this->ident($table).";\n".$createSql.";\n";
                fwrite($handle, $out);

                $state['bytes'] += strlen($out);
                $state['table_started'] = true;
                $state['cursor'] = null;
                $state['meta'] = $this->tableMeta($table);
                $state['counts'][$table] = 0;
            }

            if (in_array($table, self::DATA_EXCLUDED, true)) {
                $this->advanceTable($state, $handle);
                $this->persist($backup, $state, $handle);

                return;
            }

            while (microtime(true) < $deadline) {
                $rows = $this->fetchChunk($table, $state['meta'], $state['cursor']);

                if ($rows === []) {
                    $this->advanceTable($state, $handle);
                    break;
                }

                $out = $this->insertStatements($pdo, $table, $rows, $state['meta']['binary']);
                fwrite($handle, $out);

                $state['bytes'] += strlen($out);
                $state['rows'] += count($rows);
                $state['counts'][$table] += count($rows);
                $state['cursor'] = $state['meta']['pk']
                    ? end($rows)[$state['meta']['pk']]
                    : ($state['cursor'] ?? 0) + count($rows);

                if (count($rows) < self::CHUNK_ROWS) {
                    $this->advanceTable($state, $handle);
                    break;
                }

                $this->persist($backup, $state, $handle);
            }

            $this->persist($backup, $state, $handle);
        } finally {
            fclose($handle);
        }
    }

    private function advanceTable(array &$state, $handle): void
    {
        fwrite($handle, "\n");
        $state['bytes'] += 1;
        $state['table_index']++;
        $state['table_started'] = false;
        $state['cursor'] = null;
        $state['meta'] = null;
    }

    private function persist(Backup $backup, array $state, $handle): void
    {
        fflush($handle);
        $backup->update(['state' => $state]);
    }

    /** @return array{pk: ?string, order: string, binary: string[]} */
    private function tableMeta(string $table): array
    {
        $columns = DB::select('SHOW COLUMNS FROM '.$this->ident($table));

        $primary = [];
        $binary = [];
        foreach ($columns as $column) {
            $column = (array) $column;
            if (($column['Key'] ?? '') === 'PRI') {
                $primary[] = $column['Field'];
            }
            if (preg_match('/(blob|binary)/i', (string) $column['Type'])) {
                $binary[] = $column['Field'];
            }
        }

        return [
            // Keyset paging only works off a single-column primary key.
            'pk' => count($primary) === 1 ? $primary[0] : null,
            'order' => implode(', ', array_map(fn ($c) => $this->ident($c), $primary)),
            'binary' => $binary,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchChunk(string $table, array $meta, mixed $cursor): array
    {
        $sql = 'SELECT * FROM '.$this->ident($table);
        $bindings = [];

        if ($meta['pk']) {
            if ($cursor !== null) {
                $sql .= ' WHERE '.$this->ident($meta['pk']).' > ?';
                $bindings[] = $cursor;
            }
            $sql .= ' ORDER BY '.$this->ident($meta['pk']).' LIMIT '.self::CHUNK_ROWS;
        } else {
            if ($meta['order'] !== '') {
                $sql .= ' ORDER BY '.$meta['order'];
            }
            $sql .= ' LIMIT '.self::CHUNK_ROWS.' OFFSET '.(int) ($cursor ?? 0);
        }

        // TIMESTAMP columns are read back in the session's zone, so the dump reads
        // them in UTC (the header sets UTC on restore). The session goes straight
        // back afterwards: leaving it on UTC made every timestamp written later in
        // the same request — completed_at included — land eight hours off.
        $previous = DB::selectOne('SELECT @@session.time_zone AS tz')->tz;
        DB::statement("SET time_zone = '+00:00'");

        try {
            return array_map(fn ($row) => (array) $row, DB::select($sql, $bindings));
        } finally {
            DB::statement('SET time_zone = '.DB::getPdo()->quote($previous));
        }
    }

    /** One INSERT per line, split so no statement exceeds the packet-safe size. */
    private function insertStatements(PDO $pdo, string $table, array $rows, array $binary): string
    {
        $columns = implode(', ', array_map(fn ($c) => $this->ident($c), array_keys($rows[0])));
        $head = 'INSERT INTO '.$this->ident($table).' ('.$columns.') VALUES ';

        $out = '';
        $values = [];
        $size = 0;

        foreach ($rows as $row) {
            $tuple = '('.implode(',', array_map(
                fn ($column, $value) => $this->literal($pdo, $value, in_array($column, $binary, true)),
                array_keys($row),
                $row
            )).')';

            if ($values !== [] && $size + strlen($tuple) > self::MAX_STATEMENT_BYTES) {
                $out .= $head.implode(',', $values).";\n";
                $values = [];
                $size = 0;
            }

            $values[] = $tuple;
            $size += strlen($tuple);
        }

        if ($values !== []) {
            $out .= $head.implode(',', $values).";\n";
        }

        return $out;
    }

    private function literal(PDO $pdo, mixed $value, bool $binary): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return sprintf('%.17g', $value);
        }

        $value = (string) $value;

        if ($binary) {
            return $value === '' ? "''" : '0x'.bin2hex($value);
        }

        // Escaped newlines keep every statement on a single line.
        return str_replace(["\r", "\n"], ['\\r', '\\n'], $pdo->quote($value));
    }

    private function ident(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    // ------------------------------------------------------------------
    // Packaging
    // ------------------------------------------------------------------

    private function package(Backup $backup): void
    {
        $state = $backup->state;
        $sqlPath = $this->sqlPath($backup);

        $handle = fopen($sqlPath, 'c+b');
        ftruncate($handle, $state['bytes']);
        fseek($handle, 0, SEEK_END);
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\nSET UNIQUE_CHECKS = 1;\n");
        fclose($handle);

        $password = BackupSetting::current()->archive_password;
        $fileName = 'qrs-backup-'.now()->format('Ymd-His').'-'.$backup->id.'.zip';
        $zipPath = $this->disk()->path('backups/'.$fileName);

        $files = [];
        foreach (self::FILE_DIRECTORIES as $directory) {
            foreach (Storage::disk('public')->allFiles($directory) as $file) {
                $files[] = $file;
            }
        }

        $manifest = json_encode([
            'application' => config('app.name'),
            'created_at' => now()->toIso8601String(),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'database_version' => DB::selectOne('SELECT VERSION() AS v')->v,
            'database' => DB::connection()->getDatabaseName(),
            'latest_migration' => DB::table('migrations')->orderByDesc('id')->value('migration'),
            'encrypted' => $password !== null && $password !== '',
            'database_sha256' => hash_file('sha256', $sqlPath),
            'table_count' => count($state['tables']),
            'row_count' => $state['rows'],
            'tables' => $state['counts'],
            'files' => $files,
            'excluded_data' => self::DATA_EXCLUDED,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup archive.');
        }

        $entries = ['database.sql' => $sqlPath];
        $zip->addFile($sqlPath, 'database.sql');
        $zip->setCompressionName('database.sql', ZipArchive::CM_DEFLATE);
        $zip->addFromString('manifest.json', $manifest);
        $entries['manifest.json'] = null;

        foreach ($files as $file) {
            $name = 'files/public/'.$file;
            $zip->addFile(Storage::disk('public')->path($file), $name);
            $entries[$name] = null;
        }

        if ($password) {
            $zip->setPassword($password);
            foreach (array_keys($entries) as $name) {
                if (! $zip->setEncryptionName($name, ZipArchive::EM_AES_256)) {
                    $zip->close();
                    @unlink($zipPath);
                    throw new RuntimeException('This server cannot encrypt zip archives. Clear the archive password to continue.');
                }
            }
        }

        if (! $zip->close()) {
            @unlink($zipPath);
            throw new RuntimeException('Could not write the backup archive.');
        }

        $size = filesize($zipPath);
        $checksum = hash_file('sha256', $zipPath);
        @unlink($sqlPath);

        $backup->update([
            'status' => Backup::COMPLETED,
            'file_name' => $fileName,
            'size_bytes' => $size,
            'checksum_sha256' => $checksum,
            'is_encrypted' => (bool) $password,
            'table_count' => count($state['tables']),
            'row_count' => $state['rows'],
            'state' => null,
            'completed_at' => now(),
        ]);

        $this->prune();
    }

    private function fail(Backup $backup, Throwable $e): void
    {
        report($e);
        $this->removeFiles($backup);

        $backup->update([
            'status' => Backup::FAILED,
            'error_message' => mb_substr($e->getMessage(), 0, 1000),
            'state' => null,
        ]);
    }

    // ------------------------------------------------------------------
    // Files
    // ------------------------------------------------------------------

    public function prepareDirectory(): void
    {
        $this->disk()->makeDirectory('backups/tmp');

        // Second guard on top of the folder living outside the web root.
        $htaccess = $this->disk()->path('backups/.htaccess');
        if (! is_file($htaccess)) {
            file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
    }

    private function sqlPath(Backup $backup): string
    {
        return $this->disk()->path('backups/tmp/'.$backup->id.'.sql');
    }

    private function removeFiles(Backup $backup): void
    {
        @unlink($this->sqlPath($backup));

        if ($path = $backup->relativePath()) {
            $this->disk()->delete($path);
        }
    }
}
