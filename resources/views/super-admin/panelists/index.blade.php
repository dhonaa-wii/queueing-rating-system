@extends('layouts.super-admin')

@section('title', 'Panelist Oversight')

@section('content')
    <div class="po-page">
        <div class="po-toolbar">
            <div>
                <h2 class="h4 mb-1">Panelist Oversight</h2>
                <p class="text-brand-muted small mb-0">View-only. Panelist accounts are registered by Administrators in the Panelist Registry.</p>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <form method="GET" action="{{ route('super-admin.panelists.index') }}" class="po-search">
                    @if ($selectedCollege)
                        <input type="hidden" name="college" value="{{ $selectedCollege }}">
                    @endif
                    <div class="position-relative">
                        <svg class="search-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="7"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="search" name="q" value="{{ $search }}" class="form-control search-field-input" placeholder="Search name or username">
                    </div>
                </form>

                <form method="GET" action="{{ route('super-admin.panelists.index') }}" class="po-college-filter">
                    @if ($search)
                        <input type="hidden" name="q" value="{{ $search }}">
                    @endif
                    <select name="college" class="form-select" onchange="this.form.submit()">
                        <option value="">All Colleges</option>
                        @foreach ($colleges as $college)
                            <option value="{{ $college->id }}" @selected((string) $selectedCollege === (string) $college->id)>{{ $college->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        @if ($panelists->isEmpty())
            <div class="po-list po-empty text-center text-brand-muted">
                No panelists {{ $search || $selectedCollege ? 'match your filters' : 'registered yet' }}.
            </div>
        @else
            <div class="po-list">
                <table class="table mb-0 align-middle panelist-table" id="panelist-table" data-panelist-ids='@json($panelistIds)'>
                    <thead>
                        <tr>
                            <th class="ps-3">User</th>
                            <th class="d-none d-sm-table-cell">College</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($panelists as $panelist)
                            @php
                                $avatarPalettes = ['badge-brand-tint', 'badge-success-tint', 'badge-info-tint', 'badge-danger-tint', 'badge-muted-tint'];
                                $fullName = trim(($panelist->profile->first_name ?? '').' '.($panelist->profile->last_name ?? '')) ?: $panelist->username;
                                $initials = strtoupper(mb_substr($panelist->profile->first_name ?? $panelist->username, 0, 1).mb_substr($panelist->profile->last_name ?? '', 0, 1));
                                $avatarClass = $avatarPalettes[$loop->index % count($avatarPalettes)];

                                $statusBadge = match ($panelist->accountStatus->code) {
                                    'ACTIVE' => 'badge-success-tint',
                                    'INACTIVE' => 'badge-muted-tint',
                                    'LOCKED' => 'badge-danger-tint',
                                    default => 'badge-info-tint',
                                };
                            @endphp
                            <tr class="panelist-row" data-open-panelist="{{ $panelist->id }}" role="button">
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar-circle {{ $avatarClass }}">{{ $initials }}</span>
                                        <div>
                                            <div class="fw-semibold pm-name">{{ $fullName }}</div>
                                            <div class="text-brand-muted small">{{ $panelist->username }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-sm-table-cell pm-dept">{{ $panelist->panelistProfile->college->name ?? '—' }}</td>
                                <td>
                                    <span class="badge status-pill {{ $statusBadge }}">
                                        <span class="status-pill-dot"></span>{{ $panelist->accountStatus->name }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('super-admin.panelists.partials.detail-drawer')
    @include('super-admin.panelists.partials.drawer-scripts')

    @push('styles')
        <style>
            .admin-content:has(.po-page) {
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }

            .po-page {
                flex: 1 1 auto;
                min-height: 0;
                width: 85%;
                margin-inline: auto;
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }

            @media (max-width: 991.98px) {
                .po-page {
                    width: 100%;
                }
            }

            .po-toolbar {
                flex-shrink: 0;
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                align-items: center;
                gap: 0.75rem;
            }

            .po-toolbar .form-control,
            .po-toolbar .form-select {
                font-size: clamp(0.8rem, 0.76rem + 0.15vw, 0.9rem);
            }

            .po-search {
                flex: 1 1 12rem;
                max-width: 18rem;
            }

            .po-college-filter {
                flex: 0 0 auto;
                min-width: 11rem;
            }

            .po-list {
                flex: 1 1 auto;
                min-height: 0;
                overflow: auto;
                background-color: var(--brand-surface-alt);
                border: 1px solid var(--brand-border);
                border-radius: 1rem;
            }

            .po-empty {
                padding: 2.5rem 1rem;
                font-size: clamp(0.8rem, 0.76rem + 0.15vw, 0.9rem);
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .panelist-table {
                --bs-table-bg: transparent;
                font-size: clamp(0.78rem, 0.74rem + 0.18vw, 0.9rem);
            }

            .panelist-table > :not(caption) > * > * {
                padding-top: 0.5rem;
                padding-bottom: 0.5rem;
            }

            .panelist-table thead th,
            .pm-name {
                white-space: nowrap;
            }

            .pm-dept {
                min-width: 9rem;
            }

            .panelist-table .status-pill {
                white-space: normal;
                text-align: left;
                line-height: 1.25;
            }

            .panelist-table thead th {
                position: sticky;
                top: 0;
                z-index: 1;
                background-color: var(--brand-surface-alt);
                font-size: 0.72rem;
                font-weight: 600;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: var(--brand-muted);
            }

            .panelist-row {
                cursor: pointer;
            }

            .panelist-row:hover > td {
                background-color: var(--brand-accent-tint);
            }

            .panelist-table .badge {
                font-size: 0.72rem;
            }

            @media (max-width: 575.98px) {
                .po-search,
                .po-college-filter {
                    max-width: none;
                    flex-basis: 100%;
                }
            }

            .search-field-icon {
                position: absolute;
                left: 0.9rem;
                top: 50%;
                transform: translateY(-50%);
                width: 1.05rem;
                height: 1.05rem;
                color: var(--brand-muted);
                pointer-events: none;
            }

            .search-field-input {
                padding-left: 2.5rem;
                border-radius: 2rem;
            }

            .avatar-circle {
                width: clamp(1.85rem, 1.7rem + 0.4vw, 2.25rem);
                height: clamp(1.85rem, 1.7rem + 0.4vw, 2.25rem);
                padding: 0;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 0.72rem;
                flex-shrink: 0;
            }

            .status-pill {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                border-radius: 999px;
            }

            .status-pill-dot {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background-color: currentColor;
                flex-shrink: 0;
            }
        </style>
    @endpush
@endsection
