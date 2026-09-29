{{--
    The schedule/queue body, shared by Student Schedule Viewing and Panelist
    Schedule Viewing. Both render the identical page — only the layout and
    the back-link differ — so the route to link back to is passed in.

    Expects the view-model CategoryScheduleViewService::build() returns:
    $category, $rooms, $search, $matches, $selectedGroup, $selectedAttempt,
    $activeRoomName, plus $scheduleRouteName from the including page.

    History: this was forked out of partials/category-schedule-body.blade.php
    on 2026-08-22 so the right column could be redesigned to match the
    room-session tablet's Presentation Control card without disturbing the
    Panelist page, which stayed on the original. User-directed 2026-09-13,
    the Panelist page was moved onto this design too ("this is outdated
    feature, make this like the student view schedule") and the old partial
    was deleted, so the fork is over and there is one body again.

    Uses no Bootstrap JS — all behaviour here is hand-rolled vanilla — which
    is what lets it render under layouts/app (Student, no Bootstrap bundle)
    and layouts/panelist alike. Keep it that way.
--}}
@php
    $isTitleProposal = $category->presentationMode->code === 'TITLE_PROPOSAL';
    $leader = fn ($group) => $group?->leader();
    // Groups whose day is over and who still have to present. Once every day
    // a category has is finished or cancelled, this is the whole queue and
    // there are no room tabs left at all — the page is this list.
    $awaitingRows = $awaitingRows ?? collect();
    // Deferred groups whose room has no tab left — listed here so they are
    // not dropped off the page entirely once every day is finished.
    $orphanedDeferredRows = $orphanedDeferredRows ?? collect();
@endphp

@if ($rooms->isEmpty() && $awaitingRows->isEmpty() && $orphanedDeferredRows->isEmpty())
    <div class="card-brand p-5 text-center text-brand-muted mb-4">
        No presentation dates or rooms have been configured for this category yet. Check back once the schedule is published.
    </div>
@else
    @php
        // With every day finished there are no room tabs and nothing for the
        // room-status column to describe, so the queue list takes the full
        // width unless a searched group still has a card to show there.
        $showStatusColumn = $rooms->isNotEmpty() || (bool) $selectedGroup;
    @endphp
    <div class="row g-4 mb-4 align-items-start">
        {{-- LEFT: search + room tabs + queue list --}}
        <div class="{{ $showStatusColumn ? 'col-md-6' : 'col-12' }}">
            <div class="card-brand p-3 mb-3">
                <form method="GET" action="{{ route($scheduleRouteName, $category) }}">
                    <label class="form-label small mb-1" for="schedule-search">Search by leader name or project title</label>
                    <div class="input-group">
                        <input type="text" id="schedule-search" name="q" value="{{ $search }}" class="form-control" placeholder="e.g. Dela Cruz">
                        <button type="submit" class="btn btn-brand"><x-icon name="search" /> Search</button>
                        @if ($search !== '')
                            <a href="{{ route($scheduleRouteName, $category) }}" class="btn btn-outline-brand"><x-icon name="x" /> Clear</a>
                        @endif
                    </div>
                    <div class="form-text">Searches registered groups in this category only.</div>
                </form>

                @if ($search !== '' && $matches->isEmpty())
                    <div class="alert alert-danger py-2 px-3 small mt-3 mb-0" style="background-color: var(--brand-danger-tint); border-color: var(--brand-danger); color: var(--brand-danger);">
                        No groups matched &ldquo;{{ $search }}&rdquo; in this category.
                    </div>
                @elseif ($search !== '' && $matches->count() > 1 && ! $selectedGroup)
                    <p class="small text-brand-muted mt-3 mb-2">Multiple groups matched &mdash; select one:</p>
                    <div class="d-flex flex-column gap-2">
                        @foreach ($matches as $match)
                            <a href="{{ route($scheduleRouteName, ['category' => $category, 'q' => $search, 'group' => $match->group_reference]) }}"
                               class="card-brand p-2 small text-decoration-none text-reset">
                                <strong>{{ $match->group_reference }}</strong> &mdash; {{ $leader($match)->full_name ?? 'No leader recorded' }}
                                @if ($match->current_project_title)
                                    <br><span class="text-brand-muted">{{ $match->current_project_title }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @elseif ($selectedGroup)
                    <div class="alert alert-info py-2 px-3 small mt-3 mb-0" style="background-color: var(--brand-info-tint); border-color: var(--brand-info); color: var(--brand-info);">
                        Showing <strong>{{ $selectedGroup->group_reference }}</strong> &mdash; {{ $leader($selectedGroup)->full_name ?? 'N/A' }}.
                        <a href="{{ route($scheduleRouteName, $category) }}" class="alert-link">Clear selection</a>
                    </div>
                @endif
            </div>

            @if ($awaitingRows->isNotEmpty())
                <div class="card-brand p-3 mb-3">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <h4 class="h6 text-brand-muted mb-0">Awaiting Schedule</h4>
                        <span class="badge badge-muted-tint">{{ $awaitingRows->count() }}</span>
                    </div>
                    <div class="queue-scroll" data-queue-scroll>
                        @include('partials.schedule.queue-group-table', [
                            'rows' => $awaitingRows,
                            'variant' => 'awaiting',
                            'emptyText' => 'No groups are awaiting a schedule.',
                        ])
                    </div>
                </div>
            @endif

            @if ($orphanedDeferredRows->isNotEmpty())
                <div class="card-brand p-3 mb-3">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <h4 class="h6 text-brand-muted mb-0">Deferred</h4>
                        <span class="badge badge-muted-tint">{{ $orphanedDeferredRows->count() }}</span>
                    </div>
                    {{-- Capped by CSS only, not JS-sized like the list above
                    it: two lists both claiming the rest of the viewport would
                    leave this one 220px tall whatever its length. --}}
                    <div class="queue-scroll">
                        @include('partials.schedule.queue-group-table', [
                            'rows' => $orphanedDeferredRows,
                            'variant' => 'deferred',
                            'emptyText' => 'No groups have been deferred.',
                        ])
                    </div>
                </div>
            @endif

            @if ($rooms->isNotEmpty())
            <div class="card-brand p-3">
                <ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto" role="tablist">
                    @foreach ($rooms as $room)
                        <li class="nav-item text-nowrap" role="presentation">
                            <button type="button" class="nav-link {{ $room->name === $activeRoomName ? 'active' : '' }}"
                                    data-room-tab="{{ $room->name }}" role="tab">
                                {{ $room->name }}
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div>
                    @foreach ($rooms as $room)
                        <div class="{{ $room->name === $activeRoomName ? '' : 'd-none' }}" data-room-queue-pane="{{ $room->name }}">
                            @if ($room->queueRows->isEmpty() && $room->deferredRows->isEmpty())
                                <p class="text-brand-muted small mb-0">Queue not generated yet for this room.</p>
                            @else
                                <div class="d-flex justify-content-between align-items-center mb-2" data-queue-filter>
                                    <h4 class="h6 text-brand-muted mb-0" data-queue-filter-label>Scheduled</h4>
                                    <div class="queue-filter-wrap" data-queue-filter-wrap>
                                        <button type="button" class="queue-filter-btn" data-queue-filter-btn aria-haspopup="true" aria-expanded="false" aria-label="Filter queue list">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                                        </button>
                                        <div class="queue-filter-menu" data-queue-filter-menu>
                                            <label class="queue-filter-option">
                                                <input type="radio" name="queue-filter-{{ $room->name }}" class="form-check-input m-0" data-queue-filter-option="scheduled" checked>
                                                Scheduled
                                            </label>
                                            <label class="queue-filter-option">
                                                <input type="radio" name="queue-filter-{{ $room->name }}" class="form-check-input m-0" data-queue-filter-option="completed">
                                                Completed
                                            </label>
                                            <label class="queue-filter-option">
                                                <input type="radio" name="queue-filter-{{ $room->name }}" class="form-check-input m-0" data-queue-filter-option="deferred">
                                                Deferred
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                {{-- The queue list is the page's scroller (user-directed
                                2026-09-13): it is the only part that grows without bound,
                                so it takes its own scrollbar and the search box, room tabs
                                and filter above it stay put while a long queue is browsed. --}}
                                                <div class="queue-scroll" data-queue-scroll>
                                    <div data-queue-section="scheduled">
                                        @include('partials.schedule.queue-group-table', ['rows' => $room->scheduledRows, 'variant' => 'scheduled', 'emptyText' => 'No groups currently scheduled in this room.'])
                                    </div>
                                    <div class="d-none" data-queue-section="completed">
                                        @include('partials.schedule.queue-group-table', ['rows' => $room->completedRows, 'variant' => 'completed', 'emptyText' => 'No groups have completed in this room yet.'])
                                    </div>
                                    <div class="d-none" data-queue-section="deferred">
                                        @include('partials.schedule.queue-group-table', ['rows' => $room->deferredRows, 'variant' => 'deferred', 'emptyText' => 'No groups have been deferred in this room.'])
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- RIGHT: dynamic room status card — same "Presentation Control"
        layout as the room-session tablet's Terminal 2/3 right card
        (status-card.blade.php + current-group-card.blade.php +
        next-preview-card.blade.php, all student-schedule-only forks), in
        place of the old plain "Room Status" panel. --}}
        @if ($showStatusColumn)
        <div class="col-md-6">
            <div class="card-brand p-3">
                @if ($selectedGroup)
                    @php
                        $groupStatusLabelStyle = 'font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.03em; font-weight: 600;';
                        $leaderName = $leader($selectedGroup)->full_name ?? 'N/A';
                        $leaderInitials = strtoupper(collect(preg_split('/\s+/', trim($leaderName)))
                            ->filter()
                            ->map(fn ($part) => mb_substr($part, 0, 1))
                            ->take(2)
                            ->implode(''));

                        $selectedSchedule = $selectedAttempt?->attemptSchedule;
                        // On a day that has not started, expected_start_at
                        // simply mirrors planned_start_at (see
                        // CategoryScheduleViewService::applyExpectedTimes()),
                        // so this shows the plain planned schedule under a
                        // "Planned" label. Once the day is running, every
                        // remaining schedule gets a live-cascaded time —
                        // shown here as the ONLY time (no secondary
                        // "originally planned" line) under a
                        // relabeled "Expected" heading, so there's
                        // always exactly one date/time on screen, not
                        // two competing ones (user-directed 2026-08-22).
                        $hasAdjustedTime = $selectedSchedule?->expected_start_at
                            && $selectedSchedule->planned_start_at
                            && ! $selectedSchedule->expected_start_at->equalTo($selectedSchedule->planned_start_at);
                        $scheduleDisplayTime = $hasAdjustedTime ? $selectedSchedule->expected_start_at : $selectedSchedule?->planned_start_at;

                        // Once a group is resolved (completed/deferred), the
                        // planned/expected slot is no longer relevant — show
                        // when it actually happened instead, and drop
                        // "Assigned" from the room label since the room is
                        // no longer a forward-looking assignment either
                        // (user-directed 2026-08-22).
                        $isCompleted = $selectedAttempt?->presentationStatus?->code === 'COMPLETED';
                        $isDeferred = $selectedAttempt?->presentationStatus?->code === 'DEFERRED';
                        $latestDeferAdjustment = $isDeferred
                            ? $selectedAttempt?->attemptSchedule?->queueEntry?->queueAdjustments?->first()
                            : null;

                        // Still to present, but the day it was placed on can
                        // no longer run (user-directed 2026-09-16) — the slot
                        // and the room it still carries both belong to a day
                        // that is over, so neither is shown: the card says
                        // what is actually true, that a new date has to be
                        // set before this group has a schedule again.
                        $isAwaitingSchedule = (bool) $selectedSchedule?->isAwaitingReschedule($selectedAttempt);

                        $scheduleTimeLabel = $isCompleted ? 'Completed Date & Time'
                            : ($isDeferred ? 'Deferred Date & Time'
                            : ($isAwaitingSchedule ? 'Presentation Date & Time'
                            : ($hasAdjustedTime ? 'Expected Date & Time' : 'Planned Date & Time')));
                        $scheduleTimeValue = $isCompleted ? $selectedAttempt->completed_at
                            : ($isDeferred ? $latestDeferAdjustment?->adjusted_at : ($isAwaitingSchedule ? null : $scheduleDisplayTime));
                        $roomLabel = ($isCompleted || $isDeferred) ? 'Room' : 'Assigned Room';

                        $activeAssignments = ($selectedAttempt?->attemptPanelAssignments ?? collect())
                            ->whereNotIn('assignmentStatus.code', ['REPLACED', 'WITHDRAWN']);
                    @endphp
                    {{-- Redesigned 2026-08-22 (user-directed: "not too plain")
                    to match the visual language the neighboring
                    status-card/current-group-card/next-preview-card
                    partials already use — rounded brand-surface-alt chip
                    boxes, badge chips, the same avatar-circle treatment
                    admin/dashboard.blade.php's feature cards use
                    (badge-brand-tint + rounded-circle) — instead of a bare
                    dl.detail-list stack of label/value pairs. --}}
                    <div class="mb-3 pb-3 border-bottom-brand">
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="badge-brand-tint rounded-circle d-flex align-items-center justify-content-center fw-semibold flex-shrink-0" style="width: 2.25rem; height: 2.25rem; font-size: 0.8rem;">
                                    {{ $leaderInitials ?: '?' }}
                                </div>
                                <div>
                                    <div class="text-brand-muted" style="{{ $groupStatusLabelStyle }}">Group Status</div>
                                    <div class="fw-semibold" style="font-size: 0.95rem;">{{ $selectedGroup->group_reference }}</div>
                                </div>
                            </div>
                            <span class="badge {{ $selectedAttempt ? 'badge-info-tint' : 'badge-muted-tint' }}">
                                {{ $selectedAttempt?->presentationStatus->name ?? 'Not yet queued' }}
                            </span>
                        </div>

                        <div class="mb-3 p-2 rounded-3" style="background-color: var(--brand-surface-alt);">
                            <div class="text-brand-muted mb-1" style="{{ $groupStatusLabelStyle }}">
                                {{ $isTitleProposal ? 'Proposed Titles' : 'Project Title' }}
                            </div>
                            @if ($isTitleProposal)
                                @forelse ($selectedGroup->proposedTitles as $title)
                                    <div class="small mb-1">
                                        {{ $loop->iteration }}. {{ $title->title_text }}
                                        @if ($title->is_approved)<span class="badge badge-success-tint ms-1">Approved</span>@endif
                                    </div>
                                @empty
                                    <div class="small text-brand-muted">None recorded</div>
                                @endforelse
                            @else
                                <div class="small fw-semibold">{{ $selectedGroup->current_project_title ?: '—' }}</div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <div class="text-brand-muted mb-1" style="{{ $groupStatusLabelStyle }}">Leader</div>
                            <span class="badge badge-brand-tint mb-2 d-inline-block">{{ $leaderName }}</span>

                            <div class="text-brand-muted mb-1" style="{{ $groupStatusLabelStyle }}">Members</div>
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                @php $members = $selectedGroup->students->where('is_leader', false); @endphp
                                @forelse ($members as $member)
                                    <span class="badge badge-muted-tint">{{ $member->full_name }}{{ $member->section_name ? ' · ' . $member->section_name : '' }}</span>
                                @empty
                                    <span class="small text-brand-muted">No additional members recorded</span>
                                @endforelse
                            </div>

                            @if ($selectedGroup->technical_adviser_name)
                                <div class="text-brand-muted mb-1" style="{{ $groupStatusLabelStyle }}">Technical Adviser</div>
                                <span class="badge badge-muted-tint">{{ $selectedGroup->technical_adviser_name }}</span>
                            @endif
                        </div>

                        <div class="mb-3 p-2 rounded-3 d-flex flex-wrap gap-3" style="background-color: var(--brand-surface-alt); font-size: 0.7rem;">
                            <div>
                                <div class="text-brand-muted" style="font-size: 0.6rem;">{{ $scheduleTimeLabel }}</div>
                                <div class="fw-semibold">
                                    @if ($isAwaitingSchedule)
                                        {{ \App\Models\AttemptSchedule::AWAITING_DATE }}
                                    @else
                                        {{ $scheduleTimeValue ? $scheduleTimeValue->format('D, M j, g:i A') : 'Not yet scheduled' }}
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-brand-muted" style="font-size: 0.6rem;">{{ $roomLabel }}</div>
                                <div class="fw-semibold">
                                    @if ($isAwaitingSchedule)
                                        {{ \App\Models\AttemptSchedule::AWAITING_ROOM }}
                                    @else
                                        {{ $selectedAttempt?->attemptSchedule?->presentationDateRoom?->room_name ?? 'Not yet assigned' }}
                                    @endif
                                </div>
                            </div>
                            @if ($isDeferred)
                                <div>
                                    <div class="text-brand-muted" style="font-size: 0.6rem;">Defer Reason</div>
                                    <div class="fw-semibold">
                                        {{ $latestDeferAdjustment?->reason?->name ?? 'N/A' }}
                                        @if ($latestDeferAdjustment?->remarks)
                                            <span class="text-brand-muted fw-normal">— {{ $latestDeferAdjustment->remarks }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div>
                            <div class="text-brand-muted mb-1" style="{{ $groupStatusLabelStyle }}">Assigned Panelists</div>
                            <div class="d-flex flex-wrap gap-1">
                                @php $activeMemberNumbers = \App\Models\AttemptPanelAssignment::memberNumbers($activeAssignments); @endphp
                                @forelse ($activeAssignments as $assignment)
                                    @php
                                        $panelistName = trim(($assignment->panelist->profile->first_name ?? '') . ' ' . ($assignment->panelist->profile->last_name ?? '')) ?: 'N/A';
                                        $isBackup = $assignment->assignmentKind?->code === 'BACKUP_PANELIST';
                                        $seatPrefix = $isBackup
                                            ? 'Alternate Panel: '
                                            : ($assignment->is_lead ? 'Chair: ' : 'Member ' . ($activeMemberNumbers[$assignment->id] ?? '') . ': ');
                                    @endphp
                                    <span class="badge {{ $isBackup ? 'badge-brand-tint' : 'badge-muted-tint' }}">{{ $seatPrefix . $panelistName }}</span>
                                @empty
                                    <span class="small text-brand-muted">Not yet assigned</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif

                @foreach ($rooms as $room)
                    <div class="{{ $room->name === $activeRoomName ? '' : 'd-none' }}" data-room-status-pane="{{ $room->name }}">
                        @include('partials.schedule.status-card', ['room' => $room])
                        @include('partials.schedule.current-group-card', ['room' => $room])
                        @include('partials.schedule.next-preview-card', ['room' => $room])
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
@endif

@if ($category->categoryAnnouncements->isNotEmpty())
    <div id="announcements" class="card-brand p-4">
        <h2 class="h6 mb-3"><x-icon name="megaphone" style="width:1.1rem;height:1.1rem;color:var(--brand-accent);vertical-align:-0.2em" /> Announcements</h2>
        <div class="d-flex flex-column gap-3">
            @foreach ($category->categoryAnnouncements->sortBy(fn ($a) => $a->isPaymentInstructions() ? 0 : 1) as $announcement)
                <div class="alert alert-info py-2 px-3 small mb-0 d-flex gap-2" style="background-color: var(--brand-info-tint); border-color: var(--brand-info); color: var(--brand-info);">
                    <x-icon name="{{ $announcement->isPaymentInstructions() ? 'wallet' : 'megaphone' }}" style="width:1.1rem;height:1.1rem;flex-shrink:0;margin-top:0.1rem" />
                    <div>
                        <strong>{{ $announcement->title }}</strong>
                        <p class="mb-0" style="white-space: pre-line">{{ $announcement->message }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@push('styles')
    <style>
        .border-bottom-brand {
            border-bottom: 1px solid var(--brand-border);
        }

        tr.table-active > * {
            --bs-table-accent-bg: var(--brand-accent-tint);
        }

        /* The queue list is the only thing on this page that grows without
           bound, so it is the only thing that scrolls — everything else
           (search, room tabs, the room-status column) stays in place.
           Viewport-relative rather than a fixed pixel height because the
           two layouts this body renders under put a different amount of
           chrome above it: layouts/app scrolls the document, while
           layouts/panelist scrolls .admin-content. `contain` stops a
           finished scroll from continuing into whichever of those it is. */
        .queue-scroll {
            max-height: calc(100vh - 22rem);
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        /* Stacked single column — the room-status cards sit below the list
           rather than beside it, so the list can afford less of the screen. */
        @media (max-width: 767.98px) {
            .queue-scroll {
                max-height: 60vh;
            }
        }

        /* Bootstrap caps .container at 720px for the whole 768–991px band, so a
           9–10" tablet (roughly 850–960 CSS px in landscape) would squeeze the
           queue table and the room-status column into 720px and leave up to
           240px of screen unused. Use the real width there; 992px and up keeps
           Bootstrap's own caps. Scoped by this partial's own stylesheet, which
           only loads on the two schedule pages. */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .container {
                max-width: none;
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
        }

        /* The sticky header needs its own background or rows show through
           it as they scroll under. */
        .queue-scroll thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background-color: var(--brand-surface);
        }

        /* Scheduled/Completed/Deferred queue filter — hand-rolled (not
           Bootstrap's dropdown) since layouts/app.blade.php never loads
           bootstrap.bundle.min.js on Student pages. Opens on hover or click
           (user-directed 2026-08-22), closes on mouseleave or an outside
           click. */
        .queue-filter-wrap {
            position: relative;
        }

        .queue-filter-btn {
            border: 1px solid transparent;
            background: var(--brand-surface-alt);
            color: var(--brand-muted);
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
        }

        .queue-filter-btn svg {
            width: 1rem;
            height: 1rem;
        }

        .queue-filter-btn:hover,
        .queue-filter-wrap.is-open .queue-filter-btn {
            background-color: var(--brand-accent-tint);
            color: var(--brand-accent);
        }

        .queue-filter-menu {
            position: absolute;
            top: calc(100% + 0.4rem);
            right: 0;
            min-width: 10rem;
            z-index: 20;
            background-color: var(--brand-surface);
            border: 1px solid var(--brand-border);
            border-radius: 0.5rem;
            box-shadow: var(--brand-shadow);
            padding: 0.4rem;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-4px);
            transition: opacity 0.15s ease, transform 0.15s ease, visibility 0.15s ease;
        }

        .queue-filter-wrap.is-open .queue-filter-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .queue-filter-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.5rem;
            border-radius: 0.35rem;
            font-size: 0.8rem;
            cursor: pointer;
            margin-bottom: 0;
        }

        .queue-filter-option:hover {
            background-color: var(--brand-accent-tint);
        }
    </style>
