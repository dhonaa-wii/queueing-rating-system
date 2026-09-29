@extends('layouts.app')

@section('title', 'Terminal ' . $terminal->terminal_number . ' — ' . $room->room_name)

@section('container-class', 'container-fluid px-2 px-md-3 px-lg-4')

@section('navbar-inner')
    <div class="d-flex align-items-center gap-2">
        <span class="fw-semibold small d-none d-sm-inline">Academic Research Presentation Queueing and Rating System</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="small text-brand-muted text-end">
            {{ $room->room_name }} &middot; Terminal {{ $terminal->terminal_number }}
        </div>
        <button type="button" class="settings-icon-btn" data-bs-toggle="modal" data-bs-target="#room-session-settings-modal" aria-label="Settings">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        </button>
    </div>
@endsection

@section('content')
    @include('partials.toast-stack')

    {{-- Layout is connection-state-dependent (user-provided wireframes,
    2026-08-21). While nobody's connected: left column is just the QR/login
    card; the right column's card carries the "Presentation Control" title
    + stats + panelist + Current/Last Group status + (Lead-only) buttons +
    Next preview, all merged into one visual card. Once a panelist
    connects: the connection card and the Current/Last Group status +
    (Lead-only) control buttons merge into ONE left-column sidebar instead
    (no "Presentation Control" title on the right anymore — see
    control-info-card.blade.php — so that card stays short and the
    evaluation form gets more vertical room). #control-panel is that one
    shared mount point — its content (attempt-status-card.blade.php, plus
    presentation-control.blade.php when canControlFlow) is identical either
    way; only *which column* it's nested in changes, driven by $connection.
    This distinction was user-corrected 2026-08-21: an earlier pass
    mistakenly rendered attempt-status-card unconditionally inside
    control-info-card.blade.php (the right/stats card), which put it on the
    wrong side once a panel connects — it belongs in #control-panel so it
    follows the same column switch as everything else in this state split,
    not a separate always-right placement. Because #control-panel's parent
    element differs between the two states, the client does a plain
    window.location.reload() at the exact moment `connected` flips (see
    poll() below) rather than relocating the DOM node across columns.

    User-directed 2026-08-18: the whole right column pins to the viewport
    (position: sticky + capped height) with only the evaluation form
    scrolling inside its own box — everything else (session info, queue
    preview, timer) stays "fixed" in view the same way the left column
    already does. The View All overlay is deliberately excluded from this
    — it's a fixed-position slide-in panel (queue-panel.blade.php), so its
    own placement inside #queue-panel doesn't constrain it.

    User-corrected 2026-08-21: that sticky/capped-height treatment on the
    right column is now unclaimed-state only. Once a panel is connected,
    the right column (#control-card + #queue-panel + #evaluation-panel)
    drops sticky/max-height/its own overflow-y entirely and just scrolls
    with the page like normal content — the evaluation form is real,
    often-tall content a panelist needs to scroll through, not something
    that should be squeezed into a capped internal scrollbox. Only the
    LEFT sidebar (#connection-panel's wrapper) stays sticky in that state,
    so it stays in view while the page scrolls past the right column.
    Getting the left sidebar to actually *stay* stuck (not just carry the
    `position: sticky` declaration) needed one more fix, same day: .col-lg-4
    used to carry `align-self: start`, which stopped it from stretching to
    match .col-lg-8's height — with a short intrinsic height, its sticky
    child had no room to travel and unstuck the moment its own (short) box
    scrolled past, long before the now much-taller right column finished
    scrolling. Removed, so .col-lg-4 stretches to the row's full height (its
    Bootstrap flex default) and the sticky child can stay pinned for the
    whole scroll. Harmless in the unclaimed state too, where the right
    column was already capped to roughly viewport height anyway.

    User-caught 2026-08-21: the new Complete/Submit-Evaluation confirmation
    modals (position: fixed, Bootstrap's own z-index: 1055) were opening
    unclickable — trapped behind the sticky navbar (z-index: 1040) once
    scrolled. Root cause: #connection-panel and #control-card both carried
    `position: relative; z-index: 5`, and a position:fixed descendant's
    stacking is scoped to its nearest ancestor that establishes a stacking
    context (any positioned element with a real z-index) — NOT the
    viewport, regardless of the descendant's own z-index value. That
    trapped the whole subtree, modal included, at an effective stacking
    order of 5 against the rest of the page — far below the navbar's 1040.
    Neither id's z-index was load-bearing (grepped theme-head.blade.php —
    nothing else positions against them), so both were dropped outright,
    the same fix already proven by home.blade.php's own pre-existing
    settings modal working correctly (it was never nested inside either).
    .col-lg-8 does still need to stay a positioned ancestor even once
    connected, though — not for this bug, but because
    .view-all-overlay's `position: absolute; inset: 0` (queue-panel.blade.php
    / theme-head.blade.php) is sized against it, and dropping its sticky
    positioning earlier the same day would otherwise have silently left it
    unpositioned, growing the overlay to cover the whole page instead of
    just the right column. `position: relative` (no offsets) fixes that
    with zero effect on layout or scroll — unlike z-index, plain
    `position: relative` alone establishes a containing block for
    absolutely-positioned descendants without creating a *stacking*
    context of its own, so it doesn't reintroduce the modal-trapping bug
    just fixed above.

    Stale as of 2026-08-22: .view-all-overlay switched to `position: fixed`
    (sized/placed at runtime instead, see the view-all script further down
    this file) so it can reach the actual bottom of the screen regardless
    of #right-column's own content height — it no longer relies on this
    column being a positioned ancestor. #right-column's `position: relative`
    is left in place anyway since it's harmless (same "zero effect on
    layout or scroll" as above) and other things may still assume it. --}}
    {{-- User-directed 2026-09-16: these were col-lg-4 / col-lg-8, so the two
         columns only appeared at 992px. A 12" tablet clears that; a 9–10" one
         reports roughly 850–960 CSS px in landscape (physical width ÷ pixel
         ratio) and fell just short, stacking the QR/login panel full width
         above the room data. Split from sm (576px) instead, at a more even
         5/7 so the narrower left column still fits the QR and the login
         fields; the original 4/8 proportion takes over again at lg. --}}
    <div class="row g-3">
        <div class="col-sm-5 col-lg-4" id="left-column">
            <div id="left-sidebar-sticky" style="position: sticky; top: 1rem;">
                <div id="connection-panel" class="{{ $connection ? 'card-brand p-2' : '' }}">
                    @include('room-session.partials.connection-panel')

                    @if ($connection)
                        <div id="control-panel" class="mt-3">
                            @include('room-session.partials.attempt-status-card')
                            @if ($canControlFlow)
                                @include('room-session.partials.presentation-control', ['showHeader' => true])
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-sm-7 col-lg-8 d-flex flex-column" id="right-column" style="align-self: start; {{ $connection ? 'position: relative;' : 'position: sticky; top: 1rem; max-height: calc(100vh - 2rem);' }}">
            <div id="control-card" class="card-brand p-2 mb-3" style="flex: 0 0 auto;">
                <div id="session-info-panel">
                    @include('room-session.partials.control-info-card')
                </div>

                @unless ($connection)
                    <div id="control-panel">
                        @include('room-session.partials.attempt-status-card')
                        @if ($canControlFlow)
                            @include('room-session.partials.presentation-control')
                        @endif
                    </div>
                @endunless

                <div id="session-info-next-panel">
                    @include('room-session.partials.control-info-next')
                </div>
            </div>

            <div id="queue-panel">
                @include('room-session.partials.queue-panel')
            </div>

            <div id="evaluation-panel" style="{{ $connection ? '' : 'flex: 1 1 auto; overflow-y: auto; min-height: 0;' }}">
                @if ($canEvaluate && $evaluationSubmission)
                    @include('room-session.partials.evaluation-panel')
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="room-session-settings-modal" tabindex="-1" aria-labelledby="room-session-settings-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="room-session-settings-modal-label">Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="text-brand-muted small">Appearance</span>
                        @include('partials.theme-toggle-button')
                    </div>

                    <form method="POST" action="{{ route('room-session.logout') }}" id="settings-logout-form" class="mb-2 {{ $connection ? '' : 'd-none' }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger-brand w-100"><x-icon name="log-out" /> Log Out</button>
                    </form>

                    <form method="POST" action="{{ route('room-session.release') }}" id="settings-release-form">
                        @csrf
                        <button type="button" class="btn btn-outline-danger-brand w-100" id="settings-release-btn" @disabled($connection)
                                data-bs-toggle="modal" data-bs-target="#release-device-modal"
                                title="{{ $connection ? 'Log out the connected panelist first' : '' }}"><x-icon name="log-out" /> Release This Device</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Sibling of the settings modal, not nested inside it, so it stacks
    cleanly on top rather than being clipped by the settings modal's own
    stacking context. Matches the "Submit Evaluation" confirm modal's
    trigger/target pattern (evaluation-panel.blade.php) — a plain button
    referencing the real form by id via form="settings-release-form",
    replacing the native confirm() this used before. --}}
    <div class="modal fade" id="release-device-modal" tabindex="-1" aria-labelledby="release-device-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="release-device-modal-label">Release This Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Release this device? It will need to be set up again.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="settings-release-form" class="btn btn-outline-danger-brand"><x-icon name="log-out" /> Release This Device</button>
                </div>
            </div>
        </div>
    </div>

    @include('room-session.partials.substitution-modal')
