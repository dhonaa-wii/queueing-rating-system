<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresentationRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'presentation_attempt_id',
        'room_session_id',
        'called_at',
        'waiting_deadline_at',
        'started_at',
        'completed_at',
        'configured_duration_seconds',
        'actual_duration_seconds',
        'total_paused_seconds',
        'extended_seconds',
        'timer_status_id',
        'last_action_at',
    ];

    protected function casts(): array
    {
        return [
            'called_at' => 'datetime',
            'waiting_deadline_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_action_at' => 'datetime',
        ];
    }

    public function presentationAttempt(): BelongsTo
    {
        return $this->belongsTo(PresentationAttempt::class);
    }

    public function roomSession(): BelongsTo
    {
        return $this->belongsTo(RoomSession::class);
    }

    public function timerStatus(): BelongsTo
    {
        return $this->belongsTo(TimerStatus::class);
    }

    public function presentationPauses(): HasMany
    {
        return $this->hasMany(PresentationPause::class);
    }

    public function presentationActions(): HasMany
    {
        return $this->hasMany(PresentationAction::class);
    }
}
