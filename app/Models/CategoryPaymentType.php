<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryPaymentType extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'sort_order',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(PresentationCategory::class, 'category_id');
    }

    public function paymentVerifications(): HasMany
    {
        return $this->hasMany(PaymentVerification::class);
    }
}
