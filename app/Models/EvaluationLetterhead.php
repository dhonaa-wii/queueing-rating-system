<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per college (user-directed 2026-09-21) — a college configures its
 * own logos and header lines, and nothing it sets reaches another college's
 * evaluation sheets. Resolve one with forCollege(); never with first().
 */
class EvaluationLetterhead extends Model
{
    protected $fillable = [
        'college_id',
        'logo_path',
        'secondary_logo_path',
        'line_1',
        'line_2',
        'line_3',
        'line_4',
        'updated_by',
    ];

    /** URL slot => column, for LetterheadLogoController. */
    public const LOGO_COLUMNS = [
        'primary' => 'logo_path',
        'secondary' => 'secondary_logo_path',
    ];

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    /**
     * The letterhead a given college configured, or null when it has none
     * yet (or the caller has no college in scope at all — a category
     * without one must not silently inherit somebody else's branding).
     */
    public static function forCollege(?int $collegeId): ?self
    {
        return $collegeId
            ? static::where('college_id', $collegeId)->first()
            : null;
    }

    /**
     * Where a page loads a logo from: an app route, not Storage::url(), so it
     * works without the public/storage symlink and whatever APP_URL says
     * (relative URL). The ?v= changes when the logo is replaced, so browsers
     * never keep showing the old one. Null when that logo isn't set.
     */
    public function logoUrl(string $column): ?string
    {
        $slot = array_search($column, self::LOGO_COLUMNS, true);

        if ($slot === false || ! $this->{$column}) {
            return null;
        }

        return route('letterhead.logo', [
            'letterhead' => $this->id,
            'slot' => $slot,
            'v' => substr(md5($this->{$column}), 0, 8),
        ], false);
    }

    /** True once there is something worth rendering on a sheet. */
    public function hasContent(): bool
    {
        return (bool) ($this->logo_path || $this->secondary_logo_path
            || $this->line_1 || $this->line_2 || $this->line_3 || $this->line_4);
    }
}
