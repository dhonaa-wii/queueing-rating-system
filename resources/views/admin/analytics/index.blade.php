@extends('layouts.admin')

@section('title', 'Schedule Analytics')
@section('heading', 'Analytics')

{{--
    Schedule Analytics — configured plan vs what actually happened.

    Sizing/responsiveness follows Presentation Setup's shell (user-directed
    2026-09-17): the page is wrapped in `.page-shell`, so it inherits the
    centered 85% column and the --page-fs* clamp tokens, and only adds the few
    tokens a chart page needs on top. The chart vocabulary (panel grid, stat
    strip, bar rows, columns, one shared [data-tip] tooltip) is deliberately the
    same one the Dashboard established, scoped under `.an` so the two never
    collide.
--}}

@push('styles')
    <style>
        .an {
            --an-fs-2xs: clamp(0.63rem, 0.6rem + 0.1vw, 0.68rem);
            --an-fs-value: clamp(1.05rem, 0.88rem + 0.6vw, 1.35rem);
            --an-radius: 0.75rem;
            --an-gap: clamp(0.7rem, 0.5rem + 0.6vw, 1.05rem);
            --an-line: var(--brand-border);
        }

        .an * { min-width: 0; }
        .an-num { font-variant-numeric: tabular-nums; }

        /* ---------- Header ---------- */
        .an-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem 1rem;
            margin-bottom: var(--an-gap);
        }

        .an-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: var(--an-fs-2xs);
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--brand-muted);
            margin-bottom: 0.3rem;
        }

        .an-eyebrow svg { width: 0.8rem; height: 0.8rem; }

        .an-title {
            font-size: clamp(1.05rem, 0.9rem + 0.55vw, 1.35rem);
            font-weight: 700;
            margin: 0 0 0.25rem;
        }

        .an-sub {
            font-size: var(--page-fs-sm);
            color: var(--brand-muted);
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.3rem 0.55rem;
        }

        .an-dot-sep { width: 3px; height: 3px; border-radius: 999px; background: var(--brand-muted); opacity: 0.6; }

        .an-head-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.45rem; }
        .an-head-actions .form-select { max-width: 15rem; }

        .an-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: var(--an-fs-2xs);
            font-weight: 700;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            background: var(--brand-surface-alt);
            color: var(--brand-muted);
            white-space: nowrap;
        }

        .an-pill svg { width: 0.72rem; height: 0.72rem; }
        .an-pill.warn { background: var(--brand-danger-tint); color: var(--brand-danger); }

        /* ---------- Recommendation ---------- */
        .an-reco {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            padding: 0.7rem 0.85rem;
            border-radius: var(--an-radius);
            background: var(--brand-accent-tint);
            margin-bottom: var(--an-gap);
        }

        .an-reco-ico {
            width: 1.8rem;
            height: 1.8rem;
            border-radius: 0.5rem;
            display: grid;
            place-items: center;
            background: var(--brand-accent);
            color: var(--brand-surface);
            flex-shrink: 0;
        }

        .an-reco-ico svg { width: 0.95rem; height: 0.95rem; }
        .an-reco-body { flex: 1; font-size: var(--page-fs-sm); }
        .an-reco-body strong { color: var(--brand-accent); }
        .an-reco-title { font-weight: 700; margin-bottom: 0.1rem; }
        .an-reco-meta { font-size: var(--page-fs-xs); color: var(--brand-muted); margin-top: 0.15rem; }

        /* ---------- Stat strip ---------- */
        .an-stats {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            background: var(--brand-surface);
            border: 1px solid var(--an-line);
            border-radius: var(--an-radius);
            overflow: hidden;
            margin-bottom: var(--an-gap);
        }

        @media (max-width: 1399.98px) { .an-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 575.98px) { .an-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

        .an-stat {
            padding: 0.75rem 0.85rem 0.8rem;
            border-right: 1px solid var(--an-line);
            border-bottom: 1px solid var(--an-line);
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .an-stat:last-child { border-right: 0; }
        @media (min-width: 1400px) { .an-stat { border-bottom: 0; } }

        .an-stat-top { display: flex; align-items: center; gap: 0.5rem; }

        .an-ico {
            width: 1.6rem;
            height: 1.6rem;
            border-radius: 0.45rem;
            display: grid;
            place-items: center;
            background: var(--brand-accent-tint);
            color: var(--brand-accent);
            flex-shrink: 0;
        }

        .an-ico svg { width: 0.85rem; height: 0.85rem; }
        .an-ico.info { background: var(--brand-info-tint); color: var(--brand-info); }
        .an-ico.success { background: var(--brand-success-tint); color: var(--brand-success); }
        .an-ico.danger { background: var(--brand-danger-tint); color: var(--brand-danger); }

        .an-stat-label {
            font-size: var(--an-fs-2xs);
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--brand-muted);
            line-height: 1.25;
        }

        .an-stat-value-row { display: flex; align-items: baseline; flex-wrap: wrap; gap: 0.2rem 0.4rem; }
        .an-stat-value { font-size: var(--an-fs-value); font-weight: 700; line-height: 1.1; }
        .an-stat-sub { font-size: var(--page-fs-xs); color: var(--brand-muted); }

        .an-delta {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            font-size: var(--an-fs-2xs);
            font-weight: 700;
            padding: 0.08rem 0.35rem;
            border-radius: 999px;
        }

        .an-delta svg { width: 0.6rem; height: 0.6rem; }
        .an-delta.under { background: var(--brand-info-tint); color: var(--brand-info); }
        .an-delta.over { background: var(--brand-danger-tint); color: var(--brand-danger); }
        .an-delta.flat { background: var(--brand-surface-alt); color: var(--brand-muted); }

        .an-meter { height: 0.3rem; border-radius: 999px; background: var(--brand-surface-alt); overflow: hidden; margin-top: auto; }
        .an-meter > span { display: block; height: 100%; border-radius: 999px; background: var(--brand-accent); }

        /* ---------- Panels ---------- */
        .an-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: var(--an-gap); }
        .an-grid .span-8 { grid-column: span 8; }
        .an-grid .span-6 { grid-column: span 6; }
        .an-grid .span-4 { grid-column: span 4; }

        @media (max-width: 1199.98px) {
            .an-grid .span-8, .an-grid .span-6, .an-grid .span-4 { grid-column: span 12; }
        }

        .an-panel {
            background: var(--brand-surface);
            border: 1px solid var(--an-line);
            border-radius: var(--an-radius);
            display: flex;
            flex-direction: column;
        }

        .an-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.4rem 0.75rem;
            padding: 0.75rem 0.9rem 0;
        }

        .an-panel-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
            font-size: var(--page-fs-heading);
            font-weight: 700;
        }

        .an-panel-title svg { width: 0.95rem; height: 0.95rem; color: var(--brand-muted); }
        .an-panel-note { font-size: var(--page-fs-xs); color: var(--brand-muted); }
        .an-panel-body { padding: 0.7rem 0.9rem 0.85rem; flex: 1; }

        .an-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1.6rem 0.5rem;
            color: var(--brand-muted);
            font-size: var(--page-fs-sm);
            height: 100%;
        }

        .an-empty svg { width: 1rem; height: 1rem; }

        /* ---------- Configured vs actual ---------- */
        .an-cmp-row {
            display: grid;
            grid-template-columns: minmax(7rem, 12rem) 1fr auto;
            align-items: center;
            gap: 0.4rem 0.75rem;
            padding: 0.45rem 0;
            border-bottom: 1px dashed var(--an-line);
        }

        .an-cmp-row:last-child { border-bottom: 0; }
        .an-cmp-name { font-size: var(--page-fs-sm); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .an-cmp-meta { font-size: var(--an-fs-2xs); color: var(--brand-muted); }

        /* The track is the configured slot; the fill is the median actual, so a
           bar that stops short is a slot longer than the groups need. */
        .an-cmp-track {
            position: relative;
            height: 0.6rem;
            border-radius: 999px;
            background: var(--brand-surface-alt);
            overflow: hidden;
        }

        .an-cmp-fill { position: absolute; inset: 0 auto 0 0; border-radius: 999px; background: var(--brand-accent); }
        .an-cmp-fill.over { background: var(--brand-danger); }
        .an-cmp-values { font-size: var(--page-fs-xs); color: var(--brand-muted); white-space: nowrap; text-align: right; }
        .an-cmp-values strong { color: var(--brand-text); font-size: var(--page-fs-sm); }

        /* ---------- Columns ---------- */
        .an-cols { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: clamp(0.3rem, 0.15rem + 0.8vw, 0.8rem); height: clamp(7.5rem, 6rem + 4vw, 9.5rem); align-items: end; padding-top: 1rem; }
        .an-col { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 0.25rem; }
        .an-col-bar { width: min(100%, 2.4rem); border-radius: 0.3rem 0.3rem 0 0; background: var(--brand-accent); min-height: 2px; transition: opacity 0.15s ease; }
        .an-col-bar.is-over { background: var(--brand-danger); }
        .an-col-bar.is-zero { background: var(--an-line); }
        .an-col:hover .an-col-bar { opacity: 0.8; }
        .an-col-count { font-size: var(--page-fs-xs); font-weight: 700; }
        .an-col-axis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: clamp(0.3rem, 0.15rem + 0.8vw, 0.8rem); border-top: 1px solid var(--an-line); padding-top: 0.3rem; }
        .an-col-axis span { text-align: center; font-size: var(--an-fs-2xs); color: var(--brand-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* ---------- Stacked split ---------- */
        .an-split { display: flex; height: 2rem; border-radius: 0.45rem; overflow: hidden; background: var(--brand-surface-alt); }
        .an-split > span { display: block; height: 100%; transition: opacity 0.15s ease; }
        .an-split > span:hover { opacity: 0.85; }
        .an-split-legend { margin-top: 0.9rem; }
        .an-split-item {
            display: grid;
            grid-template-columns: 1fr auto auto;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px dashed var(--an-line);
        }
        .an-split-item:last-child { border-bottom: 0; }
        .an-split-key { display: inline-flex; align-items: center; gap: 0.5rem; font-size: var(--page-fs-sm); }
        .an-key { width: 0.6rem; height: 0.6rem; border-radius: 0.2rem; display: inline-block; flex-shrink: 0; }
        .an-split-value { font-size: var(--page-fs); font-weight: 700; white-space: nowrap; }
        .an-split-pct { font-size: var(--page-fs-xs); color: var(--brand-muted); min-width: 3rem; text-align: right; }

        /* ---------- Bars ---------- */
        .an-bar-row {
            display: grid;
            grid-template-columns: minmax(5rem, 9rem) 1fr 3rem;
            align-items: center;
            gap: 0.6rem;
            font-size: var(--page-fs-sm);
            padding: 0.32rem 0;
        }

        .an-bar-row .label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .an-bar-row .count { text-align: right; font-weight: 700; }
        .an-bar-track { height: 0.5rem; border-radius: 999px; background: var(--brand-surface-alt); overflow: hidden; }
        .an-bar-track > span { display: block; height: 100%; border-radius: 999px; background: var(--brand-accent); min-width: 0.2rem; }

        /* ---------- Table ---------- */
        .an-table-wrap { overflow-x: auto; }
        .an-table { width: 100%; border-collapse: collapse; font-size: var(--page-fs-sm); }
        .an-table th {
            text-align: left;
            font-size: var(--an-fs-2xs);
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--brand-muted);
            padding: 0 0.5rem 0.4rem;
            white-space: nowrap;
        }

        .an-table td { padding: 0.45rem 0.5rem; border-top: 1px solid var(--an-line); white-space: nowrap; }
        .an-table th:first-child, .an-table td:first-child { padding-left: 0; }
        .an-table th:last-child, .an-table td:last-child { padding-right: 0; text-align: right; }
        .an-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .an-table .muted { color: var(--brand-muted); font-size: var(--page-fs-xs); }
        .an-util { display: inline-flex; align-items: center; gap: 0.4rem; justify-content: flex-end; }
        .an-util-meter { width: 3rem; height: 0.3rem; border-radius: 999px; background: var(--brand-surface-alt); overflow: hidden; }
        .an-util-meter > span { display: block; height: 100%; background: var(--brand-accent); }

        /* ---------- Tooltip ---------- */
        .an-tip {
            position: fixed;
            z-index: 1080;
            pointer-events: none;
            background: var(--brand-text);
            color: var(--brand-surface);
            font-size: var(--page-fs-xs);
            line-height: 1.35;
            padding: 0.4rem 0.55rem;
            border-radius: 0.45rem;
            box-shadow: var(--brand-shadow-lifted);
            opacity: 0;
            transform: translate(-50%, calc(-100% - 10px));
            transition: opacity 0.1s ease;
            white-space: nowrap;
        }

        .an-tip.is-on { opacity: 1; }
        .an-tip b { font-weight: 700; }
    </style>
@endpush

@section('content')
    @php
        $icons = [
            'chart' => '<path d="M3 3v18h18"></path><path d="M18 17V9M13 17V5M8 17v-3"></path>',
            'trend' => '<path d="M22 7 13.5 15.5 8.5 10.5 2 17"></path><path d="M16 7h6v6"></path>',
            'timer' => '<circle cx="12" cy="13" r="8"></circle><path d="M12 9v4l2 2"></path><path d="M9 2h6"></path>',
            'clock' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>',
            'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path>',
            'door' => '<path d="M3 21h18"></path><path d="M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17"></path><path d="M14 12h.01"></path>',
            'gauge' => '<path d="m12 14 4-4"></path><path d="M3.34 19a10 10 0 1 1 17.32 0"></path>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>',
            'bulb' => '<path d="M9 18h6"></path><path d="M10 22h4"></path><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"></path>',
            'down' => '<path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path>',
            'up' => '<path d="M12 19V5"></path><path d="m5 12 7-7 7 7"></path>',
            'layers' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m6.08 11-3.5 1.6a1 1 0 0 0 0 1.83l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9a1 1 0 0 0 0-1.83L17.9 11"></path>',
            'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path>',
            'pie' => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path>',
            'info' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path>',
            'arrow-right' => '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>',
        ];
        $icon = fn (string $name) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';

        // Compact duration: seconds under a minute, m/s under an hour, h/m above.
        $dur = function (?int $seconds) {
            if ($seconds === null) {
                return '—';
            }
            if ($seconds < 60) {
                return $seconds . 's';
            }
            if ($seconds < 3600) {
                $m = intdiv($seconds, 60);
                $s = $seconds % 60;

                return $s === 0 ? $m . 'm' : $m . 'm ' . $s . 's';
            }
            $h = intdiv($seconds, 3600);
            $m = intdiv($seconds % 3600, 60);

            return $m === 0 ? $h . 'h' : $h . 'h ' . $m . 'm';
        };

        $hours = fn (?int $seconds) => $seconds === null ? '—' : number_format($seconds / 3600, 1);

        $stats = $data['stats'];
        $split = $data['time_split'];
        $durationMax = max(1, $data['duration_bands']->max('count'));
        $lagMax = max(1, $data['lag_bands']->max('count'));
        $roomMax = max(1, $data['rooms']->max('utilization_pct') ?? 1);
    @endphp

    <div class="page-shell an">
        <div class="an-head">
            <div>
                <span class="an-eyebrow">{!! $icon('chart') !!} Reports &amp; Analytics</span>
                <h1 class="an-title">Schedule Analytics</h1>
                <p class="an-sub">
                    <span>{{ $selected?->name ?? 'All categories' }}</span>
                    <span class="an-dot-sep"></span>
                    <span class="an-num">{{ $stats['presentations'] }}</span>
                    <span>{{ Str::plural('presentation', $stats['presentations']) }} measured</span>
                    <span class="an-dot-sep"></span>
                    <span class="an-num">{{ $stats['days'] }}</span>
                    <span>{{ Str::plural('day', $stats['days']) }} run</span>
                    @if ($data['thin_sample'])
                        <span class="an-pill warn">{!! $icon('alert') !!} Small sample</span>
                    @endif
                </p>
            </div>

            <div class="an-head-actions">
                <form method="GET" action="{{ route('admin.analytics.index') }}">
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Filter by category">
                        <option value="">All categories</option>
                        @foreach ($categories as $option)
                            <option value="{{ $option->id }}" @selected($selected?->id === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('admin.reports.index') }}" class="btn btn-sm btn-outline-brand">
                    <x-icon name="file-text" /> Reports
                </a>
            </div>
        </div>

        @if (! $data['has_data'])
            <div class="an-panel">
                <div class="an-empty">{!! $icon('chart') !!} No presentation day has run yet</div>
            </div>
        @else
            @if ($data['recommendation'])
                @php $reco = $data['recommendation']; @endphp
                <div class="an-reco">
                    <span class="an-reco-ico">{!! $icon('bulb') !!}</span>
                    <div class="an-reco-body">
                        <div class="an-reco-title">Duration per group looks {{ $reco['direction'] === 'shorter' ? 'too long' : 'too short' }}</div>
                        <div>
                            90% of presentations finished within <strong>{{ $reco['suggested'] }} minutes</strong>,
                            against a configured slot of <strong>{{ $reco['configured'] }} minutes</strong>.
                        </div>
                        <div class="an-reco-meta">
                            Based on {{ $reco['sample'] }} measured {{ Str::plural('presentation', $reco['sample']) }}.
                            @if ($selected)
                                <a href="{{ route('admin.categories.show', $selected) }}#tab-schedules">Open Schedules setup</a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- ---------- Stat strip ---------- --}}
            <div class="an-stats">
                <div class="an-stat">
                    <div class="an-stat-top">
                        <span class="an-ico">{!! $icon('users') !!}</span>
                        <span class="an-stat-label">Presentations<br>Measured</span>
                    </div>
                    <div class="an-stat-value-row">
                        <span class="an-stat-value an-num">{{ $stats['presentations'] }}</span>
                        <span class="an-stat-sub an-num">{{ $stats['groups'] }} {{ Str::plural('group', $stats['groups']) }}</span>
                    </div>
                </div>

                <div class="an-stat">
                    <div class="an-stat-top">
                        <span class="an-ico info">{!! $icon('timer') !!}</span>
                        <span class="an-stat-label">Median<br>Duration</span>
                    </div>
                    <div class="an-stat-value-row">
                        <span class="an-stat-value an-num">{{ $dur($stats['median_actual']) }}</span>
                        @if ($stats['delta_pct'] !== null)
                            @php $d = $stats['delta_pct']; @endphp
                            <span class="an-delta {{ $d > 0 ? 'over' : ($d < 0 ? 'under' : 'flat') }}">
                                {!! $icon($d > 0 ? 'up' : 'down') !!}{{ abs($d) }}%
                            </span>
                        @endif
                    </div>
                    <span class="an-stat-sub">of {{ $dur($stats['configured']) }} slot</span>
                </div>

                <div class="an-stat">
                    <div class="an-stat-top">
                        <span class="an-ico">{!! $icon('clock') !!}</span>
                        <span class="an-stat-label">Hours<br>Used</span>
                    </div>
                    <div class="an-stat-value-row">
                        <span class="an-stat-value an-num">{{ $hours($stats['presenting_seconds'] + $stats['paused_seconds']) }}</span>
                        <span class="an-stat-sub">h</span>
                    </div>
                    <span class="an-stat-sub an-num">of {{ $hours($stats['booked_seconds']) }} h booked</span>
                </div>

                <div class="an-stat">
                    <div class="an-stat-top">
                        <span class="an-ico {{ ($stats['utilization_pct'] ?? 0) >= 60 ? 'success' : 'danger' }}">{!! $icon('gauge') !!}</span>
                        <span class="an-stat-label">Room<br>Utilization</span>
                    </div>
                    <div class="an-stat-value-row">
                        <span class="an-stat-value an-num">{{ $stats['utilization_pct'] ?? '—' }}<span class="an-stat-sub">%</span></span>
                    </div>
                    <div class="an-meter"><span style="width: {{ min(100, $stats['utilization_pct'] ?? 0) }}%;"></span></div>
                </div>

                <div class="an-stat">
                    <div class="an-stat-top">
                        <span class="an-ico">{!! $icon('calendar') !!}</span>
                        <span class="an-stat-label">Days<br>Run</span>
                    </div>
                    <div class="an-stat-value-row">
                        <span class="an-stat-value an-num">{{ $stats['days'] }}</span>
                        <span class="an-stat-sub an-num">{{ $stats['room_days'] }} room-{{ Str::plural('day', $stats['room_days']) }}</span>
                    </div>
                    <span class="an-stat-sub an-num">{{ $stats['rooms'] }} {{ Str::plural('room', $stats['rooms']) }}</span>
                </div>

                <div class="an-stat">
                    <div class="an-stat-top">
                        <span class="an-ico info">{!! $icon('door') !!}</span>
                        <span class="an-stat-label">Call to<br>Start</span>
                    </div>
                    <div class="an-stat-value-row">
                        <span class="an-stat-value an-num">{{ $dur($stats['median_lag']) }}</span>
                    </div>
                    <span class="an-stat-sub">longest {{ $dur($stats['max_lag']) }}</span>
                </div>
            </div>

            {{-- ---------- Panels ---------- --}}
            <div class="an-grid">
                {{-- Configured vs actual, per category --}}
                <section class="an-panel span-8">
                    <div class="an-panel-head">
                        <h2 class="an-panel-title">{!! $icon('trend') !!} Configured vs Actual</h2>
                        <span class="an-panel-note">Median vs configured slot &middot; by {{ $data['comparison_by'] }}</span>
                    </div>
                    <div class="an-panel-body">
                        @forelse ($data['comparison'] as $row)
                            <div class="an-cmp-row" data-tip="<b>{{ e($row['name']) }}</b><br>Median {{ $dur($row['median']) }} of {{ $dur($row['configured']) }} slot<br>Longest {{ $dur($row['longest']) }} · {{ $row['over_slot'] }} over slot">
                                <div>
                                    <div class="an-cmp-name">{{ $row['name'] }}</div>
                                    <div class="an-cmp-meta an-num">{{ $row['count'] }} {{ Str::plural('presentation', $row['count']) }}</div>
                                </div>
                                <div class="an-cmp-track">
                                    <span class="an-cmp-fill {{ ($row['ratio_pct'] ?? 0) > 100 ? 'over' : '' }}" style="width: {{ min(100, max(1, $row['ratio_pct'] ?? 0)) }}%;"></span>
                                </div>
                                <div class="an-cmp-values">
                                    <strong class="an-num">{{ $dur($row['median']) }}</strong>
                                    <span class="an-num">/ {{ $dur($row['configured']) }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="an-empty">{!! $icon('trend') !!} No timed presentations yet</div>
                        @endforelse
                    </div>
                </section>

                {{-- How much of the slot each presentation used --}}
                <section class="an-panel span-4">
                    <div class="an-panel-head">
                        <h2 class="an-panel-title">{!! $icon('pie') !!} Slot Used</h2>
                    </div>
                    <div class="an-panel-body">
                        @if ($stats['presentations'] > 0)
                            <div class="an-cols">
                                @foreach ($data['duration_bands'] as $band)
                                    @php $h = $band['count'] > 0 ? max(4, round($band['count'] / $durationMax * 100)) : 0; @endphp
                                    <div class="an-col" data-tip="<b>{{ $band['label'] }}</b><br>{{ $band['count'] }} {{ Str::plural('presentation', $band['count']) }}">
                                        <span class="an-col-count an-num">{{ $band['count'] }}</span>
                                        <span class="an-col-bar {{ $band['count'] === 0 ? 'is-zero' : ($band['over'] ? 'is-over' : '') }}" style="height: {{ $h }}%;"></span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="an-col-axis">
                                @foreach ($data['duration_bands'] as $band)
                                    <span>{{ $band['label'] }}</span>
                                @endforeach
                            </div>
                        @else
                            <div class="an-empty">{!! $icon('pie') !!} No timed presentations yet</div>
                        @endif
                    </div>
                </section>

                {{-- Where booked room time actually went --}}
                <section class="an-panel span-6">
                    <div class="an-panel-head">
                        <h2 class="an-panel-title">{!! $icon('layers') !!} Where Room Time Goes</h2>
                        <span class="an-panel-note an-num">{{ $hours($stats['booked_seconds']) }} h booked</span>
                    </div>
                    <div class="an-panel-body">
                        @if ($split['total'] > 0)
                            <div class="an-split">
                                @foreach ($split['segments'] as $segment)
                                    @if ($segment['pct'] > 0)
                                        <span style="width: {{ $segment['pct'] }}%; background: {{ $segment['color'] }};"
                                              data-tip="<b>{{ $segment['label'] }}</b><br>{{ $hours($segment['seconds']) }} h · {{ $segment['pct'] }}%"></span>
                                    @endif
                                @endforeach
                            </div>
                            <div class="an-split-legend">
                                @foreach ($split['segments'] as $segment)
                                    <div class="an-split-item">
                                        <span class="an-split-key"><span class="an-key" style="background: {{ $segment['color'] }};"></span>{{ $segment['label'] }}</span>
                                        <span class="an-split-value an-num">{{ $hours($segment['seconds']) }} h</span>
                                        <span class="an-split-pct an-num">{{ $segment['pct'] }}%</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="an-empty">{!! $icon('layers') !!} No room session has run yet</div>
                        @endif
                    </div>
                </section>

                {{-- Call to start lag --}}
                <section class="an-panel span-6">
                    <div class="an-panel-head">
                        <h2 class="an-panel-title">{!! $icon('door') !!} Call to Start</h2>
                        <span class="an-panel-note an-num">{{ $stats['lag_count'] }} {{ Str::plural('presentation', $stats['lag_count']) }}</span>
                    </div>
                    <div class="an-panel-body">
                        @if ($stats['lag_count'] > 0)
                            <div class="an-cols">
                                @foreach ($data['lag_bands'] as $band)
                                    @php $h = $band['count'] > 0 ? max(4, round($band['count'] / $lagMax * 100)) : 0; @endphp
                                    <div class="an-col" data-tip="<b>{{ $band['label'] }}</b><br>{{ $band['count'] }} {{ Str::plural('presentation', $band['count']) }}">
                                        <span class="an-col-count an-num">{{ $band['count'] }}</span>
                                        <span class="an-col-bar {{ $band['count'] === 0 ? 'is-zero' : ($band['over'] ? 'is-over' : '') }}" style="height: {{ $h }}%;"></span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="an-col-axis">
                                @foreach ($data['lag_bands'] as $band)
                                    <span>{{ $band['label'] }}</span>
                                @endforeach
                            </div>
                        @else
                            <div class="an-empty">{!! $icon('door') !!} No call times recorded yet</div>
                        @endif
                    </div>
                </section>

                {{-- Per-day breakdown --}}
                <section class="an-panel span-8">
                    <div class="an-panel-head">
                        <h2 class="an-panel-title">{!! $icon('calendar') !!} Presentation Days</h2>
                        <span class="an-panel-note an-num">{{ $data['days']->count() }} {{ Str::plural('day', $data['days']->count()) }}</span>
                    </div>
                    <div class="an-panel-body">
                        @if ($data['days']->isNotEmpty())
                            <div class="an-table-wrap">
                                <table class="an-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Category</th>
                                            <th class="num">Rooms</th>
                                            <th class="num">Groups</th>
                                            <th class="num">Booked</th>
                                            <th class="num">Used</th>
                                            <th class="num">Median</th>
                                            <th>Utilization</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data['days'] as $day)
                                            <tr>
                                                <td>
                                                    {{ $day['date']->format('M j, Y') }}
                                                    @if ($day['over_seconds'])
                                                        <div class="muted">{{ $dur($day['over_seconds']) }} past plan</div>
                                                    @endif
                                                </td>
                                                <td>{{ $day['category'] }}</td>
                                                <td class="num an-num">{{ $day['rooms'] }}</td>
                                                <td class="num an-num">{{ $day['groups'] }}</td>
                                                <td class="num an-num">{{ $hours($day['booked_seconds']) }} h</td>
                                                <td class="num an-num">{{ $hours($day['used_seconds']) }} h</td>
                                                <td class="num an-num">{{ $dur($day['median']) }}</td>
                                                <td>
                                                    <span class="an-util">
                                                        <span class="an-util-meter"><span style="width: {{ min(100, $day['utilization_pct'] ?? 0) }}%;"></span></span>
                                                        <span class="an-num">{{ $day['utilization_pct'] ?? '—' }}%</span>
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="an-empty">{!! $icon('calendar') !!} No presentation day has run yet</div>
                        @endif
                    </div>
                </section>

                {{-- Per-room utilization --}}
                <section class="an-panel span-4">
                    <div class="an-panel-head">
                        <h2 class="an-panel-title">{!! $icon('door') !!} Room Utilization</h2>
                    </div>
                    <div class="an-panel-body">
                        @forelse ($data['rooms'] as $room)
                            <div class="an-bar-row" data-tip="<b>{{ e($room['name']) }}</b><br>{{ $room['groups'] }} {{ Str::plural('group', $room['groups']) }} over {{ $room['days'] }} room-{{ Str::plural('day', $room['days']) }}<br>{{ $hours($room['used_seconds']) }} h of {{ $hours($room['booked_seconds']) }} h">
                                <span class="label">{{ $room['name'] }}</span>
                                <span class="an-bar-track"><span style="width: {{ min(100, max(2, round(($room['utilization_pct'] ?? 0) / $roomMax * 100))) }}%;"></span></span>
                                <span class="count an-num">{{ $room['utilization_pct'] ?? '—' }}%</span>
                            </div>
                        @empty
                            <div class="an-empty">{!! $icon('door') !!} No room has run yet</div>
                        @endforelse
                    </div>
                </section>
            </div>
        @endif

        <div class="an-tip" id="an-tip" role="tooltip"></div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var tip = document.getElementById('an-tip');
            if (!tip) return;

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

            // One shared hover tooltip for every [data-tip] mark on the page,
            // same mechanism the Dashboard uses.
            document.querySelectorAll('.an [data-tip]').forEach(function (el) {
                el.addEventListener('mousemove', function (e) { showTip(el.dataset.tip, e.clientX, e.clientY); });
                el.addEventListener('mouseleave', hideTip);
            });
        })();
    </script>
@endpush
