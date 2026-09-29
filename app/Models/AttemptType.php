<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttemptType extends Model
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

    public function presentationAttempts(): HasMany
    {
        return $this->hasMany(PresentationAttempt::class);
    }
}