@endsection

@push('styles')
    @include('admin.evaluation-library.partials.paper-styles')
    <style>
        /* Page-scoped (this stack push only renders on this page, so it
        can't leak to the other pages sharing layouts/app.blade.php's nav) —
        user-directed 2026-08-21: now that the right column scrolls with
        the page instead of staying capped to the viewport, the top navbar
        should stay pinned in view too, same as the left sidebar. Needs an
        explicit background since the nav has none of its own by default
        (transparent, just showing body's own --brand-bg through it) — sticky
        content would otherwise scroll visibly underneath it. z-index sits
        below the "View All" overlay (1045) and Bootstrap's modal layer so
        neither ends up hidden behind the nav. */
        nav.navbar { position: sticky; top: 0; z-index: 1040; background-color: var(--brand-bg); }

        .eval-paper-live { max-width: none; }

        .eval-rating-bubble[data-score-btn] { cursor: pointer; background: var(--brand-surface); }
        .eval-rating-bubble[data-score-btn]:hover { background: var(--brand-surface-alt); }
        {{-- .is-selected itself is now styled by paper-styles.blade.php (shared with the read-only sheet views), included above. --}}

        .eval-outcome-btn { display: block; width: 100%; text-align: left; border: none; background: transparent; padding: 0.1rem 0; cursor: pointer; color: inherit; }
        .eval-outcome-btn.is-selected { font-weight: 600; }

        .eval-remarks-input { width: 100%; border: 1px solid var(--brand-border); border-radius: 0.25rem; padding: 0.35rem; font: inherit; background: var(--brand-surface); color: inherit; resize: vertical; }
        .eval-student-score-input { width: 4.5rem; border: 1px solid var(--brand-border); border-radius: 0.25rem; padding: 0.2rem 0.35rem; font: inherit; background: var(--brand-surface); color: inherit; text-align: center; }
        .eval-paper-comment-lines-readonly { border-top: 1px dashed var(--brand-border); margin-top: 0.4rem; padding-top: 0.3rem; min-height: 1.75rem; white-space: pre-wrap; font-size: 0.78rem; }

        /* Timer badge sizing (user-directed 2026-08-21): small everywhere
        except the one state meant to be read at a glance from across the
        room — a panel logged in and the run's number actually counting. */
        /* Scheduled-break warning under the timer (blinks, every terminal) and the
           banner shown while the room is actually on a break. */
        .break-warning {
            padding: 0.3rem 0.5rem;
            border-radius: 0.4rem;
            background: var(--brand-danger-tint);
            color: var(--brand-danger);
            font-size: 0.78rem;
            font-weight: 700;
            text-align: center;
            animation: break-warning-blink 1.1s ease-in-out infinite;
        }

        @keyframes break-warning-blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.25; }
        }

        @media (prefers-reduced-motion: reduce) {
            .break-warning { animation: none; }
        }

        .break-banner {
            background: var(--brand-accent-tint);
            color: var(--brand-text);
            font-size: 0.78rem;
            text-align: center;
        }

        .timer-badge-sm { font-size: 0.72rem; font-weight: 600; padding: 0.15rem 0.5rem; line-height: 1.1; }
        .timer-badge-lg { font-size: 1.6rem; font-weight: 700; padding: 0.35rem 0.9rem; line-height: 1.1; }

        /* Post-login "checking assignment" beat (2026-08-22) — a
        deliberately manufactured pause (see the reveal script below) so
        identity confirmation reads as a real check having happened, not an
        instant static badge. Covers #connection-status-content in place
        rather than replacing it, so there's no layout jump once it's
        hidden again. */
        .assignment-check-overlay {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 0.5rem;
        }

        /* ---- Left column width + fluid scale (user-directed 2026-09-16) ----

           The left column (QR / panelist login, then the panel name and
           Presentation Control once connected) is narrowed by 15% of the width
           it had, handing that space to the right column where the evaluation
           sheet lives. Bootstrap's own 5/12 and 4/12 become:

               sm+  41.667% x 0.85 = 35.417%   (right 64.583%)
               lg+  33.333% x 0.85 = 28.333%   (right 71.667%)

           Declared by id so they beat .col-sm-5 / .col-lg-4 without dropping
           those classes, which still carry the gutters and the stacking
           behaviour below 576px.

           Everything inside the column is then sized from one clamp() scale
           keyed to the viewport, so a narrower screen shrinks the card, its
           text and its buttons together rather than clipping them. The static
           line before each clamp is the fallback for older tablet browsers
           without clamp() (see theme-head's note on the same pattern). */
        @media (min-width: 576px) {
            #left-column { width: 35.417%; }
            #right-column { width: 64.583%; }
        }

        @media (min-width: 992px) {
            #left-column { width: 28.333%; }
            #right-column { width: 71.667%; }
        }

        #left-column {
            --rs-side-fs: 0.8rem;
            --rs-side-fs: clamp(0.68rem, 0.55rem + 0.42vw, 0.8rem);
            --rs-side-fs-sm: 0.72rem;
            --rs-side-fs-sm: clamp(0.62rem, 0.51rem + 0.35vw, 0.72rem);
            --rs-side-btn-py: 0.45rem;
            --rs-side-btn-py: clamp(0.3rem, 0.2rem + 0.3vw, 0.45rem);
            --rs-side-btn-px: 0.8rem;
            --rs-side-btn-px: clamp(0.5rem, 0.28rem + 0.6vw, 0.8rem);
        }

        #left-column .control-btn {
            padding: var(--rs-side-btn-py) var(--rs-side-btn-px);
            font-size: var(--rs-side-fs-sm);
        }

        #left-column .control-btn svg {
            width: 13px;
            height: 13px;
        }

        #left-column .control-btn-grid {
            gap: 0.3rem;
        }

        #left-column .btn {
            --bs-btn-padding-y: var(--rs-side-btn-py);
            --bs-btn-padding-x: var(--rs-side-btn-px);
            --bs-btn-font-size: var(--rs-side-fs-sm);
        }

        #left-column .form-control,
        #left-column .form-select {
            padding: var(--rs-side-btn-py) 0.55rem;
            font-size: var(--rs-side-fs-sm);
        }

        #left-column .form-label {
            margin-bottom: 0.2rem;
            font-size: var(--rs-side-fs-sm);
        }

        /* The connected panelist's name was an .h6 (1rem) directly above a
           .small "Connected since …" (0.875rem) — barely a 2px difference, so
           at a tablet's smaller root size the two read as the same size. The
           name now leads at roughly 1.4x the timestamp, and both ride the
           column's fluid scale. */
        .rs-panelist-name {
            margin: 0;
            font-size: 0.95rem;
            font-size: clamp(0.85rem, 0.7rem + 0.45vw, 1rem);
            font-weight: 600;
            line-height: 1.25;
        }

        .rs-panelist-since {
            font-size: 0.68rem;
            font-size: clamp(0.6rem, 0.52rem + 0.25vw, 0.72rem);
            line-height: 1.3;
        }

        /* The QR is an inline SVG at a fixed size inside a 140px-capped box;
           let it ride the same scale so it shrinks with the column instead of
           holding the column open at its own width. */
        #left-column #qr-code-container {
            margin-left: auto;
            margin-right: auto;
            max-width: 130px;
            max-width: clamp(96px, 70px + 6vw, 140px);
        }

        #left-column #qr-code-container svg,
        #left-column #qr-code-container img {
            width: 100%;
            height: auto;
        }
    </style>
