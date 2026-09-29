{{--
    Restore confirmation + progress. One modal serves every row: the trigger
    button carries the backup's URL/name/date/encryption as data-restore-*.
    Once the restore starts the modal cannot be dismissed — the site is closed
    while it runs and the progress bar is the only thing on screen worth watching.
--}}
<div class="modal fade" id="restore-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restore Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="restore-close"></button>
            </div>

            <form id="restore-form" autocomplete="off">
                <div class="modal-body">
                    <p class="mb-1 fw-semibold" id="restore-name"></p>
                    <p class="text-brand-muted mb-3" id="restore-when" style="font-size: var(--page-fs-sm);"></p>

                    <div class="bk-callout mb-3">
                        Everything now in the system is replaced by what this backup holds, including accounts,
                        registrations and results entered since it was taken. A safety backup is taken first, and the
                        system is closed to everyone until the restore finishes.
                    </div>

                    <div class="mb-3" id="restore-password-group" hidden>
                        <label for="restore-password" class="form-label">Archive Password</label>
                        <input type="password" name="archive_password" id="restore-password" class="form-control form-control-sm" autocomplete="off">
                    </div>

                    <label for="restore-confirm" class="form-label">Type RESTORE to continue</label>
                    <input type="text" name="confirm" id="restore-confirm" class="form-control form-control-sm" autocomplete="off">
                    <div class="text-danger mt-2" id="restore-error" style="font-size: var(--page-fs-sm);" hidden></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-outline-danger-brand" id="restore-submit" disabled><x-icon name="refresh" /> Restore</button>
                </div>
            </form>

            <div class="modal-body bk-modal-progress" id="restore-progress" hidden>
                <div class="d-flex justify-content-between mb-2" style="font-size: var(--page-fs-sm);">
                    <strong id="restore-phase">Starting…</strong>
                    <span id="restore-percent">0%</span>
                </div>
                <div class="progress"><div class="progress-bar" id="restore-bar" style="width: 0%;"></div></div>
            </div>
        </div>
    </div>
</div>
