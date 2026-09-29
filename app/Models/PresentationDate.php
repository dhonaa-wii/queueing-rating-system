<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class PresentationDate extends Model
{
    protected $fillable = [
        'category_id',
        'presentation_date',
        'event_start_time',
        'event_end_time',
        'break_start_time',
        'break_end_time',
        'event_date_status_id',
        'activated_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'presentation_date' => 'date',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Calendar day first, then the session's own start time, then id — a day
     * can hold more than one session (an early-ended one plus a follow-up),
     * so the date alone no longer gives a stable order.
     */
    public function scopeChronological($query)
    {
        return $query->orderBy('presentation_date')->orderBy('event_start_time')->orderBy('id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PresentationCategory::class, 'category_id');
    }

    public function eventDateStatus(): BelongsTo
    {
        return $this->belongsTo(EventDateStatus::class);
    }

    public function presentationDateRooms(): HasMany
    {
        return $this->hasMany(PresentationDateRoom::class);
    }

    public function presentationEvent(): HasOne
    {
        return $this->hasOne(PresentationEvent::class);
    }

    public function endOfDayProcessingLogs(): HasMany
    {
        return $this->hasMany(EndOfDayProcessingLog::class);
    }

    /**
     * Derives what event_date_statuses.code this date SHOULD be, from real
     * date/time plus the activation lifecycle — no manual override exists
     * (user-directed 2026-08-04, mirrors PresentationCategory::deriveStatus()).
     * ACTIVE/COMPLETED are set by App\Services\EventActivationService
     * (via activated_at / completed_at) — STANDBY only means the scheduled
     * time has arrived and the admin's Start action is now available, not
     * that anything has actually begun (user-directed 2026-08-05: renamed
     * from READY, which is overloaded elsewhere in this schema —
     * category_statuses.READY_FOR_QUEUE, event_statuses.READY,
     * queue_entries' derived READY_NEXT — STANDBY reads unambiguously as
     * "holding, nothing has started"). CANCELLED still has no writer yet.
     * Once COMPLETED/CANCELLED is reached, it's left alone rather than
     * downgraded back to an earlier status — those two are this lookup's
     * only is_terminal=true codes. ACTIVE is deliberately NOT in that sticky
     * list: it must stay re-derivable so that setting completed_at while
     * currently ACTIVE (App\Services\EventActivationService::end()) can
     * actually advance the status to COMPLETED — a currentCode-is-ACTIVE
     * short-circuit here would make the completed_at check below dead code
     * for every date once started (this exact bug existed until this fix:
     * ACTIVE used to be sticky too, and since nothing wrote activated_at
     * before EventActivationService existed, it went undetected).
     */
    public function deriveStatus(): string
    {
        $currentCode = $this->eventDateStatus?->code;

        if (in_array($currentCode, ['COMPLETED', 'CANCELLED'], true)) {
            return $currentCode;
        }

        if ($this->completed_at) {
            return 'COMPLETED';
        }

        if ($this->activated_at) {
            return 'ACTIVE';
        }

        $eventStartsAt = $this->presentation_date->copy()
            ->setTimeFromTimeString($this->event_start_time);

        return now()->gte($eventStartsAt) ? 'STANDBY' : 'PLANNED';
    }

    public function refreshStatus(): void
    {
        $derivedCode = $this->deriveStatus();

        if ($this->eventDateStatus?->code === $derivedCode) {
            return;
        }

        $status = EventDateStatus::where('code', $derivedCode)->first();

        if ($status) {
            $this->update(['event_date_status_id' => $status->id]);
        }
    }

    /**
     * True once this date's own configured window (presentation_date +
     * event_end_time) has passed in real time while it was never actually
     * started (no activated_at) and hasn't been completed/cancelled either.
     * A date can otherwise sit at STANDBY indefinitely — nothing here
     * auto-advances it — so this is the signal Admin\LiveMonitoringController/
     * App\Services\EventActivationService use to block Start Event and to
     * raise the "update the schedule" notification (user-directed
     * 2026-08-23: the planned date must not silently stay wrong once real
     * time has moved past it — the admin must edit it in Presentation Setup
     * before the event can be started). Purely derived, like deriveStatus()
     * itself — no separate flag to keep in sync, so editing the date (or its
     * end time) to something not yet passed clears this on its own.
     */
    public function isOverdue(): bool
    {
        if (in_array($this->eventDateStatus?->code, ['ACTIVE', 'COMPLETED', 'CANCELLED'], true)) {
            return false;
        }

        if ($this->activated_at) {
            return false;
        }

        $eventEndsAt = $this->presentation_date->copy()
            ->setTimeFromTimeString($this->event_end_time);

        return now()->gt($eventEndsAt);
    }

    /**
     * "Ongoing or upcoming" — the two states a day can still take new work
     * in: a room being placed on it (Presentation Setup / the room
     * registry) or a group being transferred into it (Group & Panel
     * Assignment). A finished day (COMPLETED/CANCELLED) never can; every
     * other day can right up until its own window passes unstarted, which
     * is exactly what isOverdue() already means — an overdue day can't be
     * started at all until it's edited (§2.8.5), so queueing more work onto
     * it would just be building up a pile that can never run.
     *
     * Deliberately not used to gate *editing* a past date — editing is how
     * an overdue day gets fixed, so that has to stay available.
     */
    public function isOpenForScheduling(): bool
    {
        if (in_array($this->eventDateStatus?->code, ['COMPLETED', 'CANCELLED'], true)) {
            return false;
        }

        return ! $this->isOverdue();
    }

    /**
     * This day is underway right now — a room on it has been started and the
     * day has not been ended yet.
     *
     * The switch for the planned-vs-expected display rule (user-directed
     * 2026-09-13): a still-to-present group on a running day is shown its
     * live expected time, because the day has a real timeline that the plan
     * has already drifted from; the same group on a day that has not started
     * is shown its planned time, because there is nothing real to forecast
     * from yet. Reads `activated_at`/`completed_at` rather than the derived
     * status code so it is true for exactly the window between the first
     * Start Room and the day ending, with no dependence on a refreshStatus()
     * having run first.
     */
    public function isRunning(): bool
    {
        return $this->activated_at !== null && $this->completed_at === null;
    }

    /**
     * Deletes this date along with everything under it that has a restricting FK
     * back to presentation_dates / presentation_date_rooms (rooms, breaks, capacity
     * snapshots, attempt schedules, room sessions, the event record, end-of-day
     * logs) — a plain delete() throws an FK violation the moment any room exists.
     */
    public function deleteWithChildren(): void
    {
        DB::transaction(function () {
            $this->loadMissing('presentationDateRooms');

            foreach ($this->presentationDateRooms as $room) {
                $room->scheduleBreaks()->delete();
                $room->capacityAnalysisSnapshots()->delete();
                $room->attemptSchedules()->delete();
                $room->roomSessions()->delete();
            }

            $this->presentationDateRooms()->delete();
            $this->presentationEvent()->delete();
            $this->endOfDayProcessingLogs()->delete();

            $this->delete();
        });
    }
}
