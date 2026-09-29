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

    /** True once there is something worth rendering on a sheet. */
    public function hasContent(): bool
    {
        return (bool) ($this->logo_path || $this->secondary_logo_path
            || $this->line_1 || $this->line_2 || $this->line_3 || $this->line_4);
    }
}
