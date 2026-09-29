<?php

namespace App\Services;

use App\Models\AttemptSchedule;
use App\Models\PresentationCategory;
use App\Models\ResearchGroup;
use App\Models\TerminalConnection;

class CategoryScheduleViewService
{
    public function __construct(
        private readonly PaymentVerificationService $paymentVerificationService,
    ) {
    }

    /**
     * Builds the room-tab/queue/search view-model for one category's schedule
     * page. Extracted from Student\CategoryController::schedule() so the
     * Panelist-facing schedule view (same search + room-tab + room-status
     * flow, just not scoped to the student's public/no-auth context) reuses
     * the identical query shape instead of duplicating it.
     */
    public function build(PresentationCategory $category, string $search, ?string $groupReference): array
    {
        $category->loadMissing([
            'academicYear',
            'semester',
            'college',
            'categoryStatus',
            'presentationMode',
            'categoryScheduleSetting',
            'categoryPaymentSetting',
            'categoryPaymentTypes',
            'presentationDates' => fn ($query) => $query->chronological(),
            'presentationDates.eventDateStatus',
            'presentationDates.presentationDateRooms' => fn ($query) => $query->orderBy('room_name'),
            'presentationDates.presentationDateRooms.roomUseStatus',
            'presentationDates.presentationDateRooms.scheduleBreaks',
            'presentationDates.presentationDateRooms.attemptSchedules.queueEntry',
            'presentationDates.presentationDateRooms.attemptSchedules.queueEntry.queueAdjustments' => fn ($query) => $query
                ->whereHas('adjustmentType', fn ($sq) => $sq->where('code', 'DEFER'))
                ->orderByDesc('adjusted_at'),
            'presentationDates.presentationDateRooms.attemptSchedules.queueEntry.queueAdjustments.reason',
            'presentationDates.presentationDateRooms.attemptSchedules.presentationAttempt.presentationStatus',
            'presentationDates.presentationDateRooms.attemptSchedules.presentationAttempt.researchGroup.students',
            'presentationDates.presentationDateRooms.attemptSchedules.presentationAttempt.researchGroup.proposedTitles',
            'presentationDates.presentationDateRooms.attemptSchedules.presentationAttempt.presentationRun.timerStatus',
            'presentationDates.presentationDateRooms.attemptSchedules.presentationAttempt.paymentVerifications.paymentStatus',
            'presentationDates.presentationDateRooms.attemptSchedules.presentationAttempt.attemptPanelAssignments' => fn ($query) => $query
                ->whereHas('assignmentStatus', fn ($sq) => $sq->whereNotIn('code', ['REPLACED', 'WITHDRAWN'])),
            'presentationDates.presentationDateRooms.attemptSchedules.presentationAttempt.attemptPanelAssignments.assignmentKind',
            'presentationDates.presentationDateRooms.attemptSchedules.presentationAttempt.attemptPanelAssignments.panelist.profile',
            'presentationDates.presentationDateRooms.roomSessions' => fn ($query) => $query->whereNull('ended_at')->latest('started_at'),
            'presentationDates.presentationDateRooms.roomSessions.roomSessionStatus',
            'presentationDates.presentationDateRooms.roomSessions.presentationEvent',
            'presentationDates.presentationDateRooms.roomSessions.currentAttempt.presentationStatus',
            'presentationDates.presentationDateRooms.roomSessions.currentAttempt.researchGroup.students',
            'presentationDates.presentationDateRooms.roomSessions.currentAttempt.presentationRun.timerStatus',
            'presentationDates.presentationDateRooms.roomSessions.currentAttempt.paymentVerifications.paymentStatus',
            'presentationDates.presentationDateRooms.roomSessions.currentAttempt.attemptPanelAssignments' => fn ($query) => $query
                ->whereHas('assignmentStatus', fn ($sq) => $sq->whereNotIn('code', ['REPLACED', 'WITHDRAWN'])),
            'presentationDates.presentationDateRooms.roomSessions.currentAttempt.attemptPanelAssignments.assignmentKind',
            'presentationDates.presentationDateRooms.roomSessions.currentAttempt.attemptPanelAssignments.panelist.profile',
            'categoryAnnouncements' => fn ($query) => $query->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->orderBy('starts_at'),
        ]);

        // One tab per room NAME, merged across every presentation date it's
        // configured on — a multi-day room is one tab, not one tab per day
        // (user-directed 2026-08-05: room name is the only tab separation).
        $flatDateRooms = $category->presentationDates->flatMap(
            fn ($date) => $date->presentationDateRooms->each(fn ($room) => $room->setRelation('presentationDate', $date))
        );

        // Only rooms that are actually still in play get a tab (user-directed
        // 2026-09-13) — a room is in play when it is assigned, not removed, to
        // at least one ongoing or upcoming day (PresentationDate::
        // isOpenForScheduling(), the same test Event Control and the transfer
        // target list already use). A room that only ever existed on days
        // that are finished or cancelled is not part of this schedule any
        // more and was cluttering the tab strip.
        //
        // Filtered by room NAME, not per date-room: a surviving room keeps
        // every one of its date-rooms, so its Completed/Deferred lists still
        // show what already happened in it on earlier days.
        $inPlayRoomNames = $flatDateRooms
            ->filter(fn ($room) => $room->roomUseStatus?->code !== 'REMOVED'
                && $room->presentationDate?->isOpenForScheduling())
            ->pluck('room_name')
            ->unique()
            ->flip();

        // Groups that still have to present but whose day can no longer run
        // (user-directed 2026-09-16 — AttemptSchedule::isAwaitingReschedule()).
        // Gathered from every date-room the category has, including the ones
        // the tab filter above just dropped, because that is exactly where a
        // stranded group sits: end-of-day carry-over had no open day to move
        // it to, so it stayed parked on the finished one. They are pulled out
        // of the room tabs entirely and listed on their own instead — their
        // room is no more current than their date is, so filing them under it
        // would contradict what their row now says.
        $awaitingRows = $flatDateRooms->flatMap(function ($room) {
            return $room->attemptSchedules->each(fn ($schedule) => $schedule->setRelation('presentationDateRoom', $room));
        })->filter(fn ($schedule) => $schedule->isAwaitingReschedule())
            ->each(function ($schedule) {
                $schedule->expected_start_at = null;
                $schedule->expected_end_at = null;
            })
            // Kept in the order they were queued in — the plan is dead, but
            // the order the groups were in is still the most sensible way to
            // read the list, and the one they will most likely be requeued in.
            ->sortBy(fn ($schedule) => $schedule->presentationDateRoom->presentationDate->presentation_date->format('Y-m-d')
                . '-' . $schedule->presentationDateRoom->room_name
                . '-' . str_pad((string) ($schedule->queueEntry?->queue_number ?? 0), 6, '0', STR_PAD_LEFT))
            ->values();

        $awaitingScheduleIds = $awaitingRows->pluck('id')->flip();

        // Deferred groups sitting in a room that no longer has a tab would
        // otherwise be listed nowhere at all once every day is finished, so
        // they are carried alongside the awaiting list. Their own rows are
        // unchanged — a deferred group is described by when it was really
        // deferred, not by any schedule — this only decides where it is
        // shown. A room that still has a tab keeps its own deferred list.
        $orphanedDeferredRows = $flatDateRooms
            ->reject(fn ($room) => $inPlayRoomNames->has($room->room_name))
            ->flatMap(function ($room) {
                return $room->attemptSchedules
                    ->filter(fn ($schedule) => $schedule->queueEntry && $schedule->queueEntry->removed_at)
                    ->each(fn ($schedule) => $schedule->setRelation('presentationDateRoom', $room));
            })
            ->sortByDesc(fn ($schedule) => $schedule->queueEntry->removed_at)
            ->values();

        $flatDateRooms = $flatDateRooms->filter(fn ($room) => $inPlayRoomNames->has($room->room_name));

        $registeredInCategory = $category->researchGroups()->count();
        $paymentRequired = (bool) ($category->categoryPaymentSetting->payment_required ?? false);

        $rooms = $flatDateRooms->groupBy('room_name')->map(function ($dateRooms, $roomName) use ($category, $registeredInCategory, $paymentRequired, $awaitingScheduleIds) {
            $dateRooms = $dateRooms->sortBy(fn ($room) => $room->presentationDate->presentation_date)->values();

            $queueRows = $dateRooms->flatMap(function ($room) {
                return $room->attemptSchedules
                    ->filter(fn ($schedule) => $schedule->queueEntry && ! $schedule->queueEntry->removed_at)
                    ->each(fn ($schedule) => $schedule->setRelation('presentationDateRoom', $room));
            })
                // A row awaiting a new date is listed on its own, not under
                // the room it is parked in, so it is taken out here before
                // anything else in this tab is derived from the queue —
                // the Scheduled list, the room's day stats and the live
                // current/next cascade all skip it as a result.
                ->reject(fn ($schedule) => $awaitingScheduleIds->has($schedule->id))
                ->sortBy(fn ($schedule) => $schedule->presentationDateRoom->presentationDate->presentation_date->format('Y-m-d')
                    . '-' . str_pad((string) $schedule->queueEntry->queue_number, 6, '0', STR_PAD_LEFT))
                ->values();

            // Deferred entries are excluded from $queueRows above (their
            // queue_entry.removed_at is set the moment they're deferred —
            // see QueueAdjustmentService::defer()), so they're gathered
            // separately here purely for the "Deferred" list section; they
            // never feed buildLiveStatus()'s current/called/upcoming
            // cascade, which only ever operates on $queueRows.
            $deferredRows = $dateRooms->flatMap(function ($room) {
                return $room->attemptSchedules
                    ->filter(fn ($schedule) => $schedule->queueEntry && $schedule->queueEntry->removed_at)
                    ->each(fn ($schedule) => $schedule->setRelation('presentationDateRoom', $room));
            })->sortByDesc(fn ($schedule) => $schedule->queueEntry->removed_at)->values();

            $completedRows = $queueRows->filter(fn ($schedule) => $schedule->presentationAttempt->presentationStatus->code === 'COMPLETED')->values();
            $scheduledRows = $queueRows->reject(fn ($schedule) => $schedule->presentationAttempt->presentationStatus->code === 'COMPLETED')->values();

            $currentSession = $dateRooms->flatMap->roomSessions->first();

            return (object) [
                'name' => $roomName,
                'dateRooms' => $dateRooms,
                'queueRows' => $queueRows,
                'scheduledRows' => $scheduledRows,
                'completedRows' => $completedRows,
                'deferredRows' => $deferredRows,
                'currentSession' => $currentSession,
                'live' => $this->buildLiveStatus($category, $dateRooms, $queueRows, $currentSession, $registeredInCategory, $paymentRequired),
            ];
        })->sortBy('name')->values();

        $matches = collect();
        $selectedGroup = null;

        if ($search !== '') {
            $matches = ResearchGroup::query()
                ->where('category_id', $category->id)
                ->where(function ($query) use ($search) {
                    $query->whereHas('students', fn ($sq) => $sq->nameMatches($search))
                        ->orWhere('current_project_title', 'like', "%{$search}%")
                        ->orWhere('group_reference', 'like', "%{$search}%")
                        ->orWhereHas('proposedTitles', fn ($pq) => $pq->where('title_text', 'like', "%{$search}%"));
                })
                ->with(['students', 'proposedTitles' => fn ($query) => $query->orderBy('sort_order')])
                ->orderBy('group_reference')
                ->limit(20)
                ->get();

            $selectedGroup = $groupReference
                ? $matches->firstWhere('group_reference', $groupReference)
                : ($matches->count() === 1 ? $matches->first() : null);
        }

        $selectedAttempt = null;
        $activeRoomName = null;

        if ($selectedGroup) {
            $selectedGroup->loadMissing([
                'presentationAttempts' => fn ($query) => $query->orderByDesc('attempt_number'),
                'presentationAttempts.presentationStatus',
                'presentationAttempts.attemptSchedule.presentationDateRoom.presentationDate.eventDateStatus',
                'presentationAttempts.attemptSchedule.queueEntry',
                'presentationAttempts.attemptSchedule.queueEntry.queueAdjustments' => fn ($query) => $query
                    ->whereHas('adjustmentType', fn ($sq) => $sq->where('code', 'DEFER'))
                    ->orderByDesc('adjusted_at'),
                'presentationAttempts.attemptSchedule.queueEntry.queueAdjustments.reason',
                'presentationAttempts.attemptPanelAssignments.panelist.profile',
                'presentationAttempts.attemptPanelAssignments.assignmentKind',
                'presentationAttempts.attemptPanelAssignments.assignmentStatus',
            ]);

            // Most recent attempt is the relevant one — a group only ever has more
            // than one when a prior attempt was deferred/rescheduled/absent.
            $selectedAttempt = $selectedGroup->presentationAttempts->first();
            $activeRoomName = optional($selectedAttempt?->attemptSchedule?->presentationDateRoom)->room_name;

            // $selectedAttempt->attemptSchedule was loaded via its own
            // separate query above, so it's a different object instance
            // than the matching row inside $rooms's already-expected-time-
            // adjusted queueRows — copy the computed value across rather
            // than recomputing it a second time, so Group Status always
            // shows the exact same time the room's own queue list shows
            // (user-directed 2026-08-22: "expected date and time ... like
            // how the system do it").
            if ($selectedAttempt?->attemptSchedule) {
                // The attempt this schedule belongs to is already in hand, so
                // hand it over rather than letting the Group Status card
                // lazy-load it back through the relation when it asks whether
                // this group is awaiting a new date.
                $selectedAttempt->attemptSchedule->setRelation('presentationAttempt', $selectedAttempt);
            }

            if ($selectedAttempt?->attemptSchedule && $activeRoomName) {
                $matchingSchedule = $rooms->firstWhere('name', $activeRoomName)
                    ?->queueRows
                    ->firstWhere('id', $selectedAttempt->attemptSchedule->id);

                // Null when this group is awaiting a new date: such a row is
                // deliberately not in any room's queueRows any more, and it
                // has no time to show in the first place.
                $selectedAttempt->attemptSchedule->expected_start_at = $matchingSchedule?->expected_start_at;
                $selectedAttempt->attemptSchedule->expected_end_at = $matchingSchedule?->expected_end_at;
            }
        }

        // A searched group parked in a room that no longer has a tab (every
        // day it ran on is finished, so it is awaiting a new date) must not
        // leave the strip with no tab selected — fall back to the first real
        // room, or to nothing at all when the category has none left.
        if (! $activeRoomName || ! $rooms->contains('name', $activeRoomName)) {
            $activeRoomName = $rooms->first()?->name;
        }

        return [
            'category' => $category,
            'rooms' => $rooms,
            'awaitingRows' => $awaitingRows,
            'orphanedDeferredRows' => $orphanedDeferredRows,
            'search' => $search,
            'matches' => $matches,
            'selectedGroup' => $selectedGroup,
            'selectedAttempt' => $selectedAttempt,
            'activeRoomName' => $activeRoomName,
        ];
    }

