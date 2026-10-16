<?php

namespace App\Services;

use App\Models\User;
use App\Support\SecurityPolicy;

/**
 * Failed-login lockout (Security Settings, 2026-10-16). Off until the
 * Super Admin sets it. A locked account unlocks by itself once its time is
 * up, or right away when an Admin/Super Admin resets its password.
 * Super Admin accounts are never locked (user-directed).
 */
class LoginLockout
{
    /** The refusal to show while the account is locked, else null. */
    public function lockedMessage(User $user): ?string
    {
        if (! $user->locked_until || $user->locked_until->isPast()) {
            return null;
        }

        $minutes = max(1, (int) ceil(now()->diffInSeconds($user->locked_until) / 60));

        return "This account is locked after too many failed sign-in attempts. Try again in {$minutes} minute".($minutes === 1 ? '' : 's').'.';
    }

    /** Count a wrong password. Returns the lock message if this attempt locked it. */
    public function recordFailure(User $user): ?string
    {
        $policy = SecurityPolicy::lockout();

        if (! $policy || $user->hasRole('SUPER_ADMIN')) {
            return null;
        }

        $attempts = $user->failed_login_attempts + 1;

        if ($attempts >= $policy['max_attempts']) {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => now()->addMinutes($policy['minutes']),
            ])->save();

            return $this->lockedMessage($user);
        }

        $user->forceFill(['failed_login_attempts' => $attempts])->save();

        return null;
    }

    public function clear(User $user): void
    {
        if ($user->failed_login_attempts || $user->locked_until) {
            $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();
        }
    }
}
