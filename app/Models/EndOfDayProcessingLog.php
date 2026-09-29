<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndOfDayProcessingLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'presentation_date_id',
        'processed_at',
        'processed_by',
        'unresolved_group_count',
        'absent_group_count',
        'moved_to_category_end_count',
        'details_json',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'details_json' => 'array',
        ];
    }

    public function presentationDate(): BelongsTo
    {
        return $this->belongsTo(PresentationDate::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by')->withTrashed();
    }
}
