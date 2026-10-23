<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\QueueEntry;
use App\Models\User;
use App\Support\AdminCollege;
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
        // Admins only hear about their own college (AdminCollege): a
        // notification about a record that belongs to a college goes to that
        // college's Admins.
        $college = $roleCode === 'ADMIN' && $related ? AdminCollege::ownerOf($related) : false;

        User::whereHas('userRoles.role', fn ($query) => $query->where('code', $roleCode))
            ->when(is_int($college), fn ($query) => $query->whereHas('administratorProfile', fn ($p) => $p->where('college_id', $college)))
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
     * Marks read the unread notifications whose issue is already gone, so
     * Needs Attention only lists what still needs doing: anything about a
     * record that no longer exists, and a deferred-group notice once that
     * group is back in the queue or has a recorded outcome. The other types
     * clear themselves in the sweeps that raise them.
     */
    public function clearResolved(): void
    {
        $unread = Notification::whereNull('read_at')->whereNotNull('related_type')->get(['id', 'notification_type', 'related_type', 'related_id']);
        $staleIds = [];

        foreach ($unread->groupBy('related_type') as $class => $items) {
            if (! class_exists($class)) {
                continue;
            }

            $query = $class::query();
            if ($class === QueueEntry::class) {
                $query->with('attemptSchedule.presentationAttempt.presentationStatus');
            }
            $existing = $query->whereKey($items->pluck('related_id')->unique()->all())->get()->keyBy(fn ($model) => $model->getKey());

            foreach ($items as $item) {
                $related = $existing->get($item->related_id);

                if (! $related) {
                    $staleIds[] = $item->id;
                } elseif ($item->notification_type === 'GROUP_DEFERRED' && $related instanceof QueueEntry
                    && ($related->removed_at === null || $related->attemptSchedule?->presentationAttempt?->presentationStatus?->is_terminal)) {
                    $staleIds[] = $item->id;
                }
            }
        }

        if ($staleIds !== []) {
            Notification::whereIn('id', $staleIds)->update(['read_at' => now()]);
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
