<?php

namespace App\Http\Middleware;

use App\Models\PresentationCategory;
use App\Models\RoomSession;
use App\Services\EventActivationService;
use App\Services\NotificationService;
use App\Services\PanelistConflictService;
use App\Services\QueueAdjustmentService;
use App\Services\RoomBreakService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * The six automatic sweeps this app has no scheduler for (2026-09-22).
 *
 * These used to live at the top of Admin\PanelSubstitutionController::index(),
 * the JSON endpoint the topbar notification bell polled every 20s from every
 * admin page. That bell was removed (user-directed), so the sweeps needed a
 * new home or they would simply have stopped running — a day would never
 * auto-end, an overdue date would never be flagged, an overflowing room would
 * never even out. Same reasoning as before, just a different global hook: an
 * admin page load rather than that page's background poll.
 *
 * Deliberately narrow about when it fires:
 *  - only for a signed-in ADMIN, so public/student GET traffic never triggers
 *    writes that need a real user id (the same call already made for
 *    QueueGenerationService in §2.5)
 *  - only on a plain GET page load, so an AJAX autosave or a form POST isn't
 *    made slower by work that has nothing to do with it
 *  - at most once every SWEEP_INTERVAL seconds across the whole app (cache
 *    lock), since the sweeps scan every non-archived category and the bell's
 *    old cadence was one run per 20s, not one per request
 */
class RunAdminMaintenanceSweeps
{
    /** Seconds between sweeps, app-wide. */
    private const SWEEP_INTERVAL = 60;

    private const LOCK_KEY = 'admin-maintenance-sweeps:last-run';

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldRun($request)) {
            $this->sweep((int) $request->user()->id);
        }

        return $next($request);
    }

    private function shouldRun(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->ajax() || $request->wantsJson()) {
            return false;
        }

        if (! $request->user()?->hasRole('ADMIN')) {
            return false;
        }

        // add() is atomic — the first request past the interval claims the
        // run, everything else in that window returns false and falls through.
        return Cache::add(self::LOCK_KEY, true, self::SWEEP_INTERVAL);
    }

    private function sweep(int $userId): void
    {
        $eventActivation = app(EventActivationService::class);

        $eventActivation->flagOverdueDates();
        $eventActivation->autoEndPastDay();
        $eventActivation->autoCancelNeverStarted();
        $eventActivation->flagUnscheduledGroups();

        app(PanelistConflictService::class)->syncNotifications(app(NotificationService::class));

        // A room whose groups run past its close time is evened out onto the
        // next open room/day on its own (user-directed 2026-09-13). No-ops
        // cheaply when nothing overflows.
        $queueAdjustments = app(QueueAdjustmentService::class);

        PresentationCategory::whereNull('archived_at')->get()
            ->each(function (PresentationCategory $category) use ($queueAdjustments, $userId) {
                // Groups carried past a follow-up session return to it, even if
                // it was set up before that rule existed.
                $queueAdjustments->pullBackToEarlierSession($category, $userId);
                // Groups waiting on a later day move to an earlier one that
                // has room, whenever it was configured.
                $queueAdjustments->moveToEarliestOpenDates($category, $userId);
                $queueAdjustments->spillOverflow($category, $userId);
            });

        // A room's BREAK status follows the clock; the tablet keeps it current
        // while one is open, this catches a room nobody has open.
        $breaks = app(RoomBreakService::class);

        RoomSession::whereNull('ended_at')->get()->each(fn (RoomSession $session) => $breaks->sync($session));
    }
}
