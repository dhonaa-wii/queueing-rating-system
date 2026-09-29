<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleBreak extends Model
{
    protected $fillable = [
        'presentation_date_room_id',
        'name',
        'planned_start_at',
        'planned_end_at',
        'actual_start_at',
        'actual_end_at',
        'is_mandatory',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'is_mandatory' => 'boolean',
        ];
    }

    public function presentationDateRoom(): BelongsTo
    {
        return $this->belongsTo(PresentationDateRoom::class);
    }
}
