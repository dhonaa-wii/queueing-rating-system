{{--
    Best Group card body (Reports > Generated Grades). Kept as its own partial
    because the page's 30s refresh swaps this markup in place, so it has to
    render identically on a normal load and on a re-fetch. Shows the leader
    only; View opens the top-5 list, which is also where a tie is spelled out.
--}}
@php($leader = $bestGroups->first())

<div class="rp-card-header">
    <div class="d-flex align-items-center gap-2">
        <span class="capacity-header-icon"><x-icon name="trophy" /></span>
        <h3 class="rp-card-title mb-0">{{ $bestGroups->count() > 1 ? 'Best Groups' : 'Best Group' }}</h3>
    </div>
    <div class="d-flex align-items-center gap-2">
        @if ($bestGroups->count() > 1)
            <span class="badge badge-brand-tint">Tied &middot; {{ $bestGroups->count() }}</span>
        @endif
        @if ($leader)
            <a href="{{ route('admin.reports.grades', [$category, 'top' => 'groups']) }}"
               class="btn rp-best-view {{ $top === 'groups' ? 'btn-brand' : 'btn-outline-brand' }}">
                <x-icon name="eye" /> View
            </a>
        @endif
    </div>
</div>

@if ($leader)
    <div class="rp-best-body" title="{{ $leader->members->implode(', ') }}">
        <div class="rp-best-main">
            <div class="rp-best-ref">{{ $leader->group_reference }}</div>
            <div class="rp-best-title">{{ $leader->project_title ?? '—' }}</div>
        </div>
        <div class="rp-best-score">
            <span class="rp-best-score-value">{{ number_format($leader->group_grade, 2) }}</span>
            <span class="rp-best-score-label">Group Grade</span>
        </div>
    </div>
@else
    <div class="rp-empty">—</div>
@endif
