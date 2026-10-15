{{--
    The top-5 list that replaces the grades table (Reports > Generated Grades)
    while a Best card's View is open. $type is 'groups' or 'presenters';
    $topRows are already ranked by GradeReportService, narrowed by $topFilters
    (Track/Section; default all = the category's record). One group or
    presenter per row. Refreshed in place by the page's 30s swap, so it renders
    the same on a load and on a re-fetch.
--}}
@php
    $isGroups = $type === 'groups';
    $heading = $isGroups ? 'Top 5 Best Groups' : 'Top 5 Best Presenters';
    $topQuery = array_filter(['section' => $topFilters['section'], 'track' => $topFilters['track']]);
@endphp

<div class="rp-top-toolbar">
    <div class="d-flex align-items-center gap-2">
        <span class="capacity-header-icon"><x-icon name="{{ $isGroups ? 'trophy' : 'user' }}" /></span>
        <h3 class="h6 mb-0">{{ $heading }}</h3>
    </div>

    <div class="rp-top-actions">
        <form method="GET" action="{{ route('admin.reports.grades', $category) }}" class="d-flex flex-wrap gap-2">
            <input type="hidden" name="top" value="{{ $type }}">

            @if ($tracks->isNotEmpty())
                <select name="track" class="form-select form-select-sm" aria-label="Track" data-filter-auto>
                    <option value="">All tracks</option>
                    @foreach ($tracks as $trackKey => $trackName)
                        <option value="{{ $trackKey }}" @selected($topFilters['track'] === (string) $trackKey)>{{ $trackName }}</option>
                    @endforeach
                </select>
            @endif

            <select name="section" class="form-select form-select-sm" aria-label="Section" data-filter-auto>
                <option value="">All sections</option>
                @foreach ($sections as $sectionName)
                    <option value="{{ $sectionName }}" @selected($topFilters['section'] === $sectionName)>{{ $sectionName }}</option>
                @endforeach
                @if ($hasUnassignedSection)
                    <option value="{{ \App\Services\GradeReportService::UNASSIGNED_SECTION }}" @selected($topFilters['section'] === \App\Services\GradeReportService::UNASSIGNED_SECTION)>Unassigned</option>
                @endif
            </select>

            <noscript><button type="submit" class="btn btn-sm btn-brand"><x-icon name="filter" /> Apply</button></noscript>
        </form>

        <a href="{{ route('admin.reports.grades', $category) }}" class="btn btn-sm btn-outline-brand">
            <x-icon name="arrow-left" /> Back to Grades
        </a>
        <a href="{{ route('admin.reports.top.export-pdf', [$category, $type] + $topQuery) }}" class="btn btn-sm btn-brand">
            <x-icon name="file-text" /> Export to PDF
        </a>
    </div>
</div>

@if ($topRows->isEmpty())
    <div class="card-brand rp-empty">—</div>
@else
    <div class="rp-top-list">
        @foreach ($topRows as $item)
            <div class="card-brand rp-top-card {{ $item->rank === 1 ? 'is-first' : '' }}">
                <div class="rp-top-rank">
                    <span class="rp-top-rank-number">{{ $item->rank }}</span>
                </div>

                <div class="rp-best-main">
                    <div class="rp-top-meta">
                        <span class="rp-best-ref">{{ $item->group_reference }}</span>
                        @if ($isGroups)
                            @foreach ($item->sections as $sectionName)
                                <span class="badge badge-muted-tint">{{ $sectionName }}</span>
                            @endforeach
                        @elseif ($item->section)
                            <span class="badge badge-muted-tint">{{ $item->section }}</span>
                        @endif
                        @if ($item->track_name)
                            <span class="badge badge-info-tint">{{ $item->track_name }}</span>
                        @endif
                    </div>

                    @if ($isGroups)
                        <div class="rp-best-title" title="{{ $item->project_title }}">{{ $item->project_title ?? '—' }}</div>
                        <div class="rp-best-members">{{ $item->members->implode(' · ') }}</div>
                    @else
                        <div class="rp-best-title">{{ $item->name }}</div>
                        <div class="rp-best-members">{{ $item->project_title ?? '—' }}</div>
                    @endif
                </div>

                <div class="rp-top-stats">
                    <div class="rp-top-stat">
                        <span class="rp-top-stat-value">{{ number_format($item->group_grade, 2) }}</span>
                        <span class="rp-top-stat-label">Group Grade</span>
                    </div>
                    <div class="rp-top-stat">
                        <span class="rp-top-stat-value">{{ number_format($isGroups ? $item->members_individual_avg : $item->individual_avg, 2) }}</span>
                        <span class="rp-top-stat-label">{{ $isGroups ? 'Members Ind. Avg.' : 'Individual Avg.' }}</span>
                    </div>
                </div>

                <div class="rp-best-score">
                    <span class="rp-best-score-value">{{ number_format($item->score, 2) }}</span>
                    <span class="rp-best-score-label">Score</span>
                </div>
            </div>
        @endforeach
    </div>
@endif
