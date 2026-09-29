<?php

namespace App\Services;

use App\Models\PresentationAttempt;
use App\Models\PresentationDateRoom;
use App\Models\PresentationRun;
use App\Models\RoomSession;
use App\Models\RoomSessionStatus;
use App\Models\ScheduleBreak;
use Illuminate\Support\Facades\DB;

/**
 * A room's scheduled break, as it plays out on the day (user-directed
 * 2026-09-24). Where the break sits in the plan is owned by
 * PresentationDateRoom::slotStartClearOfBreaks() and the Edit Date modal; this
 * is what happens when the live day meets it:
 *
 *  - the room is on BREAK while its clock is inside a break and no group is on
 *    stage — nothing may be called, started or verified until it ends;
 *  - a group still presenting when its break arrives does not get cut off. The
 *    Lead is asked once whether to cancel the break (the next group follows
 *    this one straight away) or to finish this group and then take what is
 *    left of the break;
 *  - the Lead can end a running break early.
 *
 * The room's status has no scheduler behind it, so BREAK is reconciled lazily
 * by sync() on the pages that already poll (the tablet, Event Control) and by
 * the admin maintenance sweep — the same "recompute on a frequently-hit page
 * load" convention the rest of the app uses. The guards that matter, in
 * PresentationControlService, ask onBreak() directly rather than trusting the
 * stored status, so a stale status can never let a call through.
 */
class RoomBreakService
{
    public function __construct(
        private readonly QueueAdjustmentService $queueAdjustments,
    ) {
    }

    /** True while a group is actually on stage — ONGOING or PAUSED. */
    public function isPresenting(RoomSession $session): bool
    {
        $session->loadMissing('currentAttempt.presentationStatus');

        return in_array($session->currentAttempt?->presentationStatus?->code, ['ONGOING', 'PAUSED'], true);
    }

    /** The break the room is on right now: inside a break's window with nobody on stage. */
    public function onBreak(RoomSession $session): ?ScheduleBreak
    {
        $session->loadMissing('roomSessionStatus', 'presentationDateRoom.scheduleBreaks');

        if (in_array($session->roomSessionStatus?->code, ['CLOSED', 'FINISHED', 'PENDING_CLOSURE', 'PAUSED'], true)) {
            return null;
        }

        if ($this->isPresenting($session)) {
            return null;
        }

        return $session->presentationDateRoom->breakAt(now());
    }

    /**
     * Set when a group is still on stage after its room's break has started
     * and the Lead has not yet said what to do about it — the trigger for the
     * "cancel the break / finish this group, then break" prompt.
     */
    public function pendingDecision(RoomSession $session): ?ScheduleBreak
    {
        $session->loadMissing('presentationDateRoom.scheduleBreaks');

        if (! $this->isPresenting($session)) {
            return null;
        }

        $break = $session->presentationDateRoom->breakAt(now());

        return $break && $break->id !== $session->kept_break_id ? $break : null;
    }

    /**
     * The break the group in front of the room will run into — shown, blinking,
     * to every terminal so the panel knows it is coming. "Runs into" is the
     * same test the schedule uses for the next slot: if a further group of the
     * configured length would touch the break, the room breaks after this one.
     */
    public function upcomingBreak(RoomSession $session, ?PresentationAttempt $attempt, ?PresentationRun $run, int $durationMinutes): ?ScheduleBreak
    {
        if (! $attempt || $durationMinutes <= 0) {
            return null;
        }

        $attempt->loadMissing('presentationStatus');

        if (! in_array($attempt->presentationStatus?->code, ['CALLED', 'ONGOING', 'PAUSED'], true)) {
            return null;
        }

        $session->loadMissing('presentationDateRoom.scheduleBreaks');

        $now = now();
        $end = ($run?->started_at)
            ? $run->started_at->copy()->addSeconds(($run->configured_duration_seconds ?? $durationMinutes * 60) + ($run->total_paused_seconds ?? 0))
            : $now->copy()->addMinutes($durationMinutes);

        if ($end->lt($now)) {
            $end = $now->copy();
        }

        return $session->presentationDateRoom->breakFollowing($end, $durationMinutes);
    }

