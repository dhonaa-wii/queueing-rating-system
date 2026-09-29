<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class College extends Model
{
    protected $fillable = [
        'campus_id',
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

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function administratorProfiles(): HasMany
    {
        return $this->hasMany(AdministratorProfile::class);
    }

    public function presentationCategories(): HasMany
    {
        return $this->hasMany(PresentationCategory::class);
    }
}
