{{--
    Shared field markup for the Add Group / Edit Group modals — same fields
    as the public Screen B registration form (student.registrations.create),
    just rendered inline inside a modal instead of a full page. Expects:
    $idPrefix (unique per modal instance), $isTitleProposal, $memberSlots,
    $requiredTitleCount, $category (for research_track_required /
    technical_adviser_required), $prefill (array of current values, blank
    for Add): leader (person array), members (person arrays), project_title,
    proposed_titles, technical_adviser_name.
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
            <label for="{{ $idPrefix }}_technical_adviser_name" class="form-label small">Technical Adviser <span class="text-brand-accent">*</span></label>
            <input type="text" name="technical_adviser_name" id="{{ $idPrefix }}_technical_adviser_name" class="form-control form-control-sm" maxlength="200" required value="{{ $prefill['technical_adviser_name'] ?? '' }}">
        </div>
    @endif
</div>
