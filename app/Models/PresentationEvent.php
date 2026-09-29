<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresentationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'presentation_date_id',
        'event_status_id',
        'started_by',
        'started_at',
        'ended_by',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function presentationDate(): BelongsTo
    {
        return $this->belongsTo(PresentationDate::class);
    }

    public function eventStatus(): BelongsTo
    {
        return $this->belongsTo(EventStatus::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by')->withTrashed();
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by')->withTrashed();
    }

    public function roomSessions(): HasMany
    {
        return $this->hasMany(RoomSession::class);
    }
}
