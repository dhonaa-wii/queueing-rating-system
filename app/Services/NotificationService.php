<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * First writer for the `notifications` table (confirmed via grep before
 * building — fully migrated, never read from or written to anywhere).
 * Deliberately thin: one notification type exists today
 * (PANEL_SUBSTITUTION_REQUESTED, see App\Services\PanelSubstitutionService),
 * but this stays generic so a future notification doesn't need its own
 * bespoke plumbing.
 */
class NotificationService
{
    public function notify(User $user, string $type, string $title, string $message, ?Model $related = null, ?array $data = null): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'notification_type' => $type,
            'title' => $title,
            'message' => $message,
            'related_type' => $related ? $related::class : null,
            'related_id' => $related?->getKey(),
            'data' => $data,
        ]);
    }

    public function notifyRole(string $roleCode, string $type, string $title, string $message, ?Model $related = null): void
    {
        User::whereHas('userRoles.role', fn ($query) => $query->where('code', $roleCode))
            ->get()
            ->each(fn (User $user) => $this->notify($user, $type, $title, $message, $related));
    }

    public function unreadCountFor(User $user): int
    {
        return Notification::where('user_id', $user->id)->whereNull('read_at')->count();
    }

    public function recentFor(User $user, int $limit = 10): Collection
    {
        return Notification::where('user_id', $user->id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function markRead(Notification $notification): void
    {
        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }
    }

    /**
     * Bulk-clears every admin's notification for one resolved item (e.g. a
     * PanelSubstitutionRequest that just got approved/rejected) — used so
     * the bell badge count drops for every Admin once one of them acts,
     * not just the one who clicked Confirm/Reject.
     */
    public function markRelatedRead(Model $related): void
    {
        Notification::where('related_type', $related::class)
            ->where('related_id', $related->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
