@extends('layouts.admin')

@section('title', 'Event Control — ' . $category->name)
@section('heading', $category->name)

@section('content')
    <div class="page-shell event-page">
    <div class="modal fade" id="complete-category-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.live-monitoring.complete', $category) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">End Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Mark <strong>{{ $category->name }}</strong> as complete? Every group has reached a final outcome. This cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="stop" /> End Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($canDeleteCategory)
        <div class="modal fade" id="delete-category-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.live-monitoring.destroy', $category) }}">
                        @csrf
                        @method('DELETE')
                        <div class="modal-header">
                            <h5 class="modal-title">Delete Category</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">Permanently delete <strong>{{ $category->name }}</strong> and every group, presentation, schedule, and terminal record under it? This cannot be undone.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="trash" /> Delete Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Header: identity chips on the left, the category picker and the
         category-level actions on the right. Both sides wrap independently,
         and below sm the action row takes the full width so the buttons stay
         tappable instead of shrinking. --}}
    <div class="event-header">
        <div class="event-header-meta">
            <span class="event-chip">{{ $category->academicYear->name ?? 'No academic year' }}</span>
            <span class="event-chip">{{ $category->semester->name ?? 'No semester' }}</span>
            <span class="event-chip event-chip-wide">{{ $category->college->name ?? 'No college' }}</span>
        </div>

        <div class="event-header-actions">
            @include('partials.category-picker-dropdown', [
                'categories' => $pickerCategories,
                'current' => $category,
                'routeName' => 'admin.live-monitoring.show',
            ])

            <button type="button" class="btn btn-sm btn-outline-danger-brand" data-bs-toggle="modal" data-bs-target="#complete-category-modal"
                    @disabled(! $canCompleteCategory)
                    title="{{ $canCompleteCategory ? '' : $completionBlockReason }}">
                <x-icon name="stop" /> End Category
            </button>
            @if ($canDeleteCategory)
                <button type="button" class="btn btn-sm btn-outline-danger-brand" data-bs-toggle="modal" data-bs-target="#delete-category-modal"><x-icon name="trash" /> Delete Category</button>
            @endif
        </div>
    </div>

    <div class="mb-3">
        @include('admin.partials.capacity-analysis-card', ['capacityAnalysis' => $capacityAnalysis])
    </div>

    @if ($category->presentationDates->isEmpty())
        <div class="card-brand p-5 text-center text-brand-muted">No presentation dates configured for this category yet.</div>
    @else
        @if ($featuredDate)
            @include('admin.live-monitoring.partials.date-detail', [
                'date' => $featuredDate,
                'panels' => $roomPanels,
                'category' => $category,
                'adjustmentReasons' => $adjustmentReasons,
                'presentationOutcomes' => $presentationOutcomes,
            ])
        @endif

        @if ($otherDates->isNotEmpty())
            <button type="button" class="btn btn-sm btn-outline-brand mb-2" data-view-all-toggle
                    data-show-label="View All Days ({{ $otherDates->count() }} more)"
                    data-hide-label="Hide Other Days">
                View All Days ({{ $otherDates->count() }} more)
            </button>

            <div class="d-none" data-view-all-days>
                @foreach ($otherDates as $date)
                    @include('admin.live-monitoring.partials.date-summary', [
                        'date' => $date,
                        'category' => $category,
                    ])
                @endforeach
            </div>
        @endif
    @endif
    </div>

    @include('admin.partials.confirm-action-modal')
@endsection

