@php
    // Top section of the right-column card — stats + panelist strip, shown
    // to every terminal (user-directed 2026-08-18). The "Presentation
    // Control" title only renders here while nobody's connected, when this
    // card also hosts the (Lead-only) control buttons right after it; once
    // a panelist connects, the buttons (and their own title) move into the
    // left sidebar instead — see presentation-control.blade.php's
    // $showHeader and home.blade.php's layout (user-provided wireframe,
    // 2026-08-21) — so dropping the title here keeps this card short and
    // leaves the evaluation form more room. The "Next" preview that used to
    // close out this same card now lives in its own partial,
    // control-info-next.blade.php, so it can render after the buttons
    // without losing its independent poll refresh.
    $statusCode = $attempt?->presentationStatus?->code;

    $currentSchedule = match ($statusCode) {
        'CALLED' => $queue['called'] ?? null,
        'ONGOING', 'PAUSED' => $queue['current'] ?? null,
        default => null,
    };

    // Which group's panel to show: whoever's called/presenting right now,
    // or — if nothing's been called yet — the group that's up next, so
    // this answers "who's the next panel to call" even before Call Next is
    // pressed.
    $panelSourceSchedule = $currentSchedule ?? collect($queue['upcoming'] ?? [])->first();
    $panelLabel = match (true) {
        in_array($statusCode, ['CALLED', 'ONGOING', 'PAUSED'], true) => 'Panelist',
        default => 'Panelist (Next to Call)',
    };

    $assignedPanel = $panelSourceSchedule?->presentationAttempt->attemptPanelAssignments->where('assignmentKind.code', 'ASSIGNED_PANELIST') ?? collect();
    $backupPanel = $panelSourceSchedule?->presentationAttempt->attemptPanelAssignments->where('assignmentKind.code', 'BACKUP_PANELIST') ?? collect();

    // Duration/group is set once by the Admin for the whole room/day, not
    // per group.
    $durationMinutesPerGroup = $room->presentationDate?->category?->categoryScheduleSetting?->duration_minutes;
    $plannedEndTime = $room->room_end_time ?? $room->presentationDate?->event_end_time;

@endphp

@unless ($connection)
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <h2 class="mb-0" style="font-size: 0.85rem;">Presentation Control</h2>
        @include('admin.live-monitoring.partials.room-session-status-badge', ['status' => $session->roomSessionStatus])
    </div>
@endunless

<div class="mb-2 p-2 rounded-3 d-flex flex-wrap gap-3" style="background-color: var(--brand-surface-alt); font-size: 0.7rem;">
    <div>
        <div class="text-brand-muted" style="font-size: 0.6rem;">Start</div>
        <div class="fw-semibold">{{ $room->startTime()?->format('g:i A') ?? '—' }}</div>
    </div>
    <div>
        <div class="text-brand-muted" style="font-size: 0.6rem;">Planned End</div>
        <div class="fw-semibold">{{ $plannedEndTime ? \Carbon\Carbon::parse($plannedEndTime)->format('g:i A') : '—' }}</div>
    </div>
    <div>
        <div class="text-brand-muted" style="font-size: 0.6rem;">Duration / Group</div>
        <div class="fw-semibold">{{ $durationMinutesPerGroup ? $durationMinutesPerGroup . ' min' : '—' }}</div>
    </div>
    <div>
        <div class="text-brand-muted" style="font-size: 0.6rem;">Registered in Category</div>
        <div class="fw-semibold">{{ $dayStats['registeredInCategory'] }}</div>
    </div>
    <div>
        <div class="text-brand-muted" style="font-size: 0.6rem;">Scheduled Today</div>
        <div class="fw-semibold">{{ $dayStats['scheduledToday'] }}</div>
    </div>
    <div>
        <div class="text-brand-muted" style="font-size: 0.6rem;">Completed Today</div>
        <div class="fw-semibold">{{ $dayStats['completedToday'] }}</div>
    </div>
</div>

@if ($panelSourceSchedule)
    <div class="mb-2 p-2 rounded-3" style="background-color: var(--brand-surface-alt); font-size: 0.7rem;">
        <div class="text-brand-muted mb-1" style="font-size: 0.62rem;">{{ $panelLabel }}</div>
        @forelse ($assignedPanel as $pa)
            @include('room-session.partials.panelist-badge', ['assignment' => $pa, 'connectedPanelistIds' => $connectedPanelistIds])
        @empty
            <span class="text-brand-muted d-block">No panel assigned</span>
        @endforelse
        @foreach ($backupPanel as $pb)
            @include('room-session.partials.panelist-badge', ['assignment' => $pb, 'isBackup' => true, 'connectedPanelistIds' => $connectedPanelistIds])
        @endforeach
    </div>
@endif
