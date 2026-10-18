<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanelistProfile extends Model
{
    protected $fillable = [
        'user_id',
        'college_id',
        'is_external',
        'external_department',
        'specialization',
        'temporary_password',
        'registered_by',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'is_external' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    /**
     * The panelist's department: the college they're registered under for an
     * internal panelist, or the department the Admin typed for an external one.
     */
    public function departmentName(): ?string
    {
        if ($this->is_external) {
            return $this->external_department ?: null;
        }

        return $this->college?->name;
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by')->withTrashed();
    }
}
