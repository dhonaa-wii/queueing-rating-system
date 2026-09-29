<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptRequirement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'presentation_attempt_id',
        'requirement_type_id',
        'description',
        'due_at',
        'status_id',
        'completed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function presentationAttempt(): BelongsTo
    {
        return $this->belongsTo(PresentationAttempt::class);
    }

    public function requirementType(): BelongsTo
    {
        return $this->belongsTo(RequirementType::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(RequirementStatus::class, 'status_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
