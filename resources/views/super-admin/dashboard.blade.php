@extends('layouts.super-admin')

@section('title', 'Dashboard')

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

        .db-context { display: flex; flex-wrap: wrap; gap: 0.35rem; }

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

        .db-btn.is-primary { background: var(--brand-accent); color: var(--brand-accent-contrast); }

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

        .db-stat-value { font-size: var(--db-fs-value); font-weight: 700; letter-spacing: -0.02em; line-height: 1.1; }

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

        .db-meter {
            margin-top: auto;
            height: 0.3rem;
            border-radius: 999px;
            background: var(--brand-surface-alt);
            overflow: hidden;
        }

        .db-meter > span { display: block; height: 100%; border-radius: 999px; background: var(--brand-accent); }

        /* ---------- Panels ---------- */
        .db-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: var(--db-gap); }

        .span-8 { grid-column: span 8; }
        .span-6 { grid-column: span 6; }
        .span-4 { grid-column: span 4; }

        @media (max-width: 1199.98px) { .span-8, .span-4 { grid-column: span 12; } }
        @media (max-width: 767.98px) { .span-6 { grid-column: span 12; } }

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

        .db-panel-title { display: flex; align-items: center; gap: 0.5rem; margin: 0; font-size: var(--db-fs-title); font-weight: 700; }
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

        /* ---------- Donut ---------- */
        .db-outcomes { container-type: inline-size; }
        .db-donut-wrap { display: flex; align-items: center; gap: 1rem 1.5rem; height: 100%; }
        @container (max-width: 30rem) {
            .db-donut-wrap { flex-direction: column; gap: 0.75rem; }
            .db-donut-wrap .db-rows { flex: none; width: 100%; }
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
        .db-key { width: 0.55rem; height: 0.55rem; border-radius: 0.15rem; display: inline-block; flex-shrink: 0; }

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

        .db-status { display: inline-flex; align-items: center; gap: 0.35rem; white-space: nowrap; font-size: var(--db-fs-xs); font-weight: 600; }
        .db-dot { width: 0.45rem; height: 0.45rem; border-radius: 50%; flex-shrink: 0; display: inline-block; }

        .db-progress { display: flex; align-items: center; gap: 0.5rem; min-width: 7.5rem; }
        .db-progress-track { flex: 1; height: 0.35rem; border-radius: 999px; background: var(--brand-surface-alt); overflow: hidden; }
        .db-progress-track > span { display: block; height: 100%; border-radius: 999px; background: var(--brand-accent); }
        .db-progress small { color: var(--brand-muted); font-size: var(--db-fs-xs); white-space: nowrap; }

        @media (max-width: 575.98px) { .db-hide-sm { display: none; } }

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

        .db-list > li + li .db-item { border-top: 1px solid var(--db-line); }
        a.db-item:hover .db-item-title { color: var(--brand-accent); }

        .db-item-main { flex: 1; min-width: 0; }
        .db-item-title { font-weight: 600; font-size: var(--db-fs-sm); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .db-item-sub { font-size: var(--db-fs-xs); color: var(--brand-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .db-item-meta { font-size: var(--db-fs-xs); color: var(--brand-muted); white-space: nowrap; flex-shrink: 0; font-weight: 600; }
        .db-item-chev { width: 0.85rem; height: 0.85rem; color: var(--brand-muted); flex-shrink: 0; }

        /* ---------- Horizontal bars ---------- */
        .db-bar-row {
            display: grid;
            grid-template-columns: minmax(7rem, 11rem) 1fr 2.2rem;
            align-items: center;
            gap: 0.6rem;
            font-size: var(--db-fs-sm);
            padding: 0.32rem 0;
        }

        .db-bar-row .label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 0.45rem; }
        .db-bar-row .count { text-align: right; font-weight: 700; }
        .db-bar-track { height: 0.55rem; border-radius: 999px; background: var(--brand-surface-alt); overflow: hidden; }
        .db-bar-track > span { display: block; height: 100%; border-radius: 999px; min-width: 0.3rem; }

        /* ---------- Columns (audit activity) ---------- */
        .db-cols { display: grid; gap: clamp(0.2rem, 0.1rem + 0.4vw, 0.45rem); height: clamp(8.5rem, 7rem + 4vw, 10.5rem); align-items: end; padding-top: 1.1rem; }
        .db-col { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 0.3rem; }
        .db-col-bar { width: min(100%, 1.8rem); border-radius: 0.3rem 0.3rem 0 0; background: var(--brand-accent); min-height: 2px; transition: opacity 0.15s ease; }
        .db-col-bar.is-zero { background: var(--db-line); }
        .db-col:hover .db-col-bar { opacity: 0.8; }
        .db-col-count { font-size: var(--db-fs-2xs); font-weight: 700; }
        .db-col-axis { display: grid; gap: clamp(0.2rem, 0.1rem + 0.4vw, 0.45rem); border-top: 1px solid var(--db-line); padding-top: 0.35rem; }
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
        .db-tip b { font-weight: 700; }
    </style>
@endpush

@section('content')
    @php
        $icons = [
            'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'user-check' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="m17 11 2 2 4-4"/>',
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
            'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
            'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
            'pie' => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
            'list-check' => '<path d="M11 6h10M11 12h10M11 18h10M3 6l1.5 1.5L7 5M3 12l1.5 1.5L7 11M3 18l1.5 1.5L7 17"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>',
            'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
            'chevron' => '<path d="m9 18 6-6-6-6"/>',
            'up' => '<path d="m18 15-6-6-6 6"/>',
            'down' => '<path d="m6 9 6 6 6-6"/>',
        ];
        $icon = fn (string $name) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';

        $statusColor = fn (string $code) => match ($code) {
            'ACTIVE' => 'var(--brand-success)',
            'TEMPORARY_CREDENTIALS_ISSUED' => 'var(--brand-info)',
            'PASSWORD_RESET_REQUIRED' => 'var(--brand-accent)',
            'INACTIVE' => 'var(--brand-muted)',
            'LOCKED' => 'var(--brand-danger)',
            default => 'var(--brand-control-border)',
        };

        $auditDelta = $auditYesterday > 0 ? (int) round(($auditToday - $auditYesterday) / $auditYesterday * 100) : null;
        $auditMax = max(1, $auditDaily->max('count'));
        $adminDonutTotal = $adminBreakdown->sum('count');
        $panelistBarMax = max(1, $panelistBreakdown->max('count'));
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
                </div>
            </div>

            <div class="db-actions">
                @if ($lockedAdmins > 0)
                    <a href="{{ route('super-admin.administrators.index') }}" class="db-btn">{!! $icon('lock') !!} Locked accounts <span class="db-count db-num">{{ $lockedAdmins }}</span></a>
                @endif
                <a href="{{ route('super-admin.settings.application.index') }}" class="db-btn is-primary">{!! $icon('settings') !!} Application Settings</a>
            </div>
        </div>

        {{-- Stat strip --}}
        <section class="db-stats" aria-label="Key figures">
            <div class="db-stat">
                <div class="db-stat-top"><span class="db-ico sm tone-accent">{!! $icon('users') !!}</span><span class="db-stat-label">Admin Accounts</span></div>
                <div class="db-stat-value-row"><span class="db-stat-value db-num">{{ $adminTotal }}</span></div>
                <div class="db-stat-sub">{{ $adminActive }} active{{ $lockedAdmins > 0 ? ' · '.$lockedAdmins.' locked' : '' }}</div>
                <div class="db-meter"><span style="width: {{ $adminTotal ? round($adminActive / $adminTotal * 100) : 0 }}%;"></span></div>
            </div>

            <div class="db-stat">
                <div class="db-stat-top"><span class="db-ico sm tone-info">{!! $icon('user-check') !!}</span><span class="db-stat-label">Panelists</span></div>
                <div class="db-stat-value-row"><span class="db-stat-value db-num">{{ $panelistTotal }}</span></div>
                <div class="db-stat-sub">{{ $panelistActive }} active</div>
                <div class="db-meter"><span style="width: {{ $panelistTotal ? round($panelistActive / $panelistTotal * 100) : 0 }}%; background: var(--brand-info);"></span></div>
            </div>

            <div class="db-stat">
                <div class="db-stat-top"><span class="db-ico sm tone-success">{!! $icon('grid') !!}</span><span class="db-stat-label">Active Categories</span></div>
                <div class="db-stat-value-row">
                    <span class="db-stat-value db-num">{{ $activeCategories }}</span>
                    <span class="db-stat-of db-num">/ {{ $totalCategories }}</span>
                </div>
                <div class="db-stat-sub">not yet completed or cancelled</div>
                <div class="db-meter"><span style="width: {{ $totalCategories ? round($activeCategories / $totalCategories * 100) : 0 }}%; background: var(--brand-success);"></span></div>
            </div>

            <div class="db-stat">
                <div class="db-stat-top"><span class="db-ico sm tone-accent">{!! $icon('map-pin') !!}</span><span class="db-stat-label">Campuses</span></div>
                <div class="db-stat-value-row"><span class="db-stat-value db-num">{{ $campuses->count() }}</span></div>
                <div class="db-stat-sub">{{ $activeCampuses }} active</div>
                <div class="db-meter"><span style="width: {{ $campuses->count() ? round($activeCampuses / $campuses->count() * 100) : 0 }}%;"></span></div>
            </div>

            <div class="db-stat">
                <div class="db-stat-top"><span class="db-ico sm tone-info">{!! $icon('book') !!}</span><span class="db-stat-label">Colleges</span></div>
                <div class="db-stat-value-row"><span class="db-stat-value db-num">{{ $collegeTotal }}</span></div>
                <div class="db-stat-sub">{{ $activeColleges }} active</div>
                <div class="db-meter"><span style="width: {{ $collegeTotal ? round($activeColleges / $collegeTotal * 100) : 0 }}%; background: var(--brand-info);"></span></div>
            </div>

            <div class="db-stat">
                <div class="db-stat-top"><span class="db-ico sm tone-muted">{!! $icon('activity') !!}</span><span class="db-stat-label">Audit Events Today</span></div>
                <div class="db-stat-value-row">
                    <span class="db-stat-value db-num">{{ $auditToday }}</span>
                    @if ($auditDelta !== null)
                        <span class="db-delta {{ $auditDelta > 0 ? 'up' : ($auditDelta < 0 ? 'down' : 'flat') }}">
                            @if ($auditDelta !== 0){!! $icon($auditDelta > 0 ? 'up' : 'down') !!}@endif{{ abs($auditDelta) }}%
                        </span>
                    @endif
                </div>
                <div class="db-stat-sub">{{ $auditYesterday }} yesterday</div>
            </div>
        </section>

        <div class="db-grid">
            {{-- Audit activity --}}
            <section class="db-panel span-8">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('activity') !!} Audit Activity</h2>
                </div>
                <div class="db-panel-body">
                    <div class="db-cols" style="grid-template-columns: repeat({{ $auditDaily->count() }}, minmax(0, 1fr));">
                        @foreach ($auditDaily as $day)
                            <div class="db-col" data-tip="<b>{{ $day['label'] }}</b><br>{{ $day['count'] }} {{ Str::plural('event', $day['count']) }}">
                                <span class="db-col-count db-num">{{ $day['count'] > 0 ? $day['count'] : '' }}</span>
                                <div class="db-col-bar {{ $day['count'] === 0 ? 'is-zero' : '' }}" style="height: {{ $day['count'] > 0 ? max(4, round($day['count'] / $auditMax * 100)) : 2 }}%;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="db-col-axis" style="grid-template-columns: repeat({{ $auditDaily->count() }}, minmax(0, 1fr));">
                        @foreach ($auditDaily as $i => $day)
                            <span>{{ $i % 2 === 1 ? $day['label'] : '' }}</span>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Admin accounts by status --}}
            <section class="db-panel span-4">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('pie') !!} Admin Accounts</h2>
                </div>
                <div class="db-panel-body db-outcomes">
                    @if ($adminDonutTotal > 0)
                        @php
                            $radius = 42;
                            $circumference = 2 * M_PI * $radius;
                            $offset = 0;
                            $gap = $adminBreakdown->count() > 1 ? 1.6 : 0;
                        @endphp
                        <div class="db-donut-wrap">
                            <div class="db-donut">
                                <svg viewBox="0 0 100 100" aria-hidden="true">
                                    <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="var(--brand-surface-alt)" stroke-width="12"></circle>
                                    @foreach ($adminBreakdown as $row)
                                        @php
                                            $length = $row['count'] / $adminDonutTotal * $circumference;
                                            $visible = max(0.5, $length - $gap);
                                        @endphp
                                        <circle cx="50" cy="50" r="{{ $radius }}" fill="none"
                                                stroke="{{ $statusColor($row['code']) }}" stroke-width="12"
                                                stroke-dasharray="{{ round($visible, 3) }} {{ round($circumference - $visible, 3) }}"
                                                stroke-dashoffset="{{ round(-$offset, 3) }}"
                                                data-tip="<b>{{ e($row['label']) }}</b><br>{{ $row['count'] }} {{ Str::plural('account', $row['count']) }} · {{ round($row['count'] / $adminDonutTotal * 100) }}%"></circle>
                                        @php $offset += $length; @endphp
                                    @endforeach
                                </svg>
                                <div class="db-donut-center">
                                    <strong class="db-num">{{ $adminDonutTotal }}</strong>
                                    <span>Total</span>
                                </div>
                            </div>
                            <div class="db-rows">
                                @foreach ($adminBreakdown as $row)
                                    <div class="db-legend-row">
                                        <span class="db-key" style="background: {{ $statusColor($row['code']) }};"></span>
                                        <span class="name" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                                        <span class="count db-num">{{ $row['count'] }}</span>
                                        <span class="pct db-num">{{ round($row['count'] / $adminDonutTotal * 100) }}%</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="db-empty">{!! $icon('pie') !!} No admin accounts yet</div>
                    @endif
                </div>
            </section>

            {{-- Recent audit log --}}
            <section class="db-panel span-8">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('list-check') !!} Recent Activity</h2>
                    <a href="{{ route('super-admin.audit-logs.index') }}" class="db-btn">View All</a>
                </div>
                <div class="db-panel-body pb-1">
                    @if ($recentAuditLogs->isEmpty())
                        <div class="db-empty">{!! $icon('list-check') !!} No audit activity recorded yet</div>
                    @else
                        <div class="db-table-wrap">
                            <table class="db-table">
                                <thead>
                                    <tr>
                                        <th>When</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th class="db-hide-sm">Entity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentAuditLogs as $log)
                                        <tr>
                                            <td class="text-nowrap">{{ $log->created_at->format('M j, g:i A') }}</td>
                                            <td>{{ $log->user?->profile?->first_name ? $log->user->profile->first_name.' '.$log->user->profile->last_name : ($log->user?->username ?? 'System') }}</td>
                                            <td>{{ Str::of($log->action)->replace('_', ' ')->title() }}</td>
                                            <td class="db-hide-sm text-brand-muted">{{ class_basename($log->entity_type) }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Reference data --}}
            <section class="db-panel span-4">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('settings') !!} Reference Data</h2>
                </div>
                <div class="db-panel-body pb-0">
                    <ul class="db-list">
                        <li>
                            <a href="{{ route('super-admin.settings.application.index') }}" class="db-item">
                                <span class="db-ico sm tone-accent">{!! $icon('calendar') !!}</span>
                                <span class="db-item-main">
                                    <span class="db-item-title">{{ $academicYear->name ?? 'None active' }}</span>
                                    <span class="db-item-sub">Academic Year</span>
                                </span>
                                <span class="db-item-chev">{!! $icon('chevron') !!}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('super-admin.settings.application.index') }}" class="db-item">
                                <span class="db-ico sm tone-info">{!! $icon('calendar') !!}</span>
                                <span class="db-item-main">
                                    <span class="db-item-title">{{ $semester->name ?? 'None active' }}</span>
                                    <span class="db-item-sub">Semester</span>
                                </span>
                                <span class="db-item-chev">{!! $icon('chevron') !!}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('super-admin.settings.application.index') }}" class="db-item">
                                <span class="db-ico sm tone-accent">{!! $icon('map-pin') !!}</span>
                                <span class="db-item-main">
                                    <span class="db-item-title">{{ $campuses->count() }} {{ Str::plural('campus', $campuses->count()) }}</span>
                                    <span class="db-item-sub">{{ $activeCampuses }} active</span>
                                </span>
                                <span class="db-item-chev">{!! $icon('chevron') !!}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('super-admin.settings.application.index') }}" class="db-item">
                                <span class="db-ico sm tone-info">{!! $icon('book') !!}</span>
                                <span class="db-item-main">
                                    <span class="db-item-title">{{ $collegeTotal }} {{ Str::plural('college', $collegeTotal) }}</span>
                                    <span class="db-item-sub">{{ $activeColleges }} active</span>
                                </span>
                                <span class="db-item-chev">{!! $icon('chevron') !!}</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </section>

            {{-- Panelist accounts by status --}}
            <section class="db-panel span-6">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('user-check') !!} Panelist Accounts</h2>
                    <a href="{{ route('super-admin.panelists.index') }}" class="db-panel-link">View roster {!! $icon('arrow-right') !!}</a>
                </div>
                <div class="db-panel-body">
                    @if ($panelistBreakdown->isEmpty())
                        <div class="db-empty">{!! $icon('user-check') !!} No panelists registered yet</div>
                    @else
                        @foreach ($panelistBreakdown as $row)
                            <div class="db-bar-row" data-tip="<b>{{ e($row['label']) }}</b><br>{{ $row['count'] }} of {{ $panelistTotal }} · {{ round($row['count'] / max(1, $panelistTotal) * 100) }}%">
                                <span class="label"><span class="db-key" style="background: {{ $statusColor($row['code']) }};"></span>{{ $row['label'] }}</span>
                                <span class="db-bar-track"><span style="width: {{ round($row['count'] / $panelistBarMax * 100) }}%; background: {{ $statusColor($row['code']) }};"></span></span>
                                <span class="count db-num">{{ $row['count'] }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
            </section>

            {{-- Campus & college overview --}}
            <section class="db-panel span-6">
                <div class="db-panel-head">
                    <h2 class="db-panel-title">{!! $icon('map-pin') !!} Campus &amp; College Overview</h2>
                    <a href="{{ route('super-admin.settings.application.index') }}" class="db-panel-link">Manage {!! $icon('arrow-right') !!}</a>
                </div>
                <div class="db-panel-body pb-1">
                    @if ($campuses->isEmpty())
                        <div class="db-empty">{!! $icon('map-pin') !!} No campuses configured yet</div>
                    @else
                        <div class="db-table-wrap">
                            <table class="db-table">
                                <thead>
                                    <tr>
                                        <th>Campus</th>
                                        <th>Colleges</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($campuses as $campus)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $campus->name }}</div>
                                                <div class="text-brand-muted" style="font-size: var(--db-fs-xs);">{{ $campus->code }}</div>
                                            </td>
                                            <td>
                                                <div class="db-progress" @if ($campus->colleges_count > 0) data-tip="<b>{{ e($campus->name) }}</b><br>{{ $campus->active_colleges_count }} of {{ $campus->colleges_count }} colleges active" @endif>
                                                    <span class="db-progress-track"><span style="width: {{ $campus->colleges_count ? round($campus->active_colleges_count / $campus->colleges_count * 100) : 0 }}%;"></span></span>
                                                    <small class="db-num">{{ $campus->active_colleges_count }}/{{ $campus->colleges_count }}</small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="db-status">
                                                    <span class="db-dot" style="background: {{ $campus->is_active ? 'var(--brand-success)' : 'var(--brand-muted)' }};"></span>
                                                    {{ $campus->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <div class="db-tip" id="db-tip" role="tooltip"></div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var tip = document.getElementById('db-tip');

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

            document.querySelectorAll('.db [data-tip]').forEach(function (el) {
                el.addEventListener('mousemove', function (e) { showTip(el.dataset.tip, e.clientX, e.clientY); });
                el.addEventListener('mouseleave', hideTip);
            });
        })();
    </script>
@endpush