@endpush

@push('scripts')
    <script>
        // Live-refreshes the queue and (while nobody's connected) the QR
        // code via a background JSON poll instead of the full-page
        // window.location.reload() this used to do — user-directed
        // 2026-08-16: the reload was visibly "felt" (a flash on every
        // tick) and had to be specially guarded against wiping the manual
        // login form mid-type. A plain DOM swap has neither problem: the
        // Room Queue panel is always safe to refresh (no inputs in it),
        // and the QR image rotates on its own inside #qr-code-container so
        // its access token keeps getting renewed even while the rest of
        // the panel sits untouched. The one exception is the exact moment
        // `connected` flips (2026-08-21): #control-panel's parent element
        // differs between the connected and unclaimed layouts (merged into
        // the left sidebar vs. the right card — see home.blade.php), so
        // that transition still does a plain reload rather than trying to
        // relocate a DOM node across columns; every other tick stays a
        // DOM swap as before.
        // Presentation Control's Defer/Refer-to-Admin buttons reveal an
        // inline form (data-toggle-target, delegated below since
        // #control-panel's own innerHTML gets replaced every poll — a
        // listener bound directly to a button inside it would be thrown
        // away on the next swap, but the wrapping #control-panel div
        // itself is never replaced, so a delegated listener on it survives).
        // While one of those forms is open, controlHtml is skipped for that
        // tick so a Lead mid-typing a defer reason doesn't get the form
        // collapsed out from under them every 5 seconds — same reasoning
        // that already protects the manual-login form below.
        (function () {
            var statusUrl = @json(route('room-session.status'));
            var sessionInfoPanel = document.getElementById('session-info-panel');
            var controlPanel = document.getElementById('control-panel');
            var sessionInfoNextPanel = document.getElementById('session-info-next-panel');
            var evaluationPanel = document.getElementById('evaluation-panel');
            var queuePanel = document.getElementById('queue-panel');
            var wasConnected = {{ $connection ? 'true' : 'false' }};
            // Tracks the backup/unassigned/assigned/no-target classification
            // (see TerminalConnectionService::classifyForRoom()) so an
            // Admin's approval — which flips this without `connected` ever
            // changing — still triggers the same full reload treatment as a
            // connect/disconnect. Kept in sync manually after a same-request
            // substitution-form submit below, so that success doesn't also
            // trigger a redundant reload on the very next tick.
            var wasClassification = @json($assignmentClassification['kind'] ?? null);
            var timer;

            // The left sidebar's sticky offset has to clear the sticky
            // navbar's real height, not just an arbitrary 1rem — user-
            // caught 2026-08-21: with a flat `top: 1rem`, the sidebar's
            // "Connected" card scrolled up underneath the (higher-
            // z-index) navbar rather than stopping below it. The navbar's
            // actual height isn't a fixed constant here (its content
            // varies — the QR/login pages show plain "Room Session" text,
            // the connected page swaps in the system name + room/
            // terminal info, see home.blade.php's navbar-inner section —
            // and it can also wrap on a narrower window), so it's measured
            // at runtime instead of hardcoded, and re-measured on resize.
            var leftSidebar = document.getElementById('left-sidebar-sticky');
            var navbarEl = document.querySelector('nav.navbar');

            function updateStickyOffset() {
                if (!leftSidebar || !navbarEl) return;
                leftSidebar.style.top = (navbarEl.getBoundingClientRect().height + 16) + 'px';
            }

            updateStickyOffset();
            window.addEventListener('resize', updateStickyOffset);

            // Confirmation modals (Complete, Submit Evaluation) render
            // inside #control-panel/#evaluation-panel, both nested inside
            // #left-sidebar-sticky or the right column. User-caught
            // 2026-08-21, round two: removing #connection-panel/#control-
            // card's z-index wasn't enough — position: sticky *itself*
            // unconditionally creates a new CSS stacking context (per spec,
            // even with no z-index set at all, unlike position: relative/
            // absolute which only do that when z-index isn't auto), so
            // #left-sidebar-sticky was still trapping any modal nested
            // inside it. Bootstrap's backdrop is appended straight to
            // <body> (outside the trap, so it paints correctly — hence the
            // screen visibly darkening), while the modal dialog itself
            // stayed trapped underneath it — explaining "screen goes dark
            // but I can't click anything," the darkened backdrop was
            // sitting on top of an inaccessible modal. There's no CSS fix
            // for this short of dropping the sidebar's own sticky
            // positioning (not an option, that's the point of it) — so
            // instead this physically relocates each .modal to a direct
            // child of <body> the moment it appears, the same place
            // home.blade.php's own pre-existing settings modal already
            // lives (never nested inside anything positioned, which is
            // exactly why that one never had this problem). data-bs-target
            // lookups work by plain document-wide id, so moving a modal
            // doesn't break its trigger button or its form="..."-linked
            // submit button, wherever either physically live in the DOM.
            function relocateModals(container) {
                if (!container) return;
                container.querySelectorAll('.modal').forEach(function (modal) {
                    var stale = document.body.querySelector('#' + modal.id + '[data-relocated-modal]');
                    if (stale && stale !== modal) stale.remove();
                    modal.setAttribute('data-relocated-modal', '1');
                    // Tags which panel this modal came from — once moved,
                    // it's no longer a descendant of that panel, so
                    // controlFormOpen()/evaluationModalOpen() below have to
                    // find it by this tag instead of by DOM position.
                    modal.setAttribute('data-relocated-from', container.id);
                    document.body.appendChild(modal);
                });
            }

            // The Lead's "cancel the break / finish this group" prompt opens by
            // itself the first time it appears for a given break (the modal is
            // rendered only while that question is unanswered — see
            // RoomBreakService::pendingDecision()). Remembered per break for the
            // life of the tab so closing it doesn't bring it straight back on
            // the next poll or reload; the Scheduled Break button reopens it.
            function autoShowBreakDecision() {
                var modalEl = document.querySelector('.modal[data-auto-show][data-relocated-from="control-panel"]');
                if (!modalEl || !window.bootstrap) return;

                var key = 'pqrs-break-prompt-' + modalEl.getAttribute('data-auto-show');
                var seen = false;
                try { seen = !!sessionStorage.getItem(key); } catch (e) {}
                if (seen) return;
                try { sessionStorage.setItem(key, '1'); } catch (e) {}

                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }

            relocateModals(controlPanel);
            relocateModals(evaluationPanel);
            autoShowBreakDecision();

            if (controlPanel) {
                controlPanel.addEventListener('click', function (event) {
                    var trigger = event.target.closest('[data-toggle-target]');
                    if (!trigger) return;
                    var target = document.getElementById(trigger.getAttribute('data-toggle-target'));
                    if (!target) return;
                    if (target.classList.contains('d-none')) {
                        // Opening: remember where the view was so Cancel can return to it.
                        target.dataset.scrollRestoreY = window.scrollY;
                        target.classList.remove('d-none');
                        target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    } else {
                        // Closing (Confirm/Cancel toggled it back): scroll back up to the
                        // view the user was on before it opened, rather than leaving them
                        // scrolled down at wherever the form happened to be.
                        target.classList.add('d-none');
                        var restoreY = target.dataset.scrollRestoreY;
                        if (restoreY !== undefined) {
                            window.scrollTo({ top: parseInt(restoreY, 10), behavior: 'smooth' });
                            delete target.dataset.scrollRestoreY;
                        }
                    }
                });
            }

            // Every 1s tick recomputes remaining/overtime seconds straight
            // from the run's own started_at/configured_duration_seconds/
            // total_paused_seconds (re-read fresh from the DOM each tick, so
            // a poll-driven innerHTML swap of #control-panel or
            // #evaluation-panel needs no separate re-binding here). The 5s
            // poll below resyncs the underlying data-* attributes from the
            // server, so client clock drift never accumulates.
            function formatDuration(totalSeconds) {
                var abs = Math.round(Math.abs(totalSeconds));
                var m = Math.floor(abs / 60);
                var s = abs % 60;
                return m + ':' + (s < 10 ? '0' : '') + s;
            }

            function tickTimers() {
                document.querySelectorAll('[data-timer-badge]').forEach(function (el) {
                    el.classList.remove('badge-success-tint', 'badge-brand-tint', 'badge-danger-tint', 'badge-info-tint', 'badge-muted-tint');

                    // One badge, one slot — while the current group is only
                    // CALLED (not yet started), it counts up how long it's
                    // been waiting; the moment the run actually starts, the
                    // exact same element switches to the countdown/overtime
                    // display below (user-directed 2026-08-22: same place,
                    // same format/size either way, so this always stays
                    // small — "big" is reserved for the genuinely-running
                    // case in the duration branch further down).
                    if (el.dataset.timerMode === 'waiting') {
                        el.classList.remove('timer-badge-lg');
                        el.classList.add('timer-badge-sm');

                        var deadline = el.dataset.waitingDeadlineAt;
                        if (!deadline) {
                            el.classList.add('badge-muted-tint');
                            el.textContent = 'Waiting';
                            return;
                        }

                        // Counts DOWN to 00:00 (user-directed 2026-08-22),
                        // not up from called_at — deadline is called_at +
                        // the category's configured called_waiting_minutes,
                        // set once at Call Next.
                        var remainingSeconds = (new Date(deadline).getTime() - new Date().getTime()) / 1000;
                        var overdue = remainingSeconds <= 0;

                        el.classList.add(overdue ? 'badge-danger-tint' : 'badge-muted-tint');
                        el.textContent = 'Waiting ' + formatDuration(Math.max(0, remainingSeconds));

                        document.querySelectorAll('[data-waiting-recommendation]').forEach(function (rec) {
                            rec.classList.toggle('d-none', !overdue);
                        });
                        return;
                    }

                    var statusCode = el.dataset.timerStatus;
                    var startedAt = el.dataset.startedAt;
                    var connected = el.dataset.timerConnected === '1';

                    // Big only once a panel is logged in AND the run is
                    // genuinely counting (user-directed 2026-08-21) — small
                    // on the QR/unclaimed page and small for "Not Started"
                    // even on a page with a panel logged in. Re-derived here
                    // every tick (not just at render time) so a poll-driven
                    // status change is reflected immediately.
                    var isBig = connected && !!startedAt && statusCode !== 'NOT_STARTED';
                    el.classList.toggle('timer-badge-lg', isBig);
                    el.classList.toggle('timer-badge-sm', !isBig);

                    if (!startedAt || statusCode === 'NOT_STARTED') {
                        el.classList.add('badge-muted-tint');
                        el.textContent = 'Not Started';
                        return;
                    }

                    if (statusCode === 'COMPLETED') {
                        el.classList.add('badge-info-tint');
                        return;
                    }

                    var configured = parseInt(el.dataset.configuredDuration || '0', 10);
                    var totalPaused = parseInt(el.dataset.totalPaused || '0', 10);
                    var referenceTime = (statusCode === 'PAUSED' && el.dataset.lastActionAt) ? new Date(el.dataset.lastActionAt) : new Date();
                    var elapsedSeconds = (referenceTime.getTime() - new Date(startedAt).getTime()) / 1000 - totalPaused;
                    var remaining = configured - elapsedSeconds;

                    if (statusCode === 'PAUSED') {
                        el.classList.add('badge-danger-tint');
                        el.textContent = 'Paused — ' + formatDuration(remaining);
                        return;
                    }

                    if (remaining >= 0) {
                        el.classList.add('badge-success-tint');
                        el.textContent = formatDuration(remaining);
                    } else {
                        el.classList.add('badge-danger-tint');
                        el.textContent = 'Extended +' + formatDuration(remaining);
                    }
                });
            }

            tickTimers();
            setInterval(tickTimers, 1000);

            function csrfTokenFrom(root) {
                var input = root.querySelector('[data-csrf-token]');
                return input ? input.value : '';
            }

            function postEvaluation(url, fields) {
                var body = new FormData();
                body.append('_token', csrfTokenFrom(evaluationPanel));
                Object.keys(fields).forEach(function (key) {
                    if (fields[key] !== null && fields[key] !== undefined) {
                        body.append(key, fields[key]);
                    }
                });

                return fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body,
                }).then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                });
            }

            function evaluationRemarksFocused() {
                return !!(evaluationPanel && document.activeElement && evaluationPanel.contains(document.activeElement) && document.activeElement.matches('[data-remarks-input], [data-student-score-input]'));
            }

            // Submit Evaluation's confirmation is a Bootstrap modal now
            // (2026-08-21, was a native confirm() dialog) — it lives inside
            // #evaluation-panel's own innerHTML, same as everything else
            // there, so a poll-driven swap mid-confirmation would yank the
            // open modal out from under the panelist. Guarded the same way
            // #control-panel's inline defer/refer forms already are.
            function evaluationModalOpen() {
                // Searched document-wide, not inside evaluationPanel — once
                // relocateModals() moves the modal out to <body> (needed to
                // escape the sidebar's stacking-context trap, see above),
                // it's no longer a descendant of #evaluation-panel, so its
                // data-relocated-from tag is the only way left to attribute
                // it back to this panel.
                return !!document.querySelector('.modal.show[data-relocated-from="evaluation-panel"]');
            }

            if (evaluationPanel) {
                evaluationPanel.addEventListener('click', function (event) {
                    var scoreBtn = event.target.closest('[data-score-btn]');
                    if (scoreBtn) {
                        var criterionId = scoreBtn.dataset.criterionId;
                        var proposedTitleId = scoreBtn.dataset.proposedTitleId || '';

                        postEvaluation(@json(route('room-session.evaluation.score')), {
                            evaluation_criterion_id: criterionId,
                            proposed_title_id: proposedTitleId,
                            score: scoreBtn.dataset.value,
                        }).then(function (result) {
                            if (!result.ok) return;

                            evaluationPanel.querySelectorAll(
                                '[data-score-btn][data-criterion-id="' + criterionId + '"][data-proposed-title-id="' + proposedTitleId + '"]'
                            ).forEach(function (btn) {
                                btn.classList.toggle('is-selected', btn === scoreBtn);
                            });

                            if (result.data.totals) {
                                var totalEl = evaluationPanel.querySelector('[data-eval-total-score]');
                                if (totalEl) {
                                    totalEl.textContent = result.data.totals.weighted !== null && result.data.totals.weighted !== undefined
                                        ? result.data.totals.weighted
                                        : '___';
                                }
                            }
                        });
                        return;
                    }

                    var outcomeBtn = event.target.closest('[data-outcome-btn]');
                    if (outcomeBtn) {
                        postEvaluation(@json(route('room-session.evaluation.outcome')), {
                            presentation_outcome_id: outcomeBtn.dataset.outcomeId,
                        }).then(function (result) {
                            if (!result.ok) return;

                            evaluationPanel.querySelectorAll('[data-outcome-btn]').forEach(function (btn) {
                                var isSelected = btn === outcomeBtn;
                                btn.classList.toggle('is-selected', isSelected);
                                var glyph = btn.querySelector('.eval-checkbox-glyph');
                                if (glyph) glyph.innerHTML = isSelected ? '&#9745;' : '&#9633;';
                            });

                            // A remark is required to submit, and it's normally
                            // the last thing picked — reflect it immediately
                            // rather than at the next poll. The server decides;
                            // this only applies its answer.
                            var submitBtn = evaluationPanel.querySelector('[data-bs-target="#submit-evaluation-modal"]');
                            if (submitBtn && typeof result.data.canSubmit === 'boolean') {
                                submitBtn.disabled = !result.data.canSubmit;
                                if (result.data.canSubmit) submitBtn.title = '';
                            }
                        });
                    }
                });

                evaluationPanel.addEventListener('blur', function (event) {
                    var textarea = event.target.closest('[data-remarks-input]');
                    if (textarea) {
                        postEvaluation(@json(route('room-session.evaluation.remarks')), {
                            remarks: textarea.value,
                        });
                        return;
                    }

                    var scoreInput = event.target.closest('[data-student-score-input]');
                    if (scoreInput) {
                        postEvaluation(@json(route('room-session.evaluation.student-score')), {
                            student_id: scoreInput.dataset.studentId,
                            score: scoreInput.value === '' ? null : scoreInput.value,
                        });
                    }
                }, true);
            }

            // The "View All" trigger now lives in the Next section of
            // #session-info-panel (control-info-card.blade.php), but the
            // overlay itself still renders from #queue-panel
            // (queue-panel.blade.php, now overlay-only) — both divs survive
            // every poll (only their innerHTML is swapped), so a delegated
            // listener on each is enough; the overlay is looked up globally
            // since it's unique on the page either way. Guarded the same
            // way #control-panel's inline forms are above, so a Lead
            // browsing the full queue doesn't get it closed out from under
            // them (or their scroll position inside it reset) every 5s.
            function viewAllPanelEl() {
                return document.querySelector('[data-view-all-panel]');
            }

            // .view-all-overlay is `position: fixed` (theme-head.blade.php,
            // 2026-08-22) so it stays anchored to the real viewport rather
            // than the scrolling right column, but that means CSS alone
            // can no longer size/place it against that column — this pins
            // its top to wherever #right-column's top edge currently sits
            // on screen (not necessarily the very top of the viewport) and
            // stretches its height down to the bottom of the visible
            // screen from there, matching the column's current width.
            // Recomputed fresh every time the panel opens (the column's
            // on-screen position depends on scroll, which can change while
            // it's closed) and on resize while it's open.
            function updateViewAllOverlayBounds() {
                var overlay = viewAllPanelEl();
                var column = document.getElementById('right-column');
                if (!overlay || !column) return;
                var rect = column.getBoundingClientRect();
                var top = Math.max(rect.top, 0);
                overlay.style.top = top + 'px';
                overlay.style.height = 'calc(100vh - ' + top + 'px)';
                overlay.style.left = rect.left + 'px';
                overlay.style.width = rect.width + 'px';
            }

            window.addEventListener('resize', function () {
                var panel = viewAllPanelEl();
                if (panel && panel.classList.contains('is-open')) {
                    updateViewAllOverlayBounds();
                }
            });

            // Delegated on #control-card (the static outer wrapper, never
            // itself replaced) rather than #session-info-next-panel
            // directly — the trigger button lives inside that child's
            // poll-refreshed innerHTML, so a listener bound to the child
            // would be thrown away on the next swap.
            var controlCard = document.getElementById('control-card');
            if (controlCard) {
                controlCard.addEventListener('click', function (event) {
                    if (event.target.closest('[data-view-all-toggle]')) {
                        var panel = viewAllPanelEl();
                        if (panel) {
                            updateViewAllOverlayBounds();
                            panel.classList.add('is-open');
                        }
                    }
                });
            }

            if (queuePanel) {
                queuePanel.addEventListener('click', function (event) {
                    if (event.target.closest('[data-view-all-close]')) {
                        var panel = viewAllPanelEl();
                        if (panel) panel.classList.remove('is-open');
                    }
                });
            }

            function controlFormOpen() {
                if (!controlPanel) return false;
                // Toggleable inline forms (defer-form,
                // still real descendants of #control-panel, never
                // relocated) AND, since 2026-08-21, the Complete
                // confirmation modal — same document-wide
                // data-relocated-from lookup as evaluationModalOpen() above,
                // since the modal itself no longer lives inside
                // #control-panel once relocateModals() has moved it.
                return !!(controlPanel.querySelector('form[id$="-form"]:not(.d-none)') || document.querySelector('.modal.show[data-relocated-from="control-panel"]'));
            }

            // Same shape as evaluationRemarksFocused() — the payment-type
            // reference-number fields sit directly inside #control-panel
            // (never toggled via d-none like defer-form,
            // so controlFormOpen()'s own selector doesn't cover them), and a
            // poll-driven innerHTML swap mid-type would wipe out whatever the
            // Lead just typed.
            function paymentInputFocused() {
                return !!(controlPanel && document.activeElement && controlPanel.contains(document.activeElement) && document.activeElement.matches('[data-reference-number-input]'));
            }

            function viewAllOpen() {
                var panel = viewAllPanelEl();
                return !!(panel && panel.classList.contains('is-open'));
            }

            function schedule() {
                clearTimeout(timer);
                timer = setTimeout(poll, 5000);
            }

            function poll() {
                fetch(statusUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.connected !== wasConnected || data.classification !== wasClassification) {
                            window.location.reload();
                            return;
                        }

                        if (sessionInfoPanel) {
                            sessionInfoPanel.innerHTML = data.sessionInfoHtml;
                        }

                        if (sessionInfoNextPanel) {
                            sessionInfoNextPanel.innerHTML = data.sessionInfoNextHtml;
                        }

                        if (!viewAllOpen()) {
                            queuePanel.innerHTML = data.queueHtml;
                        }

                        if (controlPanel && data.controlHtml !== null && !controlFormOpen() && !paymentInputFocused()) {
                            controlPanel.innerHTML = data.controlHtml;
                            relocateModals(controlPanel);
                            autoShowBreakDecision();
                        }

                        if (evaluationPanel && !evaluationRemarksFocused() && !evaluationModalOpen()) {
                            evaluationPanel.innerHTML = data.evaluationHtml || '';
                            relocateModals(evaluationPanel);
                        }

                        if (!data.connected) {
                            var qrContainer = document.getElementById('qr-code-container');
                            if (qrContainer) {
                                qrContainer.innerHTML = data.qrSvg;
                            }
                        }
                    })
                    .catch(function () {
                        // Network hiccup — just try again next tick.
                    })
                    .finally(schedule);
            }

            // Post-login "checking assignment" beat + backup/unassigned
            // replacement picker (2026-08-22) — see
            // connection-panel-connected.blade.php and
            // substitution-modal.blade.php. Fires at most once per
            // (connection, classification) pair, tracked in sessionStorage
            // so it survives the poll-triggered reloads that happen when
            // classification itself changes (e.g. an Admin approving a
            // pending request) — sessionStorage persists across reloads in
            // the same tab, which a physical tablet effectively never
            // closes, so this is a reliable one-shot flag with no new
            // server-side session state.
            function revealClassification(kind) {
                if (kind === 'lead' || kind === 'assigned') {
                    var roleLabel = kind === 'lead' ? 'the Chair' : 'a Member';
                    window.showAppToast("Identity confirmed — you're signed in as " + roleLabel + ' for this session.', false);
                    return;
                }

                if (kind === 'backup' || kind === 'unassigned') {
                    var modalEl = document.getElementById('substitution-modal');
                    if (modalEl && window.bootstrap) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                }
            }

            var statusContent = document.getElementById('connection-status-content');
            var checkOverlay = document.getElementById('assignment-check-overlay');

            if (statusContent && checkOverlay && statusContent.dataset.classificationKind) {
                var revealKind = statusContent.dataset.classificationKind;
                var revealKey = 'pqrs-reveal-' + statusContent.dataset.connectionId + '-' + revealKind;

                if (!sessionStorage.getItem(revealKey)) {
                    checkOverlay.classList.remove('d-none');
                    statusContent.style.visibility = 'hidden';

                    setTimeout(function () {
                        checkOverlay.classList.add('d-none');
                        statusContent.style.visibility = '';
                        sessionStorage.setItem(revealKey, '1');
                        revealClassification(revealKind);
                    }, 1600);
                }
            }

            var substitutionModal = document.getElementById('substitution-modal');
            if (substitutionModal) {
                substitutionModal.addEventListener('submit', function (event) {
                    var form = event.target.closest('#substitution-form');
                    if (!form) return;
                    event.preventDefault();

                    var submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) submitBtn.disabled = true;

                    var body = new FormData(form);

                    fetch(@json(route('room-session.substitution.request')), {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: body,
                    })
                        .then(function (response) {
                            return response.json().catch(function () { return {}; }).then(function (data) {
                                return { ok: response.ok, data: data };
                            });
                        })
                        .then(function (result) {
                            if (!result.ok) {
                                window.showAppToast((result.data && result.data.message) || 'Could not submit request.', true);
                                if (submitBtn) submitBtn.disabled = false;
                                return;
                            }

                            if (result.data.status === 'APPROVED') {
                                // A backup panelist's pick applies immediately
                                // (PanelSubstitutionService::request()) — keep
                                // wasClassification in sync so the next 5s
                                // poll tick doesn't also trigger a redundant
                                // reload for a change this request already
                                // handled.
                                wasClassification = 'assigned';
                                bootstrap.Modal.getOrCreateInstance(substitutionModal).hide();
                                revealClassification('assigned');
                                return;
                            }

                            var modalBody = document.getElementById('substitution-modal-body');
                            if (modalBody) {
                                modalBody.innerHTML = '<div class="text-center py-3">'
                                    + '<div class="spinner-border spinner-border-sm text-brand-accent mb-2" role="status" aria-hidden="true"></div>'
                                    + '<p class="mb-0">Waiting for an Administrator to review your request&hellip;</p>'
                                    + '</div>';
                            }
                        })
                        .catch(function () {
                            window.showAppToast('Network error — please try again.', true);
                            if (submitBtn) submitBtn.disabled = false;
                        });
                });
            }

            schedule();
        })();
    </script>
@endpush
