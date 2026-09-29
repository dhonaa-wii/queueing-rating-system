<div class="modal fade" id="add-panelist-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.panelists.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Panelist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('admin.panelists.partials.form-fields', ['idPrefix' => 'add', 'panelist' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand"><x-icon name="user-plus" /> Register Panelist</button>
                </div>
            </form>
        </div>
    </div>
</div>
