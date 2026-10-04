@extends('layouts.admin')

@section('title', 'Group & Panel Assignment')
@section('heading', $category->name)

@section('content')
    <div class="page-shell assign-page">
    {{-- One toolbar row: search, room filter and the selection-driven bulk
         actions on the left, the Needs Attention chip, the category picker
         and Add Group pinned to the right. --}}
    <div class="assign-toolbar d-flex flex-wrap align-items-start gap-2 mb-3">
        <div class="assign-toolbar-controls d-flex flex-wrap align-items-center gap-2">
            <form method="GET" action="{{ route('admin.panel-assignments.show', $category) }}" class="d-flex gap-2">
                <input type="text" name="q" class="form-control" style="width: 220px;" placeholder="Search groups…" value="{{ $search }}">
                <button type="submit" class="btn btn-outline-brand"><x-icon name="search" /> Search</button>
                @if ($search !== '')
                    <a href="{{ route('admin.panel-assignments.show', $category) }}" class="btn btn-outline-brand"><x-icon name="x" /> Clear</a>
                @endif
            </form>

            @if ($roomGroups->isNotEmpty())
                <select id="room-filter" class="form-select" style="max-width: 200px;">
                    <option value="">All Rooms</option>
                    @foreach ($roomGroups->pluck('room.room_name')->unique()->sort()->values() as $roomName)
                        <option value="{{ $roomName }}">{{ $roomName }}</option>
                    @endforeach
                </select>

                <div id="bulk-actions-bar" class="d-none d-flex gap-2 align-items-center flex-wrap">
                    <span class="text-brand-muted small" id="bulk-selection-count"></span>
                    <button type="button" class="btn btn-sm btn-outline-brand" id="bulk-edit-btn"><x-icon name="edit" /> Edit</button>
                    <button type="button" class="btn btn-sm btn-outline-brand" id="bulk-move-btn" data-bs-toggle="modal" data-bs-target="#bulk-move-modal"><x-icon name="move" /> Move</button>
                    <button type="button" class="btn btn-sm btn-outline-brand" data-bs-toggle="modal" data-bs-target="#bulk-transfer-modal"><x-icon name="transfer" /> Transfer</button>
                    <button type="button" class="btn btn-sm btn-outline-danger-brand" id="bulk-defer-btn" data-bs-toggle="modal" data-bs-target="#bulk-defer-modal"><x-icon name="defer" /> Defer</button>
                    <button type="button" class="btn btn-sm btn-outline-danger-brand" id="bulk-delete-btn" data-bs-toggle="modal" data-bs-target="#bulk-delete-modal"><x-icon name="trash" /> Delete</button>
                </div>
            @endif
        </div>

        <div class="assign-toolbar-right d-flex align-items-center gap-2 ms-auto flex-shrink-0">
            @include('admin.panel-assignments.partials.needs-attention', ['category' => $category, 'needsAttention' => $needsAttention, 'deferredCount' => $deferredCount])

            @include('partials.category-picker-dropdown', [
                'categories' => $pickerCategories,
                'current' => $category,
                'routeName' => 'admin.panel-assignments.show',
            ])

            <button type="button" class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#add-group-modal"><x-icon name="plus" /> Add Group</button>
        </div>
    </div>

    @push('styles')
        <style>
            /* The left-hand controls wrap among themselves (e.g. when the
               bulk actions appear) so Add Group keeps its place on the first
               row. On phones the controls take the full row and Add Group
               drops beneath them, still right-aligned. */
            .assign-toolbar-controls {
                flex: 1 1 0;
                min-width: 0;
            }

            @media (max-width: 575.98px) {
                .assign-toolbar-controls {
                    flex-basis: 100%;
                }
            }

            /* One control height so the search field, its button, the room
               dropdown, the Needs Attention chip, the category picker and
               Add Group line up.
               Listed per group rather than as a blanket `.assign-toolbar
               .btn` so the bulk-action bar's `btn-sm` buttons keep their
               own smaller height. */
            .assign-toolbar .form-control,
            .assign-toolbar .form-select,
            .assign-toolbar > .btn,
            .assign-toolbar form .btn,
            .assign-toolbar-right > .btn,
            .assign-toolbar-right > .dropdown > .btn {
                height: 2.1rem;
            }
        </style>
    @endpush

    <div class="modal fade" id="add-group-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.panel-assignments.groups.store', $category) }}" class="group-registration-form" data-member-slots="{{ $memberSlots }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Group</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('admin.panel-assignments.partials.group-fields', ['idPrefix' => 'add', 'prefill' => []])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-brand"><x-icon name="plus" /> Add Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('admin.panel-assignments.partials.assign-conflict-modal')
    @include('admin.panel-assignments.partials.reassign-confirm-modal')

    @if ($roomGroups->isEmpty())
        <div class="card-brand p-5 text-center text-brand-muted">
            <p class="mb-0">{{ $search !== '' ? 'No groups match your search.' : 'No groups are queued in this category yet.' }}</p>
        </div>
    @else
        @php
            $rosterRows = collect();
            $deferredRows = collect();

            foreach ($roomGroups as $group) {
                foreach ($group['active'] as $attempt) {
                    $rowSchedule = $attempt->attemptSchedule;
                    // Its day can no longer run and nothing carried it forward,
                    // so it has no date or time left to show — it needs a new
                    // presentation date before it does.
                    $rowAwaiting = $rowSchedule->isAwaitingReschedule($attempt);
                    // Completed shows what really happened; anything still to
                    // present follows the running-day rule, already resolved by
                    // RoomQueuePreviewService (expected while the day is
                    // underway, the plan while it is only upcoming).
                    $rowTime = ($attempt->presentationStatus->code === 'COMPLETED' && $attempt->completed_at)
                        ? $attempt->completed_at
                        : ($rowAwaiting ? null : ($expectedTimes->get($rowSchedule->id) ?? $rowSchedule->planned_start_at));
                    // The date band has to follow the value the row's own Date
                    // cell prints, not the room's planned day. A group that was
                    // still unfinished when its day ended is carried to a later
                    // day's room by processEndOfDay(); if it is only completed
                    // afterwards, its real completed_at is days before the room
                    // it now sits in — which used to file it under a date it
                    // never presented on (real case: CFD-2026-0008, completed
                    // Sep 17, listed under Tuesday, September 22). Same rule as
                    // the PLANNED-VS-ACTUAL policy's "a Date column next to a
                    // time must follow the same resolved value".
                    $rowDate = $rowTime ?? $group['room']->presentationDate->presentation_date;

                    $rosterRows->push([
                        'attempt' => $attempt,
                        'room' => $group['room'],
                        'isAwaiting' => $rowAwaiting,
                        'displayTime' => $rowTime,
                        'dateKey' => $rowAwaiting ? '9999-99-99' : $rowDate->format('Y-m-d'),
                        'dateLabel' => $rowAwaiting ? \App\Models\AttemptSchedule::AWAITING_DATE : $rowDate->format('l, F j, Y'),
                    ]);
                }
                foreach ($group['deferred'] as $attempt) {
                    $deferredRows->push(['attempt' => $attempt, 'room' => $group['room']]);
                }
            }

            // Presentation date first, then room, then queue position
            // (user-directed 2026-09-13). Sorting by room name alone mixed
            // days together, since the same room runs on several dates —
            // "Room 1" on the 13th and on the 14th both sorted under the
            // same name and then interleaved by queue number.
            //
            // Groups whose day can no longer run sort last, under their own
            // header instead of a date (user-directed 2026-09-16): the day
            // they are still parked on is over, so filing them under it would
            // read as if they were still due to present on it.
            $rosterRows = $rosterRows->sortBy(fn ($row) => $row['dateKey']
                . '-' . $row['room']->room_name
                . '-' . str_pad((string) $row['attempt']->attemptSchedule->queueEntry->queue_number, 5, '0', STR_PAD_LEFT))->values();
            $currentRosterDate = null;
            $deferredRows = $deferredRows->sortByDesc(fn ($row) => $row['attempt']->attemptSchedule->queueEntry->removed_at)->values();
            $completedCount = $rosterRows->filter(fn ($row) => $row['attempt']->presentationStatus->is_terminal)->count();
            $scheduledCount = $rosterRows->count() - $completedCount;
        @endphp

        <form method="POST" action="{{ route('admin.panel-assignments.assign', $category) }}" id="assign-form">
            @csrf
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="notification-tabs mb-3">
                        <button type="button" class="notification-tab active" data-tab-btn="active">
                            Scheduled <span class="badge badge-muted-tint ms-1">{{ $scheduledCount }}</span>
                        </button>
                        <button type="button" class="notification-tab" data-tab-btn="completed">
                            Completed <span class="badge badge-muted-tint ms-1">{{ $completedCount }}</span>
                        </button>
                        <button type="button" class="notification-tab {{ $deferredRows->isNotEmpty() ? 'tab-warning' : '' }}" data-tab-btn="deferred">
                            Deferred <span class="badge {{ $deferredRows->isNotEmpty() ? 'badge-danger-tint' : 'badge-muted-tint' }} ms-1">{{ $deferredRows->count() }}</span>
                        </button>
                        <button type="button" class="notification-tab {{ $reDefenseRows->isNotEmpty() ? 'tab-warning' : '' }}" data-tab-btn="redefense">
                            Re-Defense <span class="badge {{ $reDefenseRows->isNotEmpty() ? 'badge-danger-tint' : 'badge-muted-tint' }} ms-1">{{ $reDefenseRows->count() }}</span>
                        </button>
                    </div>

                    <div data-tab-pane="all">
                        @if ($rosterRows->isEmpty())
                            <p class="text-brand-muted small">No registered groups in this category yet.</p>
                        @else
                            <div class="table-responsive roster-scroll">
                                <table class="table table-sm align-middle small mb-0">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>#</th>
                                            <th>Technical Adviser</th>
                                            <th>Title</th>
                                            <th>Members</th>
                                            <th>Panel</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            // Breaks slot in ahead of the first still-to-present group
                                            // that starts after them; completed and awaiting rows never
                                            // trigger one.
                                            $rosterItems = \App\Support\ScheduleBreakRows::interleave(
                                                $rosterRows,
                                                fn ($r) => $r['room'],
                                                fn ($r) => ($r['attempt']->presentationStatus->is_terminal || $r['isAwaiting']) ? null : $r['displayTime'],
                                            );
                                        @endphp
                                        @foreach ($rosterItems as $row)
                                            @if (\App\Support\ScheduleBreakRows::isBreak($row))
                                                @php $breakDateKey = $row->planned_start_at->format('Y-m-d'); @endphp
                                                @if ($breakDateKey !== $currentRosterDate)
                                                    @php $currentRosterDate = $breakDateKey; @endphp
                                                    <tr class="roster-date-row" data-date-header="{{ $breakDateKey }}">
                                                        <td colspan="9">{{ $row->planned_start_at->format('l, F j, Y') }}</td>
                                                    </tr>
                                                @endif
                                                @include('partials.schedule.break-row', [
                                                    'break' => $row,
                                                    'span' => 9,
                                                    'rowAttrs' => 'data-room-row="' . e($row->presentationDateRoom->room_name) . '" data-row-status="active"',
                                                ])
                                                @continue
                                            @endif
                                            @php
                                                $attempt = $row['attempt'];
                                                $room = $row['room'];
                                                $schedule = $attempt->attemptSchedule;
                                                $isTerminal = $attempt->presentationStatus->is_terminal;
                                                $assigned = $attempt->attemptPanelAssignments->where('assignmentKind.code', 'ASSIGNED_PANELIST');
                                                $backup = $attempt->attemptPanelAssignments->where('assignmentKind.code', 'BACKUP_PANELIST');
                                                $leader = $attempt->researchGroup->leader();
                                                $members = $attempt->researchGroup->students->reject(fn ($s) => $s->is_leader);
                                                // Feeds the reassign-confirmation modal: a row only warns about
                                                // overwriting a panel if it actually has live assigned seats today.
                                                $memberNumbers = \App\Models\AttemptPanelAssignment::memberNumbers($assigned);
                                                $currentPanelNames = $assigned
                                                    ->map(fn ($pa) => trim(($pa->panelist->profile->first_name ?? '') . ' ' . ($pa->panelist->profile->last_name ?? '')) . ($pa->is_lead ? ' (Chair)' : ' (Member ' . ($memberNumbers[$pa->id] ?? '') . ')'))
                                                    ->merge($backup->map(fn ($pb) => trim(($pb->panelist->profile->first_name ?? '') . ' ' . ($pb->panelist->profile->last_name ?? '')) . ' (Alternate Panel)'))
                                                    ->filter(fn ($name) => ! in_array(trim($name), ['(Chair)', '(Alternate Panel)'], true) && ! str_starts_with(trim($name), '(Member') && trim($name) !== '')
                                                    ->implode(', ');
                                                // Non-null when this group's panel no longer matches what its
                                                // room requires — the same rule that blocks Start on the tablet.
                                                $panelIssue = $isTerminal ? null : ($panelIssueByAttempt[$attempt->id] ?? null);
                                                // A pending re-defense is pinned to the end of its room's
                                                // queue, so it is shown as such and its Move action is not
                                                // offered (see the dropdown below).
                                                $isReDefense = $attempt->attemptType?->code === 'RE_DEFENSE';
                                                // A panelist has already scored this group — it can no longer
                                                // be sent back to the queue (user-directed 2026-09-17), matching
                                                // QueueAdjustmentService::defer()'s own server-side guard.
                                                $hasSubmittedEvaluation = $hasSubmittedEvaluationByAttempt[$attempt->id] ?? false;
                                                // Date/time and the date band they sit under are all resolved
                                                // together where the rows are built, so the band can never
                                                // name a different day than the rows beneath it.
                                                $isAwaiting = $row['isAwaiting'];
                                                // On stage right now (the panel is evaluating): nothing about
                                                // this row can be changed until it completes or is deferred.
                                                $isPresenting = in_array($attempt->presentationStatus->code, ['ONGOING', 'PAUSED'], true);
                                                // Edit/Move/Defer/Delete only apply to a group on an ongoing or
                                                // upcoming day (user-directed 2026-09-24) — a group parked on a
                                                // day that is over can only be transferred out of it.
                                                // "Awaiting" also covers a group on a good day that simply has
                                                // no slot yet (schedule full) — that one can still be edited,
                                                // deferred or deleted; only a finished day locks it.
                                                $isDayOver = ! $schedule->presentationDateRoom->presentationDate->isOpenForScheduling();
                                                $canChange = ! $isTerminal && ! $isPresenting && ! $isDayOver;
                                                $canTransfer = ! $isTerminal && ! $isPresenting;
                                                $displayTime = $row['displayTime'];
                                                $rosterDateKey = $row['dateKey'];
                                                // Live panelist scheduling conflicts this row is part of —
                                                // the row is marked and opens its own reassignment modal.
                                                $rowConflicts = $isTerminal ? collect() : ($conflictsByAttempt->get($attempt->id) ?? collect());
                                                // Substitute-less unavailability reports pending against this
                                                // attempt (§2.12) — the Needs Attention link lands here with
                                                // ?focus=attempt-{id}, blinking the row via data-focus above and
                                                // auto-opening the first one's Assign Replacement modal below.
                                                $unavailabilityReports = $isTerminal ? collect() : ($unavailabilityReportsByAttempt->get($attempt->id) ?? collect());
                                            @endphp
                                            @if ($rosterDateKey !== $currentRosterDate)
                                                @php $currentRosterDate = $rosterDateKey; @endphp
                                                <tr class="roster-date-row" data-date-header="{{ $rosterDateKey }}">
                                                    <td colspan="9">{{ $row['dateLabel'] }}</td>
                                                </tr>
                                            @endif
                                            <tr data-room-row="{{ $room->room_name }}" data-row-status="{{ $isTerminal ? 'completed' : 'active' }}"
                                                data-focus="attempt-{{ $attempt->id }}" data-focus-reveal="[data-tab-btn='{{ $isTerminal ? 'completed' : 'active' }}']"
                                                @if ($rowConflicts->isNotEmpty())
                                                    class="roster-row-conflict"
                                                    data-conflict-modal="panel-conflict-modal-{{ $attempt->id }}"
                                                    title="{{ $rowConflicts->pluck('message')->implode("\n") }}"
                                                @endif
                                                @if ($unavailabilityReports->isNotEmpty()) data-needs-replacement="assign-replacement-modal-{{ $unavailabilityReports->first()->id }}" @endif>
                                                <td>
                                                    @if ($canTransfer)
                                                        <input type="checkbox" class="form-check-input attempt-checkbox" name="attempt_ids[]" value="{{ $attempt->id }}"
                                                               data-required="{{ $room->panelist_count ?? 0 }}"
                                                               data-awaiting="{{ $isDayOver ? '1' : '0' }}"
                                                               data-schedule-id="{{ $schedule->id }}"
                                                               data-entry-id="{{ $schedule->queueEntry->id }}"
                                                               data-group-id="{{ $attempt->researchGroup->id }}"
                                                               data-adviser="{{ $adviserNameByAttempt[$attempt->id] ?? '' }}"
                                                               data-group-ref="{{ $attempt->researchGroup->group_reference }}"
                                                               data-current-panel="{{ $currentPanelNames }}"
                                                               data-room-id="{{ $room->id }}"
                                                               data-has-eval="{{ $hasSubmittedEvaluation ? '1' : '0' }}">
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($isAwaiting)
                                                        <span class="text-brand-muted">{{ \App\Models\AttemptSchedule::AWAITING_SHORT }}</span>
                                                    @else
                                                        {{ $displayTime?->format('M j, Y') }}
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ $isAwaiting ? '—' : $displayTime?->format('g:i A') }}
                                                    @if ($isPresenting)
                                                        <div><span class="badge badge-success-tint">{{ $attempt->presentationStatus->name }}</span></div>
                                                    @endif
                                                </td>
                                                <td>{{ $schedule->queueEntry->queue_number }}</td>
                                                <td>{{ $attempt->researchGroup->technical_adviser_name ?: '—' }}</td>
                                                <td>
                                                    {{-- A group past its first attempt has a row per attempt —
                                                       its completed one and its pending re-defense — so without
                                                       this the same group reads as listed twice, once under
                                                       Completed and once under Scheduled, with nothing saying
                                                       they are different attempts. --}}
                                                    @if (($attempt->attempt_number ?? 1) > 1)
                                                        <span class="badge badge-info-tint mb-1 d-inline-block">Attempt {{ $attempt->attempt_number }}</span>
                                                    @endif
                                                    @if ($attempt->researchGroup->current_project_title)
                                                        {{ $attempt->researchGroup->current_project_title }}
                                                    @elseif ($attempt->researchGroup->proposedTitles->isNotEmpty())
                                                        @foreach ($attempt->researchGroup->proposedTitles as $proposedTitle)
                                                            <div>{{ $proposedTitle->title_text }}</div>
                                                        @endforeach
                                                    @else
                                                        <span class="text-brand-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div>{{ $leader?->full_name ?? '—' }} <span class="badge badge-brand-tint ms-1">Leader</span></div>
                                                    @foreach ($members as $member)
                                                        <div class="text-brand-muted">{{ $member->full_name }}</div>
                                                    @endforeach
                                                </td>
                                                <td>
                                                    @forelse ($assigned as $pa)
                                                        <span class="badge {{ $pa->is_lead ? 'badge-success-tint' : 'badge-muted-tint' }} mb-1 d-inline-block">{{ $pa->is_lead ? 'Chair: ' : 'Member ' . ($memberNumbers[$pa->id] ?? '') . ': ' }}{{ trim(($pa->panelist->profile->first_name ?? '') . ' ' . ($pa->panelist->profile->last_name ?? '')) }}</span>
                                                    @empty
                                                        <span class="text-brand-muted">No panelists assigned</span>
                                                    @endforelse
                                                    @foreach ($backup as $pb)
                                                        <span class="badge badge-brand-tint mb-1 d-inline-block">Alternate Panel: {{ trim(($pb->panelist->profile->first_name ?? '') . ' ' . ($pb->panelist->profile->last_name ?? '')) }}</span>
                                                    @endforeach
                                                    @if ($panelIssue && $assigned->isNotEmpty())
                                                        <div><span class="badge badge-danger-tint" title="{{ $panelIssue }}">Reassign &mdash; needs {{ $room->panelist_count }}</span></div>
                                                    @endif
                                                    @foreach ($rowConflicts as $rowConflict)
                                                        <div><span class="badge badge-danger-tint" title="{{ $rowConflict['message'] }}">Conflict: {{ $rowConflict['panelistName'] }}</span></div>
                                                    @endforeach
                                                    @foreach ($unavailabilityReports as $report)
                                                        @php
                                                            $reporterName = trim(($report->originalPanelist->profile->first_name ?? '') . ' ' . ($report->originalPanelist->profile->last_name ?? '')) ?: ($report->originalPanelist->username ?? 'A panelist');
                                                        @endphp
                                                        <div class="mt-1">
                                                            <span class="badge badge-danger-tint" title="{{ $report->originalPanelist?->trashed() ? $reporterName . ' was deleted from the system' : $reporterName . ' reported unavailable — pending review' }}">Needs Reassignment</span>
                                                            <button type="button" class="btn btn-sm btn-outline-danger-brand mt-1" data-bs-toggle="modal" data-bs-target="#assign-replacement-modal-{{ $report->id }}">
                                                                <x-icon name="user-check" /> Assign Replacement
                                                            </button>
                                                        </div>
                                                    @endforeach
                                                </td>
                                                <td class="text-end">
                                                    @if ($canTransfer)
                                                    <div class="dropdown">
                                                        <button type="button" class="row-actions-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Row actions">
                                                            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.75"></circle><circle cx="12" cy="12" r="1.75"></circle><circle cx="12" cy="19" r="1.75"></circle></svg>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            @if ($canChange)
                                                                <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#edit-group-modal-{{ $attempt->researchGroup->id }}">Edit</button></li>
                                                            @endif
                                                            @if ($canChange && $isReDefense)
                                                                {{-- Its position is not the admin's to pick: a pending
                                                                   re-defense is held at the end of its room by
                                                                   QueueAdjustmentService::applyOrder(), so a Move here
                                                                   would simply undo itself. Transfer still works — that
                                                                   chooses the room, and it goes last in whichever one. --}}
                                                                <li><span class="dropdown-item disabled" title="A re-defense always presents last in its room. Use Transfer to move it to another room or day.">Move</span></li>
                                                            @elseif ($canChange)
                                                                <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#reorder-modal-{{ $attempt->id }}">Move</button></li>
                                                            @endif
                                                            <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#transfer-modal-{{ $attempt->id }}">Transfer</button></li>
                                                            @if ($canChange)
                                                                @if ($hasSubmittedEvaluation)
                                                                    <li><span class="dropdown-item disabled" title="A panelist has already submitted an evaluation for this group — it can no longer be deferred.">Defer</span></li>
                                                                @else
                                                                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#defer-modal-{{ $attempt->id }}">Defer</button></li>
                                                                @endif
                                                            @endif
                                                            {{-- Delete works on any group not presenting right now —
                                                               running day or "to be scheduled" included
                                                               (user-directed 2026-09-30). --}}
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#delete-modal-{{ $attempt->id }}">Delete</button></li>
                                                        </ul>
                                                    </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div data-tab-pane="deferred" class="d-none">
                        @if ($deferredRows->isEmpty())
                            <p class="text-brand-muted small">No deferred groups.</p>
                        @else
                            <div class="table-responsive roster-scroll">
                                <table class="table table-sm align-middle small mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Group</th>
                                            <th>Title</th>
                                            <th>Members</th>
                                            <th>Reason</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($deferredRows as $row)
                                            @php
                                                $attempt = $row['attempt'];
                                                $room = $row['room'];
                                                $deferredEntry = $attempt->attemptSchedule->queueEntry;
                                                $lastDefer = $deferredEntry->queueAdjustments->first(fn ($adjustment) => $adjustment->adjustmentType?->code === 'DEFER');
                                                $attemptPaymentSummary = $paymentSummaryByAttempt[$attempt->id] ?? null;
                                                $paymentResolved = $attemptPaymentSummary['allSatisfied'] ?? true;
                                                $leader = $attempt->researchGroup->leader();
                                                $members = $attempt->researchGroup->students->reject(fn ($s) => $s->is_leader);
                                            @endphp
                                            <tr data-room-row="{{ $room->room_name }}" data-focus="attempt-{{ $attempt->id }}" data-focus-reveal="[data-tab-btn='deferred']">
                                                <td>{{ $deferredEntry->removed_at?->format('M j, Y') }}</td>
                                                <td>{{ $deferredEntry->removed_at?->format('g:i A') }}</td>
                                                <td>{{ $attempt->researchGroup->group_reference }}</td>
                                                <td>
                                                    {{-- A group past its first attempt has a row per attempt —
                                                       its completed one and its pending re-defense — so without
                                                       this the same group reads as listed twice, once under
                                                       Completed and once under Scheduled, with nothing saying
                                                       they are different attempts. --}}
                                                    @if (($attempt->attempt_number ?? 1) > 1)
                                                        <span class="badge badge-info-tint mb-1 d-inline-block">Attempt {{ $attempt->attempt_number }}</span>
                                                    @endif
                                                    @if ($attempt->researchGroup->current_project_title)
                                                        {{ $attempt->researchGroup->current_project_title }}
                                                    @elseif ($attempt->researchGroup->proposedTitles->isNotEmpty())
                                                        @foreach ($attempt->researchGroup->proposedTitles as $proposedTitle)
                                                            <div>{{ $proposedTitle->title_text }}</div>
                                                        @endforeach
                                                    @else
                                                        <span class="text-brand-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div>{{ $leader?->full_name ?? '—' }} <span class="badge badge-brand-tint ms-1">Leader</span></div>
                                                    @foreach ($members as $member)
                                                        <div class="text-brand-muted">{{ $member->full_name }}</div>
                                                    @endforeach
                                                </td>
                                                <td>
                                                    @if ($lastDefer?->reason)
                                                        <span class="badge badge-muted-tint">{{ $lastDefer->reason->name }}</span>
                                                    @else
                                                        <span class="text-brand-muted">—</span>
                                                    @endif
                                                    @if ($paymentRequired && $paymentResolved)
                                                        <span class="badge badge-success-tint">Payment verified</span>
                                                    @endif
                                                    @if ($lastDefer?->remarks)
                                                        <div class="small text-brand-muted fst-italic">&ldquo;{{ $lastDefer->remarks }}&rdquo;</div>
                                                    @endif
                                                </td>
                                                <td class="text-nowrap">
                                                    @if ($attempt->attemptSchedule->presentationDateRoom->presentationDate->isOpenForScheduling())
                                                        <button type="button" class="btn btn-sm btn-outline-brand" data-bs-toggle="modal" data-bs-target="#edit-group-modal-{{ $attempt->researchGroup->id }}"><x-icon name="edit" /> Edit</button>
                                                    @endif
                                                    @if ($paymentRequired && ! $paymentResolved)
                                                        <button type="button" class="btn btn-sm btn-outline-brand" data-bs-toggle="modal" data-bs-target="#verify-payment-modal-{{ $attempt->id }}"><x-icon name="shield-check" /> Verify Payment</button>
                                                    @endif
                                                    <button type="button" class="btn btn-sm btn-brand" data-bs-toggle="modal" data-bs-target="#reinsert-modal-{{ $attempt->id }}"><x-icon name="reinsert" /> Reinsert</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger-brand" data-bs-toggle="modal" data-bs-target="#delete-deferred-modal-{{ $attempt->id }}"><x-icon name="trash" /> Delete</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div data-tab-pane="redefense" class="d-none">
                        @if ($reDefenseRows->isEmpty())
                            <p class="text-brand-muted small">No groups are waiting on a re-defense.</p>
                        @else
                            <div class="table-responsive roster-scroll">
                                <table class="table table-sm align-middle small mb-0">
                                    <thead>
                                        <tr>
                                            <th>Group</th>
                                            <th>Members</th>
                                            <th>Attempts</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($reDefenseRows as $row)
                                            @php
                                                $group = $row['group'];
                                                $latest = $row['latest'];
                                                $leader = $group->leader();
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $group->group_reference }}</div>
                                                    <div class="text-brand-muted">{{ $group->current_project_title }}</div>
                                                </td>
                                                <td>
                                                    <div>{{ $leader?->full_name ?? '—' }} <span class="badge badge-brand-tint ms-1">Leader</span></div>
                                                    @foreach ($group->students->where('is_leader', false) as $member)
                                                        <div class="text-brand-muted">{{ $member->full_name }}</div>
                                                    @endforeach
                                                </td>
                                                <td>
                                                    <table class="table table-sm mb-0 border-0">
                                                        <tbody>
                                                            @foreach ($row['attempts'] as $attempt)
                                                                @php
                                                                    $submitted = $attempt->evaluationSubmissions->filter(
                                                                        fn ($s) => in_array($s->submissionStatus?->code, ['SUBMITTED', 'FINALIZED'], true)
                                                                    );
                                                                @endphp
                                                                <tr>
                                                                    <td class="ps-0 border-0">Attempt {{ $attempt->attempt_number }}</td>
                                                                    <td class="border-0">
                                                                        {{-- Planned-vs-actual: a finished attempt is described
                                                                             by its own completed_at, never the slot it was
                                                                             planned into. --}}
                                                                        {{ $attempt->completed_at?->format('M j, Y g:i A') ?? '—' }}
                                                                    </td>
                                                                    <td class="border-0">
                                                                        @if ($attempt->finalOutcome)
                                                                            <span class="badge {{ $attempt->finalOutcome->requires_new_attempt ? 'badge-danger-tint' : 'badge-muted-tint' }}">{{ $attempt->finalOutcome->name }}</span>
                                                                        @endif
                                                                    </td>
                                                                    <td class="text-end pe-0 border-0">
                                                                        <button type="button" class="btn btn-sm btn-outline-brand border-0"
                                                                                data-view-sheet
                                                                                data-url="{{ route('admin.reports.evaluation-sheet', [$category, $attempt->id]) }}"
                                                                                data-group="{{ $group->group_reference }} (Attempt {{ $attempt->attempt_number }})"
                                                                                @disabled($submitted->isEmpty())
                                                                                title="{{ $submitted->isEmpty() ? 'No submitted evaluations for this attempt' : '' }}"><x-icon name="eye" /> View Evaluation</button>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-brand" data-bs-toggle="modal"
                                                            data-bs-target="#redefense-modal-{{ $latest->id }}"
                                                            @disabled($reDefenseTargetRoom === null)
                                                            title="{{ $reDefenseTargetRoom === null ? 'No ongoing or upcoming presentation date is configured' : '' }}"><x-icon name="repeat" /> Reinsert for Another Attempt</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card-brand p-4 d-flex flex-column" id="assign-panel-card" style="position: sticky; top: 1rem; max-height: calc(100vh - 3rem); overflow: hidden; box-sizing: border-box;">
                        <h3 class="h6 mb-2">Assign Panel</h3>

                        <div class="assign-status mb-3" id="assign-status">
                            <div class="assign-status-head">
                                <span class="assign-status-count" id="assign-status-count">0</span>
                                <span class="assign-status-label" id="assign-selection-summary">No groups selected</span>
                            </div>

                            <div class="assign-status-alert" id="assign-status-alert" hidden></div>

                            <ul class="assign-req-list" id="assign-req-list" hidden>
                                <li class="assign-req" id="req-panelists">
                                    <span class="assign-req-icon"></span>
                                    <span class="assign-req-name">Panelists</span>
                                    <span class="assign-req-value">0 / 0</span>
                                </li>
                                <li class="assign-req" id="req-lead">
                                    <span class="assign-req-icon"></span>
                                    <span class="assign-req-name">Chair</span>
                                    <span class="assign-req-value">Not selected</span>
                                </li>
                                <li class="assign-req" id="req-backup">
                                    <span class="assign-req-icon"></span>
                                    <span class="assign-req-name">Alternate Panel</span>
                                    <span class="assign-req-value">Not selected</span>
                                </li>
                            </ul>
                        </div>

                        <div class="mb-2 d-flex flex-column flex-grow-1" style="min-height: 0;">
                            <label class="form-label small mb-1">Assigned Panelists</label>
                            <input type="search" class="form-control form-control-sm" id="panelist-filter" placeholder="Search panelist name…" autocomplete="off">
                            <p class="panelist-search-empty" id="panelist-search-empty" hidden></p>
                            <div style="overflow-y: auto; flex: 1 1 auto; min-height: 0;" class="border rounded p-2 mt-2" id="assigned-panelist-list">
                                @foreach ($panelists as $panelist)
                                    @php $counts = $panelistAssignmentCounts[$panelist->id] ?? ['assigned' => 0, 'backup' => 0]; @endphp
                                    <div class="form-check panelist-row d-flex align-items-start gap-2">
                                        <input class="form-check-input assigned-panelist-checkbox mt-1" type="checkbox" name="assigned_panelist_ids[]" value="{{ $panelist->id }}" id="assigned-{{ $panelist->id }}"
                                               data-panelist-names="{{ implode('|', $panelistNameVariants[$panelist->id] ?? []) }}"
                                               data-display-name="{{ trim(($panelist->profile->first_name ?? '') . ' ' . ($panelist->profile->last_name ?? '')) }}">
                                        <label class="form-check-label small panelist-label flex-grow-1" for="assigned-{{ $panelist->id }}">
                                            {{ trim(($panelist->profile->first_name ?? '') . ' ' . ($panelist->profile->last_name ?? '')) }}
                                            @if ($panelist->panelistProfile?->college)
                                                <span class="text-brand-muted" title="{{ $panelist->panelistProfile->college->name }}">&middot; {{ $panelist->panelistProfile->college->code ?: $panelist->panelistProfile->college->name }}</span>
                                            @endif
                                            @if ($panelist->panelistProfile?->specialization)
                                                <span class="text-brand-muted" title="Field of Specialization">&middot; {{ $panelist->panelistProfile->specialization }}</span>
                                            @endif
                                            <span class="badge badge-muted-tint ms-1">{{ $counts['assigned'] }} assigned</span>
                                            <span class="badge badge-brand-tint ms-1">{{ $counts['backup'] }} alternate</span>
                                            <span class="badge badge-danger-tint ms-1 adviser-badge" hidden>Adviser</span>
                                        </label>
                                        <label class="small text-brand-muted d-flex align-items-center gap-1 mt-1" style="white-space: nowrap;" title="Designate as Chair">
                                            <input type="radio" class="form-check-input lead-panelist-radio" name="lead_panelist_id" value="{{ $panelist->id }}" disabled>
                                            Chair
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3 flex-shrink-0">
                            <label for="backup_panelist_id" class="form-label small mb-1">Alternate Panel ({{ $backupSeats }})</label>
                            <select name="backup_panelist_id" id="backup_panelist_id" class="form-select form-select-sm">
                                <option value="">Select alternate panel…</option>
                                @foreach ($panelists as $panelist)
                                    @php $counts = $panelistAssignmentCounts[$panelist->id] ?? ['assigned' => 0, 'backup' => 0]; @endphp
                                    <option value="{{ $panelist->id }}" data-panelist-names="{{ implode('|', $panelistNameVariants[$panelist->id] ?? []) }}">
                                        {{ trim(($panelist->profile->first_name ?? '') . ' ' . ($panelist->profile->last_name ?? '')) }}
                                        ({{ $counts['assigned'] }} assigned, {{ $counts['backup'] }} alternate)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="btn btn-brand w-100 flex-shrink-0" id="assign-submit-btn" disabled><x-icon name="user-check" /> Assign to Selected Groups</button>
                    </div>
                </div>
            </div>
        </form>

        @push('styles')
            <style>
                /* Fluid type sizes and sidebar-colored cards come from the
                   shared .page-shell (theme-head); these bring this page's
                   own fixed-size text onto the same scale. */

                /* User-directed 2026-09-16: this page takes its full width
                   back. .page-shell's centered 85% column costs the roster
                   the room it needs (Date/Time/Title/Members/Panel side by
                   side, next to the Assign Panel sidebar). Overridden here
                   only — Presentation Setup keeps the 85% column. */
                .assign-page {
                    width: 100%;
                }

                .assign-page .notification-tab {
                    font-size: var(--page-fs-sm);
                }

                .assign-page .dropdown-item {
                    font-size: var(--page-fs-sm);
                }

                .assign-page .small,
                .assign-page .form-label.small {
                    font-size: var(--page-fs-sm);
                }

                .assign-page .table .small,
                .assign-page .badge .small {
                    font-size: var(--page-fs-xs);
                }

                /* On phones the roster keeps a readable width and scrolls
                   sideways inside .roster-scroll instead of squeezing every
                   column until dates and names wrap letter-block tall. */
                @media (max-width: 767.98px) {
                    .assign-page .roster-scroll > .table {
                        min-width: 46rem;
                    }
                }

                /* Same base .notification-tabs/.notification-tab styling the
                   Panelist Management drawer uses (theme-head.blade.php) —
                   just a red variant for the Deferred tab when it isn't empty,
                   so it reads as a warning instead of a plain tab. */
                /* Technical adviser of a currently-selected group — kept
                   visible (so it's obvious who it is and why the seat can't
                   be filled by them) but reads as unavailable. */
                .panelist-row.is-adviser-muted {
                    opacity: 0.5;
                }

                /* The Reinsert modal's read-only "here is the room's current
                   order" list — capped so a long room queue scrolls inside
                   the modal rather than pushing the position input and the
                   Reinsert button below the fold. */
                .reinsert-order {
                    max-height: 11rem;
                    overflow-y: auto;
                    border: 1px solid var(--brand-border);
                    border-radius: 0.5rem;
                    padding: 0.25rem 0.6rem;
                }

                /* Search hit — the row is highlighted in place and scrolled
                   to the top of the list; non-matching panelists stay
                   visible and pickable.

                   Only padding-block here: .panelist-row is a Bootstrap
                   .form-check, whose padding-left: 1.5em is the gutter the
                   checkbox is pulled into by its own margin-left: -1.5em. A
                   `padding` shorthand wipes that gutter out and drags the
                   checkbox outside the scroll container, where it's clipped. */
                .panelist-row {
                    border-radius: 0.4rem;
                    padding-block: 0.2rem;
                    transition: background-color 0.2s ease, box-shadow 0.2s ease;
                }

                .panelist-row.is-search-match {
                    background-color: var(--brand-accent-tint);
                    box-shadow: inset 0 0 0 1px var(--brand-accent);
                }

                .panelist-search-empty {
                    margin: 0.35rem 0 0;
                    font-size: var(--page-fs-xs);
                    color: var(--brand-danger);
                }

                /* ---------- Assign Panel status ----------
                   Selection count + a live requirements checklist, replacing
                   the two plain sentences that used to sit here. Each row
                   flips to the accent colour the moment its requirement is
                   satisfied, so the remaining work is readable at a glance. */
                /* Empty state is chrome-free — the box only draws itself once
                   there's a selection to report on. Border stays present but
                   transparent so nothing shifts when it appears. */
                /* flex-shrink: 0 is load-bearing, not cosmetic: this box is a
                   flex item of #assign-panel-card, which is a fixed-height
                   (JS-sized) column with overflow:hidden. Left shrinkable, a
                   short viewport squeezed this box instead of the scrollable
                   panelist list below it, and its own overflow:hidden then
                   silently clipped the requirement chips away under the
                   header row. The panelist list already scrolls, so it is
                   the thing that should absorb a short card, not this. */
                .assign-status {
                    flex-shrink: 0;
                    border: 1px solid transparent;
                    border-radius: 0.75rem;
                    overflow: hidden;
                    background-color: transparent;
                    transition: border-color 0.15s ease, background-color 0.15s ease;
                }

                .assign-status.has-selection {
                    border-color: var(--brand-border);
                    background-color: var(--brand-surface);
                }

                .assign-status-head {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    padding: 0.55rem 0.75rem;
                    border-bottom: 1px solid transparent;
                    transition: border-color 0.15s ease, background-color 0.15s ease, padding 0.15s ease;
                }

                /* Sit flush with the "Assign Panel" heading while empty. */
                .assign-status:not(.has-selection) .assign-status-head {
                    padding-left: 0;
                    padding-right: 0;
                }

                .assign-status.has-selection .assign-status-head {
                    border-bottom-color: var(--brand-border);
                    background-color: var(--brand-accent-tint);
                }

                .assign-status-count {
                    flex-shrink: 0;
                    min-width: 1.5rem;
                    height: 1.5rem;
                    padding: 0 0.4rem;
                    border-radius: 999px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 0.78rem;
                    font-weight: 700;
                    color: var(--brand-muted);
                    background-color: var(--brand-border);
                    transition: color 0.15s ease, background-color 0.15s ease;
                }

                .assign-status.has-selection .assign-status-count {
                    color: var(--brand-accent-contrast);
                    background-color: var(--brand-accent);
                }

                .assign-status-label {
                    font-size: var(--page-fs-sm);
                    font-weight: 600;
                    color: var(--brand-text);
                }

                .assign-status-alert {
                    display: flex;
                    align-items: flex-start;
                    gap: 0.4rem;
                    padding: 0.55rem 0.75rem;
                    font-size: var(--page-fs-xs);
                    line-height: 1.35;
                    color: var(--brand-danger);
                }

                .assign-status-alert::before {
                    content: '!';
                    flex-shrink: 0;
                    width: 1rem;
                    height: 1rem;
                    border-radius: 50%;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 0.7rem;
                    font-weight: 700;
                    color: #fff;
                    background-color: var(--brand-danger);
                }

                /* One inline row of chips rather than three stacked rows —
                   all three requirements stay readable at a glance without
                   the status box getting any taller as it fills in. */
                .assign-req-list {
                    list-style: none;
                    margin: 0;
                    padding: 0.4rem 0.75rem 0.5rem;
                    display: flex;
                    flex-flow: row wrap;
                    align-items: center;
                    gap: 0.3rem 0.75rem;
                }

                .assign-req {
                    display: flex;
                    align-items: center;
                    gap: 0.3rem;
                    font-size: var(--page-fs-xs);
                }

                .assign-req-icon {
                    flex-shrink: 0;
                    width: 0.95rem;
                    height: 0.95rem;
                    border-radius: 50%;
                    border: 1.5px solid var(--brand-border);
                    position: relative;
                    transition: background-color 0.15s ease, border-color 0.15s ease;
                }

                .assign-req.is-done .assign-req-icon {
                    background-color: var(--brand-accent);
                    border-color: var(--brand-accent);
                }

                /* Checkmark drawn from two borders so no icon font/SVG is
                   needed for a mark this small. */
                .assign-req.is-done .assign-req-icon::after {
                    content: '';
                    position: absolute;
                    left: 0.28rem;
                    top: 0.1rem;
                    width: 0.22rem;
                    height: 0.42rem;
                    border: solid var(--brand-accent-contrast);
                    border-width: 0 2px 2px 0;
                    transform: rotate(45deg);
                }

                .assign-req-name {
                    flex: 0 0 auto;
                    color: var(--brand-muted);
                }

                .assign-req.is-done .assign-req-name {
                    color: var(--brand-text);
                }

                .assign-req-value {
                    flex-shrink: 1;
                    min-width: 0;
                    max-width: 8rem;
                    font-weight: 600;
                    color: var(--brand-muted);
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .assign-req.is-done .assign-req-value {
                    color: var(--brand-accent);
                }

                .notification-tab.tab-warning {
                    color: var(--brand-danger);
                }

                .notification-tab.tab-warning.active {
                    border-bottom-color: var(--brand-danger);
                }

                /* User-directed 2026-08-25: the roster list is the only
                   part of this page that should scroll — search bar, tabs,
                   and the sticky Assign Panel sidebar all stay put while
                   a long queue scrolls inside this fixed-height box
                   instead of growing the whole page. Applied to both the
                   active/completed table and the deferred list so
                   switching tabs doesn't change the page's height.

                   User-directed 2026-09-07: the old flat 60vh cut the box
                   off well short of the actual viewport bottom whenever
                   the header above it (Needs Attention banner, search bar,
                   tabs) took up more or less room than assumed — the
                   60vh figure had nothing to do with how much space was
                   actually left below it. max-height here is now just a
                   safety-net fallback (in case JS hasn't run yet); the
                   real sizing is computed in JS below from this box's
                   actual on-screen top position, so it always fills
                   exactly down to the viewport's bottom edge with nothing
                   cut off, on any screen height. */
                .roster-scroll {
                    max-height: 60vh;
                    overflow-y: auto;
                }

                .roster-scroll thead th {
                    position: sticky;
                    top: 0;
                    background: var(--brand-surface);
                    z-index: 1;
                }

                /* A row whose panel has a live scheduling conflict — one of
                   its panelists is booked somewhere else at the same time.
                   User-directed 2026-09-22: steady, not a blink. The blink
                   (.focus-flash) is for landing on one row from another
                   page and fades; this is a standing state that has to stay
                   visible until the panel is actually fixed. The whole row
                   opens that group's Panel Conflict modal, and the reason
                   is its title tooltip. */
                .roster-row-conflict > td {
                    background: var(--brand-danger-tint);
                    box-shadow: inset 0 -1px 0 var(--brand-danger);
                }

                .roster-row-conflict > td:first-child {
                    box-shadow: inset 3px 0 0 var(--brand-danger), inset 0 -1px 0 var(--brand-danger);
                }

                .roster-row-conflict {
                    cursor: pointer;
                }

                .roster-row-conflict:hover > td {
                    background: var(--brand-danger-tint);
                    filter: brightness(0.97);
                }

                .conflict-detail {
                    background: var(--brand-surface);
                    border-radius: 0.5rem;
                    padding: 0.6rem 0.75rem;
                }
                /* Date band separating each presentation day's block of
                   rows — the roster is ordered nearest day first, and a
                   day's groups are never interleaved with another's. */
                .roster-date-row > td {
                    background: var(--brand-accent-tint);
                    color: var(--brand-accent);
                    font-weight: 600;
                    letter-spacing: 0.01em;
                    border-top: 1px solid var(--brand-border);
                }
            </style>
        @endpush

        {{-- Row-action modals: kept outside the assign form above (a <form> cannot nest another <form>) --}}
        @php $renderedGroupEditModals = []; @endphp
        @foreach ($roomGroups as $group)
            @foreach ($group['active'] as $attempt)
                @php
                    $schedule = $attempt->attemptSchedule;
                    // Same floor the Reinsert modal uses and
                    // QueueAdjustmentService::firstOpenPosition() enforces: a
                    // group that has already presented keeps its number.
                    $moveLockedThrough = (int) $group['active']
                        ->filter(fn ($queued) => $queued->presentationStatus?->is_terminal || in_array($queued->presentationStatus?->code, ['ONGOING', 'PAUSED'], true))
                        ->max(fn ($queued) => $queued->attemptSchedule->queueEntry->queue_number);
                    $moveMinPosition = $moveLockedThrough + 1;
                    $hasSubmittedEvaluation = $hasSubmittedEvaluationByAttempt[$attempt->id] ?? false;
                @endphp

                @unless (in_array($attempt->researchGroup->id, $renderedGroupEditModals, true))
                    @php $renderedGroupEditModals[] = $attempt->researchGroup->id; @endphp
                    @include('admin.panel-assignments.partials.edit-group-modal', ['editGroup' => $attempt->researchGroup])
                @endunless

                {{-- Move/Transfer/Defer/Delete are all refused server-side for an
                   attempt with a recorded outcome, and the row dropdown already
                   hides their triggers, so a completed group has no reason to
                   carry four unreachable modals. Edit above stays: a completed
                   group's registration details are still editable. --}}
                @unless ($attempt->presentationStatus?->is_terminal)
                {{-- A pending re-defense has no Move trigger (its position is
                   fixed at the end of the room), so it needs no Move modal. --}}
                @unless ($attempt->attemptType?->code === 'RE_DEFENSE')
                <div class="modal fade" id="reorder-modal-{{ $attempt->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.panel-assignments.reorder', $category) }}">
                                @csrf
                                <input type="hidden" name="schedule_ids[]" value="{{ $schedule->id }}">
                                <div class="modal-header">
                                    <h5 class="modal-title">Move {{ $attempt->researchGroup->group_reference }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    @include('admin.panel-assignments.partials.queue-position-field', [
                                        'rows' => $group['active'],
                                        'roomName' => $schedule->presentationDateRoom->room_name,
                                        'minPosition' => $moveMinPosition,
                                        'maxPosition' => $group['active']->count(),
                                        'defaultPosition' => max($moveMinPosition, $schedule->queueEntry->queue_number),
                                        'lockedThrough' => $moveLockedThrough,
                                        'currentAttemptId' => $attempt->id,
                                    ])
                                    <div class="mb-3">
                                        <label class="form-label">Reason</label>
                                        <select name="reason_id" class="form-select" required>
                                            @foreach ($adjustmentReasons as $reason)
                                                <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">Remarks (optional)</label>
                                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-brand"><x-icon name="move" /> Move</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endunless

                <div class="modal fade" id="transfer-modal-{{ $attempt->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.panel-assignments.transfer', $category) }}">
                                @csrf
                                <input type="hidden" name="schedule_ids[]" value="{{ $schedule->id }}">
                                <div class="modal-header">
                                    <h5 class="modal-title">Transfer {{ $attempt->researchGroup->group_reference }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Target date &amp; room</label>
                                        @php
                                            // Only rooms that take this group's track (TrackRouting).
                                            $rowTransferRooms = $transferRooms->filter(fn ($room) => app(\App\Services\TrackRouting::class)->accepts($room, $attempt->researchGroup));
                                        @endphp
                                        <select name="target_room_id" class="form-select" required @disabled($rowTransferRooms->isEmpty())>
                                            @forelse ($rowTransferRooms as $targetRoom)
                                                <option value="{{ $targetRoom->id }}" @selected($targetRoom->id === $schedule->presentation_date_room_id)>
                                                    {{ $targetRoom->presentationDate->presentation_date->format('M j, Y') }} &mdash; {{ $targetRoom->room_name }}
                                                </option>
                                            @empty
                                                <option value="">No ongoing or upcoming date configured</option>
                                            @endforelse
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Reason</label>
                                        <select name="reason_id" class="form-select" required>
                                            @foreach ($adjustmentReasons as $reason)
                                                <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">Remarks (optional)</label>
                                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-brand"><x-icon name="transfer" /> Transfer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- No trigger reaches this once a panelist has already
                   scored the group (row dropdown swaps the trigger for a
                   disabled span above), so it carries no modal either. --}}
                @unless ($hasSubmittedEvaluation)
                <div class="modal fade" id="defer-modal-{{ $attempt->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.panel-assignments.defer', $category) }}">
                                @csrf
                                <input type="hidden" name="entry_ids[]" value="{{ $schedule->queueEntry->id }}">
                                <div class="modal-header">
                                    <h5 class="modal-title">Defer {{ $attempt->researchGroup->group_reference }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-brand-muted small">Removes this group from the active queue. It can be reinserted later.</p>
                                    <div class="mb-3">
                                        <label class="form-label">Reason</label>
                                        <select name="reason_id" class="form-select" required>
                                            @foreach ($deferReasons as $reason)
                                                <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">Remarks (optional)</label>
                                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="defer" /> Defer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endunless

                <div class="modal fade" id="delete-modal-{{ $attempt->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.panel-assignments.schedules.destroy', $category) }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="schedule_ids[]" value="{{ $schedule->id }}">
                                <div class="modal-header">
                                    <h5 class="modal-title">Delete {{ $attempt->researchGroup->group_reference }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-0">Removes this group's presentation from the queue entirely. This cannot be undone. The group's registration itself is not affected.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="trash" /> Delete</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Panel Conflict — opened by clicking the row itself
                   (user-directed 2026-09-22). A conflict always names one
                   panelist who is booked in two places at once, so this
                   replaces exactly those seats and leaves the rest of the
                   panel alone; the replacement inherits the outgoing
                   panelist's seat kind and Lead designation
                   (PanelAssignmentService::replacePanelists()). --}}
                @php $attemptConflicts = $conflictsByAttempt->get($attempt->id) ?? collect(); @endphp
                @if ($attemptConflicts->isNotEmpty() && ! $attempt->presentationStatus->is_terminal)
                    @php $onPanelIds = $attempt->attemptPanelAssignments->pluck('panelist_user_id'); @endphp
                    <div class="modal fade" id="panel-conflict-modal-{{ $attempt->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.panel-assignments.replace-panelists', [$category, $attempt]) }}">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title">Panel Conflict &mdash; {{ $attempt->researchGroup->group_reference }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        @foreach ($attemptConflicts as $attemptConflict)
                                            <div class="conflict-detail {{ $loop->last ? '' : 'mb-3' }}">
                                                <div class="fw-semibold mb-1">{{ $attemptConflict['panelistName'] }}</div>
                                                <p class="small text-brand-muted mb-2">
                                                    Also on {{ $attemptConflict['otherGroup'] }} &middot; {{ $attemptConflict['otherCategory'] }}@if ($attemptConflict['otherTime']) &middot; {{ $attemptConflict['otherTime'] }}@endif
                                                </p>
                                                <label class="form-label" for="conflict-{{ $attempt->id }}-{{ $attemptConflict['panelistId'] }}">Replace with</label>
                                                <select class="form-select" id="conflict-{{ $attempt->id }}-{{ $attemptConflict['panelistId'] }}"
                                                        name="replacements[{{ $attemptConflict['panelistId'] }}]">
                                                    <option value="">Keep {{ $attemptConflict['panelistName'] }}</option>
                                                    @foreach ($panelists as $panelist)
                                                        @continue($onPanelIds->contains($panelist->id))
                                                        <option value="{{ $panelist->id }}">{{ trim(($panelist->profile->first_name ?? '') . ' ' . ($panelist->profile->last_name ?? '')) ?: $panelist->username }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-brand"><x-icon name="user-check" /> Reassign</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
                {{-- Assign Replacement — resolves one substitute-less
                   unavailability report (§2.12) with exactly one named
                   replacement for the exact seat the reporting panelist
                   held. Nothing else about the panel (backup, other
                   assigned seats, who's Lead) is touched here. --}}
                @foreach ($unavailabilityReportsByAttempt->get($attempt->id) ?? [] as $report)
                    @php
                        $reporterName = trim(($report->originalPanelist->profile->first_name ?? '') . ' ' . ($report->originalPanelist->profile->last_name ?? '')) ?: ($report->originalPanelist->username ?? 'This panelist');
                        $onPanelIds = $attempt->attemptPanelAssignments->pluck('panelist_user_id');
                    @endphp
                    <div class="modal fade" id="assign-replacement-modal-{{ $report->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.panel-substitutions.assign-replacement', $report) }}">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title">Assign Replacement &mdash; {{ $attempt->researchGroup->group_reference }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="text-brand-muted small mb-3">{{ $reporterName }} {{ $report->originalPanelist?->trashed() ? 'was deleted from the system' : "reported unavailable for this group's panel" }}. Pick one replacement to fill that seat &mdash; everyone else on the panel stays as-is.</p>
                                        <div class="mb-0">
                                            <label class="form-label">Replacement Panelist</label>
                                            <select name="substitute_user_id" class="form-select" required>
                                                <option value="">Select a panelist&hellip;</option>
                                                @foreach ($panelists as $panelist)
                                                    @continue($onPanelIds->contains($panelist->id))
                                                    <option value="{{ $panelist->id }}">{{ trim(($panelist->profile->first_name ?? '') . ' ' . ($panelist->profile->last_name ?? '')) ?: $panelist->username }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-brand"><x-icon name="user-check" /> Assign Replacement</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
                @endunless
            @endforeach

            @foreach ($group['deferred'] as $attempt)
                @php
                    $schedule = $attempt->attemptSchedule;
                    $room = $group['room'];
                    $capacity = $roomDayCapacities[$room->id] ?? ['configured' => false];
                    // A completed/absent/cancelled group has already had its
                    // turn, so a reinserted group can never take or precede its
                    // number — the floor is the highest terminal position in the
                    // room, not simply the count of them (they are not guaranteed
                    // to sit contiguously at the front once groups have moved).
                    // A group presenting right now holds its slot the same way.
                    $lockedThrough = (int) $group['active']
                        ->filter(fn ($queued) => $queued->presentationStatus?->is_terminal || in_array($queued->presentationStatus?->code, ['ONGOING', 'PAUSED'], true))
                        ->max(fn ($queued) => $queued->attemptSchedule->queueEntry->queue_number);
                    $minPosition = $lockedThrough + 1;
                    $defaultPosition = max($minPosition, $group['active']->count() + 1);
                    $lastDefer = $schedule->queueEntry->queueAdjustments->first(fn ($adjustment) => $adjustment->adjustmentType?->code === 'DEFER');
                    $attemptPaymentSummary = $paymentSummaryByAttempt[$attempt->id] ?? null;
                    $paymentResolved = $attemptPaymentSummary['allSatisfied'] ?? true;
                @endphp

                @unless (in_array($attempt->researchGroup->id, $renderedGroupEditModals, true))
                    @php $renderedGroupEditModals[] = $attempt->researchGroup->id; @endphp
                    @include('admin.panel-assignments.partials.edit-group-modal', ['editGroup' => $attempt->researchGroup])
                @endunless

                <div class="modal fade" id="reinsert-modal-{{ $attempt->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.panel-assignments.reinsert', [$category, $schedule->queueEntry]) }}">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title">Reinsert {{ $attempt->researchGroup->group_reference }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-brand-muted small mb-3">Places this group back into {{ $room->room_name }}'s active queue on {{ $room->presentationDate->presentation_date->format('M j, Y') }}.</p>

                                    <div class="p-3 mb-3" style="background: var(--brand-surface-alt); border-radius: .5rem;">
                                        @if ($capacity['configured'])
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>Room capacity today</span>
                                                <span>{{ $capacity['used'] }} / {{ $capacity['slots'] }} slots used</span>
                                            </div>
                                            @if ($capacity['status'] === 'over')
                                                <p class="small mb-0" style="color: var(--brand-danger);">This room is already over its capacity for today &mdash; you can still reinsert, but the day is likely to run long.</p>
                                            @elseif ($capacity['status'] === 'exact')
                                                <p class="small mb-0" style="color: var(--brand-danger);">This room is at its capacity for today &mdash; you can still reinsert, but there's no slack left.</p>
                                            @else
                                                <p class="small mb-0 text-brand-muted">{{ $capacity['remaining'] }} slot(s) remaining today.</p>
                                            @endif
                                        @else
                                            <p class="small mb-0 text-brand-muted">Capacity for this room/day isn't fully configured yet.</p>
                                        @endif
                                    </div>

                                    @include('admin.panel-assignments.partials.queue-position-field', [
                                        'rows' => $group['active'],
                                        'roomName' => $room->room_name,
                                        'minPosition' => $minPosition,
                                        'maxPosition' => $defaultPosition,
                                        'defaultPosition' => $defaultPosition,
                                        'lockedThrough' => $lockedThrough,
                                        'currentAttemptId' => null,
                                    ])

                                    <div class="mb-3">
                                        <label class="form-label">Reason</label>
                                        <select name="reason_id" class="form-select" required>
                                            @foreach ($adjustmentReasons as $reason)
                                                <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">Remarks (optional)</label>
                                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-brand"><x-icon name="reinsert" /> Reinsert</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="delete-deferred-modal-{{ $attempt->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.panel-assignments.schedules.destroy', $category) }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="schedule_ids[]" value="{{ $schedule->id }}">
                                <div class="modal-header">
                                    <h5 class="modal-title">Delete {{ $attempt->researchGroup->group_reference }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-0">Removes this deferred group's presentation from the queue entirely. This cannot be undone. The group's registration itself is not affected.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="trash" /> Delete</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                @if ($paymentRequired && ! $paymentResolved)
                    <div class="modal fade" id="verify-payment-modal-{{ $attempt->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.panel-assignments.verify-payment', [$category, $attempt]) }}">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title">Verify Payment &mdash; {{ $attempt->researchGroup->group_reference }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="text-brand-muted small">
                                            Encode this group's payment receipt #(s) to mark them resolved.
                                            This does not reinsert the group into the queue &mdash; use Reinsert
                                            separately once you're ready to place them back.
                                        </p>
                                        @foreach ($paymentTypes as $type)
                                            @php
                                                $typeRow = collect($attemptPaymentSummary['types'] ?? [])->first(fn ($row) => $row['type']->id === $type->id);
                                                $typeSatisfied = $typeRow['satisfied'] ?? false;
                                            @endphp
                                            <div class="mb-3">
                                                <label class="form-label">{{ $type->name }}</label>
                                                @if ($typeSatisfied)
                                                    <div class="form-control-plaintext small">
                                                        <span class="badge badge-success-tint">{{ $typeRow['statusName'] ?? 'Verified' }}</span>
                                                    </div>
                                                @else
                                                    <input type="text" name="reference_numbers[{{ $type->id }}]" class="form-control"
                                                           maxlength="100" placeholder="Receipt #">
                                                @endif
                                            </div>
                                        @endforeach
                                        <div class="mb-0">
                                            <label class="form-label">Remarks (optional)</label>
                                            <textarea name="remarks" class="form-control" rows="2"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-brand"><x-icon name="check" /> Mark Verified &amp; Resolved</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        @endforeach

        {{-- Bulk action modals, driven by the selection checkboxes in the
             table above — hidden schedule_ids[]/entry_ids[] inputs are
             injected by workspace JS right before each modal opens (see
             the show.bs.modal listeners below), so these forms start with
             none. --}}
        <div class="modal fade" id="bulk-move-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.panel-assignments.reorder', $category) }}" id="bulk-move-form">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Move Selected Groups</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-brand-muted small" id="bulk-move-summary"></p>
                            <div class="mb-3">
                                <label class="form-label">New starting position in the room's queue</label>
                                <input type="number" name="position" class="form-control" min="1" required>
                                <p class="text-brand-muted small mb-0 mt-1">Selected groups keep their relative order, placed together starting at this position.</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <select name="reason_id" class="form-select" required>
                                    @foreach ($adjustmentReasons as $reason)
                                        <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Remarks (optional)</label>
                                <textarea name="remarks" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-brand"><x-icon name="move" /> Move</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="bulk-transfer-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.panel-assignments.transfer', $category) }}" id="bulk-transfer-form">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Transfer Selected Groups</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-brand-muted small" id="bulk-transfer-summary"></p>
                            <div class="mb-3">
                                <label class="form-label">Target date &amp; room</label>
                                <select name="target_room_id" class="form-select" required @disabled($transferRooms->isEmpty())>
                                    @forelse ($transferRooms as $targetRoom)
                                        <option value="{{ $targetRoom->id }}">
                                            {{ $targetRoom->presentationDate->presentation_date->format('M j, Y') }} &mdash; {{ $targetRoom->room_name }}
                                        </option>
                                    @empty
                                        <option value="">No ongoing or upcoming date configured</option>
                                    @endforelse
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <select name="reason_id" class="form-select" required>
                                    @foreach ($adjustmentReasons as $reason)
                                        <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Remarks (optional)</label>
                                <textarea name="remarks" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-brand"><x-icon name="transfer" /> Transfer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="bulk-defer-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.panel-assignments.defer', $category) }}" id="bulk-defer-form">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Defer Selected Groups</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-brand-muted small" id="bulk-defer-summary">Removes the selected groups from their active queues. They can be reinserted later.</p>
                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <select name="reason_id" class="form-select" required>
                                    @foreach ($deferReasons as $reason)
                                        <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Remarks (optional)</label>
                                <textarea name="remarks" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="defer" /> Defer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="bulk-delete-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.panel-assignments.schedules.destroy', $category) }}" id="bulk-delete-form">
                        @csrf
                        @method('DELETE')
                        <div class="modal-header">
                            <h5 class="modal-title">Delete Selected Groups</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0" id="bulk-delete-summary">Removes the selected groups' presentations from the queue entirely. This cannot be undone. The groups' registrations themselves are not affected.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="trash" /> Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Re-Defense confirmations. Outside #assign-form like every other
         per-row modal on this page (a <form> can't nest another), and one per
         eligible group rather than one shared modal, since each posts to its
         own attempt's route and there is nothing to fill in. --}}
    @foreach ($reDefenseRows as $row)
        @php
            $latest = $row['latest'];
            // This group's own landing spot — the room on the last open day
            // whose queue finishes last, with its old room preferred only on
            // a tie, so two groups can differ.
            $target = $reDefenseTargets[$latest->id] ?? ['room' => null, 'position' => null];
        @endphp
        <div class="modal fade" id="redefense-modal-{{ $latest->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.panel-assignments.re-defense', [$category, $latest]) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Re-Defense for {{ $row['group']->group_reference }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <dl class="row small mb-0">
                                <dt class="col-5 text-brand-muted fw-normal">Next attempt</dt>
                                <dd class="col-7 mb-2">Attempt {{ $latest->attempt_number + 1 }}</dd>

                                <dt class="col-5 text-brand-muted fw-normal">Room</dt>
                                <dd class="col-7 mb-2">{{ $target['room']?->room_name ?? '—' }}</dd>

                                <dt class="col-5 text-brand-muted fw-normal">Date</dt>
                                <dd class="col-7 mb-2">{{ $target['room']?->presentationDate->presentation_date->format('M j, Y') ?? '—' }}</dd>

                                <dt class="col-5 text-brand-muted fw-normal">Queue position</dt>
                                <dd class="col-7 mb-2">
                                    {{ $target['position'] ?? '—' }}
                                    <span class="badge badge-info-tint ms-1">Last in room</span>
                                </dd>

                                {{-- A new attempt carries no panel of its own: the
                                   previous attempt's assignments stay with that
                                   attempt as its record, and this one is assigned
                                   from scratch. --}}
                                <dt class="col-5 text-brand-muted fw-normal">Panel</dt>
                                <dd class="col-7 mb-0">{{ \App\Models\AttemptSchedule::AWAITING_PANEL }}</dd>
                            </dl>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-brand"><x-icon name="repeat" /> Schedule Re-Defense</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    </div>

    @include('admin.partials.evaluation-sheet-modal')
    @include('partials.soft-submit-script')

    @push('scripts')
        <script>
            (function () {
                var checkboxes = document.querySelectorAll('.attempt-checkbox');
                var assignedBoxes = document.querySelectorAll('.assigned-panelist-checkbox');
                var leadRadios = document.querySelectorAll('.lead-panelist-radio');
                var backupSelect = document.getElementById('backup_panelist_id');
                var summary = document.getElementById('assign-selection-summary');
                var statusBox = document.getElementById('assign-status');
                var statusCount = document.getElementById('assign-status-count');
                var statusAlert = document.getElementById('assign-status-alert');
                var reqList = document.getElementById('assign-req-list');
                var reqPanelists = document.getElementById('req-panelists');
                var reqLead = document.getElementById('req-lead');
                var reqBackup = document.getElementById('req-backup');
                var submitBtn = document.getElementById('assign-submit-btn');
                var filterInput = document.getElementById('panelist-filter');
                var panelistList = document.getElementById('assigned-panelist-list');
                var searchEmpty = document.getElementById('panelist-search-empty');

                function leadRadioFor(value) {
                    return document.querySelector('.lead-panelist-radio[value="' + value + '"]');
                }

                function assignedCheckboxFor(value) {
                    return document.getElementById('assigned-' + value);
                }

                function setSelectionCount(count) {
                    statusCount.textContent = count;
                    summary.textContent = count === 0
                        ? 'No groups selected'
                        : (count === 1 ? '1 group selected' : count + ' groups selected');
                    statusBox.classList.toggle('has-selection', count > 0);
                }

                function showAlert(message) {
                    statusAlert.textContent = message;
                    statusAlert.hidden = false;
                    reqList.hidden = true;
                }

                function displayNameFor(value) {
                    var cb = assignedCheckboxFor(value);
                    return (cb && cb.dataset.displayName) || 'Selected';
                }

                function setRequirement(row, done, value) {
                    row.classList.toggle('is-done', done);
                    row.querySelector('.assign-req-value').textContent = value;
                    row.title = row.querySelector('.assign-req-name').textContent + ': ' + value;
                }

                function showRequirements(required, assignedChecked, leadValue, backupValue) {
                    statusAlert.hidden = true;
                    reqList.hidden = false;

                    setRequirement(reqPanelists, assignedChecked === required, assignedChecked + ' / ' + required);
                    setRequirement(reqLead, !!leadValue, leadValue ? displayNameFor(leadValue) : 'Not selected');
                    setRequirement(reqBackup, backupValue !== '', backupValue !== '' ? displayNameFor(backupValue) : 'Not selected');
                }

                function clearStatusDetail() {
                    statusAlert.hidden = true;
                    reqList.hidden = true;
                }

                // User-directed 2026-09-07: a panelist picked as Backup
                // can't also be checked as an Assigned panelist, and vice
                // versa. Kept as one function (called at the top of every
                // refresh(), so it always wins over the "ran out of
                // required seats" disabling below) rather than two
                // separate change handlers, so the two pickers can never
                // drift out of sync with each other.
                function enforceBackupAssignedExclusion(advisers) {
                    var backupValue = backupSelect.value;

                    Array.prototype.forEach.call(backupSelect.options, function (opt) {
                        if (opt.value === '') return;
                        var cb = assignedCheckboxFor(opt.value);
                        opt.disabled = !!(cb && cb.checked) || matchesAdviser(opt, advisers);
                    });

                    assignedBoxes.forEach(function (cb) {
                        if (backupValue !== '' && backupValue === cb.value) {
                            if (cb.checked) {
                                cb.checked = false;
                                var radio = leadRadioFor(cb.value);
                                if (radio) { radio.disabled = true; radio.checked = false; }
                            }
                            cb.disabled = true;
                        }
                    });
                }

                // A group's own technical adviser can't sit on that group's
                // panel — the same rule PanelAssignmentService enforces
                // server-side at assign time, applied here the moment a
                // group is selected so the adviser simply isn't pickable
                // instead of the Admin only finding out after submitting.
                // Both sides of the comparison arrive already normalized
                // from the server (data-adviser / data-panelist-names), so
                // the browser never re-implements that name matching.
                function selectedAdviserNames() {
                    var names = {};
                    checkboxes.forEach(function (cb) {
                        if (cb.checked && cb.dataset.adviser) names[cb.dataset.adviser] = true;
                    });
                    return names;
                }

                function matchesAdviser(el, advisers) {
                    var variants = (el.dataset.panelistNames || '').split('|');
                    for (var i = 0; i < variants.length; i++) {
                        if (variants[i] !== '' && advisers[variants[i]]) return true;
                    }
                    return false;
                }

                function isAdviserMuted(cb) {
                    var row = cb.closest('.panelist-row');
                    return !!(row && row.classList.contains('is-adviser-muted'));
                }

                function enforceAdviserMuting(advisers) {
                    assignedBoxes.forEach(function (cb) {
                        var row = cb.closest('.panelist-row');
                        var badge = row ? row.querySelector('.adviser-badge') : null;
                        var wasMuted = isAdviserMuted(cb);
                        var muted = matchesAdviser(cb, advisers);

                        if (badge) badge.hidden = !muted;
                        if (row) row.classList.toggle('is-adviser-muted', muted);

                        if (muted) {
                            cb.checked = false;
                            cb.disabled = true;

                            var radio = leadRadioFor(cb.value);
                            if (radio) { radio.disabled = true; radio.checked = false; }
                        } else if (wasMuted) {
                            // Released — fall back to the Backup exclusion
                            // rule; the seat-count pass below refines it.
                            cb.disabled = backupSelect.value !== '' && backupSelect.value === cb.value;
                        }
                    });

                    var picked = backupSelect.options[backupSelect.selectedIndex];

                    if (backupSelect.value !== '' && picked && matchesAdviser(picked, advisers)) {
                        backupSelect.value = '';
                    }
                }

                function selectedRequiredCounts() {
                    var values = {};
                    checkboxes.forEach(function (cb) {
                        if (cb.checked) values[cb.dataset.required] = true;
                    });
                    return Object.keys(values);
                }

                function refresh() {
                    var advisers = selectedAdviserNames();

                    enforceBackupAssignedExclusion(advisers);
                    enforceAdviserMuting(advisers);

                    var selectedCount = Array.prototype.filter.call(checkboxes, function (cb) { return cb.checked; }).length;
                    var requiredValues = selectedRequiredCounts();
                    var assignedChecked = Array.prototype.filter.call(assignedBoxes, function (cb) { return cb.checked; }).length;
                    var backupPicked = backupSelect.value !== '';
                    var leadRadio = Array.prototype.filter.call(leadRadios, function (r) { return r.checked; })[0];
                    var leadPicked = !!leadRadio;

                    setSelectionCount(selectedCount);

                    if (selectedCount === 0) {
                        clearStatusDetail();
                        assignedBoxes.forEach(function (cb) { cb.disabled = false; });
                        enforceBackupAssignedExclusion(advisers);
                        enforceAdviserMuting(advisers);
                        submitBtn.disabled = true;
                        return;
                    }

                    if (requiredValues.length > 1) {
                        showAlert('Selected groups need different panel sizes — pick groups from rooms with the same panel size.');
                        submitBtn.disabled = true;
                        return;
                    }

                    var required = parseInt(requiredValues[0], 10) || 0;

                    if (required === 0) {
                        showAlert('This room has no panelist count configured yet.');
                        submitBtn.disabled = true;
                        return;
                    }

                    showRequirements(required, assignedChecked, leadPicked ? leadRadio.value : null, backupSelect.value);

                    var backupValue = backupSelect.value;
                    assignedBoxes.forEach(function (cb) {
                        if (isAdviserMuted(cb)) return; // stays disabled — adviser of a selected group
                        if (backupValue !== '' && backupValue === cb.value) return; // stays disabled — it's the Backup pick
                        if (!cb.checked) cb.disabled = assignedChecked >= required;
                    });

                    submitBtn.disabled = !(assignedChecked === required && backupPicked && leadPicked);
                }

                // Reassigning a group that already holds a panel replaces it
                // (the dropped panelists are marked REPLACED server-side), so
                // it gets a confirmation naming each group and the panel it
                // would lose. Groups with no panel yet submit straight
                // through — nothing is being overwritten there.
                var assignForm = document.getElementById('assign-form');
                var reassignModalEl = document.getElementById('reassign-confirm-modal');
                var reassignConfirmBtn = document.getElementById('reassign-confirm-btn');
                var reassignList = document.getElementById('reassign-confirm-list');
                var reassignLead = document.getElementById('reassign-confirm-lead');

                function alreadyAssignedSelection() {
                    return Array.prototype.filter.call(checkboxes, function (cb) {
                        return cb.checked && (cb.dataset.currentPanel || '').trim() !== '';
                    });
                }

                if (assignForm && reassignModalEl && reassignConfirmBtn) {
                    assignForm.addEventListener('submit', function (e) {
                        if (assignForm.dataset.reassignConfirmed === '1') return;

                        var affected = alreadyAssignedSelection();

                        if (affected.length === 0) return;

                        e.preventDefault();

                        reassignLead.textContent = affected.length === 1
                            ? '1 selected group already has a panel. Assigning replaces it:'
                            : affected.length + ' selected groups already have a panel. Assigning replaces them:';

                        reassignList.textContent = '';

                        affected.forEach(function (cb) {
                            var item = document.createElement('li');
                            var ref = document.createElement('strong');

                            ref.textContent = cb.dataset.groupRef || 'Group';
                            item.className = 'mb-1';
                            item.appendChild(ref);
                            item.appendChild(document.createTextNode(' — ' + cb.dataset.currentPanel));
                            reassignList.appendChild(item);
                        });

                        bootstrap.Modal.getOrCreateInstance(reassignModalEl).show();
                    });

                    reassignConfirmBtn.addEventListener('click', function () {
                        bootstrap.Modal.getOrCreateInstance(reassignModalEl).hide();
                        assignForm.dataset.reassignConfirmed = '1';
                        assignForm.requestSubmit();
                    });
                }

                checkboxes.forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        // A changed selection invalidates a prior
                        // confirmation — otherwise confirming once would
                        // silently cover a different set of groups picked
                        // afterward. Cleared here rather than on the modal's
                        // hidden.bs.modal, which fires after its dismiss
                        // animation and so would race the submit the confirm
                        // button itself kicks off.
                        if (assignForm) assignForm.dataset.reassignConfirmed = '';
                        refresh();
                    });
                });
                assignedBoxes.forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        // A panelist must be checked as Assigned before they
                        // can be picked as Lead — uncheck/disable their Lead
                        // radio the moment their Assigned checkbox clears.
                        var radio = leadRadioFor(cb.value);
                        if (radio) {
                            radio.disabled = !cb.checked;
                            if (!cb.checked) radio.checked = false;
                        }
                        refresh();
                    });
                });
                leadRadios.forEach(function (r) { r.addEventListener('change', refresh); });
                backupSelect.addEventListener('change', refresh);

                // Searching brings matches to the top of the list and
                // highlights them instead of hiding everyone else —
                // user-directed: the rest of the panel list has to stay
                // visible and pickable while you look someone up.
                if (filterInput && panelistList) {
                    filterInput.addEventListener('input', function () {
                        var term = filterInput.value.trim().toLowerCase();
                        var firstMatch = null;

                        document.querySelectorAll('.panelist-row').forEach(function (row) {
                            var cb = row.querySelector('.assigned-panelist-checkbox');
                            var name = ((cb && cb.dataset.displayName) || '').toLowerCase();
                            var matched = term !== '' && name.indexOf(term) !== -1;

                            row.classList.toggle('is-search-match', matched);

                            if (matched && !firstMatch) firstMatch = row;
                        });

                        if (searchEmpty) {
                            searchEmpty.textContent = 'No panelist named "' + filterInput.value.trim() + '"';
                            searchEmpty.hidden = term === '' || !!firstMatch;
                        }

                        if (firstMatch) {
                            var delta = firstMatch.getBoundingClientRect().top - panelistList.getBoundingClientRect().top;
                            panelistList.scrollTo({ top: panelistList.scrollTop + delta, behavior: 'smooth' });
                        }
                    });
                }

                refresh();
            })();

            // User-directed 2026-09-07: the roster box(es) (.roster-scroll)
            // and the Assign Panel sidebar should both fill down to the
            // viewport's bottom edge with nothing cut off — on any screen
            // height, without ever touching the very bottom edge — and a
            // flat vh/calc figure can't do that reliably since it has no
            // idea how tall the real content above each box rendered at.
            //
            // #assign-panel-card specifically: the CSS `max-height:
            // calc(100vh - 3rem)` fallback assumed `position: sticky`
            // always sits at exactly `top: 1rem`, but that's only true
            // once the page has scrolled far enough for it to actually
            // engage — on initial load (not yet scrolled), a sticky
            // element renders at its natural in-flow position instead,
            // which on this page is well below the navbar/header, so that
            // flat calc overflowed the viewport by however tall the
            // header above it happened to be. Sized here from the card's
            // own real on-screen top instead — same technique as the
            // roster boxes below — so it's correct whether or not it's
            // actually stuck yet. Recalculated on scroll too (not just
            // resize/load), since a sticky element's top can change as the
            // page scrolls right up until it engages.
            (function () {
                var bottomMarginPx = 32; // 2rem breathing room so neither box ever reaches the bottom edge of the screen
                var assignPanelCard = document.getElementById('assign-panel-card');
                var ticking = false;

                function sizeBox(box) {
                    var top = box.getBoundingClientRect().top;
                    var height = window.innerHeight - top - bottomMarginPx;
                    box.style.maxHeight = Math.max(200, height) + 'px';
                }

                function sizeAll() {
                    document.querySelectorAll('.roster-scroll').forEach(sizeBox);
                    if (assignPanelCard) sizeBox(assignPanelCard);
                    ticking = false;
                }

                function requestSize() {
                    if (ticking) return;
                    ticking = true;
                    window.requestAnimationFrame(sizeAll);
                }

                sizeAll();
                window.addEventListener('resize', requestSize);
                window.addEventListener('scroll', requestSize, { passive: true });
            })();

            (function () {
                var checkboxes = document.querySelectorAll('.attempt-checkbox');
                var bar = document.getElementById('bulk-actions-bar');
                var countLabel = document.getElementById('bulk-selection-count');
                var editBtn = document.getElementById('bulk-edit-btn');
                var moveBtn = document.getElementById('bulk-move-btn');
                var deferBtn = document.getElementById('bulk-defer-btn');
                var deleteBtn = document.getElementById('bulk-delete-btn');
                var assignedBoxes = document.querySelectorAll('.assigned-panelist-checkbox');
                var backupSelect = document.getElementById('backup_panelist_id');

                function selected() {
                    return Array.prototype.filter.call(checkboxes, function (cb) { return cb.checked; });
                }

                function anyPanelistSelected() {
                    var anyAssigned = Array.prototype.some.call(assignedBoxes, function (cb) { return cb.checked; });
                    return anyAssigned || (backupSelect && backupSelect.value !== '');
                }

                function refreshBar() {
                    var picked = selected();

                    if (picked.length === 0 || anyPanelistSelected()) {
                        bar.classList.add('d-none');
                        return;
                    }

                    bar.classList.remove('d-none');
                    countLabel.textContent = picked.length + ' selected';
                    editBtn.classList.toggle('d-none', picked.length !== 1);

                    // A group parked on a date that is over can only be
                    // transferred out of it; everything else needs an ongoing
                    // or upcoming date.
                    var anyAwaiting = picked.some(function (cb) { return cb.dataset.awaiting === '1'; });
                    var awaitingTitle = 'A group on a date that is over can only be transferred.';

                    editBtn.disabled = anyAwaiting;
                    editBtn.title = anyAwaiting ? awaitingTitle : '';
                    // Delete works on any selectable group, a day that is over
                    // included (user-directed 2026-09-30).
                    if (deleteBtn) {
                        deleteBtn.disabled = false;
                        deleteBtn.title = '';
                    }

                    var roomIds = picked.map(function (cb) { return cb.dataset.roomId; });
                    var sameRoom = roomIds.every(function (id) { return id === roomIds[0]; });
                    moveBtn.disabled = !sameRoom || anyAwaiting;
                    moveBtn.title = anyAwaiting ? awaitingTitle : (sameRoom ? '' : 'Select groups from the same room to move them together.');

                    var anyEvaluated = picked.some(function (cb) { return cb.dataset.hasEval === '1'; });
                    if (deferBtn) {
                        deferBtn.disabled = anyEvaluated || anyAwaiting;
                        deferBtn.title = anyAwaiting ? awaitingTitle : (anyEvaluated ? 'One or more selected groups already has a submitted evaluation and can no longer be deferred.' : '');
                    }
                }

                function populateIds(formId, inputName, valueKey) {
                    var form = document.getElementById(formId);
                    if (!form) return;

                    form.querySelectorAll('[data-bulk-id]').forEach(function (el) { el.remove(); });

                    selected().forEach(function (cb) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = inputName;
                        input.value = cb.dataset[valueKey];
                        input.setAttribute('data-bulk-id', '1');
                        form.appendChild(input);
                    });
                }

                checkboxes.forEach(function (cb) { cb.addEventListener('change', refreshBar); });
                assignedBoxes.forEach(function (cb) { cb.addEventListener('change', refreshBar); });
                if (backupSelect) backupSelect.addEventListener('change', refreshBar);

                editBtn.addEventListener('click', function () {
                    var picked = selected();
                    if (picked.length !== 1) return;

                    var modalEl = document.getElementById('edit-group-modal-' + picked[0].dataset.groupId);
                    if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
                });

                var moveModal = document.getElementById('bulk-move-modal');
                if (moveModal) {
                    moveModal.addEventListener('show.bs.modal', function () {
                        populateIds('bulk-move-form', 'schedule_ids[]', 'scheduleId');
                        document.getElementById('bulk-move-summary').textContent = selected().length + ' group(s) selected.';
                    });
                }

                var transferModal = document.getElementById('bulk-transfer-modal');
                if (transferModal) {
                    transferModal.addEventListener('show.bs.modal', function () {
                        populateIds('bulk-transfer-form', 'schedule_ids[]', 'scheduleId');
                        document.getElementById('bulk-transfer-summary').textContent = selected().length + ' group(s) selected.';
                    });
                }

                var deferModal = document.getElementById('bulk-defer-modal');
                if (deferModal) {
                    deferModal.addEventListener('show.bs.modal', function () {
                        populateIds('bulk-defer-form', 'entry_ids[]', 'entryId');
                    });
                }

                var deleteModal = document.getElementById('bulk-delete-modal');
                if (deleteModal) {
                    deleteModal.addEventListener('show.bs.modal', function () {
                        populateIds('bulk-delete-form', 'schedule_ids[]', 'scheduleId');
                    });
                }

                refreshBar();
            })();

            (function () {
                var tabButtons = document.querySelectorAll('[data-tab-btn]');
                var allPane = document.querySelector('[data-tab-pane="all"]');
                var deferredPane = document.querySelector('[data-tab-pane="deferred"]');
                var reDefensePane = document.querySelector('[data-tab-pane="redefense"]');
                var roomFilter = document.getElementById('room-filter');
                var activeTab = 'active';

                function applyFilters() {
                    var roomValue = roomFilter ? roomFilter.value : '';

                    allPane.querySelectorAll('[data-row-status]').forEach(function (row) {
                        var matchesRoom = !roomValue || row.dataset.roomRow === roomValue;
                        var matchesStatus = row.dataset.rowStatus === activeTab;
                        row.style.display = (matchesRoom && matchesStatus) ? '' : 'none';
                    });

                    // A date heading only earns its place while at least one
                    // of the rows under it survives the tab/room filter.
                    var pendingHeader = null;
                    var headerHasRows = false;

                    allPane.querySelectorAll('[data-date-header], [data-row-status]').forEach(function (row) {
                        if (row.hasAttribute('data-date-header')) {
                            if (pendingHeader) pendingHeader.style.display = headerHasRows ? '' : 'none';
                            pendingHeader = row;
                            headerHasRows = false;
                            return;
                        }

                        if (row.style.display !== 'none') headerHasRows = true;
                    });

                    if (pendingHeader) pendingHeader.style.display = headerHasRows ? '' : 'none';

                    deferredPane.querySelectorAll('[data-room-row]').forEach(function (row) {
                        row.style.display = (!roomValue || row.dataset.roomRow === roomValue) ? '' : 'none';
                    });
                }

                tabButtons.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        tabButtons.forEach(function (b) { b.classList.remove('active'); });
                        btn.classList.add('active');
                        activeTab = btn.dataset.tabBtn;

                        // The roster (Scheduled/Completed) and the two
                        // standalone panes are mutually exclusive — Re-Defense
                        // lists groups by outcome, not by queue row, so it has
                        // no place in the roster table.
                        allPane.classList.toggle('d-none', activeTab === 'deferred' || activeTab === 'redefense');
                        deferredPane.classList.toggle('d-none', activeTab !== 'deferred');
                        if (reDefensePane) reDefensePane.classList.toggle('d-none', activeTab !== 'redefense');

                        applyFilters();
                    });
                });

                if (roomFilter) {
                    roomFilter.addEventListener('change', applyFilters);
                }

                applyFilters();
            })();

            (function () {
                document.querySelectorAll('.group-registration-form').forEach(function (form) {
                    form.addEventListener('submit', function (e) {
                        var memberSlots = parseInt(form.dataset.memberSlots, 10) || 0;
                        function identity(nameFor) {
                            return ['last_name', 'first_name', 'middle_name'].map(function (f) {
                                var el = form.querySelector('[name="' + nameFor(f) + '"]');
                                return el ? el.value.trim().toLowerCase() : '';
                            }).join('|');
                        }

                        var leaderName = identity(function (f) { return 'leader_' + f; });
                        var seen = [leaderName];
                        var error = '';

                        for (var i = 0; i < memberSlots; i++) {
                            var fields = form.querySelectorAll('[name^="members[' + i + ']["]');
                            if (! fields.length) continue;

                            var filled = Array.prototype.filter.call(fields, function (el) { return el.value.trim() !== ''; });

                            if (! filled.length) continue;

                            if (filled.length !== fields.length) {
                                error = 'Complete every field for member ' + (i + 1) + ', or leave the whole row blank.';
                                break;
                            }

                            var normalized = identity(function (f) { return 'members[' + i + '][' + f + ']'; });
                            if (seen.indexOf(normalized) !== -1) {
                                error = normalized === leaderName
                                    ? 'A member cannot have the same name as the group leader.'
                                    : 'A member name is entered more than once.';
                                break;
                            }

                            seen.push(normalized);
                        }

                        if (error) {
                            e.preventDefault();
                            alert(error);
                        }
                    });
                });
            })();

            (function () {
                // A conflicting (red) row is itself the click target for its
                // own Panel Conflict modal — user-directed 2026-09-22. The
                // row still holds a checkbox, a 3-dot menu and its own
                // modal triggers, so a click that landed on any real control
                // is left to that control.
                document.querySelectorAll('[data-conflict-modal]').forEach(function (row) {
                    row.addEventListener('click', function (event) {
                        if (event.target.closest('input, select, textarea, label, button, a, .dropdown')) return;

                        var modalEl = document.getElementById(row.dataset.conflictModal);
                        if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    });
                });
            })();

            (function () {
                // Landing here via a Needs Attention link for a substitute-
                // less unavailability report (?focus=attempt-{id}) — once
                // the shared focus-flash script (partials/focus-flash-
                // script.blade.php, included after this page's own scripts,
                // so location.search still carries ?focus here) has scrolled
                // to and blinked the row, auto-open that report's Assign
                // Replacement modal so picking the one new panelist is the
                // very next thing to do.
                var params = new URLSearchParams(window.location.search);
                var focusParam = params.get('focus');
                if (! focusParam) return;

                window.addEventListener('load', function () {
                    var target = null;
                    focusParam.split(',').forEach(function (key) {
                        if (target || ! key) return;
                        var el = document.querySelector('[data-focus="' + CSS.escape(key) + '"][data-needs-replacement]');
                        if (el) target = el;
                    });
                    if (! target) return;

                    var modalEl = document.getElementById(target.dataset.needsReplacement);
                    if (! modalEl) return;

                    setTimeout(function () {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }, 700);
                });
            })();
        </script>
    @endpush
@endsection
