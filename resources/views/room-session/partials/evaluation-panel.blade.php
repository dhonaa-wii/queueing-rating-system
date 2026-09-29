{{--
    Expects: $evaluationSubmission (with submissionStatus, evaluationScores,
    evaluationFormVersion.{scaleLabels,presentationOutcomes,
    applicablePresentationModes,evaluationCriteria}), $attempt (with
    researchGroup.{students,proposedTitles}), $letterhead, $connection.

    Live counterpart to admin/evaluation-library/partials/paper.blade.php —
    same "paper" shape and CSS classes (shared via paper-styles.blade.php)
    but filled with the real group/attempt in front of this room right now,
    and with real clickable scoring instead of static illustrative content.
    Only rendered (see RoomSessionController::buildHomeData()/status()) once
    a submission actually exists, i.e. once Start has been pressed for the
    connected, panel-eligible panelist viewing this terminal.
--}}
@php
    $version = $evaluationSubmission->evaluationFormVersion;
    $sections = $version->evaluationCriteria->whereNull('parent_criterion_id')->sortBy('sort_order')->values();
    $selectedMode = $version->applicablePresentationModes->first();
    $isTitleProposal = $selectedMode?->code === 'TITLE_PROPOSAL';
    $readOnly = in_array($evaluationSubmission->submissionStatus?->code, ['SUBMITTED', 'FINALIZED', 'INVALIDATED'], true);

    $researchGroup = $attempt->researchGroup;
    $students = $researchGroup->students->sortByDesc('is_leader')->values();
    $proposedTitles = $isTitleProposal ? $researchGroup->proposedTitles->sortBy('sort_order')->values() : collect();

    $scoreLookup = $evaluationSubmission->evaluationScores->keyBy(fn ($s) => $s->evaluation_criterion_id . ':' . ($s->proposed_title_id ?? '0'));
    $studentScoreLookup = $evaluationSubmission->studentScores->keyBy('student_id');
    $panelName = trim(($connection->panelist->profile->first_name ?? '') . ' ' . ($connection->panelist->profile->last_name ?? '')) ?: $connection->panelist->username;
    $panelIsLead = $attempt->attemptPanelAssignments
        ->where('panelist_user_id', $connection->panelist_user_id)
        ->reject(fn ($a) => in_array($a->assignmentStatus?->code, ['REPLACED', 'WITHDRAWN'], true))
        ->contains(fn ($a) => (bool) $a->is_lead);
    $panelLiveSeats = $attempt->attemptPanelAssignments
        ->reject(fn ($a) => in_array($a->assignmentStatus?->code, ['REPLACED', 'WITHDRAWN'], true))
        ->where('assignmentKind.code', 'ASSIGNED_PANELIST');
    $panelMemberNumbers = \App\Models\AttemptPanelAssignment::memberNumbers($panelLiveSeats);
    $panelOwnSeat = $panelLiveSeats->firstWhere('panelist_user_id', $connection->panelist_user_id);
    $panelRoleLabel = $panelIsLead
        ? 'Chair'
        : ($panelOwnSeat && isset($panelMemberNumbers[$panelOwnSeat->id]) ? 'Member ' . $panelMemberNumbers[$panelOwnSeat->id] : 'Member');

    $leafCriteriaCount = $sections->sum(fn ($section) => $section->childCriteria->count());
    $titleMultiplier = $isTitleProposal ? max(1, $proposedTitles->count()) : 1;
    $missingCount = max(0, ($leafCriteriaCount * $titleMultiplier) - $evaluationSubmission->evaluationScores->count());
    // Mirrors EvaluationSubmissionService::submit()'s own outcome check so
    // the button disables before the panelist can hit the server block.
    $outcomeMissing = $version->presentationOutcomes->isNotEmpty() && $evaluationSubmission->presentation_outcome_id === null;
@endphp

