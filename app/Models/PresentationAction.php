<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresentationAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'presentation_run_id',
        'action_type_id',
        'performed_by',
        'terminal_connection_id',
        'reason_id',
        'remarks',
        'performed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function presentationRun(): BelongsTo
    {
        return $this->belongsTo(PresentationRun::class);
    }

    public function actionType(): BelongsTo
    {
        return $this->belongsTo(PresentationActionType::class, 'action_type_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function terminalConnection(): BelongsTo
    {
        return $this->belongsTo(TerminalConnection::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(AdjustmentReason::class, 'reason_id');
    }
}
