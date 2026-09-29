@php
    // Current Group / Last Group status display — shown on every terminal
    // (2026-08-21, user-directed correction: this used to live only inside
    // presentation-control.blade.php, which is Lead-only, so Terminal 2/3
    // never saw it even in the exact same "nothing called yet" state
    // Terminal 1 shows its last-resolved-group info in. The only thing that
    // should differ between Lead and non-Lead terminals is the actual
    // control buttons, not this display. Included from home.blade.php's
    // #control-panel — the SAME mount point presentation-control.blade.php
    // uses, not control-info-card.blade.php — so it follows #control-panel's
    // own column switch (left sidebar once a panel connects, right card
    // while unclaimed) instead of always sitting in the right/stats card
    // regardless of connection state (a first cut got this wrong and was
    // corrected same-day).
    $attemptStatusCode = $attempt?->presentationStatus?->code;

    // Waiting timer counts DOWN from the category's configured
    // called_waiting_minutes (waiting_deadline_at, set at Call Next —
    // see PresentationControlService::callNext()) to 00:00, rather than
    // counting up elapsed time — user-directed 2026-08-22. Once it
    // reaches zero, $attemptWaitingOverdue drives the Defer-or-keep-
    // waiting recommendation below.
    $attemptWaitingRemainingSeconds = ($attemptStatusCode === 'CALLED' && $run?->waiting_deadline_at)
        ? $run->waiting_deadline_at->getTimestamp() - now()->getTimestamp()
        : null;
    $attemptWaitingOverdue = $attemptWaitingRemainingSeconds !== null && $attemptWaitingRemainingSeconds <= 0;

    // The ticking presentation timer — user-directed 2026-08-22: moved out
    // of control-info-next.blade.php's "Next" header (where it displayed
    // the CURRENT run's timer in a confusing spot) onto this same Current
    // Group line, right side, next to the status badge. One badge, one
    // slot, one format/size throughout — while CALLED it counts up "waiting
    // since called_at" (previously a separate, non-ticking badge further
    // down this same card); the instant the run actually starts, the exact
    // same element switches to the pre-existing ticking duration/countdown
    // (unchanged logic, see home.blade.php's tickTimers()). $timerMode
    // tells the client which behavior to run; $timerIsBig mirrors
    // tickTimers()'s own "big only once actually running" rule, so a
    // waiting countdown (no started_at yet) never renders big even before
    // JS ticks once.
    $timerMode = $attemptStatusCode === 'CALLED' ? 'waiting' : 'duration';
    $timerIsBig = $connection && $run?->started_at && $run->timerStatus?->code !== 'NOT_STARTED';
@endphp

@foreach ($sessionNotices ?? [] as $sessionNotice)
    <div class="alert alert-warning py-2 px-2 mb-2" role="alert" style="font-size: 0.72rem;">
        <div class="fw-semibold">{{ $sessionNotice->message }}</div>
        <div class="text-brand-muted" style="font-size: 0.64rem;">{{ $sessionNotice->created_at->format('g:i A') }}</div>
    </div>
@endforeach

{{-- The room is inside a scheduled break with nobody on stage — every terminal
     sees it, not just the Lead (whose controls are locked until it ends). --}}
@if ($onBreak ?? null)
    <div class="break-banner mb-2 p-2 rounded-3" role="status">
        <div class="fw-semibold">Room on break</div>
        <div style="font-size: 0.7rem;">Until {{ $onBreak->planned_end_at->format('g:i A') }}</div>
    </div>
@endif

