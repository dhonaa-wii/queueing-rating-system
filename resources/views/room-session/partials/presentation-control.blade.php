@php
    // The Current/Last Group status display itself now lives in
    // attempt-status-card.blade.php, included universally from
    // control-info-card.blade.php (2026-08-21) so Terminal 2/3 see it too —
    // this partial only needs statusCode/paymentVerified below to decide
    // which buttons to show.
    $statusCode = $attempt?->presentationStatus?->code;
    $paymentSatisfied = $paymentSummary['allSatisfied'] ?? true;

    // Every control below is locked while the room is on a break; only End
    // Break is live (RoomBreakService, server-side in PresentationControlService).
    $breakLocked = ($onBreak ?? null) !== null;
    $breakLockedTitle = 'The room is on break';
@endphp

@if ($showHeader ?? false)
    <h2 class="mb-2" style="font-size: 0.85rem;">Presentation Controls</h2>
@endif

    {{-- This whole partial only ever renders while $canControlFlow is true
    (see home.blade.php's @if($canControlFlow) / status()'s controlHtml),
    which as of 2026-09-07 requires a real connected panelist who is this
    room's Admin-designated Lead/Chair (TerminalConnectionService::
    isLead()) — no terminal number carries special meaning anymore, so
    $connection is always present here, including for Call Next below.

    User-directed 2026-09-07: End Room Session removed — a room's session
    is now only ever closed via the Admin's End Event action
    (EventActivationService::end(), Live Monitoring), not from the tablet. --}}
    @if ($breakLocked)
        <div class="control-btn-grid mb-2">
            <form method="POST" action="{{ route('room-session.break.end') }}">
                @csrf
                <button type="submit" class="btn btn-brand control-btn control-btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"></polygon><line x1="19" y1="5" x2="19" y2="19"></line></svg>
                    End Break
                </button>
            </form>
        </div>
    @endif

    @if ($breakDecision ?? null)
        <button type="button" class="btn btn-outline-danger-brand control-btn mb-2" data-bs-toggle="modal" data-bs-target="#break-decision-modal">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            Scheduled Break
        </button>

        {{-- Opened automatically the first time it renders for a given break
             (home.blade.php's data-auto-show handler); this button reopens it
             if the Lead closes it without choosing. Lives in #control-panel,
             so home.blade.php relocates it to <body> like the other modals. --}}
        <div class="modal fade" id="break-decision-modal" tabindex="-1" aria-labelledby="break-decision-label" aria-hidden="true" data-auto-show="{{ $breakDecision->id }}">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="break-decision-label">Scheduled break at {{ $breakDecision->planned_start_at->format('g:i A') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">{{ $attempt?->researchGroup?->group_reference }} is still presenting. The break runs until {{ $breakDecision->planned_end_at->format('g:i A') }}.</p>
                    </div>
                    <div class="modal-footer">
                        <form method="POST" action="{{ route('room-session.break.cancel') }}">
                            @csrf
                            <input type="hidden" name="break_id" value="{{ $breakDecision->id }}">
                            <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="x" /> Cancel Break</button>
                        </form>
                        <form method="POST" action="{{ route('room-session.break.keep') }}">
                            @csrf
                            <input type="hidden" name="break_id" value="{{ $breakDecision->id }}">
                            <button type="submit" class="btn btn-brand"><x-icon name="clock" /> Finish Group, Then Break</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="control-btn-grid mb-2">
        <form method="POST" action="{{ route('room-session.call-next') }}">
            @csrf
            <button type="submit" class="btn btn-brand control-btn control-btn-primary"
                @disabled(! $hasNextInQueue || ! $allTerminalsActive || $breakLocked)
                title="{{ $breakLocked ? $breakLockedTitle : (! $allTerminalsActive ? 'All terminals in this room must be connected first' : '') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"></path><path d="M22 2 15 22l-4-9-9-4 20-7Z"></path></svg>
                Call Next
            </button>
        </form>
    </div>

    @if ($connection)
        <div class="control-btn-grid mb-2">
            @if ($statusCode === 'CALLED')
                @php
                    // User-directed 2026-08-21: Start is also blocked while
                    // any enabled terminal has an empty panelist seat, same
                    // "every enabled terminal" shape as $allTerminalsActive
                    // above but requiring an actual connection, not just a
                    // claimed device — enforced server-side in
                    // PresentationControlService::start(), mirrored here to
                    // pre-emptively disable the button. Payment takes
                    // priority when both are blocking, matching this
                    // button's pre-existing check order.
                    $startBlockedReason = match (true) {
                        $breakLocked => $breakLockedTitle,
                        $paymentRequired && ! $paymentSatisfied => 'Verify payment before starting',
                        ! $allTerminalsStaffed => 'All panelists must be connected before starting',
                        // User-directed 2026-09-13 — the room's required panel
                        // size changed after this group was assigned; an Admin
                        // has to reassign before it can present.
                        ($panelRequirementIssue ?? null) !== null => $panelRequirementIssue,
                        default => null,
                    };
                @endphp
                <form method="POST" action="{{ route('room-session.start') }}">
                    @csrf
                    <button type="submit" class="btn btn-success-brand control-btn control-btn-primary"
                        @disabled($startBlockedReason !== null)
                        title="{{ $startBlockedReason ?? '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                        Start
                    </button>
                </form>
            @endif

            @if ($statusCode === 'ONGOING')
                <form method="POST" action="{{ route('room-session.pause') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger-brand control-btn control-btn-primary" @disabled($breakLocked) title="{{ $breakLocked ? $breakLockedTitle : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg>
                        Pause
                    </button>
                </form>
                {{-- Deliberately NOT named *-form (unlike defer-form
                below) — this form is always present and
                never toggled via .d-none, so it would permanently false-
                match home.blade.php's controlFormOpen() selector
                (form[id$="-form"]:not(.d-none)), which exists to detect
                those toggleable inline forms specifically. --}}
                <form method="POST" action="{{ route('room-session.complete') }}" id="complete-submit">
                    @csrf
                </form>
                <button type="button" class="btn btn-success-brand control-btn control-btn-primary"
                    @disabled(! $allEvaluationsSubmitted)
                    data-bs-toggle="modal" data-bs-target="#complete-modal"
                    title="{{ ! $allEvaluationsSubmitted ? 'All panelists must submit their evaluations first' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                    Complete
                </button>

                {{-- Bootstrap modal (2026-08-21, was a native confirm()
                dialog — same conversion as the evaluation submit
                confirmation) — lives inside #control-panel, which gets
                replaced wholesale on every 5s poll; see home.blade.php's
                controlFormOpen() guard below, extended to also recognize
                an open modal here so it isn't yanked away mid-confirm. --}}
                <div class="modal fade" id="complete-modal" tabindex="-1" aria-labelledby="complete-modal-label" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="complete-modal-label">Complete Presentation</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0">Mark this presentation as complete?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" form="complete-submit" class="btn btn-success-brand"><x-icon name="check" /> Complete</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($statusCode === 'PAUSED')
                <form method="POST" action="{{ route('room-session.resume') }}">
                    @csrf
                    <button type="submit" class="btn btn-brand control-btn control-btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                        Resume
                    </button>
                </form>
            @endif
        </div>

        @if ($paymentRequired && $statusCode === 'CALLED')
            <div class="mb-2 p-2 rounded-3" style="background-color: var(--brand-surface-alt);">
                @foreach ($paymentTypes as $type)
                    @php
                        $typeRow = collect($paymentSummary['types'] ?? [])->first(fn ($row) => $row['type']->id === $type->id);
                        $typeSatisfied = $typeRow['satisfied'] ?? false;
                    @endphp
                    <form method="POST" action="{{ route('room-session.verify-payment') }}" class="d-flex align-items-center gap-2 {{ $loop->last ? '' : 'mb-2' }}">
                        @csrf
                        <input type="hidden" name="category_payment_type_id" value="{{ $type->id }}">
                        <span class="small text-truncate" style="width: 7rem; flex-shrink: 0;" title="{{ $type->name }}">{{ $type->name }}</span>
                        <input type="text" name="reference_number" class="form-control form-control-sm" placeholder="Receipt #" maxlength="100"
                               data-reference-number-input @disabled($typeSatisfied || $breakLocked) @required(! $typeSatisfied)>
                        <button type="submit" class="btn btn-sm {{ $typeSatisfied ? 'btn-success-brand' : 'btn-outline-success-brand' }} flex-shrink-0" @disabled($typeSatisfied || $breakLocked)>
                            @if ($typeSatisfied)
                                <x-icon name="check" /> Verified
                            @else
                                Verify
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
        @endif

        @if ($attempt && in_array($statusCode, ['CALLED', 'ONGOING', 'PAUSED'], true))
            <button type="button" class="btn btn-outline-danger-brand control-btn mb-2" data-toggle-target="defer-form"
                @disabled(($hasSubmittedEvaluation ?? false) || $breakLocked)
                title="{{ $breakLocked ? $breakLockedTitle : (($hasSubmittedEvaluation ?? false) ? 'A panelist has already submitted an evaluation for this group — it can no longer be deferred.' : '') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 7-7 7 7"></path><path d="M12 5v14"></path></svg>
                Defer This Group
            </button>
            <form method="POST" action="{{ route('room-session.defer') }}" id="defer-form" class="d-none mb-3 p-2 rounded-3" style="background-color: var(--brand-surface-alt);">
                @csrf
                <label class="form-label small">Reason</label>
                <select name="reason_id" class="form-select form-select-sm mb-2" required>
                    <option value="">Select a reason&hellip;</option>
                    @foreach ($adjustmentReasons as $reason)
                        <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                    @endforeach
                </select>
                <textarea name="remarks" class="form-control form-control-sm mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-outline-danger-brand btn-sm"><x-icon name="defer" /> Confirm Defer</button>
                    <button type="button" class="btn btn-outline-brand btn-sm" data-toggle-target="defer-form">Cancel</button>
                </div>
            </form>
        @endif
    @endif
