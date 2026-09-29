<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanelSubstitutionRequest extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'presentation_attempt_id',
        'original_panelist_user_id',
        'requested_substitute_user_id',
        'requested_by',
        'reason',
        'status_id',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function presentationAttempt(): BelongsTo
    {
        return $this->belongsTo(PresentationAttempt::class);
    }

    public function originalPanelist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_panelist_user_id')->withTrashed();
    }

    public function requestedSubstitute(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_substitute_user_id')->withTrashed();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(SubstitutionStatus::class, 'status_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
