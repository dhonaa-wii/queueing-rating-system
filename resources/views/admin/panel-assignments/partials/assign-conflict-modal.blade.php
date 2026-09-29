{{--
    2026-08-25: PanelAssignmentService::assign() still hard-blocks a fresh
    assignment that would create a panelist scheduling conflict (unlike the
    queue-adjustment actions in QueueAdjustmentService, which allow the move
    to save and warn afterward via partials/schedule-conflict-modal.blade.php) —
    this modal explains *why* the assignment didn't go through, naming the
    panelist, the conflicting time, and the conflicting group/category,
    instead of a plain flash-error sentence.
--}}
@if (session('assign_conflict'))
    @php $conflict = session('assign_conflict'); @endphp
    <div class="modal fade" id="assign-conflict-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title mb-0">Cannot Assign Panel &mdash; Scheduling Conflict</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0 small">
                        <dt class="col-4">Panelist</dt>
                        <dd class="col-8">{{ $conflict['panelistName'] }}</dd>
                        <dt class="col-4">Time Conflict</dt>
                        <dd class="col-8">{{ $conflict['conflictingTime'] ?? '—' }}</dd>
                        <dt class="col-4">Category</dt>
                        <dd class="col-8">{{ $conflict['conflictingGroup'] }} &middot; {{ $conflict['conflictingCategory'] }}</dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-brand" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var el = document.getElementById('assign-conflict-modal');
                if (el) new bootstrap.Modal(el).show();
            });
        </script>
    @endpush
@endif
