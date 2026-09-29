<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmissionStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    public function evaluationSubmissions(): HasMany
    {
        return $this->hasMany(EvaluationSubmission::class, 'submission_status_id');
    }
}
