<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptRatingSummary extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'presentation_attempt_id',
        'required_evaluations',
        'received_evaluations',
        'final_group_score',
        'final_rating',
        'calculation_rule_version',
        'calculated_at',
        'finalized_by',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'calculated_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function presentationAttempt(): BelongsTo
    {
        return $this->belongsTo(PresentationAttempt::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by')->withTrashed();
    }
}
