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

<div class="p-2 rounded-3 flex-grow-1" style="background-color: var(--brand-surface-alt); font-size: 0.7rem;">
    <div class="text-brand-muted mb-2" style="font-size: 0.72rem;">Next</div>

    @if ($nextThree->isNotEmpty())
        <div class="sched-next-grid">
            @foreach ($nextThree as $n)
                @php
                    $nextGroup = $n->presentationAttempt->researchGroup;
                    $nextMembers = $nextGroup->students->where('is_leader', false)->pluck('full_name')->filter();
                    $expected = $n->expected_start_at ?? $n->planned_start_at;
                @endphp
                <div class="small p-2 rounded-3 {{ $n->isPastSessionEnd() ? 'past-session-row' : '' }}" style="background-color: var(--brand-surface);">
                    <div class="d-flex justify-content-between align-items-baseline gap-2 flex-wrap">
                        <span class="fw-semibold">{{ $nextGroup->group_reference }}</span>
                        @if ($expected)
                            <span class="text-brand-muted" style="font-size: 0.76rem;">Expected {{ $expected->format('g:i A') }}</span>
                        @endif
                    </div>
                    <dl class="sched-next-people mb-0 mt-1">
                        <dt>Leader</dt>
                        <dd>{{ $nextGroup->leader()?->full_name ?? '—' }}</dd>
                        <dt>Members</dt>
                        <dd>
                            @forelse ($nextMembers as $memberName)
                                <div>{{ $memberName }}</div>
                            @empty
                                —
                            @endforelse
                        </dd>
                        <dt>Adviser</dt>
                        <dd>{{ $nextGroup->technical_adviser_name ?: '—' }}</dd>
                    </dl>
                    @if ($n->isPastSessionEnd())
                        <div class="text-brand-danger fw-semibold mt-1" style="font-size: 0.7rem;">
                            {{ ($scheduleRouteName ?? null) === 'student.categories.schedule' ? \App\Models\AttemptSchedule::PAST_SESSION_SHORT_STUDENT : \App\Models\AttemptSchedule::PAST_SESSION_SHORT }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="small text-brand-muted">—</div>
    @endif
</div>
