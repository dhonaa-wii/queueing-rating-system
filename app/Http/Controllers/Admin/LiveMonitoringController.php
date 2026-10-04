<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdjustmentReason;
use App\Models\AttemptSchedule;
use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use App\Models\PresentationDateRoom;
use App\Models\PresentationOutcome;
use App\Models\ConnectionStatus;
use App\Models\RoomSession;
use App\Models\RoomSessionAccount;
use App\Models\RoomTerminal;
use App\Models\TerminalConnection;
use App\Services\CapacityAnalysisService;
use App\Services\CategoryDeletionService;
use App\Services\EventActivationService;
use App\Services\PresentationAttemptAdminActionService;
use App\Services\RoomQueuePreviewService;
use App\Services\RoomSessionAccountService;
use App\Support\DefaultCategoryPicker;
use Illuminate\Http\Request;

class LiveMonitoringController extends Controller
{
    /**
     * Event Control opens straight on a category rather than on a grid of
     * category cards (user-directed 2026-09-17) — DefaultCategoryPicker picks
     * the one with a day underway, which on this page is nearly always the one
     * the admin came here for. The picker moved onto the workspace itself, as
     * a dropdown. This route only renders something of its own when no
     * category is open at all.
     */
    public function index()
    {
        $categories = $this->openCategories();
        $category = DefaultCategoryPicker::pick($categories);

        if (! $category) {
            return view('admin.live-monitoring.index');
        }

        return redirect()->route('admin.live-monitoring.show', $category);
    }

    /**
     * Categories Event Control can open: every category that hasn't ended
     * (COMPLETED) or been retired (ARCHIVED) — not only the ones with a
     * presentation date. Corrected 2026-09-17, same day as the COMPLETED
     * exclusion below was first added: that version additionally required
     * whereHas('presentationDates'), which meant a category with no
     * schedule at all (or one whose groups/dates were since removed) could
     * never appear here — and "End Category" only lives on this page, so
     * there was no way left to end it. A category with zero dates still
     * renders fine (the workspace's own "No presentation dates configured"
     * empty state), and its End Category button sits above that state, not
     * inside it. Statuses are refreshed the same way this page has always
     * refreshed them, since both the default-category choice and the
     * workspace read them.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PresentationCategory>
     */
    private function openCategories()
    {
        $categories = PresentationCategory::forAdminCollege()->whereHas('categoryStatus', fn ($query) => $query->whereNotIn('code', ['COMPLETED', 'ARCHIVED']))
            ->with(['academicYear', 'semester', 'presentationDates.eventDateStatus'])
            ->orderByDesc('created_at')
            ->get();

        $categories->each(function (PresentationCategory $category) {
            $category->presentationDates->each->refreshStatus();
        });
        $categories->load('presentationDates.eventDateStatus');

        return $categories;
    }

