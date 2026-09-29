<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryEvaluationForm extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'category_id',
        'evaluation_form_version_id',
        'effective_from',
        'effective_until',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PresentationCategory::class, 'category_id');
    }

    public function evaluationFormVersion(): BelongsTo
    {
        return $this->belongsTo(EvaluationFormVersion::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
