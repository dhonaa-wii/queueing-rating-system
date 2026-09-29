<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptDecision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'presentation_attempt_id',
        'presentation_outcome_id',
        'decision_status_id',
        'approved_proposed_title_id',
        'recommendation',
        'recorded_by',
        'recorded_at',
        'finalized_by',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function presentationAttempt(): BelongsTo
    {
        return $this->belongsTo(PresentationAttempt::class);
    }

    public function presentationOutcome(): BelongsTo
    {
        return $this->belongsTo(PresentationOutcome::class);
    }

    public function decisionStatus(): BelongsTo
    {
        return $this->belongsTo(DecisionStatus::class);
    }

    public function approvedProposedTitle(): BelongsTo
    {
        return $this->belongsTo(ProposedTitle::class, 'approved_proposed_title_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
