<div class="modal fade" id="upload-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('super-admin.backups.upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <label for="upload-archive" class="form-label">Backup file (.zip)</label>
                    <input type="file" name="archive" id="upload-archive" accept=".zip,application/zip" class="form-control form-control-sm @error('archive') is-invalid @enderror" required>
                    @error('archive') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand"><x-icon name="file-plus" /> Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>