    /**
     * Read-only counterpart of the room-session tablet's "Presentation
     * Control" card data (RoomSessionController::buildHomeData()) — same
     * shape (current/called group, panel assigned to it, day stats, last
     * resolved outcome), computed here instead across every date-room a
     * room name spans, since a Student/Panelist schedule tab is never
     * scoped to a single day the way a physical terminal is. Only the
     * "Presentation Control" buttons/evaluation form themselves are left
     * out — this is a public/no-write context.
     */
    private function buildLiveStatus(
        PresentationCategory $category,
        $dateRooms,
        $queueRows,
        $currentSession,
        int $registeredInCategory,
        bool $paymentRequired
    ): array {
        $current = $queueRows->first(fn ($schedule) => in_array($schedule->presentationAttempt->presentationStatus->code, ['ONGOING', 'PAUSED'], true));
        $called = $queueRows->first(fn ($schedule) => $schedule->presentationAttempt->presentationStatus->code === 'CALLED');
        $upcoming = $queueRows->filter(fn ($schedule) => in_array($schedule->presentationAttempt->presentationStatus->code, ['SCHEDULED', 'QUEUED', 'READY_NEXT'], true))->values();
        $completedCount = $queueRows->filter(fn ($schedule) => $schedule->presentationAttempt->presentationStatus->code === 'COMPLETED')->count();

        // Sets ->expected_start_at/->expected_end_at directly on each
        // schedule object in $queueRows (same runtime-attribute convention
        // as RoomQueuePreviewService::applyExpectedTimes(), which this
        // mirrors) — since $current/$called/$upcoming are the exact same
        // object instances (filtered from $queueRows, not re-queried),
        // setting the attribute here is visible through all of them.
        //
        // Always called now: the running-vs-upcoming decision moved inside,
        // per row (user-directed 2026-09-13). It used to be gated here on
        // "something is current or called", which left a real gap — a day
        // that is genuinely underway but momentarily between groups (the
        // last one completed, the next not yet called) fell back to the
        // plan it had already drifted from. A row's own day is what decides
        // it now, which also handles this room tab's multi-day merge: the
        // running day's rows cascade, a later day's rows keep their plan.
        $this->applyExpectedTimes($category, $queueRows, $current, $called);

        $attempt = $currentSession?->currentAttempt;
        $run = $attempt?->presentationRun;

        $referenceDateRoom = $current?->presentationDateRoom ?? $called?->presentationDateRoom ?? $upcoming->first()?->presentationDateRoom ?? $dateRooms->first();
        $durationMinutesPerGroup = $category->categoryScheduleSetting?->duration_minutes;
        $plannedEndTime = $referenceDateRoom?->room_end_time ?? $referenceDateRoom?->presentationDate?->event_end_time;

        // The day the "Start" card describes (user-directed 2026-09-15): the
        // day this room is running now; otherwise the next ongoing/upcoming
        // day it has not already closed on — so once a day is done, the next
        // one's planned start takes over; otherwise its last day.
        $startDateRoom = ($currentSession ? $dateRooms->firstWhere('id', $currentSession->presentation_date_room_id) : null)
            ?? $dateRooms->first(fn ($room) => $room->roomUseStatus?->code !== 'REMOVED'
                && $room->presentationDate->isOpenForScheduling()
                && ! $room->hasClosedForDay())
            ?? $dateRooms->last();

        return [
            'session' => $currentSession,
            'attempt' => $attempt,
            'run' => $run,
            'paymentSummary' => $attempt ? $this->paymentVerificationService->summaryFor($category, $attempt) : null,
            'paymentRequired' => $paymentRequired,
            'lastOutcome' => $attempt ? null : $this->buildLastOutcome($dateRooms->pluck('id')),
            'current' => $current,
            'called' => $called,
            'upcoming' => $upcoming,
            'nextThree' => $upcoming->take(3),
            // Who's actually logged into a terminal in this room right now
            // (user-directed 2026-08-22) — distinct from "who's assigned to
            // present," which is already shown elsewhere (Group Status /
            // the queue table). Empty whenever there's no active room
            // session at all, same as a physical terminal with nobody
            // connected.
            'activePanelists' => $currentSession ? $this->buildActivePanelists($currentSession) : collect(),
            'durationMinutesPerGroup' => $durationMinutesPerGroup,
            'plannedEndTime' => $plannedEndTime,
            'startTime' => $startDateRoom?->startTime(),
            'dayStats' => [
                'registeredInCategory' => $registeredInCategory,
                'scheduled' => $queueRows->count(),
                'completed' => $completedCount,
            ],
        ];
    }

