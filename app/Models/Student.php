<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    public const SEXES = ['Male', 'Female'];

    protected $fillable = [
        'student_number',
        'last_name',
        'first_name',
        'middle_name',
        'sex',
        'section_name',
        'research_track_name',
        'research_group_id',
        'is_leader',
    ];

    protected function casts(): array
    {
        return [
            'is_leader' => 'boolean',
        ];
    }

    /** Display name, "Cruz, Juan D." — the one place the format is decided. */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => self::formatName($this->last_name, $this->first_name, $this->middle_name));
    }

    public static function formatName(?string $last, ?string $first, ?string $middle): string
    {
        $initial = $middle !== null && trim($middle) !== '' ? ' '.mb_strtoupper(mb_substr(trim($middle), 0, 1)).'.' : '';
        $given = trim((string) $first);
        $surname = trim((string) $last);

        if ($surname === '') {
            return $given.$initial;
        }

        return $given === '' ? $surname : "{$surname}, {$given}{$initial}";
    }

    /**
     * Trim, collapse whitespace and capitalise each word, including after a
     * hyphen or apostrophe ("dela  cruz" -> "Dela Cruz", "o'brien" ->
     * "O'Brien"). Applied to last, first and middle names alike.
     */
    public static function normalizeName(?string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name));

        return preg_replace_callback(
            "/(^|[\s\-'’])(\p{L})(\p{L}*)/u",
            fn ($m) => $m[1].mb_strtoupper($m[2]).mb_strtolower($m[3]),
            $name
        );
    }

    /**
     * Every whitespace/comma separated term must match the last, first or
     * middle name, so "juan cruz" and "cruz, juan" both find Cruz, Juan D.
     */
    public function scopeNameMatches(Builder $query, string $search): Builder
    {
        $terms = preg_split('/[\s,]+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($terms as $term) {
            $like = '%'.addcslashes($term, '%_\\').'%';

            $query->where(fn ($q) => $q
                ->where('last_name', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('middle_name', 'like', $like));
        }

        return $query;
    }

    public function researchGroup(): BelongsTo
    {
        return $this->belongsTo(ResearchGroup::class);
    }

    public function evaluationScores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class);
    }
}
