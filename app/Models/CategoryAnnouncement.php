<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryAnnouncement extends Model
{
    public const SOURCE_PAYMENT = 'PAYMENT_INSTRUCTIONS';

    public const PAYMENT_INSTRUCTIONS_TITLE = 'Payment Instructions';

    protected $fillable = [
        'category_id',
        'title',
        'message',
        'source',
        'starts_at',
        'ends_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isPaymentInstructions(): bool
    {
        return $this->source === self::SOURCE_PAYMENT;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PresentationCategory::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
