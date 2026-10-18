<?php

namespace App\Support;

use Throwable;

/**
 * The two idle lifetimes a session can have, in minutes.
 *
 * - account(): a session with a signed-in user. The Super Admin's Session
 *   Timeout when set, else SESSION_LIFETIME from .env.
 * - device(): a session with nobody signed in — above all a room session
 *   tablet, claimed once for the whole day (user-directed 2026-10-19). Never
 *   shortened by the Session Timeout setting.
 */
class SessionLifetimes
{
    public static function account(): int
    {
        try {
            $minutes = SecurityPolicy::sessionTimeout();
        } catch (Throwable) {
            // e.g. mid-restore, when system_settings is being rewritten.
            $minutes = null;
        }

        return $minutes ?? (int) config('session.env_lifetime', config('session.lifetime'));
    }

    public static function device(): int
    {
        return max(self::account(), (int) config('session.device_lifetime', 1440));
    }
}
