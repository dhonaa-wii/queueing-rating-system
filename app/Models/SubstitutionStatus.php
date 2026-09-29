<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubstitutionStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    public function panelSubstitutionRequests(): HasMany
    {
        return $this->hasMany(PanelSubstitutionRequest::class, 'status_id');
    }
}
