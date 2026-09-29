<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomSessionAccount extends Model
{
    protected $fillable = [
        'presentation_category_id',
        'presentation_date_id',
        'room_name',
        'username',
        'password',
        'max_concurrent_logins',
        'is_active',
        'generated_by',
        'generated_at',
        'credentials_last_reset_at',
        'credentials_reset_by',
        'deactivated_by',
        'deactivated_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            // User-directed 2026-08-15: unlike a personal account's password,
            // this shared/room credential needs to stay visible on the Live
            // Monitoring card at all times (not just once at reveal/reset) so
            // an Admin can notice unexpected terminal activity and compare it
            // against the credential currently in use. 'encrypted' (reversible
            // via APP_KEY) instead of 'hashed' (one-way) so it can be read back.
            'password' => 'encrypted',
            'is_active' => 'boolean',
            'generated_at' => 'datetime',
            'credentials_last_reset_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PresentationCategory::class, 'presentation_category_id');
    }

    public function presentationDate(): BelongsTo
    {
        return $this->belongsTo(PresentationDate::class, 'presentation_date_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by')->withTrashed();
    }

    public function credentialsResetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credentials_reset_by')->withTrashed();
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by')->withTrashed();
    }
}
