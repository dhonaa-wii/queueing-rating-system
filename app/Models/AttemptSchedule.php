<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AttemptSchedule extends Model
{
    /**
     * What a still-to-present group's date/time reads as once the day it was
     * parked on can no longer run (user-directed 2026-09-16). The plan such a
     * row still carries is a slot on a day that is over — showing it would
     * advertise a date that has already been and gone — so nothing shows it,
     * and these read in its place. One set of words for the whole system, so
     * a student, a panelist and an admin are all told the same thing.
     */
    public const AWAITING_SHORT = 'To be scheduled';

    public const AWAITING_DATE = 'Awaiting new presentation date';

    public const AWAITING_ROOM = 'Awaiting room assignment';

    /** The same thing as AWAITING_ROOM, for a table cell that can't carry the full phrase. */
    public const AWAITING_ROOM_SHORT = 'To be assigned';

    /**
     * A group scheduled but not yet paneled. A re-defense always reads this
     * at the moment it is created: the new attempt is a new row, so the panel
     * that sat the previous attempt stays with that attempt as its record and
     * this one starts with none (user-directed 2026-09-16).
     */
    public const AWAITING_PANEL = 'To be assigned';

    public $timestamps = false;

    protected $fillable = [
        'presentation_attempt_id',
        'presentation_date_room_id',
        'planned_call_at',
        'planned_start_at',
        'planned_end_at',
        'adjusted_expected_at',
        'scheduled_by',
        'scheduled_at',
        'change_reason_id',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'planned_call_at' => 'datetime',
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'adjusted_expected_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    public function presentationAttempt(): BelongsTo
    {
        return $this->belongsTo(PresentationAttempt::class);
    }

    public function presentationDateRoom(): BelongsTo
    {
        return $this->belongsTo(PresentationDateRoom::class);
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function changeReason(): BelongsTo
    {
        return $this->belongsTo(AdjustmentReason::class, 'change_reason_id');
    }

    public function queueEntry(): HasOne
    {
        return $this->hasOne(QueueEntry::class);
    }

    /**
     * This group still has to present, but the day it is parked on can no
     * longer run: its own day is finished/cancelled (or its window passed
     * unstarted), and end-of-day carry-over had nowhere to move it to.
     * QueueAdjustmentService::processEndOfDay() leaves such a group exactly
     * where it is rather than inventing a slot for it, and
     * EventActivationService::flagUnscheduledGroups() raises
     * CATEGORY_UNSCHEDULED_GROUPS at the admin for the same condition — this
     * is the display side of that same state.
     *
     * The single source of truth behind the "no stale slot, say so plainly"
     * rule (user-directed 2026-09-16) — see the PLANNED-VS-ACTUAL DATE/TIME
     * POLICY in CLAUDE.md. Wherever this is true, no date, time or room is
     * shown for the row at all; AWAITING_SHORT/AWAITING_DATE/AWAITING_ROOM
     * are shown in their place. Nothing is erased in the database: the
     * attempt keeps its queue placement so an admin can still transfer it
     * once a new date exists, and it recovers a real schedule the moment it
     * lands on a day that can actually run.
     *
     * Deliberately false for a row that is already resolved — a completed
     * attempt is described by when it really happened, a deferred one by when
     * it was really deferred, and the policy's existing real-timestamp rule
     * already owns both.
     *
     * $attempt is accepted so a caller already holding it (the admin roster,
     * the panelist assignment lists) doesn't lazy-load it back through the
     * relation. Needs presentationDateRoom.presentationDate.eventDateStatus
     * and queueEntry loaded to stay free inside a list.
     */
    public function isAwaitingReschedule(?PresentationAttempt $attempt = null): bool
    {
        $attempt ??= $this->presentationAttempt;

        if ($attempt?->presentationStatus?->is_terminal) {
            return false;
        }

        if ($this->queueEntry?->removed_at) {
            return false;
        }

        if ($this->hasNoSlot($attempt)) {
            return true;
        }

        $date = $this->presentationDateRoom?->presentationDate;

        return $date !== null && ! $date->isOpenForScheduling();
    }

    /**
     * The other way a group ends up "to be scheduled" (user-directed
     * 2026-09-24): it still has to present and its day is fine, but the
     * configured days and rooms have no time left for it — the queue was
     * generated with more groups than the schedule holds, or a break or a
     * shorter window squeezed one out. Such a group is not scheduled onto a
     * day that doesn't exist: its planned times are null and it stays parked
     * at the end of a room's queue (the FK needs a room) until capacity
     * appears — QueueAdjustmentService::spillOverflow() re-slots it the moment
     * a room can take it, and Capacity Analysis / Needs Attention say how
     * much is missing. A group that is live in a room (called, on stage) is
     * never "no slot", whatever its planned times say.
     */
    public function hasNoSlot(?PresentationAttempt $attempt = null): bool
    {
        if ($this->planned_start_at !== null) {
            return false;
        }

        $attempt ??= $this->presentationAttempt;
        $status = $attempt?->presentationStatus;

        if ($status?->is_terminal || in_array($status?->code, ['CALLED', 'ONGOING', 'PAUSED'], true)) {
            return false;
        }

        return $this->queueEntry?->removed_at === null;
    }
}
