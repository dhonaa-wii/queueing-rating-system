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

<div class="mb-2 p-3 rounded-3 sched-stats" style="background-color: var(--brand-surface-alt);">
    <div>
        <div class="sched-stat-label">Start</div>
        <div class="sched-stat-value">{{ $live['startTime'] ? $live['startTime']->format('M j · g:i A') : '—' }}</div>
    </div>
    <div>
        <div class="sched-stat-label">End</div>
        <div class="sched-stat-value">{{ $live['endTime'] ? $live['endTime']->format('M j · g:i A') : '—' }}</div>
    </div>
    <div>
        <div class="sched-stat-label">Duration / Group</div>
        <div class="sched-stat-value">{{ $live['durationMinutesPerGroup'] ? $live['durationMinutesPerGroup'] . ' min' : '—' }}</div>
    </div>
    <div>
        <div class="sched-stat-label">Registered Groups</div>
        <div class="sched-stat-value">{{ $live['dayStats']['registeredInCategory'] }}</div>
    </div>
    <div>
        <div class="sched-stat-label">Scheduled</div>
        <div class="sched-stat-value">{{ $live['dayStats']['scheduled'] }}</div>
    </div>
    <div>
        <div class="sched-stat-label">Completed</div>
        <div class="sched-stat-value">{{ $live['dayStats']['completed'] }}</div>
    </div>
</div>

@if ($live['started'] ?? false)
    <div class="mb-2 p-3 rounded-3" style="background-color: var(--brand-surface-alt); font-size: 0.82rem;">
        <div class="sched-stat-label mb-1">Active Panelist</div>
        @forelse ($live['activePanelists'] as $connection)
            @include('partials.schedule.active-panelist-badge', ['connection' => $connection])
        @empty
            <span class="text-brand-muted d-block">No panelist currently logged in.</span>
        @endforelse
    </div>
@endif
