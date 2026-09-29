{{--
    Shared read-only evaluation-sheet viewer: one modal, whose body is fetched
    on demand from whatever URL the clicked button carries. Any page that
    renders a button with data-view-sheet + data-url + data-group picks this
    up — Reports' Generated Grades table, Group & Panel Assignment's
    Re-Defense tab (both pointing at admin.reports.evaluation-sheet) and the
    Panelist's own My Assignments Completed tab (pointing at its own
    role-scoped panelist.assignments.evaluation-sheet, which serves the very
    same fragment). Extracted from grades.blade.php verbatim (2026-09-13) when
    the second call site appeared, rather than copying ~90 lines of
    modal/pager script into another page.
--}}
    <div class="modal fade" id="evaluation-sheet-modal" tabindex="-1" aria-labelledby="evaluation-sheet-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="evaluation-sheet-modal-label">Evaluation Sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" data-sheet-body></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @include('admin.evaluation-library.partials.paper-styles')

    <style>
        .sheet-pager { display: inline-flex; align-items: center; gap: 0.5rem; }
        .sheet-pager-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 2.4rem; height: 2.4rem; padding: 0;
            border: 1px solid transparent; border-radius: 0.75rem;
            background: var(--brand-surface-alt); color: var(--brand-text);
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, transform 0.15s ease;
        }
        .sheet-pager-btn svg { width: 1.15rem; height: 1.15rem; }
        .sheet-pager-btn:hover:not(:disabled) {
            background: var(--brand-accent-tint); color: var(--brand-accent);
            transform: translateY(-1px);
        }
        .sheet-pager-btn:disabled { opacity: 0.35; }
        .sheet-pager-count {
            min-width: 3.5rem; text-align: center;
            font-variant-numeric: tabular-nums; font-weight: 600; font-size: 0.9rem;
            color: var(--brand-muted);
        }
    </style>

    {{-- One shared modal filled on demand — same pattern as the bulk
    Move/Transfer modals in Group & Panel Assignment, except the body is
    fetched rather than composed client-side, since a sheet is far too
    large to render inline once per roster row. --}}
    <script>
        (function () {
            const modalEl = document.getElementById('evaluation-sheet-modal');
            if (! modalEl) return;

            const body = modalEl.querySelector('[data-sheet-body]');
            const label = modalEl.querySelector('.modal-title');
            let requestId = 0;
            let pages = [];
            let index = 0;

            function showPage(next) {
                if (! pages.length) return;
                index = Math.min(Math.max(next, 0), pages.length - 1);
                pages.forEach(function (page, i) { page.hidden = i !== index; });
                body.scrollTop = 0;
            }

            // Delegated: each fetched page renders its own pager, so the
            // buttons don't exist until the body is filled in.
            body.addEventListener('click', function (event) {
                if (event.target.closest('[data-sheet-prev]')) showPage(index - 1);
                else if (event.target.closest('[data-sheet-next]')) showPage(index + 1);
            });

            document.querySelectorAll('[data-view-sheet]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const current = ++requestId;
                    label.textContent = 'Evaluation Sheet — ' + btn.dataset.group;
                    body.innerHTML = '<div class="text-center text-brand-muted py-4">Loading…</div>';
                    pages = [];
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();

                    fetch(btn.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (res) {
                            if (! res.ok) throw new Error(res.status);
                            return res.text();
                        })
                        .then(function (html) {
                            if (current !== requestId) return;
                            body.innerHTML = html;
                            pages = Array.from(body.querySelectorAll('[data-sheet-page]'));
                            showPage(0);
                        })
                        .catch(function () {
                            if (current === requestId) {
                                body.innerHTML = '<p class="text-danger mb-0">Could not load the evaluation sheet.</p>';
                            }
                        });
                });
            });
        })();
    </script>
