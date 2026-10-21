@extends('layouts.panelist')

@section('title', 'My Advisees')
@section('heading', 'My Advisees')

@section('content')
    @php
        // Title Proposal groups carry several titles and no single project
        // title, so fall back to the proposed set rather than an empty cell.
        $titleOf = fn ($group) => $group->current_project_title
            ?: $group->proposedTitles->pluck('title_text')->filter()->implode('; ');

        $livePanel = fn ($attempt) => $attempt
            ? $attempt->attemptPanelAssignments
                ->reject(fn ($a) => in_array($a->assignmentStatus?->code, ['REPLACED', 'WITHDRAWN'], true))
                ->sortByDesc('is_lead')
                ->values()
            : collect();

        $panelistName = fn ($a) => trim(($a->panelist?->profile?->first_name ?? '') . ' ' . ($a->panelist?->profile?->last_name ?? '')) ?: ($a->panelist?->username ?? '—');

        // Same filter attributes as My Assignments, plus the category, which
        // is filtered client-side here.
        $searchAttrs = function ($row, $room) use ($titleOf) {
            $group = $row->group;
            $tracks = $group->students->pluck('research_track_name')->filter()->map(fn ($t) => mb_strtolower(trim($t)))->unique();
            $sections = $group->students->pluck('section_name')->filter()->map(fn ($s) => mb_strtolower(trim($s)))->unique();
            $all = mb_strtolower(implode(' ', array_filter([
                $group->group_reference,
                $titleOf($group),
                $group->category->name,
                $group->leader()?->full_name,
                $room->room_name ?? null,
            ])));

            return 'data-search="' . e($all) . '"'
                . ' data-track="' . e('|' . $tracks->implode('|') . '|') . '"'
                . ' data-sections="' . e('|' . $sections->implode('|') . '|') . '"'
                . ' data-category="' . $group->category_id . '"';
        };

        $rowActions = '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.75"></circle><circle cx="12" cy="12" r="1.75"></circle><circle cx="12" cy="19" r="1.75"></circle></svg>';
    @endphp

    <div class="page-shell myassign-page">
        @if (! $hasGroups)
            <div class="card-brand p-5 text-center text-brand-muted">
                No groups have you as their technical adviser yet.
            </div>
        @else
            <div class="assign-toolbar d-flex flex-wrap align-items-start gap-2 mb-3">
                <div class="assign-toolbar-controls d-flex flex-wrap align-items-center gap-2">
                    <input type="search" id="myassign-search" class="form-control myassign-search"
                           placeholder="Search group, title, leader…" autocomplete="off">
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

                <div class="assign-toolbar-right d-flex flex-wrap align-items-center gap-2 ms-auto">
                    @if ($categoryOptions->count() > 1)
                        <select id="myassign-category" class="form-select myassign-filter myassign-category" aria-label="Category">
                            <option value="">All Categories</option>
                            @foreach ($categoryOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                    @endif

                    <div class="advisee-rec-tools">
                        <span class="advisee-rec-label"><x-icon name="file-text" /> Recommendations Summary</span>
                        <button type="button" class="btn btn-sm btn-outline-brand" data-recommendations-open>
                            View
                        </button>
                        <a href="{{ route('panelist.advisees.recommendations.export-pdf') }}" class="btn btn-sm btn-outline-brand">
                            <x-icon name="file-text" /> Export to PDF
                        </a>
                    </div>
                </div>
            </div>

            <div class="notification-tabs mb-3">
                <button type="button" class="notification-tab active" data-tab-btn="upcoming">
                    Upcoming <span class="badge badge-muted-tint ms-1">{{ $upcoming->count() }}</span>
                </button>
                <button type="button" class="notification-tab" data-tab-btn="completed">
                    Completed <span class="badge badge-muted-tint ms-1">{{ $completed->count() }}</span>
                </button>
            </div>

            {{-- Upcoming — nearest first, on the time the row shows. --}}
            <div class="card-brand p-0 myassign-card" data-tab-pane="upcoming">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle myassign-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Group</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Room</th>
                                <th>Panel</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($upcoming as $index => $row)
                                @php
                                    $group = $row->group;
                                    $attempt = $row->attempt;
                                    $schedule = $attempt?->attemptSchedule;
                                    $room = $schedule?->presentationDateRoom;
                                    $isDeferred = $schedule?->queueEntry?->removed_at !== null;
                                    $isAwaiting = ! $schedule || (bool) $schedule->isAwaitingReschedule($attempt);
                                    $expectedAt = ($isAwaiting || $isDeferred) ? null : ($expectedTimes->get($schedule->id)
                                        ?? $schedule->adjusted_expected_at
                                        ?? $schedule->planned_start_at);
                                    $panel = $livePanel($attempt);
                                    // Live expected time runs past the room's session end.
                                    $rowSessionEnd = ($isAwaiting || $isDeferred) ? null : $pastSessionEnd->get($schedule->id);
                                @endphp
                                <tr {!! $searchAttrs($row, $room) !!}
                                    @if ($rowSessionEnd) class="past-session-row" data-past-session-line="{{ \App\Models\AttemptSchedule::pastSessionLine($rowSessionEnd, false) }}" @endif>
                                    <td class="text-nowrap">
                                        @if ($isDeferred)
                                            <span class="text-brand-muted">Deferred</span>
                                        @elseif ($isAwaiting)
                                            <span class="text-brand-muted">{{ \App\Models\AttemptSchedule::AWAITING_SHORT }}</span>
                                        @else
                                            <div>{{ ($expectedAt ?? $room?->presentationDate?->presentation_date)?->format('M j, Y') ?? '—' }}</div>
                                            <div class="text-brand-muted myassign-subline">{{ $expectedAt?->format('g:i A') ?? 'TBA' }}</div>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        {{ $group->group_reference }}
                                        @if (($attempt?->attempt_number ?? 1) > 1)
                                            <div><span class="badge badge-info-tint">Attempt {{ $attempt->attempt_number }}</span></div>
                                        @endif
                                    </td>
                                    <td class="myassign-title-cell" title="{{ $titleOf($group) }}">{{ $titleOf($group) ?: '—' }}</td>
                                    <td class="myassign-title-cell" title="{{ $group->category->name }}">{{ $group->category->name }}</td>
                                    <td class="text-nowrap">
                                        @if ($isAwaiting && ! $isDeferred)
                                            <span class="text-brand-muted">{{ \App\Models\AttemptSchedule::AWAITING_ROOM_SHORT }}</span>
                                        @else
                                            {{ $room->room_name ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        @if ($panel->isEmpty())
                                            <span class="text-brand-muted">{{ \App\Models\AttemptSchedule::AWAITING_PANEL }}</span>
                                        @else
                                            {{ $panel->count() }} {{ \Illuminate\Support\Str::plural('panelist', $panel->count()) }}
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{{ $attempt?->presentationStatus?->name ?? 'Registered' }}</td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button type="button" class="row-actions-btn" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Row actions">{!! $rowActions !!}</button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#advisee-modal-{{ $index }}">
                                                        View
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-brand-muted p-3">No upcoming presentations.</td></tr>
                            @endforelse
                            @if ($upcoming->isNotEmpty())
                                <tr data-no-match-row class="d-none"><td colspan="8" class="text-brand-muted p-3">No groups match your search.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Completed — View Evaluation opens the sheets the panel filed. --}}
            <div class="card-brand p-0 myassign-card d-none" data-tab-pane="completed">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle myassign-table">
                        <thead>
                            <tr>
                                <th>Finished</th>
                                <th>Group</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Room</th>
                                <th>Outcome</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($completed as $row)
                                @php
                                    $group = $row->group;
                                    $attempt = $row->attempt;
                                    $room = $attempt->attemptSchedule?->presentationDateRoom;
                                    // Planned-vs-actual: described by its own completion time.
                                    $finishedAt = $attempt->completed_at;
                                    $sheetCount = $attempt->evaluationSubmissions->count();
                                @endphp
                                <tr {!! $searchAttrs($row, $room) !!}>
                                    <td class="text-nowrap">
                                        <div>{{ ($finishedAt ?? $room?->presentationDate?->presentation_date)?->format('M j, Y') ?? '—' }}</div>
                                        <div class="text-brand-muted myassign-subline">{{ $finishedAt?->format('g:i A') ?? '—' }}</div>
                                    </td>
                                    <td class="text-nowrap">
                                        {{ $group->group_reference }}
                                        @if ($attempt->attempt_number > 1)
                                            <div><span class="badge badge-info-tint">Attempt {{ $attempt->attempt_number }}</span></div>
                                        @endif
                                    </td>
                                    <td class="myassign-title-cell" title="{{ $titleOf($group) }}">{{ $titleOf($group) ?: '—' }}</td>
                                    <td class="myassign-title-cell" title="{{ $group->category->name }}">{{ $group->category->name }}</td>
                                    <td class="text-nowrap">{{ $room->room_name ?? '—' }}</td>
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
                                                    title="{{ $sheetCount === 0 ? 'No submitted evaluations for this group' : '' }}">{!! $rowActions !!}</button>
                                            @if ($sheetCount > 0)
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <button type="button" class="dropdown-item"
                                                                data-view-sheet
                                                                data-url="{{ route('panelist.advisees.evaluation-sheet', $attempt) }}"
                                                                data-group="{{ $group->group_reference }}">
                                                            View Evaluation
                                                        </button>
                                                    </li>
                                                </ul>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-brand-muted p-3">No completed presentations yet.</td></tr>
                            @endforelse
                            @if ($completed->isNotEmpty())
                                <tr data-no-match-row class="d-none"><td colspan="7" class="text-brand-muted p-3">No groups match your search.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Group-data modals for the Upcoming rows. --}}
            @foreach ($upcoming as $index => $row)
                @php
                    $group = $row->group;
                    $attempt = $row->attempt;
                    $schedule = $attempt?->attemptSchedule;
                    $room = $schedule?->presentationDateRoom;
                    $isDeferred = $schedule?->queueEntry?->removed_at !== null;
                    $isAwaiting = ! $schedule || (bool) $schedule->isAwaitingReschedule($attempt);
                    $panel = $livePanel($attempt);
                @endphp
                <div class="modal fade" id="advisee-modal-{{ $index }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{ $group->group_reference }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <dl class="detail-list small mb-0">
                                    <dt>Category</dt>
                                    <dd>
                                        {{ $group->category->name }}
                                        <div class="text-brand-muted">
                                            {{ $group->category->academicYear->name ?? 'N/A' }} &middot;
                                            {{ $group->category->semester->name ?? 'N/A' }} &middot;
                                            {{ $group->category->college->name ?? 'N/A' }}
                                        </div>
                                    </dd>

                                    <dt>Group Leader</dt>
                                    <dd>{{ $group->leader()?->full_name ?? 'N/A' }}</dd>

                                    <dt>Members</dt>
                                    <dd>
                                        @forelse ($group->students->where('is_leader', false) as $member)
                                            <div>{{ $member->full_name }}{{ $member->section_name ? ' (' . $member->section_name . ')' : '' }}</div>
                                        @empty
                                            <span class="text-brand-muted">None</span>
                                        @endforelse
                                    </dd>

                                    @if ($group->current_project_title)
                                        <dt>Project Title</dt>
                                        <dd>{{ $group->current_project_title }}</dd>
                                    @elseif ($group->proposedTitles->isNotEmpty())
                                        <dt>Proposed Titles</dt>
                                        <dd>
                                            <ol class="mb-0 ps-3">
                                                @foreach ($group->proposedTitles as $title)
                                                    <li>{{ $title->title_text }}</li>
                                                @endforeach
                                            </ol>
                                        </dd>
                                    @endif

                                    <dt>Room</dt>
                                    <dd>{{ $isAwaiting && ! $isDeferred ? \App\Models\AttemptSchedule::AWAITING_ROOM : ($room->room_name ?? '—') }}</dd>

                                    <dt>Status</dt>
                                    <dd>{{ $attempt?->presentationStatus?->name ?? 'Registered' }}</dd>

                                    <dt>Panel</dt>
                                    <dd>
                                        @forelse ($panel as $seat)
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <span>{{ $panelistName($seat) }}</span>
                                                <span class="badge {{ $seat->roleBadgeClass() }}">{{ $seat->roleLabel() }}</span>
                                            </div>
                                        @empty
                                            <span class="text-brand-muted">{{ \App\Models\AttemptSchedule::AWAITING_PANEL }}</span>
                                        @endforelse
                                    </dd>
                                </dl>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            @include('admin.partials.evaluation-sheet-modal')

            @include('admin.reports.partials.recommendations-modal', [
                'fetchUrl' => route('panelist.advisees.recommendations'),
                'pdfUrl' => route('panelist.advisees.recommendations.export-pdf'),
            ])
        @endif
    </div>

    @push('styles')
        <style>
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

            .myassign-category {
                max-width: 16rem;
            }

            /* Stays on the right however the toolbar wraps. */
            .assign-toolbar-right {
                justify-content: flex-end;
            }

            .advisee-rec-tools {
                margin-left: auto;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: flex-end;
                gap: 0.4rem;
            }

            .advisee-rec-tools .btn {
                height: 1.6rem;
                padding: 0 0.4rem;
                font-size: 0.78rem;
                gap: 0.3rem;
            }

            .advisee-rec-label {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                /* Its own line, the buttons below it, at every width. */
                flex: 0 0 100%;
                justify-content: flex-end;
                font-size: 0.85rem;
                font-weight: 600;
                white-space: nowrap;
            }

            /* Bare icon outside a button — only .btn-* > svg has a size rule. */
            .advisee-rec-label svg {
                width: 1.05em;
                height: 1.05em;
                flex-shrink: 0;
            }

            @media (max-width: 575.98px) {
                .assign-toolbar-right {
                    flex-basis: 100%;
                }
            }

            .assign-toolbar .form-control,
            .assign-toolbar .form-select {
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
        @include('partials.past-session-divider-script')
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
                if (!input) return;

                var trackSelect = document.getElementById('myassign-track');
                var sectionSelect = document.getElementById('myassign-section');
                var categorySelect = document.getElementById('myassign-category');
                var panes = document.querySelectorAll('[data-tab-pane]');

                function applyFilter() {
                    var q = input.value.trim().toLowerCase();
                    var track = trackSelect.value;
                    var section = sectionSelect.value;
                    var category = categorySelect ? categorySelect.value : '';

                    panes.forEach(function (pane) {
                        var rows = pane.querySelectorAll('tbody tr[data-search]');
                        var visible = 0;

                        rows.forEach(function (row) {
                            var match = (!q || row.dataset.search.indexOf(q) !== -1)
                                && (!track || (row.dataset.track || '').indexOf('|' + track + '|') !== -1)
                                && (!section || (row.dataset.sections || '').indexOf('|' + section + '|') !== -1)
                                && (!category || row.dataset.category === category);
                            row.classList.toggle('d-none', !match);
                            if (match) visible++;
                        });

                        var noMatchRow = pane.querySelector('[data-no-match-row]');
                        if (noMatchRow) {
                            noMatchRow.classList.toggle('d-none', !(rows.length > 0 && visible === 0));
                        }

                        window.placePastSessionDividers(pane);
                    });
                }

                input.addEventListener('input', applyFilter);
                trackSelect.addEventListener('change', applyFilter);
                sectionSelect.addEventListener('change', applyFilter);
                if (categorySelect) categorySelect.addEventListener('change', applyFilter);
            })();
        </script>
    @endpush
@endsection
