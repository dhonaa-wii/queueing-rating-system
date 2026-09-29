<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'is_terminal',
    ];

    protected function casts(): array
    {
        return [
            'is_terminal' => 'boolean',
        ];
    }

    public function paymentVerifications(): HasMany
    {
        return $this->hasMany(PaymentVerification::class);
    }
}
