{{--
    "Next" preview for one room — a read-only fork of
    room-session/partials/control-info-next.blade.php, kept as its own
    file (per user direction 2026-08-22) so this public page's version can
    be restyled without touching the live room-session tablet's. No
    "View All" button here — the full queue is already listed in the left
    column's table. The timer that used to sit in this header moved onto
    the Current Group line (current-group-card.blade.php), same as the
    room-session tablet's own move, 2026-08-22. Expects $room, same as
    status-card.blade.php.
--}}
@php
    $live = $room->live;
    $nextThree = $live['nextThree'];
@endphp

<div class="p-2 rounded-3" style="background-color: var(--brand-surface-alt); font-size: 0.7rem;">
    <div class="text-brand-muted mb-2" style="font-size: 0.62rem;">Next</div>

    @if ($nextThree->isNotEmpty())
        <div class="d-flex gap-2 flex-wrap">
            @foreach ($nextThree as $n)
                <div class="small p-2 rounded-3" style="background-color: var(--brand-surface); flex: 1 1 140px; min-width: 140px;">
                    <div class="fw-semibold">{{ $n->presentationAttempt->researchGroup->group_reference }}</div>
                    <div class="text-brand-muted" style="font-size: 0.72rem;">{{ $n->presentationAttempt->researchGroup->leader()?->full_name ?? '—' }}</div>
                    @php $expected = $n->expected_start_at ?? $n->planned_start_at; @endphp
                    @if ($expected)
                        <div class="text-brand-muted" style="font-size: 0.68rem;">Expected {{ $expected->format('g:i A') }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="small text-brand-muted">—</div>
    @endif
</div>
