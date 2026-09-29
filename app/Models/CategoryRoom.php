<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CategoryRoom extends Model
{
    protected $fillable = [
        'category_id',
        'room_name',
        'default_panelist_count',
        'is_active',
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PresentationCategory::class, 'category_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** The tracks this room is limited to. None: it takes the groups no track room takes. */
    public function researchTracks(): BelongsToMany
    {
        return $this->belongsToMany(CategoryResearchTrack::class, 'category_room_tracks')->withTimestamps()->orderBy('name');
    }
}
