{{--
    Shared field markup for the Add Group / Edit Group modals — same fields
    as the public Screen B registration form (student.registrations.create),
    just rendered inline inside a modal instead of a full page. Expects:
    $idPrefix (unique per modal instance), $isTitleProposal, $memberSlots,
    $requiredTitleCount, $category (for research_track_required /
    technical_adviser_required), $prefill (array of current values, blank
    for Add): leader (person array), members (person arrays), project_title,
    proposed_titles, technical_adviser_user_id.
--}}
@php
    $personFields = ['last_name', 'first_name', 'middle_name', 'sex', 'section', 'research_track_name'];
    // The relation is cached on the category, so the many Edit Group modals share one query.
    $trackOptions = $category->researchTracks->where('is_active', true)->pluck('name')->values()->all();
@endphp

<div class="mb-3">
    <h3 class="h6 mb-2">Group Leader</h3>
    @include('partials.person-fields', [
        'nameFor' => fn ($field) => 'leader_'.$field,
        'idBase' => $idPrefix.'_leader',
        'values' => $prefill['leader'] ?? [],
        'trackRequired' => $category->research_track_required,
        'trackOptions' => $trackOptions,
        'required' => true,
        'small' => true,
    ])
</div>

@if ($memberSlots > 0)
    <div class="mb-3">
        <h3 class="h6 mb-1">Group Members</h3>
        <p class="text-brand-muted small mb-2">Up to {{ $memberSlots }} additional member{{ $memberSlots > 1 ? 's' : '' }}. Fill in every field of a member, or leave the row blank.</p>
        @for ($i = 0; $i < $memberSlots; $i++)
            <div class="{{ $i > 0 ? 'mt-2 pt-2 border-top' : '' }}">
                <div class="small fw-semibold mb-1">Member {{ $i + 1 }}</div>
                @include('partials.person-fields', [
                    'nameFor' => fn ($field) => "members[$i][$field]",
                    'idBase' => $idPrefix."_member_$i",
                    'values' => $prefill['members'][$i] ?? [],
                    'trackRequired' => $category->research_track_required,
                    'trackOptions' => $trackOptions,
                    'required' => false,
                    'small' => true,
                ])
            </div>
        @endfor
    </div>
@endif

<div class="mb-1">
    <h3 class="h6 mb-2">Project Information</h3>

    @if ($isTitleProposal)
        <p class="text-brand-muted small mb-2">Exactly {{ $requiredTitleCount }} proposed title{{ $requiredTitleCount > 1 ? 's' : '' }}.</p>
        @for ($i = 0; $i < $requiredTitleCount; $i++)
            <div class="mb-2">
                <label for="{{ $idPrefix }}_proposed_title_{{ $i }}" class="form-label small">Proposed Title {{ $i + 1 }} <span class="text-brand-accent">*</span></label>
                <input type="text" name="proposed_titles[{{ $i }}]" id="{{ $idPrefix }}_proposed_title_{{ $i }}" class="form-control form-control-sm" maxlength="300" required value="{{ $prefill['proposed_titles'][$i] ?? '' }}">
            </div>
        @endfor
    @else
        <div class="mb-2">
            <label for="{{ $idPrefix }}_project_title" class="form-label small">Project Title <span class="text-brand-accent">*</span></label>
            <input type="text" name="project_title" id="{{ $idPrefix }}_project_title" class="form-control form-control-sm" maxlength="255" required value="{{ $prefill['project_title'] ?? '' }}">
        </div>
    @endif

    @if ($category->technical_adviser_required)
        <div class="mb-0">
            <label for="{{ $idPrefix }}_technical_adviser_user_id" class="form-label small">Technical Adviser <span class="text-brand-accent">*</span></label>
            <select name="technical_adviser_user_id" id="{{ $idPrefix }}_technical_adviser_user_id" class="form-select form-select-sm" required data-adviser-select>
                <option value="">Select an adviser&hellip;</option>
                @php
                    $adviserOptions = $category->choosablePanelists();
                    $currentAdviser = $prefill['technical_adviser_user_id'] ?? null;
                    $adviserLabelCounts = $adviserOptions->countBy(fn ($panelist) => mb_strtolower(\App\Models\PresentationCategory::panelistName($panelist)));
                @endphp
                @if ($currentAdviser && ! $adviserOptions->contains('id', (int) $currentAdviser))
                    <option value="{{ $currentAdviser }}" selected>{{ $prefill['technical_adviser_name'] ?? 'Current adviser' }}</option>
                @endif
                @foreach ($adviserOptions as $adviser)
                    @php $adviserName = \App\Models\PresentationCategory::panelistName($adviser); @endphp
                    <option value="{{ $adviser->id }}" @selected((string) $currentAdviser === (string) $adviser->id)>{{ $adviserName }}@if (($adviserLabelCounts[mb_strtolower($adviserName)] ?? 0) > 1) · {{ $adviser->username }}@endif</option>
                @endforeach
            </select>
        </div>
    @endif
</div>

{{-- Add Group only: once registered, a group's panel is changed with Edit Panel. --}}
@if (($withPanelChoices ?? false) && $category->groupsChoosePanelists())
    <div class="mt-3">
        <h3 class="h6 mb-2">Panel</h3>
        @for ($i = 0; $i < (int) $category->panelist_count; $i++)
            <div class="mb-2">
                <label for="{{ $idPrefix }}_panelist_choice_{{ $i }}" class="form-label small">{{ $i === 0 ? 'Chair' : 'Member '.$i }} <span class="text-brand-accent">*</span></label>
                <select name="panelist_choices[{{ $i }}]" id="{{ $idPrefix }}_panelist_choice_{{ $i }}" class="form-select form-select-sm" required data-panelist-choice>
                    <option value="">Select a panelist&hellip;</option>
                    @foreach ($category->choosablePanelists() as $panelist)
                        <option value="{{ $panelist->id }}">{{ \App\Models\PresentationCategory::panelistName($panelist) }}</option>
                    @endforeach
                </select>
            </div>
        @endfor
    </div>
@endif
@include('partials.adviser-panel-exclusion-script')
