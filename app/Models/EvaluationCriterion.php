<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationCriterion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'evaluation_form_version_id',
        'parent_criterion_id',
        'name',
        'description',
        'criterion_scope_id',
        'weight',
        'maximum_score',
        'sort_order',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    public function evaluationFormVersion(): BelongsTo
    {
        return $this->belongsTo(EvaluationFormVersion::class);
    }

    public function parentCriterion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_criterion_id');
    }

    public function childCriteria(): HasMany
    {
        return $this->hasMany(self::class, 'parent_criterion_id')->orderBy('sort_order');
    }

    public function criterionScope(): BelongsTo
    {
        return $this->belongsTo(CriterionScope::class);
    }

    public function evaluationScores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class, 'evaluation_criterion_id');
    }
}
