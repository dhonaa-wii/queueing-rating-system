<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresentationOutcome extends Model
{
    public $timestamps = false;

    /**
     * The confirmed Remarks sequence (user-directed 2026-10-13). Every list of
     * outcomes, and every form's saved Remarks order, follows it; an outcome an
     * Admin adds later goes after these five, by name.
     */
    public const ORDER = ['PASS_NO_REVISION', 'PASS_MINOR_REVISION', 'PASS_MAJOR_REVISION', 'RE_DEFENSE', 'FAILED'];

    public function scopeOrdered(Builder $query): Builder
    {
        $cases = collect(self::ORDER)->map(fn ($code, $i) => "WHEN '{$code}' THEN {$i}")->implode(' ');

        return $query->orderByRaw("CASE code {$cases} ELSE ".count(self::ORDER).' END')->orderBy('name');
    }

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
