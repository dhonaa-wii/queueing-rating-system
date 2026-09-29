<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TerminalConnection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'room_terminal_id',
        'panelist_user_id',
        'connected_at',
        'disconnected_at',
        'connection_status_id',
        'authenticated_via',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    public function roomTerminal(): BelongsTo
    {
        return $this->belongsTo(RoomTerminal::class);
    }

    public function panelist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'panelist_user_id')->withTrashed();
    }

    public function connectionStatus(): BelongsTo
    {
        return $this->belongsTo(ConnectionStatus::class);
    }

    public function attemptPanelParticipations(): HasMany
    {
        return $this->hasMany(AttemptPanelParticipation::class);
    }

    public function presentationActions(): HasMany
    {
        return $this->hasMany(PresentationAction::class);
    }

    public function paymentVerifications(): HasMany
    {
        return $this->hasMany(PaymentVerification::class);
    }
}
