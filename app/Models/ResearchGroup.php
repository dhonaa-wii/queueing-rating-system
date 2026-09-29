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

    public function leader(): ?Student
    {
        return $this->students->firstWhere('is_leader', true);
    }
}
