<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchGroup extends Model
{
    protected $fillable = [
        'group_reference',
        'category_id',
        'current_project_title',
        'technical_adviser_name',
        'technical_adviser_user_id',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PresentationCategory::class, 'category_id');
    }

    /** The adviser's panelist account (2026-10-19); null for an adviser typed before advisers were picked from the registry. */
    public function technicalAdviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technical_adviser_user_id')->withTrashed();
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function presentationAttempts(): HasMany
    {
        return $this->hasMany(PresentationAttempt::class);
    }

    public function proposedTitles(): HasMany
    {
        return $this->hasMany(ProposedTitle::class);
    }

    /** The panel the group chose at registration, Chair first. */
    public function panelistChoices(): HasMany
    {
        return $this->hasMany(ResearchGroupPanelistChoice::class)->orderBy('sort_order');
    }

    public function leader(): ?Student
    {
        return $this->students->firstWhere('is_leader', true);
    }
}