    /**
     * Cascades a live-adjusted "expected" start/end time forward from
     * whatever's current/called, across every remaining non-terminal
     * schedule in the room — adapted from
     * RoomQueuePreviewService::applyExpectedTimes(), which does the same
     * thing scoped to one PresentationDateRoom; this version works over the
     * merged multi-day $queueRows a Student/Panelist schedule tab shows, so
     * the duration comes from the category directly rather than a single
     * date-room's own category relation.
     *
     * Only rows on a day that is actually running take the cascade — a row
     * on a day that has not started keeps its plan (user-directed
     * 2026-09-13). The cursor starts at "now", so cascading a future day
     * would drag it onto today's clock.
     */
    private function applyExpectedTimes(PresentationCategory $category, $queueRows, $current, $called): void
    {
        $scheduleSetting = $category->categoryScheduleSetting;
        $durationMinutes = $scheduleSetting?->duration_minutes ?? 30;

        $cursor = now();

        if ($current && ! $this->dayIsRunning($current)) {
            $current = null;
        }

        if ($called && ! $this->dayIsRunning($called)) {
            $called = null;
        }

        if ($current) {
            $run = $current->presentationAttempt->presentationRun;

            if ($run && $run->started_at) {
                $current->expected_start_at = $run->started_at;
                $current->expected_end_at = $run->started_at->copy()
                    ->addSeconds($run->configured_duration_seconds ?? ($durationMinutes * 60))
                    ->addSeconds($run->total_paused_seconds ?? 0)
                    ->addSeconds($run->extended_seconds ?? 0);
            } elseif ($current->planned_start_at && $current->planned_end_at) {
                $current->expected_start_at = $current->planned_start_at;
                $current->expected_end_at = $current->planned_end_at;
            } else {
                $current->expected_start_at = $cursor;
                $current->expected_end_at = $cursor->copy()->addMinutes($durationMinutes);
            }

            // Still on stage past its slot: the room is not free before now
            // (see RoomQueuePreviewService::applyExpectedTimes()).
            if ($current->expected_end_at->lt($cursor)) {
                $current->expected_end_at = $cursor->copy();
            }

            $cursor = $current->expected_end_at->copy();
        }

        if ($called) {
            $start = $called->presentationDateRoom?->breakAt($cursor)?->planned_end_at?->copy() ?? $cursor->copy();

            $called->expected_start_at = $start;
            $called->expected_end_at = $start->copy()->addMinutes($durationMinutes);

            $cursor = $called->expected_end_at->copy();
        }

        foreach ($queueRows as $schedule) {
            if ($schedule === $current || $schedule === $called) {
                continue;
            }

            // A row that is already decided, or one on a day that has not
            // begun, has no forecast to make — it shows its plan, unless it
            // is still to present on a day that can no longer run at all, in
            // which case it has nothing to show (user-directed 2026-09-16 —
            // see AttemptSchedule::isAwaitingReschedule()).
            if ($schedule->presentationAttempt->presentationStatus->is_terminal || ! $this->dayIsRunning($schedule)) {
                $awaiting = $schedule->isAwaitingReschedule($schedule->presentationAttempt);

                $schedule->expected_start_at = $awaiting ? null : $schedule->planned_start_at;
                $schedule->expected_end_at = $awaiting ? null : $schedule->planned_end_at;

                continue;
            }

            if ($schedule->hasNoSlot($schedule->presentationAttempt)) {
                $schedule->expected_start_at = null;
                $schedule->expected_end_at = null;

                continue;
            }

            // Steps over the row's own room's breaks, like the plan does
            // (PresentationDateRoom::slotStartClearOfBreaks()).
            $start = $schedule->presentationDateRoom
                ? $schedule->presentationDateRoom->slotStartClearOfBreaks($cursor, $durationMinutes)
                : $cursor;

            $schedule->expected_start_at = $start;
            $schedule->expected_end_at = $start->copy()->addMinutes($durationMinutes);

            $cursor = $schedule->expected_end_at->copy();
        }
    }

