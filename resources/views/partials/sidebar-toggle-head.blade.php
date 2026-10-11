{{--
    Sidebar open/close toggle, shared by the Admin, Super Admin and Panelist
    layouts. Include in <head> after theme-head; pair with
    sidebar-toggle-button (the buttons) and sidebar-toggle-script.

    Desktop (lg and up): the sidebar slides out to the left and the page
    widens into its space; a toggle in the top bar brings it back. The choice
    is a per-browser convenience in localStorage.
    Below lg the same two buttons open and close the existing overlay drawer,
    and the saved state is ignored.
--}}
<script>
    // Runs before first paint so a collapsed sidebar never flashes open.
    try {
        if (localStorage.getItem('qrs_sidebar') === 'collapsed') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    } catch (e) {}
</script>
<style>
    .sidebar-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 2rem;
        height: 2rem;
        padding: 0;
        border: 0;
        border-radius: 0.5rem;
        background: transparent;
        color: var(--brand-muted);
        cursor: pointer;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .sidebar-toggle:hover {
        background-color: var(--brand-accent-tint);
        color: var(--brand-text);
    }

    .sidebar-toggle:active {
        transform: scale(0.94);
    }

    .sidebar-toggle:focus-visible {
        outline: 2px solid var(--brand-accent);
        outline-offset: 1px;
    }

    .sidebar-toggle svg {
        width: 1.15rem;
        height: 1.15rem;
    }

    /* Brand on the left, toggle on the right of the sidebar's header row. */
    .admin-sidebar-brand {
        gap: 0.5rem;
        padding-right: 0.85rem !important;
    }

    .admin-sidebar-brand .admin-sidebar-brand-text {
        text-align: left;
        min-width: 0;
    }

    @media (min-width: 992px) {
        .admin-sidebar {
            transition: margin-left 0.24s cubic-bezier(0.2, 0, 0, 1), visibility 0s linear 0s;
        }

        /* Hidden after the slide finishes, so its links leave the tab order. */
        html.sidebar-collapsed .admin-sidebar {
            margin-left: -264px;
            visibility: hidden;
            transition: margin-left 0.24s cubic-bezier(0.2, 0, 0, 1), visibility 0s linear 0.24s;
        }

        .sidebar-toggle-open {
            display: none;
        }

        html.sidebar-collapsed .sidebar-toggle-open {
            display: inline-flex;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .admin-sidebar,
        html.sidebar-collapsed .admin-sidebar {
            transition: none !important;
        }
    }
</style>
