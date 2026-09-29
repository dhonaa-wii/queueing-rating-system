<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventDateStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'is_terminal',
    ];

    protected function casts(): array
    {
        return [
            'is_terminal' => 'boolean',
        ];
    }

    public function presentationDates(): HasMany
    {
        return $this->hasMany(PresentationDate::class);
    }
}
