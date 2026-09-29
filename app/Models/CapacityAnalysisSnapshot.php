<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapacityAnalysisSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'presentation_date_room_id',
        'analysis_type',
        'calculated_at',
        'available_seconds',
        'configured_duration_seconds',
        'groups_considered',
        'projected_capacity',
        'projected_overflow_count',
        'estimated_completion_at',
        'recommended_additional_days',
        'calculation_data',
    ];

    protected function casts(): array
    {
        return [
            'calculated_at' => 'datetime',
            'estimated_completion_at' => 'datetime',
            'calculation_data' => 'array',
        ];
    }

    public function presentationDateRoom(): BelongsTo
    {
        return $this->belongsTo(PresentationDateRoom::class);
    }
}
