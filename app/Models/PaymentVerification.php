<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentVerification extends Model
{
    protected $fillable = [
        'presentation_attempt_id',
        'category_payment_type_id',
        'payment_status_id',
        'receipt_code_encrypted',
        'receipt_code_hash',
        'initially_checked_by',
        'terminal_connection_id',
        'checked_at',
        'referred_to_admin_at',
        'referred_by',
        'resolved_by',
        'resolved_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            // Never stored in plain text — the column name promised this
            // from the start (2026-07-28) but nothing actually encrypted it
            // until this cast was added; Laravel's cast encrypts on save and
            // decrypts transparently on read using APP_KEY. receipt_code_hash
            // (a plain, deterministic SHA-256 of the normalized value, set by
            // PaymentVerificationService) exists alongside it purely so a
            // duplicate reference number can be detected — the encrypted
            // column can't be queried directly since its ciphertext changes
            // on every save.
            'receipt_code_encrypted' => 'encrypted',
            'checked_at' => 'datetime',
            'referred_to_admin_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function presentationAttempt(): BelongsTo
    {
        return $this->belongsTo(PresentationAttempt::class);
    }

    public function categoryPaymentType(): BelongsTo
    {
        return $this->belongsTo(CategoryPaymentType::class);
    }

    public function paymentStatus(): BelongsTo
    {
        return $this->belongsTo(PaymentStatus::class);
    }

    public function initiallyCheckedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initially_checked_by');
    }

    public function terminalConnection(): BelongsTo
    {
        return $this->belongsTo(TerminalConnection::class);
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
