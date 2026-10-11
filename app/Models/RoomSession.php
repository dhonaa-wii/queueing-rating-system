<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'presentation_event_id',
        'presentation_date_room_id',
        'room_session_status_id',
        'current_attempt_id',
        'kept_break_id',
        'started_at',
        'ended_at',
        'ended_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * Started and not yet closed — the rooms the Room Session list shows and
     * a device can watch or a panelist can join.
     */
    public function isOpen(): bool
    {
        $this->loadMissing('roomSessionStatus');

        return $this->ended_at === null && $this->roomSessionStatus?->code !== 'CLOSED';
    }

    /**
     * A group is on stage right now (ONGOING or PAUSED). A merely CALLED
     * group doesn't count, the same test End Room uses.
     */
    public function isPresenting(): bool
    {
        $this->loadMissing('currentAttempt.presentationStatus');

        return in_array($this->currentAttempt?->presentationStatus?->code, ['ONGOING', 'PAUSED'], true);
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('ended_at')
            ->whereHas('roomSessionStatus', fn ($status) => $status->where('code', '!=', 'CLOSED'));
    }

    public function presentationEvent(): BelongsTo
    {
        return $this->belongsTo(PresentationEvent::class);
    }

    public function presentationDateRoom(): BelongsTo
    {
        return $this->belongsTo(PresentationDateRoom::class);
    }

    public function roomSessionStatus(): BelongsTo
    {
        return $this->belongsTo(RoomSessionStatus::class);
    }

    public function currentAttempt(): BelongsTo
    {
        return $this->belongsTo(PresentationAttempt::class, 'current_attempt_id');
    }

    /** The break the Lead chose to keep after this group finishes (see RoomBreakService). */
    public function keptBreak(): BelongsTo
    {
        return $this->belongsTo(ScheduleBreak::class, 'kept_break_id');
    }

    public function endedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by_user_id')->withTrashed();
    }

    public function roomTerminals(): HasMany
    {
        return $this->hasMany(RoomTerminal::class);
    }

    public function presentationRuns(): HasMany
    {
        return $this->hasMany(PresentationRun::class);
    }

    public function notices(): HasMany
    {
        return $this->hasMany(RoomSessionNotice::class);
    }
}
