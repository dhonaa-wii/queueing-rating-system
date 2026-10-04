{{-- Help Center styles. Always light (white page, built for reading and for
     printing to PDF), so the tokens are fixed here rather than taken from
     theme-head's light/dark set. The visual language follows Event Control's
     "How Terminals Work" guide: icon tiles, uppercase section labels,
     numbered timelines, option cards, legend panels and tinted notes. --}}
<style>
    :root {
        --hc-text: #2B2926;
        --hc-muted: #6B6560;
        --hc-faint: #9A928A;
        --hc-accent: #C1712E;
        --hc-accent-dark: #A55E22;
        --hc-accent-tint: #FBEEE1;
        --hc-border: #E8E1D9;
        --hc-border-soft: #F1ECE6;
        --hc-surface: #FFFFFF;
        --hc-surface-alt: #F8F3EE;
        --hc-success: #2E7D4F;
        --hc-success-tint: #E7F3EC;
        --hc-danger: #B3441E;
        --hc-danger-tint: #FBEAE3;
        --hc-info: #2E6B8A;
        --hc-info-tint: #E8F1F6;
        --hc-warn: #8A6100;
        --hc-warn-tint: #FFF4D6;
        --hc-radius: 0.85rem;
        --hc-topbar-h: 3.75rem;
        --hc-fs: 0.92rem;
        --hc-fs-sm: 0.84rem;
        --hc-fs-xs: 0.74rem;
        color-scheme: light;
    }

    *, *::before, *::after { box-sizing: border-box; }

    html { scroll-behavior: smooth; scroll-padding-top: calc(var(--hc-topbar-h) + 1rem); }
    @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }

    body {
        margin: 0;
        background: var(--hc-surface);
        color: var(--hc-text);
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        font-size: var(--hc-fs);
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
    }

    a { color: var(--hc-accent); text-decoration: none; }
    a:hover { color: var(--hc-accent-dark); text-decoration: underline; }
    p { margin: 0 0 0.75rem; }
    strong { font-weight: 600; }
    code, kbd {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 0.85em;
        padding: 0.08rem 0.35rem;
        border-radius: 0.3rem;
        background: var(--hc-surface-alt);
        border: 1px solid var(--hc-border);
    }
    kbd { box-shadow: inset 0 -1px 0 var(--hc-border); }
    svg { flex-shrink: 0; }

    .brand-mark {
        font-family: 'Montserrat', sans-serif;
        font-style: italic;
        letter-spacing: 0.04em;
        color: var(--hc-accent);
    }

    /* ---------- Top bar ---------- */
    .hc-topbar {
        position: sticky;
        top: 0;
        z-index: 50;
        height: var(--hc-topbar-h);
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0 1.25rem;
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: saturate(160%) blur(8px);
        border-bottom: 1px solid var(--hc-border);
    }
    .hc-topbar-brand {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        min-width: 0;
        color: var(--hc-text);
        text-decoration: none !important;
    }
    .hc-topbar-brand .brand-mark { font-size: 1.15rem; }
    .hc-topbar-divider { width: 1px; height: 1.3rem; background: var(--hc-border); }
    .hc-topbar-title { font-weight: 600; font-size: 0.95rem; white-space: nowrap; }
    .hc-topbar-actions { margin-left: auto; display: flex; align-items: center; gap: 0.5rem; }

    .hc-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        height: 2.25rem;
        padding: 0 0.95rem;
        border: 0;
        border-radius: 999px;
        font: inherit;
        font-size: var(--hc-fs-sm);
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        text-decoration: none !important;
        transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    }
    .hc-btn svg { width: 1rem; height: 1rem; }
    .hc-btn-solid { background: var(--hc-accent); color: #fff; }
    .hc-btn-solid:hover { background: var(--hc-accent-dark); color: #fff; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(193, 113, 46, 0.25); }
    .hc-btn-soft { background: var(--hc-surface-alt); color: var(--hc-text); }
    .hc-btn-soft:hover { background: var(--hc-accent-tint); color: var(--hc-accent); }
    .hc-btn:focus-visible { outline: 0; box-shadow: 0 0 0 0.2rem rgba(193, 113, 46, 0.35); }

    /* Search */
    .hc-search { position: relative; flex: 0 1 22rem; min-width: 0; margin-left: 1rem; }
    .hc-search-input {
        width: 100%;
        height: 2.25rem;
        padding: 0 2.6rem 0 2.25rem;
        border: 1px solid var(--hc-border);
        border-radius: 999px;
        background: var(--hc-surface-alt);
        font: inherit;
        font-size: var(--hc-fs-sm);
        color: var(--hc-text);
        transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
    }
    .hc-search-input::placeholder { color: var(--hc-faint); }
    .hc-search-input:focus { outline: 0; background: #fff; border-color: var(--hc-accent); box-shadow: 0 0 0 0.2rem rgba(193, 113, 46, 0.15); }
    .hc-search-icon { position: absolute; left: 0.8rem; top: 50%; width: 0.95rem; height: 0.95rem; transform: translateY(-50%); color: var(--hc-faint); pointer-events: none; }
    .hc-search-kbd { position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); font-size: 0.68rem; padding: 0 0.35rem; color: var(--hc-faint); }
    .hc-search-results {
        position: absolute;
        top: calc(100% + 0.4rem);
        left: 0;
        right: 0;
        min-width: 18rem;
        max-height: 22rem;
        overflow-y: auto;
        padding: 0.35rem;
        background: #fff;
        border: 1px solid var(--hc-border);
        border-radius: 0.75rem;
        box-shadow: 0 2px 4px rgba(43, 41, 38, 0.06), 0 14px 32px rgba(43, 41, 38, 0.12);
        display: none;
    }
    .hc-search-results.is-open { display: block; }
    .hc-search-result {
        display: flex;
        align-items: flex-start;
        gap: 0.55rem;
        width: 100%;
        padding: 0.5rem 0.6rem;
        border: 0;
        border-radius: 0.5rem;
        background: none;
        text-align: left;
        font: inherit;
        color: var(--hc-text);
        cursor: pointer;
    }
    .hc-search-result:hover, .hc-search-result.is-active { background: var(--hc-accent-tint); }
    .hc-search-result svg { width: 0.95rem; height: 0.95rem; margin-top: 0.2rem; color: var(--hc-accent); }
    .hc-search-result-title { font-size: var(--hc-fs-sm); font-weight: 600; line-height: 1.35; }
    .hc-search-result-path { font-size: var(--hc-fs-xs); color: var(--hc-muted); }
    .hc-search-empty { padding: 0.75rem; font-size: var(--hc-fs-sm); color: var(--hc-muted); }
    mark.hc-hit { background: #FFE7A8; color: inherit; padding: 0 0.1rem; border-radius: 0.2rem; }

    /* ---------- Shell ---------- */
    .hc-shell {
        display: grid;
        grid-template-columns: 17rem minmax(0, 1fr);
        max-width: 84rem;
        margin: 0 auto;
    }
    .hc-toc {
        position: sticky;
        top: var(--hc-topbar-h);
        align-self: start;
        height: calc(100vh - var(--hc-topbar-h));
        overflow-y: auto;
        padding: 1.5rem 1rem 2rem 1.25rem;
        border-right: 1px solid var(--hc-border-soft);
    }
    .hc-toc-label {
        font-size: var(--hc-fs-xs);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--hc-muted);
        margin: 0 0 0.6rem 0.6rem;
    }
    .hc-toc ol { list-style: none; margin: 0; padding: 0; }
    .hc-toc-chapter > a {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.45rem 0.6rem;
        border-radius: 0.5rem;
        color: var(--hc-text);
        font-size: var(--hc-fs-sm);
        font-weight: 600;
        text-decoration: none;
    }
    .hc-toc-chapter > a svg { width: 1rem; height: 1rem; color: var(--hc-muted); }
    .hc-toc-chapter > a:hover { background: var(--hc-surface-alt); text-decoration: none; }
    .hc-toc-chapter.is-active > a { background: var(--hc-accent-tint); color: var(--hc-accent); }
    .hc-toc-chapter.is-active > a svg { color: var(--hc-accent); }
    .hc-toc-sub { margin: 0.1rem 0 0.5rem 1.45rem !important; padding-left: 0.75rem !important; border-left: 1.5px solid var(--hc-border-soft); display: none; }
    .hc-toc-chapter.is-active .hc-toc-sub { display: block; }
    .hc-toc-sub a {
        display: block;
        padding: 0.22rem 0.5rem;
        border-radius: 0.4rem;
        font-size: 0.8rem;
        color: var(--hc-muted);
        line-height: 1.4;
    }
    .hc-toc-sub a:hover { color: var(--hc-text); background: var(--hc-surface-alt); text-decoration: none; }
    .hc-toc-sub a.is-active { color: var(--hc-accent); font-weight: 600; }

    .hc-main { min-width: 0; padding: 2.25rem clamp(1rem, 4vw, 3.5rem) 5rem; }
    .hc-content { max-width: 52rem; margin: 0 auto; }

    /* ---------- Cover ---------- */
    .hc-cover { padding-bottom: 1.5rem; }
    .hc-cover-head { display: flex; align-items: flex-start; gap: 1.1rem; }
    .hc-cover-icon {
        flex-shrink: 0;
        display: grid;
        place-items: center;
        width: 3.4rem;
        height: 3.4rem;
        border-radius: 1rem;
        background: var(--hc-accent-tint);
        color: var(--hc-accent);
    }
    .hc-cover-icon svg { width: 1.75rem; height: 1.75rem; }
    .hc-eyebrow {
        font-size: var(--hc-fs-xs);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--hc-accent);
        margin-bottom: 0.25rem;
    }
    .hc-cover h1 { margin: 0 0 0.35rem; font-size: clamp(1.6rem, 3vw, 2.1rem); line-height: 1.2; font-weight: 700; letter-spacing: -0.01em; }
    .hc-cover-lead { color: var(--hc-muted); font-size: 1rem; max-width: 42rem; margin: 0; }
    .hc-cover-meta { display: flex; flex-wrap: wrap; gap: 0.4rem 1.25rem; margin-top: 1rem; font-size: var(--hc-fs-xs); color: var(--hc-muted); }
    .hc-cover-meta span { display: inline-flex; align-items: center; gap: 0.35rem; }
    .hc-cover-meta svg { width: 0.85rem; height: 0.85rem; }
    .hc-print-only { display: none; }

    .hc-roles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; margin-top: 1.6rem; }
    .hc-role {
        position: relative;
        display: flex;
        gap: 0.8rem;
        padding: 1rem;
        border: 1px solid var(--hc-border);
        border-radius: var(--hc-radius);
        background: #fff;
        color: var(--hc-text);
        text-decoration: none !important;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    }
    .hc-role:hover { border-color: var(--hc-accent); box-shadow: 0 2px 4px rgba(43, 41, 38, 0.05), 0 10px 24px rgba(43, 41, 38, 0.08); transform: translateY(-2px); color: var(--hc-text); }
    .hc-role.is-you { border-color: var(--hc-accent); box-shadow: 0 0 0 3px var(--hc-accent-tint); }
    .hc-role-title { font-weight: 600; display: flex; align-items: center; gap: 0.4rem; }
    .hc-role-text { margin: 0.15rem 0 0; font-size: var(--hc-fs-sm); color: var(--hc-muted); line-height: 1.45; }
    .hc-you { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.1rem 0.4rem; border-radius: 0.3rem; background: var(--hc-accent); color: #fff; }

    /* ---------- Chapter ---------- */
    .hc-chapter { padding-top: 2.75rem; }
    .hc-chapter-head {
        display: flex;
        align-items: flex-start;
        gap: 0.95rem;
        padding-bottom: 1rem;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid var(--hc-border);
    }
    .hc-chapter-icon {
        flex-shrink: 0;
        display: grid;
        place-items: center;
        width: 2.85rem;
        height: 2.85rem;
        border-radius: 0.8rem;
        background: var(--hc-accent-tint);
        color: var(--hc-accent);
    }
    .hc-chapter-icon svg { width: 1.45rem; height: 1.45rem; }
    .hc-chapter-num { font-size: var(--hc-fs-xs); font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--hc-accent); }
    .hc-chapter h2 { margin: 0.05rem 0 0.2rem; font-size: 1.5rem; line-height: 1.25; font-weight: 700; letter-spacing: -0.01em; }
    .hc-chapter-sub { margin: 0; color: var(--hc-muted); font-size: var(--hc-fs-sm); }

    .hc-topic { margin-top: 2rem; }
    .hc-topic:first-of-type { margin-top: 0; }
    .hc-topic h3 {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        margin: 0 0 0.6rem;
        font-size: 1.12rem;
        font-weight: 650;
        line-height: 1.35;
    }
    .hc-topic h3 svg { width: 1.1rem; height: 1.1rem; color: var(--hc-accent); }
    .hc-topic h4 { margin: 1.1rem 0 0.4rem; font-size: 0.95rem; font-weight: 650; }
    .hc-topic > p, .hc-topic li { color: #403C38; }
    .hc-topic ul.hc-list { margin: 0 0 0.85rem; padding-left: 1.2rem; }
    .hc-topic ul.hc-list li { margin-bottom: 0.3rem; }
    .hc-topic ul.hc-list li::marker { color: var(--hc-accent); }

    .hc-section-title {
        font-size: var(--hc-fs-xs);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--hc-muted);
        margin: 1.25rem 0 0.7rem;
    }

    /* ---------- Timeline (numbered steps) ---------- */
    .hc-steps { list-style: none; padding: 0; margin: 0.4rem 0 1.25rem; }
    .hc-step { position: relative; display: flex; gap: 0.9rem; padding-bottom: 1rem; }
    .hc-step:last-child { padding-bottom: 0; }
    .hc-step:not(:last-child)::before {
        content: "";
        position: absolute;
        left: 0.8rem;
        top: 1.75rem;
        bottom: 0.2rem;
        width: 2px;
        margin-left: -1px;
        background: var(--hc-border);
    }
    .hc-step-marker {
        flex-shrink: 0;
        display: grid;
        place-items: center;
        width: 1.6rem;
        height: 1.6rem;
        border-radius: 50%;
        background: var(--hc-accent);
        color: #fff;
        font-size: 0.78rem;
        font-weight: 700;
        box-shadow: 0 0 0 4px var(--hc-accent-tint);
    }
    .hc-step-body { flex: 1; min-width: 0; padding-top: 0.05rem; }
    .hc-step-head { display: flex; align-items: center; gap: 0.45rem; font-weight: 600; margin-bottom: 0.15rem; }
    .hc-step-head svg { width: 1rem; height: 1rem; color: var(--hc-accent); }
    .hc-step-text { margin: 0; color: var(--hc-muted); font-size: var(--hc-fs-sm); line-height: 1.55; }
    .hc-result { display: inline-flex; align-items: center; gap: 0.4rem; margin-top: 0.4rem; font-size: var(--hc-fs-xs); font-weight: 600; color: var(--hc-muted); }
    .hc-result svg { width: 0.85rem; height: 0.85rem; }

    /* ---------- Option / feature cards ---------- */
    .hc-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 0.65rem; margin: 0.5rem 0 1.25rem; }
    .hc-options-or { display: grid; grid-template-columns: 1fr auto 1fr; align-items: stretch; gap: 0.6rem; margin: 0.5rem 0 1.25rem; }
    .hc-or { align-self: center; font-size: var(--hc-fs-xs); font-weight: 600; text-transform: uppercase; color: var(--hc-muted); }
    .hc-option {
        display: flex;
        align-items: flex-start;
        gap: 0.7rem;
        padding: 0.8rem 0.85rem;
        border: 1px solid var(--hc-border);
        border-radius: 0.75rem;
        background: #fff;
    }
    .hc-option-icon {
        flex-shrink: 0;
        display: grid;
        place-items: center;
        width: 2.1rem;
        height: 2.1rem;
        border-radius: 0.6rem;
        background: var(--hc-accent-tint);
        color: var(--hc-accent);
    }
    .hc-option-icon svg { width: 1.05rem; height: 1.05rem; }
    .hc-option-icon.is-danger { background: var(--hc-danger-tint); color: var(--hc-danger); }
    .hc-option-icon.is-success { background: var(--hc-success-tint); color: var(--hc-success); }
    .hc-option-icon.is-info { background: var(--hc-info-tint); color: var(--hc-info); }
    .hc-option-title { font-weight: 600; margin-bottom: 0.1rem; }
    .hc-option p { margin: 0; color: var(--hc-muted); font-size: var(--hc-fs-sm); line-height: 1.5; }

    /* ---------- Legend panel ---------- */
    .hc-panel { border: 1px solid var(--hc-border); border-radius: 0.75rem; padding: 0.8rem 0.9rem; margin: 0.5rem 0 1.25rem; }
    .hc-legend-group { font-size: var(--hc-fs-xs); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--hc-text); margin-bottom: 0.45rem; }
    .hc-legend-group:not(:first-child) { margin-top: 0.7rem; padding-top: 0.7rem; border-top: 1px dashed var(--hc-border); }
    .hc-legend-row { display: grid; grid-template-columns: 9.5rem 1fr; align-items: baseline; gap: 0.75rem; padding: 0.25rem 0; font-size: var(--hc-fs-sm); color: var(--hc-muted); }
    .hc-legend-row > :first-child { justify-self: start; }

    /* ---------- Badges (same tints as the system) ---------- */
    .hc-badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.18rem 0.5rem; border-radius: 0.375rem; font-size: 0.72rem; font-weight: 600; line-height: 1.3; white-space: nowrap; }
    .hc-badge-brand { background: var(--hc-accent-tint); color: var(--hc-accent); }
    .hc-badge-success { background: var(--hc-success-tint); color: var(--hc-success); }
    .hc-badge-danger { background: var(--hc-danger-tint); color: var(--hc-danger); }
    .hc-badge-info { background: var(--hc-info-tint); color: var(--hc-info); }
    .hc-badge-muted { background: var(--hc-surface-alt); color: var(--hc-muted); box-shadow: inset 0 0 0 1px var(--hc-border); }
    .hc-badge-warn { background: var(--hc-warn-tint); color: var(--hc-warn); }

    /* ---------- Notes ---------- */
    .hc-note {
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        margin: 0.75rem 0 1.1rem;
        padding: 0.7rem 0.85rem;
        border-radius: 0.65rem;
        font-size: var(--hc-fs-sm);
        line-height: 1.5;
    }
    .hc-note svg { width: 1.05rem; height: 1.05rem; margin-top: 0.12rem; }
    .hc-note p { margin: 0; }
    .hc-note-info { background: var(--hc-info-tint); color: #1F4E66; }
    .hc-note-info svg { color: var(--hc-info); }
    .hc-note-tip { background: var(--hc-success-tint); color: #1E5535; }
    .hc-note-tip svg { color: var(--hc-success); }
    .hc-note-warn { background: var(--hc-warn-tint); color: #5E4300; }
    .hc-note-warn svg { color: var(--hc-warn); }
    .hc-note-danger { background: var(--hc-danger-tint); color: #7A2C10; }
    .hc-note-danger svg { color: var(--hc-danger); }
    .hc-note-title { font-weight: 700; margin-right: 0.2rem; }

    /* ---------- Tables ---------- */
    .hc-table-wrap { overflow-x: auto; margin: 0.5rem 0 1.25rem; border: 1px solid var(--hc-border); border-radius: 0.75rem; }
    .hc-table { width: 100%; border-collapse: collapse; font-size: var(--hc-fs-sm); }
    .hc-table th {
        text-align: left;
        font-size: var(--hc-fs-xs);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--hc-muted);
        background: var(--hc-surface-alt);
        padding: 0.55rem 0.75rem;
        border-bottom: 1px solid var(--hc-border);
        white-space: nowrap;
    }
    .hc-table td { padding: 0.55rem 0.75rem; border-bottom: 1px solid var(--hc-border-soft); vertical-align: top; color: #403C38; }
    .hc-table tr:last-child td { border-bottom: 0; }
    .hc-table td:first-child { font-weight: 600; color: var(--hc-text); white-space: nowrap; }
    .hc-check { color: var(--hc-success); font-weight: 700; }
    .hc-dash { color: var(--hc-faint); }

    /* ---------- FAQ ---------- */
    .hc-faq { border: 1px solid var(--hc-border); border-radius: 0.75rem; margin: 0.5rem 0 1.25rem; overflow: hidden; }
    .hc-faq details + details { border-top: 1px solid var(--hc-border-soft); }
    .hc-faq summary {
        list-style: none;
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        padding: 0.75rem 0.9rem;
        cursor: pointer;
        font-weight: 600;
        line-height: 1.45;
    }
    .hc-faq summary::-webkit-details-marker { display: none; }
    .hc-faq summary:hover { background: var(--hc-surface-alt); }
    .hc-faq summary .hc-faq-q { flex-shrink: 0; display: grid; place-items: center; width: 1.35rem; height: 1.35rem; border-radius: 0.4rem; background: var(--hc-accent-tint); color: var(--hc-accent); font-size: 0.72rem; font-weight: 700; margin-top: 0.05rem; }
    .hc-faq summary .hc-faq-chevron { margin-left: auto; width: 1rem; height: 1rem; color: var(--hc-faint); transition: transform 0.15s ease; margin-top: 0.2rem; }
    .hc-faq details[open] summary .hc-faq-chevron { transform: rotate(90deg); }
    .hc-faq-a { padding: 0 0.9rem 0.85rem 2.85rem; color: var(--hc-muted); font-size: var(--hc-fs-sm); }
    .hc-faq-a p:last-child, .hc-faq-a ul:last-child { margin-bottom: 0; }
    .hc-faq-a ul { padding-left: 1.1rem; margin: 0 0 0.5rem; }

    /* ---------- Figures: coded "screenshots" of the real screens ---------- */
    .hc-figure { margin: 1rem 0 1.5rem; }
    .hc-window {
        border: 1px solid var(--hc-border);
        border-radius: 0.8rem;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 1px 2px rgba(43, 41, 38, 0.05), 0 8px 24px rgba(43, 41, 38, 0.07);
    }
    .hc-window-bar {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.45rem 0.7rem;
        background: var(--hc-surface-alt);
        border-bottom: 1px solid var(--hc-border);
    }
    .hc-window-dots { display: flex; gap: 0.3rem; }
    .hc-window-dots i { display: block; width: 0.55rem; height: 0.55rem; border-radius: 50%; background: #E2D9CF; }
    .hc-window-url {
        flex: 1;
        min-width: 0;
        max-width: 24rem;
        margin: 0 auto;
        padding: 0.12rem 0.7rem;
        border-radius: 999px;
        background: #fff;
        border: 1px solid var(--hc-border);
        font-size: 0.68rem;
        color: var(--hc-muted);
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .hc-screen { padding: 0.9rem; font-size: 0.76rem; line-height: 1.45; color: var(--hc-text); background: #fff; }
    .hc-screen.is-flush { padding: 0; }
    .hc-screen.is-tinted { background: var(--hc-surface-alt); }
    .hc-figure figcaption {
        display: flex;
        gap: 0.45rem;
        margin-top: 0.55rem;
        font-size: var(--hc-fs-xs);
        color: var(--hc-muted);
        line-height: 1.45;
    }
    .hc-figure figcaption strong { color: var(--hc-text); white-space: nowrap; }

    /* Callout numbers on figures, matched by the list under them */
    .m-pin {
        display: inline-grid;
        place-items: center;
        width: 1.1rem;
        height: 1.1rem;
        border-radius: 50%;
        background: var(--hc-accent);
        color: #fff;
        font-size: 0.62rem;
        font-weight: 700;
        line-height: 1;
        box-shadow: 0 0 0 2px #fff, 0 0 0 3.5px var(--hc-accent);
        vertical-align: middle;
        flex-shrink: 0;
    }
    .hc-pins { list-style: none; padding: 0; margin: 0.25rem 0 1.25rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); gap: 0.4rem 1rem; }
    .hc-pins li { display: flex; gap: 0.55rem; font-size: var(--hc-fs-sm); color: var(--hc-muted); line-height: 1.45; }
    .hc-pins li strong { color: var(--hc-text); }
    .hc-pins .m-pin { margin-top: 0.12rem; margin-left: 0; }
    .hc-screen .m-pin { margin-left: 0.3rem; }

    /* Mock building blocks — small-scale copies of the system's own UI */
    .m-row { display: flex; align-items: center; gap: 0.5rem; }
    .m-col { display: flex; flex-direction: column; gap: 0.5rem; }
    .m-grow { flex: 1; min-width: 0; }
    .m-between { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
    .m-wrap { flex-wrap: wrap; }
    .m-muted { color: var(--hc-muted); }
    .m-faint { color: var(--hc-faint); }
    .m-small { font-size: 0.68rem; }
    .m-xs { font-size: 0.62rem; }
    .m-bold { font-weight: 600; }
    .m-title { font-size: 0.86rem; font-weight: 650; }
    .m-label { font-size: 0.6rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--hc-muted); }
    .m-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; }
    .m-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.6rem; }
    .m-card { border: 1px solid var(--hc-border); border-radius: 0.6rem; padding: 0.65rem 0.75rem; background: #fff; }
    .m-card.is-alt { background: var(--hc-surface-alt); border-color: transparent; }
    .m-btn { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.22rem 0.6rem; border-radius: 999px; font-size: 0.66rem; font-weight: 600; white-space: nowrap; background: var(--hc-accent); color: #fff; }
    .m-btn svg { width: 0.72rem; height: 0.72rem; }
    .m-btn.is-soft { background: var(--hc-accent-tint); color: var(--hc-accent); }
    .m-btn.is-ghost { background: var(--hc-surface-alt); color: var(--hc-text); }
    .m-btn.is-danger { background: var(--hc-danger-tint); color: var(--hc-danger); }
    .m-btn.is-success { background: var(--hc-success); color: #fff; }
    .m-btn.is-disabled { opacity: 0.45; }
    .m-btn.is-block { justify-content: center; width: 100%; padding: 0.35rem; }
    .m-input { display: flex; align-items: center; min-height: 1.5rem; padding: 0.18rem 0.5rem; border: 1px solid #CFC6BC; border-radius: 0.35rem; background: #fff; font-size: 0.68rem; color: var(--hc-text); }
    .m-input.is-empty { color: var(--hc-faint); }
    .m-input.is-disabled { background: var(--hc-surface-alt); color: var(--hc-muted); border-color: var(--hc-border); }
    .m-field { display: flex; flex-direction: column; gap: 0.18rem; }
    .m-field > span:first-child { font-size: 0.62rem; font-weight: 500; color: var(--hc-muted); }
    .m-check { display: inline-grid; place-items: center; width: 0.75rem; height: 0.75rem; border: 1px solid #B9AFA5; border-radius: 0.2rem; background: #fff; flex-shrink: 0; }
    .m-check.is-on { background: var(--hc-accent); border-color: var(--hc-accent); }
    .m-check.is-on::after { content: ""; width: 0.35rem; height: 0.2rem; border-left: 1.5px solid #fff; border-bottom: 1.5px solid #fff; transform: rotate(-45deg) translate(0.03rem, -0.03rem); }
    .m-radio { display: inline-block; width: 0.72rem; height: 0.72rem; border: 1px solid #B9AFA5; border-radius: 50%; background: #fff; flex-shrink: 0; }
    .m-radio.is-on { border: 0.22rem solid var(--hc-accent); }
    .m-dot { display: inline-block; width: 0.45rem; height: 0.45rem; border-radius: 50%; background: var(--hc-accent); flex-shrink: 0; }
    .m-dot.is-success { background: var(--hc-success); }
    .m-dot.is-danger { background: var(--hc-danger); }
    .m-dot.is-muted { background: #CFC6BC; }
    .m-rule { height: 1px; background: var(--hc-border); margin: 0.2rem 0; }
    .m-line { height: 0.35rem; border-radius: 0.2rem; background: #EDE6DE; }
    .m-avatar { display: grid; place-items: center; width: 1.4rem; height: 1.4rem; border-radius: 50%; background: var(--hc-surface-alt); color: var(--hc-text); flex-shrink: 0; }
    .m-avatar svg { width: 0.75rem; height: 0.75rem; }
    .m-icon-btn { display: grid; place-items: center; width: 1.55rem; height: 1.55rem; border-radius: 50%; background: var(--hc-surface-alt); color: var(--hc-text); flex-shrink: 0; position: relative; }
    .m-icon-btn svg { width: 0.8rem; height: 0.8rem; }
    .m-icon-btn .m-count { position: absolute; top: -0.2rem; right: -0.25rem; min-width: 0.8rem; height: 0.8rem; padding: 0 0.15rem; border-radius: 999px; background: var(--hc-danger); color: #fff; font-size: 0.5rem; font-weight: 700; display: grid; place-items: center; }
    .m-tabs { display: flex; gap: 0.9rem; border-bottom: 1px solid var(--hc-border); font-size: 0.68rem; color: var(--hc-muted); }
    .m-tabs span { padding: 0.3rem 0.05rem; display: inline-flex; align-items: center; gap: 0.25rem; }
    .m-tabs span.is-on { color: var(--hc-accent); font-weight: 600; box-shadow: inset 0 -2px 0 var(--hc-accent); }
    .m-red-dot { display: inline-block; width: 0.35rem; height: 0.35rem; border-radius: 50%; background: var(--hc-danger); }
    .m-pills { display: flex; gap: 0.3rem; flex-wrap: wrap; }
    .m-table { width: 100%; border-collapse: collapse; font-size: 0.66rem; }
    .m-table th { text-align: left; font-size: 0.58rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--hc-muted); font-weight: 600; padding: 0.3rem 0.4rem; border-bottom: 1px solid var(--hc-border); white-space: nowrap; }
    .m-table td { padding: 0.32rem 0.4rem; border-bottom: 1px solid var(--hc-border-soft); white-space: nowrap; }
    .m-table tr.is-conflict td { background: var(--hc-danger-tint); }
    .m-table tr.is-conflict td:first-child { box-shadow: inset 2px 0 0 var(--hc-danger); }
    .m-table tr.is-done td { color: var(--hc-muted); }
    .m-table tr.is-now td { background: var(--hc-accent-tint); }
    .m-band { font-size: 0.6rem; font-weight: 600; color: var(--hc-muted); background: var(--hc-surface-alt); padding: 0.2rem 0.4rem !important; }

    /* App shell mock (sidebar + topbar) */
    .m-app { display: grid; grid-template-columns: 8.5rem 1fr; min-height: 13rem; }
    .m-side { background: var(--hc-surface-alt); border-right: 1px solid var(--hc-border); padding: 0.6rem 0.45rem; display: flex; flex-direction: column; gap: 0.12rem; }
    .m-side-brand { font-family: 'Montserrat', sans-serif; font-style: italic; color: var(--hc-accent); text-align: center; font-size: 0.78rem; margin-bottom: 0.45rem; }
    .m-nav { display: flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.4rem; border-radius: 0.35rem; font-size: 0.62rem; color: var(--hc-text); white-space: nowrap; overflow: hidden; }
    .m-nav svg { width: 0.7rem; height: 0.7rem; }
    .m-nav.is-on { background: var(--hc-accent-tint); color: var(--hc-accent); font-weight: 600; }
    .m-nav.is-sub { margin-left: 0.7rem; border-left: 1px solid var(--hc-border); border-radius: 0 0.35rem 0.35rem 0; }
    .m-nav.is-label { color: var(--hc-muted); font-weight: 600; }
    .m-side-foot { margin-top: auto; }
    .m-body { min-width: 0; display: flex; flex-direction: column; }
    .m-top { display: flex; align-items: center; gap: 0.5rem; padding: 0.45rem 0.75rem; }
    .m-top .m-title { flex: 1; }
    .m-page { padding: 0.35rem 0.75rem 0.8rem; display: flex; flex-direction: column; gap: 0.55rem; }

    /* Stat strip mock */
    .m-stats { display: grid; grid-template-columns: repeat(4, 1fr); border: 1px solid var(--hc-border); border-radius: 0.55rem; overflow: hidden; }
    .m-stat { padding: 0.45rem 0.55rem; }
    .m-stat + .m-stat { border-left: 1px solid var(--hc-border); }
    .m-stat-value { font-size: 0.95rem; font-weight: 700; line-height: 1.2; }

    /* Timer / tablet */
    .m-timer { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 1.15rem; font-weight: 700; letter-spacing: 0.04em; }
    .m-control-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.35rem; }
    .m-control { display: flex; flex-direction: column; align-items: center; gap: 0.2rem; padding: 0.45rem 0.25rem; border-radius: 0.55rem; background: var(--hc-surface-alt); font-size: 0.6rem; font-weight: 600; text-align: center; }
    .m-control svg { width: 0.9rem; height: 0.9rem; color: var(--hc-accent); }
    .m-control.is-primary { background: var(--hc-accent); color: #fff; }
    .m-control.is-primary svg { color: #fff; }
    .m-control.is-disabled { opacity: 0.4; }
    .m-control.is-danger svg { color: var(--hc-danger); }
    .m-qr { display: grid; grid-template-columns: repeat(9, 1fr); gap: 1px; width: 5.2rem; height: 5.2rem; padding: 0.3rem; background: #fff; border: 1px solid var(--hc-border); border-radius: 0.4rem; }
    .m-qr i { background: var(--hc-text); border-radius: 1px; }
    .m-qr i.o { background: transparent; }
    .m-bubbles { display: inline-flex; gap: 0.2rem; }
    .m-bubbles i { display: grid; place-items: center; width: 1rem; height: 1rem; border-radius: 50%; border: 1px solid #CFC6BC; font-style: normal; font-size: 0.55rem; color: var(--hc-muted); }
    .m-bubbles i.is-on { background: var(--hc-accent); border-color: var(--hc-accent); color: #fff; font-weight: 700; }
    .m-blink { color: var(--hc-danger); font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem; }
    .m-modal { border: 1px solid var(--hc-border); border-radius: 0.7rem; background: #fff; box-shadow: 0 10px 30px rgba(43, 41, 38, 0.14); overflow: hidden; }
    .m-modal-head { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.7rem; border-bottom: 1px solid var(--hc-border); font-weight: 650; font-size: 0.78rem; }
    .m-modal-body { padding: 0.65rem 0.7rem; display: flex; flex-direction: column; gap: 0.45rem; }
    .m-modal-foot { display: flex; justify-content: flex-end; gap: 0.35rem; padding: 0.45rem 0.7rem; border-top: 1px solid var(--hc-border); }
    .m-backdrop { background: rgba(43, 41, 38, 0.18); padding: 1rem; display: grid; place-items: center; }
    .m-menu { border: 1px solid var(--hc-border); border-radius: 0.45rem; background: #fff; box-shadow: 0 6px 18px rgba(43, 41, 38, 0.12); padding: 0.2rem; font-size: 0.64rem; min-width: 6.5rem; }
    .m-menu div { display: flex; align-items: center; gap: 0.35rem; padding: 0.22rem 0.4rem; border-radius: 0.3rem; }
    .m-menu div svg { width: 0.7rem; height: 0.7rem; }
    .m-menu div.is-hover { background: var(--hc-accent-tint); color: var(--hc-accent); }
    .m-menu div.is-danger { color: var(--hc-danger); }
    .m-progress { height: 0.35rem; border-radius: 999px; background: var(--hc-surface-alt); overflow: hidden; }
    .m-progress > i { display: block; height: 100%; background: var(--hc-accent); border-radius: 999px; }
    .m-paper { background: #fff; border: 1px solid var(--hc-border); border-radius: 0.3rem; padding: 0.7rem 0.8rem; box-shadow: 0 1px 3px rgba(43, 41, 38, 0.08); }
    .m-seg { display: inline-flex; border-radius: 999px; background: var(--hc-surface-alt); padding: 0.12rem; font-size: 0.62rem; }
    .m-seg span { padding: 0.15rem 0.55rem; border-radius: 999px; color: var(--hc-muted); }
    .m-seg span.is-on { background: var(--hc-accent); color: #fff; font-weight: 600; }

    /* Back to top */
    .hc-top-link { position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 40; width: 2.6rem; height: 2.6rem; padding: 0; justify-content: center; box-shadow: 0 6px 16px rgba(43, 41, 38, 0.12); opacity: 0; pointer-events: none; transition: opacity 0.2s ease; }
    .hc-top-link.is-visible { opacity: 1; pointer-events: auto; }

    .hc-footer { margin-top: 4rem; padding-top: 1.25rem; border-top: 1px solid var(--hc-border); font-size: var(--hc-fs-xs); color: var(--hc-muted); display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.5rem; }

    /* ---------- Responsive ---------- */
    .hc-toc-toggle { display: none; }
    @media (max-width: 991.98px) {
        .hc-shell { grid-template-columns: minmax(0, 1fr); }
        .hc-toc {
            position: fixed;
            inset: var(--hc-topbar-h) auto 0 0;
            z-index: 45;
            width: min(20rem, 86vw);
            height: auto;
            background: #fff;
            border-right: 1px solid var(--hc-border);
            box-shadow: 0 10px 40px rgba(43, 41, 38, 0.18);
            transform: translateX(-105%);
            transition: transform 0.2s ease;
        }
        .hc-toc.is-open { transform: translateX(0); }
        .hc-toc-toggle { display: inline-flex; }
        .hc-search { margin-left: 0; }
    }
    @media (max-width: 767.98px) {
        .hc-topbar { gap: 0.6rem; padding: 0 0.75rem; }
        .hc-topbar-divider, .hc-topbar-title, .hc-search-kbd { display: none; }
        .hc-btn-label { display: none; }
        .hc-topbar-actions .hc-btn { padding: 0 0.65rem; }
        .hc-search { flex: 1 1 auto; }
        .hc-main { padding: 1.5rem 1rem 4rem; }
        .hc-legend-row { grid-template-columns: 1fr; gap: 0.2rem; }
        .hc-options-or { grid-template-columns: 1fr; }
        .hc-or { justify-self: center; }
        .hc-roles { grid-template-columns: 1fr; }
        /* Figures keep their desktop layout, like a real screenshot, and
           scroll sideways inside their frame rather than reflowing. */
        .hc-screen { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .hc-screen:not(.m-backdrop):not([style*="place-items"]) > * { min-width: 34rem; }
        .hc-screen.is-flush > .m-app { min-width: 36rem; }
    }
    @media (max-width: 479.98px) {
        .hc-topbar-brand { display: none; }
    }

    /* ---------- Print / Save as PDF ---------- */
    @page {
        size: A4;
        margin: 16mm 15mm 18mm;
        @bottom-left { content: "ARPQRS User Guide"; font-size: 8pt; color: #9A928A; }
        @bottom-right { content: "Page " counter(page) " of " counter(pages); font-size: 8pt; color: #9A928A; }
    }
    @page :first {
        @bottom-left { content: none; }
        @bottom-right { content: none; }
    }
    @media print {
        :root { --hc-fs: 10pt; --hc-fs-sm: 9.2pt; --hc-fs-xs: 8pt; }
        html { scroll-behavior: auto; }
        body { font-size: 10pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .hc-topbar, .hc-toc, .hc-top-link, .hc-screen-only, .hc-search-results { display: none !important; }
        .hc-shell { display: block; max-width: none; }
        .hc-main { padding: 0; }
        .hc-content { max-width: none; }
        a { color: var(--hc-text); text-decoration: none; }
        .hc-print-only { display: block; }

        .hc-cover { min-height: 245mm; display: flex; flex-direction: column; padding-top: 30mm; }
        .hc-cover h1 { font-size: 26pt; }
        .hc-cover-lead { font-size: 11.5pt; }
        .hc-role { break-inside: avoid; }
        .hc-role:hover { transform: none; box-shadow: none; }
        .hc-print-contents { break-before: page; }
        .hc-print-contents h2 { font-size: 16pt; margin: 0 0 6mm; }
        .hc-print-contents ol { list-style: none; padding: 0; margin: 0; }
        .hc-print-contents > ol > li { margin-bottom: 3.5mm; break-inside: avoid; }
        .hc-print-contents .hc-pc-chapter { font-weight: 700; font-size: 11pt; }
        .hc-print-contents .hc-pc-chapter span { color: var(--hc-accent); margin-right: 3mm; }
        .hc-print-contents .hc-pc-topics { color: var(--hc-muted); font-size: 9pt; padding-left: 8mm; margin-top: 1mm; }

        .hc-chapter { break-before: page; padding-top: 0; }
        .hc-chapter-head, .hc-topic h3, .hc-topic h4, .hc-section-title { break-after: avoid; }
        .hc-figure, .hc-option, .hc-panel, .hc-note, .hc-step, .hc-faq details, .hc-table tr, .hc-pins li { break-inside: avoid; }
        .hc-figure { margin: 4mm 0 6mm; }
        .hc-window { box-shadow: none; }
        .hc-table-wrap { overflow: visible; }
        .hc-faq details > summary .hc-faq-chevron { display: none; }
        .hc-faq summary:hover { background: none; }
        p, li { orphans: 3; widows: 3; }
        .hc-footer { display: none; }
    }
</style>
