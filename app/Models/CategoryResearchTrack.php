<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CategoryResearchTrack extends Model
{
    protected $fillable = ['category_id', 'name', 'evaluation_form_version_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PresentationCategory::class, 'category_id');
    }

    /** The track's own evaluation form, used when the category requires a track. */
    public function evaluationFormVersion(): BelongsTo
    {
        return $this->belongsTo(EvaluationFormVersion::class);
    }

    public function categoryRooms(): BelongsToMany
    {
        return $this->belongsToMany(CategoryRoom::class, 'category_room_tracks')->withTimestamps();
    }
}
