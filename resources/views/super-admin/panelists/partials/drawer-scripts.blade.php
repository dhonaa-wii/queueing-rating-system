@push('scripts')
    <script>
        (function () {
            var table = document.getElementById('panelist-table');
            var panelistIds = table ? JSON.parse(table.dataset.panelistIds || '[]') : [];
            var overlay = document.querySelector('[data-panelist-drawer]');
            if (!overlay) {
                return;
            }

            var loadingEl = overlay.querySelector('[data-drawer-loading]');
            var contentEl = overlay.querySelector('[data-drawer-content]');
            var positionEl = overlay.querySelector('[data-drawer-position]');
            var prevBtn = overlay.querySelector('[data-drawer-prev]');
            var nextBtn = overlay.querySelector('[data-drawer-next]');
            var rowsEl = overlay.querySelector('[data-drawer-rows]');
            var emptyEl = overlay.querySelector('[data-drawer-empty]');
            var searchInput = overlay.querySelector('[data-drawer-search]');

            var currentId = null;
            var currentRows = [];
            var activeTab = 'all';

            function escapeHtml(value) {
                var div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            }

            function panelBadge(panel) {
                var cls = panel.kind === 'backup' ? 'badge-brand-tint' : 'badge-muted-tint';
                var label = panel.kind === 'backup' ? 'Alternate Panel: ' + panel.name : panel.name;
                return '<span class="badge ' + cls + ' mb-1 d-inline-block me-1">' + escapeHtml(label) + '</span>';
            }

            function renderRows() {
                var needle = (searchInput.value || '').toLowerCase().trim();

                var filtered = currentRows.filter(function (row) {
                    if (activeTab !== 'all' && row.status !== activeTab) {
                        return false;
                    }
                    if (!needle) {
                        return true;
                    }
                    var haystack = (row.titleOrLeader + ' ' + row.category).toLowerCase();
                    return haystack.indexOf(needle) !== -1;
                });

                if (filtered.length === 0) {
                    rowsEl.innerHTML = '';
                    emptyEl.classList.remove('d-none');
                    return;
                }

                emptyEl.classList.add('d-none');
                rowsEl.innerHTML = filtered.map(function (row) {
                    var timeCell = row.status === 'deferred'
                        ? '<span class="badge badge-danger-tint">Deferred</span>'
                        : escapeHtml(row.dateTimeDisplay);
                    var panels = row.otherPanels.length
                        ? row.otherPanels.map(panelBadge).join('')
                        : '<span class="text-brand-muted">None</span>';

                    return '<tr>'
                        + '<td>' + timeCell + '</td>'
                        + '<td>' + escapeHtml(row.titleOrLeader) + '</td>'
                        + '<td>' + panels + '</td>'
                        + '<td>' + escapeHtml(row.category) + '</td>'
                        + '</tr>';
                }).join('');
            }

            function renderPayload(data) {
                overlay.querySelector('[data-drawer-name]').textContent = data.fullName;
                overlay.querySelector('[data-drawer-username]').textContent = data.username;
                overlay.querySelector('[data-drawer-sex]').textContent = data.sex || '—';
                overlay.querySelector('[data-drawer-contact]').textContent = data.contactNumber || '—';
                overlay.querySelector('[data-drawer-college]').textContent = data.college || '—';
                overlay.querySelector('[data-drawer-specialization]').textContent = data.specialization || '—';

                var statusBadgeClasses = {
                    ACTIVE: 'badge-success-tint',
                    INACTIVE: 'badge-muted-tint',
                    LOCKED: 'badge-danger-tint'
                };
                var statusEl = overlay.querySelector('[data-drawer-status]');
                statusEl.textContent = data.accountStatusName || '—';
                statusEl.className = 'badge flex-shrink-0 ' + (statusBadgeClasses[data.accountStatus] || 'badge-info-tint');

                var initialsSource = (data.fullName || data.username || '').trim();
                var parts = initialsSource.split(/\s+/);
                var initials = ((parts[0] || '')[0] || '') + ((parts.length > 1 ? parts[parts.length - 1] : '')[0] || '');
                overlay.querySelector('[data-drawer-initials]').textContent = initials.toUpperCase();

                var conflictBadge = overlay.querySelector('[data-drawer-conflict-badge]');
                var conflictAlert = overlay.querySelector('[data-drawer-conflict-alert]');
                if (data.hasConflict) {
                    conflictBadge.classList.remove('d-none');
                    conflictAlert.classList.remove('d-none');
                    conflictAlert.textContent = (data.conflictMessages || []).join(' ');
                } else {
                    conflictBadge.classList.add('d-none');
                    conflictAlert.classList.add('d-none');
                }

                overlay.querySelector('[data-drawer-stat-categories]').textContent = data.summary.assignedCategories;
                overlay.querySelector('[data-drawer-stat-groups]').textContent = data.summary.assignedGroups;
                overlay.querySelector('[data-drawer-stat-evaluated]').textContent = data.summary.evaluatedGroups;

                currentRows = data.assignedGroups || [];
                renderRows();
            }

            function updatePositionAndArrows() {
                var index = panelistIds.indexOf(currentId);
                positionEl.textContent = (index + 1) + ' of ' + panelistIds.length;
                prevBtn.disabled = index <= 0;
                nextBtn.disabled = index === -1 || index >= panelistIds.length - 1;
            }

            function openDrawer(id) {
                currentId = id;
                overlay.classList.add('is-open');
                document.body.style.overflow = 'hidden';

                loadingEl.classList.remove('d-none');
                contentEl.classList.add('d-none');
                updatePositionAndArrows();

                fetch('{{ url('super-admin/panelists') }}/' + id, { headers: { Accept: 'application/json' } })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        renderPayload(data);
                        loadingEl.classList.add('d-none');
                        contentEl.classList.remove('d-none');

                        var url = new URL(window.location.href);
                        url.searchParams.set('panelist', id);
                        window.history.replaceState({}, '', url);
                    });
            }

            function closeDrawer() {
                overlay.classList.remove('is-open');
                document.body.style.overflow = '';

                var url = new URL(window.location.href);
                url.searchParams.delete('panelist');
                window.history.replaceState({}, '', url);
            }

            document.querySelectorAll('[data-open-panelist]').forEach(function (row) {
                row.addEventListener('click', function () {
                    openDrawer(parseInt(row.dataset.openPanelist, 10));
                });
            });

            overlay.querySelectorAll('[data-drawer-close]').forEach(function (el) {
                el.addEventListener('click', closeDrawer);
            });

            prevBtn.addEventListener('click', function () {
                var index = panelistIds.indexOf(currentId);
                if (index > 0) {
                    openDrawer(panelistIds[index - 1]);
                }
            });

            nextBtn.addEventListener('click', function () {
                var index = panelistIds.indexOf(currentId);
                if (index !== -1 && index < panelistIds.length - 1) {
                    openDrawer(panelistIds[index + 1]);
                }
            });

            searchInput.addEventListener('input', renderRows);

            overlay.querySelectorAll('[data-drawer-tab]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    overlay.querySelectorAll('[data-drawer-tab]').forEach(function (b) {
                        b.classList.remove('active');
                    });
                    btn.classList.add('active');
                    activeTab = btn.dataset.drawerTab;
                    renderRows();
                });
            });

            var initialId = new URLSearchParams(window.location.search).get('panelist');
            if (initialId && panelistIds.indexOf(parseInt(initialId, 10)) !== -1) {
                openDrawer(parseInt(initialId, 10));
            }
        })();
    </script>
@endpush
