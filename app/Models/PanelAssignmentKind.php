<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PanelAssignmentKind extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    public function attemptPanelAssignments(): HasMany
    {
        return $this->hasMany(AttemptPanelAssignment::class, 'assignment_kind_id');
    }
}
