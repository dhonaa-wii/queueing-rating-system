{{--
    Single reusable "confirm before doing something destructive" modal,
    replacing the native confirm() dialogs that used to sit on plain-POST
    forms throughout this module. A trigger is a plain button carrying the
    target URL/verb/message as data attributes (data-confirm-action,
    data-confirm-method, data-confirm-message, optional data-confirm-title/
    data-confirm-submit-label/data-confirm-variant/data-confirm-list) plus
    data-bs-toggle="modal" data-bs-target="#confirm-action-modal" — the
    modal's own single <form> is repointed at those values right before it
    opens, so one modal instance serves every row/card on the page instead
    of one modal per row. Include this partial once per page (not once per
    looped row) — it's already included from admin.categories.index and
    admin.categories.show.
--}}
<div class="modal fade" id="confirm-action-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirm-action-title">Confirm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="confirm-action-message"></p>
                {{-- Optional itemised detail (data-confirm-list, a JSON array of
                     strings) for a confirmation that has to name what it affects —
                     e.g. the groups still queued in a room being removed. --}}
                <ul class="confirm-action-list mt-2 mb-0" id="confirm-action-list" hidden></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                <form id="confirm-action-form" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="confirm-action-method-field" value="POST">
                    <button type="submit" class="btn btn-outline-danger-brand" id="confirm-action-submit"><x-icon name="check" /> Confirm</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            var modalEl = document.getElementById('confirm-action-modal');
            if (! modalEl) return;

            modalEl.addEventListener('show.bs.modal', function (event) {
                var trigger = event.relatedTarget;
                if (! trigger) return;

                var form = document.getElementById('confirm-action-form');
                var methodField = document.getElementById('confirm-action-method-field');
                var title = document.getElementById('confirm-action-title');
                var message = document.getElementById('confirm-action-message');
                var submitButton = document.getElementById('confirm-action-submit');

                form.action = trigger.dataset.confirmAction || '';
                methodField.value = trigger.dataset.confirmMethod || 'POST';
                title.textContent = trigger.dataset.confirmTitle || 'Confirm';
                message.textContent = trigger.dataset.confirmMessage || 'Are you sure?';

                var list = document.getElementById('confirm-action-list');
                var items = [];

                try {
                    items = JSON.parse(trigger.dataset.confirmList || '[]');
                } catch (e) {
                    items = [];
                }

                list.textContent = '';
                list.hidden = items.length === 0;

                items.forEach(function (text) {
                    var li = document.createElement('li');
                    li.textContent = text;
                    list.appendChild(li);
                });

                submitButton.disabled = false;
                submitButton.textContent = trigger.dataset.confirmSubmitLabel || 'Confirm';
                submitButton.className = 'btn ' + (trigger.dataset.confirmVariant || 'btn-outline-danger-brand');
            });
        })();
    </script>
@endpush
