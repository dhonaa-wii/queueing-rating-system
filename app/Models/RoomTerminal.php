<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomTerminal extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'room_session_id',
        'terminal_number',
        'terminal_type_id',
        'device_identifier',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function roomSession(): BelongsTo
    {
        return $this->belongsTo(RoomSession::class);
    }

    public function terminalType(): BelongsTo
    {
        return $this->belongsTo(TerminalType::class);
    }

    public function terminalAccessTokens(): HasMany
    {
        return $this->hasMany(TerminalAccessToken::class);
    }

    public function terminalConnections(): HasMany
    {
        return $this->hasMany(TerminalConnection::class);
    }
}
