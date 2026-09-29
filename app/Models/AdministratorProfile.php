<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdministratorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'college_id',
        'employee_reference',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }
}
