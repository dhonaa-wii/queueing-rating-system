{{-- Behaviour for the sidebar toggle; see sidebar-toggle-head. --}}
<script>
    (function () {
        var root = document.documentElement;
        var sidebar = document.getElementById('admin-sidebar');
        var backdrop = document.getElementById('admin-sidebar-backdrop');
        var openBtn = document.getElementById('admin-sidebar-open');
        var closeBtn = document.getElementById('admin-sidebar-close');
        var desktop = window.matchMedia('(min-width: 992px)');

        if (! sidebar) return;

        function sync() {
            var shown = desktop.matches
                ? ! root.classList.contains('sidebar-collapsed')
                : sidebar.classList.contains('is-open');
            [openBtn, closeBtn].forEach(function (btn) {
                if (btn) btn.setAttribute('aria-expanded', shown ? 'true' : 'false');
            });
        }

        function setCollapsed(collapsed) {
            root.classList.toggle('sidebar-collapsed', collapsed);
            try { localStorage.setItem('qrs_sidebar', collapsed ? 'collapsed' : 'open'); } catch (e) {}
            sync();
        }

        function openDrawer() {
            sidebar.classList.add('is-open');
            backdrop.classList.add('is-open');
            sync();
        }

        function closeDrawer() {
            sidebar.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            sync();
        }

        if (openBtn) openBtn.addEventListener('click', function () {
            if (desktop.matches) setCollapsed(false); else openDrawer();
        });
        if (closeBtn) closeBtn.addEventListener('click', function () {
            if (desktop.matches) setCollapsed(true); else closeDrawer();
        });
        backdrop.addEventListener('click', closeDrawer);
        sidebar.querySelectorAll('[data-bs-toggle="modal"]').forEach(function (btn) {
            btn.addEventListener('click', closeDrawer);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && ! desktop.matches && sidebar.classList.contains('is-open')) closeDrawer();
        });
        desktop.addEventListener('change', function () { closeDrawer(); });

        // Pages that size things from the available width (charts, the Assign
        // Panel column) re-measure once the slide has finished.
        sidebar.addEventListener('transitionend', function (e) {
            if (e.propertyName === 'margin-left') window.dispatchEvent(new Event('resize'));
        });

        sync();
    })();
</script>
