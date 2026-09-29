<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresentationOutcome extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'requires_new_attempt',
        'allows_project_title_change',
        'is_successful',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_new_attempt' => 'boolean',
            'allows_project_title_change' => 'boolean',
            'is_successful' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function presentationAttempts(): HasMany
    {
        return $this->hasMany(PresentationAttempt::class, 'final_outcome_id');
    }

    public function attemptDecisions(): HasMany
    {
        return $this->hasMany(AttemptDecision::class, 'presentation_outcome_id');
    }
}
