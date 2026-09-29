<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CriterionScope extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    public function evaluationCriteria(): HasMany
    {
        return $this->hasMany(EvaluationCriterion::class);
    }
}
