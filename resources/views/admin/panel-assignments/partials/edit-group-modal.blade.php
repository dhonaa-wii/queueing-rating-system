{{-- Expects $editGroup (ResearchGroup, with students + proposedTitles loaded), $category, $memberSlots, $isTitleProposal, $requiredTitleCount. --}}
@php
    $editLeader = $editGroup->leader();
    $editMembers = $editGroup->students->reject(fn ($s) => $s->is_leader)->values();
    $person = fn ($s) => [
        'last_name' => $s->last_name,
        'first_name' => $s->first_name,
        'middle_name' => $s->middle_name,
        'sex' => $s->sex,
        'section' => $s->section_name,
        'research_track_name' => $s->research_track_name,
    ];
@endphp
<div class="modal fade" id="edit-group-modal-{{ $editGroup->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.panel-assignments.groups.update', [$category, $editGroup]) }}" class="group-registration-form" data-member-slots="{{ $memberSlots }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit {{ $editGroup->group_reference }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('admin.panel-assignments.partials.group-fields', [
                        'idPrefix' => 'edit-'.$editGroup->id,
                        'prefill' => [
                            'leader' => $editLeader ? $person($editLeader) : [],
                            'members' => $editMembers->map($person)->all(),
                            'project_title' => $editGroup->current_project_title,
                            'proposed_titles' => $editGroup->proposedTitles->pluck('title_text')->all(),
                            'technical_adviser_name' => $editGroup->technical_adviser_name,
                        ],
                    ])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand"><x-icon name="save" /> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
