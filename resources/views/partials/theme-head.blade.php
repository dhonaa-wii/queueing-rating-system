<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@1,400&display=swap" rel="stylesheet">
<script>
    (function () {
        var stored = localStorage.getItem('theme');
        var theme = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-bs-theme', theme);
    })();
</script>
<style>
    :root {
        scroll-behavior: smooth;
    }

    /* Scale the base rem size down on smaller screens so text, spacing, and
       — most visibly — table cells shrink proportionally instead of a
       desktop-sized table just getting cramped/overflowing on a phone. */
    @media (max-width: 767.98px) {
        html { font-size: 15px; }
    }

    @media (max-width: 575.98px) {
        html { font-size: 13px; }

        .table th,
        .table td {
            padding: 0.4rem 0.5rem;
        }
    }

    [data-bs-theme="light"] {
        --brand-bg: #FDFBF9;
        --brand-text: #2B2926;
        --brand-muted: #6B6560;
        --brand-accent: #C1712E;
        --brand-accent-contrast: #FFFFFF;
        --brand-accent-tint: #FBEEE1;
        --brand-border: #E8E1D9;
        /* Checkbox/radio outlines only — deliberately darker than
           --brand-border, which is tuned for large surfaces (cards, table
           rules) and is far too faint on a 1em control. */
        --brand-control-border: #8B8279;
        --brand-surface: #FFFFFF;
        --brand-surface-alt: #F8F3EE;
        --brand-shadow: 0 1px 2px rgba(43, 41, 38, 0.06), 0 4px 12px rgba(43, 41, 38, 0.04);
        --brand-shadow-lifted: 0 2px 4px rgba(43, 41, 38, 0.06), 0 12px 28px rgba(43, 41, 38, 0.10);
        --brand-success: #2E7D4F;
        --brand-success-tint: #E7F3EC;
        --brand-danger: #B3441E;
        --brand-danger-tint: #FBEAE3;
        --brand-info: #2E6B8A;
        --brand-info-tint: #E8F1F6;
        /* Solid buttons shade toward this on hover: darker on light, brighter on dark. */
        --brand-btn-shade: #000000;
    }

    [data-bs-theme="dark"] {
        --brand-bg: #201D1A;
        --brand-text: #F2EDE8;
        --brand-muted: #B3AA9F;
        --brand-accent: #E08A45;
        --brand-accent-contrast: #201D1A;
        --brand-accent-tint: rgba(224, 138, 69, 0.14);
        --brand-border: #3A342D;
        /* Dark theme inverts the relationship — the outline has to be
           lighter than the surface to read as a line at all. */
        --brand-control-border: #8A8076;
        --brand-surface: #2A2622;
        --brand-surface-alt: #25221E;
        --brand-shadow: 0 1px 2px rgba(0, 0, 0, 0.2), 0 4px 16px rgba(0, 0, 0, 0.24);
        --brand-shadow-lifted: 0 2px 6px rgba(0, 0, 0, 0.28), 0 14px 32px rgba(0, 0, 0, 0.36);
        --brand-success: #5FBE8A;
        --brand-success-tint: rgba(95, 190, 138, 0.14);
        --brand-danger: #E28362;
        --brand-danger-tint: rgba(226, 131, 98, 0.14);
        --brand-info: #6BB4D6;
        --brand-info-tint: rgba(107, 180, 214, 0.14);
        --brand-btn-shade: #FFFFFF;
    }

    body {
        background-color: var(--brand-bg);
        color: var(--brand-text);
    }

    .navbar-brand-mark {
        font-family: 'Montserrat', sans-serif;
        font-weight: 400;
        font-style: italic;
        letter-spacing: 0.04em;
        color: var(--brand-accent);
    }

    /* Brand buttons — borderless. Solid variants darken and lift on hover;
       the "outline" variants (kept under their old class names so every
       existing view picks this up) are soft tints that deepen on hover, and
       the destructive/success tints fill solid. Colours go through
       Bootstrap's --bs-btn-* variables so :hover, :active, .btn-check
       selection and :disabled all stay in step. */
    .btn-brand,
    .btn-outline-brand,
    .btn-outline-danger-brand,
    .btn-success-brand,
    .btn-outline-success-brand {
        --bs-btn-border-color: transparent;
        --bs-btn-hover-border-color: transparent;
        --bs-btn-active-border-color: transparent;
        --bs-btn-disabled-border-color: transparent;
        --bs-btn-disabled-opacity: 0.5;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45em;
        border-color: transparent;
        border-radius: 0.55rem;
        font-weight: 600;
        transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    }

    .btn-brand > svg,
    .btn-outline-brand > svg,
    .btn-outline-danger-brand > svg,
    .btn-success-brand > svg,
    .btn-outline-success-brand > svg {
        width: 1.05em;
        height: 1.05em;
        flex-shrink: 0;
    }

    .btn-brand {
        --bs-btn-bg: var(--brand-accent);
        --bs-btn-color: var(--brand-accent-contrast);
        --bs-btn-hover-bg: color-mix(in srgb, var(--brand-accent) 86%, var(--brand-btn-shade));
        --bs-btn-hover-color: var(--brand-accent-contrast);
        --bs-btn-active-bg: color-mix(in srgb, var(--brand-accent) 78%, var(--brand-btn-shade));
        --bs-btn-active-color: var(--brand-accent-contrast);
        --bs-btn-disabled-bg: var(--brand-accent);
        --bs-btn-disabled-color: var(--brand-accent-contrast);
    }

    .btn-success-brand {
        --bs-btn-bg: var(--brand-success);
        --bs-btn-color: var(--brand-accent-contrast);
        --bs-btn-hover-bg: color-mix(in srgb, var(--brand-success) 86%, var(--brand-btn-shade));
        --bs-btn-hover-color: var(--brand-accent-contrast);
        --bs-btn-active-bg: color-mix(in srgb, var(--brand-success) 78%, var(--brand-btn-shade));
        --bs-btn-active-color: var(--brand-accent-contrast);
        --bs-btn-disabled-bg: var(--brand-success);
        --bs-btn-disabled-color: var(--brand-accent-contrast);
    }

    .btn-outline-brand {
        --bs-btn-bg: var(--brand-accent-tint);
        --bs-btn-color: var(--brand-accent);
        --bs-btn-hover-bg: color-mix(in srgb, var(--brand-accent) 22%, transparent);
        --bs-btn-hover-color: var(--brand-accent);
        /* Also the selected state of a .btn-check segmented toggle. */
        --bs-btn-active-bg: var(--brand-accent);
        --bs-btn-active-color: var(--brand-accent-contrast);
        --bs-btn-disabled-bg: var(--brand-accent-tint);
        --bs-btn-disabled-color: var(--brand-accent);
    }

    .btn-outline-danger-brand {
        --bs-btn-bg: var(--brand-danger-tint);
        --bs-btn-color: var(--brand-danger);
        --bs-btn-hover-bg: var(--brand-danger);
        --bs-btn-hover-color: var(--brand-accent-contrast);
        --bs-btn-active-bg: color-mix(in srgb, var(--brand-danger) 85%, var(--brand-btn-shade));
        --bs-btn-active-color: var(--brand-accent-contrast);
        --bs-btn-disabled-bg: var(--brand-danger-tint);
        --bs-btn-disabled-color: var(--brand-danger);
    }

    .btn-outline-success-brand {
        --bs-btn-bg: var(--brand-success-tint);
        --bs-btn-color: var(--brand-success);
        --bs-btn-hover-bg: var(--brand-success);
        --bs-btn-hover-color: var(--brand-accent-contrast);
        --bs-btn-active-bg: color-mix(in srgb, var(--brand-success) 85%, var(--brand-btn-shade));
        --bs-btn-active-color: var(--brand-accent-contrast);
        --bs-btn-disabled-bg: var(--brand-success-tint);
        --bs-btn-disabled-color: var(--brand-success);
    }

    .btn-brand:not(:disabled):not(.disabled):hover,
    .btn-outline-brand:not(:disabled):not(.disabled):hover,
    .btn-outline-danger-brand:not(:disabled):not(.disabled):hover,
    .btn-success-brand:not(:disabled):not(.disabled):hover,
    .btn-outline-success-brand:not(:disabled):not(.disabled):hover {
        transform: translateY(-1px);
    }

    .btn-brand:not(:disabled):not(.disabled):hover { box-shadow: 0 6px 16px -6px color-mix(in srgb, var(--brand-accent) 75%, transparent); }
    .btn-success-brand:not(:disabled):not(.disabled):hover { box-shadow: 0 6px 16px -6px color-mix(in srgb, var(--brand-success) 75%, transparent); }
    .btn-outline-danger-brand:not(:disabled):not(.disabled):hover { box-shadow: 0 6px 16px -6px color-mix(in srgb, var(--brand-danger) 70%, transparent); }
    .btn-outline-success-brand:not(:disabled):not(.disabled):hover { box-shadow: 0 6px 16px -6px color-mix(in srgb, var(--brand-success) 70%, transparent); }

    .btn-brand:active,
    .btn-outline-brand:active,
    .btn-outline-danger-brand:active,
    .btn-success-brand:active,
    .btn-outline-success-brand:active {
        transform: none !important;
        box-shadow: none !important;
    }

    .btn-brand:focus-visible,
    .btn-outline-brand:focus-visible,
    .btn-outline-danger-brand:focus-visible,
    .btn-success-brand:focus-visible,
    .btn-outline-success-brand:focus-visible,
    .btn-check:focus-visible + .btn-outline-brand {
        outline: 0;
        box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--brand-accent) 35%, transparent);
    }

    .btn-brand:disabled,
    .btn-outline-brand:disabled,
    .btn-outline-danger-brand:disabled,
    .btn-success-brand:disabled,
    .btn-outline-success-brand:disabled {
        cursor: not-allowed;
        pointer-events: auto;
    }

    /* Presentation Control action grid (room-session Lead terminal) — big
       touch-friendly pill buttons with an icon, meant to read at a glance
       from arm's length on a tablet rather than as a row of plain
       Bootstrap-default buttons. */
    .control-btn-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
    }

    .control-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.78rem;
        line-height: 1.1;
        transition: transform 0.12s ease, box-shadow 0.12s ease, opacity 0.12s ease;
        box-shadow: var(--brand-shadow);
    }

    .control-btn svg {
        width: 15px;
        height: 15px;
        flex-shrink: 0;
    }

    .control-btn:not(:disabled):hover,
    .control-btn:not(:disabled):focus {
        transform: translateY(-1px);
    }

    .control-btn:disabled {
        opacity: 0.4;
        box-shadow: none;
        cursor: not-allowed;
        transform: none;
    }

    .control-btn-primary {
        flex: 1 1 auto;
        min-width: 8rem;
        padding: 0.6rem 0.9rem;
        font-size: 0.85rem;
        border-radius: 0.75rem;
    }

    .control-btn-primary svg {
        width: 17px;
        height: 17px;
    }

    /* Room Session "View All" queue panel (room-session home.blade.php) —
       hidden by default, slides in from the right. It's fine for this to
       cover the Presentation Control card when open (same width as the
       right column), but it must never reach into the left column, so the
       QR/login card there stays clickable the whole time with no extra JS
       guard needed.

       User-corrected 2026-08-22: this used to be `position: absolute;
       inset: 0` against the right column (#right-column) itself, which
       meant its height tracked that column's *content* height — once
       connected, that column scrolls with the page, and its content can be
       shorter than the actual screen, leaving the panel not reaching the
       bottom of the visible viewport. Switched to `position: fixed` (so it
       stays put, anchored to the real viewport, if the page happens to
       scroll while it's open) with `top`/`height` set at runtime
       (home.blade.php's view-all script, updateViewAllOverlayBounds()) to
       the panel's current on-screen top down to the bottom of the visible
       screen — not the full 100vh from the very top of the page, per
       user correction: "from the current position to the lowest part of
       the screen." A fixed element's percentage width also resolves
       against the viewport, not #right-column, so `left`/`width` are set
       the same way, to match #right-column's live bounding rect and keep
       the same width as before this fix. top/height/left/width below are
       just a sane pre-JS fallback (never visibly used — the panel starts
       off-screen via translateX(100%) either way). */
    .view-all-overlay {
        position: fixed;
        top: 0;
        height: 100vh;
        height: 100dvh;
        z-index: 1045;
        pointer-events: none;
        /* The closed panel sits just off the right edge via translateX(100%)
           — without clipping here, that off-screen box still counted toward
           this element's positioned ancestor's scrollable width, so the
           "hidden" panel was reachable by scrolling the page sideways. */
        overflow: hidden;
    }

    .view-all-overlay-backdrop {
        position: absolute;
        inset: 0;
        background-color: rgba(0, 0, 0, 0.35);
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    /* Room Session View All table — deliberately smaller/denser than the
       app's default .table, and with real grid lines (not just the
       default's bottom-only dividers) since it packs several columns of
       paper-schedule-style detail into a panel that has to stay scrollable
       rather than grow huge. */
    .view-all-table {
        font-size: 0.62rem;
    }

    .view-all-table th,
    .view-all-table td {
        border: 1px solid var(--brand-border);
        padding: 0.3rem 0.4rem;
        vertical-align: top;
    }

    .view-all-table thead th {
        font-size: 0.58rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: var(--brand-muted);
        background-color: var(--brand-surface-alt);
        white-space: nowrap;
    }

    /* Expected Time column — start/end stacked on two lines instead of one
       wide "start–end" line, so the column can stay narrow. */
    .view-all-time-col {
        width: 1%;
        white-space: nowrap;
        text-align: center;
    }

    /* A room's break inside a schedule list. Cells are tinted (the row's own
       background is painted over by table cell backgrounds) so it reads as a
       pause in the timeline rather than another group. */
    .schedule-break-row > td {
        background-color: var(--brand-accent-tint);
        color: var(--brand-text);
        vertical-align: middle;
    }

    .view-all-overlay-panel {
        position: absolute;
        top: 0;
        right: 0;
        height: 100%;
        width: 100%;
        background-color: var(--brand-surface);
        border-left: 1px solid var(--brand-border);
        box-shadow: var(--brand-shadow);
        padding: 1.25rem;
        overflow-y: auto;
        transform: translateX(100%);
        transition: transform 0.25s ease;
    }

    .view-all-overlay.is-open {
        pointer-events: auto;
    }

    .view-all-overlay.is-open .view-all-overlay-backdrop {
        opacity: 1;
    }

    .view-all-overlay.is-open .view-all-overlay-panel {
        transform: translateX(0);
    }

    .timer-display {
        font-variant-numeric: tabular-nums;
        font-size: 2.25rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    .row-actions-btn {
        border: 1px solid transparent;
        background: transparent;
        color: var(--brand-muted);
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .row-actions-btn svg {
        width: 1.1rem;
        height: 1.1rem;
    }

    .row-actions-btn:hover,
    .row-actions-btn:focus {
        background-color: var(--brand-surface-alt);
        color: var(--brand-text);
        border-color: var(--brand-border);
    }

    .dropdown-menu {
        background-color: var(--brand-surface);
        border-color: var(--brand-border);
        box-shadow: var(--brand-shadow);
    }

    .dropdown-item {
        color: var(--brand-text);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .dropdown-item:hover,
    .dropdown-item:focus {
        background-color: var(--brand-accent-tint);
        color: var(--brand-accent);
    }

    /* The icon component carries no width/height of its own — it is sized by
       whatever it sits in, and .btn-* only sizes its own direct children. A
       menu item is not a .btn-*, so an icon dropped into one renders at the SVG
       default of 300x150 until this rule catches it. Matches
       .dropdown-item-icon, which some views still set explicitly. */
    .dropdown-item > svg {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
    }

    /* Small circular "i" beside a heading that opens that section's help. */
    .info-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.4rem;
        height: 1.4rem;
        padding: 0;
        border: 0;
        border-radius: 50%;
        background: transparent;
        color: var(--brand-accent);
        cursor: pointer;
        transition: color 0.15s ease, background-color 0.15s ease;
    }
    .info-icon-btn > svg {
        width: 0.95rem;
        height: 0.95rem;
    }
    .info-icon-btn:hover,
    .info-icon-btn:focus-visible {
        color: var(--brand-accent);
        background-color: var(--brand-accent-tint);
        outline: none;
    }
    .info-icon-btn:focus-visible {
        box-shadow: 0 0 0 0.2rem var(--brand-accent-tint);
    }

    .dropdown-item.text-danger-brand {
        color: var(--brand-danger);
    }

    .dropdown-item.text-danger-brand:hover,
    .dropdown-item.text-danger-brand:focus {
        background-color: var(--brand-danger-tint);
        color: var(--brand-danger);
    }

    .dropdown-item-icon {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
    }

    dl.detail-list {
        margin-bottom: 0;
    }

    dl.detail-list dt {
        color: var(--brand-muted);
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        font-weight: 600;
    }

    dl.detail-list dd {
        margin-bottom: 0.85rem;
    }

    .settings-icon-btn {
        border: 1px solid transparent;
        background: var(--brand-surface-alt);
        color: var(--brand-text);
        transition: background-color 0.15s ease, color 0.15s ease, transform 0.15s ease;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .settings-icon-btn:hover {
        background-color: var(--brand-accent-tint);
        color: var(--brand-accent);
        transform: translateY(-1px);
    }

    .settings-icon-btn svg {
        width: 1.15rem;
        height: 1.15rem;
    }

    /* Topbar link to the Help Center (partials/help-link). A labelled pill on
       wide screens, the same round icon as the gear/bell on narrow ones. */
    .help-link {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        height: 2.5rem;
        padding: 0 0.95rem 0 0.8rem;
        border-radius: 999px;
        background: var(--brand-surface-alt);
        color: var(--brand-text);
        font-size: 0.85rem;
        font-weight: 500;
        text-decoration: none;
        white-space: nowrap;
        flex-shrink: 0;
        transition: background-color 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }
    .help-link svg { width: 1.15rem; height: 1.15rem; flex-shrink: 0; }
    .help-link:hover,
    .help-link:focus-visible {
        background-color: var(--brand-accent-tint);
        color: var(--brand-accent);
        transform: translateY(-1px);
    }
    .help-link.is-compact { height: 2.1rem; font-size: 0.8rem; }
    @media (max-width: 767.98px) {
        .help-link { width: 2.5rem; padding: 0; justify-content: center; }
        .help-link-label { display: none; }
    }

    /* Named for the topbar notification bell this started out in (removed
       2026-09-22), but kept because five other screens reuse it as the
       system's generic tab strip — Group & Panel Assignment's roster tabs,
       the Admin/Super Admin panelist drawers, and Panelist My Assignments. */
    .notification-tabs {
        flex-shrink: 0;
        display: flex;
        border-bottom: 1px solid var(--brand-border);
        padding: 0 0.5rem;
    }

    .notification-tab {
        flex: 1;
        background: none;
        border: none;
        padding: 0.55rem 0;
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--brand-muted);
        border-bottom: 2px solid transparent;
        cursor: pointer;
    }

    .notification-tab.active {
        color: var(--brand-accent);
        font-weight: 600;
        border-bottom-color: var(--brand-accent);
    }

    .theme-toggle {
        border: 1px solid var(--brand-border);
        background: transparent;
        color: var(--brand-text);
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .theme-toggle svg {
        width: 1.1rem;
        height: 1.1rem;
    }

    [data-bs-theme="dark"] .icon-sun,
    [data-bs-theme="light"] .icon-moon {
        display: none;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        text-decoration: none;
        color: var(--brand-muted);
        font-size: 0.85rem;
    }

    .back-link:hover,
    .back-link:focus {
        color: var(--brand-accent);
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .text-brand-muted {
        color: var(--brand-muted);
    }

    .text-brand-accent {
        color: var(--brand-accent);
    }

    .card-brand,
    .brand-surface {
        background-color: var(--brand-surface);
        border: 1px solid var(--brand-border);
        border-radius: 0.75rem;
        box-shadow: var(--brand-shadow);
    }

    /* The page-shell column width on its own, without the type/card overrides
       that come with it — for a page that only wants the narrower measure
       (Reports, user-directed 2026-09-17: "reduce the width 15%"). */
    .page-narrow {
        width: 85%;
        margin-inline: auto;
    }

    @media (max-width: 991.98px) {
        .page-narrow {
            width: 100%;
        }
    }
    /* Workspace page shell — shared by Presentation Setup's category
       workspace and Group & Panel Assignment. Centered 85% column (full
       width below lg), fluid type tokens capped so text never gets large,
       and cards on the sidebar's surface. Page-specific extras (compact
       controls etc.) layer on top in each view. */
    .page-shell {
        --page-fs: clamp(0.85rem, 0.78rem + 0.2vw, 0.9rem);
        --page-fs-sm: clamp(0.78rem, 0.73rem + 0.15vw, 0.82rem);
        --page-fs-xs: clamp(0.7rem, 0.67rem + 0.1vw, 0.75rem);
        --page-fs-heading: clamp(0.88rem, 0.82rem + 0.2vw, 0.95rem);
        width: 85%;
        margin-inline: auto;
        font-size: var(--page-fs);
    }

    @media (max-width: 991.98px) {
        .page-shell {
            width: 100%;
        }
    }

    .page-shell .badge {
        font-size: var(--page-fs-xs);
    }

    .page-shell h3.h6 {
        font-size: var(--page-fs-heading);
        font-weight: 600;
    }

    .page-shell .card-brand {
        background-color: var(--brand-surface-alt);
        border-radius: 0.85rem;
        box-shadow: none;
    }

    .page-shell .card-brand .form-control:disabled {
        background-color: transparent;
    }

    .page-shell .card-brand .table {
        --bs-table-bg: transparent;
    }

    .page-shell .form-label,
    .page-shell .form-text,
    .page-shell .form-check-label,
    .page-shell .table {
        font-size: var(--page-fs-sm);
    }

    .page-shell .form-control,
    .page-shell .form-select {
        font-size: var(--page-fs-sm);
    }

    .page-shell .btn {
        --bs-btn-font-size: var(--page-fs-sm);
    }

    .page-shell .btn-sm {
        --bs-btn-font-size: var(--page-fs-xs);
    }

    .page-shell .table thead th {
        font-size: var(--page-fs-xs);
        font-weight: 600;
    }

    .badge-brand-tint {
        background-color: var(--brand-accent-tint);
        color: var(--brand-accent);
        font-weight: 500;
    }

    .badge-success-tint {
        background-color: var(--brand-success-tint);
        color: var(--brand-success);
        font-weight: 500;
    }

    .badge-danger-tint {
        background-color: var(--brand-danger-tint);
        color: var(--brand-danger);
        font-weight: 500;
    }

    .badge-info-tint {
        background-color: var(--brand-info-tint);
        color: var(--brand-info);
        font-weight: 500;
    }

    .badge-muted-tint {
        background-color: var(--brand-surface-alt);
        color: var(--brand-muted);
        font-weight: 500;
    }

    /* Itemised detail inside the shared confirm modal (data-confirm-list) —
       e.g. the groups still queued in a room about to be removed. */
    .confirm-action-list {
        list-style: none;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--brand-border);
        border-radius: 0.5rem;
        background-color: var(--brand-surface-alt);
        max-height: 11rem;
        overflow-y: auto;
        font-size: 0.82rem;
        color: var(--brand-text);
    }

    .confirm-action-list li + li {
        margin-top: 0.25rem;
    }

    /* ---------- Category picker ----------
       The three category-scoped admin modules (Group & Panel Assignment,
       Event Control, Reports) each open straight on a category rather than
       on a grid of cards, and Panelist Schedule Viewing does the same, so
       switching category is this one dropdown, built from
       partials/category-picker-dropdown.blade.php. The button says only
       "Category": the current one's name is already the page heading beside
       it, so labelling the button with it would print the same string twice
       on one line. */
    .category-picker-menu {
        min-width: 16rem;
        max-height: 19rem;
        overflow-y: auto;
    }

    .category-picker-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.85rem;
    }

    .category-picker-item-name {
        font-size: 0.85rem;
        font-weight: 600;
    }

    .category-picker-item-meta {
        font-size: 0.72rem;
        color: var(--brand-muted);
    }

    /* Bootstrap's own .active is its default blue, which has no place in this
       theme — the current category uses the accent tint the rest of the menu
       already hovers to. */
    .category-picker-item.active {
        background-color: var(--brand-accent-tint);
        color: var(--brand-accent);
    }

    .category-picker-item.active .category-picker-item-meta {
        color: inherit;
        opacity: 0.75;
    }

    .category-picker-item-icon {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
    }
    /* Empty state shared by those same three pages, for the case where none
       of their categories qualifies yet and there is nothing to open. */
    .picker-empty {
        padding: 3.25rem 1.5rem;
        border: 1px dashed var(--brand-border);
        border-radius: 0.9rem;
        background-color: var(--brand-surface);
        text-align: center;
    }

    .picker-empty h2 {
        margin: 0 0 0.4rem;
        font-size: 1rem;
        font-weight: 600;
        color: var(--brand-text);
    }

    .picker-empty p {
        margin: 0 auto;
        max-width: 34rem;
        font-size: 0.85rem;
        color: var(--brand-muted);
    }

    hr.brand-divider {
        border-top: 1px solid var(--brand-border);
        opacity: 1;
    }

    .text-brand-success { color: var(--brand-success); }
    .text-brand-danger { color: var(--brand-danger); }
    .text-brand-info { color: var(--brand-info); }

    .bg-brand-accent { background-color: var(--brand-accent); color: var(--brand-accent-contrast); }
    .bg-brand-success { background-color: var(--brand-success); color: #FFFFFF; }
    .bg-brand-danger { background-color: var(--brand-danger); color: #FFFFFF; }
    .bg-brand-info { background-color: var(--brand-info); color: #FFFFFF; }
    .bg-brand-muted { background-color: var(--brand-muted); color: var(--brand-surface); }

    /* Category card action buttons: a solid primary action, a bordered
       secondary action, a plain ghost action, and a solid destructive
       action — icon + label, sized for a card footer. */
    .btn-card-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.22rem;
        padding: 0.34rem 0.38rem;
        border-radius: 0.45rem;
        border: 1px solid transparent;
        font-size: 0.69rem;
        font-weight: 600;
        line-height: 1.2;
        text-decoration: none;
        white-space: nowrap;
        flex: 1 1 auto;
        min-width: 0;
        transition: opacity 0.12s ease, transform 0.12s ease, background-color 0.15s ease, box-shadow 0.15s ease;
    }

    .category-card-actions {
        display: flex;
        flex-wrap: nowrap;
        gap: 0.2rem;
    }

    .btn-card-action svg {
        width: 12px;
        height: 12px;
        flex-shrink: 0;
    }

    .btn-card-action:not(:disabled):hover,
    .btn-card-action:not(:disabled):focus {
        opacity: 0.85;
        transform: translateY(-1px);
    }

    .btn-card-solid {
        background-color: var(--brand-accent);
        border-color: var(--brand-accent);
        color: var(--brand-accent-contrast);
    }

    .btn-card-solid:not(:disabled):hover,
    .btn-card-solid:not(:disabled):focus {
        background-color: color-mix(in srgb, var(--brand-accent) 86%, var(--brand-btn-shade));
        border-color: transparent;
        color: var(--brand-accent-contrast);
        box-shadow: 0 6px 14px -6px color-mix(in srgb, var(--brand-accent) 75%, transparent);
        opacity: 1;
    }

    .btn-card-outline {
        background-color: var(--brand-accent-tint);
        border-color: transparent;
        color: var(--brand-accent);
    }

    .btn-card-outline:not(:disabled):hover,
    .btn-card-outline:not(:disabled):focus {
        background-color: color-mix(in srgb, var(--brand-accent) 22%, transparent);
        opacity: 1;
    }

    .btn-card-ghost {
        background-color: transparent;
        border-color: transparent;
        color: var(--brand-muted);
    }

    .btn-card-ghost:not(:disabled):hover,
    .btn-card-ghost:not(:disabled):focus {
        background-color: var(--brand-surface-alt);
        opacity: 1;
        transform: none;
    }

    .btn-card-danger {
        background-color: var(--brand-danger);
        border-color: var(--brand-danger);
        color: #FFFFFF;
    }

    /* Category card: tinted header fading into a plain body, an icon+badge
       stat grid, and the button row above. */
    .category-card-header {
        background-color: var(--brand-accent-tint);
        border-bottom: 1px solid var(--brand-border);
        padding: 0.7rem 0.85rem;
    }

    .category-card-title {
        font-size: 0.9rem;
        font-weight: 600;
        line-height: 1.25;
    }

    .category-card-meta {
        font-size: 0.72rem;
        color: var(--brand-muted);
        line-height: 1.4;
    }

    .category-card-body {
        padding: 0.7rem 0.85rem 0.8rem;
    }

    .category-card-dates {
        font-size: 0.72rem;
    }

    .category-card-icon {
        width: 1.3rem;
        height: 1.3rem;
        color: var(--brand-accent);
        flex-shrink: 0;
    }

    .category-stat-item {
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }

    .category-stat-icon-wrap {
        position: relative;
        width: 1.6rem;
        height: 1.6rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--brand-accent);
        flex-shrink: 0;
    }

    .category-stat-icon-wrap svg {
        width: 1.15rem;
        height: 1.15rem;
    }

    .category-stat-badge {
        position: absolute;
        bottom: -3px;
        right: -3px;
        width: 0.85rem;
        height: 0.85rem;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--brand-surface);
    }

    .category-stat-badge svg {
        width: 0.48rem;
        height: 0.48rem;
        stroke-width: 3;
    }

    .category-stat-label {
        font-size: 0.68rem;
        color: var(--brand-muted);
        line-height: 1.2;
    }

    .category-stat-value {
        font-size: 0.78rem;
        font-weight: 700;
        line-height: 1.2;
    }

    /* Capacity Analysis card: tinted header (icon + title + status pill),
       an icon-per-metric stat grid that reflows via auto-fit (so it looks
       right whether the card is full-width or squeezed into a half column),
       and a colored takeaway banner. */
    .capacity-card {
        padding: 0 !important;
        overflow: hidden;
    }

    .capacity-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        background-color: var(--brand-accent-tint);
        border-bottom: 1px solid var(--brand-border);
    }

    .capacity-header-icon {
        width: 2.35rem;
        height: 2.35rem;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.6rem;
        background-color: var(--brand-surface);
        color: var(--brand-accent);
        box-shadow: var(--brand-shadow);
    }

    .capacity-header-icon svg {
        width: 1.3rem;
        height: 1.3rem;
    }

    .capacity-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .capacity-status-pill svg {
        width: 0.85rem;
        height: 0.85rem;
        stroke-width: 3;
        flex-shrink: 0;
    }

    .capacity-stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr));
        gap: 1rem 1.25rem;
        padding: 1.15rem 1.25rem;
    }

    .capacity-stat-item {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        min-width: 0;
    }

    .capacity-stat-icon {
        width: 2.3rem;
        height: 2.3rem;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.55rem;
        background-color: var(--brand-surface-alt);
        color: var(--brand-accent);
    }

    .capacity-stat-icon svg {
        width: 1.2rem;
        height: 1.2rem;
    }

    /* Wraps rather than nowraps: the longest labels ("Min/Remaining Day",
       "Slots/Room/Day") overflowed their grid cell and printed over the
       next stat once a column got narrow — the grid is auto-fit, so that
       happens on any page that gives the card less width. */
    .capacity-stat-label {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--brand-muted);
        line-height: 1.2;
        /* "Duration/Group", "Slots/Room/Day" carry no spaces, so wrapping
           alone can't save them in a narrow cell — this lets them break
           rather than spill over the neighbouring stat. */
        overflow-wrap: anywhere;
    }

    .capacity-stat-value {
        font-size: 1.05rem;
        font-weight: 700;
        line-height: 1.3;
    }

    .capacity-stat-value small {
        font-size: 0.72rem;
        font-weight: 500;
        color: var(--brand-muted);
    }

    /* Event Control room status card (admin live-monitoring date-detail) —
       reuses the .capacity-stat-* icon tiles, laid out inline and wrapping. */
    .room-status-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 0.85rem 1.25rem;
    }

    .room-status-stats .capacity-stat-item {
        flex: 1 1 7rem;
    }

    .room-status-text {
        min-width: 0;
    }

    .room-status-value {
        font-size: 0.95rem;
        font-weight: 700;
        line-height: 1.3;
    }

    .room-status-account {
        padding: 0.85rem 0.95rem;
        border-radius: 0.6rem;
        background-color: var(--brand-surface-alt);
        border: 1px solid var(--brand-border);
    }

    /* Event Control "Room Data" card (admin live-monitoring date-detail).
       Base sizes match the room-session tablet's own info card; --rd-scale
       grows everything on wider screens so it stays readable on a desktop
       monitor instead of staying tablet-small. */
    .room-data {
        --rd-scale: 1;
    }

    @media (min-width: 1200px) {
        .room-data { --rd-scale: 1.2; }
    }

    @media (min-width: 1600px) {
        .room-data { --rd-scale: 1.4; }
    }

    .room-data-title {
        font-size: calc(1rem * var(--rd-scale));
        font-weight: 600;
    }

    .room-data-section {
        padding: calc(0.5rem * var(--rd-scale));
        border-radius: 0.5rem;
        background-color: var(--brand-surface-alt);
        font-size: calc(0.7rem * var(--rd-scale));
    }

    .room-data-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(calc(6.5rem * var(--rd-scale)), 1fr));
        gap: calc(0.5rem * var(--rd-scale)) calc(1rem * var(--rd-scale));
    }

    .room-data-label {
        font-size: calc(0.62rem * var(--rd-scale));
        color: var(--brand-muted);
    }

    .room-data-body {
        font-size: calc(0.6125rem * var(--rd-scale));
    }

    .room-data-sub {
        font-size: calc(0.7rem * var(--rd-scale));
    }

    .room-data-badge {
        font-size: calc(0.525rem * var(--rd-scale));
    }

    .room-data-btn {
        font-size: calc(0.68rem * var(--rd-scale));
        padding: calc(0.15rem * var(--rd-scale)) calc(0.5rem * var(--rd-scale));
    }

    .room-data-next {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .room-data-next-item {
        flex: 1 1 calc(140px * var(--rd-scale));
        min-width: 0;
        padding: calc(0.5rem * var(--rd-scale));
        border-radius: 0.5rem;
        background-color: var(--brand-surface);
        font-size: calc(0.6125rem * var(--rd-scale));
    }

    .capacity-message {
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        margin: 0 1.25rem 1.15rem;
        padding: 0.75rem 0.95rem;
        border-radius: 0.6rem;
        font-size: 0.85rem;
        line-height: 1.4;
        border: 1px solid transparent;
    }

    .capacity-message svg {
        width: 1.1rem;
        height: 1.1rem;
        flex-shrink: 0;
        margin-top: 0.1rem;
    }

    .capacity-empty {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1.5rem 1.25rem;
        color: var(--brand-muted);
    }

    .capacity-empty svg {
        width: 1.75rem;
        height: 1.75rem;
        flex-shrink: 0;
    }

    .form-control,
    .form-select {
        background-color: var(--brand-surface);
        border-color: var(--brand-border);
        color: var(--brand-text);
    }

    .form-control:focus,
    .form-select:focus {
        background-color: var(--brand-surface);
        color: var(--brand-text);
        border-color: var(--brand-accent);
        box-shadow: 0 0 0 0.25rem var(--brand-accent-tint);
    }

    .form-control::placeholder {
        color: var(--brand-muted);
        opacity: 0.7;
    }

    .form-control:disabled,
    .form-select:disabled {
        background-color: var(--brand-surface-alt);
        color: var(--brand-muted);
    }

    /* Bootstrap ships these at --bs-border-color (#dee2e6), which all but
       disappears against this palette's surfaces. */
    .form-check-input {
        border-color: var(--brand-control-border);
        border-width: 1.5px;
        background-color: var(--brand-surface);
    }

    .form-check-input:focus {
        border-color: var(--brand-accent);
        box-shadow: 0 0 0 0.2rem var(--brand-accent-tint);
    }

    .nav-tabs {
        border-color: var(--brand-border);
    }

    .nav-tabs .nav-link {
        color: var(--brand-muted);
        border: 1px solid transparent;
    }

    .nav-tabs .nav-link.active {
        color: var(--brand-accent);
        background-color: var(--brand-surface);
        border-color: var(--brand-border) var(--brand-border) var(--brand-surface);
    }

    .nav-tabs .nav-link:hover:not(.active) {
        border-color: transparent;
        color: var(--brand-accent);
    }

    .table {
        color: var(--brand-text);
    }

    .table > :not(caption) > * > * {
        border-bottom-color: var(--brand-border);
    }

    a {
        color: var(--brand-accent);
    }

    /* ---------- Modals (system-wide) ----------
       Sidebar-surface panel, fluid type capped small, compact controls.
       Driven through Bootstrap 5.3's --bs-modal-* variables so every modal
       in every layout picks it up with no per-view markup. Modals with a
       deliberately different look (the panelist QR scanner's dark camera
       panel) keep it by setting their own background on .modal-content. */
    .modal {
        --modal-fs: clamp(0.84rem, 0.79rem + 0.18vw, 0.9rem);
        --modal-fs-sm: clamp(0.78rem, 0.74rem + 0.14vw, 0.83rem);
        --modal-fs-xs: clamp(0.7rem, 0.68rem + 0.1vw, 0.75rem);
        --modal-fs-title: clamp(0.95rem, 0.89rem + 0.25vw, 1.06rem);
        --modal-pad-x: clamp(0.95rem, 0.75rem + 0.6vw, 1.35rem);
        --bs-modal-margin: 0.75rem;
        --bs-modal-color: var(--brand-text);
        --bs-modal-bg: var(--brand-surface-alt);
        --bs-modal-border-color: var(--brand-border);
        --bs-modal-border-radius: 1rem;
        --bs-modal-inner-border-radius: calc(1rem - 1px);
        --bs-modal-box-shadow: 0 1.5rem 3.5rem -1rem rgba(0, 0, 0, 0.28);
        --bs-modal-padding: 0.95rem var(--modal-pad-x);
        --bs-modal-header-padding: 0.85rem var(--modal-pad-x);
        --bs-modal-header-border-color: var(--brand-border);
        --bs-modal-footer-border-color: var(--brand-border);
        --bs-modal-footer-gap: 0.5rem;
        --bs-modal-title-line-height: 1.3;
    }

    @media (min-width: 576px) {
        .modal {
            --bs-modal-margin: 1.5rem;
        }
    }

    .modal-backdrop {
        --bs-backdrop-bg: #140f0a;
        --bs-backdrop-opacity: 0.45;
    }

    .modal-backdrop.show {
        backdrop-filter: blur(2px);
    }

    .modal-content {
        color: var(--brand-text);
        font-size: var(--modal-fs);
        line-height: 1.5;
        box-shadow: var(--bs-modal-box-shadow);
    }

    .modal-header {
        gap: 0.75rem;
    }

    .modal-title {
        font-size: var(--modal-fs-title);
        font-weight: 600;
        letter-spacing: -0.005em;
        color: var(--brand-text);
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .modal-header .btn-close {
        --bs-btn-close-focus-shadow: 0 0 0 0.2rem var(--brand-accent-tint);
        flex-shrink: 0;
        width: 0.65rem;
        height: 0.65rem;
        padding: 0.45rem;
        margin: -0.2rem -0.3rem -0.2rem auto;
        border-radius: 0.5rem;
        background-size: 0.65rem;
        transition: background-color 0.15s ease, opacity 0.15s ease;
    }

    .modal-header .btn-close:hover {
        background-color: var(--brand-accent-tint);
    }

    .modal-body p {
        line-height: 1.55;
    }

    .modal-body p:last-child {
        margin-bottom: 0;
    }

    .modal-body .small,
    .modal-body small {
        font-size: var(--modal-fs-sm);
    }

    .modal-body strong,
    .modal-body b {
        font-weight: 600;
    }

    .modal-body .mb-3 {
        margin-bottom: 0.75rem !important;
    }

    .modal-body .mb-4 {
        margin-bottom: 1rem !important;
    }

    .modal-body .brand-divider.my-4 {
        margin-block: 1rem !important;
    }

    .modal-footer {
        padding: 0.7rem var(--modal-pad-x);
    }

    @media (max-width: 575.98px) {
        .modal-footer {
            justify-content: stretch;
        }

        .modal-footer > .btn,
        .modal-footer > form {
            flex: 1 1 auto;
        }

        .modal-footer > form > .btn {
            width: 100%;
        }
    }

    /* Form controls inside modals — compact padding, small legible type. */
    .modal .form-label {
        font-size: var(--modal-fs-sm);
        font-weight: 500;
        margin-bottom: 0.25rem;
        color: var(--brand-text);
    }

    .modal .form-text,
    .modal .form-check-label {
        font-size: var(--modal-fs-sm);
    }

    .modal .form-text {
        margin-top: 0.2rem;
        color: var(--brand-muted);
    }

    .modal .form-control,
    .modal .form-select {
        font-size: var(--modal-fs-sm);
        padding: 0.3rem 0.6rem;
        line-height: 1.45;
        border-radius: 0.5rem;
    }

    .modal .form-select {
        padding-right: 1.9rem;
        background-position: right 0.55rem center;
        background-size: 12px 9px;
    }

    .modal .form-control:focus,
    .modal .form-select:focus {
        box-shadow: 0 0 0 0.18rem var(--brand-accent-tint);
    }

    .modal .form-control:disabled,
    .modal .form-select:disabled {
        background-color: transparent;
    }

    .modal .form-check {
        min-height: 0;
        margin-bottom: 0.3rem;
    }

    .modal .btn {
        --bs-btn-font-size: var(--modal-fs-sm);
        --bs-btn-padding-y: 0.32rem;
        --bs-btn-padding-x: 0.8rem;
        --bs-btn-border-radius: 0.5rem;
    }

    .modal .btn-sm {
        --bs-btn-font-size: var(--modal-fs-xs);
        --bs-btn-padding-y: 0.22rem;
        --bs-btn-padding-x: 0.55rem;
        --bs-btn-border-radius: 0.45rem;
    }

    .modal .badge {
        font-size: var(--modal-fs-xs);
    }

    .modal .table {
        --bs-table-bg: transparent;
        font-size: var(--modal-fs-sm);
    }

    .modal .table thead th {
        font-size: var(--modal-fs-xs);
        font-weight: 600;
        color: var(--brand-muted);
    }

    .modal .alert {
        font-size: var(--modal-fs-sm);
        padding: 0.5rem 0.75rem;
        border-radius: 0.55rem;
    }

    /* Anything that paints itself in the sidebar surface would disappear
       into the modal (and a sidebar-colored card) — lift it one step. */
    .modal .badge-muted-tint,
    .modal .confirm-action-list,
    .modal .sheet-pager-btn,
    .page-shell .card-brand .badge-muted-tint {
        background-color: var(--brand-surface);
    }

    .modal .card-brand {
        background-color: var(--brand-surface);
    }

    .app-toast-stack {
        position: fixed;
        top: 4.5rem;
        right: 1rem;
        z-index: 1080;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        max-width: 360px;
    }
    .app-toast {
        background-color: var(--brand-surface);
        border: 1px solid var(--brand-border);
        border-left: 4px solid var(--brand-success, #198754);
        box-shadow: var(--brand-shadow, 0 4px 12px rgba(0, 0, 0, 0.12));
        border-radius: 0.5rem;
        padding: 0.65rem 0.9rem;
        font-size: 0.85rem;
        color: var(--brand-text);
        animation: app-toast-in 0.15s ease;
    }
    .app-toast.is-error { border-left-color: var(--brand-danger, #dc3545); }
    @keyframes app-toast-in {
        from { opacity: 0; transform: translateY(-6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Blink highlight for the exact element a notification points at
       (partials/focus-flash-script.blade.php adds and removes the class).
       The tint is an inset shadow rather than a background change so the
       element keeps its own background once the animation ends. */
    .focus-flash {
        border-color: var(--brand-danger) !important;
        animation: focus-flash 0.9s ease-in-out 3;
    }

    tr.focus-flash { animation: none; }
    tr.focus-flash > td { animation: focus-flash-cell 0.9s ease-in-out 3; }

    @keyframes focus-flash {
        0%, 100% { box-shadow: 0 0 0 0 transparent, inset 0 0 0 100vmax transparent; }
        50% {
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--brand-danger) 30%, transparent),
                        0 0 18px 2px color-mix(in srgb, var(--brand-danger) 25%, transparent),
                        inset 0 0 0 100vmax var(--brand-danger-tint);
        }
    }

    @keyframes focus-flash-cell {
        0%, 100% { box-shadow: inset 0 0 0 100vmax transparent; }
        50% { box-shadow: inset 0 0 0 100vmax var(--brand-danger-tint); }
    }

    /* Border glow variant (data-focus-glow) — the accent outline breathes,
       nothing is tinted. Used where a page should point at newly arrived
       rows without looking like an error (Panelist My Assignments). */
    .focus-glow {
        border-color: var(--brand-accent) !important;
        animation: focus-glow 1.2s ease-in-out 5;
    }

    tr.focus-glow { animation: none; }
    tr.focus-glow > td {
        animation: focus-glow-cell 1.2s ease-in-out 5;
        box-shadow: inset 0 2px 0 var(--brand-accent), inset 0 -2px 0 var(--brand-accent);
    }
    tr.focus-glow > td:first-child { box-shadow: inset 2px 0 0 var(--brand-accent), inset 0 2px 0 var(--brand-accent), inset 0 -2px 0 var(--brand-accent); }
    tr.focus-glow > td:last-child { box-shadow: inset -2px 0 0 var(--brand-accent), inset 0 2px 0 var(--brand-accent), inset 0 -2px 0 var(--brand-accent); }

    @keyframes focus-glow {
        0%, 100% { box-shadow: 0 0 0 2px var(--brand-accent), 0 0 4px 0 color-mix(in srgb, var(--brand-accent) 30%, transparent); }
        50% { box-shadow: 0 0 0 2px var(--brand-accent), 0 0 18px 4px color-mix(in srgb, var(--brand-accent) 55%, transparent); }
    }

    @keyframes focus-glow-cell {
        0%, 100% { filter: drop-shadow(0 0 0 transparent); }
        50% { filter: drop-shadow(0 0 6px color-mix(in srgb, var(--brand-accent) 70%, transparent)); }
    }

    @media (prefers-reduced-motion: reduce) {
        .focus-glow, tr.focus-glow > td { animation: none; }
    }

    @media (prefers-reduced-motion: reduce) {
        .focus-flash, tr.focus-flash > td { animation: none; }
        .focus-flash { box-shadow: 0 0 0 4px color-mix(in srgb, var(--brand-danger) 30%, transparent); }
        tr.focus-flash > td { box-shadow: inset 0 0 0 100vmax var(--brand-danger-tint); }
    }
</style>

{{--
    Flexbox `gap` fallback for older tablet browsers.

    Added 2026-09-16 after a photo of a room tablet showed the day-stats strip
    (control-info-card's `d-flex flex-wrap gap-3`) rendering as
    "Registered in CategoryScheduled TodayCompleted Today" — every label butted
    against the next. Flexbox gap only shipped in Chrome/Android WebView 84;
    on anything older every `gap-*` on a flex container is silently dropped,
    while the same property keeps working on grid containers (supported since
    Chrome 66). That asymmetry is why it can't be detected with @supports —
    `CSS.supports('row-gap', '1rem')` answers for grid and returns true — so it
    is measured once at runtime instead, and only the flex cases are patched.

    Margins are applied to all but the last child, so a container that does not
    wrap gains no trailing space. Row spacing is only added for flex-column and
    flex-wrap, where it is what the gap was actually doing.
--}}
<style>
    .no-flex-gap .d-flex.gap-1 > *:not(:last-child) { margin-right: 0.25rem; }
    .no-flex-gap .d-flex.gap-2 > *:not(:last-child) { margin-right: 0.5rem; }
    .no-flex-gap .d-flex.gap-3 > *:not(:last-child) { margin-right: 1rem; }

    .no-flex-gap .d-flex.flex-column.gap-1 > *:not(:last-child) { margin-right: 0; margin-bottom: 0.25rem; }
    .no-flex-gap .d-flex.flex-column.gap-2 > *:not(:last-child) { margin-right: 0; margin-bottom: 0.5rem; }
    .no-flex-gap .d-flex.flex-column.gap-3 > *:not(:last-child) { margin-right: 0; margin-bottom: 1rem; }

    .no-flex-gap .d-flex.flex-wrap.gap-1 > * { margin-bottom: 0.25rem; }
    .no-flex-gap .d-flex.flex-wrap.gap-2 > * { margin-bottom: 0.5rem; }
    .no-flex-gap .d-flex.flex-wrap.gap-3 > * { margin-bottom: 1rem; }
</style>

<script>
    // Measure whether flex gap works, once, and tag <html> so the rules above
    // apply only where it doesn't. Two 1px-tall children in a column flex box
    // with a 1px row-gap make the box 3px tall when gap is honoured, 2px when
    // it is ignored. Runs on DOMContentLoaded because it needs a body to
    // measure in; the probe is removed immediately afterwards.
    (function () {
        function detect() {
            try {
                var probe = document.createElement('div');
                probe.style.cssText = 'display:flex;flex-direction:column;row-gap:1px;position:absolute;visibility:hidden;';
                probe.appendChild(document.createElement('div'));
                probe.appendChild(document.createElement('div'));
                probe.firstChild.style.height = '1px';
                probe.lastChild.style.height = '1px';
                document.body.appendChild(probe);
                var supported = probe.scrollHeight === 3;
                document.body.removeChild(probe);

                if (! supported) {
                    document.documentElement.className += ' no-flex-gap';
                }
            } catch (e) {
                // A browser that throws here is one we can't measure; leave the
                // page exactly as it was rather than guessing.
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', detect);
        } else {
            detect();
        }
    })();
</script>
