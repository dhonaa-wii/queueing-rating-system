<?php

namespace App\Services;

use App\Models\PresentationDate;
use App\Models\PresentationDateRoom;
use App\Models\RoomSessionAccount;
use Illuminate\Support\Str;

/**
 * One reusable login per room, generated fresh each day — user-directed
 * 2026-09-07 (replacing the earlier 2026-08-15 design, where one account
 * per (category, room) was generated the moment a room's panelist_count
 * was set and reused for the whole category's lifetime): an account now
 * only exists while its day is actually live. generateForRoom() is called
 * from EventActivationService::startRoom(), right after that room's
 * session/terminals are created (since 2026-09-13 rooms are the only thing
 * started — there is no day-level Start Event anymore); removeForRoom() is
 * called from endRoom() and removeForDate() from end()
 * (and, defensively, autoCancelNeverStarted() — a never-started date
 * should never have one, but costs nothing to also clear). No more
 * idempotent "sync on every page load" — generation/removal now happen
 * exactly once, at the moment the day actually starts/stops, not
 * recomputed speculatively from Presentation Setup or Live Monitoring page
 * loads. Its concurrent-login cap is the room's required panelist_count —
 * backups can still use the account to fill a seat but never raise the
 * cap. Deliberately not folded into PanelistCredentialGenerator: that
 * generates a personal, individually-owned account; this is a shared
 * operational credential scoped to a room's one live day, closer in spirit
 * to a kiosk login than a personal one.
 */
class RoomSessionAccountService
{
    /**
     * Called the moment a day stops being live — its last room closing,
     * the midnight auto-end, or a never-started date getting auto-cancelled. Hard-deletes
     * rather than deactivating: unlike the old reusable account, this
     * credential has no life beyond its one day, so there's nothing worth
     * keeping around — RoomSessionController::requireAccount() already
     * treats a missing row exactly the same as an inactive one (session
     * forgotten, bounced back to login), so a tablet still connected at
     * the moment this runs degrades the same way it always did.
     */
    public function removeForDate(PresentationDate $date): void
    {
        RoomSessionAccount::where('presentation_date_id', $date->id)->delete();
    }

    /**
     * Per-room counterpart to removeForDate(), for the
     * room-at-a-time Start/End Room actions (user-directed 2026-09-12) —
     * one room going live doesn't generate credentials for rooms that
     * haven't started, and one room closing doesn't invalidate the tablets
     * still logged into the rooms that are still running. Keyed the same
     * way: one account per (presentation date, room name).
     */
    public function generateForRoom(PresentationDate $date, PresentationDateRoom $room, int $performedByUserId): RoomSessionAccount
    {
        $existing = RoomSessionAccount::where('presentation_date_id', $date->id)
            ->where('room_name', $room->room_name)
            ->first();

        if ($existing) {
            return $existing;
        }

        return RoomSessionAccount::create([
            'presentation_category_id' => $date->category_id,
            'presentation_date_id' => $date->id,
            'room_name' => $room->room_name,
            'username' => $this->generateUsername($date, $room->room_name),
            'password' => $this->generatePassword(),
            'max_concurrent_logins' => (int) $room->panelist_count,
            'is_active' => true,
            'generated_by' => $performedByUserId,
            'generated_at' => now(),
        ]);
    }

    public function removeForRoom(PresentationDate $date, PresentationDateRoom $room): void
    {
        RoomSessionAccount::where('presentation_date_id', $date->id)
            ->where('room_name', $room->room_name)
            ->delete();
    }

    /**
     * User-directed 2026-08-15 (reversing the earlier "never store
     * retrievably" posture this originally copied from the Panelist
     * reset-password flow): unlike a personal account, this shared room
     * credential's password is meant to stay visible on the Live
     * Monitoring card at all times, not just once — so an Admin can spot
     * unexpected terminal activity against the credential currently in
     * use and reset it on the spot. This still issues a brand-new
     * password (the old one stops working immediately) — it's a reset,
     * not just a reveal of the existing one. Username never changes.
     */
    public function resetCredentials(RoomSessionAccount $account, int $performedByUserId): string
    {
        $password = $this->generatePassword();

        $account->update([
            'password' => $password,
            'credentials_last_reset_at' => now(),
            'credentials_reset_by' => $performedByUserId,
        ]);

        return $password;
    }

    private function generateUsername(PresentationDate $date, string $roomName): string
    {
        $date->loadMissing('category');

        preg_match_all('/[A-Za-z0-9]+/', $date->category->name, $words);
        $initials = collect($words[0])->map(fn ($word) => strtoupper($word[0]))->implode('');
        $prefix = strtolower(substr($initials, 0, 4) ?: 'cat');

        $roomSlug = Str::slug($roomName) ?: 'room';
        $base = "{$prefix}-{$roomSlug}";

        $username = $base;
        $suffix = 2;

        while (RoomSessionAccount::where('username', $username)->exists()) {
            $username = "{$base}-{$suffix}";
            $suffix++;
        }

        return $username;
    }

    private function generatePassword(): string
    {
        // Excludes visually ambiguous characters (0/O, 1/I/l) — this gets
        // handwritten or read aloud to a room full of panelists sharing one
        // device, unlike a personal password typed by its own owner.
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        $password = '';
        for ($i = 0; $i < 8; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
