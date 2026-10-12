@extends('layouts.panelist')

@section('title', 'Panelist Dashboard')
@section('heading', 'Dashboard')

{{-- Panelist Dashboard (redesigned 2026-10-11): the Admin dashboard's look —
     header, hairline stat strip, panels — with only what a panelist acts on:
     their own seats, their advisees, and their rooms while a day runs. --}}
@php
    $firstName = auth()->user()->profile->first_name ?? auth()->user()->username;
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

    // A date beside a time follows the time shown, not the room's planned day.
    $when = fn ($row) => match ($row['state']) {
        'scheduled' => $row['at'] ? $row['at']->format('M j') . ' · ' . $row['at']->format('g:i A') : '—',
        'deferred' => 'Deferred',
        'unqueued' => 'Not yet queued',
        default => \App\Models\AttemptSchedule::AWAITING_SHORT,
    };
    $outcomeTone = fn ($outcome) => $outcome?->is_successful
        ? 'badge-success-tint'
        // Re-Defense is stored is_successful = false but is not a fail.
        : ($outcome?->requires_new_attempt ? 'badge-info-tint' : 'badge-danger-tint');
    $evaluatedPct = $stats['presented'] > 0 ? min(100, (int) round($stats['evaluated'] / $stats['presented'] * 100)) : 0;
@endphp

