<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationForm extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function evaluationFormVersions(): HasMany
    {
        return $this->hasMany(EvaluationFormVersion::class);
    }

    public function draftVersion(): ?EvaluationFormVersion
    {
        return $this->evaluationFormVersions()
            ->whereHas('status', fn ($q) => $q->where('code', 'DRAFT'))
            ->latest('version_number')
            ->first();
    }

    public function activeVersion(): ?EvaluationFormVersion
    {
        return $this->evaluationFormVersions()
            ->whereHas('status', fn ($q) => $q->where('code', 'ACTIVE'))
            ->latest('version_number')
            ->first();
    }

    /**
     * The one version a form has. Versions were removed as a concept on
     * 2026-09-22 (user-directed) - a form is created with a single version
     * and that same row is edited and published in place forever after, so
     * every screen resolves "the form" through this.
     *
     * The precedence exists only for the handful of forms built before that
     * change, which can still carry a RETIRED row and/or a leftover DRAFT
     * beside their published one: the published row is the form that is
     * actually in use, so it wins. A form that has never been published has
     * only its draft.
     */
    public function currentVersion(): ?EvaluationFormVersion
    {
        return $this->activeVersion()
            ?? $this->draftVersion()
            ?? $this->evaluationFormVersions()->latest('version_number')->first();
    }
}
