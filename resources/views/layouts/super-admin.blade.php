<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Super Administrator') &middot; {{ config('app.name') }}</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    @include('partials.theme-head')
    <style>
        html, body {
            height: 100%;
        }

        body {
            margin: 0;
            overflow: hidden;
        }

        .admin-shell {
            display: flex;
            height: 100vh;
        }

        .admin-sidebar {
            width: 264px;
            flex-shrink: 0;
            height: 100vh;
            background-color: var(--brand-surface-alt);
            border-right: 1px solid var(--brand-border);
            display: flex;
            flex-direction: column;
        }

        .admin-sidebar-brand {
            flex-shrink: 0;
            padding: 1.25rem 1.25rem 1rem;
        }

        .admin-sidebar-brand-text {
            flex: 1;
            text-align: center;
        }

        .admin-sidebar-nav {
            flex: 1;
            overflow: hidden;
            padding: 0.75rem;
        }

        .admin-sidebar-footer {
            flex-shrink: 0;
            padding: 0.75rem;
        }

        .admin-sidebar-footer .admin-nav-link {
            width: 100%;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            font: inherit;
            margin-bottom: 0;
        }

        .admin-nav-link {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.6rem 0.75rem;
            border-radius: 0.5rem;
            color: var(--brand-text);
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 0.15rem;
        }

        .admin-nav-link svg {
            width: 1.1rem;
            height: 1.1rem;
            flex-shrink: 0;
        }

        .admin-nav-link:hover {
            background-color: var(--brand-accent-tint);
            color: var(--brand-accent);
        }

        .admin-nav-link.active {
            background-color: var(--brand-accent-tint);
            color: var(--brand-accent);
            font-weight: 600;
        }

        .admin-main {
            flex: 1;
            min-width: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .admin-topbar {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1.5rem;
            background-color: var(--brand-surface);
        }

        .admin-content {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 1.75rem;
            background-color: var(--brand-surface);
        }

        .admin-user-menu .btn {
            border-color: var(--brand-border);
            color: var(--brand-text);
        }

        @media (max-width: 991.98px) {
            .admin-sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                z-index: 1045;
                transform: translateX(-100%);
                transition: transform 0.2s ease;
            }

            .admin-sidebar.is-open {
                transform: translateX(0);
            }

            .admin-sidebar-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.35);
                z-index: 1040;
            }

            .admin-sidebar-backdrop.is-open {
                display: block;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="admin-shell">
        <div class="admin-sidebar-backdrop" id="admin-sidebar-backdrop"></div>

        <aside class="admin-sidebar" id="admin-sidebar">
            <div class="admin-sidebar-brand d-flex align-items-center justify-content-between">
                <div class="admin-sidebar-brand-text">
                    <span class="navbar-brand-mark fs-5 mb-0 d-block">ARPQRS</span>
                </div>
                <button type="button" class="btn-close d-lg-none" id="admin-sidebar-close" aria-label="Close menu"></button>
            </div>

            <nav class="admin-sidebar-nav">
                <a href="{{ route('super-admin.dashboard') }}" class="admin-nav-link {{ request()->routeIs('super-admin.dashboard') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"></rect><rect x="14" y="3" width="7" height="5" rx="1"></rect><rect x="14" y="12" width="7" height="9" rx="1"></rect><rect x="3" y="16" width="7" height="5" rx="1"></rect></svg>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('super-admin.administrators.index') }}" class="admin-nav-link {{ request()->routeIs('super-admin.administrators.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <span>Admin Accounts</span>
                </a>

                <a href="{{ route('super-admin.panelists.index') }}" class="admin-nav-link {{ request()->routeIs('super-admin.panelists.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span>Panelist Oversight</span>
                </a>

                <a href="{{ route('super-admin.settings.security.index') }}" class="admin-nav-link {{ request()->routeIs('super-admin.settings.security.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    <span>Security Settings</span>
                </a>

                <a href="{{ route('super-admin.audit-logs.index') }}" class="admin-nav-link {{ request()->routeIs('super-admin.audit-logs.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8M8 17h5"></path></svg>
                    <span>Audit Log</span>
                </a>

                <a href="{{ route('super-admin.backups.index') }}" class="admin-nav-link {{ request()->routeIs('super-admin.backups.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                    <span>Backup &amp; Maintenance</span>
                </a>
            </nav>

            <div class="admin-sidebar-footer">
                <button type="button" class="admin-nav-link" data-bs-toggle="modal" data-bs-target="#settings-modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    <span>Settings</span>
                </button>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-brand btn-sm d-lg-none" id="admin-sidebar-open" aria-label="Open menu">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18M3 6h18M3 18h18"></path></svg>
                    </button>
                </div>

            </header>

            <main class="admin-content">
                @if (\App\Support\MaintenanceMode::active())
                    <a href="{{ route('super-admin.backups.index') }}#tab-maintenance"
                       style="display: block; margin-bottom: .75rem; padding: .5rem .85rem; border-radius: .5rem; background: var(--brand-accent-tint); color: var(--brand-accent); font-size: .85rem; font-weight: 500; text-decoration: none;">
                        Maintenance mode is on. Only Super Admins can use the system.
                    </a>
                @endif

                @include('partials.toast-stack')
                @include('partials.credential-reveal-modal')

                @yield('content')
            </main>
        </div>
    </div>

    @include('partials.settings-modal')

    @include('partials.theme-toggle-script')
    <script>
        (function () {
            var sidebar = document.getElementById('admin-sidebar');
            var backdrop = document.getElementById('admin-sidebar-backdrop');
            var openBtn = document.getElementById('admin-sidebar-open');
            var closeBtn = document.getElementById('admin-sidebar-close');

            function open() {
                sidebar.classList.add('is-open');
                backdrop.classList.add('is-open');
            }

            function close() {
                sidebar.classList.remove('is-open');
                backdrop.classList.remove('is-open');
            }

            if (openBtn) openBtn.addEventListener('click', open);
            if (closeBtn) closeBtn.addEventListener('click', close);
            backdrop.addEventListener('click', close);
        })();
    </script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