    public function show(Request $request, PresentationCategory $category, RoomQueuePreviewService $roomQueuePreviewService, EventActivationService $eventActivationService)
    {
        // Rooms on a scheduled break read "Break" here — bring each open
        // session's status in line with the clock before it is loaded below.
        $breakService = app(\App\Services\RoomBreakService::class);

        \App\Models\RoomSession::whereNull('ended_at')
            ->whereHas('presentationDateRoom.presentationDate', fn ($query) => $query->where('category_id', $category->id))
            ->get()
            ->each(fn (\App\Models\RoomSession $session) => $breakService->sync($session));

        $category->load([
            'academicYear',
            'semester',
            'college',
            'presentationMode',
            'categoryStatus',
            'categoryScheduleSetting',
            'presentationDates' => fn ($query) => $query->chronological(),
            'presentationDates.eventDateStatus',
            'presentationDates.presentationEvent.eventStatus',
            'presentationDates.presentationDateRooms.roomUseStatus',
            'presentationDates.presentationDateRooms.roomSessions.roomSessionStatus',
            'presentationDates.presentationDateRooms.roomSessions.currentAttempt.researchGroup',
            'presentationDates.presentationDateRooms.roomSessions.roomTerminals.terminalType',
            'presentationDates.presentationDateRooms.roomSessions.roomTerminals.terminalConnections' => fn ($query) => $query->orderByDesc('connected_at'),
            'presentationDates.presentationDateRooms.roomSessions.roomTerminals.terminalConnections.connectionStatus',
            'presentationDates.presentationDateRooms.roomSessions.roomTerminals.terminalConnections.panelist.profile',
        ]);

        $category->presentationDates->each->refreshStatus();
        $category->load('presentationDates.eventDateStatus');

        // One category-wide capacity read, same CapacityAnalysisService::
        // analyze() Presentation Setup's Schedules tab already shows —
        // user-directed 2026-08-06: the room-scoped "live" version read
        // confusingly different (remaining minutes/slots-today) next to the
        // days×rooms×slots figure admins already know from Presentation
        // Setup, so Live Monitoring now shows the exact same card instead of
        // its own per-room approximation.
        $capacityAnalysis = app(CapacityAnalysisService::class)->analyze($category);

        // User-directed 2026-08-16: the page used to render a full detail
        // card (room tabs, live queue, terminals...) for every configured
        // date at once, which buried the day that actually matters behind a
        // long scroll. Now exactly one "featured" date gets the full detail
        // treatment — the currently-ACTIVE one if there is one, else the
        // next date that hasn't happened yet, else (everything already
        // finished/cancelled) the most recent one — with every other date
        // reduced to a compact status card behind a "View All Days" toggle.
        // `?date=` lets an Admin explicitly pick a different day to feature
        // (e.g. from that toggle's "View Full Details" link) without that
        // choice fighting the auto-selection on the next page load.
        $featuredDate = $this->resolveFeaturedDate($category, $request->integer('date') ?: null);

        // User-directed 2026-09-07: accounts are no longer synced/created
        // here — they only ever exist while the featured date's own event
        // is actually ACTIVE (generated at Start Room, removed at End
        // Event — see RoomSessionAccountService). This just reads whatever
        // currently exists for that one date; a not-yet-started or
        // already-ended date correctly shows no account at all.
        $roomAccountsByName = $featuredDate
            ? RoomSessionAccount::where('presentation_date_id', $featuredDate->id)->get()->keyBy('room_name')
            : collect();

        // Only the featured date needs the expensive per-room queue preview
        // built — the others only need their own already-loaded status/room
        // data for the compact card.
        $roomPanels = collect();

        if ($featuredDate) {
            $registeredInCategory = $category->researchGroups()->count();

            $roomPanels = $featuredDate->presentationDateRooms->map(function (PresentationDateRoom $room) use ($featuredDate, $roomQueuePreviewService, $roomAccountsByName, $registeredInCategory) {
                $room->setRelation('presentationDate', $featuredDate);

                return (object) [
                    'room' => $room,
                    'session' => $room->roomSessions->sortByDesc('id')->first(),
                    'queue' => $roomQueuePreviewService->forRoom($room),
                    'account' => $roomAccountsByName->get($room->room_name),
                    // Same shape/logic as RoomSessionController::buildDayStats()
                    // — reused here so the admin "Room Data" card (mirroring
                    // the room-session tablet's own info card) shows the
                    // same stats a panelist sees at the terminal.
                    'dayStats' => [
                        'registeredInCategory' => $registeredInCategory,
                        'scheduledToday' => AttemptSchedule::where('presentation_date_room_id', $room->id)
                            ->whereHas('queueEntry', fn ($query) => $query->whereNull('removed_at'))
                            ->count(),
                        'completedToday' => AttemptSchedule::where('presentation_date_room_id', $room->id)
                            ->whereHas('presentationAttempt.presentationStatus', fn ($query) => $query->where('code', 'COMPLETED'))
                            ->count(),
                    ],
                ];
            })->values();
        }

        // Latest schedule first (user-directed 2026-09-13).
        $otherDates = ($featuredDate
            ? $category->presentationDates->reject(fn (PresentationDate $date) => $date->id === $featuredDate->id)
            : $category->presentationDates)
            ->sortByDesc('presentation_date')
            ->values();

        $categoryStatusCode = $category->categoryStatus?->code;
        $completionBlockReason = $eventActivationService->completionBlockReason($category);

        return view('admin.live-monitoring.show', [
            'category' => $category,
            // The header's category picker, which replaced the back link —
            // Event Control opens straight on a category, so there is no
            // picker page to go back to.
            'pickerCategories' => DefaultCategoryPicker::withCurrent($this->openCategories(), $category),
            'capacityAnalysis' => $capacityAnalysis,
            'featuredDate' => $featuredDate,
            'roomPanels' => $roomPanels,
            'otherDates' => $otherDates,
            'adjustmentReasons' => AdjustmentReason::forOtherAdjustments()->get(),
            'presentationOutcomes' => PresentationOutcome::where('is_active', true)->orderBy('name')->get(),
            'canCompleteCategory' => $completionBlockReason === null,
            'completionBlockReason' => $completionBlockReason,
            'canDeleteCategory' => in_array($categoryStatusCode, ['COMPLETED', 'ARCHIVED'], true),
        ]);
    }

    private function resolveFeaturedDate(PresentationCategory $category, ?int $requestedDateId): ?PresentationDate
    {
        if ($requestedDateId) {
            $requested = $category->presentationDates->firstWhere('id', $requestedDateId);

            if ($requested) {
                return $requested;
            }
        }

        return $category->presentationDates->first(fn (PresentationDate $date) => $date->eventDateStatus?->code === 'ACTIVE')
            ?? $category->presentationDates->first(fn (PresentationDate $date) => in_array($date->eventDateStatus?->code, ['PLANNED', 'STANDBY'], true))
            ?? $category->presentationDates->last();
    }

