<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationSubmissionStudentScore extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'evaluation_submission_id',
        'student_id',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
        ];
    }

    public function evaluationSubmission(): BelongsTo
    {
        return $this->belongsTo(EvaluationSubmission::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
