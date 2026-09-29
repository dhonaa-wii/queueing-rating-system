<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomSessionStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    public function roomSessions(): HasMany
    {
        return $this->hasMany(RoomSession::class);
    }
}