    /**
     * Per-room Start/End (user-directed 2026-09-12). Since 2026-09-13 the
     * only way a day starts or ends — the day-level Start/End Event buttons
     * were removed, and the first room started / last room closed carries
     * that responsibility (see EventActivationService::endRoom()).
     */
    public function startRoom(Request $request, PresentationCategory $category, PresentationDateRoom $room, EventActivationService $service)
    {
        $this->ensureDateBelongsToCategory($room->presentationDate, $category);

        $result = $service->startRoom($room, $request->user()->id);

        return $result['ok']
            ? back()->with('status', "{$room->room_name} started.")
            : back()->with('error', $result['error']);
    }

    public function endRoom(Request $request, PresentationCategory $category, RoomSession $roomSession, EventActivationService $service)
    {
        $this->ensureRoomSessionBelongsToCategory($roomSession, $category);

        $roomName = $roomSession->presentationDateRoom->room_name;
        $result = $service->endRoom($roomSession, $request->user()->id);

        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        if (! ($result['dayEnded'] ?? false)) {
            $message = "{$roomName} closed.";

            if (! empty($result['roomsRemaining'])) {
                $message .= ' The day stays live until ' . implode(', ', $result['roomsRemaining']) . ' ' . (count($result['roomsRemaining']) === 1 ? 'is' : 'are') . ' started and closed.';
            }

            if (isset($result['dayEndError'])) {
                $message .= " The day itself could not be closed yet: {$result['dayEndError']}";
            }

            return back()->with('status', $message);
        }

        return back()->with('status', "{$roomName} closed — that was the day's last open room. " . $this->describeEndOfDaySummary($result));
    }

    private function describeEndOfDaySummary(array $result): string
    {
        $notes = [];

        if (($result['unfinishedMoved'] ?? 0) > 0) {
            $notes[] = "{$result['unfinishedMoved']} unfinished group(s) moved to the next day's queue";
        }

        if (($result['unfinishedUnresolved'] ?? 0) > 0) {
            $notes[] = "{$result['unfinishedUnresolved']} unfinished group(s) left unresolved — no later date to move them to";
        }

        if (($result['deferredMoved'] ?? 0) > 0) {
            $notes[] = "{$result['deferredMoved']} deferred group(s) re-queued at the end of the category";
        }

        if (($result['deferredUnresolved'] ?? 0) > 0) {
            $notes[] = "{$result['deferredUnresolved']} deferred group(s) left unresolved — no open room to place them in";
        }

        return $notes === [] ? 'Event ended.' : 'Event ended. ' . implode('; ', $notes) . '.';
    }