@endpush

@push('scripts')
    <script>
        // Sizes the queue list so its bottom edge lands just above the
        // viewport bottom, making it the page's only scroller. The CSS
        // max-height is a fallback: the real distance from the top of the
        // screen to the top of the list depends on how tall the search card
        // and tab strip wrapped, and on which layout this body is rendered
        // under (Student's document scroll vs. the Panelist shell's
        // .admin-content), none of which CSS can measure. Same JS-sized
        // column approach already used by Group & Panel Assignment's
        // Assign Panel card.
        function sizeQueueLists() {
            // Below md the two columns stack, so filling the viewport here
            // would push the room-status cards off the bottom entirely —
            // the CSS 60vh cap is the right answer on a narrow screen.
            var stacked = window.matchMedia('(max-width: 767.98px)').matches;

            document.querySelectorAll('[data-queue-scroll]').forEach(function (el) {
                // offsetParent is null for the hidden room panes — measuring
                // those would give a meaningless 0.
                if (! el.offsetParent) return;

                el.style.maxHeight = '';

                if (stacked) return;

                var top = el.getBoundingClientRect().top;
                el.style.maxHeight = Math.max(220, window.innerHeight - top - 24) + 'px';
            });
        }

        sizeQueueLists();
        window.addEventListener('resize', sizeQueueLists);

        document.querySelectorAll('[data-room-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var roomId = btn.dataset.roomTab;

                document.querySelectorAll('[data-room-tab]').forEach(function (b) {
                    b.classList.toggle('active', b === btn);
                });
                document.querySelectorAll('[data-room-queue-pane]').forEach(function (pane) {
                    pane.classList.toggle('d-none', pane.dataset.roomQueuePane !== roomId);
                });
                document.querySelectorAll('[data-room-status-pane]').forEach(function (pane) {
                    pane.classList.toggle('d-none', pane.dataset.roomStatusPane !== roomId);
                });

                // The newly-revealed pane was unmeasurable while hidden.
                sizeQueueLists();
            });
        });

        var highlightedRow = document.querySelector('tr.table-active[id^="queue-row-"]');
        if (highlightedRow) {
            highlightedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Scheduled/Completed/Deferred queue filter — one independent
        // instance per room pane (a category can have several rooms, all
        // present in the DOM at once, just d-none'd except the active tab),
        // scoped via closest()/querySelectorAll rather than ids so they
        // never collide.
        (function () {
            var statusLabels = { scheduled: 'Scheduled', completed: 'Completed', deferred: 'Deferred' };

            document.querySelectorAll('[data-queue-filter]').forEach(function (filterBar) {
                var wrap = filterBar.querySelector('[data-queue-filter-wrap]');
                var btn = filterBar.querySelector('[data-queue-filter-btn]');
                var label = filterBar.querySelector('[data-queue-filter-label]');
                var pane = filterBar.closest('[data-room-queue-pane]');
                var radios = filterBar.querySelectorAll('[data-queue-filter-option]');

                function applyFilter() {
                    radios.forEach(function (radio) {
                        var status = radio.dataset.queueFilterOption;
                        var section = pane ? pane.querySelector('[data-queue-section="' + status + '"]') : null;

                        if (section) {
                            section.classList.toggle('d-none', !radio.checked);
                        }

                        if (radio.checked) {
                            label.textContent = statusLabels[status];
                        }
                    });
                }

                radios.forEach(function (radio) {
                    radio.addEventListener('change', applyFilter);
                });

                function openMenu() {
                    wrap.classList.add('is-open');
                    btn.setAttribute('aria-expanded', 'true');
                }

                function closeMenu() {
                    wrap.classList.remove('is-open');
                    btn.setAttribute('aria-expanded', 'false');
                }

                btn.addEventListener('click', function (event) {
                    event.stopPropagation();
                    wrap.classList.contains('is-open') ? closeMenu() : openMenu();
                });

                wrap.addEventListener('mouseenter', openMenu);
                wrap.addEventListener('mouseleave', closeMenu);

                applyFilter();
            });

            document.addEventListener('click', function (event) {
                document.querySelectorAll('[data-queue-filter-wrap].is-open').forEach(function (openWrap) {
                    if (!openWrap.contains(event.target)) {
                        openWrap.classList.remove('is-open');
                        var openBtn = openWrap.querySelector('[data-queue-filter-btn]');
                        if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            });
        })();

        // Ticks the Current Group timer badge(s) every second (user-directed
        // 2026-08-22: an earlier cut only rendered a one-shot PHP snapshot).
        // Deliberately its own small copy rather than reusing room-session's
        // tickTimers() (per the same "keep them independently editable"
        // direction as the badge partials themselves) — same data-timer-*
        // attribute contract, written once in current-group-card.blade.php,
        // read here. This page still has no 5s poll refreshing the
        // underlying who's-called/started data (see next-preview-card.blade.php),
        // so only the displayed clock genuinely counts — the room tabs can
        // carry more than one of these badges at once (one per room, all
        // present in the DOM even while their pane is hidden), so every tick
        // updates all of them regardless of which tab is active.
        (function () {
            function formatDuration(totalSeconds) {
                var abs = Math.round(Math.abs(totalSeconds));
                var m = Math.floor(abs / 60);
                var s = abs % 60;
                return m + ':' + (s < 10 ? '0' : '') + s;
            }

            function tickScheduleTimers() {
                document.querySelectorAll('[data-timer-badge]').forEach(function (el) {
                    el.classList.remove('badge-success-tint', 'badge-brand-tint', 'badge-danger-tint', 'badge-info-tint', 'badge-muted-tint');

                    if (el.dataset.timerMode === 'waiting') {
                        var deadline = el.dataset.waitingDeadlineAt;
                        if (!deadline) {
                            el.classList.add('badge-muted-tint');
                            el.textContent = 'Waiting';
                            return;
                        }

                        // Counts DOWN to 00:00, not up — matches the
                        // room-session tablet's timer (2026-08-22).
                        var remainingSeconds = (new Date(deadline).getTime() - new Date().getTime()) / 1000;
                        var overdue = remainingSeconds <= 0;

                        el.classList.add(overdue ? 'badge-danger-tint' : 'badge-muted-tint');
                        el.textContent = 'Waiting ' + formatDuration(Math.max(0, remainingSeconds));
                        return;
                    }

                    var statusCode = el.dataset.timerStatus;
                    var startedAt = el.dataset.startedAt;

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

            tickScheduleTimers();
            setInterval(tickScheduleTimers, 1000);
        })();
    </script>
@endpush
