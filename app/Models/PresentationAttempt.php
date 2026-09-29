<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PresentationAttempt extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'research_group_id',
        'attempt_number',
        'attempt_type_id',
        'previous_attempt_id',
        'presentation_status_id',
        'final_outcome_id',
        'created_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function researchGroup(): BelongsTo
    {
        return $this->belongsTo(ResearchGroup::class);
    }

    public function attemptType(): BelongsTo
    {
        return $this->belongsTo(AttemptType::class);
    }

    public function previousAttempt(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_attempt_id');
    }

    public function nextAttempts(): HasMany
    {
        return $this->hasMany(self::class, 'previous_attempt_id');
    }

    public function presentationStatus(): BelongsTo
    {
        return $this->belongsTo(PresentationStatus::class);
    }

    public function finalOutcome(): BelongsTo
    {
        return $this->belongsTo(PresentationOutcome::class, 'final_outcome_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function proposedTitles(): HasMany
    {
        return $this->hasMany(ProposedTitle::class);
    }

    public function attemptSchedule(): HasOne
    {
        return $this->hasOne(AttemptSchedule::class);
    }

    public function attemptPanelAssignments(): HasMany
    {
        return $this->hasMany(AttemptPanelAssignment::class);
    }

    public function panelSubstitutionRequests(): HasMany
    {
        return $this->hasMany(PanelSubstitutionRequest::class);
    }

    public function attemptPanelParticipations(): HasMany
    {
        return $this->hasMany(AttemptPanelParticipation::class);
    }

    public function presentationRun(): HasOne
    {
        return $this->hasOne(PresentationRun::class);
    }

    /**
     * One row per configured category payment type (2026-09-20 — used to be
     * a single hasOne row per attempt, back when a category only ever had
     * one payment_required flag). See PaymentVerificationService for how
     * these are read/written; almost nothing outside that service should
     * query this relation directly.
     */
    public function paymentVerifications(): HasMany
    {
        return $this->hasMany(PaymentVerification::class);
    }

    public function evaluationSubmissions(): HasMany
    {
        return $this->hasMany(EvaluationSubmission::class);
    }

    public function attemptRatingSummary(): HasOne
    {
        return $this->hasOne(AttemptRatingSummary::class);
    }

    public function attemptDecisions(): HasMany
    {
        return $this->hasMany(AttemptDecision::class);
    }

    public function attemptRequirements(): HasMany
    {
        return $this->hasMany(AttemptRequirement::class);
    }
}
