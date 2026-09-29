<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueueAdjustmentType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    public function queueAdjustments(): HasMany
    {
        return $this->hasMany(QueueAdjustment::class, 'adjustment_type_id');
    }
}
