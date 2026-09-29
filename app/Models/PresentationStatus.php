<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresentationStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'is_terminal',
    ];

    protected function casts(): array
    {
        return [
            'is_terminal' => 'boolean',
        ];
    }

    public function presentationAttempts(): HasMany
    {
        return $this->hasMany(PresentationAttempt::class);
    }
}
