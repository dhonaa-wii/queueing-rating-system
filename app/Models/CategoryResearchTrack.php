<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CategoryResearchTrack extends Model
{
    protected $fillable = ['category_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PresentationCategory::class, 'category_id');
    }

    public function categoryRooms(): BelongsToMany
    {
        return $this->belongsToMany(CategoryRoom::class, 'category_room_tracks')->withTimestamps();
    }
}
