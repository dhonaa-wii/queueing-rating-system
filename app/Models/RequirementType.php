<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequirementType extends Model
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

    public function attemptRequirements(): HasMany
    {
        return $this->hasMany(AttemptRequirement::class);
    }
}
