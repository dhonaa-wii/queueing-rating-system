@extends('layouts.panelist')

@section('title', 'My Assignments')
@section('heading', 'My Assignments')

@section('content')
    @php
        $total = $scheduled->count() + $deferred->count() + $completed->count();

        // Title Proposal groups carry several titles and no single project
        // title, so fall back to the proposed set rather than an empty cell.
        $titleOf = function ($group) {
            return $group->current_project_title
                ?: $group->proposedTitles->pluck('title_text')->filter()->implode('; ');
        };

        // Lowercased haystack for the text search, plus the exact track and
        // section values the two dropdowns match against. Sections are
        // pipe-delimited because a group's members can sit in different ones.
        $searchAttrs = function ($assignment, $group, $room) use ($titleOf) {
            $tracks = $group->students->pluck('research_track_name')->filter()->map(fn ($t) => mb_strtolower(trim($t)))->unique()->values();
            $track = '|' . $tracks->implode('|') . '|';
            $sections = $group->students->pluck('section_name')->filter()->map(fn ($s) => mb_strtolower(trim($s)))->unique()->values();
            $all = mb_strtolower(implode(' ', array_filter([
                $group->group_reference,
                $titleOf($group),
                $group->category->name,
                $room->room_name ?? null,
            ])));

            return 'data-search="' . e($all) . '" data-track="' . e($track) . '" data-sections="' . e('|' . $sections->implode('|') . '|') . '"';
        };
    @endphp

    <div class="page-shell myassign-page">
        @if ($categoryOptions->isEmpty())
            <div class="card-brand p-5 text-center text-brand-muted">
                No panel assignments yet. Once an Administrator assigns you to a presentation, it will show up here.
            </div>
        @else
            {{-- One toolbar row, same shape as Group & Panel Assignment's: search
            on the left, the category picker pinned to the right. --}}
            <div class="assign-toolbar d-flex flex-wrap align-items-start gap-2 mb-3">
                <div class="assign-toolbar-controls d-flex flex-wrap align-items-center gap-2">
                    <input type="search" id="myassign-search" class="form-control myassign-search"
                           placeholder="Search group, title, room…" autocomplete="off">
                    <select id="myassign-track" class="form-select myassign-filter" aria-label="Track">
                        <option value="">All Tracks</option>
                        @foreach ($trackOptions as $track)
                            <option value="{{ mb_strtolower(trim($track)) }}">{{ $track }}</option>
                        @endforeach
                    </select>
                    <select id="myassign-section" class="form-select myassign-filter" aria-label="Section">
                        <option value="">All Sections</option>
                        @foreach ($sectionOptions as $section)
                            <option value="{{ mb_strtolower(trim($section)) }}">{{ $section }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="assign-toolbar-right d-flex align-items-center gap-2 ms-auto flex-shrink-0">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-brand dropdown-toggle category-picker-btn" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <x-icon name="list-check" />
                            {{ $selectedCategoryId ? $categoryOptions->firstWhere('id', $selectedCategoryId)?->name : 'All Categories' }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end category-picker-menu">
                            <li>
                                <a class="dropdown-item category-picker-item {{ ! $selectedCategoryId ? 'active' : '' }}"
                                   href="{{ route('panelist.assignments.index') }}">
                                    <span><span class="category-picker-item-name">All Categories</span></span>
                                    @if (! $selectedCategoryId)
                                        <x-icon name="check" class="category-picker-item-icon" />
                                    @endif
                                </a>
                            </li>
                            @foreach ($categoryOptions as $option)
                                <li>
                                    <a class="dropdown-item category-picker-item {{ $selectedCategoryId === $option->id ? 'active' : '' }}"
                                       href="{{ route('panelist.assignments.index', ['category' => $option->id]) }}">
                                        <span>
                                            <span class="category-picker-item-name">{{ $option->name }}</span>
                                            <span class="category-picker-item-meta d-block">
                                                {{ $option->academicYear->name ?? 'No academic year' }} &middot; {{ $option->semester->name ?? 'No semester' }}
                                            </span>
                                        </span>
                                        @if ($selectedCategoryId === $option->id)
                                            <x-icon name="check" class="category-picker-item-icon" />
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Tabs sit on their own full-width row below the toolbar, same
            placement as Group & Panel Assignment's roster. --}}
            <div class="notification-tabs mb-3">
                <button type="button" class="notification-tab active" data-tab-btn="scheduled">
                    Scheduled <span class="badge badge-muted-tint ms-1">{{ $scheduled->count() }}</span>
                </button>
                <button type="button" class="notification-tab" data-tab-btn="deferred">
                    Deferred <span class="badge {{ $deferred->isNotEmpty() ? 'badge-danger-tint' : 'badge-muted-tint' }} ms-1">{{ $deferred->count() }}</span>
                </button>
                <button type="button" class="notification-tab" data-tab-btn="completed">
                    Completed <span class="badge badge-muted-tint ms-1">{{ $completed->count() }}</span>
                </button>
            </div>

            {{-- Scheduled — nearest schedule first. --}}
            <div class="card-brand p-0 myassign-card" data-tab-pane="scheduled">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle myassign-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Group</th>
                                <th>Title</th>
                                @unless ($selectedCategoryId)
                                    <th>Category</th>
                                @endunless
                                <th>Room</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($scheduled as $assignment)
                                @php
                                    $attempt = $assignment->presentationAttempt;
                                    $group = $attempt->researchGroup;
                                    $schedule = $attempt->attemptSchedule;
                                    $room = $schedule?->presentationDateRoom;
                                    $isPending = $pendingRequestAttemptIds->has($assignment->presentation_attempt_id);
                                    $canMarkUnavailable = \App\Http\Controllers\Panelist\AssignmentController::canMarkUnavailable($assignment);
                                    // The day this group was parked on can no longer run, so it has
                                    // no date, time or room until the admin sets a new one.
                                    $isAwaiting = (bool) $schedule?->isAwaitingReschedule($attempt);
                                    // RoomQueuePreviewService already applies the running-day rule
                                    // (live cascade while the day is underway, the plan otherwise),
                                    // so this just displays whatever it resolved to.
                                    $expectedAt = $isAwaiting ? null : ($expectedTimes->get($schedule?->id)
                                        ?? $schedule?->adjusted_expected_at
                                        ?? $schedule?->planned_start_at);
                                @endphp
                                <tr {!! $searchAttrs($assignment, $group, $room) !!} data-focus="assign-{{ $attempt->id }}" data-focus-glow data-focus-reveal="[data-tab-btn='scheduled']">
                                    <td class="text-nowrap">
                                        @if ($isAwaiting)
                                            <span class="text-brand-muted">{{ \App\Models\AttemptSchedule::AWAITING_SHORT }}</span>
                                        @else
                                            <div>{{ ($expectedAt ?? $room?->presentationDate?->presentation_date)?->format('M j, Y') ?? '—' }}</div>
                                            <div class="text-brand-muted myassign-subline">{{ $expectedAt?->format('g:i A') ?? 'TBA' }}</div>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{{ $group->group_reference }}</td>
                                    <td class="myassign-title-cell" title="{{ $titleOf($group) }}">{{ $titleOf($group) ?: '—' }}</td>
                                    @unless ($selectedCategoryId)
                                        <td class="myassign-title-cell" title="{{ $group->category->name }}">{{ $group->category->name }}</td>
                                    @endunless
                                    <td class="text-nowrap">
                                        @if ($isAwaiting)
                                            <span class="text-brand-muted">{{ \App\Models\AttemptSchedule::AWAITING_ROOM_SHORT }}</span>
                                        @else
                                            {{ $room->room_name ?? 'Not scheduled' }}
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $assignment->roleBadgeClass() }}">{{ $assignment->roleLabel() }}</span>
                                    </td>
                                    <td class="text-nowrap">{{ $attempt->presentationStatus->name }}</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end align-items-center gap-2">
                                            @if ($isPending)
                                                <span class="badge badge-info-tint">Pending</span>
                                            @elseif (! $canMarkUnavailable)
                                                <span class="badge badge-muted-tint">Presenting</span>
                                            @endif
                                            <div class="dropdown">
                                                <button type="button" class="row-actions-btn" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Row actions">
                                                    <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.75"></circle><circle cx="12" cy="12" r="1.75"></circle><circle cx="12" cy="19" r="1.75"></circle></svg>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#view-modal-{{ $assignment->id }}">
                                                            <x-icon name="eye" /> View
                                                        </button>
                                                    </li>
                                                    @if (! $isPending && $canMarkUnavailable)
                                                        <li @if ($isAwaiting) title="Unavailable until this group has a schedule and room." @endif>
                                                            <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#unavailable-modal-{{ $assignment->id }}"
                                                                    @disabled($isAwaiting)>
                                                                <x-icon name="user-x" /> Mark Unavailable
                                                            </button>
                                                        </li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-brand-muted p-3">No upcoming assignments.</td></tr>
                            @endforelse
                            @if ($scheduled->isNotEmpty())
                                <tr data-no-match-row class="d-none"><td colspan="8" class="text-brand-muted p-3">No assignments match your search.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Deferred — view only. Date/time is the real moment the group was
            pulled out of the queue, not the slot it was planned for. --}}
            <div class="card-brand p-0 myassign-card d-none" data-tab-pane="deferred">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle myassign-table">
                        <thead>
                            <tr>
                                <th>Deferred</th>
                                <th>Group</th>
                                <th>Title</th>
                                @unless ($selectedCategoryId)
                                    <th>Category</th>
                                @endunless
                                <th>Room</th>
                                <th>Role</th>
                                <th>Reason</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($deferred as $assignment)
                                @php
                                    $attempt = $assignment->presentationAttempt;
                                    $group = $attempt->researchGroup;
                                    $room = $attempt->attemptSchedule?->presentationDateRoom;
                                    $queueEntry = $attempt->attemptSchedule?->queueEntry;
                                    $adjustment = $queueEntry?->latestDeferAdjustment();
                                    $deferredAt = $adjustment?->adjusted_at ?? $queueEntry?->removed_at;
                                @endphp
                                <tr {!! $searchAttrs($assignment, $group, $room) !!} data-focus="assign-{{ $attempt->id }}" data-focus-glow data-focus-reveal="[data-tab-btn='deferred']">
                                    <td class="text-nowrap">
                                        <div>{{ $deferredAt?->format('M j, Y') ?? '—' }}</div>
                                        <div class="text-brand-muted myassign-subline">{{ $deferredAt?->format('g:i A') ?? '—' }}</div>
                                    </td>
                                    <td class="text-nowrap">{{ $group->group_reference }}</td>
                                    <td class="myassign-title-cell" title="{{ $titleOf($group) }}">{{ $titleOf($group) ?: '—' }}</td>
                                    @unless ($selectedCategoryId)
                                        <td class="myassign-title-cell" title="{{ $group->category->name }}">{{ $group->category->name }}</td>
                                    @endunless
                                    <td class="text-nowrap">{{ $room->room_name ?? '—' }}</td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $assignment->roleBadgeClass() }}">{{ $assignment->roleLabel() }}</span>
                                    </td>
                                    <td class="myassign-title-cell" title="{{ $adjustment?->reason?->name }} {{ $adjustment?->remarks }}">
                                        {{ $adjustment?->reason?->name ?? 'N/A' }}
                                        @if ($adjustment?->remarks)
                                            <div class="text-brand-muted myassign-subline">{{ $adjustment->remarks }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button type="button" class="row-actions-btn" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Row actions">
                                                <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.75"></circle><circle cx="12" cy="12" r="1.75"></circle><circle cx="12" cy="19" r="1.75"></circle></svg>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#view-modal-{{ $assignment->id }}">
                                                        <x-icon name="eye" /> View
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-brand-muted p-3">No deferred assignments.</td></tr>
                            @endforelse
                            @if ($deferred->isNotEmpty())
                                <tr data-no-match-row class="d-none"><td colspan="8" class="text-brand-muted p-3">No assignments match your search.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Completed — View opens the evaluation sheets the panel filed. --}}
            <div class="card-brand p-0 myassign-card d-none" data-tab-pane="completed">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle myassign-table">
                        <thead>
                            <tr>
                                <th>Finished</th>
                                <th>Group</th>
                                <th>Title</th>
                                @unless ($selectedCategoryId)
                                    <th>Category</th>
                                @endunless
                                <th>Room</th>
                                <th>Role</th>
                                <th>Outcome</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($completed as $assignment)
                                @php
                                    $attempt = $assignment->presentationAttempt;
                                    $group = $attempt->researchGroup;
                                    $room = $attempt->attemptSchedule?->presentationDateRoom;
                                    // Planned-vs-actual: a finished attempt is
                                    // described by its own completion timestamp,
                                    // never by the slot it was planned into.
                                    $finishedAt = $attempt->completed_at;
                                    $sheetCount = $attempt->evaluationSubmissions->count();
                                @endphp
                                <tr {!! $searchAttrs($assignment, $group, $room) !!} data-focus="assign-{{ $attempt->id }}" data-focus-glow data-focus-reveal="[data-tab-btn='completed']">
                                    <td class="text-nowrap">
                                        <div>{{ ($finishedAt ?? $room?->presentationDate?->presentation_date)?->format('M j, Y') ?? '—' }}</div>
                                        <div class="text-brand-muted myassign-subline">{{ $finishedAt?->format('g:i A') ?? '—' }}</div>
                                    </td>
                                    <td class="text-nowrap">{{ $group->group_reference }}</td>
                                    <td class="myassign-title-cell" title="{{ $titleOf($group) }}">{{ $titleOf($group) ?: '—' }}</td>
                                    @unless ($selectedCategoryId)
                                        <td class="myassign-title-cell" title="{{ $group->category->name }}">{{ $group->category->name }}</td>
                                    @endunless
                                    <td class="text-nowrap">{{ $room->room_name ?? '—' }}</td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $assignment->roleBadgeClass() }}">{{ $assignment->roleLabel() }}</span>
                                    </td>
                                    <td class="text-nowrap">
                                        @if ($attempt->finalOutcome)
                                            @php
                                                // Re-Defense is stored is_successful = false but is not
                                                // a failure — it is still awaiting another attempt.
                                                $outcomeTone = $attempt->finalOutcome->is_successful
                                                    ? 'badge-success-tint'
                                                    : ($attempt->finalOutcome->requires_new_attempt ? 'badge-info-tint' : 'badge-danger-tint');
                                            @endphp
                                            <span class="badge {{ $outcomeTone }}">{{ $attempt->finalOutcome->name }}</span>
                                        @elseif ($attempt->presentationStatus->code !== 'COMPLETED')
                                            <span class="badge badge-muted-tint">{{ $attempt->presentationStatus->name }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button type="button" class="row-actions-btn" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Row actions" @disabled($sheetCount === 0)
                                                    title="{{ $sheetCount === 0 ? 'No submitted evaluations for this group' : '' }}">
                                                <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.75"></circle><circle cx="12" cy="12" r="1.75"></circle><circle cx="12" cy="19" r="1.75"></circle></svg>
                                            </button>
                                            @if ($sheetCount > 0)
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <button type="button" class="dropdown-item"
                                                                data-view-sheet
                                                                data-url="{{ route('panelist.assignments.evaluation-sheet', $assignment) }}"
                                                                data-group="{{ $group->group_reference }}">
                                                            <x-icon name="eye" /> View Evaluation
                                                        </button>
                                                    </li>
                                                </ul>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-brand-muted p-3">No completed assignments yet.</td></tr>
                            @endforelse
                            @if ($completed->isNotEmpty())
                                <tr data-no-match-row class="d-none"><td colspan="8" class="text-brand-muted p-3">No assignments match your search.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Group-data modals: Scheduled and Deferred only. --}}
            @foreach ($scheduled as $assignment)
                @include('panelist.assignments.partials.detail-modal', [
                    'assignment' => $assignment,
                    'isPending' => $pendingRequestAttemptIds->has($assignment->presentation_attempt_id),
                    'canMark' => ! $pendingRequestAttemptIds->has($assignment->presentation_attempt_id)
                        && \App\Http\Controllers\Panelist\AssignmentController::canMarkUnavailable($assignment),
                    'expectedAt' => $expectedTimes->get($assignment->presentationAttempt->attemptSchedule?->id),
                ])
            @endforeach

            @foreach ($deferred as $assignment)
                @include('panelist.assignments.partials.detail-modal', [
                    'assignment' => $assignment,
                    'isPending' => $pendingRequestAttemptIds->has($assignment->presentation_attempt_id),
                    'canMark' => false,
                    'expectedAt' => null,
                ])
            @endforeach

            @include('admin.partials.evaluation-sheet-modal')
        @endif
    </div>

    @push('styles')
        <style>
            /* Same toolbar shape as Group & Panel Assignment's .assign-toolbar
            (page-local there too, not a shared class): the search field wraps
            on its own, the category picker stays pinned to the right, and
            every toolbar control shares one height. */
            .assign-toolbar-controls {
                flex: 1 1 0;
                min-width: 0;
            }

            @media (max-width: 575.98px) {
                .assign-toolbar-controls {
                    flex-basis: 100%;
                }
            }

            .myassign-search {
                width: 16rem;
                max-width: 100%;
                border-radius: 999px;
                padding-left: .9rem;
                padding-right: .9rem;
            }

            .myassign-filter {
                width: auto;
                min-width: 8.5rem;
                max-width: 12rem;
            }

            .assign-toolbar .form-control,
            .assign-toolbar .form-select,
            .assign-toolbar-right > .dropdown > .btn {
                height: 2.1rem;
            }

            .myassign-card {
                overflow: hidden;
            }

            .myassign-subline {
                font-size: .82em;
            }

            .myassign-title-cell {
                max-width: 14rem;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function () {
                var buttons = document.querySelectorAll('[data-tab-btn]');
                var panes = document.querySelectorAll('[data-tab-pane]');

                buttons.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        buttons.forEach(function (b) { b.classList.remove('active'); });
                        btn.classList.add('active');

                        panes.forEach(function (pane) {
                            pane.classList.toggle('d-none', pane.dataset.tabPane !== btn.dataset.tabBtn);
                        });
                    });
                });
            })();

            (function () {
                var input = document.getElementById('myassign-search');
                var trackSelect = document.getElementById('myassign-track');
                var sectionSelect = document.getElementById('myassign-section');
                if (!input) return;

                var panes = document.querySelectorAll('[data-tab-pane]');

                function applyFilter() {
                    var q = input.value.trim().toLowerCase();
                    var track = trackSelect.value;
                    var section = sectionSelect.value;

                    panes.forEach(function (pane) {
                        var rows = pane.querySelectorAll('tbody tr[data-search]');
                        var visible = 0;

                        rows.forEach(function (row) {
                            var match = (!q || row.dataset.search.indexOf(q) !== -1)
                                && (!track || (row.dataset.track || '').indexOf('|' + track + '|') !== -1)
                                && (!section || (row.dataset.sections || '').indexOf('|' + section + '|') !== -1);
                            row.classList.toggle('d-none', !match);
                            if (match) visible++;
                        });

                        var noMatchRow = pane.querySelector('[data-no-match-row]');
                        if (noMatchRow) {
                            noMatchRow.classList.toggle('d-none', !(rows.length > 0 && visible === 0));
                        }
                    });
                }

                input.addEventListener('input', applyFilter);
                trackSelect.addEventListener('change', applyFilter);
                sectionSelect.addEventListener('change', applyFilter);
            })();
        </script>
    @endpush
@endsection
