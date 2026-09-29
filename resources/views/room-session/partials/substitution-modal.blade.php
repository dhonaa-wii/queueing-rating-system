{{--
    Sibling of the other modals at the bottom of home.blade.php (not nested
    inside #connection-panel/#left-sidebar-sticky) — that sidebar's
    position: sticky creates a stacking context that traps a nested
    position: fixed modal behind the navbar, the exact bug already
    documented at length in home.blade.php's own opening comment for the
    Complete/Submit-Evaluation modals. Opened programmatically (not
    data-bs-toggle) by the reveal script below, once the loading beat
    finishes.
--}}
@if ($connection && in_array($assignmentClassification['kind'] ?? null, ['backup', 'unassigned'], true))
    <div class="modal fade" id="substitution-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ $assignmentClassification['kind'] === 'backup' ? "You're the Alternate Panel" : "You're Not Currently Assigned" }}
                    </h5>
                </div>
                <div class="modal-body" id="substitution-modal-body" data-kind="{{ $assignmentClassification['kind'] }}">
                    @include('room-session.partials.substitution-modal-body')
                </div>
                <div class="modal-footer">
                    <form method="POST" action="{{ route('room-session.logout') }}" class="w-100">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger-brand w-100"><x-icon name="log-out" /> Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
