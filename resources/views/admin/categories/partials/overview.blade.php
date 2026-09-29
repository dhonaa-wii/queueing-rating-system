@php
    $isCreate = ! $category;
    $selectedModeId = old('presentation_mode_id', $category->presentation_mode_id ?? $presentationModes->first()?->id);
    $missingIdentityData = ! $activeAcademicYear || ! $activeSemester || ! $ownCollege;
    // Frozen once the category's first presentation date has actually started
    // (user-directed 2026-09-21) — see PresentationCategory::hasStarted().
    $modeFrozen = ! $isCreate && $category->hasStarted();
    $displayCollege = $category->college ?? $ownCollege;
@endphp

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card-brand p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h3 class="h6 mb-0">Project Information</h3>

                <div class="d-flex align-items-center gap-2">
                    <label for="presentation_mode_id" class="visually-hidden">Presentation Mode</label>
                    <select name="presentation_mode_id" id="presentation_mode_id" form="overview-form"
                            class="form-select form-select-sm" style="width:auto;" data-locked="{{ $isCreate ? '0' : '1' }}"
                            @if ($modeFrozen) disabled title="This category has already started presenting — the presentation mode is fixed." @endif required>
                        @foreach ($presentationModes as $mode)
                            <option value="{{ $mode->id }}" data-code="{{ $mode->code }}" @selected($selectedModeId == $mode->id)>{{ $mode->name }}</option>
                        @endforeach
                    </select>

                    @if ($modeFrozen)
                        {{-- A disabled control submits nothing, and the mode is a required field. --}}
                        <input type="hidden" name="presentation_mode_id" value="{{ $category->presentation_mode_id }}" form="overview-form">
                    @endif
                </div>
            </div>

            <form id="overview-form"
                  method="POST"
                  action="{{ $isCreate ? route('admin.categories.store') : route('admin.categories.update', $category) }}"
                  data-ajax="{{ $isCreate ? 'create' : 'update' }}">
                @csrf
                @unless ($isCreate) @method('PUT') @endunless

                <div class="mb-3">
                    <label for="name" class="form-label">Category Name</label>
                    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $category->name ?? '') }}" maxlength="200" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $category->description ?? '') }}</textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Academic Year</label>
                        <input type="text" class="form-control" disabled value="{{ $category->academicYear->name ?? $activeAcademicYear->name ?? 'None active' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Semester</label>
                        <input type="text" class="form-control" disabled value="{{ $category->semester->name ?? $activeSemester->name ?? 'None active' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">College</label>
                        <input type="text" class="form-control" disabled value="{{ $displayCollege->name ?? 'Not assigned' }}">
                    </div>
                </div>

                @if ($isCreate && $missingIdentityData)
                    <div class="alert alert-warning py-2 small">
                        Cannot create a category yet &mdash;
                        @if (! $activeAcademicYear) no Academic Year is set active. @endif
                        @if (! $activeSemester) no Semester is set active. @endif
                        @if (! $ownCollege) your Admin account has no College assigned. @endif
                        Ask your Super Administrator to fix this first.
                    </div>
                @endif

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label for="subject_or_research_type" class="form-label">Subject</label>
                        <input type="text" name="subject_or_research_type" id="subject_or_research_type" class="form-control" value="{{ old('subject_or_research_type', $category->subject_or_research_type ?? '') }}" maxlength="150">
                    </div>

                    <div class="col-md-4">
                        <label for="maximum_members" class="form-label">Max Group Members</label>
                        <input type="number" name="maximum_members" id="maximum_members" class="form-control" value="{{ old('maximum_members', $category->maximum_members ?? 5) }}" min="1" max="255" required>
                    </div>
                </div>

                <div class="collapse-smooth mb-2" id="title-proposal-field">
                    <div class="mb-3">
                        <label for="required_proposed_title_count" class="form-label">Required Proposed Title Count</label>
                        <input type="number" name="required_proposed_title_count" id="required_proposed_title_count" class="form-control"
                               value="{{ old('required_proposed_title_count', $category->required_proposed_title_count ?? 3) }}" min="1" max="255">
                    </div>
                </div>

                <button type="submit" class="btn btn-brand" @if($isCreate && $missingIdentityData) disabled @endif>
                    {{ $isCreate ? 'Create Category & Continue' : 'Save Changes' }}
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        @if ($category)
            <div class="card-brand p-4">
                <h3 class="h6 mb-3">Panel Count Configuration</h3>

                <form method="POST" action="{{ route('admin.categories.panelist-count-config.update', $category) }}" data-ajax="update">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="panelist_count" class="form-label">Panelists per Room</label>
                        <input type="number" name="panelist_count" id="panelist_count" class="form-control"
                               value="{{ old('panelist_count', $category->panelist_count) }}" min="1" max="50">
                    </div>

                    <button type="submit" class="btn btn-outline-brand btn-sm"><x-icon name="save" /> Save Panel Count</button>
                </form>

                <hr class="brand-divider my-4">

                <h3 class="h6 mb-3">Requirement Checklist</h3>

                <form method="POST" action="{{ route('admin.categories.project-info-config.update', $category) }}" data-ajax="update">
                    @csrf
                    @method('PUT')

                    <div class="form-check mb-2">
                        <input type="hidden" name="technical_adviser_required" value="0">
                        <input class="form-check-input" type="checkbox" name="technical_adviser_required" id="technical_adviser_required" value="1" @checked($category->technical_adviser_required)>
                        <label class="form-check-label" for="technical_adviser_required">Technical adviser required</label>
                    </div>

                    <div class="form-check mb-3">
                        <input type="hidden" name="research_track_required" value="0">
                        <input class="form-check-input" type="checkbox" name="research_track_required" id="research_track_required" value="1" @checked($category->research_track_required)>
                        <label class="form-check-label" for="research_track_required">Track required</label>
                    </div>

                    <button type="submit" class="btn btn-outline-brand btn-sm"><x-icon name="save" /> Save Checklist</button>
                </form>

                @if ($category->research_track_required)
                    <hr class="brand-divider my-4">

                    <h3 class="h6 mb-3">Research Tracks</h3>

                    <form method="POST" action="{{ route('admin.categories.tracks.store', $category) }}" class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        @csrf
                        <input type="text" name="name" placeholder="Track name" class="form-control form-control-sm" style="width:auto;" required maxlength="150">
                        <button type="submit" class="btn btn-sm btn-outline-brand"><x-icon name="plus" /> Add Track</button>
                    </form>

                    @forelse ($category->researchTracks->where('is_active', true) as $track)
                        <form method="POST" action="{{ route('admin.categories.tracks.update', [$category, $track]) }}" class="d-flex gap-2 align-items-center mb-2">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ $track->name }}" class="form-control form-control-sm" required maxlength="150" aria-label="Track name">
                            <button type="submit" class="btn btn-sm btn-outline-brand" title="Rename"><x-icon name="save" /></button>
                            <button type="button" class="btn btn-sm btn-outline-danger-brand" title="Remove"
                                    data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                                    data-confirm-action="{{ route('admin.categories.tracks.destroy', [$category, $track]) }}"
                                    data-confirm-method="DELETE"
                                    data-confirm-title="Remove {{ $track->name }}"
                                    data-confirm-message="Rooms limited to this track lose it, and its groups move to rooms with no tracks."
                                    data-confirm-submit-label="Remove Track">
                                <x-icon name="trash" />
                            </button>
                        </form>
                    @empty
                        <p class="text-brand-muted small mb-0">No tracks yet.</p>
                    @endforelse
                @endif
            </div>
        @endif
    </div>
