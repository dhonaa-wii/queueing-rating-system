<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequirementStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    public function attemptRequirements(): HasMany
    {
        return $this->hasMany(AttemptRequirement::class, 'status_id');
    }
}
