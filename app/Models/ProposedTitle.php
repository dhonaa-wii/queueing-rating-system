<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProposedTitle extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'research_group_id',
        'presentation_attempt_id',
        'title_text',
        'sort_order',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
        ];
    }

    public function researchGroup(): BelongsTo
    {
        return $this->belongsTo(ResearchGroup::class);
    }

    public function presentationAttempt(): BelongsTo
    {
        return $this->belongsTo(PresentationAttempt::class);
    }

    public function evaluationScores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class);
    }
}
