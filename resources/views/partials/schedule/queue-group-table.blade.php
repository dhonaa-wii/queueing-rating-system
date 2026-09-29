{{--
    Shared row-table for the room queue's Scheduled/Completed/Deferred
    sections on the Student schedule page — same dense bordered
    ".view-all-table" look as the room-session tablet's "View All" slide-in
    (room-session/partials/queue-panel.blade.php), just rendered inline and
    always visible instead of behind a button/overlay (user-directed
    2026-08-22), and split three ways instead of one flat list.

    Expects: $rows (collection of AttemptSchedule, each with
    presentationDateRoom.presentationDate, presentationAttempt.researchGroup
    .students/.proposedTitles, presentationAttempt.attemptPanelAssignments,
    queueEntry loaded), $variant ('scheduled'|'completed'|'deferred'),
    $isTitleProposal, $selectedGroup, $emptyText.

    The 'awaiting' variant is the group that still has to present but whose
    day can no longer run (AttemptSchedule::isAwaitingReschedule()): it has no
    date, no time and no queue number to show, so those three columns say so
    instead of repeating a slot on a day that is over.
--}}
@php
    $isAwaitingVariant = $variant === 'awaiting';
    $timeHeading = match ($variant) {
        'completed' => 'Completed At',
        'deferred' => 'Deferred At',
        'awaiting' => 'Time',
        default => 'Expected Time',
    };
@endphp
<div class="table-responsive">
    <table class="table table-sm mb-0 align-middle view-all-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th class="view-all-time-col">{{ $timeHeading }}</th>
                <th>Proponents</th>
                <th>Title</th>
                <th>Adviser</th>
                <th>Panelist</th>
                @if ($variant === 'deferred')
                    <th>Reason</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php
                // Breaks only belong in the list of groups still to present:
                // completed and deferred rows are history, and an awaiting row
                // has no slot for a break to sit next to.
                $listItems = $variant === 'scheduled'
                    ? \App\Support\ScheduleBreakRows::interleave(
                        $rows,
                        fn ($s) => $s->presentationDateRoom,
                        fn ($s) => $s->isAwaitingReschedule($s->presentationAttempt)
                            ? null
                            : ($s->expected_start_at ?? $s->planned_start_at),
                    )
                    : $rows;
            @endphp
            @forelse ($listItems as $schedule)
                @if (\App\Support\ScheduleBreakRows::isBreak($schedule))
                    @include('partials.schedule.break-row', ['break' => $schedule, 'span' => 7])
                    @continue
                @endif
                @php
                    $rowAttempt = $schedule->presentationAttempt;
                    $rowGroup = $rowAttempt->researchGroup;
                    $rowStudents = $rowGroup->students->sortByDesc('is_leader');
                    $rowAssigned = $rowAttempt->attemptPanelAssignments->where('assignmentKind.code', 'ASSIGNED_PANELIST');
                    $rowBackup = $rowAttempt->attemptPanelAssignments->where('assignmentKind.code', 'BACKUP_PANELIST');
                    $rowTitle = $isTitleProposal
                        ? $rowGroup->proposedTitles->pluck('title_text')->filter()->implode('; ')
                        : $rowGroup->current_project_title;
                    $isSelectedRow = $selectedGroup && $rowGroup->id === $selectedGroup->id;
                    $latestDeferAdjustment = $variant === 'deferred' ? $schedule->queueEntry?->queueAdjustments?->first() : null;
                    // The row's date column follows the same planned-vs-actual
                    // rule as its time column below: a row that hasn't
                    // happened yet shows the planned room/date, but a
                    // completed or deferred row shows the real calendar day
                    // it actually completed/was deferred on, which can differ
                    // from the day it was originally planned for
                    // (user-directed 2026-08-22).
                    // A still-to-present row follows the running-day rule instead:
                    // its expected time while the day is underway, its plan
                    // otherwise (CategoryScheduleViewService resolved which).
                    $rowActualAt = match ($variant) {
                        'completed' => $rowAttempt->completed_at,
                        'deferred' => $latestDeferAdjustment?->adjusted_at,
                        default => $schedule->expected_start_at
                            ?? $schedule->adjusted_expected_at
                            ?? $schedule->planned_start_at,
                    };
                    $rowDateAt = $rowActualAt ?? $schedule->presentationDateRoom->presentationDate->presentation_date;
                    // Belt and braces: the awaiting rows are handed to this
                    // partial under their own variant, but a row that is
                    // awaiting a new date must never print a date whichever
                    // list it turns up in.
                    $isAwaitingRow = $isAwaitingVariant
                        || ($variant !== 'completed' && $variant !== 'deferred' && $schedule->isAwaitingReschedule($rowAttempt));
                @endphp
                <tr id="queue-row-{{ $schedule->id }}" class="{{ $isSelectedRow ? 'table-active' : '' }}">
                    <td>{{ $isAwaitingRow ? '—' : $schedule->queueEntry->queue_number }}</td>
                    <td class="text-nowrap">
                        @if ($isAwaitingRow)
                            <span class="text-brand-muted">{{ \App\Models\AttemptSchedule::AWAITING_SHORT }}</span>
                        @else
                            {{ $rowDateAt->format('M j, Y') }}
                        @endif
                    </td>
                    <td class="view-all-time-col">
                        @if ($isAwaitingRow)
                            <span class="text-brand-muted">—</span>
                        @elseif ($variant === 'completed')
                            {{ $rowAttempt->completed_at ? $rowAttempt->completed_at->format('M j, g:i A') : '—' }}
                        @elseif ($variant === 'deferred')
                            {{ $latestDeferAdjustment?->adjusted_at ? $latestDeferAdjustment->adjusted_at->format('M j, g:i A') : '—' }}
                        @else
                            {{ $rowActualAt?->format('g:i A') ?? 'TBA' }}
                        @endif
                    </td>
                    <td>
                        @forelse ($rowStudents as $rowStudent)
                            <div>{{ $rowStudent->full_name }}{{ $rowStudent->section_name ? ' (' . $rowStudent->section_name . ')' : '' }}</div>
                        @empty
                            —
                        @endforelse
                    </td>
                    <td>{{ $rowTitle ?: '—' }}</td>
                    <td>{{ $rowGroup->technical_adviser_name ?? '—' }}</td>
                    <td>
                        @php $rowMemberNumbers = \App\Models\AttemptPanelAssignment::memberNumbers($rowAssigned); @endphp
                        @forelse ($rowAssigned as $pa)
                            <div>{{ trim(($pa->panelist->profile->first_name ?? '') . ' ' . ($pa->panelist->profile->last_name ?? '')) }} ({{ $pa->is_lead ? 'Chair' : 'Member ' . ($rowMemberNumbers[$pa->id] ?? '') }})</div>
                        @empty
                            <span class="text-brand-muted">—</span>
                        @endforelse
                        @foreach ($rowBackup as $pb)
                            <div class="text-brand-muted">{{ trim(($pb->panelist->profile->first_name ?? '') . ' ' . ($pb->panelist->profile->last_name ?? '')) }} (Alternate Panel)</div>
                        @endforeach
                    </td>
                    @if ($variant === 'deferred')
                        <td>
                            {{ $latestDeferAdjustment?->reason?->name ?? 'N/A' }}
                            @if ($latestDeferAdjustment?->remarks)
                                <div class="text-brand-muted">{{ $latestDeferAdjustment->remarks }}</div>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $variant === 'deferred' ? 8 : 7 }}" class="text-brand-muted">{{ $emptyText }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