@section('content')
    <div class="pd">
        @if ($categories->isEmpty())
            <div class="pd-panel pd-empty-page">
                <span class="pd-ico"><x-icon name="clipboard-check" /></span>
                <div class="fw-semibold">No assignments or advisees yet</div>
                <div class="text-brand-muted small">Groups you sit on or advise will show here.</div>
            </div>
        @else
            {{-- Header --}}
            <div class="pd-head">
                <div>
                    <div class="pd-eyebrow"><x-icon name="calendar" /> {{ now()->format('l, F j, Y') }}</div>
                    <h1 class="pd-greeting">{{ $greeting }}, {{ $firstName }}</h1>
                    <div class="pd-context">
                        <span class="pd-chip is-accent" title="{{ $categories->pluck('name')->join(', ') }}">{{ $categories->count() }} {{ Str::plural('category', $categories->count()) }}</span>
                    </div>
                </div>
            </div>

            {{-- Stat strip --}}
            <section class="pd-stats" aria-label="Key figures">
                <div class="pd-stat">
                    <div class="pd-stat-top"><span class="pd-ico sm"><x-icon name="clipboard-check" /></span><span class="pd-stat-label">Assigned Groups</span></div>
                    <div class="pd-stat-value">{{ $stats['seats'] }}</div>
                    <div class="pd-stat-sub">{{ $stats['toPresent'] }} still to present</div>
                </div>
                <div class="pd-stat">
                    <div class="pd-stat-top"><span class="pd-ico sm tone-success"><x-icon name="check" /></span><span class="pd-stat-label">Evaluated</span></div>
                    <div class="pd-stat-value-row"><span class="pd-stat-value">{{ $stats['evaluated'] }}</span><span class="pd-stat-of">/ {{ $stats['presented'] }} presented</span></div>
                    <div class="pd-meter" role="img" aria-label="{{ $evaluatedPct }}% evaluated"><span style="width: {{ $evaluatedPct }}%"></span></div>
                </div>
                <div class="pd-stat">
                    <div class="pd-stat-top"><span class="pd-ico sm tone-info"><x-icon name="clock" /></span><span class="pd-stat-label">Next Presentation</span></div>
                    @if ($nextUp)
                        <div class="pd-stat-value">{{ $nextUp['at']->format('g:i A') }}</div>
                        <div class="pd-stat-sub text-truncate">{{ $nextUp['at']->format('M j') }} · {{ $nextUp['room']?->room_name }} · {{ $nextUp['attempt']->researchGroup->group_reference }}</div>
                        @if ($nextUp['sessionEnd'])
                            <div class="pd-stat-sub text-brand-danger fw-semibold">{{ \App\Models\AttemptSchedule::PAST_SESSION_SHORT }}</div>
                        @endif
                    @else
                        <div class="pd-stat-value text-brand-muted">—</div>
                        <div class="pd-stat-sub">Nothing scheduled</div>
                    @endif
                </div>
                <div class="pd-stat">
                    <div class="pd-stat-top"><span class="pd-ico sm tone-brand"><x-icon name="graduation-cap" /></span><span class="pd-stat-label">Advisees</span></div>
                    <div class="pd-stat-value">{{ $stats['advisees'] }}</div>
                    <div class="pd-stat-sub">{{ $stats['adviseesPresented'] }} presented</div>
                </div>
            </section>

            <div class="pd-grid">
                {{-- Rooms running now — only while a day is live. --}}
                @if ($liveRooms->isNotEmpty())
                    <section class="pd-panel span-12">
                        <div class="pd-panel-head">
                            <h2 class="pd-panel-title"><span class="pd-live-dot" aria-hidden="true"></span> Live Now</h2>
                        </div>
                        <div class="pd-panel-body pd-live">
                            @foreach ($liveRooms as $live)
                                @php
                                    $preview = $live['preview'];
                                    $now = $preview['current'] ?? $preview['called'];
                                    $sessionCode = $live['session']?->roomSessionStatus?->code;
                                @endphp
                                <div class="pd-live-room">
                                    <div class="pd-live-name">
                                        <span class="fw-semibold text-truncate" title="{{ $live['room']->presentationDate?->category?->name }}">{{ $live['room']->room_name }} <span class="pd-row-meta">· {{ $live['room']->presentationDate?->category?->name }}</span></span>
                                        <span class="badge {{ $sessionCode === 'ACTIVE' ? 'badge-success-tint' : ($sessionCode === 'PAUSED' ? 'badge-danger-tint' : ($sessionCode === 'BREAK' ? 'badge-info-tint' : 'badge-muted-tint')) }}">{{ $live['session']?->roomSessionStatus?->name ?? 'Not started' }}</span>
                                    </div>
                                    <div class="pd-live-slot">
                                        <span class="pd-label">{{ $preview['current'] ? 'Presenting' : 'Called' }}</span>
                                        <span class="text-truncate">{{ $now?->presentationAttempt->researchGroup->group_reference ?? '—' }}</span>
                                    </div>
                                    <div class="pd-live-slot">
                                        <span class="pd-label">Next</span>
                                        <span class="text-truncate">{{ $preview['next']?->presentationAttempt->researchGroup->group_reference ?? '—' }}</span>
                                        @if ($preview['next']?->isPastSessionEnd())
                                            <span class="text-brand-danger fw-semibold text-nowrap" style="font-size: 0.7rem;">{{ \App\Models\AttemptSchedule::PAST_SESSION_SHORT }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- My upcoming seats --}}
                <section class="pd-panel span-7">
                    <div class="pd-panel-head">
                        <h2 class="pd-panel-title"><x-icon name="list-check" /> Upcoming Assignments</h2>
                        <a href="{{ route('panelist.assignments.index') }}" class="pd-panel-link">View all <x-icon name="arrow-right" /></a>
                    </div>
                    <div class="pd-panel-body">
                        @forelse ($upcoming as $row)
                            @php $group = $row['attempt']->researchGroup; @endphp
                            @include('panelist.partials.dashboard-past-session-line', ['row' => $row, 'previous' => $loop->first ? null : $upcoming[$loop->index - 1]])
                            <div class="pd-row {{ $row['sessionEnd'] ? 'past-session-row' : '' }}">
                                <div class="pd-row-main">
                                    <div class="pd-row-title">{{ $group->group_reference }}</div>
                                    <div class="pd-row-sub text-truncate">{{ $group->category?->name }} · {{ $group->current_project_title ?? $group->leader()?->full_name }}</div>
                                </div>
                                <div class="pd-row-side">
                                    <span class="badge {{ $row['assignment']->roleBadgeClass() }}">{{ $row['assignment']->roleLabel() }}</span>
                                    <span class="pd-row-meta">{{ $when($row) }}@if ($row['state'] === 'scheduled' && $row['room']) · {{ $row['room']->room_name }}@endif</span>
                                </div>
                            </div>
                        @empty
                            <div class="pd-empty"><x-icon name="check" /> Nothing left to present.</div>
                        @endforelse
                        @if ($upcomingMore > 0)
                            <a href="{{ route('panelist.assignments.index') }}" class="pd-more">+{{ $upcomingMore }} more</a>
                        @endif
                    </div>
                </section>

                {{-- Advisees --}}
                <section class="pd-panel span-5">
                    <div class="pd-panel-head">
                        <h2 class="pd-panel-title"><x-icon name="graduation-cap" /> My Advisees</h2>
                        <a href="{{ route('panelist.advisees.index') }}" class="pd-panel-link">View all <x-icon name="arrow-right" /></a>
                    </div>
                    <div class="pd-panel-body">
                        @forelse ($adviseeRows as $row)
                            @php $group = $row['group']; @endphp
                            @include('panelist.partials.dashboard-past-session-line', ['row' => $row, 'previous' => $loop->first ? null : $adviseeRows[$loop->index - 1]])
                            <div class="pd-row {{ $row['sessionEnd'] ? 'past-session-row' : '' }}">
                                <div class="pd-row-main">
                                    <div class="pd-row-title">{{ $group->group_reference }}</div>
                                    <div class="pd-row-sub text-truncate">{{ $group->category?->name }} · {{ $group->current_project_title ?? $group->leader()?->full_name }}</div>
                                </div>
                                <div class="pd-row-side">
                                    @if ($row['state'] === 'done')
                                        @if ($row['attempt']->finalOutcome)
                                            <span class="badge {{ $outcomeTone($row['attempt']->finalOutcome) }}">{{ $row['attempt']->finalOutcome->name }}</span>
                                        @else
                                            <span class="badge badge-muted-tint">{{ $row['attempt']->presentationStatus->name }}</span>
                                        @endif
                                        <span class="pd-row-meta">{{ $row['attempt']->completed_at?->format('M j') }}</span>
                                    @else
                                        <span class="pd-row-meta">{{ $when($row) }}@if ($row['state'] === 'scheduled' && $row['room']) · {{ $row['room']->room_name }}@endif</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="pd-empty"><x-icon name="graduation-cap" /> No advisees yet.</div>
                        @endforelse
                        @if ($adviseeMore > 0)
                            <a href="{{ route('panelist.advisees.index') }}" class="pd-more">+{{ $adviseeMore }} more</a>
                        @endif
                    </div>
                </section>

                {{-- Announcements --}}
                @if ($announcements->isNotEmpty())
                    <section class="pd-panel span-12">
                        <div class="pd-panel-head">
                            <h2 class="pd-panel-title"><x-icon name="megaphone" /> Announcements</h2>
                        </div>
                        <div class="pd-panel-body">
                            @foreach ($announcements as $announcement)
                                <div class="pd-row align-items-start">
                                    <div class="pd-row-main">
                                        <div class="pd-row-title">{{ $announcement->title }} <span class="pd-row-meta">· {{ $announcement->category?->name }}</span></div>
                                        <div class="pd-row-sub">{{ $announcement->message }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .pd {
            --pd-fs-2xs: 0.66rem;
            --pd-fs-xs: 0.72rem;
            --pd-fs-sm: 0.8rem;
            --pd-fs-base: 0.86rem;
            --pd-fs-title: clamp(0.84rem, 0.8rem + 0.15vw, 0.92rem);
            --pd-fs-value: clamp(1.1rem, 0.95rem + 0.55vw, 1.4rem);
            --pd-radius: 0.75rem;
            --pd-gap: clamp(0.75rem, 0.55rem + 0.6vw, 1.1rem);
            --pd-line: var(--brand-border);
            font-size: var(--pd-fs-base);
        }

        .pd * { min-width: 0; }

        /* ---------- Header ---------- */
        .pd-head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.75rem 1.5rem;
            margin-bottom: var(--pd-gap);
        }

        .pd-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: var(--pd-fs-xs);
            color: var(--brand-muted);
            font-weight: 500;
        }

        .pd-eyebrow svg { width: 0.85rem; height: 0.85rem; }

        .pd-greeting {
            font-size: clamp(1.05rem, 0.9rem + 0.6vw, 1.4rem);
            font-weight: 700;
            letter-spacing: -0.01em;
            margin: 0.2rem 0 0.45rem;
        }

        .pd-context { display: flex; flex-wrap: wrap; gap: 0.35rem; }

        .pd-chip {
            display: inline-flex;
            align-items: center;
            max-width: 20rem;
            padding: 0.16rem 0.55rem;
            border: 1px solid var(--pd-line);
            border-radius: 999px;
            font-size: var(--pd-fs-xs);
            background: var(--brand-surface);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pd-chip.is-accent {
            display: inline-block;
            border-color: transparent;
            background: var(--brand-accent-tint);
            color: var(--brand-accent);
            font-weight: 600;
        }

        /* ---------- Icon tile ---------- */
        .pd-ico {
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

        .pd-ico svg { width: 1rem; height: 1rem; }
        .pd-ico.sm { width: 1.6rem; height: 1.6rem; border-radius: 0.45rem; }
        .pd-ico.sm svg { width: 0.85rem; height: 0.85rem; }
        .pd-ico.tone-success { background: var(--brand-success-tint, var(--brand-surface-alt)); color: var(--brand-success); }
        .pd-ico.tone-info { background: var(--brand-info-tint, var(--brand-surface-alt)); color: var(--brand-info); }
        .pd-ico.tone-brand { background: var(--brand-surface-alt); color: var(--brand-text); }

        /* ---------- Stat strip ---------- */
        .pd-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--pd-line);
            border: 1px solid var(--pd-line);
            border-radius: var(--pd-radius);
            overflow: hidden;
            margin-bottom: var(--pd-gap);
        }

        @media (max-width: 767.98px) { .pd-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

        .pd-stat {
            background: var(--brand-surface);
            padding: 0.8rem 0.95rem 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .pd-stat-top { display: flex; align-items: center; gap: 0.55rem; }

        .pd-stat-label {
            font-size: var(--pd-fs-xs);
            color: var(--brand-muted);
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pd-stat-value {
            font-size: var(--pd-fs-value);
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }

        .pd-stat-value-row { display: flex; align-items: baseline; flex-wrap: wrap; gap: 0.2rem 0.4rem; }
        .pd-stat-of { font-size: var(--pd-fs-xs); color: var(--brand-muted); font-weight: 500; }
        .pd-stat-sub { font-size: var(--pd-fs-xs); color: var(--brand-muted); }

        .pd-meter {
            margin-top: auto;
            height: 0.28rem;
            border-radius: 999px;
            background: var(--brand-surface-alt);
            overflow: hidden;
        }

        .pd-meter > span { display: block; height: 100%; border-radius: 999px; background: var(--brand-success); }

        /* ---------- Panels ---------- */
        .pd-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: var(--pd-gap);
        }

        .span-12 { grid-column: span 12; }
        .span-7 { grid-column: span 7; }
        .span-5 { grid-column: span 5; }

        @media (max-width: 991.98px) { .span-7, .span-5 { grid-column: span 12; } }

        .pd-panel {
            background: var(--brand-surface);
            border: 1px solid var(--pd-line);
            border-radius: var(--pd-radius);
            display: flex;
            flex-direction: column;
        }

        .pd-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem 0.75rem;
            padding: 0.75rem 0.95rem 0;
        }

        .pd-panel-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
            font-size: var(--pd-fs-title);
            font-weight: 700;
        }

        .pd-panel-title svg { width: 0.95rem; height: 0.95rem; color: var(--brand-muted); }

        .pd-panel-link {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            font-size: var(--pd-fs-xs);
            font-weight: 600;
            color: var(--brand-muted);
            text-decoration: none;
            white-space: nowrap;
        }

        .pd-panel-link svg { width: 0.8rem; height: 0.8rem; transition: transform 0.15s ease; }
        .pd-panel-link:hover { color: var(--brand-accent); }
        .pd-panel-link:hover svg { transform: translateX(2px); }

        .pd-panel-body { padding: 0.4rem 0.95rem 0.7rem; flex: 1; }

        .pd-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1.25rem 0.5rem;
            color: var(--brand-muted);
            font-size: var(--pd-fs-sm);
        }

        .pd-empty svg { width: 1rem; height: 1rem; }

        .pd-empty-page {
            align-items: center;
            gap: 0.4rem;
            padding: 2.5rem 1rem;
            text-align: center;
        }

        .pd-more {
            display: block;
            padding-top: 0.45rem;
            font-size: var(--pd-fs-xs);
            font-weight: 600;
            color: var(--brand-muted);
            text-decoration: none;
        }

        .pd-more:hover { color: var(--brand-accent); }

        /* ---------- Rows ---------- */
        .pd-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.5rem 0;
        }

        .pd-row + .pd-row { border-top: 1px solid var(--pd-line); }

        .pd-row-main { flex: 1; }
        .pd-row-title { font-weight: 600; font-size: var(--pd-fs-sm); }
        .pd-row-sub { font-size: var(--pd-fs-xs); color: var(--brand-muted); }

        .pd-row-side {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.2rem;
            flex-shrink: 0;
            max-width: 55%;
        }

        .pd-row-side .badge { max-width: 100%; overflow: hidden; text-overflow: ellipsis; }

        .pd-row-meta {
            font-size: var(--pd-fs-2xs);
            color: var(--brand-muted);
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        /* ---------- Live rooms ---------- */
        .pd-live-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background: var(--brand-success);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand-success) 22%, transparent);
        }

        .pd-live {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr));
            gap: 0.6rem;
            padding-top: 0.6rem;
        }

        .pd-live-room {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.35rem 0.75rem;
            padding: 0.6rem 0.75rem;
            border-radius: 0.6rem;
            background: var(--brand-surface-alt);
        }

        .pd-live-name {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .pd-live-slot { display: flex; flex-direction: column; font-size: var(--pd-fs-sm); font-weight: 600; }

        .pd-label {
            font-size: var(--pd-fs-2xs);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--brand-muted);
            font-weight: 600;
        }
    </style>
@endpush
