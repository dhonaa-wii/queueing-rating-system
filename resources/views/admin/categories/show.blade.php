@extends('layouts.admin')

@section('title', $category?->name ?? 'New Category')
@section('heading', $category?->name ?? 'New Presentation Category')
@section('back-link')
    @include('partials.back-link', ['href' => route('admin.categories.index')])
@endsection

@section('content')
    @php
        $scheduleIncomplete = $category && (! $category->isRegistrationConfigured() || ! $category->isEventConfigured());
        $queueIncomplete = $category && ! $category->isQueueConfigured();
        $evaluationIncomplete = $category && ! $category->isEvaluationConfigured();

        $setupSteps = collect();

        if ($category) {
            $setupSteps = collect([
                [
                    'label' => 'Project Information',
                    'tabTarget' => '#tab-overview',
                    'items' => [
                        ['label' => 'Category Name & Description', 'done' => filled($category->name)],
                        ['label' => 'Subject','done' => filled($category->subject_or_research_type)],
                        ['label' => 'Max Group Members', 'done' => $category->maximum_members !== null],
                        ['label' => 'Panel Count (default per room)', 'done' => $category->panelist_count !== null],
                    ],
                ],
                [
                    'label' => 'Schedules',
                    'tabTarget' => '#tab-schedules',
                    'items' => [
                        ['label' => 'Registration Opens', 'done' => $category->registration_opens_at !== null],
                        ['label' => 'Registration Closes', 'done' => $category->registration_closes_at !== null],
                        ['label' => 'Duration per Group', 'done' => optional($category->categoryScheduleSetting)->duration_minutes !== null],
                        ['label' => 'Presentation Dates & Rooms', 'done' => $category->isEventConfigured()],
                    ],
                ],
                [
                    'label' => 'Queue & Payment',
                    'tabTarget' => '#tab-queue-payment',
                    'items' => [
                        ['label' => 'Queue Strategy', 'done' => $category->isQueueConfigured()],
                        ['label' => 'Payment Configuration', 'done' => $category->categoryPaymentSetting !== null],
                    ],
                ],
                [
                    'label' => 'Evaluation Configuration',
                    'tabTarget' => '#tab-evaluation',
                    'items' => [
                        ['label' => 'Evaluation Form Assigned', 'done' => $category->isEvaluationConfigured()],
                    ],
                ],
                [
                    'label' => 'Announcements',
                    'tabTarget' => '#tab-announcements',
                    'items' => [
                        ['label' => 'At Least One Announcement', 'done' => $category->categoryAnnouncements->isNotEmpty()],
                    ],
                ],
            ])->map(function ($step) {
                $step['doneCount'] = collect($step['items'])->where('done', true)->count();
                $step['totalCount'] = count($step['items']);
                $step['isDone'] = $step['doneCount'] === $step['totalCount'];

                return $step;
            });
        }

        $setupStepsDone = $setupSteps->where('isDone', true)->count();
        $setupStepsTotal = $setupSteps->count();
        $setupStepsPercent = $setupStepsTotal ? (int) round($setupStepsDone / $setupStepsTotal * 100) : 0;
    @endphp

    <div class="page-shell setup-page">
    <div class="setup-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <p class="setup-header-meta text-brand-muted mb-0">
                    @if ($category)
                        {{ $category->academicYear->name ?? 'No academic year' }} &middot;
                        {{ $category->semester->name ?? 'No semester' }} &middot;
                        {{ $category->college->name ?? 'No college' }}
                    @else
                        Fill in Project Information below to create this category and unlock the rest of setup.
                    @endif
                </p>
            </div>

            @if ($category)
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge badge-brand-tint">{{ $category->statusDisplayName() }}</span>
                    <button type="button" class="btn btn-sm btn-outline-brand setup-guide-btn" data-bs-toggle="modal" data-bs-target="#setup-guide-modal">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Setup Guide
                        <span class="setup-guide-btn-count">{{ $setupStepsDone }}/{{ $setupStepsTotal }}</span>
                    </button>
                    @if ($category->categoryStatus->code !== 'ARCHIVED')
                        <button type="button" class="btn btn-sm btn-outline-brand"
                                data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                                data-confirm-action="{{ route('admin.categories.archive', $category) }}"
                                data-confirm-method="POST"
                                data-confirm-title="Archive Category"
                                data-confirm-message="Archive {{ $category->name }}? It can be unarchived again later."
                                data-confirm-submit-label="Archive"
                                data-confirm-variant="btn-outline-danger-brand">
                            <x-icon name="archive" /> Archive Category
                        </button>
                    @else
                        <button type="button" class="btn btn-sm btn-outline-brand"
                                data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                                data-confirm-action="{{ route('admin.categories.unarchive', $category) }}"
                                data-confirm-method="POST"
                                data-confirm-title="Unarchive Category"
                                data-confirm-message="Restore {{ $category->name }} to the active list?"
                                data-confirm-submit-label="Unarchive"
                                data-confirm-variant="btn-brand">
                            <x-icon name="refresh" /> Unarchive Category
                        </button>
                    @endif
                </div>
            @endif
        </div>

    </div>

    @php $setupReadOnly = $category && $category->isEnded(); @endphp
    @if ($setupReadOnly)
        <div class="alert alert-secondary d-flex align-items-center gap-2 mb-3">
            <x-icon name="lock" />
            <span>This category has ended — its setup is view only.</span>
        </div>
    @endif

    @if ($category)
        <div class="modal fade setup-guide-modal" id="setup-guide-modal" tabindex="-1" aria-labelledby="setup-guide-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="setup-guide-modal-label">Setup Guide</h5>
                            <p class="setup-guide-modal-subtitle">{{ $setupStepsDone }} of {{ $setupStepsTotal }} steps complete</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="setup-guide-progress-track">
                            <div class="setup-guide-progress-fill" style="width: {{ $setupStepsPercent }}%;"></div>
                        </div>

                        <div class="setup-guide-list">
                            @foreach ($setupSteps as $step)
                                <div class="setup-guide-step-card {{ $step['isDone'] ? 'is-done' : '' }} {{ ! $step['isDone'] ? 'is-open' : '' }}">
                                    <button type="button" class="setup-guide-step" data-toggle-step aria-expanded="{{ $step['isDone'] ? 'false' : 'true' }}" aria-controls="setup-guide-items-{{ $loop->index }}">
                                        <span class="setup-guide-step-badge">
                                            @if ($step['isDone'])
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            @else
                                                {{ $loop->iteration }}
                                            @endif
                                        </span>
                                        <span class="setup-guide-step-info">
                                            <span class="setup-guide-step-label">{{ $step['label'] }}</span>
                                            <span class="setup-guide-step-meta">{{ $step['doneCount'] }}/{{ $step['totalCount'] }} configured</span>
                                        </span>
                                        <svg class="setup-guide-step-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    </button>

                                    <div class="setup-guide-item-collapse" id="setup-guide-items-{{ $loop->index }}">
                                        <div class="setup-guide-item-collapse-inner">
                                            <ul class="setup-guide-item-list">
                                                @foreach ($step['items'] as $item)
                                                    <li class="setup-guide-item {{ $item['done'] ? 'is-done' : '' }}" data-jump-tab="{{ $step['tabTarget'] }}" data-bs-dismiss="modal" role="button" tabindex="0">
                                                        <span class="setup-guide-item-dot">
                                                            @if ($item['done'])
                                                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                            @endif
                                                        </span>
                                                        <span class="setup-guide-item-label">{{ $item['label'] }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <ul class="nav nav-tabs setup-tabs-underline" id="setup-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button" role="tab">Project Information</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-schedules" type="button" role="tab" @disabled(! $category)>
                Schedules
                @if ($scheduleIncomplete)<span class="tab-incomplete-dot"></span>@endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-queue-payment" type="button" role="tab" @disabled(! $category)>
                Queue &amp; Payment
                @if ($queueIncomplete)<span class="tab-incomplete-dot"></span>@endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-evaluation" type="button" role="tab" @disabled(! $category)>
                Evaluation Configuration
                @if ($evaluationIncomplete)<span class="tab-incomplete-dot"></span>@endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-announcements" type="button" role="tab" @disabled(! $category)>Announcements</button>
        </li>
        <span class="setup-tabs-indicator" id="setup-tabs-indicator"></span>
    </ul>

    <div class="tab-content" @if ($setupReadOnly) data-setup-readonly @endif>
        <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
            @include('admin.categories.partials.overview')
        </div>
        <div class="tab-pane fade" id="tab-schedules" role="tabpanel">
            @if ($category) @include('admin.categories.partials.schedule') @else @include('admin.categories.partials.locked') @endif
        </div>
        <div class="tab-pane fade" id="tab-queue-payment" role="tabpanel">
            @if ($category) @include('admin.categories.partials.queue-payment') @else @include('admin.categories.partials.locked') @endif
        </div>
        <div class="tab-pane fade" id="tab-evaluation" role="tabpanel">
            @if ($category) @include('admin.categories.partials.evaluation') @else @include('admin.categories.partials.locked') @endif
        </div>
        <div class="tab-pane fade" id="tab-announcements" role="tabpanel">
            @if ($category) @include('admin.categories.partials.announcements') @else @include('admin.categories.partials.locked') @endif
        </div>
    </div>
    </div>

    @include('admin.categories.partials.scripts')
    @include('admin.partials.confirm-action-modal')

    @if ($setupReadOnly)
        {{-- Ended category: view only. Every control in the tabs is an edit
             (save/add/remove/edit triggers), so all buttons go and all fields
             are disabled; links (e.g. "go to tab") stay. The server refuses
             writes regardless (CategorySetupLock). --}}
        @push('styles')
            <style>
                [data-setup-readonly] button,
                [data-setup-readonly] .row-actions-btn,
                [data-setup-readonly] input[type="submit"] { display: none !important; }
            </style>
        @endpush
        @push('scripts')
            <script>
                document.querySelectorAll('[data-setup-readonly] input, [data-setup-readonly] select, [data-setup-readonly] textarea')
                    .forEach(function (field) { field.disabled = true; });
            </script>
        @endpush
    @endif

    @push('styles')
        <style>
            /* ---------- Presentation Setup workspace ----------
               Width, type tokens and sidebar-colored cards come from the
               shared .page-shell (theme-head). This page adds compact
               controls and tighter spacing on top. */
            .setup-page {
                --setup-card-pad: clamp(0.8rem, 0.6rem + 0.6vw, 1.1rem);
            }

            .setup-header {
                margin-bottom: 0.6rem;
            }

            .setup-header-meta {
                font-size: var(--page-fs-sm);
            }

            .setup-page h3.h6 {
                margin-bottom: 0.6rem !important;
            }

            .setup-page h3.h6.mb-0 {
                margin-bottom: 0 !important;
            }

            .setup-page .card-brand.p-4 {
                padding: var(--setup-card-pad) !important;
            }

            .setup-page .card-brand.p-3 {
                padding: calc(var(--setup-card-pad) * 0.8) !important;
            }

            .setup-page .card-brand.p-5 {
                padding: calc(var(--setup-card-pad) * 1.6) !important;
            }

            .setup-page .card-brand.mb-4,
            .setup-page .card-brand.mb-3 {
                margin-bottom: 0.9rem !important;
            }

            .setup-page .mt-4 {
                margin-top: 0.9rem !important;
            }

            .setup-page .row.g-4 {
                --bs-gutter-x: 0.9rem;
                --bs-gutter-y: 0.9rem;
            }

            .setup-page .row.g-3 {
                --bs-gutter-x: 0.65rem;
                --bs-gutter-y: 0.55rem;
            }

            .setup-page .card-brand .mb-3 {
                margin-bottom: 0.65rem !important;
            }

            .setup-page .card-brand .mb-4 {
                margin-bottom: 0.85rem !important;
            }

            .setup-page .brand-divider.my-4 {
                margin-block: 0.85rem !important;
            }

            .setup-page .brand-divider {
                margin-block: 0.7rem;
            }

            /* Form controls */
            .setup-page .form-label {
                font-size: var(--page-fs-sm);
                font-weight: 500;
                margin-bottom: 0.2rem;
            }

            .setup-page .form-control,
            .setup-page .form-select {
                font-size: var(--page-fs-sm);
                padding: 0.28rem 0.55rem;
                min-height: 0;
                line-height: 1.45;
                border-radius: 0.45rem;
            }

            .setup-page .form-select {
                padding-right: 1.9rem;
                background-position: right 0.55rem center;
                background-size: 12px 9px;
            }

            .setup-page .form-control:focus,
            .setup-page .form-select:focus {
                box-shadow: 0 0 0 0.18rem var(--brand-accent-tint);
            }

            .setup-page textarea.form-control {
                min-height: 0;
            }

            .setup-page input[type="date"].form-control,
            .setup-page input[type="time"].form-control,
            .setup-page input[type="datetime-local"].form-control {
                min-width: 0;
            }

            /* Registration Opens/Closes sit side by side only when each
               date-time field has room to show its full value. */
            .registration-window-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
                gap: 0.55rem 0.65rem;
            }

            .setup-page .form-text {
                margin-top: 0.2rem;
            }

            .setup-page .form-check {
                min-height: 0;
            }

            .setup-page .section-order-box {
                min-height: calc(1.45em + 0.56rem + 2px);
            }

            /* Buttons */
            .setup-page .btn {
                --bs-btn-padding-y: 0.3rem;
                --bs-btn-padding-x: 0.75rem;
                --bs-btn-border-radius: 0.5rem;
            }

            .setup-page .btn-sm {
                --bs-btn-padding-y: 0.22rem;
                --bs-btn-padding-x: 0.55rem;
                --bs-btn-border-radius: 0.45rem;
            }

            .setup-page .btn.mt-2 {
                margin-top: 0.25rem !important;
            }

            /* Tables + alerts inside cards */
            .setup-page .card-brand .table > :not(caption) > * > * {
                padding: 0.4rem 0.5rem;
            }

            @media (max-width: 767.98px) {
                .setup-page .card-brand .table th,
                .setup-page .card-brand .table td {
                    white-space: nowrap;
                }
            }

            .setup-page .alert {
                font-size: var(--page-fs-sm);
                padding: 0.5rem 0.75rem;
                margin-bottom: 0.65rem;
                border-radius: 0.55rem;
            }

            .setup-page p,
            .setup-page .small {
                line-height: 1.45;
            }

            /* Capacity Analysis card (shared partial) — compact on this page */
            .setup-page .capacity-card-header {
                padding: 0.65rem var(--setup-card-pad);
            }

            .setup-page .capacity-header-icon {
                width: 2rem;
                height: 2rem;
                background-color: var(--brand-surface-alt);
            }

            .setup-page .capacity-header-icon svg {
                width: 1.1rem;
                height: 1.1rem;
            }

            .setup-page .capacity-card-header .small {
                font-size: var(--page-fs-xs);
            }

            .setup-page .capacity-status-pill {
                font-size: var(--page-fs-xs);
                padding: 0.25rem 0.6rem;
            }

            .setup-page .capacity-stat-grid {
                gap: 0.7rem 1rem;
                padding: 0.75rem var(--setup-card-pad);
            }

            .setup-page .capacity-stat-icon {
                width: 2rem;
                height: 2rem;
                background-color: var(--brand-surface);
            }

            .setup-page .capacity-stat-icon svg {
                width: 1.05rem;
                height: 1.05rem;
            }

            .setup-page .capacity-stat-label {
                font-size: clamp(0.62rem, 0.6rem + 0.08vw, 0.68rem);
            }

            .setup-page .capacity-stat-value {
                font-size: clamp(0.88rem, 0.82rem + 0.2vw, 1rem);
            }

            .tab-incomplete-dot {
                display: inline-block;
                width: 0.4rem;
                height: 0.4rem;
                border-radius: 50%;
                background-color: var(--brand-danger);
                margin-left: 0.4rem;
                vertical-align: middle;
            }

            /* ---------- Setup Guide (modal) ----------
               A compact trigger button (with a live x/y count) opens a modal
               listing every setup step; each row is a full-width button that
               jumps straight to that tab and closes the modal. */
            .setup-guide-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
            }

            .setup-guide-btn-count {
                font-size: 0.7rem;
                font-weight: 700;
                padding: 0.05rem 0.4rem;
                border-radius: 999px;
                background-color: var(--brand-accent-tint);
                color: var(--brand-accent);
            }

            .setup-guide-modal .modal-header {
                align-items: flex-start;
            }

            .setup-guide-modal-subtitle {
                margin: 0.15rem 0 0;
                font-size: var(--modal-fs-xs);
                color: var(--brand-muted);
            }

            .setup-guide-progress-track {
                height: 0.35rem;
                border-radius: 999px;
                background-color: var(--brand-accent-tint);
                overflow: hidden;
                margin-bottom: 1.1rem;
            }

            .setup-guide-progress-fill {
                height: 100%;
                border-radius: 999px;
                background-color: var(--brand-accent);
                transition: width 0.3s ease;
            }

            .setup-guide-list {
                display: flex;
                flex-direction: column;
                gap: 0.6rem;
                max-height: 60vh;
                overflow-y: auto;
                padding-right: 0.15rem;
            }

            .setup-guide-step-card {
                border: 1px solid var(--brand-border);
                background-color: var(--brand-surface);
                border-radius: 0.85rem;
                overflow: hidden;
                transition: border-color 0.15s ease;
            }

            .setup-guide-step-card.is-done {
                border-color: color-mix(in srgb, var(--brand-accent) 35%, var(--brand-border));
            }

            .setup-guide-step {
                display: flex;
                align-items: center;
                gap: 0.85rem;
                width: 100%;
                border: none;
                background: none;
                padding: 0.7rem 0.9rem;
                text-align: left;
                cursor: pointer;
                transition: background-color 0.15s ease, transform 0.15s ease;
            }

            .setup-guide-step:hover {
                background-color: var(--brand-accent-tint);
            }

            .setup-guide-step-badge {
                flex-shrink: 0;
                width: 1.85rem;
                height: 1.85rem;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 0.8rem;
                font-weight: 700;
                color: var(--brand-muted);
                background-color: var(--brand-surface-alt, var(--brand-accent-tint));
                border: 1.5px solid var(--brand-border);
            }

            .setup-guide-step-card.is-done .setup-guide-step-badge {
                background-color: var(--brand-accent);
                border-color: var(--brand-accent);
                color: var(--brand-accent-contrast);
            }

            .setup-guide-step-info {
                flex: 1 1 auto;
                min-width: 0;
                display: flex;
                flex-direction: column;
                gap: 0.05rem;
            }

            .setup-guide-step-label {
                font-weight: 600;
                color: var(--brand-text);
                font-size: 0.9rem;
            }

            .setup-guide-step-meta {
                font-size: 0.75rem;
                color: var(--brand-muted);
            }

            .setup-guide-step-card.is-done .setup-guide-step-meta {
                color: var(--brand-accent);
            }

            .setup-guide-step-arrow {
                flex-shrink: 0;
                color: var(--brand-muted);
                transition: transform 0.2s ease;
            }

            .setup-guide-step-card.is-open .setup-guide-step-arrow {
                transform: rotate(90deg);
            }

            .setup-guide-step:hover .setup-guide-step-arrow {
                color: var(--brand-accent);
            }

            /* Slide-down accordion — grid-rows 0fr/1fr trick (same technique
               used elsewhere in this app for height-unknown collapse content)
               so each step's field checklist animates open/closed instead of
               all being shown at once. */
            .setup-guide-item-collapse {
                display: grid;
                grid-template-rows: 0fr;
                transition: grid-template-rows 0.25s ease;
            }

            .setup-guide-step-card.is-open .setup-guide-item-collapse {
                grid-template-rows: 1fr;
            }

            .setup-guide-item-collapse-inner {
                min-height: 0;
                overflow: hidden;
            }

            .setup-guide-item-list {
                list-style: none;
                margin: 0;
                padding: 0 0.9rem 0.75rem calc(0.9rem + 1.85rem + 0.85rem);
                display: flex;
                flex-direction: column;
                gap: 0.4rem;
            }

            .setup-guide-item {
                display: flex;
                align-items: center;
                gap: 0.55rem;
                cursor: pointer;
            }

            .setup-guide-item-dot {
                flex-shrink: 0;
                width: 1rem;
                height: 1rem;
                border-radius: 50%;
                border: 1.5px solid var(--brand-border);
                background-color: var(--brand-surface);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: var(--brand-accent-contrast);
            }

            .setup-guide-item.is-done .setup-guide-item-dot {
                background-color: var(--brand-accent);
                border-color: var(--brand-accent);
            }

            .setup-guide-item-label {
                font-size: 0.8rem;
                color: var(--brand-muted);
            }

            .setup-guide-item.is-done .setup-guide-item-label {
                color: var(--brand-text);
            }

            .setup-guide-item:hover .setup-guide-item-label {
                color: var(--brand-accent);
            }

            /* ---------- Plain underline tabs (Presentation Setup only) ---------- */
            #setup-tabs.setup-tabs-underline {
                position: relative;
                border-bottom: 1px solid var(--brand-border);
                margin-bottom: 0.9rem;
                gap: 0.1rem;
                flex-wrap: nowrap;
                overflow-x: auto;
                scrollbar-width: none;
            }

            #setup-tabs.setup-tabs-underline::-webkit-scrollbar {
                display: none;
            }

            #setup-tabs.setup-tabs-underline .nav-link {
                background: none;
                border: none;
                border-radius: 0;
                color: var(--brand-muted);
                font-weight: 500;
                font-size: var(--page-fs-sm);
                padding: 0.5rem clamp(0.55rem, 0.4rem + 0.4vw, 0.8rem);
                white-space: nowrap;
                transition: color 0.15s ease;
            }

            #setup-tabs.setup-tabs-underline .nav-link:hover:not(.active):not(:disabled) {
                color: var(--brand-accent);
                border: none;
            }

            #setup-tabs.setup-tabs-underline .nav-link.active {
                background: none;
                border: none;
                color: var(--brand-accent);
                font-weight: 600;
            }

            #setup-tabs.setup-tabs-underline .nav-link:disabled {
                color: var(--brand-muted);
                opacity: 0.45;
            }

            .setup-tabs-indicator {
                position: absolute;
                bottom: -1px;
                left: 0;
                height: 2px;
                background-color: var(--brand-accent);
                border-radius: 999px;
                transition: left 0.25s cubic-bezier(0.4, 0, 0.2, 1), width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function () {
                // A plain (non-AJAX) form submit is a real page navigation — the
                // server's redirect (back()/redirect()->route(...)) never carries
                // the #tab-x hash, since URL fragments are never sent to the server
                // at all (the Referer header strips them too), so the browser lands
                // back on the bare URL with no hash for Bootstrap's tab machinery to
                // pick up, and the page falls back to whichever tab-pane is marked
                // "active" in the HTML (Project Information). Persisting the active
                // tab + scroll position through sessionStorage instead of the URL
                // survives that round trip regardless of which controller/route
                // handled the action.
                var storageKey = 'category-setup-tab-state:' + window.location.pathname;

                var hash = window.location.hash;
                var stored = null;
                try {
                    stored = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
                } catch (e) {
                    stored = null;
                }
                // sessionStorage.removeItem: this is a one-shot restore — once
                // consumed, a later plain navigation back to this page (e.g. via a
                // sidebar link) should start fresh at Project Information rather
                // than reopening a stale tab from an unrelated earlier visit.
                sessionStorage.removeItem(storageKey);

                var targetHash = hash || (stored && stored.tab);
                if (targetHash) {
                    var trigger = document.querySelector('[data-bs-target="' + targetHash + '"]');
                    if (trigger && ! trigger.disabled) {
                        new bootstrap.Tab(trigger).show();
                    }
                }

                var scrollContainer = document.querySelector('.admin-content');

                if (stored && typeof stored.scroll === 'number' && scrollContainer) {
                    requestAnimationFrame(function () {
                        scrollContainer.scrollTop = stored.scroll;
                    });
                }

                document.querySelectorAll('#setup-tabs button').forEach(function (button) {
                    button.addEventListener('shown.bs.tab', function (event) {
                        history.replaceState(null, '', event.target.dataset.bsTarget);
                    });
                });

                // Capture (not bind/prevent) every real form submit anywhere on the
                // page — including AJAX-enhanced ones (harmless there, since those
                // never navigate away so the stored value is simply left unread
                // until the next real navigation) — so whichever tab/scroll
                // position was current the instant an action was taken is what
                // comes back after the page reloads.
                document.addEventListener('submit', function (event) {
                    // Deferred so a form handled in place (AJAX or the soft
                    // refresh) has already called preventDefault — only a
                    // submit that really navigates needs its tab and scroll
                    // restored, and a leftover value would reopen a stale tab
                    // on the next unrelated visit.
                    setTimeout(function () {
                        if (event.defaultPrevented) {
                            return;
                        }
                        var activeTab = document.querySelector('#setup-tabs button.active');
                        sessionStorage.setItem(storageKey, JSON.stringify({
                            tab: activeTab ? activeTab.dataset.bsTarget : null,
                            scroll: scrollContainer ? scrollContainer.scrollTop : 0,
                        }));
                    }, 0);
                }, true);
            })();

            (function () {
                var modal = document.getElementById('setup-guide-modal');

                if (! modal) {
                    return;
                }

                modal.querySelectorAll('[data-toggle-step]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var card = button.closest('.setup-guide-step-card');
                        if (! card) {
                            return;
                        }
                        var isOpen = card.classList.toggle('is-open');
                        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    });
                });

                modal.querySelectorAll('[data-jump-tab]').forEach(function (cell) {
                    cell.addEventListener('click', function () {
                        var trigger = document.querySelector('#setup-tabs [data-bs-target="' + cell.dataset.jumpTab + '"]');
                        if (trigger && ! trigger.disabled) {
                            new bootstrap.Tab(trigger).show();
                            trigger.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                        }
                    });

                    cell.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            cell.click();
                        }
                    });
                });
            })();

            (function () {
                var tabs = document.getElementById('setup-tabs');
                var indicator = document.getElementById('setup-tabs-indicator');

                if (! tabs || ! indicator) {
                    return;
                }

                function moveIndicatorTo(button) {
                    if (! button) {
                        indicator.style.width = '0px';
                        return;
                    }

                    indicator.style.left = button.offsetLeft + 'px';
                    indicator.style.width = button.offsetWidth + 'px';
                }

                function currentActiveButton() {
                    return tabs.querySelector('.nav-link.active');
                }

                // No transition on the very first paint — the indicator should
                // appear already in place under the initially-active tab, not
                // visibly slide in from the left edge on page load.
                indicator.style.transition = 'none';
                moveIndicatorTo(currentActiveButton());
                requestAnimationFrame(function () {
                    indicator.style.transition = '';
                });

                tabs.querySelectorAll('button').forEach(function (button) {
                    button.addEventListener('shown.bs.tab', function (event) {
                        moveIndicatorTo(event.target);
                    });
                });

                window.addEventListener('resize', function () {
                    indicator.style.transition = 'none';
                    moveIndicatorTo(currentActiveButton());
                    requestAnimationFrame(function () {
                        indicator.style.transition = '';
                    });
                });
            })();
        </script>
    @endpush
@endsection
