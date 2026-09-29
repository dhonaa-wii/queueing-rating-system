<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueueStrategy extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function categoryQueueSettings(): HasMany
    {
        return $this->hasMany(CategoryQueueSetting::class);
    }
}
