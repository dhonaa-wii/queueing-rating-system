{{--
    Expects: $attempt (with researchGroup.{students,proposedTitles}),
    $submissions (submitted/finalized only, with panelist.profile,
    submissionStatus, evaluationScores, studentScores,
    evaluationFormVersion.{evaluationForm,scaleLabels,presentationOutcomes,
    applicablePresentationModes,evaluationCriteria.childCriteria}),
    $letterhead, $leadPanelistIds, $pdfUrl (optional — the Export to PDF link;
    the PDF holds every sheet for the attempt, not just the one on screen).
    admin/reports/evaluation-sheets-pdf.blade.php is the Dompdf twin of this
    markup — change one, check the other.

    Each submission is wrapped in its own [data-sheet-page] — the modal in
    grades.blade.php shows one at a time and pages between them, since a
    group is normally evaluated by every panelist on its panel. Each page
    carries its own pager (position, bounds and all), so the modal's JS only
    has to toggle visibility — nothing to keep in sync client-side.

    Read-only rendering of the sheets a panel actually filed for one
    completed attempt, served as an HTML fragment into the Generated Grades
    table's View modal. Same "paper" shape as the room-session live panel
    and the Evaluation Library builder — the criteria table itself is the
    very same partial the tablet renders (with $readOnly = true), so the
    three can't drift apart.
--}}
@forelse ($submissions as $submission)
    @php
        $version = $submission->evaluationFormVersion;
        $sections = $version->evaluationCriteria->whereNull('parent_criterion_id')->sortBy('sort_order')->values();
        $isTitleProposal = $version->applicablePresentationModes->first()?->code === 'TITLE_PROPOSAL';

        $researchGroup = $attempt->researchGroup;
        $students = $researchGroup->students->sortByDesc('is_leader')->values();
        $proposedTitles = $isTitleProposal ? $researchGroup->proposedTitles->sortBy('sort_order')->values() : collect();

        $scoreLookup = $submission->evaluationScores->keyBy(fn ($s) => $s->evaluation_criterion_id . ':' . ($s->proposed_title_id ?? '0'));
        $studentScoreLookup = $submission->studentScores->keyBy('student_id');
        $panelName = trim(($submission->panelist->profile->first_name ?? '') . ' ' . ($submission->panelist->profile->last_name ?? ''))
            ?: ($submission->panelist->username ?? '—');
        $liveSeats = $attempt->attemptPanelAssignments
            ->reject(fn ($a) => in_array($a->assignmentStatus?->code, ['REPLACED', 'WITHDRAWN'], true))
            ->where('assignmentKind.code', 'ASSIGNED_PANELIST');
        $seatMemberNumbers = \App\Models\AttemptPanelAssignment::memberNumbers($liveSeats);
        $submitterSeat = $liveSeats->firstWhere('panelist_user_id', $submission->panelist_user_id);
        $panelIsLead = $leadPanelistIds->contains($submission->panelist_user_id);
        $panelRoleLabel = $panelIsLead
            ? 'Chair'
            : ($submitterSeat && isset($seatMemberNumbers[$submitterSeat->id]) ? 'Member ' . $seatMemberNumbers[$submitterSeat->id] : 'Member');
    @endphp

    <div data-sheet-page {{ $loop->first ? '' : 'hidden' }}>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="sheet-pager">
            <button type="button" class="sheet-pager-btn" data-sheet-prev @disabled($loop->first) aria-label="Previous evaluation sheet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m11 17-5-5 5-5"></path><path d="m18 17-5-5 5-5"></path></svg>
            </button>
            <span class="sheet-pager-count">{{ $loop->iteration }} / {{ $submissions->count() }}</span>
            <button type="button" class="sheet-pager-btn" data-sheet-next @disabled($loop->last) aria-label="Next evaluation sheet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m13 17 5-5-5-5"></path><path d="m6 17 5-5-5-5"></path></svg>
            </button>
        </div>
        @isset($pdfUrl)
            <a href="{{ $pdfUrl }}" class="btn btn-sm btn-outline-brand"><x-icon name="file-text" /> Export to PDF</a>
        @endisset
    </div>

    <div class="eval-paper">
        <span class="badge badge-success-tint eval-paper-submitted">
            {{ $submission->submissionStatus?->name ?? 'Submitted' }}{{ $submission->submitted_at ? ' · ' . $submission->submitted_at->format('M j, Y g:i A') : '' }}
        </span>

        @if ($version->show_letterhead && $letterhead && $letterhead->hasContent())
        @include('partials.evaluation-letterhead', ['letterhead' => $letterhead])
    @endif

        <div class="eval-title-wrap">
            <h3 class="eval-title-static">{{ $version->evaluationForm->name }}</h3>
        </div>

        @unless ($isTitleProposal)
            <table class="eval-paper-info-table">
                <tr>
                    <td class="eval-info-label">Capstone Title:</td>
                    <td class="eval-info-value">{{ $researchGroup->current_project_title ?? '—' }}</td>
                </tr>
            </table>
        @endunless

        <table class="eval-paper-researchers-table">
            <thead>
                <tr>
                    <th>Researchers</th>
                    @unless ($isTitleProposal)
                        <th>Individual<br>(Raw Score)</th>
                    @endunless
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    @php $studentScore = $studentScoreLookup->get($student->id)?->score; @endphp
                    <tr>
                        <td>{{ $loop->iteration }}. {{ $student->full_name }}{{ $student->is_leader ? ' (Leader)' : '' }}</td>
                        @unless ($isTitleProposal)
                            <td class="eval-info-value">
                                {{ $studentScore !== null ? rtrim(rtrim(number_format($studentScore, 2), '0'), '.') : '—' }}
                            </td>
                        @endunless
                        @if ($loop->first)
                            <td class="eval-remarks-cell" rowspan="{{ max($students->count(), 1) }}">
                                @forelse ($version->presentationOutcomes as $outcome)
                                    <div class="eval-remark-option">
                                        <span class="eval-checkbox-glyph">{!! $submission->presentation_outcome_id == $outcome->id ? '&#9745;' : '&#9633;' !!}</span> {{ $outcome->name }}
                                    </div>
                                @empty
                                    <span class="text-brand-muted small">—</span>
                                @endforelse
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $isTitleProposal ? 2 : 3 }}" class="text-brand-muted">No researchers registered.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($isTitleProposal)
            @foreach ($proposedTitles as $proposedTitle)
                <table class="eval-paper-info-table eval-title-block-header">
                    <tr>
                        <td class="eval-info-label">Proposed Title:</td>
                        <td class="eval-info-value">{{ $proposedTitle->title_text }}</td>
                        <td class="eval-approved-cell">
                            <span class="eval-checkbox-glyph">{!! $proposedTitle->is_approved ? '&#9745;' : '&#9633;' !!}</span> Approved
                        </td>
                    </tr>
                </table>

                @include('room-session.partials.evaluation-criteria-table', [
                    'version' => $version,
                    'sections' => $sections,
                    'scoreLookup' => $scoreLookup,
                    'proposedTitleId' => $proposedTitle->id,
                    'readOnly' => true,
                ])
            @endforeach
        @else
            @include('room-session.partials.evaluation-criteria-table', [
                'version' => $version,
                'sections' => $sections,
                'scoreLookup' => $scoreLookup,
                'proposedTitleId' => null,
                'readOnly' => true,
            ])

            <div class="eval-paper-footer-box">
                <strong>Total Score:</strong>
                {{ $submission->weighted_total_score !== null ? rtrim(rtrim(number_format($submission->weighted_total_score, 2), '0'), '.') : '___' }} / 100
            </div>
        @endif

        <div class="eval-paper-footer-box">
            <strong>Comment/s:</strong>
            <div class="eval-paper-comment-lines-readonly">{{ $submission->remarks ?: '—' }}</div>
        </div>

        @include('admin.evaluation-library.partials.paper-signoff', [
            'panelName' => $panelName,
            'panelRoleLabel' => $panelRoleLabel,
            'adviserName' => $researchGroup->technical_adviser_name ?: '',
        ])

        @include('partials.evaluation-sheet-mark')
    </div>
    </div>
@empty
    <p class="text-brand-muted mb-0">No submitted evaluations for this group.</p>
@endforelse
