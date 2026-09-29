{{--
    The top-5 cards that replace the grades table (Reports > Generated Grades)
    while a Best card's View is open. $type is 'groups' or 'presenters';
    $topRows are already ranked by GradeReportService. Refreshed in place by the
    page's 30s swap, so it renders the same on a load and on a re-fetch.
--}}
@php
    $isGroups = $type === 'groups';
    $heading = $isGroups ? 'Top 5 Best Groups' : 'Top 5 Best Presenters';
@endphp

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <div class="d-flex align-items-center gap-2">
        <span class="capacity-header-icon"><x-icon name="{{ $isGroups ? 'trophy' : 'user' }}" /></span>
        <h3 class="h6 mb-0">{{ $heading }}</h3>
    </div>

    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('admin.reports.grades', $category) }}" class="btn btn-sm btn-outline-brand">
            <x-icon name="arrow-left" /> Back to Grades
        </a>
        <a href="{{ route('admin.reports.top.export-pdf', [$category, $type]) }}" class="btn btn-sm btn-brand">
            <x-icon name="file-text" /> Export to PDF
        </a>
    </div>
</div>

@if ($topRows->isEmpty())
    <div class="card-brand rp-empty">—</div>
@else
    <div class="rp-top-grid">
        @foreach ($topRows as $item)
            <div class="card-brand rp-top-card {{ $item->rank === 1 ? 'is-first' : '' }}">
                <div class="rp-top-rank">
                    <span class="rp-top-rank-number">{{ $item->rank }}</span>
                </div>

                <div class="rp-best-main flex-grow-1">
                    @if ($isGroups)
                        <div class="rp-best-ref">{{ $item->group_reference }}</div>
                        <div class="rp-best-title">{{ $item->project_title ?? '—' }}</div>
                        <div class="rp-best-members" title="{{ $item->members->implode(', ') }}">{{ $item->members->implode(' · ') }}</div>
                    @else
                        <div class="rp-best-ref">{{ $item->group_reference }}@if ($item->section) &middot; {{ $item->section }}@endif</div>
                        <div class="rp-best-title">{{ $item->name }}</div>
                        <div class="rp-best-members" title="{{ $item->project_title }}">{{ $item->project_title ?? '—' }}</div>
                    @endif
                </div>

                <div class="rp-best-score">
                    <span class="rp-best-score-value">{{ number_format($isGroups ? $item->group_grade : $item->individual_avg, 2) }}</span>
                    <span class="rp-best-score-label">{{ $isGroups ? 'Group Grade' : 'Individual Avg.' }}</span>
                </div>
            </div>
        @endforeach
    </div>
@endif
