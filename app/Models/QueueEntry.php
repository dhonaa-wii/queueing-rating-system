<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueueEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'attempt_schedule_id',
        'queue_number',
        'priority_value',
        'inserted_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'inserted_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function attemptSchedule(): BelongsTo
    {
        return $this->belongsTo(AttemptSchedule::class);
    }

    public function queueAdjustments(): HasMany
    {
        return $this->hasMany(QueueAdjustment::class);
    }

    /**
     * The adjustment that actually deferred this entry — the source of a
     * deferred row's real "when" and "why" under the planned-vs-actual
     * date/time policy. Deliberately not just the newest adjustment: an
     * entry accumulates REORDER/TRANSFER_ROOM rows too (a busy room's entry
     * can carry a dozen), and the newest of those says nothing about the
     * defer. Reads the already-loaded relation rather than querying, so it
     * stays free inside a list; callers eager-load queueAdjustments (newest
     * first) with adjustmentType.
     */
    public function latestDeferAdjustment(): ?QueueAdjustment
    {
        return $this->queueAdjustments
            ->sortByDesc('adjusted_at')
            ->first(fn (QueueAdjustment $adjustment) => $adjustment->adjustmentType?->code === 'DEFER');
    }
}