</div>

@unless ($isCreate || $modeFrozen)
    <div class="modal fade" id="mode-change-confirm-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Presentation Mode?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        This category's presentation mode is already set. Changing it can reset mode-specific
                        fields (like the required proposed title count) back to blank/default once saved.
                        Continue?
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-brand" id="mode-change-confirm-btn">Continue <x-icon name="arrow-right" /></button>
                </div>
            </div>
        </div>
    </div>
@endunless

@push('styles')
    <style>
        .collapse-smooth {
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transition: max-height 0.25s ease, opacity 0.2s ease;
        }

        .collapse-smooth.is-open {
            max-height: 200px;
            opacity: 1;
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            var select = document.getElementById('presentation_mode_id');
            var titleField = document.getElementById('title-proposal-field');
            var locked = select.dataset.locked === '1';
            var originalValue = select.value;
            var pendingValue = null;

            var confirmModalEl = document.getElementById('mode-change-confirm-modal');
            var confirmModal = confirmModalEl && window.bootstrap ? new bootstrap.Modal(confirmModalEl) : null;
            var confirmBtn = document.getElementById('mode-change-confirm-btn');

            function syncTitleField() {
                var selected = select.options[select.selectedIndex];
                var isTitleProposal = selected && selected.dataset.code === 'TITLE_PROPOSAL';
                titleField.classList.toggle('is-open', !!isTitleProposal);
            }

            select.addEventListener('change', function () {
                if (locked && select.value !== originalValue) {
                    pendingValue = select.value;
                    select.value = originalValue;

                    if (confirmModal) {
                        confirmModal.show();
                    } else {
                        // Fallback if Bootstrap JS isn't ready yet — plain confirm.
                        if (confirm('Change the presentation mode? This can reset mode-specific fields back to blank/default once saved.')) {
                            select.value = pendingValue;
                            originalValue = pendingValue;
                            pendingValue = null;
                        }
                    }

                    syncTitleField();
                    return;
                }

                syncTitleField();
            });

            if (confirmBtn) {
                confirmBtn.addEventListener('click', function () {
                    if (pendingValue !== null) {
                        select.value = pendingValue;
                        originalValue = pendingValue;
                        pendingValue = null;
                        syncTitleField();
                    }

                    if (confirmModal) {
                        confirmModal.hide();
                    }
                });
            }

            syncTitleField();
        })();
    </script>
@endpush
