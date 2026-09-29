<div class="dropdown pn-bell" id="pn-bell">
    <button type="button" class="settings-icon-btn position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Notifications" id="pn-bell-btn">
        <x-icon name="bell" />
        <span class="pn-badge d-none" id="pn-badge">0</span>
    </button>

    <div class="dropdown-menu dropdown-menu-end pn-menu">
        <div class="pn-header">Notifications</div>
        <div class="pn-list" id="pn-list"></div>
    </div>
</div>

<style>
    .pn-badge {
        position: absolute;
        top: -0.15rem;
        right: -0.15rem;
        min-width: 1.1rem;
        height: 1.1rem;
        padding: 0 0.3rem;
        border-radius: 999px;
        background: var(--brand-danger);
        color: #fff;
        font-size: 0.65rem;
        font-weight: 700;
        line-height: 1.1rem;
        text-align: center;
    }

    .pn-menu {
        width: 23rem;
        max-width: calc(100vw - 2rem);
        padding: 0;
        overflow: hidden;
        background-color: var(--brand-surface-alt);
        border: 1px solid var(--brand-border);
        border-radius: 0.85rem;
        box-shadow: var(--brand-shadow-lifted);
    }

    .pn-header {
        padding: 0.7rem 1rem;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--brand-text);
        border-bottom: 1px solid var(--brand-border);
    }

    .pn-list {
        max-height: 24rem;
        overflow-y: auto;
    }

    .pn-item {
        display: block;
        padding: 0.7rem 1rem;
        border-bottom: 1px solid var(--brand-border);
        color: var(--brand-text);
        text-decoration: none;
        cursor: pointer;
    }

    .pn-item:last-child { border-bottom: 0; }
    .pn-item:hover { background-color: var(--brand-accent-tint); }
    .pn-item.is-unread { background-color: color-mix(in srgb, var(--brand-accent) 8%, transparent); }

    .pn-title {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .pn-dot {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 50%;
        background: var(--brand-accent);
        flex-shrink: 0;
    }

    .pn-message {
        margin-top: 0.15rem;
        font-size: 0.8rem;
        line-height: 1.35;
    }

    .pn-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-top: 0.4rem;
    }

    .pn-date {
        font-size: 0.72rem;
        color: var(--brand-text-muted, var(--brand-text));
        opacity: 0.75;
    }

    .pn-empty {
        padding: 1.5rem 1rem;
        text-align: center;
        font-size: 0.85rem;
        color: var(--brand-text-muted, var(--brand-text));
    }
</style>

<script>
    (function () {
        var badge = document.getElementById('pn-badge');
        var list = document.getElementById('pn-list');
        if (!badge || !list) return;

        var indexUrl = @json(route('panelist.notifications.index'));
        var readUrl = @json(route('panelist.notifications.read', ['notification' => '__ID__']));
        var csrf = @json(csrf_token());

        function markRead(id) {
            fetch(readUrl.replace('__ID__', id), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                keepalive: true
            }).catch(function () {});
        }

        function el(tag, className, text) {
            var node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        }

        function render(data) {
            badge.textContent = data.unreadCount > 99 ? '99+' : data.unreadCount;
            badge.classList.toggle('d-none', !data.unreadCount);

            list.textContent = '';

            if (!data.items.length) {
                list.appendChild(el('div', 'pn-empty', 'No notifications yet.'));
                return;
            }

            data.items.forEach(function (item) {
                var row = el('div', 'pn-item' + (item.read ? '' : ' is-unread'));

                var title = el('div', 'pn-title');
                if (!item.read) title.appendChild(el('span', 'pn-dot'));
                title.appendChild(el('span', '', item.title));
                row.appendChild(title);
                row.appendChild(el('div', 'pn-message', item.message));

                var foot = el('div', 'pn-foot');
                foot.appendChild(el('span', 'pn-date', item.date));

                if (item.link) {
                    var view = el('a', 'btn btn-outline-brand btn-sm', 'View');
                    view.href = item.link;
                    view.addEventListener('click', function () { if (!item.read) markRead(item.id); });
                    foot.appendChild(view);
                }

                row.appendChild(foot);

                row.addEventListener('click', function (event) {
                    if (event.target.closest('a')) return;
                    if (item.read) return;
                    item.read = true;
                    row.classList.remove('is-unread');
                    var dot = row.querySelector('.pn-dot');
                    if (dot) dot.remove();
                    markRead(item.id);
                    var remaining = Math.max(0, (parseInt(badge.textContent, 10) || 0) - 1);
                    badge.textContent = remaining;
                    badge.classList.toggle('d-none', remaining === 0);
                });

                list.appendChild(row);
            });
        }

        function load() {
            fetch(indexUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) { if (data) render(data); })
                .catch(function () {});
        }

        load();
        setInterval(load, 20000);
    })();
</script>
