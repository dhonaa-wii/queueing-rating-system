<?php

namespace App\Services;

use App\Models\AttemptSchedule;
use App\Models\PanelSubstitutionRequest;
use App\Models\PresentationAttempt;
use App\Models\User;

/**
 * Notifications shown in the Panelist topbar bell. Wording lives here so the
 * three writers (Assign Panel, panel replacement, substitution requests) can't
 * drift apart. Every message is short by design — the bell shows the date
 * itself, so none of them repeat it.
 */
class PanelistNotifier
{
    /** @var array<int, array{room_id: int, start: ?string, kind: string}> attempt id => placement before the change */
    private static array $pending = [];

    private static bool $flushRegistered = false;

    public function __construct(private NotificationService $notifications)
    {
    }

    /** One notification per panelist per action, however many groups it covered. */
    public function assigned(int $panelistUserId, array $attemptIds, int $assignedByUserId): void
    {
        $attemptIds = array_values(array_unique($attemptIds));
        $panelist = User::find($panelistUserId);

        if (! $panelist || $attemptIds === [] || $panelistUserId === $assignedByUserId) {
            return;
        }

        $by = $this->fullName(User::with('profile')->find($assignedByUserId));

        if (count($attemptIds) === 1) {
            $reference = PresentationAttempt::with('researchGroup')->find($attemptIds[0])?->researchGroup?->group_reference ?? 'a group';
            $message = "Assigned to {$reference}. Assigned by {$by}.";
        } else {
            $message = 'Assigned to ' . count($attemptIds) . " groups. Assigned by {$by}.";
        }

        $this->notifications->notify($panelist, 'PANELIST_ASSIGNED', 'New assignment', $message, null, ['attempt_ids' => $attemptIds]);
    }

    /** Tells the panelist who was swapped out. $approvedByUserId is null for a backup's self-service swap. */
    public function replaced(PanelSubstitutionRequest $request, ?int $approvedByUserId): void
    {
        $request->loadMissing('originalPanelist', 'requestedSubstitute.profile', 'presentationAttempt.researchGroup');

        if (! $request->originalPanelist || ! $request->requestedSubstitute) {
            return;
        }

        $reference = $request->presentationAttempt?->researchGroup?->group_reference ?? 'a group';
        $message = "Replaced by {$this->fullName($request->requestedSubstitute)} on {$reference} via session login.";

        if ($approvedByUserId) {
            $message .= " Approved by {$this->username($approvedByUserId)}.";
        }

        $this->notifications->notify($request->originalPanelist, 'PANELIST_REPLACED', 'Replaced on panel', $message);
    }

    public function unavailabilityApproved(PanelSubstitutionRequest $request, int $approvedByUserId): void
    {
        $request->loadMissing('originalPanelist', 'presentationAttempt.researchGroup');

        if (! $request->originalPanelist) {
            return;
        }

        $reference = $request->presentationAttempt?->researchGroup?->group_reference ?? 'a group';

        $this->notifications->notify(
            $request->originalPanelist,
            'PANELIST_UNAVAILABILITY_APPROVED',
            'Unavailability approved',
            "Marked unavailable for {$reference}. Approved by {$this->username($approvedByUserId)}."
        );
    }

