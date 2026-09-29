<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationSubmission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'presentation_attempt_id',
        'panelist_user_id',
        'evaluation_form_version_id',
        'attempt_panel_participation_id',
        'submission_status_id',
        'remarks',
        'presentation_outcome_id',
        'raw_total_score',
        'weighted_total_score',
        'submitted_at',
        'reopened_by',
        'reopened_at',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reopened_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function presentationAttempt(): BelongsTo
    {
        return $this->belongsTo(PresentationAttempt::class);
    }

    public function panelist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'panelist_user_id');
    }

    public function evaluationFormVersion(): BelongsTo
    {
        return $this->belongsTo(EvaluationFormVersion::class);
    }

    public function attemptPanelParticipation(): BelongsTo
    {
        return $this->belongsTo(AttemptPanelParticipation::class);
    }

    public function submissionStatus(): BelongsTo
    {
        return $this->belongsTo(SubmissionStatus::class);
    }

    public function presentationOutcome(): BelongsTo
    {
        return $this->belongsTo(PresentationOutcome::class);
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function evaluationScores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class);
    }

    public function studentScores(): HasMany
    {
        return $this->hasMany(EvaluationSubmissionStudentScore::class);
    }
}
