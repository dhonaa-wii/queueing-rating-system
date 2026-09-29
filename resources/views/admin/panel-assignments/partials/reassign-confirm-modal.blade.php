{{--
    2026-09-12: assigning to a group that already has a panel is an
    edit-in-place — PanelAssignmentService::assign() keeps a panelist who
    stays on, and marks a dropped one REPLACED with ended_at set — so it's
    silent and easy to do by accident when a group is swept up in a
    multi-select. This confirms first, naming each affected group and the
    panel it currently holds.

    Generic (one modal, not one per row) and filled in from whatever is
    checked at open time, same pattern as the bulk Move/Transfer/Defer
    modals on this page. Lives outside #assign-form so its buttons can
    never submit that form on their own.
--}}
<div class="modal fade" id="reassign-confirm-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0">Replace Existing Panel?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-2" id="reassign-confirm-lead"></p>
                <ul class="list-unstyled small mb-0" id="reassign-confirm-list"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-brand" id="reassign-confirm-btn"><x-icon name="check" /> Replace Panel</button>
            </div>
        </div>
    </div>
</div>