    /** Is the presentation day this row sits on underway right now? */
    private function dayIsRunning($schedule): bool
    {
        return (bool) $schedule->presentationDateRoom?->presentationDate?->isRunning();
    }

    /**
     * Panelists currently connected to any terminal in this room session —
     * same underlying query as RoomSessionController::buildSessionInfoData()'s
     * $connectedPanelistIds, just resolved to full connection records (with
     * the panelist's name and which terminal they're on) instead of a bare
     * id list, since this card shows them directly rather than just badging
     * an already-rendered name.
     */
    private function buildActivePanelists($session)
    {
        return TerminalConnection::whereHas(
            'roomTerminal',
            fn ($query) => $query->where('room_session_id', $session->id)
        )
            ->whereNull('disconnected_at')
            ->with(['panelist.profile', 'roomTerminal.terminalType'])
            ->get();
    }

    /**
     * Most recently resolved attempt across every date-room a room name
     * spans — adapted from RoomSessionController::buildLastOutcome(), which
     * is scoped to a single PresentationDateRoom (a physical terminal only
     * ever sits in one). Same "COMPLETED/ABSENT/CANCELLED by completed_at,
     * DEFERRED by its own DEFER action" ordering.
     */
    private function buildLastOutcome($dateRoomIds): ?array
    {
        $schedules = AttemptSchedule::whereIn('presentation_date_room_id', $dateRoomIds)
            ->whereHas('presentationAttempt.presentationStatus', fn ($query) => $query->whereIn('code', ['COMPLETED', 'ABSENT', 'CANCELLED', 'DEFERRED']))
            ->with([
                'presentationAttempt.presentationStatus',
                'presentationAttempt.researchGroup',
                'presentationAttempt.presentationRun.presentationActions' => fn ($query) => $query
                    ->whereHas('actionType', fn ($subQuery) => $subQuery->whereIn('code', ['COMPLETE', 'DEFER']))
                    ->with(['reason'])
                    ->latest('performed_at'),
            ])
            ->get();

        if ($schedules->isEmpty()) {
            return null;
        }

        $latestSchedule = $schedules->sortByDesc(function ($schedule) {
            $attempt = $schedule->presentationAttempt;
            $action = $attempt->presentationRun?->presentationActions->first();

            return $action?->performed_at ?? $attempt->completed_at ?? $attempt->created_at;
        })->first();

        $attempt = $latestSchedule->presentationAttempt;
        $action = $attempt->presentationRun?->presentationActions->first();
        $isDeferred = $attempt->presentationStatus->code === 'DEFERRED';

        return [
            'attempt' => $attempt,
            'deferReason' => $isDeferred ? $action?->reason : null,
            'deferRemarks' => $isDeferred ? $action?->remarks : null,
            'startedAt' => ! $isDeferred ? $attempt->presentationRun?->started_at : null,
            'completedAt' => ! $isDeferred ? $attempt->presentationRun?->completed_at : null,
            'deferredAt' => $isDeferred ? $action?->performed_at : null,
        ];
    }
}
