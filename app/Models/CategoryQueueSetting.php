<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryQueueSetting extends Model
{
    protected $fillable = [
        'category_id',
        'queue_strategy_id',
        'called_waiting_minutes',
        'allow_same_day_reinsertion',
        'late_defer_enabled',
        'unresolved_absent_end_of_day',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'allow_same_day_reinsertion' => 'boolean',
            'late_defer_enabled' => 'boolean',
            'unresolved_absent_end_of_day' => 'boolean',
            'settings_json' => 'array',
        ];
    }

    /**
     * The Random Draw strategy's secret seed, drawn once when the strategy is
     * saved and kept for as long as the category stays on it — see
     * QueueGenerationService::orderByRandomDraw().
     */
    public function randomDrawSeed(): ?string
    {
        return $this->settings_json['random_draw_seed'] ?? null;
    }

    /**
     * The Section Based strategy's configured section order (e.g. ["4C",
     * "4A"]) — see QueueGenerationService::orderBySection(). Empty means
     * sections run A–Z.
     */
    public function sectionOrder(): array
    {
        return $this->settings_json['section_order'] ?? [];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PresentationCategory::class, 'category_id');
    }

    public function queueStrategy(): BelongsTo
    {
        return $this->belongsTo(QueueStrategy::class);
    }
}
