<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttemptPanelParticipation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'presentation_attempt_id',
        'panelist_user_id',
        'terminal_number',
        'terminal_type_id',
        'terminal_connection_id',
        'participation_started_at',
        'participation_ended_at',
        'is_approved_substitute',
    ];

    protected function casts(): array
    {
        return [
            'participation_started_at' => 'datetime',
            'participation_ended_at' => 'datetime',
            'is_approved_substitute' => 'boolean',
        ];
    }

    public function presentationAttempt(): BelongsTo
    {
        return $this->belongsTo(PresentationAttempt::class);
    }

    public function panelist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'panelist_user_id');
    }

    public function terminalType(): BelongsTo
    {
        return $this->belongsTo(TerminalType::class);
    }

    public function terminalConnection(): BelongsTo
    {
        return $this->belongsTo(TerminalConnection::class);
    }

    public function evaluationSubmissions(): HasMany
    {
        return $this->hasMany(EvaluationSubmission::class, 'attempt_panel_participation_id');
    }
}
