{{--
    2026-08-25: a queue adjustment (reorder/transfer/defer/reinsert) that
    creates a new panelist scheduling conflict is no longer blocked — it
    saves, and this one-time modal alerts the admin immediately. Same
    flash-then-auto-open pattern as partials/credential-reveal-modal.blade.php.
    A standing notification/badge (bell, Group & Panel Assignment's Needs
    Attention card, Panelist Management) also gets raised independently, so
    dismissing this modal doesn't lose track of the conflict.

    Expected shape: ['title' => string, 'items' => string[]]
--}}
@if (session('conflict_alert'))
    @php $conflictAlert = session('conflict_alert'); @endphp
    <div class="modal fade" id="schedule-conflict-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title mb-0">{{ $conflictAlert['title'] ?? 'Scheduling Conflict' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="mb-0 ps-3">
                        @foreach ($conflictAlert['items'] ?? [] as $item)
                            <li class="small mb-2">{{ $item }}</li>
                        @endforeach
                    </ul>
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
                var el = document.getElementById('schedule-conflict-modal');
                if (el) new bootstrap.Modal(el).show();
            });
        </script>
    @endpush
@endif
