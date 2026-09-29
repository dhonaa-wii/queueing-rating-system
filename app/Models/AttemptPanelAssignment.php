<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptPanelAssignment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'presentation_attempt_id',
        'panelist_user_id',
        'assignment_kind_id',
        'is_lead',
        'assignment_status_id',
        'assigned_by',
        'assigned_at',
        'ended_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_lead' => 'boolean',
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function presentationAttempt(): BelongsTo
    {
        return $this->belongsTo(PresentationAttempt::class);
    }

    public function panelist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'panelist_user_id')->withTrashed();
    }

    public function assignmentKind(): BelongsTo
    {
        return $this->belongsTo(PanelAssignmentKind::class, 'assignment_kind_id');
    }

    public function assignmentStatus(): BelongsTo
    {
        return $this->belongsTo(PanelAssignmentStatus::class, 'assignment_status_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withTrashed();
    }

    /**
     * How this seat reads on screen (user-directed 2026-09-25): the Lead is
     * the "Chair", every other seated panelist is "Member 1", "Member 2", …
     * numbered from 1 in the order they were seated, and the reserve is the
     * "Alternate Panel". Members only evaluate; the Chair also runs the flow.
     *
     * The number is derived from the panel's current live seats rather than
     * stored, so it stays contiguous whichever panelist is designated Chair
     * or swapped out.
     */
    public function roleLabel(): string
    {
        if ($this->isAlternate()) {
            return 'Alternate Panel';
        }

        if ($this->is_lead) {
            return 'Chair';
        }

        $number = $this->memberNumber();

        return $number ? 'Member ' . $number : 'Member';
    }

    public function isAlternate(): bool
    {
        return $this->assignmentKind?->code === 'BACKUP_PANELIST';
    }

    private ?int $memberNumberCache = null;

    /** 1-based position among this attempt's live, non-Chair assigned seats. */
    public function memberNumber(): ?int
    {
        if ($this->isAlternate() || $this->is_lead) {
            return null;
        }

        if ($this->memberNumberCache === null) {
            $ids = static::query()
                ->where('presentation_attempt_id', $this->presentation_attempt_id)
                ->where('is_lead', false)
                ->whereHas('assignmentKind', fn ($q) => $q->where('code', 'ASSIGNED_PANELIST'))
                ->whereHas('assignmentStatus', fn ($q) => $q->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $position = array_search($this->id, $ids, true);
            $this->memberNumberCache = $position === false ? 0 : $position + 1;
        }

        return $this->memberNumberCache ?: null;
    }

    /**
     * Number the members of an already-loaded panel: assignment id => 1..N.
     * For lists that hold the whole panel, so no per-row query is needed.
     */
    public static function memberNumbers(iterable $assignments): array
    {
        $numbers = [];
        $next = 1;

        foreach (collect($assignments)->sortBy('id') as $assignment) {
            if ($assignment->is_lead || $assignment->isAlternate()) {
                continue;
            }
            $numbers[$assignment->id] = $next++;
        }

        return $numbers;
    }

    /** Badge tint matching roleLabel(), so the two never disagree. */
    public function roleBadgeClass(): string
    {
        if ($this->assignmentKind?->code === 'BACKUP_PANELIST') {
            return 'badge-brand-tint';
        }

        return $this->is_lead ? 'badge-info-tint' : 'badge-muted-tint';
    }
}