    /**
     * Records that a group's queue placement was changed — moved in its room,
     * transferred, carried or pulled to another day, deferred, reinserted
     * (user-directed 2026-09-29: the group's assigned panel goes with it and
     * must be told). The panel itself needs no moving: attempt_panel_assignments
     * hang off the attempt, not the room.
     *
     * Nothing is sent yet. Everything recorded during the request is sent once
     * it has finished (flushScheduleChanges()), one notification per panelist,
     * so a sweep that moves ten of a panelist's groups is one message, not ten.
     * The first recording for an attempt keeps where it started; at send time
     * a group back where it started (a no-op, or a rolled-back transaction) is
     * dropped.
     *
     * $kind: MOVED (room, day or time changed) or DEFERRED.
     */
    public function scheduleChanged(AttemptSchedule $schedule, string $kind = 'MOVED'): void
    {
        $attemptId = $schedule->presentation_attempt_id;

        if (! isset(self::$pending[$attemptId])) {
            $original = $schedule->getOriginal();

            self::$pending[$attemptId] = [
                'room_id' => $original['presentation_date_room_id'] ?? $schedule->presentation_date_room_id,
                'start' => isset($original['planned_start_at']) ? (string) $original['planned_start_at'] : null,
                'kind' => $kind,
            ];
        } elseif ($kind === 'DEFERRED') {
            self::$pending[$attemptId]['kind'] = 'DEFERRED';
        } else {
            self::$pending[$attemptId]['kind'] = 'MOVED';
        }

        if (! self::$flushRegistered) {
            self::$flushRegistered = true;
            app()->terminating(fn () => app(self::class)->flushScheduleChanges());
        }
    }

    /** Sends every recorded schedule change. Called once the request finishes. */
    public function flushScheduleChanges(): void
    {
        $pending = self::$pending;
        self::$pending = [];
        self::$flushRegistered = false;

        if ($pending === []) {
            return;
        }

        $schedules = AttemptSchedule::whereIn('presentation_attempt_id', array_keys($pending))
            ->with([
                'queueEntry',
                'presentationDateRoom.presentationDate',
                'presentationAttempt.researchGroup',
                'presentationAttempt.attemptPanelAssignments' => fn ($q) => $q->whereHas('assignmentStatus', fn ($s) => $s->whereNotIn('code', ['REPLACED', 'WITHDRAWN'])),
            ])
            ->get();

        $byPanelist = [];

        foreach ($schedules as $schedule) {
            $before = $pending[$schedule->presentation_attempt_id];
            $deferred = $schedule->queueEntry?->removed_at !== null;
            $start = $schedule->planned_start_at ? (string) $schedule->planned_start_at : null;

            $changed = $before['kind'] === 'DEFERRED'
                ? $deferred
                : $schedule->presentation_date_room_id !== $before['room_id'] || $start !== $before['start'];

            if (! $changed) {
                continue;
            }

            $line = $this->placementLine($schedule, $deferred);

            foreach ($schedule->presentationAttempt->attemptPanelAssignments as $assignment) {
                $byPanelist[$assignment->panelist_user_id][$schedule->presentation_attempt_id] = $line;
            }
        }

        foreach ($byPanelist as $panelistId => $lines) {
            $panelist = User::find($panelistId);

            if (! $panelist) {
                continue;
            }

            $message = count($lines) === 1
                ? reset($lines)
                : count($lines) . ' of your groups were rescheduled.';

            $this->notifications->notify($panelist, 'PANELIST_SCHEDULE_CHANGED', 'Schedule changed', $message, null, ['attempt_ids' => array_keys($lines)]);
        }
    }

    private function placementLine(AttemptSchedule $schedule, bool $deferred): string
    {
        $reference = $schedule->presentationAttempt->researchGroup?->group_reference ?? 'A group';

        if ($deferred) {
            return "{$reference} was deferred.";
        }

        $room = $schedule->presentationDateRoom;
        $day = $room->presentationDate->presentation_date->format('M j, Y');

        return $schedule->planned_start_at
            ? "{$reference} moved to {$room->room_name}, {$day} · {$schedule->planned_start_at->format('g:i A')}."
            : "{$reference} moved to {$room->room_name}, {$day} · time to be scheduled.";
    }

    private function fullName(?User $user): string
    {
        if (! $user) {
            return 'an administrator';
        }

        $user->loadMissing('profile');
        $name = trim(($user->profile->first_name ?? '') . ' ' . ($user->profile->last_name ?? ''));

        return $name !== '' ? $name : $user->username;
    }

    private function username(int $userId): string
    {
        return User::find($userId)?->username ?? 'an administrator';
    }
}