    /**
     * Brings the stored room status in line with the clock: BREAK while the
     * room is on a break, back to WAITING/ACTIVE once it is not. Idempotent.
     */
    public function sync(RoomSession $session): void
    {
        $session->loadMissing('roomSessionStatus', 'presentationDateRoom.scheduleBreaks');

        $code = $session->roomSessionStatus?->code;
        $break = $this->onBreak($session);

        if ($break) {
            if ($code !== 'BREAK') {
                $this->setStatus($session, 'BREAK');
            }

            if ($break->actual_start_at === null) {
                $break->update(['actual_start_at' => now()]);
            }

            return;
        }

        if ($code === 'BREAK') {
            $this->setStatus($session, $session->current_attempt_id ? 'ACTIVE' : 'WAITING');
        }

        // A break that ran its full course records when it really ended.
        $session->presentationDateRoom->scheduleBreaks
            ->filter(fn (ScheduleBreak $break) => $break->actual_start_at !== null && $break->actual_end_at === null && $break->planned_end_at->lte(now()))
            ->each(fn (ScheduleBreak $break) => $break->update(['actual_end_at' => $break->planned_end_at]));
    }

    /** Ends the break the room is on now, so the next group can be called. */
    public function endBreak(RoomSession $session, int $performedByUserId): array
    {
        $break = $this->onBreak($session);

        if (! $break) {
            return $this->failure('This room is not on a break.');
        }

        $room = $session->presentationDateRoom;
        $now = now();

        DB::transaction(function () use ($session, $break, $now) {
            $break->update([
                'planned_end_at' => $now,
                'actual_start_at' => $break->actual_start_at ?? $break->planned_start_at,
                'actual_end_at' => $now,
            ]);

            $this->setStatus($session, $session->current_attempt_id ? 'ACTIVE' : 'WAITING');
        });

        $this->relayout($room, $performedByUserId);

        return ['ok' => true];
    }

    /**
     * Removes the room's break altogether — the group that ran into it
     * carries on and the next one can be called straight after. Takes the
     * planned time out of the room's day, so everything behind it moves up.
     */
    public function cancelBreak(RoomSession $session, int $breakId, int $performedByUserId): array
    {
        $session->loadMissing('presentationDateRoom.scheduleBreaks');
        $room = $session->presentationDateRoom;
        $break = $room->scheduleBreaks->firstWhere('id', $breakId);

        if (! $break || $break->planned_end_at->lte(now())) {
            return $this->failure('That break is no longer scheduled.');
        }

        DB::transaction(fn () => $break->delete());

        $room->load('scheduleBreaks');
        $session->unsetRelation('keptBreak');
        $session->kept_break_id = null;
        $this->sync($session);
        $this->relayout($room, $performedByUserId);

        return ['ok' => true];
    }

    /**
     * The Lead's other answer: let this group finish, and take whatever is
     * left of the break afterwards. Nothing about the plan changes — the
     * break keeps its scheduled end, so the time the group overran is simply
     * lost from it; this only records that the question has been answered.
     */
    public function keepBreak(RoomSession $session, int $breakId): array
    {
        $session->loadMissing('presentationDateRoom.scheduleBreaks');
        $break = $session->presentationDateRoom->scheduleBreaks->firstWhere('id', $breakId);

        if (! $break || $break->planned_end_at->lte(now())) {
            return $this->failure('That break is no longer scheduled.');
        }

        $session->update(['kept_break_id' => $break->id]);

        return ['ok' => true];
    }

    private function relayout(PresentationDateRoom $room, int $performedByUserId): void
    {
        $this->queueAdjustments->relayoutAfterBreakChange($room, $performedByUserId);
    }

    private function setStatus(RoomSession $session, string $code): void
    {
        $session->update(['room_session_status_id' => RoomSessionStatus::where('code', $code)->firstOrFail()->id]);
        $session->load('roomSessionStatus');
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