@if ($attempt)
    <div class="mb-2 p-2 rounded-3" style="background-color: var(--brand-surface-alt); font-size: 0.78rem;">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
            <div>
                <div class="text-brand-muted mb-1" style="font-size: 0.66rem;">Current Group</div>
                <div class="fw-semibold">{{ $attempt->researchGroup->group_reference }}</div>
                @if ($attempt->researchGroup->current_project_title)
                    <div class="text-brand-muted">{{ $attempt->researchGroup->current_project_title }}</div>
                @endif
                <div class="text-brand-muted">Leader: {{ $attempt->researchGroup->leader()?->full_name ?? '—' }}</div>
            </div>
            <div class="d-flex flex-column align-items-end gap-1">
                <span class="badge badge-brand-tint" style="font-size: 0.66rem;">{{ $attempt->presentationStatus->name }}</span>
                @if ($run)
                    <span class="badge badge-muted-tint {{ $timerIsBig ? 'timer-badge-lg' : 'timer-badge-sm' }}" data-timer-badge
                          data-timer-mode="{{ $timerMode }}"
                          data-timer-status="{{ $run->timerStatus?->code }}"
                          data-timer-connected="{{ $connection ? '1' : '0' }}"
                          data-called-at="{{ $run->called_at?->toIso8601String() }}"
                          data-waiting-deadline-at="{{ $run->waiting_deadline_at?->toIso8601String() }}"
                          data-started-at="{{ $run->started_at?->toIso8601String() }}"
                          data-configured-duration="{{ $run->configured_duration_seconds }}"
                          data-total-paused="{{ $run->total_paused_seconds }}"
                          data-last-action-at="{{ $run->last_action_at?->toIso8601String() }}"
                         >
                        @if ($timerMode === 'waiting' && $attemptWaitingRemainingSeconds !== null)
                            @php $attemptWaitingDisplaySeconds = max(0, $attemptWaitingRemainingSeconds); @endphp
                            Waiting {{ intdiv($attemptWaitingDisplaySeconds, 60) }}:{{ str_pad($attemptWaitingDisplaySeconds % 60, 2, '0', STR_PAD_LEFT) }}
                        @else
                            {{ $run->timerStatus?->name ?? 'Not Started' }}
                        @endif
                    </span>
                @endif
            </div>
        </div>

        {{-- Directly under the timer, on every terminal: this group runs into the
             room's scheduled break. Blinks so it can't be missed from across the
             room. --}}
        @if ($upcomingBreak ?? null)
            <div class="break-warning mt-2" role="alert">Scheduled break at {{ $upcomingBreak->planned_start_at->format('g:i A') }}</div>
        @endif

        {{-- Text only — no buttons (user-directed 2026-08-22, "keep waiting"
        button removed as useless — the Lead already has a real Defer This
        Group action on the Presentation Controls panel
        (presentation-control.blade.php); this line is just the plain
        recommendation, universal across all terminals). --}}
        @if ($timerMode === 'waiting' && $run)
            <div class="alert alert-danger py-2 px-2 mt-2 mb-0 {{ $attemptWaitingOverdue ? '' : 'd-none' }}"
                 data-waiting-recommendation
                 style="background-color: var(--brand-danger-tint); border-color: var(--brand-danger); color: var(--brand-danger); font-size: 0.72rem;">
                <div class="fw-semibold">Waiting time exceeded — defer group or keep waiting</div>
            </div>
        @endif

        @if ($run?->started_at)
            <div class="mt-2" style="font-size: 0.7rem;">
                <div class="text-brand-muted" style="font-size: 0.6rem;">Started</div>
                <div class="fw-semibold">{{ $run->started_at->format('g:i A') }}</div>
            </div>
        @endif

        @if ($paymentRequired ?? false)
            <div class="mt-2 d-flex align-items-center gap-2">
                <span class="text-brand-muted">Payment:</span>
                <span class="badge {{ $paymentSummary['badgeClass'] ?? 'badge-muted-tint' }}" style="font-size: 0.64rem;">{{ $paymentSummary['label'] ?? 'Not Checked' }}</span>
            </div>
        @endif
    </div>
@elseif ($lastOutcome ?? null)
    {{-- While nothing's called yet, this slot shows the room's last
    resolved group instead of going blank — it flips to "Current Group"
    above the instant a group is called, then ongoing/paused, per the
    normal flow (user-directed 2026-08-21). --}}
    @php
        $lastAttempt = $lastOutcome['attempt'];
        $lastOutcomeBadgeClass = match ($lastAttempt->presentationStatus->code) {
            'COMPLETED' => 'badge-success-tint',
            'DEFERRED' => 'badge-danger-tint',
            default => 'badge-muted-tint',
        };
    @endphp
    <div class="mb-2 p-2 rounded-3" style="background-color: var(--brand-surface-alt); font-size: 0.78rem;">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
            <div>
                <div class="text-brand-muted mb-1" style="font-size: 0.66rem;">Last Group</div>
                <div class="fw-semibold">{{ $lastAttempt->researchGroup->group_reference }}</div>
                @if ($lastAttempt->researchGroup->current_project_title)
                    <div class="text-brand-muted">{{ $lastAttempt->researchGroup->current_project_title }}</div>
                @endif
                <div class="text-brand-muted">Leader: {{ $lastAttempt->researchGroup->leader()?->full_name ?? '—' }}</div>
            </div>
            <span class="badge {{ $lastOutcomeBadgeClass }}" style="font-size: 0.66rem;">{{ $lastAttempt->presentationStatus->name }}</span>
        </div>

        <div class="d-flex flex-wrap gap-3 mt-2" style="font-size: 0.7rem;">
            @if ($lastOutcome['deferredAt'])
                <div>
                    <div class="text-brand-muted" style="font-size: 0.6rem;">Deferred At</div>
                    <div class="fw-semibold">{{ $lastOutcome['deferredAt']->format('g:i A') }}</div>
                </div>
            @else
                <div>
                    <div class="text-brand-muted" style="font-size: 0.6rem;">Started</div>
                    <div class="fw-semibold">{{ $lastOutcome['startedAt']?->format('g:i A') ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-brand-muted" style="font-size: 0.6rem;">Ended</div>
                    <div class="fw-semibold">{{ $lastOutcome['completedAt']?->format('g:i A') ?? '—' }}</div>
                </div>
            @endif
        </div>

        @if ($lastOutcome['deferReason'] || $lastOutcome['deferRemarks'])
            <div class="mt-2" style="font-size: 0.7rem;">
                @if ($lastOutcome['deferReason'])
                    <div class="text-brand-muted">Reason: {{ $lastOutcome['deferReason']->name }}</div>
                @endif
                @if ($lastOutcome['deferRemarks'])
                    <div class="text-brand-muted">{{ $lastOutcome['deferRemarks'] }}</div>
                @endif
            </div>
        @endif
    </div>
@endif
