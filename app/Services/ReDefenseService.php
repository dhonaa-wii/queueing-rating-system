<?php

namespace App\Services;

use App\Models\AttemptSchedule;
use App\Models\AttemptType;
use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
use App\Models\PresentationDateRoom;
use App\Models\PresentationStatus;
use App\Models\QueueEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Re-Defense workflow (user-directed 2026-09-13). A completed attempt whose
 * official outcome carries presentation_outcomes.requires_new_attempt — which
 * is RE_DEFENSE and nothing else in the confirmed five-outcome set (§2.9.3) —
 * entitles the group to one more attempt. Every other outcome is final: the
 * three Pass rows and FAILED all leave the group completed with no further
 * attempt, which is why this service keys off requires_new_attempt rather
 * than the RE_DEFENSE code string.
 *
 * This is the first writer anywhere of a second presentation_attempts row —
 * QueueGenerationService has only ever created attempt_number 1 with
 * attempt_type INITIAL and previous_attempt_id null. The original attempt is
 * never touched: functional-spec §2's "previous presentation attempts are
 * never overwritten" means a re-defense is a new row chained to the old one
 * through previous_attempt_id, so the first attempt's evaluations, panel
 * assignments and recorded outcome all stay readable afterwards.
 */
class ReDefenseService
{
    public function __construct(private readonly QueueAdjustmentService $queueAdjustments)
    {
    }

    /**
     * The category's groups currently waiting on a re-defense: their latest
     * attempt is COMPLETED with a requires_new_attempt outcome, and no
     * follow-up attempt has been created for it yet. Returns one row per
     * group carrying every attempt that group has made, newest first, so the
     * tab can show the attempt count / dates / per-attempt evaluations
     * without a second query per row.
     */
    public function awaiting(PresentationCategory $category): Collection
    {
        return PresentationAttempt::whereHas('researchGroup', fn ($q) => $q->where('category_id', $category->id))
            ->with([
                'researchGroup.students' => fn ($q) => $q->orderByDesc('is_leader')->orderBy('id'),
                'presentationStatus',
                'finalOutcome',
                'attemptType',
                'attemptSchedule.presentationDateRoom.presentationDate',
                'evaluationSubmissions.submissionStatus',
            ])
            ->get()
            ->groupBy('research_group_id')
            ->map(function (Collection $groupAttempts) {
                $ordered = $groupAttempts->sortByDesc('attempt_number')->values();

                return [
                    'group' => $ordered->first()->researchGroup,
                    'latest' => $ordered->first(),
                    'attempts' => $ordered,
                ];
            })
            ->filter(fn (array $row) => $this->isAwaitingReDefense($row['latest']))
            ->sortBy(fn (array $row) => $row['group']->group_reference)
            ->values();
    }

    /**
     * A single attempt's own eligibility, kept separate from awaiting() so
     * createNextAttempt() re-checks the exact same rule server-side rather
     * than trusting the row the admin clicked.
     */
    public function isAwaitingReDefense(PresentationAttempt $attempt): bool
    {
        return $attempt->presentationStatus?->code === 'COMPLETED'
            && $attempt->finalOutcome?->requires_new_attempt === true
            && ! PresentationAttempt::where('previous_attempt_id', $attempt->id)->exists();
    }

    /**
     * Existence-only version of awaiting() — same rule as
     * isAwaitingReDefense(), as one query instead of loading every attempt
     * with its full relations. Used by EventActivationService::
     * completionBlockReason() to decide whether "End Category" is allowed:
     * a group still owed a re-defense hasn't reached a final outcome yet,
     * even though its latest attempt row is itself COMPLETED.
     */
    public function hasAwaiting(PresentationCategory $category): bool
    {
        return PresentationAttempt::whereHas('researchGroup', fn ($q) => $q->where('category_id', $category->id))
            ->whereHas('presentationStatus', fn ($q) => $q->where('code', 'COMPLETED'))
            ->whereHas('finalOutcome', fn ($q) => $q->where('requires_new_attempt', true))
            ->whereDoesntHave('nextAttempts')
            ->exists();
    }

