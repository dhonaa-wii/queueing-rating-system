@extends('layouts.admin')

@section('title', 'Administrator Dashboard')
@section('heading', 'Dashboard')

@push('styles')
    <style>
        .db {
            --db-fs-2xs: 0.66rem;
            --db-fs-xs: 0.72rem;
            --db-fs-sm: 0.8rem;
            --db-fs-base: 0.86rem;
            --db-fs-title: clamp(0.84rem, 0.8rem + 0.15vw, 0.92rem);
            --db-fs-value: clamp(1.1rem, 0.95rem + 0.55vw, 1.4rem);
            --db-radius: 0.75rem;
            --db-gap: clamp(0.75rem, 0.55rem + 0.6vw, 1.1rem);
            --db-line: var(--brand-border);
            font-size: var(--db-fs-base);
        }

        .db * { min-width: 0; }

        .db-num { font-variant-numeric: tabular-nums; }

        /* ---------- Header ---------- */
        .db-head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.75rem 1.5rem;
            margin-bottom: var(--db-gap);
        }

        .db-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: var(--db-fs-xs);
            color: var(--brand-muted);
            font-weight: 500;
        }

        .db-eyebrow svg { width: 0.85rem; height: 0.85rem; }

        .db-greeting {
            font-size: clamp(1.05rem, 0.9rem + 0.6vw, 1.4rem);
            font-weight: 700;
            letter-spacing: -0.01em;
            margin: 0.2rem 0 0.45rem;
        }

        .db-context {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }

        .db-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.18rem 0.55rem;
            border: 1px solid var(--db-line);
            border-radius: 999px;
            font-size: var(--db-fs-xs);
            color: var(--brand-text);
            background: var(--brand-surface);
            white-space: nowrap;
        }

        .db-chip svg { width: 0.78rem; height: 0.78rem; color: var(--brand-muted); }

        .db-actions { display: flex; flex-wrap: wrap; gap: 0.45rem; }

        .db-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.75rem;
            border-radius: 0.55rem;
            border: 0;
            background: var(--brand-surface-alt);
            color: var(--brand-text);
            font-size: var(--db-fs-sm);
            font-weight: 600;
            text-decoration: none;
            line-height: 1.2;
            transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }

        .db-btn svg { width: 0.95rem; height: 0.95rem; }

        .db-btn:hover { background: var(--brand-accent-tint); color: var(--brand-accent); transform: translateY(-1px); }

        .db-btn.is-primary {
            background: var(--brand-accent);
            color: var(--brand-accent-contrast);
        }

        .db-btn.is-primary:hover {
            background: color-mix(in srgb, var(--brand-accent) 86%, var(--brand-btn-shade));
            color: var(--brand-accent-contrast);
            box-shadow: 0 6px 16px -6px color-mix(in srgb, var(--brand-accent) 75%, transparent);
        }

        .db-btn .db-count {
            background: var(--brand-danger);
            color: #fff;
            border-radius: 999px;
            font-size: var(--db-fs-2xs);
            padding: 0.05rem 0.4rem;
        }

        /* ---------- Icon chips ---------- */
        .db-ico {
            width: 1.9rem;
            height: 1.9rem;
            border-radius: 0.55rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: var(--brand-accent-tint);
            color: var(--brand-accent);
        }

        .db-ico svg { width: 1rem; height: 1rem; }
        .db-ico.sm { width: 1.6rem; height: 1.6rem; border-radius: 0.45rem; }
        .db-ico.sm svg { width: 0.85rem; height: 0.85rem; }

        .tone-accent { background: var(--brand-accent-tint); color: var(--brand-accent); }
        .tone-success { background: var(--brand-success-tint); color: var(--brand-success); }
        .tone-info { background: var(--brand-info-tint); color: var(--brand-info); }
        .tone-danger { background: var(--brand-danger-tint); color: var(--brand-danger); }
        .tone-muted { background: var(--brand-surface-alt); color: var(--brand-muted); }

        /* ---------- Stat strip ---------- */
        .db-stats {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 1px;
            background: var(--db-line);
            border: 1px solid var(--db-line);
            border-radius: var(--db-radius);
            overflow: hidden;
            margin-bottom: var(--db-gap);
        }

        @media (max-width: 1399.98px) { .db-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 575.98px) { .db-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

        .db-stat {
            background: var(--brand-surface);
            padding: 0.85rem 0.95rem 0.8rem;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .db-stat-top { display: flex; align-items: center; gap: 0.55rem; }

        .db-stat-label {
            font-size: var(--db-fs-xs);
            color: var(--brand-muted);
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .db-stat-value-row { display: flex; align-items: baseline; flex-wrap: wrap; gap: 0.2rem 0.45rem; }

        .db-stat-value {
            font-size: var(--db-fs-value);
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.1;
        }

        .db-stat-of { font-size: var(--db-fs-sm); color: var(--brand-muted); font-weight: 500; }

        .db-stat-sub {
            font-size: var(--db-fs-xs);
            color: var(--brand-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .db-delta {
            display: inline-flex;
            align-items: center;
            gap: 0.15rem;
            font-size: var(--db-fs-2xs);
            font-weight: 700;
            padding: 0.08rem 0.38rem;
            border-radius: 999px;
        }

        .db-delta svg { width: 0.62rem; height: 0.62rem; }
        .db-delta.up { background: var(--brand-success-tint); color: var(--brand-success); }
        .db-delta.down { background: var(--brand-danger-tint); color: var(--brand-danger); }
        .db-delta.flat { background: var(--brand-surface-alt); color: var(--brand-muted); }

        .db-spark { width: 100%; height: 1.6rem; display: block; margin-top: auto; }

        .db-meter {
            margin-top: auto;
            height: 0.3rem;
            border-radius: 999px;
            background: var(--brand-surface-alt);
            overflow: hidden;
        }

        .db-meter > span { display: block; height: 100%; border-radius: 999px; background: var(--brand-accent); }

        /* ---------- Panels ---------- */
        .db-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: var(--db-gap);
        }

        .span-8 { grid-column: span 8; }
        .span-6 { grid-column: span 6; }
        .span-4 { grid-column: span 4; }

        @media (max-width: 1199.98px) {
            .span-8, .span-4 { grid-column: span 12; }
            .span-4.pair { grid-column: span 6; }
            .span-4.wide { grid-column: span 12; }
        }

        @media (max-width: 767.98px) {
            .span-6, .span-4.pair { grid-column: span 12; }
        }

        .db-panel {
            background: var(--brand-surface);
            border: 1px solid var(--db-line);
            border-radius: var(--db-radius);
            display: flex;
            flex-direction: column;
        }

        .db-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem 0.75rem;
            padding: 0.8rem 0.95rem 0;
        }

        .db-panel-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
            font-size: var(--db-fs-title);
            font-weight: 700;
        }

        .db-panel-title svg { width: 0.95rem; height: 0.95rem; color: var(--brand-muted); }

        .db-panel-link {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            font-size: var(--db-fs-xs);
            font-weight: 600;
            color: var(--brand-muted);
            text-decoration: none;
        }

        .db-panel-link svg { width: 0.8rem; height: 0.8rem; transition: transform 0.15s ease; }
        .db-panel-link:hover { color: var(--brand-accent); }
        .db-panel-link:hover svg { transform: translateX(2px); }

        .db-panel-body { padding: 0.75rem 0.95rem 0.9rem; flex: 1; }

        .db-pill {
            font-size: var(--db-fs-2xs);
            font-weight: 700;
            padding: 0.1rem 0.45rem;
            border-radius: 999px;
            white-space: nowrap;
        }

        .db-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1.4rem 0.5rem;
            color: var(--brand-muted);
            font-size: var(--db-fs-sm);
            height: 100%;
        }

        .db-empty svg { width: 1rem; height: 1rem; }

        /* ---------- Segmented control + legend ---------- */
        .db-seg {
            display: inline-flex;
            padding: 0.15rem;
            border-radius: 0.5rem;
            background: var(--brand-surface-alt);
        }

        .db-seg button {
            border: 0;
            background: transparent;
            color: var(--brand-muted);
            font-size: var(--db-fs-2xs);
            font-weight: 700;
            padding: 0.22rem 0.55rem;
            border-radius: 0.38rem;
        }

        .db-seg button.is-active {
            background: var(--brand-surface);
            color: var(--brand-text);
            box-shadow: var(--brand-shadow);
        }

        .db-legend { display: flex; flex-wrap: wrap; gap: 0.3rem 0.8rem; font-size: var(--db-fs-xs); color: var(--brand-muted); }
        .db-legend span { display: inline-flex; align-items: center; gap: 0.35rem; }
        .db-key { width: 0.55rem; height: 0.55rem; border-radius: 0.15rem; display: inline-block; flex-shrink: 0; }
        .db-key.line { height: 0.18rem; width: 0.8rem; border-radius: 999px; }

        /* ---------- Activity chart ---------- */
        .db-totals { display: flex; flex-wrap: wrap; gap: 0.4rem 1.6rem; margin-bottom: 0.4rem; }
        .db-total-label { font-size: var(--db-fs-xs); color: var(--brand-muted); display: flex; align-items: center; gap: 0.35rem; }
        .db-total-value { font-size: var(--db-fs-value); font-weight: 700; letter-spacing: -0.02em; line-height: 1.15; }

        .db-activity-body { display: flex; flex-direction: column; }
        .db-chart { position: relative; flex: 1; min-height: clamp(10.5rem, 8rem + 8vw, 14rem); }
        /* Absolute so the drawn SVG never props the flex-sized box open on resize. */
        .db-chart svg { position: absolute; inset: 0; display: block; width: 100%; height: 100%; overflow: visible; }
        .db-chart text { font-size: 10.5px; fill: var(--brand-muted); font-variant-numeric: tabular-nums; }

        /* ---------- Donut ---------- */
        /* Side by side when the panel is wide; donut over legend when it isn't,
           so outcome names never have to wrap or truncate. */
        .db-outcomes { container-type: inline-size; }
        .db-donut-wrap { display: flex; align-items: center; gap: 1rem 1.5rem; height: 100%; }
        @container (max-width: 30rem) {
            .db-donut-wrap { flex-direction: column; gap: 0.75rem; }
            .db-donut-wrap .db-rows { flex: none; width: 100%; }
            .db-donut-wrap .db-legend-row { padding: 0.22rem 0; }
        }
        .db-donut { position: relative; width: clamp(7rem, 6rem + 2.5vw, 8.5rem); aspect-ratio: 1; flex-shrink: 0; margin-inline: auto; }
        .db-donut svg { width: 100%; height: 100%; transform: rotate(-90deg); }
        .db-donut circle { transition: stroke-width 0.15s ease; }
        .db-donut circle[data-tip]:hover { stroke-width: 15; }
        .db-donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
        .db-donut-center strong { font-size: var(--db-fs-value); line-height: 1.1; }
        .db-donut-center span { font-size: var(--db-fs-2xs); color: var(--brand-muted); text-transform: uppercase; letter-spacing: 0.05em; }

        .db-rows { flex: 1 1 11rem; display: flex; flex-direction: column; gap: 0.1rem; }

        .db-legend-row {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            align-items: center;
            gap: 0.5rem;
            padding: 0.3rem 0;
            font-size: var(--db-fs-sm);
        }

        .db-legend-row + .db-legend-row { border-top: 1px dashed var(--db-line); }
        .db-legend-row .name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .db-legend-row .count { font-weight: 700; }
        .db-legend-row .pct { color: var(--brand-muted); font-size: var(--db-fs-xs); width: 2.6rem; text-align: right; }

        /* ---------- Table ---------- */
        .db-table-wrap { overflow-x: auto; margin: 0 -0.95rem; }

        .db-table { width: 100%; border-collapse: collapse; font-size: var(--db-fs-sm); }

        .db-table th {
            font-size: var(--db-fs-2xs);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--brand-muted);
            font-weight: 700;
            padding: 0.45rem 0.6rem;
            border-bottom: 1px solid var(--db-line);
            white-space: nowrap;
            text-align: left;
        }

        .db-table td { padding: 0.55rem 0.6rem; border-bottom: 1px solid var(--db-line); vertical-align: middle; }
        .db-table tr:last-child td { border-bottom: 0; }
        .db-table th:first-child, .db-table td:first-child { padding-left: 0.95rem; }
        .db-table th:last-child, .db-table td:last-child { padding-right: 0.95rem; }
        .db-table tbody tr { transition: background-color 0.12s ease; }
        .db-table tbody tr:hover { background: var(--brand-surface-alt); }

        .db-cat { display: flex; align-items: center; gap: 0.6rem; }
        .db-cat a { color: var(--brand-text); font-weight: 600; text-decoration: none; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 16rem; }
        .db-cat a:hover { color: var(--brand-accent); }
        .db-cat small { display: block; color: var(--brand-muted); font-size: var(--db-fs-xs); white-space: nowrap; }

        .db-status { display: inline-flex; align-items: center; gap: 0.35rem; white-space: nowrap; font-size: var(--db-fs-xs); font-weight: 600; }
        .db-dot { width: 0.45rem; height: 0.45rem; border-radius: 50%; flex-shrink: 0; display: inline-block; }

        .db-progress { display: flex; align-items: center; gap: 0.5rem; min-width: 7.5rem; }
        .db-progress-track { flex: 1; height: 0.35rem; border-radius: 999px; background: var(--brand-surface-alt); overflow: hidden; }
        .db-progress-track > span { display: block; height: 100%; border-radius: 999px; background: var(--brand-accent); }
        .db-progress small { color: var(--brand-muted); font-size: var(--db-fs-xs); white-space: nowrap; }

        @media (max-width: 575.98px) {
            .db-hide-sm, .db-cat .db-ico { display: none; }
        }

        /* ---------- Lists ---------- */
        .db-list { list-style: none; margin: 0; padding: 0; }

        .db-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.55rem 0;
            color: inherit;
            text-decoration: none;
        }

        .db-list > li + li .db-item, .db-list > li + li > .db-item { border-top: 1px solid var(--db-line); }
        a.db-item:hover .db-item-title { color: var(--brand-accent); }

        .db-item-main { flex: 1; min-width: 0; }
        .db-item-title { font-weight: 600; font-size: var(--db-fs-sm); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .db-item-sub { font-size: var(--db-fs-xs); color: var(--brand-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .db-item-meta { font-size: var(--db-fs-2xs); color: var(--brand-muted); white-space: nowrap; flex-shrink: 0; }
        .db-item-chev { width: 0.85rem; height: 0.85rem; color: var(--brand-muted); flex-shrink: 0; }

        .db-mini-btn {
            border: 0;
            background: var(--brand-surface-alt);
            color: var(--brand-muted);
            border-radius: 0.45rem;
            width: 1.7rem;
            height: 1.7rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .db-mini-btn svg { width: 0.85rem; height: 0.85rem; }
        .db-mini-btn:hover { background: var(--brand-accent-tint); color: var(--brand-accent); }
        .db-mini-btn.ok { background: var(--brand-success-tint); color: var(--brand-success); }
        .db-mini-btn.ok:hover { background: var(--brand-success); color: var(--brand-accent-contrast); }
        .db-mini-btn.no { background: var(--brand-danger-tint); color: var(--brand-danger); }
        .db-mini-btn.no:hover { background: var(--brand-danger); color: var(--brand-accent-contrast); }

        .db-date {
            width: 2.9rem;
            flex-shrink: 0;
            text-align: center;
            border-radius: 0.5rem;
            border: 1px solid var(--db-line);
            overflow: hidden;
            line-height: 1;
        }

        .db-date span { display: block; font-size: var(--db-fs-2xs); text-transform: uppercase; font-weight: 700; padding: 0.18rem 0; background: var(--brand-surface-alt); color: var(--brand-muted); letter-spacing: 0.04em; }
        .db-date strong { display: block; font-size: 0.95rem; padding: 0.25rem 0 0.28rem; }
        .db-date.is-today span { background: var(--brand-accent); color: var(--brand-accent-contrast); }

        .db-live {
            position: relative;
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 50%;
            background: var(--brand-success);
            flex-shrink: 0;
        }

        .db-live::after {
            content: '';
            position: absolute;
            inset: -0.2rem;
            border-radius: 50%;
            border: 2px solid var(--brand-success);
            opacity: 0;
            animation: db-ping 1.8s ease-out infinite;
        }

        .db-live.is-idle { background: var(--brand-info); }
        .db-live.is-idle::after { border-color: var(--brand-info); }
        .db-live.is-paused { background: var(--brand-danger); }
        .db-live.is-paused::after { display: none; }

        @keyframes db-ping {
            0% { transform: scale(0.6); opacity: 0.7; }
            100% { transform: scale(1.5); opacity: 0; }
        }

        @media (prefers-reduced-motion: reduce) {
            .db-live::after { animation: none; }
        }

        .db-avatar {
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 50%;
            background: var(--brand-surface-alt);
            border: 1px solid var(--db-line);
            color: var(--brand-text);
            font-size: var(--db-fs-2xs);
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* ---------- Horizontal bars ---------- */
        .db-bar-row {
            display: grid;
            grid-template-columns: minmax(5.5rem, 8rem) 1fr 2.2rem;
            align-items: center;
            gap: 0.6rem;
            font-size: var(--db-fs-sm);
            padding: 0.32rem 0;
        }

        .db-bar-row .label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 0.45rem; }
        .db-bar-row .count { text-align: right; font-weight: 700; }
        .db-bar-track { height: 0.55rem; border-radius: 999px; background: var(--brand-surface-alt); display: flex; gap: 2px; overflow: hidden; }
        .db-bar-track > span { display: block; height: 100%; border-radius: 999px; min-width: 0.3rem; }

        /* One column in a narrow panel, two once the panel spans the full row. */
        .db-load-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); column-gap: 1.75rem; }
        .db-load-row { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 0.6rem; padding: 0.42rem 0; border-bottom: 1px dashed var(--db-line); }
        .db-load-name { font-size: var(--db-fs-sm); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 0.25rem; }
        .db-load-row .count { font-size: var(--db-fs-xs); color: var(--brand-muted); white-space: nowrap; text-align: right; }
        .db-load-row .count strong { color: var(--brand-text); font-size: var(--db-fs-sm); }

        /* ---------- Columns (score distribution) ---------- */
        .db-cols { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: clamp(0.4rem, 0.2rem + 1vw, 1rem); height: clamp(8.5rem, 7rem + 4vw, 10.5rem); align-items: end; padding-top: 1.1rem; }
        .db-col { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 0.3rem; }
        .db-col-bar { width: min(100%, 2.6rem); border-radius: 0.3rem 0.3rem 0 0; background: var(--brand-accent); min-height: 2px; transition: opacity 0.15s ease; }
        .db-col-bar.is-zero { background: var(--db-line); }
        .db-col:hover .db-col-bar { opacity: 0.8; }
        .db-col-count { font-size: var(--db-fs-xs); font-weight: 700; }
        .db-col-axis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: clamp(0.4rem, 0.2rem + 1vw, 1rem); border-top: 1px solid var(--db-line); padding-top: 0.35rem; }
        .db-col-axis span { text-align: center; font-size: var(--db-fs-2xs); color: var(--brand-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* ---------- Tooltip ---------- */
        .db-tip {
            position: fixed;
            z-index: 1080;
            pointer-events: none;
            background: var(--brand-text);
            color: var(--brand-surface);
            font-size: var(--db-fs-xs);
            line-height: 1.35;
            padding: 0.4rem 0.55rem;
            border-radius: 0.45rem;
            box-shadow: var(--brand-shadow-lifted);
            opacity: 0;
            transform: translate(-50%, calc(-100% - 10px));
            transition: opacity 0.1s ease;
            white-space: nowrap;
        }

        .db-tip.is-on { opacity: 1; }

        /* Needs Attention blink — the animation itself is the shared
           .focus-flash in theme-head; these only keep its contents legible. */
        .db-panel.focus-flash .db-panel-title svg { color: var(--brand-danger); }
        .db-panel.focus-flash .db-pill { background-color: var(--brand-surface); }
        .db-tip b { font-weight: 700; }
        .db-tip .tip-row { display: flex; align-items: center; gap: 0.4rem; }
        .db-tip .tip-row .db-key { outline: 1.5px solid var(--brand-surface); }
    </style>
@endpush

@section('content')
    @php
        $icons = [
            'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/>',
            'radio' => '<circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49M7.76 16.24a6 6 0 0 1 0-8.49M19.07 4.93a10 10 0 0 1 0 14.14M4.93 19.07a10 10 0 0 1 0-14.14"/>',
            'user-check' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="m17 11 2 2 4-4"/>',
            'award' => '<circle cx="12" cy="8" r="7"/><path d="M8.21 13.89 7 23l5-3 5 3-1.21-9.12"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
            'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
            'pie' => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
            'alert' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
            'swap' => '<path d="m17 1 4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
            'layers' => '<path d="m12 2 10 5-10 5L2 7l10-5z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/>',
            'bar' => '<path d="M12 20V10M18 20V4M6 20v-4"/>',
            'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
            'chevron' => '<path d="m9 18 6-6-6-6"/>',
            'up' => '<path d="m18 15-6-6-6 6"/>',
            'down' => '<path d="m6 9 6 6 6-6"/>',
            'check' => '<path d="M20 6 9 17l-5-5"/>',
            'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
            'monitor' => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
            'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
            'send' => '<path d="m22 2-7 20-4-9-9-4 20-7z"/>',
        ];
        $icon = fn (string $name) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';

        $spark = function ($values) {
            $values = collect($values)->values();
            $max = max(1, $values->max());
            $n = max(1, $values->count() - 1);
            $points = $values->map(fn ($v, $i) => round($i / $n * 100, 2) . ',' . round(28 - ($v / $max) * 24, 2))->implode(' ');

            return '<svg class="db-spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">'
                . '<polygon points="0,30 ' . $points . ' 100,30" fill="var(--brand-accent)" opacity="0.1"/>'
                . '<polyline points="' . $points . '" fill="none" stroke="var(--brand-accent)" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>'
                . '</svg>';
        };

        $duration = function (?int $seconds) {
            if ($seconds === null) {
                return null;
            }
            $m = intdiv($seconds, 60);
            $s = $seconds % 60;

            return match (true) {
                $m === 0 => $s . 's',
                $s === 0 => $m . 'm',
                default => $m . 'm ' . $s . 's',
            };
        };

        // A closed-registration category whose setup is done displays as
        // "Queue Generated"/"Awaiting Groups" instead of Setup Incomplete, so
        // it must not carry the warning tone either.
        $categoryTone = fn (?string $code, string $label) => match (true) {
            $code === 'ACTIVE' => 'success',
            $code === 'REGISTRATION_OPEN' => 'info',
            $code === 'SETUP_INCOMPLETE' && $label === 'Setup Incomplete' => 'danger',
            $code === 'UPCOMING', $code === 'SETUP_INCOMPLETE' => 'accent',
            default => 'muted',
        };

        $outcomeColor = fn (string $code) => match ($code) {
            'PASS_NO_REVISION' => 'var(--brand-success)',
            'PASS_MINOR_REVISION' => 'var(--brand-info)',
            'PASS_MAJOR_REVISION' => 'var(--brand-accent)',
            'RE_DEFENSE' => 'var(--brand-muted)',
            'FAILED' => 'var(--brand-danger)',
            default => 'var(--brand-control-border)',
        };

        $pipelineColor = fn (string $code) => match ($code) {
            'SCHEDULED', 'QUEUED', 'READY_NEXT' => 'var(--brand-info)',
            'CALLED', 'ONGOING', 'PAUSED' => 'var(--brand-accent)',
            'COMPLETED' => 'var(--brand-success)',
            'DEFERRED', 'ABSENT', 'CANCELLED' => 'var(--brand-danger)',
            default => 'var(--brand-muted)',
        };

        $toneVar = fn (string $tone) => $tone === 'accent' ? 'var(--brand-accent)' : 'var(--brand-' . $tone . ')';

        $groups = $stats['groups'];
        $groupDelta = $groups['previousWeek'] > 0
            ? (int) round(($groups['week'] - $groups['previousWeek']) / $groups['previousWeek'] * 100)
            : null;

        $outcomeTotal = $outcomes->sum('count');
        $pipelineMax = max(1, $pipeline->max('count'));
        $scoreMax = max(1, $scoreDistribution->max('count'));
        $workloadMax = max(1, $panelistWorkload->max(fn ($p) => $p['upcoming'] + $p['completed']));
        $attentionItems = $pendingSubstitutions->count() + $otherNotifications->count();
    @endphp

    <div class="db">
        {{-- Header --}}
        <div class="db-head">
            <div>
                <div class="db-eyebrow">{!! $icon('calendar') !!} {{ now()->format('l, F j, Y') }}</div>
                <h1 class="db-greeting">{{ $greeting }}, {{ $firstName }}</h1>
                <div class="db-context">
                    @if ($academicYear)
                        <span class="db-chip">{!! $icon('book') !!} A.Y. {{ $academicYear->name }}</span>
                    @endif
                    @if ($semester)
                        <span class="db-chip">{{ $semester->name }}</span>
                    @endif
                    @if ($college)
                        <span class="db-chip" title="{{ $college->name }}">{{ $college->code ?: $college->name }}</span>
                    @endif
                </div>
            </div>

            <div class="db-actions">
                @if ($needsAttentionCount > 0)
                    <a href="#db-attention" class="db-btn">{!! $icon('bell') !!} Needs attention <span class="db-count db-num">{{ $needsAttentionCount }}</span></a>
                @endif
                <a href="{{ route('admin.reports.index') }}" class="db-btn">{!! $icon('bar') !!} Reports</a>
                <a href="{{ route('admin.live-monitoring.index') }}" class="db-btn is-primary">{!! $icon('monitor') !!} Event Control</a>
            </div>
        </div>

        {{-- Stat strip --}}
        <section class="db-stats" aria-label="Key figures">
            <div class="db-stat">
                <div class="db-stat-top"><span class="db-ico sm tone-accent">{!! $icon('users') !!}</span><span class="db-stat-label">Registered Groups</span></div>
                <div class="db-stat-value-row">
                    <span class="db-stat-value db-num">{{ number_format($groups['value']) }}</span>
                    @if ($groupDelta !== null)
                        <span class="db-delta {{ $groupDelta > 0 ? 'up' : ($groupDelta < 0 ? 'down' : 'flat') }}">
                            @if ($groupDelta !== 0){!! $icon($groupDelta > 0 ? 'up' : 'down') !!}@endif{{ abs($groupDelta) }}%
                        </span>
                    @endif
                </div>
                <div class="db-stat-sub">+{{ $groups['week'] }} in the last 7 days</div>
                {!! $spark($groups['spark']) !!}
            </div>

            <div class="db-stat">
                <div class="db-stat-top"><span class="db-ico sm tone-success">{!! $icon('check-circle') !!}</span><span class="db-stat-label">Presentations Done</span></div>
                <div class="db-stat-value-row">
                    <span class="db-stat-value db-num">{{ number_format($stats['presentations']['value']) }}</span>
                    <span class="db-stat-of db-num">/ {{ number_format($stats['presentations']['total']) }}</span>
                </div>
                <div class="db-stat-sub">{{ $stats['presentations']['today'] }} completed today</div>
                {!! $spark($stats['presentations']['spark']) !!}
            </div>

            <div class="db-stat">
                <div class="db-stat-top"><span class="db-ico sm tone-info">{!! $icon('radio') !!}</span><span class="db-stat-label">Rooms Live</span></div>
                <div class="db-stat-value-row">
                    <span class="db-stat-value db-num">{{ $stats['rooms']['value'] }}</span>
                    @if ($stats['rooms']['value'] > 0)
                        <span class="db-live" aria-hidden="true"></span>
                    @endif
                </div>
                <div class="db-stat-sub">{{ $stats['rooms']['presenting'] }} presenting now</div>
                <div class="db-meter"><span style="width: {{ $stats['rooms']['value'] > 0 ? round($stats['rooms']['presenting'] / $stats['rooms']['value'] * 100) : 0 }}%; background: var(--brand-info);"></span></div>
            </div>

            <div class="db-stat">
                <div class="db-stat-top"><span class="db-ico sm tone-accent">{!! $icon('user-check') !!}</span><span class="db-stat-label">Active Panelists</span></div>
                <div class="db-stat-value-row">
                    <span class="db-stat-value db-num">{{ $stats['panelists']['value'] }}</span>
                    <span class="db-stat-of db-num">/ {{ $stats['panelists']['total'] }}</span>
                </div>
                <div class="db-stat-sub">{{ $stats['panelists']['assigned'] }} with upcoming panels</div>
                <div class="db-meter"><span style="width: {{ $stats['panelists']['total'] > 0 ? round($stats['panelists']['assigned'] / $stats['panelists']['total'] * 100) : 0 }}%;"></span></div>
            </div>

            <div class="db-stat">
                <div class="db-stat-top"><span class="db-ico sm tone-success">{!! $icon('award') !!}</span><span class="db-stat-label">Avg. Group Score</span></div>
                <div class="db-stat-value-row">
                    <span class="db-stat-value db-num">{{ $stats['score']['value'] !== null ? number_format($stats['score']['value'], 1) . '%' : '—' }}</span>
                </div>
                <div class="db-stat-sub">{{ number_format($stats['score']['evaluations']) }} {{ Str::plural('evaluation', $stats['score']['evaluations']) }}</div>
                <div class="db-meter"><span style="width: {{ min(100, $stats['score']['value'] ?? 0) }}%; background: var(--brand-success);"></span></div>
            </div>

            <div class="db-stat">
                <div class="db-stat-top"><span class="db-ico sm tone-info">{!! $icon('clock') !!}</span><span class="db-stat-label">Avg. Presentation</span></div>
                <div class="db-stat-value-row">
                    <span class="db-stat-value db-num">{{ $duration($stats['duration']['value']) ?? '—' }}</span>
                </div>
                <div class="db-stat-sub">
                    @if ($stats['duration']['planned'])
                        of {{ $duration($stats['duration']['planned']) }} slot
                    @else
                        No runs yet
                    @endif
                </div>
                <div class="db-meter"><span style="width: {{ $stats['duration']['planned'] ? min(100, round($stats['duration']['value'] / $stats['duration']['planned'] * 100)) : 0 }}%; background: var(--brand-info);"></span></div>
            </div>
        </section>

        <div class="db-grid">
            {{-- Activity --}}
            <section class="db-panel span-8">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('activity') !!} Activity</h2>
                    <div class="db-seg" role="tablist" aria-label="Range">
                        <button type="button" data-range="7">7D</button>
                        <button type="button" data-range="14" class="is-active">14D</button>
                        <button type="button" data-range="30">30D</button>
                    </div>
                </div>
                <div class="db-panel-body db-activity-body">
                    <div class="db-totals">
                        <div>
                            <div class="db-total-label"><span class="db-key line" style="background: var(--brand-info);"></span> Registrations</div>
                            <div class="db-total-value db-num" data-total="registered">0</div>
                        </div>
                        <div>
                            <div class="db-total-label"><span class="db-key line" style="background: var(--brand-accent);"></span> Presentations Completed</div>
                            <div class="db-total-value db-num" data-total="completed">0</div>
                        </div>
                    </div>
                    <div class="db-chart" id="db-activity-chart" role="img" aria-label="Registrations and completed presentations per day"></div>
                </div>
            </section>

            {{-- Outcomes --}}
            <section class="db-panel span-4">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('pie') !!} Presentation Outcomes</h2>
                </div>
                <div class="db-panel-body db-outcomes">
                    @if ($outcomeTotal > 0)
                        @php
                            $radius = 42;
                            $circumference = 2 * M_PI * $radius;
                            $offset = 0;
                            $segments = $outcomes->where('count', '>', 0)->values();
                            $gap = $segments->count() > 1 ? 1.6 : 0;
                        @endphp
                        <div class="db-donut-wrap">
                            <div class="db-donut">
                                <svg viewBox="0 0 100 100" aria-hidden="true">
                                    <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="var(--brand-surface-alt)" stroke-width="12"></circle>
                                    @foreach ($segments as $segment)
                                        @php
                                            $length = $segment['count'] / $outcomeTotal * $circumference;
                                            $visible = max(0.5, $length - $gap);
                                        @endphp
                                        <circle cx="50" cy="50" r="{{ $radius }}" fill="none"
                                                stroke="{{ $outcomeColor($segment['code']) }}" stroke-width="12"
                                                stroke-dasharray="{{ round($visible, 3) }} {{ round($circumference - $visible, 3) }}"
                                                stroke-dashoffset="{{ round(-$offset, 3) }}"
                                                data-tip="<b>{{ e($segment['label']) }}</b><br>{{ $segment['count'] }} {{ Str::plural('group', $segment['count']) }} · {{ round($segment['count'] / $outcomeTotal * 100) }}%"></circle>
                                        @php $offset += $length; @endphp
                                    @endforeach
                                </svg>
                                <div class="db-donut-center">
                                    <strong class="db-num">{{ $outcomeTotal }}</strong>
                                    <span>Completed</span>
                                </div>
                            </div>
                            <div class="db-rows">
                                @foreach ($outcomes as $row)
                                    <div class="db-legend-row">
                                        <span class="db-key" style="background: {{ $outcomeColor($row['code']) }};"></span>
                                        <span class="name" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                                        <span class="count db-num">{{ $row['count'] }}</span>
                                        <span class="pct db-num">{{ round($row['count'] / $outcomeTotal * 100) }}%</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="db-empty">{!! $icon('pie') !!} No completed presentations yet</div>
                    @endif
                </div>
            </section>

            {{-- Categories --}}
            <section class="db-panel span-8">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('grid') !!} Categories</h2>
                    <a href="{{ route('admin.categories.index') }}" class="db-panel-link">Presentation Setup {!! $icon('arrow-right') !!}</a>
                </div>
                <div class="db-panel-body pb-1">
                    @if ($categoryRows->isNotEmpty())
                        <div class="db-table-wrap">
                            <table class="db-table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th class="text-end">Groups</th>
                                        <th>Progress</th>
                                        <th class="db-hide-sm">Next Day</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($categoryRows as $row)
                                        @php
                                            $tone = $categoryTone($row['code'], $row['status']);
                                            $pct = $row['attempts'] > 0 ? round($row['completed'] / $row['attempts'] * 100) : 0;
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="db-cat">
                                                    <span class="db-ico sm tone-{{ $tone }}">{!! $icon('layers') !!}</span>
                                                    <div class="min-w-0">
                                                        <a href="{{ route('admin.categories.show', $row['model']) }}" title="{{ $row['model']->name }}">{{ $row['model']->name }}</a>
                                                        <small>{{ $row['mode'] }} · {{ $row['days'] }} {{ Str::plural('day', $row['days']) }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="db-status"><span class="db-dot" style="background: {{ $toneVar($tone) }};"></span>{{ $row['status'] }}</span></td>
                                            <td class="text-end db-num fw-semibold">{{ $row['groups'] }}</td>
                                            <td>
                                                <div class="db-progress" @if ($row['attempts'] > 0) data-tip="<b>{{ e($row['model']->name) }}</b><br>{{ $row['completed'] }} of {{ $row['attempts'] }} presentations resolved" @endif>
                                                    <span class="db-progress-track"><span style="width: {{ $pct }}%; {{ $pct === 100 ? 'background: var(--brand-success);' : '' }}"></span></span>
                                                    <small class="db-num">{{ $row['attempts'] > 0 ? $pct . '%' : '—' }}</small>
                                                </div>
                                            </td>
                                            <td class="db-hide-sm text-nowrap">
                                                @if ($row['nextDate'])
                                                    {{ $row['nextDate']->isToday() ? 'Today' : $row['nextDate']->format('M j') }}
                                                @else
                                                    <span class="text-brand-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="db-empty">{!! $icon('grid') !!} No categories yet</div>
                    @endif
                </div>
            </section>

            {{-- Needs attention --}}
            <section class="db-panel span-4" id="db-attention">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('bell') !!} Needs Attention</h2>
                    @if ($needsAttentionCount > 0)
                        <span class="db-pill badge-danger-tint db-num">{{ $needsAttentionCount }} open</span>
                    @endif
                </div>
                <div class="db-panel-body pt-1">
                    @if ($attentionItems > 0)
                        <ul class="db-list">
                            @foreach ($pendingSubstitutions as $request)
                                @php
                                    $reqGroup = $request->presentationAttempt?->researchGroup;
                                    $nameOf = fn ($u) => $u ? (trim(($u->profile->first_name ?? '') . ' ' . ($u->profile->last_name ?? '')) ?: $u->username) : null;
                                    $original = $nameOf($request->originalPanelist);
                                    $substitute = $nameOf($request->requestedSubstitute);
                                @endphp
                                <li>
                                    @php
                                        $reportTitle = $substitute ? $substitute . ' → replace ' . $original : $original . ($request->originalPanelist?->trashed() ? ' was deleted' : ' is unavailable');
                                        $reportSub = $reqGroup?->group_reference . ' · ' . $reqGroup?->category?->name . ' · ' . $request->created_at?->diffForHumans(null, true, true);
                                    @endphp
                                    @if (! $substitute && $reqGroup?->category)
                                        {{-- Substitute-less unavailability report — same full-row-clickable,
                                             hover-title style as every other Needs Attention item (no
                                             separate arrow button), landing on the group's blinking row in
                                             Group & Panel Assignment. --}}
                                        <a href="{{ \App\Support\FocusLink::to(route('admin.panel-assignments.show', $reqGroup->category), ['attempt-' . $request->presentation_attempt_id]) }}" class="db-item">
                                            <span class="db-ico sm tone-accent">{!! $icon('swap') !!}</span>
                                            <div class="db-item-main">
                                                <div class="db-item-title">{{ $reportTitle }}</div>
                                                <div class="db-item-sub">{{ $reportSub }}</div>
                                            </div>
                                        </a>
                                    @else
                                        <div class="db-item">
                                            <span class="db-ico sm tone-accent">{!! $icon('swap') !!}</span>
                                            <div class="db-item-main">
                                                <div class="db-item-title">{{ $reportTitle }}</div>
                                                <div class="db-item-sub">{{ $reportSub }}</div>
                                            </div>
                                            <form method="POST" action="{{ route('admin.panel-substitutions.approve', $request) }}" class="m-0">
                                                @csrf
                                                <button type="submit" class="db-mini-btn ok" title="Confirm" aria-label="Confirm substitution">{!! $icon('check') !!}</button>
                                            </form>
                                            <button type="button" class="db-mini-btn no" title="Reject" aria-label="Reject substitution" data-bs-toggle="modal" data-bs-target="#db-reject-{{ $request->id }}">{!! $icon('x') !!}</button>
                                        </div>
                                    @endif
                                </li>
                            @endforeach

                            @foreach ($otherNotifications as $item)
                                @php
                                    [$itemIcon, $itemTone] = match ($item['type']) {
                                        'PRESENTATION_DATE_OVERDUE' => ['calendar', 'danger'],
                                        'EVENT_AUTO_END_BLOCKED' => ['alert', 'danger'],
                                        'CATEGORY_UNSCHEDULED_GROUPS' => ['users', 'accent'],
                                        'GROUP_DEFERRED' => ['clock', 'info'],
                                        default => ['bell', 'muted'],
                                    };
                                @endphp
                                <li>
                                    @if ($item['link'])<a href="{{ $item['link'] }}" class="db-item">@else<div class="db-item">@endif
                                        <span class="db-ico sm tone-{{ $itemTone }}">{!! $icon($itemIcon) !!}</span>
                                        <div class="db-item-main">
                                            <div class="db-item-title">{{ $item['title'] }}</div>
                                            <div class="db-item-sub" title="{{ $item['message'] }}">{{ $item['message'] }}</div>
                                        </div>
                                        <span class="db-item-meta">{{ $item['createdAt'] }}</span>
                                    @if ($item['link'])</a>@else</div>@endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="db-empty">{!! $icon('check-circle') !!} All caught up</div>
                    @endif
                </div>
            </section>

            {{-- Live rooms --}}
            <section class="db-panel span-4 pair">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('radio') !!} Live Rooms</h2>
                    <a href="{{ route('admin.live-monitoring.index') }}" class="db-panel-link">Event Control {!! $icon('arrow-right') !!}</a>
                </div>
                <div class="db-panel-body pt-1">
                    @if ($liveSessions->isNotEmpty())
                        <ul class="db-list">
                            @foreach ($liveSessions as $session)
                                @php
                                    $dotClass = match (true) {
                                        $session['statusCode'] === 'PAUSED' => 'is-paused',
                                        in_array($session['groupStatusCode'], ['ONGOING'], true) => '',
                                        default => 'is-idle',
                                    };
                                @endphp
                                <li>
                                    <a href="{{ route('admin.live-monitoring.show', $session['category']) }}?date={{ $session['dateId'] }}" class="db-item">
                                        <span class="db-live {{ $dotClass }}" aria-hidden="true"></span>
                                        <div class="db-item-main">
                                            <div class="db-item-title">{{ $session['room'] }} · {{ $session['category']->name }}</div>
                                            <div class="db-item-sub">
                                                @if ($session['group'])
                                                    {{ $session['group']->group_reference }} · {{ $session['groupStatus'] }}@if ($session['startedAt']) since {{ $session['startedAt']->format('g:i A') }}@endif
                                                @else
                                                    {{ $session['status'] }}
                                                @endif
                                            </div>
                                        </div>
                                        <span class="db-item-meta db-num">{{ $session['done'] }}/{{ $session['total'] }}</span>
                                        {!! str_replace('<svg ', '<svg class="db-item-chev" ', $icon('chevron')) !!}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="db-empty">{!! $icon('radio') !!} No rooms running</div>
                    @endif
                </div>
            </section>

            {{-- Upcoming days --}}
            <section class="db-panel span-4 pair">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('calendar') !!} Upcoming Days</h2>
                </div>
                <div class="db-panel-body pt-1">
                    @if ($upcomingDates->isNotEmpty())
                        <ul class="db-list">
                            @foreach ($upcomingDates as $date)
                                @php
                                    $activeRooms = $date->presentationDateRooms->filter(fn ($r) => $r->roomUseStatus?->code !== 'REMOVED');
                                    $scheduled = $activeRooms->sum('attempt_schedules_count');
                                    $running = $date->isRunning();
                                    [$pillLabel, $pillClass] = match (true) {
                                        $running => ['Running', 'badge-success-tint'],
                                        $date->isOverdue() => ['Overdue', 'badge-danger-tint'],
                                        $date->eventDateStatus?->code === 'STANDBY' => ['Standby', 'badge-info-tint'],
                                        default => ['Upcoming', 'badge-muted-tint'],
                                    };
                                @endphp
                                <li>
                                    <a href="{{ route('admin.live-monitoring.show', $date->category) }}?date={{ $date->id }}" class="db-item">
                                        <span class="db-date {{ $date->presentation_date->isToday() ? 'is-today' : '' }}">
                                            <span>{{ $date->presentation_date->isToday() ? 'Today' : $date->presentation_date->format('M') }}</span>
                                            <strong class="db-num">{{ $date->presentation_date->format('j') }}</strong>
                                        </span>
                                        <div class="db-item-main">
                                            <div class="db-item-title">{{ $date->category->name }}</div>
                                            <div class="db-item-sub">
                                                {{ \Carbon\Carbon::parse($date->event_start_time)->format('g:i A') }}–{{ \Carbon\Carbon::parse($date->event_end_time)->format('g:i A') }}
                                                · {{ $activeRooms->count() }} {{ Str::plural('room', $activeRooms->count()) }}
                                                · {{ $scheduled }} {{ Str::plural('group', $scheduled) }}
                                            </div>
                                        </div>
                                        <span class="db-pill {{ $pillClass }}">{{ $pillLabel }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="db-empty">{!! $icon('calendar') !!} No upcoming days</div>
                    @endif
                </div>
            </section>

            {{-- Panelist workload --}}
            <section class="db-panel span-4 wide">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('user-check') !!} Panelist Workload</h2>
                    <div class="db-legend">
                        <span><span class="db-key" style="background: var(--brand-accent);"></span>Done</span>
                        <span><span class="db-key" style="background: var(--brand-info);"></span>Upcoming</span>
                    </div>
                </div>
                <div class="db-panel-body pt-1">
                    @if ($panelistWorkload->isNotEmpty())
                        <div class="db-load-list">
                        @foreach ($panelistWorkload as $panelist)
                            @php $load = $panelist['upcoming'] + $panelist['completed']; @endphp
                            <div class="db-load-row" data-tip="<b>{{ e($panelist['name']) }}</b><div class='tip-row'><span class='db-key' style='background: var(--brand-accent)'></span>{{ $panelist['completed'] }} done</div><div class='tip-row'><span class='db-key' style='background: var(--brand-info)'></span>{{ $panelist['upcoming'] }} upcoming</div>">
                                <span class="db-avatar">{{ $panelist['initials'] }}</span>
                                <div>
                                    <div class="db-load-name">{{ $panelist['name'] }}</div>
                                    <div class="db-bar-track" style="width: {{ max(8, round($load / $workloadMax * 100)) }}%;">
                                        @if ($panelist['completed'] > 0)<span style="flex: {{ $panelist['completed'] }}; background: var(--brand-accent);"></span>@endif
                                        @if ($panelist['upcoming'] > 0)<span style="flex: {{ $panelist['upcoming'] }}; background: var(--brand-info);"></span>@endif
                                    </div>
                                </div>
                                <span class="count db-num"><strong>{{ $load }}</strong></span>
                            </div>
                        @endforeach
                        </div>
                    @else
                        <div class="db-empty">{!! $icon('user-check') !!} No panels assigned yet</div>
                    @endif
                </div>
            </section>

            {{-- Queue pipeline --}}
            <section class="db-panel span-6">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('layers') !!} Queue Pipeline</h2>
                    <span class="db-stat-sub db-num">{{ $stats['presentations']['total'] }} {{ Str::plural('attempt', $stats['presentations']['total']) }}</span>
                </div>
                <div class="db-panel-body pt-2">
                    @if ($pipeline->isNotEmpty())
                        @foreach ($pipeline as $row)
                            <div class="db-bar-row" data-tip="<b>{{ e($row['label']) }}</b><br>{{ $row['count'] }} of {{ $stats['presentations']['total'] }} · {{ round($row['count'] / max(1, $stats['presentations']['total']) * 100) }}%">
                                <span class="label"><span class="db-dot" style="background: {{ $pipelineColor($row['code']) }};"></span>{{ $row['label'] }}</span>
                                <span class="db-bar-track"><span style="width: {{ round($row['count'] / $pipelineMax * 100) }}%; background: {{ $pipelineColor($row['code']) }};"></span></span>
                                <span class="count db-num">{{ $row['count'] }}</span>
                            </div>
                        @endforeach
                    @else
                        <div class="db-empty">{!! $icon('layers') !!} No queue generated yet</div>
                    @endif
                </div>
            </section>

            {{-- Score distribution --}}
            <section class="db-panel span-6">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('award') !!} Score Distribution</h2>
                    <span class="db-stat-sub db-num">{{ $scoredAttempts }} {{ Str::plural('scored presentation', $scoredAttempts) }}</span>
                </div>
                <div class="db-panel-body pt-1">
                    @if ($scoredAttempts > 0)
                        <div class="db-cols">
                            @foreach ($scoreDistribution as $bin)
                                @php $h = $bin['count'] > 0 ? max(4, round($bin['count'] / $scoreMax * 100)) : 0; @endphp
                                <div class="db-col" data-tip="<b>{{ $bin['label'] }}%</b><br>{{ $bin['count'] }} {{ Str::plural('presentation', $bin['count']) }}">
                                    <span class="db-col-count db-num">{{ $bin['count'] }}</span>
                                    <span class="db-col-bar {{ $bin['count'] === 0 ? 'is-zero' : '' }}" style="height: {{ $h }}%;"></span>
                                </div>
                            @endforeach
                        </div>
                        <div class="db-col-axis">
                            @foreach ($scoreDistribution as $bin)
                                <span>{{ $bin['label'] }}</span>
                            @endforeach
                        </div>
                    @else
                        <div class="db-empty">{!! $icon('award') !!} No evaluations submitted yet</div>
                    @endif
                </div>
            </section>
        </div>
    </div>

    {{-- Reject substitution modals (outside the list so they can't inherit its layout) --}}
    @foreach ($pendingSubstitutions->filter(fn ($r) => $r->requestedSubstitute) as $request)
        <div class="modal fade" id="db-reject-{{ $request->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.panel-substitutions.reject', $request) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Reject Substitution Request</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">
                                This will log {{ trim(($request->requestedSubstitute->profile->first_name ?? '') . ' ' . ($request->requestedSubstitute->profile->last_name ?? '')) ?: $request->requestedSubstitute->username }}
                                out of the terminal they're currently connected to.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="x" /> Reject Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    <div class="db-tip" id="db-tip" role="tooltip"></div>
@endsection

@push('scripts')
    <script>
        (function () {
            var activity = @json($activity->values());
            var chart = document.getElementById('db-activity-chart');
            var tip = document.getElementById('db-tip');
            var range = 14;
            var SVG = 'http://www.w3.org/2000/svg';

            function showTip(html, x, y) {
                tip.innerHTML = html;
                tip.classList.add('is-on');
                var half = tip.offsetWidth / 2 + 8;
                tip.style.left = Math.min(Math.max(x, half), window.innerWidth - half) + 'px';
                tip.style.top = Math.max(y, tip.offsetHeight + 16) + 'px';
            }

            function hideTip() {
                tip.classList.remove('is-on');
            }

            // Shared hover tooltip for every [data-tip] mark on the page.
            document.querySelectorAll('.db [data-tip]').forEach(function (el) {
                el.addEventListener('mousemove', function (e) { showTip(el.dataset.tip, e.clientX, e.clientY); });
                el.addEventListener('mouseleave', hideTip);
            });

            function el(name, attrs) {
                var node = document.createElementNS(SVG, name);
                Object.keys(attrs).forEach(function (k) { node.setAttribute(k, attrs[k]); });
                return node;
            }

            // Four integer ticks: the step is 1, 2 or 5 times a power of ten.
            function niceMax(peak) {
                var raw = Math.max(1, peak) / 4;
                var magnitude = Math.pow(10, Math.floor(Math.log10(raw)));
                var step = [1, 2, 5, 10].map(function (m) { return m * magnitude; }).find(function (v) { return v >= raw; });
                return Math.max(1, Math.ceil(step)) * 4;
            }

            function draw() {
                if (!chart) return;
                var data = activity.slice(-range);
                var width = chart.clientWidth;
                var height = chart.clientHeight;
                if (!width || !height) return;

                document.querySelector('[data-total="registered"]').textContent = data.reduce(function (s, d) { return s + d.registered; }, 0);
                document.querySelector('[data-total="completed"]').textContent = data.reduce(function (s, d) { return s + d.completed; }, 0);

                var pad = { top: 8, right: 6, bottom: 22, left: 26 };
                var innerW = width - pad.left - pad.right;
                var innerH = height - pad.top - pad.bottom;
                var peak = Math.max.apply(null, data.map(function (d) { return Math.max(d.registered, d.completed); }));
                var max = niceMax(peak);
                var n = data.length;
                var x = function (i) { return pad.left + (n > 1 ? i / (n - 1) : 0.5) * innerW; };
                var y = function (v) { return pad.top + innerH - (v / max) * innerH; };

                var svg = el('svg', { viewBox: '0 0 ' + width + ' ' + height, width: width, height: height });

                // Gridlines + y ticks
                for (var t = 0; t <= 4; t++) {
                    var value = max / 4 * t;
                    var gy = Math.round(y(value)) + 0.5;
                    svg.appendChild(el('line', { x1: pad.left, x2: width - pad.right, y1: gy, y2: gy, stroke: 'var(--brand-border)', 'stroke-width': 1 }));
                    var label = el('text', { x: pad.left - 7, y: gy + 3.5, 'text-anchor': 'end' });
                    label.textContent = value;
                    svg.appendChild(label);
                }

                // X labels (about six, always including the last day)
                var every = Math.max(1, Math.ceil(n / Math.max(2, Math.min(6, Math.floor(innerW / 70)))));
                data.forEach(function (d, i) {
                    if ((n - 1 - i) % every !== 0) return;
                    var anchor = i === 0 ? 'start' : (i === n - 1 ? 'end' : 'middle');
                    var text = el('text', { x: x(i), y: height - 5, 'text-anchor': anchor });
                    text.textContent = d.label;
                    svg.appendChild(text);
                });

                var series = [
                    { key: 'registered', color: 'var(--brand-info)', name: 'Registrations' },
                    { key: 'completed', color: 'var(--brand-accent)', name: 'Presentations' }
                ];

                series.forEach(function (s) {
                    var pts = data.map(function (d, i) { return x(i).toFixed(1) + ',' + y(d[s.key]).toFixed(1); });
                    svg.appendChild(el('polygon', {
                        points: x(0).toFixed(1) + ',' + y(0) + ' ' + pts.join(' ') + ' ' + x(n - 1).toFixed(1) + ',' + y(0),
                        fill: s.color, opacity: 0.08
                    }));
                    svg.appendChild(el('polyline', {
                        points: pts.join(' '), fill: 'none', stroke: s.color,
                        'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round'
                    }));
                });

                // Hover layer
                var guide = el('line', { y1: pad.top, y2: pad.top + innerH, stroke: 'var(--brand-control-border)', 'stroke-width': 1, opacity: 0 });
                svg.appendChild(guide);
                var dots = series.map(function (s) {
                    var dot = el('circle', { r: 4.5, fill: s.color, stroke: 'var(--brand-surface)', 'stroke-width': 2, opacity: 0 });
                    svg.appendChild(dot);
                    return dot;
                });
                var hit = el('rect', { x: pad.left, y: pad.top, width: innerW, height: innerH, fill: 'transparent' });
                svg.appendChild(hit);

                function move(evt) {
                    var rect = svg.getBoundingClientRect();
                    var px = (evt.touches ? evt.touches[0].clientX : evt.clientX) - rect.left;
                    var i = Math.round((px - pad.left) / innerW * (n - 1));
                    i = Math.max(0, Math.min(n - 1, i));
                    var d = data[i];
                    var gx = x(i);
                    guide.setAttribute('x1', gx);
                    guide.setAttribute('x2', gx);
                    guide.setAttribute('opacity', 1);
                    series.forEach(function (s, k) {
                        dots[k].setAttribute('cx', gx);
                        dots[k].setAttribute('cy', y(d[s.key]));
                        dots[k].setAttribute('opacity', 1);
                    });
                    var top = y(Math.max(d.registered, d.completed));
                    showTip(
                        '<b>' + d.label + '</b>' + series.map(function (s) {
                            return '<div class="tip-row"><span class="db-key" style="background:' + s.color + '"></span>' + s.name + ' <b style="margin-left:auto;padding-left:.6rem">' + d[s.key] + '</b></div>';
                        }).join(''),
                        rect.left + gx,
                        rect.top + top
                    );
                }

                function leave() {
                    guide.setAttribute('opacity', 0);
                    dots.forEach(function (dot) { dot.setAttribute('opacity', 0); });
                    hideTip();
                }

                hit.addEventListener('mousemove', move);
                hit.addEventListener('touchstart', move, { passive: true });
                hit.addEventListener('touchmove', move, { passive: true });
                hit.addEventListener('mouseleave', leave);
                hit.addEventListener('touchend', leave);

                chart.replaceChildren(svg);
            }

            // Needs attention: scroll the card into view, then blink it
            // (window.focusFlash comes from partials/focus-flash-script).
            var attention = document.getElementById('db-attention');

            document.querySelectorAll('a[href="#db-attention"]').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (window.focusFlash) window.focusFlash([attention]);
                });
            });

            if (window.location.hash === '#db-attention') {
                window.addEventListener('load', function () {
                    if (window.focusFlash) window.focusFlash([attention]);
                });
            }

            document.querySelectorAll('.db-seg [data-range]').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.querySelectorAll('.db-seg [data-range]').forEach(function (b) { b.classList.remove('is-active'); });
                    button.classList.add('is-active');
                    range = parseInt(button.dataset.range, 10);
                    draw();
                });
            });

            if (window.ResizeObserver && chart) {
                new ResizeObserver(draw).observe(chart);
            } else {
                window.addEventListener('resize', draw);
            }
            draw();
        })();
    </script>
@endpush
