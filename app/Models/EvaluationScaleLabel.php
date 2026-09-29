<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationScaleLabel extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'evaluation_form_version_id',
        'value',
        'label',
    ];

    public function evaluationFormVersion(): BelongsTo
    {
        return $this->belongsTo(EvaluationFormVersion::class);
    }
}
