<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationScore extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'evaluation_submission_id',
        'evaluation_criterion_id',
        'student_id',
        'proposed_title_id',
        'score',
        'weighted_score',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_criterion_id' => 'integer',
            'student_id' => 'integer',
            'proposed_title_id' => 'integer',
            'score' => 'float',
            'weighted_score' => 'float',
        ];
    }

    public function evaluationSubmission(): BelongsTo
    {
        return $this->belongsTo(EvaluationSubmission::class);
    }

    public function evaluationCriterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'evaluation_criterion_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function proposedTitle(): BelongsTo
    {
        return $this->belongsTo(ProposedTitle::class);
    }
}
