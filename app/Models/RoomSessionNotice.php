<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomSessionNotice extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'room_session_id',
        'action',
        'group_reference',
        'message',
        'performed_by',
        'created_at',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function roomSession(): BelongsTo
    {
        return $this->belongsTo(RoomSession::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by')->withTrashed();
    }
}
