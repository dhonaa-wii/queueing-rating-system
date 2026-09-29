<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresentationPause extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'presentation_run_id',
        'paused_at',
        'resumed_at',
        'reason_id',
        'remarks',
        'paused_by',
        'resumed_by',
    ];

    protected function casts(): array
    {
        return [
            'paused_at' => 'datetime',
            'resumed_at' => 'datetime',
        ];
    }

    public function presentationRun(): BelongsTo
    {
        return $this->belongsTo(PresentationRun::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(AdjustmentReason::class, 'reason_id');
    }

    public function pausedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by');
    }

    public function resumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resumed_by');
    }
}