<div class="card-brand p-2">
    <input type="hidden" data-csrf-token value="{{ csrf_token() }}">
    <input type="hidden" data-evaluation-submission-id value="{{ $evaluationSubmission->id }}">

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="mb-0" style="font-size: 0.85rem;">Evaluation</h2>
        <span class="badge {{ $readOnly ? 'badge-success-tint' : 'badge-muted-tint' }}" style="font-size: 0.66rem;">
            {{ $evaluationSubmission->submissionStatus?->name ?? 'Draft' }}
        </span>
    </div>

    <div class="eval-paper eval-paper-live">
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
                                @if ($readOnly)
                                    {{ $studentScore !== null ? rtrim(rtrim(number_format($studentScore, 2), '0'), '.') : '—' }}
                                @else
                                    <input type="number" class="eval-student-score-input" data-student-score-input
                                           data-student-id="{{ $student->id }}" min="0" max="100" step="0.01"
                                           value="{{ $studentScore !== null ? rtrim(rtrim(number_format($studentScore, 2), '0'), '.') : '' }}">
                                @endif
                            </td>
                        @endunless
                        @if ($loop->first)
                            <td class="eval-remarks-cell" rowspan="{{ max($students->count(), 1) }}">
                                @forelse ($version->presentationOutcomes as $outcome)
                                    @if ($readOnly)
                                        <div class="eval-remark-option">
                                            <span class="eval-checkbox-glyph">{!! $evaluationSubmission->presentation_outcome_id == $outcome->id ? '&#9745;' : '&#9633;' !!}</span> {{ $outcome->name }}
                                        </div>
                                    @else
                                        <button type="button" class="eval-outcome-btn {{ $evaluationSubmission->presentation_outcome_id == $outcome->id ? 'is-selected' : '' }}"
                                                data-outcome-btn data-outcome-id="{{ $outcome->id }}">
                                            <span class="eval-checkbox-glyph">{!! $evaluationSubmission->presentation_outcome_id == $outcome->id ? '&#9745;' : '&#9633;' !!}</span> {{ $outcome->name }}
                                        </button>
                                    @endif
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
            @forelse ($proposedTitles as $proposedTitle)
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
                    'readOnly' => $readOnly,
                ])
            @empty
                <div class="text-brand-muted small mb-3">No proposed titles registered for this group.</div>
            @endforelse

            <div class="eval-paper-footer-box">
                <strong>Approved Title:</strong> <span class="text-brand-muted">___</span>
            </div>
        @else
            @include('room-session.partials.evaluation-criteria-table', [
                'version' => $version,
                'sections' => $sections,
                'scoreLookup' => $scoreLookup,
                'proposedTitleId' => null,
                'readOnly' => $readOnly,
            ])

            <div class="eval-paper-footer-box">
                <strong>Total Score:</strong>
                <span data-eval-total-score>{{ $evaluationSubmission->weighted_total_score !== null ? rtrim(rtrim(number_format($evaluationSubmission->weighted_total_score, 2), '0'), '.') : '___' }}</span> / 100
            </div>
        @endif

        <div class="eval-paper-footer-box">
            <strong>Comment/s:</strong>
            @if ($readOnly)
                <div class="eval-paper-comment-lines-readonly">{{ $evaluationSubmission->remarks ?: '—' }}</div>
            @else
                <textarea class="eval-remarks-input" data-remarks-input rows="2"
                          placeholder="">{{ $evaluationSubmission->remarks }}</textarea>
            @endif
        </div>

        @include('admin.evaluation-library.partials.paper-signoff', [
            'panelName' => $panelName,
            'panelRoleLabel' => $panelRoleLabel,
            'adviserName' => $researchGroup->technical_adviser_name ?: '',
        ])

        @include('partials.evaluation-sheet-mark')
    </div>

    @if ($readOnly)
        <div class="mt-2 text-center">
            <span class="badge badge-success-tint">Submitted{{ $evaluationSubmission->submitted_at ? ' ' . $evaluationSubmission->submitted_at->format('g:i A') : '' }}</span>
        </div>
    @else
        <form method="POST" action="{{ route('room-session.evaluation.submit') }}" class="mt-2" id="evaluation-submit-form">
            @csrf
        </form>
        <button type="button" class="btn btn-success-brand w-100 mt-2" @disabled($missingCount > 0 || $outcomeMissing)
                data-bs-toggle="modal" data-bs-target="#submit-evaluation-modal"
                title="{{ $missingCount > 0 ? $missingCount . ' rating(s) still missing' : ($outcomeMissing ? 'Select a remark before submitting' : '') }}">
            Submit Evaluation
        </button>

        {{-- Bootstrap modal (2026-08-21, was a native confirm() dialog) —
        same visual pattern as home.blade.php's own settings modal and the
        Schedules tab's date-deletion modal. Lives inside #evaluation-panel,
        which gets replaced wholesale on every 5s poll — see home.blade.php's
        evaluationModalOpen() guard, which skips that swap while this is
        open so a panelist mid-confirmation doesn't get it yanked away. --}}
        <div class="modal fade" id="submit-evaluation-modal" tabindex="-1" aria-labelledby="submit-evaluation-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="submit-evaluation-modal-label">Submit Evaluation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Submit this evaluation? You won't be able to change it afterward.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" form="evaluation-submit-form" class="btn btn-success-brand"><x-icon name="send" /> Submit Evaluation</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
