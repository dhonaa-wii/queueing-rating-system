<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryScheduleSetting extends Model
{
    protected $fillable = [
        'category_id',
        'duration_minutes',
        'allow_extended_time',
    ];

    protected function casts(): array
    {
        return [
            'allow_extended_time' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PresentationCategory::class, 'category_id');
    }
}
