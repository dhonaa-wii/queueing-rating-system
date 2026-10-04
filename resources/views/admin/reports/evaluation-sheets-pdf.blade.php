<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $attempt->researchGroup->group_reference }} — Evaluation Sheets</title>
    {{-- Rendered by Dompdf (CSS 2.1 only), same conventions as
         panelist-sheet-pdf.blade.php: tables and literal colours, a fixed
         footer repeating on every page. One sheet per submitted evaluation,
         each starting on a fresh page. Mirrors partials/evaluation-sheets
         (the HTML viewer) — keep the two in step. --}}
    <style>
        @page { margin: 10mm 12mm 16mm 12mm; }

        body {
            margin: 0;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            line-height: 1.25;
            color: #1F1D1A;
        }

        .footer { position: fixed; left: 0; right: 0; bottom: -9mm; text-align: center; font-size: 8px; color: #9A948E; }

        table { width: 100%; border-collapse: collapse; }

        .sheet-break { page-break-before: always; }

        .letterhead { margin-bottom: 8px; border-bottom: 2px solid #1F1D1A; }
        .letterhead td { padding: 0 0 8px; vertical-align: middle; text-align: center; border: 0; }
        .letterhead .logo-cell { width: 66px; }
        .letterhead img { width: 58px; height: 58px; }
        .letterhead .line { font-size: 9px; line-height: 1.35; }
        .letterhead .line-first { font-size: 10.5px; font-weight: bold; }

        .title { text-align: center; margin-bottom: 7px; }
        .title h1 { margin: 0 0 2px; font-size: 13px; }
        .title .submitted { font-size: 8px; color: #6B6560; }

        table.info, table.people, table.criteria { margin-bottom: 7px; }
        table.info td, table.people th, table.people td, table.criteria th, table.criteria td {
            border: 1px solid #CFC7BD; padding: 2.5px 6px; vertical-align: top;
        }
        table.info .label { width: 95px; font-weight: bold; }
        table.info .approved { width: 70px; text-align: center; white-space: nowrap; }
        table.people th { text-align: left; background: #F3EEE8; }
        table.people .remarks { width: 165px; }
        table.people .center, table.people th.center { text-align: center; width: 70px; }
        .remark { margin-bottom: 2px; }

        table.criteria th { text-align: left; background: #F3EEE8; }
        table.criteria tr { page-break-inside: avoid; }
        table.criteria .legend td { background: #F7F4F0; font-size: 8px; color: #6B6560; }
        table.criteria .section td { background: #FBE9D2; font-weight: bold; }
        table.criteria .item td.name { padding-left: 14px; }
        table.criteria .rating { width: 150px; white-space: nowrap; }

        .bub { display: inline-block; width: 13px; height: 13px; line-height: 10px; vertical-align: middle; margin-right: 3px; text-align: center; border: 1px solid #CFC7BD; border-radius: 7px; font-size: 7.5px; }
        .bub.on { background: #C1712E; border-color: #C1712E; color: #FFFFFF; font-weight: bold; }

        .box { border: 1px solid #CFC7BD; padding: 5px 7px; margin-bottom: 8px; page-break-inside: avoid; }
        .comment { margin-top: 3px; min-height: 22px; white-space: pre-wrap; }

        table.signoff { margin-top: 12px; page-break-inside: avoid; }
        table.signoff td { width: 50%; padding: 0 18px; text-align: center; vertical-align: bottom; border: 0; }
        table.signoff .name { border-bottom: 1px solid #1F1D1A; padding: 16px 2px 2px; min-height: 12px; }
        table.signoff .role { margin-top: 2px; font-size: 8px; color: #6B6560; }

        .empty { text-align: center; color: #6B6560; padding: 30px 0; }
    </style>
</head>
<body>
    <div class="footer">{{ $footerText }}</div>

    @forelse ($submissions as $submission)
        @php
            $version = $submission->evaluationFormVersion;
            $sections = $version->evaluationCriteria->whereNull('parent_criterion_id')->sortBy('sort_order')->values();
            $isTitleProposal = $version->applicablePresentationModes->first()?->code === 'TITLE_PROPOSAL';

            $researchGroup = $attempt->researchGroup;
            $students = $researchGroup->students->sortByDesc('is_leader')->values();
            $proposedTitles = $isTitleProposal ? $researchGroup->proposedTitles->sortBy('sort_order')->values() : collect();
            $adviserName = trim((string) $researchGroup->technical_adviser_name);

            $scoreLookup = $submission->evaluationScores->keyBy(fn ($s) => $s->evaluation_criterion_id . ':' . ($s->proposed_title_id ?? '0'));
            $studentScoreLookup = $submission->studentScores->keyBy('student_id');
            $panelName = trim(($submission->panelist->profile->first_name ?? '') . ' ' . ($submission->panelist->profile->last_name ?? ''))
                ?: ($submission->panelist->username ?? '—');
            $liveSeats = $attempt->attemptPanelAssignments
                ->reject(fn ($a) => in_array($a->assignmentStatus?->code, ['REPLACED', 'WITHDRAWN'], true))
                ->where('assignmentKind.code', 'ASSIGNED_PANELIST');
            $seatMemberNumbers = \App\Models\AttemptPanelAssignment::memberNumbers($liveSeats);
            $submitterSeat = $liveSeats->firstWhere('panelist_user_id', $submission->panelist_user_id);
            $panelRoleLabel = $leadPanelistIds->contains($submission->panelist_user_id)
                ? 'Chair'
                : ($submitterSeat && isset($seatMemberNumbers[$submitterSeat->id]) ? 'Member ' . $seatMemberNumbers[$submitterSeat->id] : 'Member');
            $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
        @endphp

        <div class="{{ $loop->first ? '' : 'sheet-break' }}">
            @if ($version->show_letterhead && $letterhead)
                <table class="letterhead">
                    <tr>
                        <td class="logo-cell">@if ($logo)<img src="{{ $logo }}" alt="">@endif</td>
                        <td>
                            @foreach (array_values(array_filter([$letterhead->line_1, $letterhead->line_2, $letterhead->line_3, $letterhead->line_4])) as $line)
                                <div class="line {{ $loop->first ? 'line-first' : '' }}">{{ $line }}</div>
                            @endforeach
                        </td>
                        <td class="logo-cell">@if ($secondaryLogo)<img src="{{ $secondaryLogo }}" alt="">@endif</td>
                    </tr>
                </table>
            @endif

            <div class="title">
                <h1>{{ $version->evaluationForm->name }}</h1>
                @if ($submission->submitted_at)
                    <div class="submitted">{{ $submission->submissionStatus?->name ?? 'Submitted' }} &middot; {{ $submission->submitted_at->format('M j, Y g:i A') }}</div>
                @endif
            </div>

            @unless ($isTitleProposal)
                <table class="info">
                    <tr>
                        <td class="label">Capstone Title:</td>
                        <td>{{ $researchGroup->current_project_title ?? '—' }}</td>
                    </tr>
                </table>
            @endunless

            <table class="people">
                <thead>
                    <tr>
                        <th>Researchers</th>
                        @unless ($isTitleProposal)
                            <th class="center">Individual (Raw Score)</th>
                        @endunless
                        <th class="remarks">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        @php $studentScore = $studentScoreLookup->get($student->id)?->score; @endphp
                        <tr>
                            <td>{{ $loop->iteration }}. {{ $student->full_name }}{{ $student->is_leader ? ' (Leader)' : '' }}</td>
                            @unless ($isTitleProposal)
                                <td class="center">{{ $studentScore !== null ? $num($studentScore) : '—' }}</td>
                            @endunless
                            @if ($loop->first)
                                <td class="remarks" rowspan="{{ max($students->count(), 1) + ($adviserName !== '' ? 1 : 0) }}">
                                    @forelse ($version->presentationOutcomes as $outcome)
                                        <div class="remark">{!! $submission->presentation_outcome_id == $outcome->id ? '[x]' : '[&nbsp;&nbsp;]' !!} {{ $outcome->name }}</div>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isTitleProposal ? 2 : 3 }}">No researchers registered.</td></tr>
                    @endforelse
                    {{-- The adviser listed like a member (user-directed 2026-10-04); not scored. --}}
                    @if ($adviserName !== '')
                        <tr>
                            <td>{{ $adviserName }} (Adviser)</td>
                            @unless ($isTitleProposal)
                                <td class="center"></td>
                            @endunless
                            @if ($students->isEmpty())
                                <td></td>
                            @endif
                        </tr>
                    @endif
                </tbody>
            </table>

            @if ($isTitleProposal)
                @foreach ($proposedTitles as $proposedTitle)
                    <table class="info">
                        <tr>
                            <td class="label">Proposed Title:</td>
                            <td>{{ $proposedTitle->title_text }}</td>
                            <td class="approved">{!! $proposedTitle->is_approved ? '[x]' : '[&nbsp;&nbsp;]' !!} Approved</td>
                        </tr>
                    </table>
                    @include('admin.reports.partials.evaluation-criteria-pdf', ['proposedTitleId' => $proposedTitle->id])
                @endforeach
            @else
                @include('admin.reports.partials.evaluation-criteria-pdf', ['proposedTitleId' => null])

                <div class="box">
                    <strong>Total Score:</strong>
                    {{ $submission->weighted_total_score !== null ? $num($submission->weighted_total_score) : '___' }} / 100
                </div>
            @endif

            <div class="box">
                <strong>Comment/s:</strong>
                <div class="comment">{{ $submission->remarks ?: '—' }}</div>
            </div>

            <table class="signoff">
                <tr>
                    <td>
                        <div class="name">{{ $panelName }}</div>
                        <div class="role">{{ $panelRoleLabel }}</div>
                    </td>
                    <td></td>
                </tr>
            </table>
        </div>
    @empty
        <div class="empty">No submitted evaluations for this group.</div>
    @endforelse
</body>
</html>
