<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueAdjustment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'queue_entry_id',
        'adjustment_type_id',
        'old_position',
        'new_position',
        'reason_id',
        'remarks',
        'approved_by',
        'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'adjusted_at' => 'datetime',
        ];
    }

    public function queueEntry(): BelongsTo
    {
        return $this->belongsTo(QueueEntry::class);
    }

    public function adjustmentType(): BelongsTo
    {
        return $this->belongsTo(QueueAdjustmentType::class, 'adjustment_type_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(AdjustmentReason::class, 'reason_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
