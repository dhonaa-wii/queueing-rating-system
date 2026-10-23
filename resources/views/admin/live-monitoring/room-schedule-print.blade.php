<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $room->room_name }} Schedule &middot; {{ $date?->presentation_date?->format('M j, Y') }}</title>
    {{-- Standalone print page for one room's day (Event Control > View All >
         Print). The letterhead + column headers live in <thead> and the
         live-estimate note in <tfoot>, which browsers repeat on every printed
         page. Always light; loaded into a hidden iframe and printed there. --}}
    <style>
        :root {
            --ink: #1F1D1A;
            --muted: #6B6560;
            --rule: #D9D2CA;
            --head: #F3EEE8;
            --accent: #C1712E;
            --danger: #B3261E;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 16px;
            background: #fff;
            color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        table.sheet { width: 100%; border-collapse: collapse; }

        .letterhead {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            text-align: center;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--ink);
        }

        .letterhead-logo { width: 54px; height: 54px; object-fit: contain; flex: 0 0 auto; }

        .letterhead-text { font-size: 11px; line-height: 1.3; }

        .letterhead-text div:first-child { font-weight: 700; font-size: 12px; }

        .sheet-heading {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            margin: 10px 0 8px;
        }

        .eyebrow {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--accent);
        }

        .sheet-heading h1 { margin: 2px 0 0; font-size: 16px; }

        .sheet-heading p { margin: 0; color: var(--muted); }

        .sheet-heading .right { text-align: right; }

        .sheet-heading .right strong { display: block; font-size: 13px; }

        .head-cell { padding: 0; border: 0; text-align: left; font-weight: normal; }

        .cols th {
            padding: 6px 8px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            text-align: left;
            color: var(--muted);
            background: var(--head);
            border: 1px solid var(--rule);
        }

        tbody td {
            padding: 6px 8px;
            border: 1px solid var(--rule);
            vertical-align: top;
        }

        tbody tr { break-inside: avoid; page-break-inside: avoid; }

        .col-num { width: 28px; text-align: center; color: var(--muted); }

        .col-time { width: 78px; white-space: nowrap; }

        .muted { color: var(--muted); }

        .break-row td { text-align: center; font-weight: 600; background: #FAF7F3; }

        .past-row td { background: #FDF1F0; }

        .past-divider td { border: 0; padding: 6px 0 2px; color: var(--danger); font-weight: 600; }

        .past-divider div { border-top: 2px solid var(--danger); padding-top: 3px; }

        .empty-row { text-align: center; color: var(--muted); padding: 20px 8px; }

        .note-cell { padding: 8px 0 0; border: 0; }

        .note {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding-top: 6px;
            border-top: 1px solid var(--rule);
            font-size: 10px;
            color: var(--muted);
        }

        .note a { color: var(--ink); font-weight: 600; text-decoration: none; }

        .system-footer {
            margin-top: 6px;
            text-align: center;
            font-size: 9px;
            color: #9A948E;
        }

        .system-footer-stamp { text-align: left; margin-bottom: 2px; }

        /* A zero page margin is what stops the browser printing its own
           header/footer (page title, date, URL). The page's real margins come
           from the side padding and the spacer rows in <thead>/<tfoot>, which
           repeat on every page. */
        .page-spacer td, .page-spacer th { height: 9mm; padding: 0; border: 0; }
        .page-spacer-foot td { height: 4mm; }

        @page { size: A4 landscape; margin: 0; }

        @media screen {
            .page-spacer { display: none; }
        }

        @media print {
            body { padding: 0 10mm; }
        }
    </style>
</head>
<body>
    @php
        $siteUrl = 'queueing.csuaparri.net';
    @endphp

    <table class="sheet">
        <thead>
            <tr class="page-spacer"><th colspan="7"></th></tr>
            <tr>
                <th colspan="7" class="head-cell">
                    @if ($letterhead && $letterhead->hasContent())
                        <div class="letterhead">
                            @if ($logo = $letterhead->logoDataUri('logo_path'))
                                <img src="{{ $logo }}" alt="" class="letterhead-logo">
                            @endif
                            <div class="letterhead-text">
                                @foreach ([$letterhead->line_1, $letterhead->line_2, $letterhead->line_3, $letterhead->line_4] as $line)
                                    @if ($line)
                                        <div>{{ $line }}</div>
                                    @endif
                                @endforeach
                            </div>
                            @if ($secondaryLogo = $letterhead->logoDataUri('secondary_logo_path'))
                                <img src="{{ $secondaryLogo }}" alt="" class="letterhead-logo">
                            @endif
                        </div>
                    @endif
                    <div class="sheet-heading">
                        <div>
                            <div class="eyebrow">Presentation Schedule</div>
                            <h1>{{ $category->name }}</h1>
                            <p>{{ $category->academicYear->name ?? '—' }} &middot; {{ $category->semester->name ?? '—' }} &middot; {{ $category->college->name ?? '—' }}</p>
                        </div>
                        <div class="right">
                            <strong>{{ $room->room_name }}</strong>
                            <span class="muted">{{ $date?->presentation_date?->format('l, M j, Y') ?? '—' }}</span>
                        </div>
                    </div>
                </th>
            </tr>
            <tr class="cols">
                <th class="col-num">#</th>
                <th class="col-time">Expected Time</th>
                <th>Group</th>
                <th>Proponents</th>
                <th>Title</th>
                <th>Adviser</th>
                <th>Panelist</th>
            </tr>
        </thead>
        <tfoot>
            <tr>
                <td colspan="7" class="note-cell">
                    <div class="note">
                        <span>Times on this schedule are live estimates and may change as the day runs. View the latest schedule at <a href="https://{{ $siteUrl }}">{{ $siteUrl }}</a>.</span>
                    </div>
                    <div class="system-footer"><div class="system-footer-stamp">Generated {{ now()->format('M j, Y g:i A') }}</div><div>{{ $footerText }}</div></div>
                </td>
            </tr>
            <tr class="page-spacer page-spacer-foot"><td colspan="7"></td></tr>
        </tfoot>
        <tbody>
            @forelse (\App\Support\ScheduleBreakRows::interleave($schedules, fn ($s) => $room, fn ($s) => $s->expected_start_at ?? $s->planned_start_at) as $schedule)
                @if (\App\Support\ScheduleBreakRows::isBreak($schedule))
                    <tr class="break-row">
                        <td colspan="7">{{ $schedule->name ?: 'Break' }} &nbsp; {{ $schedule->planned_start_at->format('g:i A') }} &ndash; {{ $schedule->planned_end_at->format('g:i A') }}</td>
                    </tr>
                    @continue
                @endif
                @php
                    $rowGroup = $schedule->presentationAttempt->researchGroup;
                    $rowStudents = $rowGroup->students->sortByDesc('is_leader');
                    $rowAssigned = $schedule->presentationAttempt->attemptPanelAssignments->where('assignmentKind.code', 'ASSIGNED_PANELIST')->sortByDesc('is_lead');
                    $rowBackup = $schedule->presentationAttempt->attemptPanelAssignments->where('assignmentKind.code', 'BACKUP_PANELIST');
                    $rowTitle = $rowGroup->current_project_title ?: $rowGroup->proposedTitles->pluck('title_text')->filter()->implode('; ');
                    $rowMemberNumbers = \App\Models\AttemptPanelAssignment::memberNumbers($rowAssigned);
                @endphp
                @if ($schedule->isPastSessionEnd() && ! ($pastSessionPrev ?? false))
                    <tr class="past-divider">
                        <td colspan="7"><div>{{ \App\Models\AttemptSchedule::pastSessionLine($schedule->session_end_at, false) }}</div></td>
                    </tr>
                @endif
                @php $pastSessionPrev = $schedule->isPastSessionEnd(); @endphp
                <tr class="{{ $schedule->isPastSessionEnd() ? 'past-row' : '' }}">
                    <td class="col-num">{{ $schedule->queueEntry->queue_number }}</td>
                    <td class="col-time">
                        @if ($schedule->expected_start_at && $schedule->expected_end_at)
                            {{ $schedule->expected_start_at->format('g:i A') }}<br>{{ $schedule->expected_end_at->format('g:i A') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="col-time">{{ $rowGroup->group_reference }}</td>
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
                        @forelse ($rowAssigned as $pa)
                            <div>{{ trim(($pa->panelist->profile->first_name ?? '') . ' ' . ($pa->panelist->profile->last_name ?? '')) }} ({{ $pa->is_lead ? 'Chair' : 'Member ' . ($rowMemberNumbers[$pa->id] ?? '') }})</div>
                        @empty
                            <span class="muted">—</span>
                        @endforelse
                        @foreach ($rowBackup as $pb)
                            <div class="muted">{{ trim(($pb->panelist->profile->first_name ?? '') . ' ' . ($pb->panelist->profile->last_name ?? '')) }} (Alternate Panel)</div>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-row">No groups queued in this room yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
