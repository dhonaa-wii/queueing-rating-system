<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DecisionStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    public function attemptDecisions(): HasMany
    {
        return $this->hasMany(AttemptDecision::class, 'decision_status_id');
    }
}
