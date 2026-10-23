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
     * A Move or Transfer by an Administrator. A pending re-defense placed this
     * way keeps its place instead of being pushed to the end of the queue
     * (user-directed 2026-10-23: re-defense goes last automatically, but the
     * Admin may let it present earlier). A later Defer or Reinsert undoes it,
     * so a reinserted re-defense goes last again.
     */
    public const ADMIN_PLACED_TYPES = ['REORDER', 'TRANSFER_ROOM', 'CHANGE_DATE'];

    private const PLACEMENT_RESET_TYPES = ['DEFER', 'REINSERT'];

    /**
     * Of $entryIds, those whose latest Move/Transfer/Defer/Reinsert is a Move
     * or Transfer. Returned flipped (entry id => index) for isset() checks.
     *
     * @return array<int, int>
     */
    public static function adminPlacedIds(iterable $entryIds): array
    {
        $ids = collect($entryIds)->filter()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return QueueAdjustment::query()
            ->whereIn('queue_entry_id', $ids)
            ->join('queue_adjustment_types', 'queue_adjustment_types.id', '=', 'queue_adjustments.adjustment_type_id')
            ->whereIn('queue_adjustment_types.code', [...self::ADMIN_PLACED_TYPES, ...self::PLACEMENT_RESET_TYPES])
            ->orderBy('queue_adjustments.adjusted_at')
            ->orderBy('queue_adjustments.id')
            ->get(['queue_adjustments.queue_entry_id', 'queue_adjustment_types.code'])
            ->groupBy('queue_entry_id')
            ->filter(fn ($rows) => in_array($rows->last()->code, self::ADMIN_PLACED_TYPES, true))
            ->keys()
            ->flip()
            ->all();
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
