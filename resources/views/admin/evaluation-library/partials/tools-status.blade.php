{{--
    Expects: $evaluationForm, $editingVersion (evaluationCriteria loaded), $publishErrors, $canEdit, $readOnly

    A form has one version and is edited in place (2026-09-22, user-directed),
    so there is no version list or "start a new draft" here any more. A draft
    is always editable and offers Publish; a published form opens read-only
    and offers Edit, which reloads the workspace with ?edit=1.
--}}
@php
    $sections = $editingVersion->evaluationCriteria->whereNull('parent_criterion_id');
    $weightTotal = round((float) $sections->sum('weight'), 2);
    $isDraft = $editingVersion->isDraft();
@endphp

<div class="eval-tools-section">
    <h4 class="eval-tools-heading">Status &amp; Publish</h4>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small text-brand-muted">Status</span>
        <span class="badge {{ $isDraft ? 'badge-brand-tint' : ($editingVersion->isActive() ? 'badge-success-tint' : 'badge-muted-tint') }}">
            {{ $isDraft ? 'Draft' : ($editingVersion->isActive() ? 'Published' : $editingVersion->status->name) }}
        </span>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small text-brand-muted">Weight total</span>
        {{-- Recomputed live from the weight inputs as they are typed (see
             workspace-scripts.blade.php); this server value is the starting
             point and what a refresh restores it to. --}}
        <span class="badge {{ abs($weightTotal - 100) < 0.01 ? 'badge-success-tint' : 'badge-danger-tint' }}" data-weight-total>{{ $weightTotal }}%</span>
    </div>

    @if ($isDraft)
        @if ($publishErrors !== [])
            <ul class="small text-brand-danger mb-2 ps-3">
                @foreach ($publishErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
        <button type="button" class="btn btn-sm btn-brand w-100 mb-2" data-bs-toggle="modal" data-bs-target="#publish-version-modal" @disabled($publishErrors !== [])><x-icon name="send" /> Publish</button>
    @elseif ($canEdit && $readOnly)
        <a href="{{ route('admin.evaluation-library.show', [$evaluationForm, 'edit' => 1]) }}" class="btn btn-sm btn-brand w-100 mb-2"><x-icon name="edit" /> Edit Form</a>
    @elseif ($canEdit)
        <a href="{{ route('admin.evaluation-library.show', $evaluationForm) }}" class="btn btn-sm btn-outline-brand w-100 mb-2"><x-icon name="check" /> Done Editing</a>
    @else
        <p class="small mb-2">This form is retired and can no longer be edited.</p>
    @endif

    @if ($evaluationForm->is_active)
        <button type="button" class="btn btn-sm btn-outline-brand w-100" data-bs-toggle="modal" data-bs-target="#archive-form-modal-tools"><x-icon name="archive" /> Archive Form</button>
    @else
        <span class="badge badge-muted-tint">Archived</span>
    @endif
</div>

@if ($editingVersion->isDraft())
    <div class="modal fade" id="publish-version-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Publish Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Publish <strong>{{ $evaluationForm->name }}</strong>? It becomes assignable to categories, and you can keep editing it afterwards.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="{{ route('admin.evaluation-library.versions.publish', [$evaluationForm, $editingVersion]) }}" data-ajax="refresh">
                        @csrf
                        <button type="submit" class="btn btn-brand"><x-icon name="send" /> Publish</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

@if ($evaluationForm->is_active)
    <div class="modal fade" id="archive-form-modal-tools" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Archive Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Archive <strong>{{ $evaluationForm->name }}</strong>? Categories already using it keep it assigned.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="{{ route('admin.evaluation-library.archive', $evaluationForm) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="archive" /> Archive</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
