<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationFormVersion extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'evaluation_form_id',
        'version_number',
        'status_id',
        'scoring_method_id',
        'total_weight',
        'maximum_total_score',
        'scale_min',
        'scale_max',
        'show_letterhead',
        'created_by',
        'activated_at',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'show_letterhead' => 'boolean',
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function evaluationForm(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(FormVersionStatus::class, 'status_id');
    }

    public function scoringMethod(): BelongsTo
    {
        return $this->belongsTo(ScoringMethod::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function evaluationCriteria(): HasMany
    {
        return $this->hasMany(EvaluationCriterion::class);
    }

    public function categoryEvaluationForms(): HasMany
    {
        return $this->hasMany(CategoryEvaluationForm::class);
    }

    public function evaluationSubmissions(): HasMany
    {
        return $this->hasMany(EvaluationSubmission::class);
    }

    public function scaleLabels(): HasMany
    {
        return $this->hasMany(EvaluationScaleLabel::class)->orderBy('value');
    }

    public function sections(): HasMany
    {
        return $this->evaluationCriteria()
            ->whereNull('parent_criterion_id')
            ->orderBy('sort_order');
    }

    public function presentationOutcomes(): BelongsToMany
    {
        return $this->belongsToMany(PresentationOutcome::class, 'evaluation_form_version_outcomes')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function applicablePresentationModes(): BelongsToMany
    {
        return $this->belongsToMany(PresentationMode::class, 'evaluation_form_version_presentation_modes');
    }

    public function isDraft(): bool
    {
        return $this->status->code === 'DRAFT';
    }

    public function isActive(): bool
    {
        return $this->status->code === 'ACTIVE';
    }

    public function hasSubmissions(): bool
    {
        return $this->evaluationSubmissions()->exists();
    }

    /**
     * A form is editable for as long as it is live - draft or published.
     * User-directed 2026-09-22 ("just let admin to edit the form anytime"),
     * retiring the old rule that locked a published version the moment a real
     * panelist evaluation was recorded against it and pushed the admin into a
     * second version instead. There are no second versions any more, so
     * locking would mean a published form could never be corrected.
     *
     * Editing a form that already carries submissions is therefore allowed,
     * with one guard kept where it would otherwise crash rather than merely
     * be inconsistent: EvaluationFormBuilderService refuses to *delete* a
     * section/criterion that real scores are recorded against
     * (evaluation_scores.evaluation_criterion_id is a hard, non-cascading FK).
     * Renaming and reweighting stay open - a weight change does not rewrite
     * the weighted totals already stored on past submissions.
     *
     * RETIRED is still never editable: those rows only exist from before
     * versions were removed, and are a record of what was actually served.
     */
    public function isEditable(): bool
    {
        return $this->isDraft() || $this->isActive();
    }
}
