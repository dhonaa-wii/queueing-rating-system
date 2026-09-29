{{--
    Top section of the schedule page's right-column card for one room —
    a read-only fork of room-session/partials/control-info-card.blade.php
    (the "Presentation Control" stats + panelist strip Terminal 2/3 show).
    Kept as a separate file rather than an @include of that partial so the
    live tablet card and this public/no-write one can be restyled
    independently, per user direction (2026-08-22). Expects $room (one
    entry from CategoryScheduleViewService::build()'s $rooms collection,
    carrying a ->live array from buildLiveStatus()).
--}}
@php
    $live = $room->live;
    $session = $live['session'];
@endphp

<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <h2 class="h6 mb-0">{{ $room->name }}</h2>
    @if ($session)
        @include('admin.live-monitoring.partials.room-session-status-badge', ['status' => $session->roomSessionStatus])
    @else
        <span class="badge badge-muted-tint">Not started</span>
    @endif
</div>

<div class="mb-2 p-2 rounded-3 d-flex flex-wrap gap-3" style="background-color: var(--brand-surface-alt); font-size: 0.7rem;">
    <div>
        <div class="text-brand-muted" style="font-size: 0.6rem;">Start</div>
        <div class="fw-semibold">{{ $live['startTime'] ? $live['startTime']->format('M j · g:i A') : '—' }}</div>
    </div>
    <div>
        <div class="text-brand-muted" style="font-size: 0.6rem;">Planned End</div>
        <div class="fw-semibold">{{ $live['plannedEndTime'] ? \Illuminate\Support\Carbon::parse($live['plannedEndTime'])->format('g:i A') : '—' }}</div>
    </div>
    <div>
        <div class="text-brand-muted" style="font-size: 0.6rem;">Duration / Group</div>
        <div class="fw-semibold">{{ $live['durationMinutesPerGroup'] ? $live['durationMinutesPerGroup'] . ' min' : '—' }}</div>
    </div>
    <div>
        <div class="text-brand-muted" style="font-size: 0.6rem;">Registered in Category</div>
        <div class="fw-semibold">{{ $live['dayStats']['registeredInCategory'] }}</div>
    </div>
    <div>
        <div class="text-brand-muted" style="font-size: 0.6rem;">Scheduled</div>
        <div class="fw-semibold">{{ $live['dayStats']['scheduled'] }}</div>
    </div>
    <div>
        <div class="text-brand-muted" style="font-size: 0.6rem;">Completed</div>
        <div class="fw-semibold">{{ $live['dayStats']['completed'] }}</div>
    </div>
</div>

<div class="mb-2 p-2 rounded-3" style="background-color: var(--brand-surface-alt); font-size: 0.7rem;">
    <div class="text-brand-muted mb-1" style="font-size: 0.62rem;">Active Panelist</div>
    @forelse ($live['activePanelists'] as $connection)
        @include('partials.schedule.active-panelist-badge', ['connection' => $connection])
    @empty
        <span class="text-brand-muted d-block">No panelist currently logged in.</span>
    @endforelse
</div>
