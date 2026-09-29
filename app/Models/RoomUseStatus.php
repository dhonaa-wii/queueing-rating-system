<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomUseStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'is_accepting_queue',
        'is_terminal',
    ];

    protected function casts(): array
    {
        return [
            'is_accepting_queue' => 'boolean',
            'is_terminal' => 'boolean',
        ];
    }

    public function presentationDateRooms(): HasMany
    {
        return $this->hasMany(PresentationDateRoom::class);
    }
}
