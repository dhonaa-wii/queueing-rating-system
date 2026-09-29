<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdjustmentReason extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
