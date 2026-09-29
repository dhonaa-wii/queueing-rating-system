{{-- Admin "View All" slide-in overlay for one room — same list/columns as
     the room-session tablet's own View All (queue-panel.blade.php: #, Date,
     Expected Time, Proponents, Title, Adviser, Panelist), reusing the same
     .view-all-overlay/.view-all-table CSS from theme-head.blade.php, just
     scoped per room (this page can show several room tabs at once, unlike
     the single-room tablet) via data-room-view-all-* attributes instead of
     the tablet's single global data-view-all-*. Read-only, like the
     tablet's own version — no admin-override actions in this list. Left/
     top/width/height below are only the pre-JS fallback (show.blade.php's
     script sets the real position/size the moment it opens). --}}
<div class="view-all-overlay" data-room-view-all-panel="{{ $room->id }}" style="left: auto; right: 0; width: min(480px, 92vw);">
    <div class="view-all-overlay-backdrop" data-room-view-all-close="{{ $room->id }}"></div>
    <div class="view-all-overlay-panel">
        <div class="d-flex justify-content-between align-items-center mb-3" style="position: sticky; top: -1.25rem; z-index: 2; background-color: var(--brand-surface); margin: -1.25rem -1.25rem 0.75rem; padding: 1.25rem 1.25rem 0.75rem;">
            <h3 class="h6 mb-0">View All &mdash; {{ $room->room_name }}</h3>
            <button type="button" class="btn-close" data-room-view-all-close="{{ $room->id }}" aria-label="Close"></button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle view-all-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th class="view-all-time-col">Expected Time</th>
                        <th>Proponents</th>
                        <th>Title</th>
                        <th>Adviser</th>
                        <th>Panelist</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (\App\Support\ScheduleBreakRows::interleave($fullQueue, fn ($s) => $room, fn ($s) => $s->expected_start_at ?? $s->planned_start_at) as $schedule)
                        @if (\App\Support\ScheduleBreakRows::isBreak($schedule))
                            @include('partials.schedule.break-row', ['break' => $schedule, 'span' => 7])
                            @continue
                        @endif
                        @php
                            $rowGroup = $schedule->presentationAttempt->researchGroup;
                            $rowStudents = $rowGroup->students->sortByDesc('is_leader');
                            $rowAssigned = $schedule->presentationAttempt->attemptPanelAssignments->where('assignmentKind.code', 'ASSIGNED_PANELIST');
                            $rowBackup = $schedule->presentationAttempt->attemptPanelAssignments->where('assignmentKind.code', 'BACKUP_PANELIST');
                            $rowTitle = $rowGroup->current_project_title ?: $rowGroup->proposedTitles->pluck('title_text')->filter()->implode('; ');
                        @endphp
                        <tr>
                            <td>{{ $schedule->queueEntry->queue_number }}</td>
                            <td class="text-nowrap">{{ $room->presentationDate?->presentation_date?->format('M d, Y') ?? '—' }}</td>
                            <td class="view-all-time-col">
                                @if ($schedule->expected_start_at && $schedule->expected_end_at)
                                    <div>{{ $schedule->expected_start_at->format('g:i A') }}</div>
                                    <div>{{ $schedule->expected_end_at->format('g:i A') }}</div>
                                @else
                                    —
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
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-brand-muted">No groups queued in this room yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
