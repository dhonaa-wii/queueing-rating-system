{{--
    Best Presenter card body (Reports > Generated Grades) — the student with
    the highest Individual Grade Avg., mirroring the Best Group card above it
    (partials/best-groups) and refreshed by the same 30s swap.
--}}
@php($leader = $bestPresenters->first())

<div class="rp-card-header">
    <div class="d-flex align-items-center gap-2">
        <span class="capacity-header-icon"><x-icon name="user" /></span>
        <h3 class="rp-card-title mb-0">{{ $bestPresenters->count() > 1 ? 'Best Presenters' : 'Best Presenter' }}</h3>
    </div>
    <div class="d-flex align-items-center gap-2">
        @if ($bestPresenters->count() > 1)
            <span class="badge badge-brand-tint">Tied &middot; {{ $bestPresenters->count() }}</span>
        @endif
        @if ($leader)
            <a href="{{ route('admin.reports.grades', [$category, 'top' => 'presenters']) }}"
               class="btn rp-best-view {{ $top === 'presenters' ? 'btn-brand' : 'btn-outline-brand' }}">
                <x-icon name="eye" /> View
            </a>
        @endif
    </div>
</div>

@if ($leader)
    <div class="rp-best-body" title="{{ $leader->project_title }}">
        <div class="rp-best-main">
            <div class="rp-best-ref">{{ $leader->group_reference }}@if ($leader->section) &middot; {{ $leader->section }}@endif</div>
            <div class="rp-best-title">{{ $leader->name }}</div>
        </div>
        <div class="rp-best-score">
            <span class="rp-best-score-value">{{ number_format($leader->individual_avg, 2) }}</span>
            <span class="rp-best-score-label">Individual Avg.</span>
        </div>
    </div>
@else
    <div class="rp-empty">—</div>
@endif