    /**
     * The last configured day that can still run, and within it the room the
     * re-defense lands in — user-directed: "redefense should be on the last
     * date and last queue". Deliberately not QueueAdjustmentService::
     * lastOpenRoomInCategory(), which means "wherever the category's last
     * active queue slot currently sits" and falls back to the *earliest*
     * open room when the later days are still empty — the opposite of what
     * is wanted here.
     *
     * Within that day it is the room that **finishes last**, not the room the
     * group presented in before (user-directed 2026-09-16: "it needs to
     * finish all groups to present first before redefense group"). A day's
     * rooms run in parallel, so returning the group to its old room put the
     * re-defense at 8:00 AM in a room that had emptied while other rooms
     * still had a full morning of groups to get through — last in that one
     * room, but far from last in the category. Ordering by when each room's
     * current queue actually ends is what makes "after everyone else" true
     * in time rather than only in one room's numbering. The old room is kept
     * as the tie-break, so a re-defense still returns to it whenever that
     * doesn't put it ahead of anyone.
     */
    public function targetRoom(PresentationCategory $category, ?PresentationAttempt $previousAttempt = null): ?PresentationDateRoom
    {
        $rooms = PresentationDateRoom::whereHas('presentationDate', fn ($q) => $q->where('category_id', $category->id))
            ->whereHas('roomUseStatus', fn ($q) => $q->where('is_accepting_queue', true))
            ->with('presentationDate')
            ->get()
            ->filter(fn (PresentationDateRoom $room) => $room->presentationDate->isOpenForScheduling())
            // Only rooms that take the group's track (TrackRouting).
            ->filter(fn (PresentationDateRoom $room) => $previousAttempt === null || app(TrackRouting::class)->accepts($room, $previousAttempt->researchGroup));

        if ($rooms->isEmpty()) {
            return null;
        }

        $lastDate = $rooms
            ->sortByDesc(fn (PresentationDateRoom $room) => $room->presentationDate->presentation_date->format('Y-m-d'))
            ->first()
            ->presentationDate;

        $onLastDate = $rooms
            ->filter(fn (PresentationDateRoom $room) => $room->presentation_date_id === $lastDate->id)
            ->sortBy('room_name')
            ->values();

        $finishes = $onLastDate->mapWithKeys(
            fn (PresentationDateRoom $room) => [$room->id => $this->queueEndsAt($room)->format('Y-m-d H:i:s')]
        );

        $latestFinish = $finishes->max();

        $latestRooms = $onLastDate->filter(fn (PresentationDateRoom $room) => $finishes[$room->id] === $latestFinish)->values();

        $previousRoomName = $previousAttempt?->attemptSchedule?->presentationDateRoom?->room_name;

        return $latestRooms->firstWhere('room_name', $previousRoomName) ?? $latestRooms->first();
    }

    /**
     * When this room's queue, as it currently stands, is done: the latest
     * planned end among its active entries, or the room's own opening time
     * when nothing is queued in it yet. Read from planned_end_at because
     * QueueAdjustmentService::renumberRoom() recomputes every active slot
     * whenever the room's order changes, so it is the room's real timeline
     * rather than a stale write.
     */
    private function queueEndsAt(PresentationDateRoom $room): \Illuminate\Support\Carbon
    {
        $lastEnd = AttemptSchedule::where('presentation_date_room_id', $room->id)
            ->whereHas('queueEntry', fn ($q) => $q->whereNull('removed_at'))
            ->orderByDesc('planned_end_at')
            ->value('planned_end_at');

        if ($lastEnd) {
            return \Illuminate\Support\Carbon::parse($lastEnd);
        }

        return $room->presentationDate->presentation_date->copy()
            ->setTimeFromTimeString($room->room_start_time ?? $room->presentationDate->event_start_time);
    }

    /**
     * The queue number the next attempt will actually land on in $room —
     * one past its current active entries, since createNextAttempt()
     * appends and then renumberRoom() compacts to 1..N. Used by the
     * confirmation modal so it can state the real number rather than
     * describing the placement.
     */
    public function nextPositionIn(PresentationDateRoom $room): int
    {
        return QueueEntry::whereHas(
            'attemptSchedule',
            fn ($q) => $q->where('presentation_date_room_id', $room->id)
        )->whereNull('removed_at')->count() + 1;
    }

