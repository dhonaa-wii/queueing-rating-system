{{--
    Expects: $allOutcomesForManagement (every presentation_outcomes row,
    including inactive ones so they can be reactivated here)

    Replaces the old standalone "Manage Outcomes" page — this is the same
    CRUD, just opened from the tools panel as a modal instead of navigating
    away from the workspace.
--}}
<div class="modal fade" id="manage-outcomes-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0">Manage Remarks / Outcomes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 class="h6">Add Outcome</h6>
                <form method="POST" action="{{ route('admin.evaluation-library.outcomes.store') }}" class="row g-2 align-items-end mb-4">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label small text-brand-muted">Code</label>
                        <input type="text" name="code" class="form-control form-control-sm" maxlength="50" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-brand-muted">Name</label>
                        <input type="text" name="name" class="form-control form-control-sm" maxlength="150" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-brand-muted">Successful?</label>
                        <select name="is_successful" class="form-select form-select-sm">
                            <option value="">N/A</option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex flex-column gap-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="requires_new_attempt" value="1" id="new-outcome-attempt">
                            <label class="form-check-label small" for="new-outcome-attempt">Requires new attempt</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allows_project_title_change" value="1" id="new-outcome-title">
                            <label class="form-check-label small" for="new-outcome-title">Allows title change</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-brand"><x-icon name="plus" /> Add Outcome</button>
                    </div>
                </form>

                <h6 class="h6">All Outcomes</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr class="text-brand-muted small">
                                <th>Code</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($allOutcomesForManagement as $outcome)
                                <tr>
                                    <td colspan="4" class="p-0">
                                        <form id="outcome-update-{{ $outcome->id }}" method="POST" action="{{ route('admin.evaluation-library.outcomes.update', $outcome) }}" class="d-none">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="requires_new_attempt" value="{{ $outcome->requires_new_attempt ? 1 : 0 }}">
                                            <input type="hidden" name="allows_project_title_change" value="{{ $outcome->allows_project_title_change ? 1 : 0 }}">
                                            <input type="hidden" name="is_successful" value="{{ is_null($outcome->is_successful) ? '' : ($outcome->is_successful ? 1 : 0) }}">
                                        </form>
                                        <div class="row g-2 align-items-center py-1 px-2">
                                            <div class="col-3">
                                                <input form="outcome-update-{{ $outcome->id }}" type="text" name="code" value="{{ $outcome->code }}" maxlength="50" required class="form-control form-control-sm">
                                            </div>
                                            <div class="col-4">
                                                <input form="outcome-update-{{ $outcome->id }}" type="text" name="name" value="{{ $outcome->name }}" maxlength="150" required class="form-control form-control-sm">
                                            </div>
                                            <div class="col-2">
                                                <span class="badge {{ $outcome->is_active ? 'badge-success-tint' : 'badge-muted-tint' }}">{{ $outcome->is_active ? 'Active' : 'Inactive' }}</span>
                                            </div>
                                            <div class="col-3 d-flex gap-1 justify-content-end">
                                                <button form="outcome-update-{{ $outcome->id }}" type="submit" class="btn btn-sm btn-outline-brand"><x-icon name="save" /> Save</button>
                                                <form method="POST" action="{{ route('admin.evaluation-library.outcomes.toggle-active', $outcome) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-brand">{{ $outcome->is_active ? 'Deactivate' : 'Activate' }}</button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-brand-muted small text-center py-3">No outcomes yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
