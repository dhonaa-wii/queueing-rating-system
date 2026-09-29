{{--
    Expects: $evaluationForm, $editingVersion (presentationOutcomes loaded), $allOutcomes, $readOnly
--}}
<div class="eval-tools-section">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="eval-tools-heading mb-0">Remarks / Outcomes</h4>
        <button type="button" class="btn btn-link btn-sm p-0 small" data-bs-toggle="modal" data-bs-target="#manage-outcomes-modal">Manage list &rarr;</button>
    </div>

    @if ($readOnly)
        @if ($editingVersion->presentationOutcomes->isEmpty())
            <p class="text-brand-muted small mb-0">No outcomes selected.</p>
        @else
            <ul class="small mb-0 ps-3">
                @foreach ($editingVersion->presentationOutcomes as $outcome)
                    <li>{{ $outcome->name }}</li>
                @endforeach
            </ul>
        @endif
    @else
        @php $selectedIds = $editingVersion->presentationOutcomes->pluck('id')->all(); @endphp
        <form method="POST" action="{{ route('admin.evaluation-library.versions.outcomes.sync', [$evaluationForm, $editingVersion]) }}" data-ajax="refresh">
            @csrf
            @method('PUT')
            @forelse ($allOutcomes as $outcome)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="outcome_ids[]" value="{{ $outcome->id }}"
                           id="outcome-{{ $outcome->id }}" data-autosave-change @checked(in_array($outcome->id, $selectedIds, true))>
                    <label class="form-check-label small" for="outcome-{{ $outcome->id }}">{{ $outcome->name }}</label>
                </div>
            @empty
                <p class="text-brand-muted small mb-0">
                    No active outcomes yet.
                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-bs-toggle="modal" data-bs-target="#manage-outcomes-modal">Add some first</button>.
                </p>
            @endforelse
        </form>
    @endif
</div>
