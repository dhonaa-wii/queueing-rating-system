{{--
    Expects: $attempt (loaded as ReportController::loadSheetAttempt() does),
    $submission (one of its submitted sheets), $updateUrl.

    Admin edit form for a sheet a panelist already submitted (user-directed
    2026-10-13). Swapped into the modal page the read-only sheet sat on
    (evaluation-sheet-modal's [data-sheet-edit] handler) and saved over
    fetch to ReportController::updateEvaluationSubmission(). Same paper
    shape as the read-only sheet; the criteria table is the tablet's own
    partial with $readOnly = false, so its rating bubbles are buttons the
    modal script toggles.
--}}
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
@endphp

<form data-sheet-edit-form data-url="{{ $updateUrl }}">
    @csrf
    @method('PUT')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <span class="badge badge-brand-tint">Editing · {{ $panelName }}</span>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-brand" data-sheet-edit-cancel>Cancel</button>
            <button type="submit" class="btn btn-sm btn-brand"><x-icon name="save" /> Save Changes</button>
        </div>
    </div>

    <div class="alert alert-danger py-2 small d-none" data-sheet-edit-error></div>

    <div class="eval-paper">
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
                                <input type="number" class="form-control form-control-sm sheet-edit-score" min="0" max="100" step="0.01"
                                       name="student_scores[{{ $student->id }}]"
                                       value="{{ $studentScore !== null ? rtrim(rtrim(number_format($studentScore, 2, '.', ''), '0'), '.') : '' }}">
                            </td>
                        @endunless
                        @if ($loop->first)
                            <td class="eval-remarks-cell" rowspan="{{ max($students->count(), 1) + ($adviserName !== '' ? 1 : 0) }}">
                                @forelse ($version->presentationOutcomes as $outcome)
                                    <label class="eval-remark-option d-flex align-items-center gap-1">
                                        <input type="radio" class="form-check-input mt-0" name="presentation_outcome_id" value="{{ $outcome->id }}"
                                               @checked($submission->presentation_outcome_id == $outcome->id)>
                                        {{ $outcome->name }}
                                    </label>
                                @empty
                                    <span class="text-brand-muted small">—</span>
                                @endforelse
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $isTitleProposal ? 2 : 3 }}" class="text-brand-muted">No researchers registered.</td></tr>
                @endforelse
                @include('admin.evaluation-library.partials.paper-adviser-row', [
                    'adviserName' => $adviserName,
                    'isTitleProposal' => $isTitleProposal,
                    'remarksSpanned' => $students->isNotEmpty(),
                ])
            </tbody>
        </table>

        @if ($isTitleProposal)
            @foreach ($proposedTitles as $proposedTitle)
                <table class="eval-paper-info-table eval-title-block-header">
                    <tr>
                        <td class="eval-info-label">Proposed Title:</td>
                        <td class="eval-info-value">{{ $proposedTitle->title_text }}</td>
                    </tr>
                </table>

                @include('room-session.partials.evaluation-criteria-table', [
                    'version' => $version,
                    'sections' => $sections,
                    'scoreLookup' => $scoreLookup,
                    'proposedTitleId' => $proposedTitle->id,
                    'readOnly' => false,
                ])
            @endforeach
        @else
            @include('room-session.partials.evaluation-criteria-table', [
                'version' => $version,
                'sections' => $sections,
                'scoreLookup' => $scoreLookup,
                'proposedTitleId' => null,
                'readOnly' => false,
            ])
        @endif

        <div class="eval-paper-footer-box">
            <strong>Comment/s:</strong>
            <textarea class="form-control form-control-sm mt-1" name="remarks" rows="3" maxlength="5000">{{ $submission->remarks }}</textarea>
        </div>
    </div>
</form>
