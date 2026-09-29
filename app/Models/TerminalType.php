<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TerminalType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'can_control_flow',
        'can_verify_payment',
        'can_evaluate',
    ];

    protected function casts(): array
    {
        return [
            'can_control_flow' => 'boolean',
            'can_verify_payment' => 'boolean',
            'can_evaluate' => 'boolean',
        ];
    }

    public function roomTerminals(): HasMany
    {
        return $this->hasMany(RoomTerminal::class);
    }

    public function attemptPanelParticipations(): HasMany
    {
        return $this->hasMany(AttemptPanelParticipation::class);
    }
}
