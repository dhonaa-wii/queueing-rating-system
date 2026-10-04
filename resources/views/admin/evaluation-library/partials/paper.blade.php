{{--
    Expects: $evaluationForm, $editingVersion (evaluationCriteria/scaleLabels/
    presentationOutcomes/applicablePresentationModes loaded), $letterhead, $readOnly

    This partial is the live "paper" canvas — everything on it is content that
    would appear on a real printed evaluation sheet. Config/meta/actions live
    in partials/tools-panel.blade.php instead, so this stays a clean surface.
--}}
@php
    $sections = $editingVersion->evaluationCriteria->whereNull('parent_criterion_id')->sortBy('sort_order')->values();
    $selectedMode = $editingVersion->applicablePresentationModes->first();
    $isTitleProposal = $selectedMode?->code === 'TITLE_PROPOSAL';
    $illustrativeResearchers = ['Leader', 'Member 1', 'Member 2', 'Member 3'];
@endphp

<div class="eval-paper">
    @if ($editingVersion->show_letterhead && $letterhead && $letterhead->hasContent())
        @include('partials.evaluation-letterhead', ['letterhead' => $letterhead])
    @endif

    @unless ($readOnly)
        <form id="eval-title-form" method="POST" action="{{ route('admin.evaluation-library.update', $evaluationForm) }}" class="d-none" data-ajax="update">
            @csrf
            @method('PUT')
        </form>
    @endunless

    <div class="eval-title-wrap">
        @if ($readOnly)
            <h3 class="eval-title-static">{{ $evaluationForm->name }}</h3>
        @else
            <input type="text" name="name" form="eval-title-form" class="eval-plain-input eval-title-input"
                   value="{{ $evaluationForm->name }}" maxlength="200" placeholder="Evaluation Title" data-autosave-input>
        @endif
    </div>

    @unless ($isTitleProposal)
        <table class="eval-paper-info-table">
            <tr>
                <td class="eval-info-label">Capstone Title:</td>
                <td class="eval-info-value text-brand-muted">[auto-filled from the group's registered project title]</td>
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
            @foreach ($illustrativeResearchers as $role)
                <tr>
                    <td class="text-brand-muted">{{ $loop->iteration }}. {{ $role }} <span class="eval-illustrative-tag">(auto-filled)</span></td>
                    @unless ($isTitleProposal)
                        <td class="eval-info-value"></td>
                    @endunless
                    @if ($loop->first)
                        <td class="eval-remarks-cell" rowspan="{{ count($illustrativeResearchers) + 1 }}">
                            @forelse ($editingVersion->presentationOutcomes as $outcome)
                                <div class="eval-remark-option">
                                    <span class="eval-checkbox-glyph">&#9633;</span> {{ $outcome->name }}
                                </div>
                            @empty
                                <span class="text-brand-muted small">No remarks selected yet &mdash; choose some in the tools panel.</span>
                            @endforelse
                        </td>
                    @endif
                </tr>
            @endforeach
            <tr class="eval-adviser-row">
                <td class="text-brand-muted">Adviser <span class="eval-illustrative-tag">(auto-filled)</span></td>
                @unless ($isTitleProposal)
                    <td class="eval-info-value"></td>
                @endunless
            </tr>
        </tbody>
    </table>

    @if ($isTitleProposal)
        {{-- Builder shows exactly one title block — this is the one real,
             editable criteria set. At actual evaluation time this whole
             block (header row + criteria table) repeats once per title the
             group registered, each rated separately; that repetition is a
             live-evaluation-time concern, not something the builder itself
             renders multiple copies of. --}}
        <table class="eval-paper-info-table eval-title-block-header">
            <tr>
                <td class="eval-info-label">Proposed Title:</td>
                <td class="eval-info-value text-brand-muted">[auto-filled from the group's registered proposed title]</td>
                <td class="eval-approved-cell"><span class="eval-checkbox-glyph">&#9633;</span> Approved</td>
            </tr>
        </table>

        @include('admin.evaluation-library.partials.criteria-table', [
            'evaluationForm' => $evaluationForm,
            'editingVersion' => $editingVersion,
            'sections' => $sections,
            'tableReadOnly' => $readOnly,
        ])

        <div class="eval-paper-footer-box">
            <strong>Approved Title:</strong> <span class="text-brand-muted">___</span>
        </div>
    @else
        @include('admin.evaluation-library.partials.criteria-table', [
            'evaluationForm' => $evaluationForm,
            'editingVersion' => $editingVersion,
            'sections' => $sections,
            'tableReadOnly' => $readOnly,
        ])

        <div class="eval-paper-footer-box">
            <strong>Total Score:</strong> <span class="text-brand-muted">___ / 100</span>
        </div>
    @endif

    <div class="eval-paper-footer-box">
        <strong>Comment/s:</strong>
        <div class="eval-paper-comment-lines"></div>
    </div>

    @include('admin.evaluation-library.partials.paper-signoff', [
        'panelName' => '',
        'panelRoleLabel' => 'Panelist',
    ])
</div>