@push('styles')
    <style>
        /* ---------- Event Control workspace ----------
           Width, fluid type tokens and sidebar-colored cards come from the
           shared .page-shell (theme-head); this page layers on compact
           padding and, more importantly, re-tunes the two card families it
           owns exclusively — .room-status-* and .room-data-* — so they read
           as part of this page instead of as a transplanted tablet screen.

           .room-data-* deliberately drops the --rd-scale growth it carries
           in theme-head (which pushed "Room Data" to ~1.4rem at 1600px+):
           user-directed 2026-09-16, text on this page stays small at every
           width. Every size below is scoped to .event-page, so the tablet's
           own .control-btn / Reports' .capacity-stat-* are untouched. */
        .event-page {
            --event-card-pad: clamp(0.7rem, 0.55rem + 0.55vw, 1rem);
        }

        /* --- Header --- */
        .event-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem 0.9rem;
            margin-bottom: 0.75rem;
        }

        .event-header-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem;
            min-width: 0;
        }

        .event-chip {
            display: inline-block;
            max-width: 100%;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            background-color: var(--brand-surface-alt);
            color: var(--brand-muted);
            font-size: var(--page-fs-xs);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .event-chip-wide {
            max-width: 22rem;
        }

        .event-header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        /* --- Card padding / rhythm --- */
        .event-page .card-brand.p-4 {
            padding: var(--event-card-pad) !important;
        }

        .event-page .card-brand.p-3 {
            padding: calc(var(--event-card-pad) * 0.85) !important;
        }

        .event-page .card-brand.p-5 {
            padding: calc(var(--event-card-pad) * 1.8) !important;
        }

        .event-page .card-brand.mb-3,
        .event-page .card-brand.mb-2 {
            margin-bottom: 0.7rem !important;
        }

        .event-page .row.g-3 {
            --bs-gutter-x: 0.7rem;
            --bs-gutter-y: 0.7rem;
        }

        .event-page h4.h6 {
            font-size: var(--page-fs-heading);
            font-weight: 600;
        }

        .event-page .detail-list {
            font-size: var(--page-fs-sm);
        }

        .event-page code {
            font-size: var(--page-fs-sm);
        }

        /* --- Capacity Analysis (shared partial) — compact here too --- */
        .event-page .capacity-card-header {
            padding: 0.6rem var(--event-card-pad);
        }

        .event-page .capacity-header-icon {
            width: 2rem;
            height: 2rem;
            background-color: var(--brand-surface-alt);
        }

        .event-page .capacity-header-icon svg {
            width: 1.05rem;
            height: 1.05rem;
        }

        .event-page .capacity-card-header .small {
            font-size: var(--page-fs-xs);
        }

        .event-page .capacity-status-pill {
            font-size: var(--page-fs-xs);
            padding: 0.22rem 0.55rem;
        }

        /* 9.25rem is the narrowest column that still fits the icon tile plus
           the longest label ("Duration/Group") on one line. */
        .event-page .capacity-stat-grid {
            grid-template-columns: repeat(auto-fit, minmax(9.25rem, 1fr));
            gap: 0.6rem 0.9rem;
            padding: 0.7rem var(--event-card-pad);
        }

        .event-page .capacity-stat-icon {
            width: 1.85rem;
            height: 1.85rem;
            border-radius: 0.5rem;
            background-color: var(--brand-surface);
        }

        .event-page .capacity-stat-icon svg {
            width: 0.95rem;
            height: 0.95rem;
        }

        .event-page .capacity-stat-label {
            font-size: var(--page-fs-xs);
        }

        .event-page .capacity-stat-value {
            font-size: clamp(0.85rem, 0.8rem + 0.18vw, 0.95rem);
        }

        .event-page .capacity-stat-value small {
            font-size: var(--page-fs-xs);
        }

        .event-page .capacity-message,
        .event-page .capacity-empty {
            font-size: var(--page-fs-sm);
            padding: 0.55rem var(--event-card-pad);
        }

        /* --- Day header --- */
        .event-day-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.4rem 0.75rem;
            margin-bottom: 0.7rem;
        }

        .event-day-title {
            margin: 0;
            font-size: var(--page-fs-heading);
            font-weight: 600;
            line-height: 1.3;
        }

        .event-day-time {
            font-size: var(--page-fs-xs);
            color: var(--brand-muted);
        }

        .event-day-note {
            font-size: var(--page-fs-sm);
            margin-bottom: 0.7rem;
        }

        /* --- Per-day summary rows (View All Days) --- */
        .event-summary-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.4rem 0.9rem;
        }

        .event-summary-when {
            min-width: 0;
        }

        .event-summary-date {
            font-size: var(--page-fs);
            font-weight: 600;
            line-height: 1.3;
        }

        .event-summary-side {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.4rem 0.6rem;
        }

        .event-summary-rooms {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.3rem 0.6rem;
        }

        .event-summary-room {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: var(--page-fs-xs);
            color: var(--brand-muted);
        }

        @media (max-width: 575.98px) {
            .event-summary-side {
                width: 100%;
            }
        }

        /* --- Room tabs — scroll sideways rather than wrap into a tall stack --- */
        .event-page .nav-tabs {
            gap: 0.15rem;
            border-bottom-color: var(--brand-border);
        }

        .event-page .nav-tabs .nav-link {
            padding: 0.35rem 0.75rem;
            font-size: var(--page-fs-sm);
            font-weight: 500;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        /* --- Room Status card --- */
        .event-page .room-status-stats {
            gap: 0.6rem 0.9rem;
        }

        .event-page .room-status-stats .capacity-stat-item {
            flex: 1 1 6.5rem;
            gap: 0.45rem;
        }

        .event-page .room-status-value {
            font-size: var(--page-fs);
            font-weight: 600;
        }

        .event-page .room-status-account {
            padding: 0.65rem 0.75rem;
            border-radius: 0.5rem;
            background-color: var(--brand-surface);
        }

        /* --- Action buttons: the tablet's pill treatment, admin-sized --- */
        .event-page .control-btn {
            padding: 0.35rem 0.7rem;
            font-size: var(--page-fs-xs);
        }

        .event-page .control-btn svg {
            width: 13px;
            height: 13px;
        }

        /* The tablet stretches its primary controls edge to edge for a
           touch target; on the admin page they size to their label and sit
           left, and only go full width once the card itself is phone-narrow. */
        /* Each action is wrapped in its own POST form, and those forms carry
           the tablet's d-flex flex-fill — left alone they'd each claim an
           equal share of the row and stretch the buttons across the card. */
        .event-page .control-btn-grid > form {
            flex: 0 1 auto;
        }

        .event-page .control-btn-primary {
            flex: 0 1 auto;
            min-width: 0;
            padding: 0.42rem 0.9rem;
            font-size: var(--page-fs-sm);
            border-radius: 0.6rem;
        }

        .event-page .control-btn-primary svg {
            width: 14px;
            height: 14px;
        }

        /* --- Terminals table --- */
        .event-page .table-sm > :not(caption) > * > * {
            padding: 0.35rem 0.4rem;
        }

        .event-page .table.small,
        .event-page .table.small td {
            font-size: var(--page-fs-xs);
        }

        /* The per-room "View All" overlay is a dense seven-column list in a
           half-viewport panel; .page-shell's .table sizing would inflate it,
           so it keeps its own compact scale (now expressed in page tokens). */
        .event-page .view-all-table {
            font-size: var(--page-fs-xs);
        }

        .event-page .view-all-table th,
        .event-page .view-all-table td {
            padding: 0.3rem 0.4rem;
        }

        /* --- Room Data card --- */
        .event-page .room-data {
            --rd-scale: 1;
        }

        .event-page .room-data-title {
            font-size: var(--page-fs-heading);
        }

        .event-page .room-data-section {
            padding: 0.6rem 0.7rem;
            font-size: var(--page-fs-sm);
        }

        /* Six stats, fixed 3-up. auto-fit filled the row with narrow columns
           that wrapped "Registered in Category" and left the sixth stat
           stranded on a line of its own; three even columns keep every
           label on one line and both rows balanced. */
        .event-page .room-data-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.55rem 0.9rem;
        }

        .event-page .room-data-stats .fw-semibold {
            font-size: var(--page-fs);
        }

        .event-page .room-data-label {
            font-size: var(--page-fs-xs);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-weight: 600;
        }

        .event-page .room-data-body,
        .event-page .room-data-sub {
            font-size: var(--page-fs-sm);
        }

        .event-page .room-data-badge {
            font-size: var(--page-fs-xs);
        }

        .event-page .room-data-btn {
            font-size: var(--page-fs-xs);
            padding: 0.2rem 0.55rem;
        }

        .event-page .room-data-next-item {
            flex: 1 1 9rem;
            padding: 0.55rem 0.65rem;
            font-size: var(--page-fs-sm);
        }

        .event-page .room-data-next-item .fw-semibold {
            font-size: var(--page-fs-sm);
        }

        /* --- Small screens: everything steps down another notch --- */
        @media (max-width: 767.98px) {
            .event-page {
                --event-card-pad: 0.7rem;
            }

            .event-page .room-data-next-item {
                flex: 1 1 100%;
            }
        }

        @media (max-width: 575.98px) {
            .event-header-actions {
                width: 100%;
            }

            /* The picker's button is wrapped in its own .dropdown, so the
               wrapper is what shares the row and the button fills it. */
            .event-header-actions > .btn,
            .event-header-actions > .dropdown {
                flex: 1 1 auto;
            }

            .event-header-actions > .dropdown > .btn {
                width: 100%;
            }

            .event-header-actions .btn {
                justify-content: center;
            }

            .event-chip-wide {
                max-width: 100%;
            }

            .event-page .capacity-stat-grid,
            .event-page .room-data-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .event-page .room-status-stats .capacity-stat-item {
                flex: 1 1 100%;
            }

            .event-page .control-btn-grid > form,
            .event-page .control-btn-primary {
                flex: 1 1 100%;
                justify-content: center;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('[data-room-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var roomId = btn.dataset.roomTab;

                document.querySelectorAll('[data-room-tab]').forEach(function (b) {
                    b.classList.toggle('active', b === btn);
                });
                document.querySelectorAll('[data-room-panel]').forEach(function (pane) {
                    pane.classList.toggle('d-none', pane.dataset.roomPanel !== roomId);
                });
            });
        });

        var viewAllToggle = document.querySelector('[data-view-all-toggle]');
        var viewAllDays = document.querySelector('[data-view-all-days]');

        if (viewAllToggle && viewAllDays) {
            viewAllToggle.addEventListener('click', function () {
                var nowHidden = viewAllDays.classList.toggle('d-none');
                viewAllToggle.textContent = nowHidden
                    ? viewAllToggle.dataset.showLabel
                    : viewAllToggle.dataset.hideLabel;
            });
        }

        // Per-room "View All" overlay (room-view-all-overlay.blade.php) — this
        // is a page-scoped function, only ever wired to this page's own
        // data-room-view-all-* elements, so it can't reach the room-session
        // tablet's separate data-view-all-* overlay (queue-panel.blade.php,
        // positioned by its own script in home.blade.php) even though both
        // reuse the same .view-all-overlay CSS. Each trigger/panel/close
        // control is keyed by room id (deliberately distinct from the
        // "View All Days" attributes above, which are a single, page-wide
        // toggle). User-directed 2026-09-07: exactly half the viewport width,
        // sliding in from the right edge, spanning the full viewport height
        // — an earlier cut anchored its top to the room's "Room Data" column
        // instead (mimicking the room-session tablet's own overlay, which
        // aligns to #right-column), but that column sits well down this page
        // and left the panel only covering the lower part of the screen, so
        // this dropped that anchoring in favor of the plain top-to-bottom
        // fill the CSS default already gives it.
        function positionRoomViewAllOverlay(roomId) {
            var panel = document.querySelector('[data-room-view-all-panel="' + roomId + '"]');
            if (!panel) return;

            panel.style.top = '0';
            panel.style.height = '100vh';
            panel.style.left = 'auto';
            panel.style.right = '0';
            panel.style.width = '50vw';
        }

        document.addEventListener('click', function (event) {
            var opener = event.target.closest('[data-room-view-all-toggle]');
            if (opener) {
                var roomId = opener.dataset.roomViewAllToggle;
                var panel = document.querySelector('[data-room-view-all-panel="' + roomId + '"]');
                if (panel) {
                    positionRoomViewAllOverlay(roomId);
                    panel.classList.add('is-open');
                }
                return;
            }

            var closer = event.target.closest('[data-room-view-all-close]');
            if (closer) {
                var closePanel = document.querySelector('[data-room-view-all-panel="' + closer.dataset.roomViewAllClose + '"]');
                if (closePanel) closePanel.classList.remove('is-open');
            }
        });

        window.addEventListener('resize', function () {
            var openPanel = document.querySelector('[data-room-view-all-panel].is-open');
            if (openPanel) positionRoomViewAllOverlay(openPanel.dataset.roomViewAllPanel);
        });
    </script>
@endpush
