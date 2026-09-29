<div class="modal fade" id="edit-panelist-modal-{{ $panelist->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.panelists.update', $panelist) }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit {{ $panelist->username }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="{{ $panelist->username }}" disabled>
                    </div>
                    @include('admin.panelists.partials.form-fields', ['idPrefix' => 'edit-'.$panelist->id, 'panelist' => $panelist])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand"><x-icon name="save" /> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
