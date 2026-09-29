<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryPaymentSetting extends Model
{
    protected $fillable = [
        'category_id',
        'payment_required',
        'verification_instructions',
        'allow_admin_referral',
    ];

    protected function casts(): array
    {
        return [
            'payment_required' => 'boolean',
            'allow_admin_referral' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PresentationCategory::class, 'category_id');
    }
}
