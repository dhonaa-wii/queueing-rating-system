<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PresentationDateRoom extends Model
{
    protected $fillable = [
        'presentation_date_id',
        'room_name',
        'panelist_count',
        'room_use_status_id',
        'room_start_time',
        'room_end_time',
        'added_by',
        'added_at',
        'closure_requested_by',
        'closure_requested_at',
        'closed_by',
        'closed_at',
        'removal_reason',
    ];

    protected function casts(): array
    {
        return [
            'added_at' => 'datetime',
            'closure_requested_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * A room assigned to a day picks up that day's break (the one set with the
     * daily times when the date span was saved) — every room on every created
     * day has it without being told, wherever the room is added from.
     */
    protected static function booted(): void
    {
        static::created(function (PresentationDateRoom $room) {
            $date = $room->presentationDate;

            if (! $date?->break_start_time || ! $date->break_end_time) {
                return;
            }

            $day = $date->presentation_date->toDateString();

            $room->scheduleBreaks()->create([
                'name' => 'Break',
                'planned_start_at' => "{$day} {$date->break_start_time}",
                'planned_end_at' => "{$day} {$date->break_end_time}",
                'is_mandatory' => true,
            ]);
        });
    }

    public function presentationDate(): BelongsTo
    {
        return $this->belongsTo(PresentationDate::class);
    }

    public function roomUseStatus(): BelongsTo
    {
        return $this->belongsTo(RoomUseStatus::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by')->withTrashed();
    }

    public function closureRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closure_requested_by')->withTrashed();
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    public function scheduleBreaks(): HasMany
    {
        return $this->hasMany(ScheduleBreak::class);
    }

    /**
     * The earliest moment at or after $start where a slot of $minutes fits
     * without touching one of this room's breaks — a slot that would begin
     * inside a break, or run into one, starts when the break ends instead.
     * The one place a room's breaks are applied to a timeline: queue
     * generation, every re-layout of a room's planned times and both live
     * forecasts all go through it, so a break is never filled with a group.
     */
    public function slotStartClearOfBreaks(Carbon $start, int $minutes): Carbon
    {
        $breaks = $this->scheduleBreaks->sortBy('planned_start_at')->values();

        $cursor = $start->copy();

        do {
            $moved = false;

            foreach ($breaks as $break) {
                if ($cursor->lt($break->planned_end_at) && $cursor->copy()->addMinutes($minutes)->gt($break->planned_start_at)) {
                    $cursor = $break->planned_end_at->copy();
                    $moved = true;
                }
            }
        } while ($moved);

        return $cursor;
    }

    /** The break this room is inside of at $at, if any (its start included, its end not). */
    public function breakAt(Carbon $at): ?ScheduleBreak
    {
        return $this->scheduleBreaks->first(
            fn (ScheduleBreak $break) => $break->planned_start_at->lte($at) && $at->lt($break->planned_end_at)
        );
    }

    /**
     * The break that the slot after $endsAt would have to wait out: the first
     * one not yet over that a slot of $minutes starting at $endsAt would touch.
     * The same test slotStartClearOfBreaks() applies, so "a break follows this
     * group" and "the next group starts when the break ends" always agree.
     */
    public function breakFollowing(Carbon $endsAt, int $minutes): ?ScheduleBreak
    {
        return $this->scheduleBreaks
            ->sortBy('planned_start_at')
            ->first(fn (ScheduleBreak $break) => $endsAt->lt($break->planned_end_at) && $endsAt->copy()->addMinutes($minutes)->gt($break->planned_start_at));
    }

    /** Whole minutes of this room's breaks that fall inside [$from, $to]. */
    public function breakMinutesBetween(Carbon $from, Carbon $to): int
    {
        return (int) $this->scheduleBreaks->sum(function (ScheduleBreak $break) use ($from, $to) {
            $start = $break->planned_start_at->greaterThan($from) ? $break->planned_start_at : $from;
            $end = $break->planned_end_at->lessThan($to) ? $break->planned_end_at : $to;

            return $end->greaterThan($start) ? $start->diffInMinutes($end) : 0;
        });
    }

    public function capacityAnalysisSnapshots(): HasMany
    {
        return $this->hasMany(CapacityAnalysisSnapshot::class);
    }

    public function attemptSchedules(): HasMany
    {
        return $this->hasMany(AttemptSchedule::class);
    }

    public function roomSessions(): HasMany
    {
        return $this->hasMany(RoomSession::class);
    }

    /**
     * The one "Start" value every room card shows (user-directed 2026-09-15):
     * the real moment this room started on its day once it has, otherwise the
     * planned start. The card label never changes — only the value does.
     */
    public function startTime(): ?Carbon
    {
        return $this->actualStartAt() ?? $this->plannedStartAt();
    }

    public function plannedStartAt(): ?Carbon
    {
        $date = $this->presentationDate;
        $time = $this->room_start_time ?? $date?->event_start_time;

        return $date && $time ? $date->presentation_date->copy()->setTimeFromTimeString($time) : null;
    }

    /**
     * When this room was started on its day. Start Room stamps
     * room_sessions.started_at; sessions from before it did fall back to the
     * day's own start. Queried rather than read off the roomSessions relation
     * because some pages eager-load that relation filtered to open sessions
     * only, which would hide a room already closed for the day.
     */
    public function actualStartAt(): ?Carbon
    {
        $session = $this->roomSessions()->with('presentationEvent')->oldest('id')->first();

        if (! $session) {
            return null;
        }

        return $session->started_at ?? $session->presentationEvent?->started_at;
    }

    public function hasClosedForDay(): bool
    {
        return $this->roomSessions()->whereNotNull('ended_at')->exists();
    }
}
