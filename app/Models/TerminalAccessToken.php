<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalAccessToken extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'room_terminal_id',
        'token_hash',
        'manual_code_hash',
        'expires_at',
        'used_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function roomTerminal(): BelongsTo
    {
        return $this->belongsTo(RoomTerminal::class);
    }
}
