<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'allows_registration',
        'allows_queue_generation',
        'is_terminal',
    ];

    protected function casts(): array
    {
        return [
            'allows_registration' => 'boolean',
            'allows_queue_generation' => 'boolean',
            'is_terminal' => 'boolean',
        ];
    }

    public function presentationCategories(): HasMany
    {
        return $this->hasMany(PresentationCategory::class);
    }
}
