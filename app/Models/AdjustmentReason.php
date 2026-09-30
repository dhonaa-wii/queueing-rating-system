<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdjustmentReason extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'is_active',
        'is_defer_reason',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_defer_reason' => 'boolean',
        ];
    }

    /** The reasons a Defer dropdown offers, in their set order. */
    public function scopeForDefer(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_defer_reason', true)->orderBy('sort_order')->orderBy('name');
    }

    /** The reasons Move, Transfer, Reinsert and Pause offer. */
    public function scopeForOtherAdjustments(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_defer_reason', false)->orderBy('name');
    }

    public function queueAdjustments(): HasMany
    {
        return $this->hasMany(QueueAdjustment::class, 'reason_id');
    }

    public function attemptSchedules(): HasMany
    {
        return $this->hasMany(AttemptSchedule::class, 'change_reason_id');
    }

    public function presentationPauses(): HasMany
    {
        return $this->hasMany(PresentationPause::class, 'reason_id');
    }

    public function presentationActions(): HasMany
    {
        return $this->hasMany(PresentationAction::class, 'reason_id');
    }
}
