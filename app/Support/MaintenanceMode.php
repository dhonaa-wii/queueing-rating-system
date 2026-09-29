<?php

namespace App\Support;

/**
 * The app's own maintenance switch.
 *
 * Kept in a file rather than a table or the cache on purpose: an in-app restore
 * replaces the database underneath itself, and a flag stored there would be
 * overwritten by whatever the backup held (off), letting everyone back in
 * halfway through. It is also the one thing that can be cleared by hand on a
 * host with no shell — delete the file.
 *
 * Two modes:
 *  - MAINTENANCE: the Super Admin turned it on. Signed-in Super Admins get
 *    through, everyone else sees the maintenance page.
 *  - RESTORE: a restore is rewriting the database. Nobody gets through (not
 *    even a Super Admin — the users table may be half-written), except the
 *    token-authenticated restore step that is doing the work.
 */
class MaintenanceMode
{
    public const MAINTENANCE = 'MAINTENANCE';

    public const RESTORE = 'RESTORE';

    private static ?array $cache = null;

    public static function path(): string
    {
        return storage_path('framework/qrs-maintenance.json');
    }

    /** @return array{mode: string, message: ?string, since: string, by: ?string}|null */
    public static function state(): ?array
    {
        if (self::$cache !== null) {
            return self::$cache ?: null;
        }

        $path = self::path();
        $data = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;

        self::$cache = is_array($data) && isset($data['mode']) ? $data : [];

        return self::$cache ?: null;
    }

    public static function active(): bool
    {
        return self::state() !== null;
    }

    public static function restoring(): bool
    {
        return (self::state()['mode'] ?? null) === self::RESTORE;
    }

    public static function enable(string $mode, ?string $message = null, ?string $by = null): void
    {
        $state = [
            'mode' => $mode,
            'message' => $message !== null && trim($message) !== '' ? trim($message) : null,
            'since' => now()->toIso8601String(),
            'by' => $by,
        ];

        file_put_contents(self::path(), json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
        self::$cache = $state;
    }

    public static function disable(): void
    {
        @unlink(self::path());
        self::$cache = [];
    }

    /** Forget what was read, so a state changed mid-process is re-read. */
    public static function refresh(): void
    {
        self::$cache = null;
    }
}
