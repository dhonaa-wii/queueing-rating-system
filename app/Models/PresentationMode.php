<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresentationMode extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    public function presentationCategories(): HasMany
    {
        return $this->hasMany(PresentationCategory::class);
    }
}
