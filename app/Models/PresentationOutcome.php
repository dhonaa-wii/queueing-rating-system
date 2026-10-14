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

    /**
     * The grading rubric (user-directed 2026-10-13): a Standard-mode remark is
     * no longer picked by the panel, it follows the score. Lower bound of each
     * band => outcome; anything under the last one is FAILED. The grade is
     * rounded off to a whole percent first (user-directed 2026-10-14), so
     * 90.5 is No Revision, 90.49 is Minor Revision and 60.5 is Re-Defense.
     */
    public const GRADE_BANDS = [
        91 => 'PASS_NO_REVISION',
        81 => 'PASS_MINOR_REVISION',
        71 => 'PASS_MAJOR_REVISION',
        61 => 'RE_DEFENSE',
    ];

    public static function codeForGrade(float $grade): string
    {
        // Round on the 2-dp value every screen shows, so a stored 90.4999…
        // that displays as 90.50 also grades as 90.50.
        $grade = round(round($grade, 2));

        foreach (self::GRADE_BANDS as $floor => $code) {
            if ($grade >= $floor) {
                return $code;
            }
        }

        return 'FAILED';
    }

    public static function idForGrade(?float $grade): ?int
    {
        if ($grade === null) {
            return null;
        }

        return self::where('code', self::codeForGrade($grade))->value('id');
    }

    /**
     * The rubric as the evaluation sheet prints it under the rating legend:
     * [['range' => '91–100%', 'name' => 'Passed with No Revision'], ...,
     * ['range' => 'Below 61%', 'name' => 'Failed']]. Names come from the
     * catalog, so a renamed outcome prints its current name.
     */
    public static function rubricLegend(): array
    {
        static $legend = null;

        if ($legend !== null) {
            return $legend;
        }

        $names = self::whereIn('code', self::ORDER)->pluck('name', 'code');
        $legend = [];
        $ceiling = 100;

        foreach (self::GRADE_BANDS as $floor => $code) {
            $legend[] = ['range' => $floor.'–'.$ceiling.'%', 'name' => $names[$code] ?? $code];
            $ceiling = $floor - 1;
        }

        $lowest = array_key_last(self::GRADE_BANDS);
        $legend[] = ['range' => 'Below '.$lowest.'%','name' => $names['FAILED'] ?? 'FAILED'];

        return $legend;
    }

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
