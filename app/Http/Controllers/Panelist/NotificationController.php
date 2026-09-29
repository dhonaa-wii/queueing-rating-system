<?php

namespace App\Http\Controllers\Panelist;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Support\FocusLink;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Polled by the topbar bell on every panelist page. */
    public function index(Request $request, NotificationService $notifications)
    {
        $user = $request->user();

        $items = $notifications->recentFor($user, 30)->map(function (Notification $notification) {
            $attemptIds = in_array($notification->notification_type, ['PANELIST_ASSIGNED', 'PANELIST_SCHEDULE_CHANGED'], true)
                ? ($notification->data['attempt_ids'] ?? [])
                : [];

            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'date' => $notification->created_at->format('M j, Y · g:i A'),
                'read' => $notification->read_at !== null,
                'link' => $attemptIds === []
                    ? null
                    : FocusLink::to(route('panelist.assignments.index', [], false), array_map(fn ($id) => "assign-{$id}", $attemptIds)),
            ];
        })->values();

        return response()->json([
            'unreadCount' => $notifications->unreadCountFor($user),
            'items' => $items,
        ]);
    }

    public function markRead(Request $request, Notification $notification, NotificationService $notifications)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notifications->markRead($notification);

        return response()->json(['ok' => true]);
    }
}