    public function pauseRoomSession(Request $request, PresentationCategory $category, RoomSession $roomSession, EventActivationService $service)
    {
        $this->ensureRoomSessionBelongsToCategory($roomSession, $category);

        $validated = $request->validate([
            'reason_id' => ['nullable', 'integer', 'exists:adjustment_reasons,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $service->pauseRoomSession($roomSession, $request->user()->id, $validated['reason_id'] ?? null, $validated['remarks'] ?? null);

        return $result['ok']
            ? back()->with('status', 'Room session paused.')
            : back()->with('error', $result['error']);
    }

    public function resumeRoomSession(Request $request, PresentationCategory $category, RoomSession $roomSession, EventActivationService $service)
    {
        $this->ensureRoomSessionBelongsToCategory($roomSession, $category);

        $result = $service->resumeRoomSession($roomSession, $request->user()->id);

        return $result['ok']
            ? back()->with('status', 'Room session resumed.')
            : back()->with('error', $result['error']);
    }

    /**
     * The password stays visible on the Room Session Account card at all
     * times (user-directed 2026-08-15 — an Admin needs to be able to
     * notice unexpected terminal activity against the credential currently
     * in use, not just at the moment it's issued). This still always
     * issues a brand-new password — it's a reset, not a re-reveal of the
     * existing one — so the old one stops working immediately.
     */
    public function resetRoomAccountCredentials(Request $request, PresentationCategory $category, RoomSessionAccount $account, RoomSessionAccountService $service)
    {
        abort_unless($account->presentation_category_id === $category->id, 404);

        $service->resetCredentials($account, $request->user()->id);

        return back()->with('status', "Room \"{$account->room_name}\" account password reset.");
    }

    /**
     * Manual escape hatch for a seat a panelist forgot to log out of —
     * nothing currently expires a terminal_connections row on its own.
     */
    public function forceDisconnectTerminal(Request $request, PresentationCategory $category, RoomTerminal $terminal)
    {
        $terminal->loadMissing('roomSession.presentationDateRoom.presentationDate');

        abort_unless($terminal->roomSession->presentationDateRoom->presentationDate->category_id === $category->id, 404);

        $disconnectedStatus = ConnectionStatus::where('code', 'DISCONNECTED')->firstOrFail();

        TerminalConnection::where('room_terminal_id', $terminal->id)
            ->whereNull('disconnected_at')
            ->update([
                'disconnected_at' => now(),
                'connection_status_id' => $disconnectedStatus->id,
            ]);

        return back()->with('status', 'Terminal seat cleared.');
    }

    /**
     * Frees up a terminal number for a different physical tablet
     * (2026-08-22, user-directed) — RoomSessionController::claimTerminal()
     * now refuses to let a second device claim a terminal number that's
     * already device-claimed (room_terminals.device_identifier set), so
     * this is the only remaining way to recover a terminal whose original
     * tablet is gone/lost its session (previously the picker itself quietly
     * allowed a re-claim to overwrite the old device_identifier, which is
     * exactly the loophole this closes). Also force-clears any live
     * TerminalConnection on the seat — unlike the tablet's own self-service
     * release() (only reachable once the connected panelist has already
     * logged out), an Admin invoking this is explicitly handling a stuck/
     * abandoned tablet, so any stale connection should go with it.
     */
    public function releaseTerminalDevice(Request $request, PresentationCategory $category, RoomTerminal $terminal)
    {
        $terminal->loadMissing('roomSession.presentationDateRoom.presentationDate');

        abort_unless($terminal->roomSession->presentationDateRoom->presentationDate->category_id === $category->id, 404);

        $disconnectedStatus = ConnectionStatus::where('code', 'DISCONNECTED')->firstOrFail();

        TerminalConnection::where('room_terminal_id', $terminal->id)
            ->whereNull('disconnected_at')
            ->update([
                'disconnected_at' => now(),
                'connection_status_id' => $disconnectedStatus->id,
            ]);

        $terminal->update(['device_identifier' => null]);

        return back()->with('status', "Terminal {$terminal->terminal_number} released — it can be set up on a new device now.");
    }

    public function completeCategory(Request $request, PresentationCategory $category, EventActivationService $service)
    {
        $result = $service->completeCategory($category, $request->user()->id);

        return $result['ok']
            ? redirect()->route('admin.live-monitoring.show', $category)->with('status', 'Category marked complete.')
            : back()->with('error', $result['error']);
    }

    public function deleteCategory(PresentationCategory $category, CategoryDeletionService $service)
    {
        $result = $service->deleteCompletedCategory($category);

        return $result['ok']
            ? redirect()->route('admin.live-monitoring.index')->with('status', 'Category deleted.')
            : back()->with('error', $result['error']);
    }

    /**
     * Admin-side equivalent of PresentationControlService::complete() —
     * marks a presentation done without needing a panelist connected to
     * Terminal 1. See PresentationAttemptAdminActionService's class doc.
     */
    public function completeAttempt(Request $request, PresentationCategory $category, AttemptSchedule $schedule, PresentationAttemptAdminActionService $service)
    {
        $this->ensureScheduleBelongsToCategory($schedule, $category);

        $validated = $request->validate([
            'final_outcome_id' => ['nullable', 'integer', 'exists:presentation_outcomes,id'],
        ]);

        $result = $service->complete($schedule, $request->user()->id, $validated['final_outcome_id'] ?? null);

        return $result['ok']
            ? back()->with('status', 'Presentation marked complete.')
            : back()->with('error', $result['error']);
    }

    public function deleteAttempt(Request $request, PresentationCategory $category, AttemptSchedule $schedule, PresentationAttemptAdminActionService $service)
    {
        $this->ensureScheduleBelongsToCategory($schedule, $category);

        $result = $service->delete($schedule, $request->user()->id);

        return $result['ok']
            ? back()->with('status', 'Presentation deleted.')
            : back()->with('error', $result['error']);
    }

    private function ensureScheduleBelongsToCategory(AttemptSchedule $schedule, PresentationCategory $category): void
    {
        $schedule->loadMissing('presentationDateRoom.presentationDate');

        abort_unless($schedule->presentationDateRoom->presentationDate->category_id === $category->id, 404);
    }

    private function ensureDateBelongsToCategory(PresentationDate $date, PresentationCategory $category): void
    {
        abort_unless($date->category_id === $category->id, 404);
    }

    private function ensureRoomSessionBelongsToCategory(RoomSession $roomSession, PresentationCategory $category): void
    {
        $roomSession->loadMissing('presentationDateRoom.presentationDate');

        abort_unless($roomSession->presentationDateRoom->presentationDate->category_id === $category->id, 404);
    }
}
