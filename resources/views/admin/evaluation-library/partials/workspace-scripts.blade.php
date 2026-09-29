@push('styles')
    @include('admin.evaluation-library.partials.paper-styles')
    <style>
        /* Evaluation Library's own content area only ever scrolls through
           .eval-workspace-paper below — the surrounding admin frame (sidebar,
           topbar) and the tools panel stay put, so an autosave/refresh deep
           in the form can't shift anything else on screen. */
        .admin-content { display: flex; flex-direction: column; overflow: hidden; }

        .eval-workspace { display: flex; align-items: stretch; gap: 1.5rem; flex: 1; min-height: 0; }
        .eval-workspace-paper { flex: 1; min-width: 0; height: 100%; overflow-y: auto; padding-right: 0.25rem; }

        .eval-tools-panel {
            position: static;
            width: 300px;
            flex: 0 0 300px;
            height: 100%;
            background: var(--brand-surface);
            border: 1px solid var(--brand-border);
            border-radius: 0.5rem;
            padding: 1rem;
            overflow-y: auto;
        }
        .eval-tools-panel.is-collapsed { width: 44px; flex-basis: 44px; padding: 0.5rem; overflow: hidden; }
        .eval-tools-panel.is-collapsed .eval-tools-panel-inner { display: none; }
        .eval-tools-panel.is-collapsed .eval-tools-toggle svg { transform: rotate(180deg); }
        .eval-tools-toggle { border: none; background: transparent; color: var(--brand-muted); margin-bottom: 0.5rem; cursor: pointer; padding: 0.25rem; }
        .eval-tools-section { margin-bottom: 1.25rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--brand-border); }
        .eval-tools-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .eval-tools-heading { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em; color: var(--brand-muted); margin-bottom: 0.5rem; }

        .eval-title-input { font-size: 1.05rem; font-weight: 600; text-align: center; width: 100%; }
        .eval-title-hint { font-size: 0.7rem; margin: 0.15rem 0 0; }

        .eval-plain-input { border: none; background: transparent; padding: 0.1rem 0.25rem; color: inherit; font: inherit; border-radius: 0.25rem; }
        .eval-plain-input:hover, .eval-plain-input:focus { background: var(--brand-surface-muted, rgba(127, 127, 127, 0.1)); outline: none; }
        .eval-weight-input { width: 3.5rem; text-align: right; }
        .eval-illustrative-tag { font-size: 0.65rem; }

        .eval-inline-form { display: inline-flex; align-items: center; gap: 0.15rem; flex-wrap: wrap; }
        .eval-section-name-input { min-width: 220px; }
        .eval-item-name-input { min-width: 280px; }

        .eval-row-actions { display: inline-flex; gap: 0.2rem; margin-left: 0.5rem; opacity: 0; transition: opacity 0.1s ease; vertical-align: middle; }
        tr[data-eval-row]:hover .eval-row-actions, tr[data-eval-row]:focus-within .eval-row-actions { opacity: 1; }
        .eval-row-action-btn { border: 1px solid var(--brand-border); background: var(--brand-surface); border-radius: 0.25rem; width: 1.5rem; height: 1.5rem; line-height: 1; font-size: 0.75rem; cursor: pointer; }
        .eval-row-action-danger { color: var(--brand-danger, #dc3545); }

        tr[data-eval-row] { cursor: pointer; }
        tr[data-eval-row].is-selected td { background: var(--brand-surface-selected, rgba(127, 127, 127, 0.28)) !important; }

        .eval-add-row-cell { background: var(--brand-surface-muted, rgba(127, 127, 127, 0.04)); }
        .eval-add-row-toolbar { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; padding: 0.35rem 0; }

        @media (max-width: 991px) {
            /* Stacked layout has no room for two independent scroll panes,
               so fall back to one normal scrolling page instead. */
            .admin-content { display: block; overflow-y: auto; }
            .eval-workspace { flex-direction: column; height: auto; }
            .eval-workspace-paper { height: auto; overflow-y: visible; }
            .eval-tools-panel { width: 100%; flex-basis: auto; height: auto; max-height: none; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            var TOOLS_COLLAPSED_KEY = 'eval-tools-panel-collapsed';

            function showToast(message, isError) {
                window.showAppToast(message, isError);
            }

            function submitAjax(form) {
                return fetch(form.getAttribute('action'), {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                }).then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (data) {
                        return { status: response.status, ok: response.ok, data: data };
                    });
                });
            }

            // Structural actions (reorder, remove, publish, new draft) used to
            // be plain form posts — a real page navigation on every click,
            // which always snapped the screen back to the top. `data-ajax=
            // "refresh"` submits them the same way as any other autosave form,
            // then re-renders the workspace in place (scroll position
            // preserved, see refreshWorkspace below) instead of navigating.
            function refreshAfterModalClose(form) {
                var modalEl = form.closest('.modal');
                var modalInstance = modalEl && window.bootstrap ? window.bootstrap.Modal.getInstance(modalEl) : null;

                if (!modalInstance) {
                    return refreshWorkspace(true);
                }

                modalEl.addEventListener('hidden.bs.modal', function onHidden() {
                    modalEl.removeEventListener('hidden.bs.modal', onHidden);
                    refreshWorkspace(true);
                });
                modalInstance.hide();
            }

            function bindAjaxForm(form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    submitAjax(form).then(function (result) {
                        if (result.ok) {
                            showToast(result.data.message || 'Saved.', false);
                            if (form.dataset.ajax === 'refresh') {
                                refreshAfterModalClose(form);
                            }
                        } else {
                            showToast(result.data.message || 'Something went wrong. Please try again.', true);
                        }
                    }).catch(function () {
                        showToast('Network error. Please try again.', true);
                    });
                });
            }

            var workspaceEl = document.querySelector('.eval-workspace');

            // Binds every interactive behavior within `root` — called once on
            // load, and again after each poll-driven refresh (see below)
            // since replacing innerHTML wipes previously-attached listeners.
            function bindWorkspace(root) {
                root.querySelectorAll('form[data-ajax]').forEach(bindAjaxForm);

                // Row selection: clicking a criteria row, or typing/focusing
                // into one of its inputs, marks it the target for "Add row
                // Above/Below" — instead of those buttons always acting on
                // the last row.
                var selectedRow = null;

                function selectRow(row) {
                    root.querySelectorAll('tr[data-eval-row].is-selected').forEach(function (r) {
                        r.classList.remove('is-selected');
                    });
                    row.classList.add('is-selected');
                    selectedRow = {
                        type: row.dataset.rowType,
                        id: parseInt(row.dataset.rowId, 10),
                        sectionId: row.dataset.rowType === 'section'
                            ? parseInt(row.dataset.rowId, 10)
                            : parseInt(row.dataset.sectionId, 10),
                    };
                }

                root.addEventListener('click', function (event) {
                    var row = event.target.closest('tr[data-eval-row]');
                    if (row) selectRow(row);
                });
                root.addEventListener('focusin', function (event) {
                    var row = event.target.closest('tr[data-eval-row]');
                    if (row) selectRow(row);
                });

                // "+ Criteria/Rating (Above/Below)" — placed directly next to
                // the selected row via the store endpoints' position/
                // reference_id params; falls back to the very top/bottom (for
                // a section) or the last section (for a rating) when nothing
                // is selected yet.
                root.querySelectorAll('[data-add-row]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var toolbar = button.closest('.eval-add-row-toolbar');
                        var tokenInput = toolbar ? toolbar.querySelector('input[name="_token"]') : null;
                        if (!tokenInput) return;

                        var kind = button.dataset.kind;
                        var position = button.dataset.position;
                        var url;
                        var body = new FormData();
                        body.append('_token', tokenInput.value);
                        body.append('name', button.dataset.createValue);
                        body.append('position', position);

                        if (kind === 'section') {
                            url = button.dataset.createUrl;
                            var refSectionId = selectedRow ? selectedRow.sectionId : null;
                            if (refSectionId) body.append('reference_id', refSectionId);
                        } else {
                            var sectionId = selectedRow ? selectedRow.sectionId : parseInt(button.dataset.fallbackSectionId, 10);
                            if (!sectionId) {
                                showToast('Add a section first.', true);
                                return;
                            }
                            url = button.dataset.createUrlTemplate.replace('REPLACE_SECTION_ID', sectionId);
                            if (selectedRow && selectedRow.type === 'item') {
                                body.append('reference_id', selectedRow.id);
                            }
                        }

                        button.disabled = true;
                        fetch(url, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            body: body,
                        }).then(function (r) {
                            return r.json();
                        }).then(function () {
                            return refreshWorkspace(true);
                        }).catch(function () {
                            button.disabled = false;
                            showToast('Could not add the row. Please try again.', true);
                        });
                    });
                });

                // Weight total follows the section weight inputs as they are
                // typed, rather than waiting for the blur-autosave below and
                // the next refresh. Nothing is saved by this — it only keeps
                // the tools panel's badge honest about what is on screen; the
                // save still happens on blur, and a refresh re-renders the
                // badge from the server.
                var weightTotalBadge = root.querySelector('[data-weight-total]');
                var weightInputs = root.querySelectorAll('.eval-weight-input');

                function recomputeWeightTotal() {
                    var total = 0;
                    weightInputs.forEach(function (input) {
                        var value = parseFloat(input.value);
                        if (!isNaN(value)) total += value;
                    });
                    total = Math.round(total * 100) / 100;

                    weightTotalBadge.textContent = total + '%';
                    var balanced = Math.abs(total - 100) < 0.01;
                    weightTotalBadge.classList.toggle('badge-success-tint', balanced);
                    weightTotalBadge.classList.toggle('badge-danger-tint', !balanced);
                }

                if (weightTotalBadge) {
                    weightInputs.forEach(function (input) {
                        input.addEventListener('input', recomputeWeightTotal);
                    });
                }

                // Word-style typing: blur-triggered autosave for plain-looking inputs.
                root.querySelectorAll('[data-autosave-input]').forEach(function (el) {
                    var lastValue = el.value;
                    el.addEventListener('blur', function () {
                        if (el.value === lastValue) return;
                        lastValue = el.value;
                        if (el.form) el.form.requestSubmit();
                    });
                });

                // Instant-apply tools-panel controls (radio/select/checkbox).
                root.querySelectorAll('[data-autosave-change]').forEach(function (el) {
                    el.addEventListener('change', function () {
                        if (el.form) el.form.requestSubmit();
                    });
                });

                // Collapsed/expanded is remembered across the periodic/
                // action-triggered refreshes below (which rebuild this panel
                // from scratch) so it doesn't silently pop back open.
                var toolsPanel = root.querySelector('[data-tools-panel]');
                var toolsToggle = root.querySelector('[data-tools-toggle]');
                if (toolsPanel && toolsToggle) {
                    if (localStorage.getItem(TOOLS_COLLAPSED_KEY) === '1') {
                        toolsPanel.classList.add('is-collapsed');
                    }
                    toolsToggle.addEventListener('click', function () {
                        toolsPanel.classList.toggle('is-collapsed');
                        localStorage.setItem(TOOLS_COLLAPSED_KEY, toolsPanel.classList.contains('is-collapsed') ? '1' : '0');
                    });
                }
            }

            // Auto-refresh so structural changes (row add/move/delete, a
            // publish, a new draft) made from another tab/admin show up here
            // without a manual reload — but never while the admin has an
            // editable field focused inside the workspace (so it can't wipe
            // in-progress typing) or a confirmation modal open inside it (so
            // it can't yank a Remove/Publish/Archive dialog out from under
            // an admin mid-decision — the old native confirm() blocked the
            // page entirely so this couldn't happen, but Bootstrap modals
            // don't).
            function refreshWorkspace(force) {
                if (!workspaceEl) return Promise.resolve();

                var active = document.activeElement;
                var isEditingField = !force && active && workspaceEl.contains(active) &&
                    (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT');
                if (isEditingField) return Promise.resolve();

                if (workspaceEl.querySelector('.modal.show')) return Promise.resolve();

                // The paper and tools panel are their own scroll containers
                // now (see the styles above) — replacing their innerHTML
                // would otherwise silently reset both back to the top, which
                // is exactly the "screen jumps after saving" behavior this
                // is here to prevent.
                var existingPaper = workspaceEl.querySelector('.eval-workspace-paper');
                var existingTools = workspaceEl.querySelector('.eval-tools-panel');
                var paperScrollTop = existingPaper ? existingPaper.scrollTop : 0;
                var toolsScrollTop = existingTools ? existingTools.scrollTop : 0;

                return fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (response) { return response.text(); })
                    .then(function (html) {
                        var freshWorkspace = new DOMParser().parseFromString(html, 'text/html').querySelector('.eval-workspace');
                        if (!freshWorkspace || freshWorkspace.innerHTML === workspaceEl.innerHTML) return;
                        workspaceEl.innerHTML = freshWorkspace.innerHTML;
                        bindWorkspace(workspaceEl);

                        var newPaper = workspaceEl.querySelector('.eval-workspace-paper');
                        if (newPaper) newPaper.scrollTop = paperScrollTop;
                        var newTools = workspaceEl.querySelector('.eval-tools-panel');
                        if (newTools) newTools.scrollTop = toolsScrollTop;
                    })
                    .catch(function () {});
            }

            if (workspaceEl) {
                bindWorkspace(workspaceEl);
                setInterval(function () { refreshWorkspace(false); }, 6000);
            }
        })();
    </script>
@endpush
