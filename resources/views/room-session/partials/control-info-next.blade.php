@php
    // Bottom section of the merged "Presentation Control" card — extracted
    // out of control-info-card.blade.php so it can render after the
    // (Lead-only) control buttons in DOM order while still refreshing
    // independently every poll, same as before (user-directed 2026-08-21
    // wireframe: Presentation Control -> stats -> panelist -> buttons ->
    // Next, all one visual card).
    //
    // The ticking timer that used to live in this header moved out
    // 2026-08-22 (user-directed) onto the Current Group line in
    // attempt-status-card.blade.php, right next to that card's status
    // badge — showing the run's own timer here, in the "Next" section,
    // read as if it belonged to the next group rather than the current one.
    $nextThree = collect($queue['upcoming'] ?? [])->take(3);
@endphp

<div class="p-2 rounded-3" style="background-color: var(--brand-surface-alt); font-size: 0.7rem;">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <div class="text-brand-muted" style="font-size: 0.62rem;">Next</div>
        <button type="button" class="btn btn-sm btn-outline-brand" style="font-size: 0.68rem; padding: 0.15rem 0.5rem;" data-view-all-toggle><x-icon name="eye" /> View All</button>
    </div>

    @if ($nextThree->isNotEmpty())
        <div class="d-flex gap-2 flex-wrap">
            @foreach ($nextThree as $n)
                <div class="small p-2 rounded-3" style="background-color: var(--brand-surface); flex: 1 1 140px; min-width: 140px;">
                    <div class="fw-semibold">{{ $n->presentationAttempt->researchGroup->group_reference }}</div>
                    <div class="text-brand-muted" style="font-size: 0.72rem;">{{ $n->presentationAttempt->researchGroup->leader()?->full_name ?? '—' }}</div>
                    @if ($n->expected_start_at)
                        <div class="text-brand-muted" style="font-size: 0.68rem;">Expected {{ $n->expected_start_at->format('g:i A') }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="small text-brand-muted">—</div>
    @endif
</div>
