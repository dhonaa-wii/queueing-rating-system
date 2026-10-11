<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A panelist a group chose at registration; sort_order 1 is the Chair. */
class ResearchGroupPanelistChoice extends Model
{
    protected $fillable = [
        'research_group_id',
        'panelist_user_id',
        'sort_order',
    ];

    public function researchGroup(): BelongsTo
    {
        return $this->belongsTo(ResearchGroup::class);
    }

    public function panelist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'panelist_user_id');
    }
}
