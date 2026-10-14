@extends('layouts.admin')

@section('title', 'Generated Grades')
@section('heading', 'Generated Grades')

@php
    use App\Services\GradeReportService;

    $activeFilters = $filters['search'] !== ''
        || $filters['section'] !== null
        || $filters['date'] !== null
        || $filters['sort'] !== GradeReportService::SORT_NAME;
@endphp

@section('content')
    <div class="page-narrow">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1">{{ $category->name }}</h2>
                <p class="text-brand-muted small mb-0">
                    {{ $category->academicYear->name ?? 'No academic year' }} &middot; {{ $category->semester->name ?? 'No semester' }}
                    &middot; {{ $category->college->name ?? 'No college' }}
                </p>
            </div>

            {{-- Replaces the old "Back to Reports" button (user-directed
                 2026-09-17): Reports opens straight on a category now, so there
                 is no picker page to go back to. --}}
            @include('partials.category-picker-dropdown', [
                'categories' => $categories,
                'current' => $category,
                'routeName' => 'admin.reports.grades',
            ])
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="card-brand rp-card h-100">
                    <div class="rp-card-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="capacity-header-icon"><x-icon name="filter" /></span>
                            <h3 class="rp-card-title mb-0">Filters</h3>
                        </div>
                        @if ($activeFilters)
                            <a href="{{ route('admin.reports.grades', $category) }}" class="btn btn-sm btn-outline-brand"><x-icon name="x" /> Clear</a>
                        @endif
                    </div>

                    <form method="GET" action="{{ route('admin.reports.grades', $category) }}" class="rp-card-body">
                        <div class="rp-top-row">
                            <div class="rp-filter-search">
                                <label for="filter-name" class="rp-field-label">Name or Group Code</label>
                                <div class="input-group input-group-sm">
                                    <input type="search" class="form-control" id="filter-name" name="q"
                                           value="{{ $filters['search'] }}" placeholder="e.g. Dela Cruz or SLD-2026-0002" autocomplete="off">
                                    <button type="submit" class="btn btn-brand"><x-icon name="search" /> Search</button>
                                </div>
                            </div>

                            <div class="rp-tools">
                            <div class="rp-sheets">
                                <span class="rp-sheets-label"><x-icon name="file-text" /> Recommendations Summary</span>

                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-brand" data-recommendations-open>
                                        <x-icon name="eye" /> View
                                    </button>
                                    <a href="{{ route('admin.reports.recommendations.export-pdf', $category) }}" class="btn btn-sm btn-outline-brand">
                                        <x-icon name="file-text" /> Export to PDF
                                    </a>
                                </div>
                            </div>

                            <div class="rp-sheets">
                                <span class="rp-sheets-label"><x-icon name="printer" /> Panelist Sign-off Sheets</span>

                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-brand" data-sheet-open>
                                        <x-icon name="eye" /> View
                                    </button>
                                    <a href="{{ route('admin.reports.panelist-sheet.export-pdf', $category) }}" class="btn btn-sm btn-outline-brand">
                                        <x-icon name="file-text" /> Export to PDF
                                    </a>
                                </div>
                            </div>
                            </div>
                        </div>

                        <div class="rp-filter-grid">
                            <div>
                                <label for="filter-section" class="rp-field-label">Section</label>
                                <select name="section" id="filter-section" class="form-select form-select-sm" data-filter-auto>
                                    <option value="">All sections</option>
                                    @foreach ($sections as $sectionName)
                                        <option value="{{ $sectionName }}" @selected($filters['section'] === $sectionName)>{{ $sectionName }}</option>
                                    @endforeach
                                    @if ($hasUnassignedSection)
                                        <option value="{{ GradeReportService::UNASSIGNED_SECTION }}" @selected($filters['section'] === GradeReportService::UNASSIGNED_SECTION)>Unassigned</option>
                                    @endif
                                </select>
                            </div>

                            <div>
                                <label for="filter-date" class="rp-field-label">Date</label>
                                <select name="date" id="filter-date" class="form-select form-select-sm" data-filter-auto>
                                    <option value="">All dates</option>
                                    @foreach ($dates as $presentationDate)
                                        <option value="{{ $presentationDate->id }}" @selected((int) $filters['date'] === $presentationDate->id)>
                                            {{ $presentationDate->presentation_date->format('M j, Y') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="filter-sort" class="rp-field-label">Sort</label>
                                <select name="sort" id="filter-sort" class="form-select form-select-sm" data-filter-auto>
                                    <option value="{{ GradeReportService::SORT_NAME }}" @selected($filters['sort'] === GradeReportService::SORT_NAME)>Section, then name</option>
                                    <option value="{{ GradeReportService::SORT_LAST_NAME }}" @selected($filters['sort'] === GradeReportService::SORT_LAST_NAME)>Last name A–Z, then highest grade</option>
                                </select>
                            </div>
                        </div>

                        <noscript><button type="submit" class="btn btn-sm btn-brand mt-2"><x-icon name="filter" /> Apply</button></noscript>
                    </form>
                </div>
            </div>

            <div class="col-lg-4 d-flex flex-column gap-3">
                {{-- Refreshed in place every 30s by the script below, so they
                     keep up as panels submit evaluations. Sized to their
                     content, not to the filters card beside them. Each one's
                     View swaps the table below for its top-5 list. --}}
                <div class="card-brand rp-card" data-best-groups>
                    @include('admin.reports.partials.best-groups')
                </div>

                <div class="card-brand rp-card" data-best-presenters>
                    @include('admin.reports.partials.best-presenters')
                </div>
            </div>
        </div>

        @if ($top)
        <div data-top-list>
            @include('admin.reports.partials.top-list', ['type' => $top])
        </div>
        @else
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="rp-count">{{ $rows->count() }} {{ Str::plural('student', $rows->count()) }}</span>
                @if ($filterSummary !== '')
                    <span class="rp-filter-summary">{{ $filterSummary }}</span>
                @endif
            </div>

            @php
                $exportQuery = array_filter([
                    $category,
                    'q' => $filters['search'] ?: null,
                    'section' => $filters['section'],
                    'date' => $filters['date'],
                    'sort' => $filters['sort'],
                ]);
            @endphp
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('admin.reports.grades.export-pdf', $exportQuery) }}" class="btn btn-sm btn-outline-brand">
                    <x-icon name="file-text" /> Export to PDF
                </a>
                <a href="{{ route('admin.reports.grades.export', $exportQuery) }}" class="btn btn-sm btn-outline-brand">
                    <x-icon name="file-text" /> Export to Excel
                </a>
            </div>
        </div>

        <div class="card-brand p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 rp-grades-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Section</th>
                            <th>Individual Grade Avg.</th>
                            <th>Group Grade</th>
                            <th>Total</th>
                            <th>Remarks</th>
                            @if ($paymentTypes->isNotEmpty())
                                <th>Payment/s</th>
                            @endif
                            <th class="text-end">Evaluation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['section'] ?? '—' }}</td>
                                <td>{{ $row['individual_avg'] !== null ? number_format($row['individual_avg'], 2) : '—' }}</td>
                                <td>{{ $row['group_grade'] !== null ? number_format($row['group_grade'], 2) : '—' }}</td>
                                <td>{{ $row['total'] !== null ? number_format($row['total'], 2) : '—' }}</td>
                                <td>
                                    @if ($row['outcome'])
                                        <span class="badge {{ $row['outcome_failed'] ? 'badge-danger-tint' : 'badge-success-tint' }}">{{ $row['outcome'] }}</span>
                                        @if ($row['attempt_number'] > 1)
                                            <span class="text-brand-muted small ms-1">Attempt {{ $row['attempt_number'] }}</span>
                                        @endif
                                    @else
                                        <span class="text-brand-muted">—</span>
                                    @endif
                                </td>
                                @if ($paymentTypes->isNotEmpty())
                                    @php
                                        $paidCount = collect($row['payment_details'])->where('paid', true)->count();
                                        $typeCount = count($row['payment_details']);
                                    @endphp
                                    <td>
                                        <button type="button"
                                                class="rp-pay-link {{ $paidCount === $typeCount ? 'is-paid' : ($paidCount > 0 ? 'is-partial' : 'is-unpaid') }}"
                                                data-payment-view
                                                data-attempt="{{ $row['attempt_id'] }}"
                                                data-group="{{ $row['group_reference'] }}"
                                                data-url="{{ route('admin.reports.payment.update', [$category, $row['attempt_id'], '__V__']) }}"
                                                data-store-url="{{ route('admin.reports.payment.store', [$category, $row['attempt_id']]) }}"
                                                data-payments='@json($row['payment_details'])'>{{ $paidCount === $typeCount ? 'Paid' : ($paidCount > 0 ? "{$paidCount}/{$typeCount} Paid" : 'Unpaid') }}</button>
                                    </td>
                                @endif
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-brand border-0"
                                            data-view-sheet
                                            data-url="{{ route('admin.reports.evaluation-sheet', [$category, $row['attempt_id']]) }}"
                                            data-group="{{ $row['group_reference'] }}"
                                            @disabled($row['submission_count'] === 0)
                                            title="{{ $row['submission_count'] === 0 ? 'No submitted evaluations yet' : '' }}"><x-icon name="eye" /> View</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 7 + ($paymentTypes->isNotEmpty() ? 1 : 0) }}" class="text-center text-brand-muted py-4">No completed groups match this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @include('admin.partials.evaluation-sheet-modal')

        {{-- Payment/s: each payment type with its recorded reference number,
             filled from the row's View button. Only a recorded number can be
             edited; the exports keep their own per-type columns. --}}
        <div class="modal fade" id="payment-modal" tabindex="-1" aria-labelledby="payment-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="payment-modal-label">Payment/s <span class="rp-pay-group" data-payment-group></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="rp-pay-list" data-payment-list></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Running list of completed groups with the panelists' comments,
             fetched from its own route each time it opens. --}}
        <div class="modal fade" id="recommendations-modal" tabindex="-1" aria-labelledby="recommendations-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="recommendations-modal-label">Summary of Recommendations &amp; Comments</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" data-recommendations-body></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Close</button>
                        <a href="{{ route('admin.reports.recommendations.export-pdf', $category) }}" class="btn btn-brand"><x-icon name="file-text" /> Export to PDF</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- The running sign-off sheet, embedded from its own standalone route
             (?embed=1 drops that page's toolbar) so there is exactly one copy
             of the paper. Loaded fresh on each open, and Print goes through
             the frame, so what prints is the sheet and not this page. --}}
        <div class="modal fade" id="panelist-sheet-modal" tabindex="-1" aria-labelledby="panelist-sheet-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="panelist-sheet-modal-label">Panelist Sign-off Sheet</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <iframe class="rp-sheet-frame" title="Panelist sign-off sheet" data-sheet-frame></iframe>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Close</button>
                        <a href="{{ route('admin.reports.panelist-sheet.export-pdf', $category) }}" class="btn btn-outline-brand"><x-icon name="file-text" /> Export to PDF</a>
                        <button type="button" class="btn btn-brand" data-sheet-print><x-icon name="printer" /> Print</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        /* Reports: Generated Grades — filters card, Best Group card, sign-off
           sheet dropdown and preview. Page-local; nothing else uses them. */
        .rp-card {
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .rp-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.7rem 1rem;
            background-color: var(--brand-accent-tint);
            border-bottom: 1px solid var(--brand-border);
        }

        .rp-card-title {
            font-size: 0.92rem;
            font-weight: 600;
        }

        .rp-grades-table > thead th {
            font-size: 0.72rem;
            font-weight: 600;
        }

        .rp-card-body {
            padding: 0.9rem 1rem;
        }

        .rp-filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr));
            gap: 0.65rem 0.75rem;
        }

        /* Search bar (left) and the sign-off sheets picker (right, label
           stacked above its dropdown) share one row above the Section/Date/
           Sort grid — moved here 2026-09-17 so the sheets picker sits beside
           the search bar instead of as its own row underneath the form. */
        .rp-top-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem 1.5rem;
            margin-bottom: 0.85rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid var(--brand-border);
        }

        /* The box itself is capped well short of the row (was 40% of the
           whole form width, user-directed 2026-09-17: 60% narrower) with a
           floor so it stays usable on a phone, and a pill shape. Bootstrap
           squares an input group's inner corners, so the outer two are
           rounded back individually. */
        .rp-filter-search {
            flex: 1 1 16rem;
            max-width: 24rem;
        }

        .rp-filter-search .input-group {
            width: 100%;
            min-width: min(100%, 15rem);
        }

        .rp-filter-search .form-control {
            border-top-left-radius: 999px;
            border-bottom-left-radius: 999px;
            padding-left: 0.85rem;
        }

        .rp-filter-search .btn {
            border-top-right-radius: 999px;
            border-bottom-right-radius: 999px;
            padding-right: 0.9rem;
        }

        /* Recommendations / sign-off tools: label above its View + Export
           buttons, sitting to the right of the search bar. */
        .rp-sheets {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.4rem;
            flex: 0 0 auto;
        }

        .rp-sheets-label {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }

        /* Bare icon outside a button — theme-head only scopes `.btn-* > svg`
           to 1.05em, so this one had no size rule at all and rendered at the
           browser's default replaced-element size (a giant printer). */
        .rp-sheets-label svg {
            width: 1.05em;
            height: 1.05em;
            flex-shrink: 0;
        }

        .rp-best-body {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.8rem 1rem;
        }

        .rp-best-main {
            min-width: 0;
        }

        .rp-best-ref {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--brand-accent);
        }

        .rp-best-title {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 1.3;
        }

        .rp-best-members {
            margin-top: 0.1rem;
            overflow: hidden;
            font-size: 0.72rem;
            color: var(--brand-muted);
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rp-best-score {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex-shrink: 0;
            min-width: 4.4rem;
            padding: 0.35rem 0.5rem;
            border-radius: 0.65rem;
            background-color: var(--brand-success-tint);
            color: var(--brand-success);
        }

        .rp-best-score-value {
            font-size: 1.2rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .rp-best-score-label {
            font-size: 0.58rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* Compact Best Group / Best Presenter cards (about half their old
           height): the View button lives in the header, the members line is a
           tooltip, and the top-5 cards below keep the roomier rules above. */
        [data-best-groups] .rp-card-header,
        [data-best-presenters] .rp-card-header {
            padding: 0.35rem 0.75rem;
        }

        [data-best-groups] .rp-card-title,
        [data-best-presenters] .rp-card-title {
            font-size: 0.82rem;
        }

        [data-best-groups] .rp-best-body,
        [data-best-presenters] .rp-best-body {
            padding: 0.4rem 0.75rem;
        }

        [data-best-groups] .rp-best-main,
        [data-best-presenters] .rp-best-main {
            flex: 1 1 auto;
        }

        [data-best-groups] .rp-best-title,
        [data-best-presenters] .rp-best-title {
            -webkit-line-clamp: 1;
            line-clamp: 1;
            font-size: 0.82rem;
        }

        [data-best-groups] .rp-best-score,
        [data-best-presenters] .rp-best-score {
            min-width: 3.6rem;
            padding: 0.15rem 0.4rem;
        }

        [data-best-groups] .rp-best-score-value,
        [data-best-presenters] .rp-best-score-value {
            font-size: 1rem;
        }

        [data-best-groups] .rp-best-score-label,
        [data-best-presenters] .rp-best-score-label {
            font-size: 0.5rem;
        }

        .rp-best-view {
            --bs-btn-padding-y: 0.1rem;
            --bs-btn-padding-x: 0.5rem;
            --bs-btn-font-size: 0.7rem;
            gap: 0.25rem;
        }

        .rp-empty {
            padding: 1rem;
            color: var(--brand-muted);
        }

        .rp-count {
            font-size: 0.8rem;
            color: var(--brand-muted);
        }

        .rp-filter-summary {
            padding-left: 0.65rem;
            border-left: 1px solid var(--brand-border);
            font-size: 0.78rem;
            color: var(--brand-muted);
        }

        /* Top-5 cards that replace the grades table. */
        .rp-top-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 19rem), 1fr));
            gap: 0.75rem;
        }

        .rp-top-card {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.9rem 1rem;
        }

        .rp-top-card .rp-best-main {
            min-width: 0;
        }

        .rp-top-card .rp-best-ref {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rp-top-card.is-first {
            box-shadow: inset 3px 0 0 var(--brand-accent);
        }

        .rp-top-rank {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 2.2rem;
            height: 2.2rem;
            border-radius: 50%;
            background-color: var(--brand-surface);
            color: var(--brand-muted);
        }

        .is-first .rp-top-rank {
            background-color: var(--brand-accent);
            color: #fff;
        }

        .rp-top-rank-number {
            font-size: 0.95rem;
            font-weight: 800;
        }

        .rp-tools {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 0.75rem 1.5rem;
        }

        .rp-rec-count {
            margin-bottom: 0.6rem;
            font-size: 0.8rem;
            color: var(--brand-muted);
        }

        .rp-rec-grid {
            min-width: 44rem;
            border: 1px solid var(--brand-border);
            border-radius: 0.6rem;
            overflow: hidden;
        }

        .rp-rec-row {
            display: grid;
            grid-template-columns: 2.5rem 7.5rem minmax(0, 1.1fr) minmax(0, 2fr);
            gap: 0.75rem;
            padding: 0.7rem 0.85rem;
            font-size: 0.85rem;
        }

        .rp-rec-row + .rp-rec-row {
            border-top: 1px solid var(--brand-border);
        }

        .rp-rec-head {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--brand-muted);
            background-color: var(--brand-surface);
        }

        .rp-rec-seq {
            font-weight: 600;
            text-align: center;
        }

        .rp-rec-title {
            font-weight: 600;
            margin: 0.1rem 0 0.25rem;
        }

        .rp-rec-comment + .rp-rec-comment {
            margin-top: 0.4rem;
        }

        .rp-rec-panelist {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--brand-accent);
        }

        .rp-rec-text {
            white-space: pre-line;
        }

        /* Payment/s column + its modal. */
        .rp-pay-link {
            padding: 0;
            border: 0;
            background: none;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
            text-decoration: underline;
            text-underline-offset: 0.2em;
            text-decoration-thickness: 1px;
            cursor: pointer;
        }

        .rp-pay-link:hover,
        .rp-pay-link:focus-visible {
            text-decoration-thickness: 2px;
        }

        .rp-pay-link:focus-visible {
            outline: 2px solid var(--brand-accent);
            outline-offset: 2px;
            border-radius: 0.2rem;
        }

        .rp-pay-link.is-paid {
            color: var(--brand-success);
        }

        .rp-pay-link.is-partial {
            color: var(--brand-accent);
        }

        .rp-pay-link.is-unpaid {
            color: var(--brand-muted);
        }

        .rp-pay-group {
            margin-left: 0.35rem;
            font-size: var(--modal-fs-sm, 0.8rem);
            font-weight: 600;
            color: var(--brand-accent);
        }

        .rp-pay-list {
            border: 1px solid var(--brand-border);
            border-radius: 0.6rem;
            overflow: hidden;
            background-color: var(--brand-surface);
        }

        .rp-pay-row {
            padding: 0.55rem 0.75rem;
        }

        .rp-pay-row + .rp-pay-row {
            border-top: 1px solid var(--brand-border);
        }

        .rp-pay-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            font-size: var(--modal-fs, 0.85rem);
            font-weight: 600;
        }

        .rp-pay-edit {
            display: flex;
            gap: 0.4rem;
            margin-top: 0.4rem;
        }

        .rp-pay-edit .form-control {
            flex: 1 1 auto;
            min-width: 0;
        }

        .rp-pay-msg {
            margin-top: 0.3rem;
            font-size: var(--modal-fs-xs, 0.75rem);
        }

        .rp-pay-msg.is-error {
            color: var(--brand-danger);
        }

        .rp-pay-msg.is-ok {
            color: var(--brand-success);
        }

        .rp-sheet-frame {
            display: block;
            width: 100%;
            height: 72vh;
            border: 0;
            background-color: #fff;
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            // Selects apply immediately; the name box keeps its own Search
            // button so a half-typed name doesn't reload the page.
            document.querySelectorAll('[data-filter-auto]').forEach(function (control) {
                control.addEventListener('change', function () {
                    control.closest('form').submit();
                });
            });
        })();

        (function () {
            // Running Best Group / Best Presenter: re-fetch this same page and
            // swap only those cards and the open top-5 list, so they follow new
            // evaluations without a reload or a separate route.
            var selectors = ['[data-best-groups]', '[data-best-presenters]', '[data-top-list]'];
            if (! document.querySelector(selectors[0])) return;

            setInterval(function () {
                if (document.hidden) return;

                fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (response) { return response.ok ? response.text() : null; })
                    .then(function (html) {
                        if (! html) return;
                        var doc = new DOMParser().parseFromString(html, 'text/html');
                        selectors.forEach(function (selector) {
                            var current = document.querySelector(selector);
                            var fresh = doc.querySelector(selector);
                            if (current && fresh) current.innerHTML = fresh.innerHTML;
                        });
                    })
                    .catch(function () {});
            }, 30000);
        })();

        (function () {
            var modalEl = document.getElementById('recommendations-modal');
            var trigger = document.querySelector('[data-recommendations-open]');
            if (! modalEl || ! trigger) return;

            var body = modalEl.querySelector('[data-recommendations-body]');

            // Fetched on every open, so groups completed since the last look
            // are already on the list.
            trigger.addEventListener('click', function () {
                body.innerHTML = '<div class="text-brand-muted">Loading&hellip;</div>';
                bootstrap.Modal.getOrCreateInstance(modalEl).show();

                fetch(@json(route('admin.reports.recommendations', $category)), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (response) { return response.ok ? response.text() : Promise.reject(); })
                    .then(function (html) { body.innerHTML = '<div class="table-responsive">' + html + '</div>'; })
                    .catch(function () { body.innerHTML = '<div class="text-danger">Could not load the summary. Try again.</div>'; });
            });
        })();

        (function () {
            var modalEl = document.getElementById('payment-modal');
            if (! modalEl) return;

            var list = modalEl.querySelector('[data-payment-list]');
            var groupLabel = modalEl.querySelector('[data-payment-group]');
            var csrf = @json(csrf_token());

            function el(tag, className, text) {
                var node = document.createElement(tag);
                if (className) node.className = className;
                if (text !== undefined) node.textContent = text;
                return node;
            }

            // Every row of the same group shares one attempt, so a saved
            // number is written back to all of its status links, and their
            // Paid / N/M Paid / Unpaid text follows.
            function storeReceipt(attemptId, typeId, verificationId, receipt) {
                document.querySelectorAll('[data-payment-view][data-attempt="' + attemptId + '"]').forEach(function (link) {
                    var payments = JSON.parse(link.dataset.payments);
                    payments.forEach(function (payment) {
                        if (payment.type_id !== typeId) return;
                        payment.verification_id = verificationId;
                        payment.receipt = receipt;
                        payment.paid = true;
                    });
                    link.dataset.payments = JSON.stringify(payments);

                    var paid = payments.filter(function (payment) { return payment.paid; }).length;
                    var all = paid === payments.length;
                    link.textContent = all ? 'Paid' : (paid > 0 ? paid + '/' + payments.length + ' Paid' : 'Unpaid');
                    link.classList.remove('is-paid', 'is-partial', 'is-unpaid');
                    link.classList.add(all ? 'is-paid' : (paid > 0 ? 'is-partial' : 'is-unpaid'));
                });
            }

            function render(button, notice) {
                var payments = JSON.parse(button.dataset.payments);
                list.innerHTML = '';
                groupLabel.textContent = button.dataset.group;

                payments.forEach(function (payment) {
                    var recorded = payment.verification_id && payment.receipt !== null;

                    var row = el('div', 'rp-pay-row');
                    var head = el('div', 'rp-pay-head');
                    head.appendChild(el('span', '', payment.type));
                    head.appendChild(el('span', 'badge ' + (payment.paid ? 'badge-success-tint' : 'badge-muted-tint'), payment.paid ? 'Paid' : 'Unpaid'));
                    row.appendChild(head);

                    var form = el('form', 'rp-pay-edit');
                    var input = el('input', 'form-control form-control-sm');
                    input.type = 'text';
                    input.maxLength = 100;
                    input.value = recorded ? payment.receipt : '';
                    input.setAttribute('aria-label', payment.type + ' receipt number');
                    var save = el('button', 'btn btn-sm btn-brand', recorded ? 'Save' : 'Record');
                    save.type = 'submit';
                    form.appendChild(input);
                    form.appendChild(save);
                    row.appendChild(form);

                    var message = el('div', 'rp-pay-msg');
                    if (notice && notice.typeId === payment.type_id) {
                        message.className = 'rp-pay-msg is-ok';
                        message.textContent = notice.text;
                    }
                    row.appendChild(message);

                    form.addEventListener('submit', function (event) {
                        event.preventDefault();
                        message.className = 'rp-pay-msg';
                        message.textContent = '';
                        save.disabled = true;

                        var data = new FormData();
                        data.append('_token', csrf);
                        data.append('reference_number', input.value);
                        if (recorded) {
                            data.append('_method', 'PUT');
                        } else {
                            data.append('category_payment_type_id', payment.type_id);
                        }

                        var url = recorded
                            ? button.dataset.url.replace('__V__', payment.verification_id)
                            : button.dataset.storeUrl;

                        fetch(url, {
                            method: 'POST',
                            body: data,
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        })
                            .then(function (response) {
                                return response.json().then(function (json) { return { ok: response.ok, json: json }; });
                            })
                            .then(function (result) {
                                if (! result.ok) {
                                    var errors = result.json.errors && result.json.errors.reference_number;
                                    message.className = 'rp-pay-msg is-error';
                                    message.textContent = errors ? errors[0] : (result.json.message || 'Could not save.');
                                    save.disabled = false;
                                    return;
                                }
                                storeReceipt(button.dataset.attempt, payment.type_id, result.json.verification_id, result.json.receipt);
                                render(button, { typeId: payment.type_id, text: result.json.message });
                            })
                            .catch(function () {
                                message.className = 'rp-pay-msg is-error';
                                message.textContent = 'Could not save. Try again.';
                                save.disabled = false;
                            });
                    });

                    list.appendChild(row);
                });
            }

            document.addEventListener('click', function (event) {
                var button = event.target.closest('[data-payment-view]');
                if (! button) return;
                render(button);
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
        })();

        (function () {
            var modalEl = document.getElementById('panelist-sheet-modal');
            if (! modalEl) return;

            var frame = modalEl.querySelector('[data-sheet-frame]');
            var sheetUrl = @json(route('admin.reports.panelist-sheet', [$category, 'embed' => 1]));

            document.querySelectorAll('[data-sheet-open]').forEach(function (item) {
                item.addEventListener('click', function () {
                    frame.src = sheetUrl;
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                });
            });

            modalEl.querySelector('[data-sheet-print]').addEventListener('click', function () {
                if (! frame.contentWindow) return;
                frame.contentWindow.focus();
                frame.contentWindow.print();
            });

            // Drop the loaded sheet on close, so reopening always fetches the
            // one that was picked and no stale paper flashes up first.
            modalEl.addEventListener('hidden.bs.modal', function () {
                frame.removeAttribute('src');
            });
        })();
    </script>
@endpush
