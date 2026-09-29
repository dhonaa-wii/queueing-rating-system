<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PanelistCredentialGenerator
{
    public function username(string $firstName, ?int $ignoreUserId = null): string
    {
        $base = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $firstName)) ?: 'panelist';

        $username = $base;
        $suffix = 2;

        // withTrashed: a deleted panelist keeps their row (their history hangs off
        // it) and the unique index still counts it, so its name is not free.
        while (User::withTrashed()->where('username', $username)->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))->exists()) {
            $username = $base.$suffix;
            $suffix++;
        }

        return $username;
    }

    public function password(string $firstName, string $lastName): string
    {
        $activeAcademicYear = AcademicYear::where('is_active', true)->first();

        if (! $activeAcademicYear) {
            throw ValidationException::withMessages([
                'college_id' => 'Cannot generate a panelist password: no Academic Year is currently set active. Set one in Academic Information first.',
            ]);
        }

        $firstInitial = strtoupper(substr($firstName, 0, 1));
        $lastInitial = strtoupper(substr($lastName, 0, 1));

        return $firstInitial.$lastInitial.$activeAcademicYear->start_year;
    }
}
