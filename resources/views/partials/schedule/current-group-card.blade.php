{{--
    Current/Last Group status display for one room — a read-only fork of
    room-session/partials/attempt-status-card.blade.php, kept as its own
    file (per user direction 2026-08-22) so this public page's version can
    be restyled without touching the live room-session tablet's. No
    Complete/Defer buttons here (those are Presentation Control actions,
    not something a public schedule page ever offers). Expects $room, same
    as status-card.blade.php.

    The timer badge itself genuinely ticks client-side (user-directed
    2026-08-22, same day: an earlier cut rendered a static PHP snapshot,
    correct only as of page load/reload) — same data-timer-badge/data-*
    attribute contract as the room-session tablet's, ticked by this page's
    own small script (schedule-body.blade.php's @push('scripts')) rather
    than the tablet's tickTimers(), so the two can keep changing
    independently. Unlike the tablet, this page has no 5s poll refreshing
    the underlying data (see next-preview-card.blade.php's own note) — only
    the displayed clock genuinely runs; who's called/started still only
    updates on reload/re-search.
--}}
@php
    $live = $room->live;
    $attempt = $live['attempt'];
    $run = $live['run'];
    $lastOutcome = $live['lastOutcome'];
    $paymentRequired = $live['paymentRequired'];
    $paymentSummary = $live['paymentSummary'];

    $attemptStatusCode = $attempt?->presentationStatus?->code;
    $timerMode = match (true) {
        $attemptStatusCode === 'CALLED' => 'waiting',
        in_array($attemptStatusCode, ['ONGOING', 'PAUSED'], true) => 'duration',
        default => null,
    };

    // Server-rendered fallback (same "m:ss" shape the page's own tick
    // script produces) — only visible for the instant before that script's
    // first tick runs on load.
    $formatDuration = function (float $totalSeconds): string {
        $abs = (int) round(abs($totalSeconds));

        return intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT);
    };

    $timerBadge = null;

    if ($timerMode === 'waiting' && $run?->waiting_deadline_at) {
        // Counts DOWN to 00:00 from the deadline (called_at + the
        // category's configured called_waiting_minutes), matching the
        // room-session tablet's own timer — user-directed 2026-08-22.
        $remainingSeconds = $run->waiting_deadline_at->getTimestamp() - now()->getTimestamp();
        $overdue = $remainingSeconds <= 0;

        $timerBadge = [
            'text' => 'Waiting ' . $formatDuration(max(0, $remainingSeconds)),
            'class' => $overdue ? 'badge-danger-tint' : 'badge-muted-tint',
        ];
    } elseif ($timerMode === 'duration' && $run?->started_at) {
        $configured = $run->configured_duration_seconds ?? 0;
        $totalPaused = $run->total_paused_seconds ?? 0;
        $referenceTime = ($attemptStatusCode === 'PAUSED' && $run->last_action_at) ? $run->last_action_at : now();
        $elapsedSeconds = $referenceTime->getTimestamp() - $run->started_at->getTimestamp() - $totalPaused;
        $remaining = $configured - $elapsedSeconds;

        if ($attemptStatusCode === 'PAUSED') {
            $timerBadge = ['text' => 'Paused — ' . $formatDuration($remaining), 'class' => 'badge-danger-tint'];
        } elseif ($remaining >= 0) {
            $timerBadge = ['text' => $formatDuration($remaining), 'class' => 'badge-success-tint'];
        } else {
            $timerBadge = ['text' => 'Extended +' . $formatDuration($remaining), 'class' => 'badge-danger-tint'];
        }
    } elseif ($timerMode === 'duration') {
        $timerBadge = ['text' => 'Not Started', 'class' => 'badge-muted-tint'];
    }
@endphp

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
                @if ($timerMode && $run)
                    <span class="badge {{ $timerBadge['class'] ?? 'badge-muted-tint' }}" style="font-size: 0.66rem;" data-timer-badge
                          data-timer-mode="{{ $timerMode }}"
                          data-timer-status="{{ $run->timerStatus?->code }}"
                          data-called-at="{{ $run->called_at?->toIso8601String() }}"
                          data-waiting-deadline-at="{{ $run->waiting_deadline_at?->toIso8601String() }}"
                          data-started-at="{{ $run->started_at?->toIso8601String() }}"
                          data-configured-duration="{{ $run->configured_duration_seconds }}"
                          data-total-paused="{{ $run->total_paused_seconds }}"
                          data-last-action-at="{{ $run->last_action_at?->toIso8601String() }}"
                         >{{ $timerBadge['text'] ?? '' }}</span>
                @endif
            </div>
        </div>

        @if ($run?->started_at)
            <div class="d-flex align-items-center flex-wrap gap-3 mt-2" style="font-size: 0.7rem;">
                <div>
                    <div class="text-brand-muted" style="font-size: 0.6rem;">Started</div>
                    <div class="fw-semibold">{{ $run->started_at->format('g:i A') }}</div>
                </div>
            </div>
        @endif

        @if ($paymentRequired)
            <div class="mt-2 d-flex align-items-center gap-2">
                <span class="text-brand-muted">Payment:</span>
                <span class="badge {{ $paymentSummary['badgeClass'] ?? 'badge-muted-tint' }}" style="font-size: 0.64rem;">{{ $paymentSummary['label'] ?? 'Not Checked' }}</span>
            </div>
        @endif
    </div>
@elseif ($lastOutcome)
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
@else
    <div class="mb-2 p-2 rounded-3 small text-brand-muted" style="background-color: var(--brand-surface-alt);">No group has been called in this room yet.</div>
@endif
