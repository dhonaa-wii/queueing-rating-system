@extends('layouts.panelist')

@section('title', 'Panelist Dashboard')
@section('heading', 'Panelist Dashboard')

@php
    $mustChangePassword = auth()->user()->mustChangePassword();
    $firstName = auth()->user()->profile->first_name ?? auth()->user()->username;
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp

@section('content')
    <div class="pd">
        @if ($mustChangePassword)
            <div class="card-brand p-4 text-center text-brand-muted">
                Change your temporary password to unlock your dashboard. Use the gear icon in the top-right corner.
            </div>
        @else
            @if ($categories->isEmpty())
                <div class="card-brand p-5 text-center text-brand-muted">
                    No panel assignments yet. Once an Administrator assigns you to a presentation, it will show up here.
                </div>
            @else
                <div class="pd-head">
                    <div class="pd-head-main">
                        <div class="pd-eyebrow"><x-icon name="clock" /> {{ now()->format('l, F j') }}</div>
                        <h1 class="pd-greeting">{{ $greeting }}, {{ $firstName }}</h1>
                        @if ($selectedCategory)
                            <div class="pd-context">
                                <span class="pd-chip pd-chip-accent">{{ $selectedCategory->name }}</span>
                                <span class="pd-chip">{{ $selectedCategory->academicYear->name ?? 'N/A' }}</span>
                                <span class="pd-chip">{{ $selectedCategory->semester->name ?? 'N/A' }}</span>
                                <span class="pd-chip">{{ $selectedCategory->college->name ?? 'N/A' }}</span>
                            </div>
                        @endif
                    </div>

                    @if ($categories->count() > 1)
                        <form method="GET" class="pd-switcher">
                            <label for="category-switcher" class="visually-hidden">Category</label>
                            <select id="category-switcher" name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected($selectedCategory?->id === $category->id)>
                                        {{ $category->name }} ({{ $categoryCounts[$category->id] ?? 0 }})
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </div>

                @if ($selectedCategory)
                    {{-- Stat strip --}}
                    <div class="pd-stats">
                        <div class="pd-stat">
                            <div class="pd-stat-top">
                                <span class="pd-stat-icon tone-accent"><x-icon name="clipboard-check" /></span>
                                <span class="pd-stat-label">Assigned Presentations</span>
                            </div>
                            <div class="pd-stat-value">{{ $assignedPresentationsCount }}</div>
                        </div>

                        <div class="pd-stat">
                            <div class="pd-stat-top">
                                <span class="pd-stat-icon tone-info"><x-icon name="clock" /></span>
                                <span class="pd-stat-label">Pending Evaluations</span>
                            </div>
                            <div class="pd-stat-value">{{ $pendingEvaluationsCount }}</div>
                        </div>

                        <div class="pd-stat">
                            <div class="pd-stat-top">
                                <span class="pd-stat-icon tone-success"><x-icon name="check" /></span>
                                <span class="pd-stat-label">Completed Evaluations</span>
                            </div>
                            <div class="pd-stat-value-row">
                                <span class="pd-stat-value">{{ $completedEvaluationsCount }}</span>
                                <span class="pd-stat-of">/ {{ $assignedPresentationsCount }}</span>
                            </div>
                            <div class="pd-meter"><span style="width: {{ $evaluationCompletionPercent }}%"></span></div>
                        </div>
                    </div>

                    <div class="pd-grid">
                        {{-- Live Room Status --}}
                        <div class="pd-panel span-12">
                            <div class="pd-panel-head">
                                <h2 class="pd-panel-title"><x-icon name="monitor" /> Live Room Status</h2>
                            </div>
                            <div class="pd-panel-body">
                                @if ($assignedRooms->isEmpty())
                                    <div class="pd-empty"><x-icon name="monitor" /> No room assigned yet for this category.</div>
                                @else
                                    <div class="pd-rooms">
                                        @foreach ($assignedRooms as $room)
                                            @php
                                                $session = $room->roomSessions->sortByDesc('id')->first();
                                                $sessionCode = $session?->roomSessionStatus?->code;
                                                $preview = $roomPreviews[$room->id];
                                                $dateCode = $room->presentationDate->eventDateStatus?->code;
                                            @endphp
                                            <div class="pd-room-card">
                                                <div class="pd-room-head">
                                                    <div class="min-w-0">
                                                        <div class="pd-room-name text-truncate">{{ $room->room_name }}</div>
                                                        <div class="pd-room-sub text-truncate">
                                                            {{ $room->presentationDate->presentation_date->format('M j, Y') }}
                                                            &middot; {{ $room->room_start_time }}&ndash;{{ $room->room_end_time }}
                                                        </div>
                                                    </div>
                                                    <span class="badge {{ $dateCode === 'ACTIVE' ? 'badge-success-tint' : ($dateCode === 'STANDBY' ? 'badge-brand-tint' : 'badge-muted-tint') }}">
                                                        {{ $room->presentationDate->eventDateStatus->name ?? 'Unknown' }}
                                                    </span>
                                                </div>

                                                <div class="pd-room-session">
                                                    <span class="text-brand-muted">Session</span>
                                                    @if ($session)
                                                        <span class="badge {{ $sessionCode === 'PAUSED' ? 'badge-danger-tint' : 'badge-info-tint' }}">{{ $session->roomSessionStatus->name }}</span>
                                                    @else
                                                        <span class="badge badge-muted-tint">Not started</span>
                                                    @endif
                                                </div>

                                                <div class="pd-queue-strip">
                                                    @foreach (['current' => 'Now Presenting', 'called' => 'Called', 'next' => 'Up Next'] as $slotKey => $slotLabel)
                                                        @php $slotSchedule = $preview[$slotKey]; @endphp
                                                        <div class="pd-queue-cell {{ $slotKey === 'current' && $slotSchedule ? 'is-current' : '' }}">
                                                            <div class="pd-queue-label">
                                                                @if ($slotKey === 'current' && $slotSchedule)
                                                                    <span class="pd-pulse" aria-hidden="true"></span>
                                                                @endif
                                                                {{ $slotLabel }}
                                                            </div>
                                                            @if ($slotSchedule)
                                                                <div class="pd-queue-group text-truncate">{{ $slotSchedule->presentationAttempt->researchGroup->group_reference }}</div>
                                                            @else
                                                                <div class="pd-queue-group text-brand-muted">&mdash;</div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Upcoming Assignments --}}
                        <div class="pd-panel {{ $announcements->isNotEmpty() ? 'span-7' : 'span-12' }}">
                            <div class="pd-panel-head">
                                <h2 class="pd-panel-title"><x-icon name="calendar" /> Upcoming</h2>
                                <a href="{{ route('panelist.assignments.index') }}" class="pd-panel-link">View all <x-icon name="arrow-right" /></a>
                            </div>
                            <div class="pd-panel-body">
                                @if ($upcomingAssignments->isEmpty())
                                    <div class="pd-empty"><x-icon name="check" /> Nothing left to present in this category.</div>
                                @else
                                    <ul class="pd-list">
                                        @foreach ($upcomingAssignments as $assignment)
                                            @php
                                                $rowAttempt = $assignment->presentationAttempt;
                                                $group = $rowAttempt->researchGroup;
                                                $rowSchedule = $rowAttempt->attemptSchedule;
                                                $room = $rowSchedule?->presentationDateRoom;
                                                $isAwaiting = (bool) $rowSchedule?->isAwaitingReschedule($rowAttempt);
                                            @endphp
                                            <li class="pd-item">
                                                <div class="pd-item-main">
                                                    <div class="pd-item-title text-truncate">{{ $group->group_reference }}</div>
                                                    <div class="pd-item-sub text-truncate">{{ $group->current_project_title ?? $group->leader()?->full_name }}</div>
                                                </div>
                                                <div class="pd-item-side">
                                                    <span class="badge {{ $assignment->roleBadgeClass() }}">{{ $assignment->roleLabel() }}</span>
                                                    @if ($isAwaiting)
                                                        <div class="pd-item-meta">{{ \App\Models\AttemptSchedule::AWAITING_SHORT }}</div>
                                                    @else
                                                        <div class="pd-item-meta">{{ $room?->room_name ?? 'Not scheduled' }} &middot; {{ $room?->presentationDate?->presentation_date?->format('M j') ?? '—' }}</div>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>

                        {{-- Announcements --}}
                        @if ($announcements->isNotEmpty())
                            <div class="pd-panel span-5">
                                <div class="pd-panel-head">
                                    <h2 class="pd-panel-title"><x-icon name="megaphone" /> Announcements</h2>
                                </div>
                                <div class="pd-panel-body">
                                    <ul class="pd-list">
                                        @foreach ($announcements as $announcement)
                                            <li class="pd-item align-items-start">
                                                <div class="pd-item-main">
                                                    <div class="pd-item-title">{{ $announcement->title }}</div>
                                                    <div class="pd-item-sub text-wrap">{{ $announcement->message }}</div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            @endif
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .pd {
            --pd-fs-2xs: 0.68rem;
            --pd-fs-xs: 0.74rem;
            --pd-fs-sm: 0.82rem;
            --pd-fs-base: 0.86rem;
            --pd-fs-value: clamp(1.2rem, 1.05rem + 0.5vw, 1.5rem);
            --pd-radius: 0.75rem;
            --pd-gap: clamp(0.75rem, 0.6rem + 0.5vw, 1rem);
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
            font-size: clamp(1.05rem, 0.9rem + 0.6vw, 1.35rem);
            font-weight: 700;
            letter-spacing: -0.01em;
            margin: 0.2rem 0 0.5rem;
        }

        .pd-context {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }

        .pd-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.18rem 0.55rem;
            border: 1px solid var(--pd-line);
            border-radius: 999px;
            font-size: var(--pd-fs-xs);
            color: var(--brand-text);
            background: var(--brand-surface);
            white-space: nowrap;
        }

        .pd-chip-accent {
            border-color: transparent;
            background: var(--brand-accent-tint);
            color: var(--brand-accent);
            font-weight: 600;
        }

        .pd-switcher .form-select {
            min-width: 14rem;
        }

        /* ---------- Stat strip ---------- */
        .pd-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1px;
            background: var(--pd-line);
            border: 1px solid var(--pd-line);
            border-radius: var(--pd-radius);
            overflow: hidden;
            margin-bottom: var(--pd-gap);
        }

        @media (max-width: 575.98px) {
            .pd-stats { grid-template-columns: 1fr; }
        }

        .pd-stat {
            background: var(--brand-surface);
            padding: 0.85rem 0.95rem 0.8rem;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .pd-stat-top { display: flex; align-items: center; gap: 0.55rem; }

        .pd-stat-icon {
            width: 1.9rem;
            height: 1.9rem;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.55rem;
            background-color: var(--brand-surface-alt);
        }

        .pd-stat-icon svg { width: 1.05rem; height: 1.05rem; }
        .pd-stat-icon.tone-accent { color: var(--brand-accent); }
        .pd-stat-icon.tone-info { color: var(--brand-info); }
        .pd-stat-icon.tone-success { color: var(--brand-success); }

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
        }

        .pd-stat-value-row { display: flex; align-items: baseline; gap: 0.3rem; }
        .pd-stat-of { font-size: var(--pd-fs-sm); color: var(--brand-muted); font-weight: 500; }

        .pd-meter {
            margin-top: auto;
            height: 0.3rem;
            border-radius: 999px;
            background: var(--brand-surface-alt);
            overflow: hidden;
        }

        .pd-meter > span { display: block; height: 100%; border-radius: 999px; background: var(--brand-accent); }

        /* ---------- Panels ---------- */
        .pd-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: var(--pd-gap);
        }

        .span-12 { grid-column: span 12; }
        .span-7 { grid-column: span 7; }
        .span-5 { grid-column: span 5; }

        @media (max-width: 991.98px) {
            .span-7, .span-5 { grid-column: span 12; }
        }

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
            flex-wrap: wrap;
            gap: 0.5rem 0.75rem;
            padding: 0.8rem 0.95rem 0;
        }

        .pd-panel-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
            font-size: 0.9rem;
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
        }

        .pd-panel-link svg { width: 0.8rem; height: 0.8rem; transition: transform 0.15s ease; }
        .pd-panel-link:hover { color: var(--brand-accent); }
        .pd-panel-link:hover svg { transform: translateX(2px); }

        .pd-panel-body { padding: 0.75rem 0.95rem 0.9rem; flex: 1; }

        .pd-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1.4rem 0.5rem;
            color: var(--brand-muted);
            font-size: var(--pd-fs-sm);
        }

        .pd-empty svg { width: 1rem; height: 1rem; }

        /* ---------- Room cards ---------- */
        .pd-rooms {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(15.5rem, 1fr));
            gap: 0.75rem;
        }

        .pd-room-card {
            border: 1px solid var(--pd-line);
            border-radius: 0.6rem;
            padding: 0.75rem 0.85rem;
        }

        .pd-room-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .pd-room-name { font-size: 0.9rem; font-weight: 600; }
        .pd-room-sub { font-size: var(--pd-fs-xs); color: var(--brand-muted); }

        .pd-room-session {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: var(--pd-fs-xs);
            margin-bottom: 0.6rem;
        }

        .pd-queue-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border-top: 1px solid var(--pd-line);
            padding-top: 0.5rem;
        }

        .pd-queue-cell {
            padding: 0 0.5rem;
            border-left: 1px solid var(--pd-line);
            min-width: 0;
        }

        .pd-queue-cell:first-child { border-left: none; padding-left: 0; }

        .pd-queue-cell.is-current {
            background-color: var(--brand-accent-tint);
            border-radius: 0.4rem;
            margin: -0.15rem;
            padding: 0.15rem 0.5rem;
        }

        .pd-queue-label {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            color: var(--brand-muted);
            font-size: var(--pd-fs-2xs);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-weight: 600;
            margin-bottom: 0.2rem;
        }

        .pd-pulse {
            width: 0.35rem;
            height: 0.35rem;
            border-radius: 50%;
            background-color: var(--brand-success);
            flex-shrink: 0;
        }

        .pd-queue-group { font-size: var(--pd-fs-sm); font-weight: 600; }

        /* ---------- Lists ---------- */
        .pd-list { list-style: none; margin: 0; padding: 0; }

        .pd-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.55rem 0;
        }

        .pd-list > .pd-item + .pd-item { border-top: 1px solid var(--pd-line); }

        .pd-item-main { flex: 1; min-width: 0; }
        .pd-item-title { font-weight: 600; font-size: var(--pd-fs-sm); }
        .pd-item-sub { font-size: var(--pd-fs-xs); color: var(--brand-muted); }

        .pd-item-side {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.25rem;
            flex-shrink: 0;
        }

        .pd-item-meta {
            font-size: var(--pd-fs-2xs);
            color: var(--brand-muted);
            white-space: nowrap;
        }
    </style>
@endpush
