<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormVersionStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    public function evaluationFormVersions(): HasMany
    {
        return $this->hasMany(EvaluationFormVersion::class, 'status_id');
    }
}
