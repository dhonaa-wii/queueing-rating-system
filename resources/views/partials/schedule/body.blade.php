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
    {{-- Layout (user-directed 2026-10-13): the room status sits on top at the
    queue list's full width — times, counts, panelists and the current group
    on the left, the next three groups in three columns on the right — then
    the search bar and a searched group's status, then the queue list. --}}
    <div class="mb-4">
        @if ($rooms->isNotEmpty())
            <div class="card-brand p-3 mb-3">
            @foreach ($rooms as $room)
                @php $roomStarted = $room->live['started'] ?? false; @endphp
                <div class="{{ $room->name === $activeRoomName ? '' : 'd-none' }}" data-room-status-pane="{{ $room->name }}">
                    <div class="row g-3">
                        <div class="{{ $roomStarted ? 'col-lg-6' : 'col-12' }}">
                            @include('partials.schedule.status-card', ['room' => $room])
                            @if ($roomStarted)
                                @include('partials.schedule.current-group-card', ['room' => $room])
                            @endif
                        </div>
                        @if ($roomStarted)
                            <div class="col-lg-6 d-flex flex-column">
                                @include('partials.schedule.next-preview-card', ['room' => $room])
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
            </div>
        @endif

        <div class="sched-narrow">
            <div class="sched-finder mb-3">
                <form method="GET" action="{{ route($scheduleRouteName, $category) }}" role="search">
                    <label class="visually-hidden" for="schedule-search">Search by leader name, project title or group code</label>
                    <div class="sched-search {{ $search !== '' && $matches->count() > 1 && ! $selectedGroup ? 'has-results' : '' }}">
                        <span class="sched-search-icon"><x-icon name="search" /></span>
                        <input type="search" id="schedule-search" name="q" value="{{ $search }}" class="sched-search-input" placeholder="Search leader, title or group code" autocomplete="off">
                        @if ($search !== '')
                            <a href="{{ route($scheduleRouteName, $category) }}" class="sched-search-clear" title="Clear search" aria-label="Clear search"><x-icon name="x" /></a>
                        @endif
                        <button type="submit" class="sched-search-btn" aria-label="Search">
                            <x-icon name="search" /><span class="sched-search-btn-label">Search</span>
                        </button>
                    </div>
                </form>

                @if ($search !== '' && $matches->isEmpty())
                    <div class="sched-search-empty">
                        <x-icon name="alert-triangle" />
                        <span>No groups matched &ldquo;{{ $search }}&rdquo; in this category.</span>
                    </div>
                @elseif ($search !== '' && $matches->count() > 1 && ! $selectedGroup)
                    <div class="sched-search-results">
                        <div class="sched-search-results-head">{{ $matches->count() }} groups matched</div>
                        @foreach ($matches as $match)
                            <a href="{{ route($scheduleRouteName, ['category' => $category, 'q' => $search, 'group' => $match->group_reference]) }}"
                               class="sched-search-result">
                                <span class="sched-search-result-code">{{ $match->group_reference }}</span>
                                <span class="sched-search-result-body">
                                    <span class="sched-search-result-name">{{ $leader($match)->full_name ?? 'No leader recorded' }}</span>
                                    @if ($match->current_project_title)
                                        <span class="sched-search-result-title">{{ $match->current_project_title }}</span>
                                    @endif
                                </span>
                                <span class="sched-search-result-arrow" aria-hidden="true">&rsaquo;</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($selectedGroup)
                <div class="card-brand p-3 mb-3">
                    @php
                        $groupStatusLabelStyle = 'font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em; font-weight: 600;';
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
                        // A defer only stamps queue_entries.removed_at — the
                        // attempt keeps its SCHEDULED/QUEUED status unless it
                        // had been called — so the removed entry is the real
                        // marker, the same one the roster and My Assignments
                        // use. Without it a deferred group searched here read
                        // "Scheduled" (user-reported 2026-10-12).
                        $isDeferred = ! ($selectedAttempt?->presentationStatus?->is_terminal)
                            && ($selectedAttempt?->presentationStatus?->code === 'DEFERRED'
                                || $selectedAttempt?->attemptSchedule?->queueEntry?->removed_at !== null);
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
                            : ($hasAdjustedTime ? 'Expected Date & Time' : 'Scheduled Date & Time')));
                        $scheduleTimeValue = $isCompleted ? $selectedAttempt->completed_at
                            : ($isDeferred ? $latestDeferAdjustment?->adjusted_at : ($isAwaitingSchedule ? null : $scheduleDisplayTime));
                        $roomLabel = ($isCompleted || $isDeferred) ? 'Room' : 'Assigned Room';
                    @endphp
                    {{-- Redesigned 2026-08-22 (user-directed: "not too plain")
                    to match the visual language the neighboring
                    status-card/current-group-card/next-preview-card
                    partials already use — rounded brand-surface-alt chip
                    boxes, badge chips, the same avatar-circle treatment
                    admin/dashboard.blade.php's feature cards use
                    (badge-brand-tint + rounded-circle) — instead of a bare
                    dl.detail-list stack of label/value pairs. --}}
                    <div class="sched-group-body">
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
                            <span class="badge {{ $isDeferred ? 'badge-danger-tint' : ($selectedAttempt ? 'badge-info-tint' : 'badge-muted-tint') }}">
                                {{ $isDeferred ? 'Deferred' : ($selectedAttempt?->presentationStatus->name ?? 'Not yet queued') }}
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

                        <div class="mb-3 p-2 rounded-3 d-flex flex-wrap gap-3" style="background-color: var(--brand-surface-alt); font-size: 0.7rem;">
                            <div>
                                <div class="text-brand-muted" style="font-size: 0.7rem;">{{ $scheduleTimeLabel }}</div>
                                <div class="fw-semibold">
                                    @if ($isAwaitingSchedule)
                                        {{ \App\Models\AttemptSchedule::AWAITING_DATE }}
                                    @else
                                        {{ $scheduleTimeValue ? $scheduleTimeValue->format('D, M j, g:i A') : 'Not yet scheduled' }}
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-brand-muted" style="font-size: 0.7rem;">{{ $roomLabel }}</div>
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
                                    <div class="text-brand-muted" style="font-size: 0.7rem;">Defer Reason</div>
                                    <div class="fw-semibold">
                                        {{ $latestDeferAdjustment?->reason?->name ?? 'N/A' }}
                                        @if ($latestDeferAdjustment?->remarks)
                                            <span class="text-brand-muted fw-normal">— {{ $latestDeferAdjustment->remarks }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if (! $isCompleted && ! $isDeferred && ! $isAwaitingSchedule && $selectedSchedule?->isPastSessionEnd())
                            <div class="mt-n2 mb-3">
                                @include('partials.past-session-notice', [
                                    'sessionEnd' => $selectedSchedule->session_end_at,
                                    'forStudents' => $scheduleRouteName === 'student.categories.schedule',
                                ])
                            </div>
                        @endif

                        @if ($isDeferred)
                            <div class="mb-3 p-2 rounded-3" style="background-color: var(--brand-danger-tint, var(--brand-surface-alt)); font-size: 0.78rem;">
                                <div class="text-brand-muted mb-1" style="{{ $groupStatusLabelStyle }}">What to do</div>
                                <div class="mb-2">{{ \App\Support\DeferGuidance::howToResolve($latestDeferAdjustment?->reason?->code) }}</div>
                                <div class="text-brand-muted mb-1" style="{{ $groupStatusLabelStyle }}">If not resolved</div>
                                <div>{{ \App\Support\DeferGuidance::ifUnresolved($selectedAttempt?->attemptSchedule) }}</div>
                            </div>
                        @endif

                        <div>
                            {{-- Select Panel (user-directed 2026-10-11): a group that
                               registered before the category let groups choose its
                               panel, and has no panel yet, picks it once here. No
                               edit afterwards — the Admin changes it with Edit Panel.
                               Public student page only. --}}
                            @if ($scheduleRouteName === 'student.categories.schedule')
                                @if (session('panel_status'))
                                    <div class="alert alert-success small py-2 px-3 mt-2 mb-0">{{ session('panel_status') }}</div>
                                @elseif (session('panel_error'))
                                    <div class="alert alert-danger small py-2 px-3 mt-2 mb-0">{{ session('panel_error') }}</div>
                                @endif

                                @if (app(\App\Services\ResearchGroupRegistrationService::class)->canChoosePanelLater($selectedGroup, $selectedAttempt))
                                    <button type="button" class="btn btn-brand btn-sm mt-2" data-panel-pick-toggle>
                                        <x-icon name="user-plus" /> Select Panel
                                    </button>

                                    <form method="POST" action="{{ route('student.categories.choose-panel', [$category, $selectedGroup->group_reference]) }}"
                                          class="panel-pick-form mt-2 d-none" data-panel-pick-form>
                                        @csrf
                                        @for ($i = 0; $i < (int) $category->panelist_count; $i++)
                                            <div class="mb-2">
                                                <label for="panel_pick_{{ $i }}" class="form-label small mb-1">{{ $i === 0 ? 'Chair' : 'Member '.$i }} <span class="text-brand-accent">*</span></label>
                                                <select name="panelist_choices[{{ $i }}]" id="panel_pick_{{ $i }}" class="form-select form-select-sm" required>
                                                    <option value="">Select a panelist&hellip;</option>
                                                    @foreach ($category->choosablePanelists() as $panelist)
                                                        @php $panelistName = \App\Models\PresentationCategory::panelistName($panelist); @endphp
                                                        {{-- The group's own adviser can't sit on its panel. --}}
                                                        <option value="{{ $panelist->id }}" @disabled($panelistName === $selectedGroup->technical_adviser_name)>{{ $panelistName }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endfor
                                        <div class="small text-danger mb-2 d-none" data-panel-pick-error></div>
                                        <div class="d-flex gap-2 justify-content-end flex-wrap">
                                            <button type="button" class="btn btn-outline-brand btn-sm" data-panel-pick-toggle>Cancel</button>
                                            <button type="submit" class="btn btn-brand btn-sm"><x-icon name="save" /> Save Panel</button>
                                        </div>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

            @php
                // Scheduled and Awaiting Schedule share one card as two views
                // (user-directed 2026-10-11): room tabs belong to Scheduled
                // only, since an awaiting group has no room yet. A searched
                // group that is awaiting opens on its own view.
                $hasScheduledView = $rooms->isNotEmpty();
                $hasAwaitingView = $awaitingRows->isNotEmpty();
                $activeView = $hasScheduledView
                    && ! ($selectedGroup && $awaitingRows->contains(fn ($row) => $row->presentationAttempt?->research_group_id === $selectedGroup->id))
                    ? 'scheduled' : 'awaiting';
            @endphp

            @if ($hasScheduledView || $hasAwaitingView)
            <div class="card-brand p-3 mb-3">
                @if ($hasScheduledView && $hasAwaitingView)
                    <ul class="nav sched-tabs sched-view-tabs mb-3" role="tablist">
                        <li class="nav-item text-nowrap" role="presentation">
                            <button type="button" class="nav-link {{ $activeView === 'scheduled' ? 'active' : '' }}" data-sched-view-tab="scheduled" role="tab">Scheduled</button>
                        </li>
                        <li class="nav-item text-nowrap" role="presentation">
                            <button type="button" class="nav-link {{ $activeView === 'awaiting' ? 'active' : '' }}" data-sched-view-tab="awaiting" role="tab">
                                Awaiting Schedule <span class="badge badge-muted-tint ms-1">{{ $awaitingRows->count() }}</span>
                            </button>
                        </li>
                    </ul>
                @elseif ($hasAwaitingView)
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <h4 class="h6 text-brand-muted mb-0">Awaiting Schedule</h4>
                        <span class="badge badge-muted-tint">{{ $awaitingRows->count() }}</span>
                    </div>
                @endif

                @if ($hasAwaitingView)
                    <div class="{{ $activeView === 'awaiting' ? '' : 'd-none' }}" data-sched-view-pane="awaiting">
                        <div class="queue-scroll" data-queue-scroll>
                            @include('partials.schedule.queue-group-table', [
                                'rows' => $awaitingRows,
                                'variant' => 'awaiting',
                                'emptyText' => 'No groups are awaiting a schedule.',
                            ])
                        </div>
                    </div>
                @endif

                @if ($hasScheduledView)
                <div class="{{ $activeView === 'scheduled' ? '' : 'd-none' }}" data-sched-view-pane="scheduled">
                <ul class="nav sched-tabs mb-3 flex-nowrap overflow-x-auto overflow-y-hidden" role="tablist">
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

                                {{-- Scrolls inside the card so the sticky header stays in view. --}}
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

        /* Search bar — a standalone raised pill (no card around it): icon,
           field, clear and a round accent submit. Multiple matches hang
           off its bottom edge as one attached panel. */
        .sched-search {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            min-height: 3.1rem;
            padding: 0.3rem 0.3rem 0.3rem 1.1rem;
            border-radius: 999px;
            border: 1px solid color-mix(in srgb, var(--brand-border) 70%, transparent);
            background-color: var(--brand-surface);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04), 0 6px 20px -8px rgba(0, 0, 0, 0.18);
            transition: border-color 0.15s ease, box-shadow 0.2s ease;
        }

        .sched-search:hover { border-color: var(--brand-border); }

        .sched-search:focus-within {
            border-color: var(--brand-accent);
            box-shadow: 0 0 0 0.25rem color-mix(in srgb, var(--brand-accent) 16%, transparent),
                        0 8px 24px -10px color-mix(in srgb, var(--brand-accent) 45%, transparent);
        }

        .sched-search.has-results {
            border-radius: 1.55rem 1.55rem 0 0;
            border-color: var(--brand-accent);
        }

        .sched-search-icon {
            display: inline-flex;
            color: var(--brand-muted);
            flex-shrink: 0;
            transition: color 0.15s ease;
        }

        .sched-search:focus-within .sched-search-icon { color: var(--brand-accent); }

        .sched-search-icon svg { width: 1.15rem; height: 1.15rem; }

        .sched-search-input {
            flex: 1 1 auto;
            min-width: 0;
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--brand-text);
            font-size: clamp(0.88rem, 0.84rem + 0.18vw, 1rem);
            font-weight: 500;
            padding: 0.4rem 0.25rem;
        }

        .sched-search-input::placeholder { color: var(--brand-muted); opacity: 0.8; font-weight: 400; }

        /* The x is ours; hide the browser's own clear button on type=search. */
        .sched-search-input::-webkit-search-cancel-button { -webkit-appearance: none; appearance: none; }

        .sched-search-clear {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            flex-shrink: 0;
            color: var(--brand-muted);
            background-color: var(--brand-surface-alt);
            text-decoration: none;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .sched-search-clear:hover {
            background-color: var(--brand-accent-tint);
            color: var(--brand-accent);
        }

        .sched-search-clear svg { width: 0.9rem; height: 0.9rem; }

        .sched-search-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            height: 2.5rem;
            padding: 0 1.25rem 0 1rem;
            border: 0;
            border-radius: 999px;
            flex-shrink: 0;
            background-color: var(--brand-accent);
            color: #fff;
            font-weight: 600;
            font-size: clamp(0.82rem, 0.8rem + 0.12vw, 0.9rem);
            transition: background-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
        }

        .sched-search-btn:hover {
            background-color: color-mix(in srgb, var(--brand-accent) 86%, #000);
            box-shadow: 0 4px 12px -4px color-mix(in srgb, var(--brand-accent) 60%, transparent);
        }

        .sched-search-btn:active { transform: scale(0.97); }

        .sched-search-btn:focus-visible {
            outline: 0;
            box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--brand-accent) 35%, transparent);
        }

        .sched-search-btn svg { width: 1rem; height: 1rem; }

        @media (max-width: 575.98px) {
            .sched-search { padding-left: 0.9rem; }
            .sched-search-btn { width: 2.5rem; padding: 0; }
            .sched-search-btn-label { display: none; }
        }

        /* The Select Panel wrapper is always the card's last child and is
           usually empty, so the block before it must not leave a gap. */
        .sched-group-body > :nth-last-child(2) { margin-bottom: 0 !important; }

        .sched-search-empty {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.6rem;
            padding: 0.55rem 0.9rem;
            border-radius: 0.75rem;
            background-color: var(--brand-danger-tint);
            color: var(--brand-danger);
            font-size: clamp(0.78rem, 0.76rem + 0.1vw, 0.85rem);
        }

        .sched-search-empty svg { width: 1rem; height: 1rem; flex-shrink: 0; }

        .sched-search-results {
            border: 1px solid var(--brand-accent);
            border-top: 0;
            border-radius: 0 0 1.25rem 1.25rem;
            background-color: var(--brand-surface);
            box-shadow: 0 10px 24px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }

        .sched-search-results-head {
            padding: 0.55rem 1.1rem 0.35rem;
            border-top: 1px solid var(--brand-border);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--brand-muted);
        }

        .sched-search-result {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.6rem 1.1rem;
            color: var(--brand-text);
            text-decoration: none;
            border-top: 1px solid color-mix(in srgb, var(--brand-border) 60%, transparent);
            transition: background-color 0.12s ease;
        }

        .sched-search-result:hover,
        .sched-search-result:focus-visible {
            background-color: var(--brand-accent-tint);
            color: var(--brand-text);
            outline: 0;
        }

        .sched-search-result-code {
            flex-shrink: 0;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            background-color: var(--brand-surface-alt);
            font-size: 0.72rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .sched-search-result-body {
            display: flex;
            flex-direction: column;
            min-width: 0;
            flex: 1 1 auto;
        }

        .sched-search-result-name,
        .sched-search-result-title {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sched-search-result-name {
            font-size: clamp(0.82rem, 0.8rem + 0.1vw, 0.9rem);
            font-weight: 600;
        }

        .sched-search-result-title {
            font-size: 0.76rem;
            color: var(--brand-muted);
        }

        .sched-search-result-arrow {
            flex-shrink: 0;
            font-size: 1.3rem;
            line-height: 1;
            color: var(--brand-muted);
            transition: transform 0.15s ease, color 0.15s ease;
        }

        .sched-search-result:hover .sched-search-result-arrow {
            transform: translateX(3px);
            color: var(--brand-accent);
        }

        /* Room tabs as rounded pills instead of Bootstrap's boxed tabs. */
        .sched-tabs {
            gap: 0.4rem;
            padding: 0.25rem;
            border-radius: 999px;
            background-color: var(--brand-surface-alt);
            width: fit-content;
            max-width: 100%;
        }

        .sched-tabs .nav-link {
            border: 0;
            border-radius: 999px;
            padding: 0.4rem 1rem;
            font-size: clamp(0.8rem, 0.78rem + 0.12vw, 0.88rem);
            font-weight: 500;
            color: var(--brand-muted);
            background: transparent;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .sched-tabs .nav-link:hover { color: var(--brand-accent); }

        .sched-tabs .nav-link.active {
            background-color: var(--brand-surface);
            color: var(--brand-accent);
            font-weight: 600;
            box-shadow: var(--brand-shadow);
        }

        /* Room stats strip: label/value pairs, readable at any width. */
        .sched-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(6.5rem, 1fr));
            gap: 0.6rem 0.9rem;
        }

        .sched-stat-label {
            font-size: clamp(0.64rem, 0.62rem + 0.1vw, 0.7rem);
            color: var(--brand-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .sched-stat-value {
            font-size: clamp(0.8rem, 0.78rem + 0.15vw, 0.9rem);
            font-weight: 600;
        }

        tr.table-active > * {
            --bs-table-accent-bg: var(--brand-accent-tint);
        }

        /* Next three groups: one row each beside the room info. */
        .sched-next-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 0.5rem;
        }

        .sched-next-grid > div {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .sched-next-people {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 0.1rem 0.6rem;
            font-size: 0.74rem;
        }

        .sched-next-people dt {
            font-weight: 500;
            color: var(--brand-muted);
        }

        .sched-next-people dd {
            margin: 0;
        }

        /* Search and a searched group sit in one centered column between
           the full-width room status and the full-width queue list. */
        .sched-narrow {
            max-width: 46rem;
            margin-left: auto;
            margin-right: auto;
        }

        /* The queue list scrolls inside its card so its sticky header stays
           in view on a long queue; the page scrolls down to reach it. */
        .queue-scroll {
            max-height: 75vh;
            overflow-y: auto;
        }

        @media (max-width: 767.98px) {
            .queue-scroll {
                max-height: 65vh;
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
        // Select Panel: opens/closes the group's panel form, and stops a
        // submit that picks the same panelist twice (the server checks too).
        (function () {
            var form = document.querySelector('[data-panel-pick-form]');
            if (! form) return;
            var openBtn = form.previousElementSibling;
            var error = form.querySelector('[data-panel-pick-error]');

            document.querySelectorAll('[data-panel-pick-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var opening = form.classList.contains('d-none');
                    form.classList.toggle('d-none', ! opening);
                    if (openBtn) openBtn.classList.toggle('d-none', opening);
                    if (opening) form.querySelector('select').focus();
                });
            });

            form.addEventListener('submit', function (e) {
                var seen = [];
                var duplicate = false;
                form.querySelectorAll('select').forEach(function (select) {
                    if (select.value && seen.indexOf(select.value) !== -1) duplicate = true;
                    seen.push(select.value);
                });
                error.classList.toggle('d-none', ! duplicate);
                if (duplicate) {
                    e.preventDefault();
                    error.textContent = 'Choose a different panelist for each seat.';
                    return;
                }
                form.querySelector('button[type="submit"]').disabled = true;
            });
        })();

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
            });
        });

        // Scheduled / Awaiting Schedule views. Room tabs live inside the
        // Scheduled pane, so they hide with it.
        document.querySelectorAll('[data-sched-view-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var view = btn.dataset.schedViewTab;

                document.querySelectorAll('[data-sched-view-tab]').forEach(function (b) {
                    b.classList.toggle('active', b === btn);
                });
                document.querySelectorAll('[data-sched-view-pane]').forEach(function (pane) {
                    pane.classList.toggle('d-none', pane.dataset.schedViewPane !== view);
                });
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