    /**
     * Create the group's next attempt and put it at the end of the target
     * room's queue. Returns the standard ['ok' => bool, ...] shape every
     * other service in this codebase uses, and never a partial write — the
     * whole insertion is one transaction.
     */
    public function createNextAttempt(PresentationAttempt $previousAttempt, int $performedByUserId): array
    {
        $previousAttempt->loadMissing(
            'researchGroup',
            'presentationStatus',
            'finalOutcome',
            'attemptSchedule.presentationDateRoom.presentationDate',
        );

        if (! $this->isAwaitingReDefense($previousAttempt)) {
            return $this->failure($this->ineligibleReason($previousAttempt));
        }

        $category = $previousAttempt->researchGroup->category;
        $room = $this->targetRoom($category, $previousAttempt);

        if ($room === null) {
            return $this->failure('No ongoing or upcoming presentation date is configured for this category — add one in Presentation Setup before scheduling a re-defense.');
        }

        if (! $this->queueAdjustments->roomHasRemainingCapacity($room)) {
            $day = $room->presentationDate->presentation_date->format('M j, Y');

            return $this->failure("\"{$room->room_name}\" on {$day} has no time left in its window — extend that day or add another date before scheduling this re-defense.");
        }

        $attempt = DB::transaction(function () use ($previousAttempt, $room, $performedByUserId) {
            $nextNumber = (int) PresentationAttempt::where('research_group_id', $previousAttempt->research_group_id)
                ->max('attempt_number') + 1;

            $attempt = PresentationAttempt::create([
                'research_group_id' => $previousAttempt->research_group_id,
                'attempt_number' => $nextNumber,
                'attempt_type_id' => AttemptType::where('code', 'RE_DEFENSE')->firstOrFail()->id,
                'previous_attempt_id' => $previousAttempt->id,
                'presentation_status_id' => PresentationStatus::where('code', 'SCHEDULED')->firstOrFail()->id,
                'final_outcome_id' => null,
                'created_by' => $performedByUserId,
            ]);

            $schedule = AttemptSchedule::create([
                'presentation_attempt_id' => $attempt->id,
                'presentation_date_room_id' => $room->id,
                // Left null on purpose: renumberRoom() below recomputes every
                // active slot's planned times by walking forward from the
                // room's own start time (the same layout QueueGenerationService
                // ::plan() does originally), so a guess written here would just
                // be overwritten a few lines later.
                'planned_call_at' => null,
                'planned_start_at' => null,
                'planned_end_at' => null,
                'adjusted_expected_at' => null,
                'scheduled_by' => $performedByUserId,
                'scheduled_at' => now(),
                'change_reason_id' => null,
                'remarks' => null,
            ]);

            QueueEntry::create([
                'attempt_schedule_id' => $schedule->id,
                // Past the end of the room's current order; renumberRoom()
                // immediately compacts it down to the real last position.
                'queue_number' => (int) QueueEntry::whereHas(
                    'attemptSchedule',
                    fn ($q) => $q->where('presentation_date_room_id', $room->id)
                )->max('queue_number') + 1,
                'priority_value' => null,
                'inserted_at' => now(),
                'removed_at' => null,
            ]);

            $this->queueAdjustments->renumberRoom($room);

            return $attempt;
        });

        return [
            'ok' => true,
            'attempt' => $attempt->fresh(),
            'room' => $room,
            'attempt_number' => $attempt->attempt_number,
        ];
    }

    private function ineligibleReason(PresentationAttempt $attempt): string
    {
        if ($attempt->presentationStatus?->code !== 'COMPLETED') {
            return 'This group has not completed a presentation yet.';
        }

        if ($attempt->finalOutcome === null) {
            return 'This presentation has no recorded outcome yet.';
        }

        if (! $attempt->finalOutcome->requires_new_attempt) {
            return "\"{$attempt->finalOutcome->name}\" is a final outcome — this group cannot be scheduled for another attempt.";
        }

        return 'A follow-up attempt has already been scheduled for this group.';
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
