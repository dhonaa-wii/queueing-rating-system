@extends('layouts.admin')

@section('title', 'Panelist Management')
@section('heading', 'Panelist Management')

@section('content')
    <div class="pm-page">
    <div class="pm-toolbar">
        <form method="GET" action="{{ route('admin.panelists.index') }}" class="pm-search">
            @foreach ($selectedStatuses as $code)
                <input type="hidden" name="status[]" value="{{ $code }}">
            @endforeach
            <div class="position-relative">
                <svg class="search-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="search" name="q" value="{{ $search }}" class="form-control search-field-input" placeholder="Search here">
            </div>
        </form>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="dropdown">
                <button type="button" class="btn btn-outline-brand d-inline-flex align-items-center gap-2" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"></path>
                    </svg>
                    Filter
                </button>
                <form method="GET" action="{{ route('admin.panelists.index') }}" class="dropdown-menu dropdown-menu-end p-3" style="min-width: 240px;">
                    <input type="hidden" name="q" value="{{ $search }}">
                    <p class="text-brand-muted small text-uppercase mb-2">Status</p>
                    @foreach ($statuses as $status)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="status[]" value="{{ $status->code }}"
                                   id="filter-status-{{ $status->code }}" @checked(in_array($status->code, $selectedStatuses, true))>
                            <label class="form-check-label" for="filter-status-{{ $status->code }}">{{ $status->name }}</label>
                        </div>
                    @endforeach
                    <button type="submit" class="btn btn-brand btn-sm w-100 mt-2"><x-icon name="filter" /> Apply</button>
                </form>
            </div>

            <button type="button" class="btn btn-brand d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#add-panelist-modal">
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add Panelist
            </button>
        </div>
    </div>

    @if ($panelists->isEmpty())
        <div class="pm-list pm-empty text-center text-brand-muted">
            No panelists {{ $search || $selectedStatuses ? 'match your search' : 'registered yet' }}.
        </div>
    @else
        <div class="pm-list">
                <table class="table mb-0 align-middle panelist-table" id="panelist-table" data-panelist-ids='@json($panelistIds)'>
                    <thead>
                        <tr>
                            <th class="ps-3">User</th>
                            <th class="d-none d-md-table-cell">Role</th>
                            <th class="d-none d-sm-table-cell">College</th>
                            <th><span class="d-none d-sm-inline">Assigned </span>Groups</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
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
                                };                            @endphp
                            <tr class="panelist-row" data-open-panelist="{{ $panelist->id }}" role="button">
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar-circle {{ $avatarClass }}">{{ $initials }}</span>
                                        <div>
                                            <div class="fw-semibold d-flex align-items-center gap-2 pm-name">
                                                {{ $fullName }}
                                                @if ($panelist->has_conflict)
                                                    <span class="badge badge-danger-tint">Conflict</span>
                                                @endif
                                            </div>
                                            <div class="text-brand-muted small">{{ $panelist->username }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell">Panelist</td>
                                <td class="d-none d-sm-table-cell pm-dept">{{ $panelist->panelistProfile->college->name ?? '—' }}</td>
                                <td>{{ $panelist->assigned_groups_count }}</td>
                                <td>
                                    <span class="badge status-pill {{ $statusBadge }}">
                                        <span class="status-pill-dot"></span>{{ $panelist->accountStatus->name }}
                                    </span>
                                </td>
                                <td class="text-end pe-3" onclick="event.stopPropagation()">
                                    <div class="dropdown">
                                        <button type="button" class="row-actions-btn" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Row actions">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.75"></circle><circle cx="12" cy="12" r="1.75"></circle><circle cx="12" cy="19" r="1.75"></circle></svg>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <button type="button" class="dropdown-item d-flex align-items-center gap-2 w-100" data-open-panelist="{{ $panelist->id }}">
                                                    <svg class="dropdown-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"></path>
                                                        <circle cx="12" cy="12" r="3"></circle>
                                                    </svg>
                                                    View
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item d-flex align-items-center gap-2 w-100" data-bs-toggle="modal" data-bs-target="#edit-panelist-modal-{{ $panelist->id }}">
                                                    <svg class="dropdown-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 20h9"></path>
                                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                                    </svg>
                                                    Edit
                                                </button>
                                            </li>
                                            <li>
                                                @if ($panelist->accountStatus->code === 'INACTIVE')
                                                    <form method="POST" action="{{ route('admin.panelists.activate', $panelist) }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 w-100">
                                                            <svg class="dropdown-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                                            </svg>
                                                            Activate
                                                        </button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('admin.panelists.deactivate', $panelist) }}"
                                                          onsubmit="return confirm('Deactivate {{ addslashes($panelist->username) }}?');">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 w-100">
                                                            <svg class="dropdown-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                                <circle cx="12" cy="12" r="9"></circle>
                                                                <line x1="5.5" y1="5.5" x2="18.5" y2="18.5"></line>
                                                            </svg>
                                                            Deactivate
                                                        </button>
                                                    </form>
                                                @endif
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item d-flex align-items-center gap-2 text-danger-brand" data-bs-toggle="modal" data-bs-target="#delete-panelist-{{ $panelist->id }}">
                                                    <svg class="dropdown-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="3 6 5 6 21 6"></polyline>
                                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                                        <path d="M10 11v6"></path>
                                                        <path d="M14 11v6"></path>
                                                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
        </div>

        @foreach ($panelists as $panelist)
            @include('admin.panelists.partials.edit-modal')

            <div class="modal fade" id="reset-password-panelist-{{ $panelist->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Reset Password</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">
                                This issues a new temporary password for
                                <strong>{{ $panelist->username }}</strong> and signs them out of their
                                current one. The new password is shown once and cannot be retrieved again.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                            <form method="POST" action="{{ route('admin.panelists.reset-password', $panelist) }}">
                                @csrf
                                <button type="submit" class="btn btn-brand"><x-icon name="key" /> Reset Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="delete-panelist-{{ $panelist->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Delete Panelist</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">
                                Are you sure you want to permanently delete
                                <strong>{{ trim(($panelist->profile->first_name ?? '').' '.($panelist->profile->last_name ?? '')) ?: $panelist->username }}</strong>?
                                This cannot be undone. If they're still referenced elsewhere in the system (e.g. panel assignments), deletion will be blocked.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancel</button>
                            <form method="POST" action="{{ route('admin.panelists.destroy', $panelist) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger-brand"><x-icon name="trash" /> Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
    </div>

    @include('admin.panelists.partials.add-modal')
    @include('admin.panelists.partials.detail-drawer')
    @include('admin.panelists.partials.drawer-scripts')

    @push('styles')
        <style>
            [data-bs-theme="light"] .admin-content {
                background-color: #ffffff;
            }

            /* The page never scrolls; only the list does. The toolbar stays put
               and the list takes whatever height is left, up to its content. */
            .admin-content:has(.pm-page) {
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }

            .pm-page {
                flex: 1 1 auto;
                min-height: 0;
                width: 85%;
                margin-inline: auto;
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }

            .pm-toolbar {
                flex-shrink: 0;
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                align-items: center;
                gap: 0.5rem;
            }

            .pm-toolbar .btn,
            .pm-toolbar .form-control {
                font-size: clamp(0.8rem, 0.76rem + 0.15vw, 0.9rem);
            }

            .pm-search {
                flex: 1 1 12rem;
                max-width: 20rem;
            }

            .pm-list {
                flex: 0 1 auto;
                min-height: 0;
                overflow: auto;
                background-color: var(--brand-surface-alt);
                border: 1px solid var(--brand-border);
                border-radius: 1rem;
            }

            .pm-empty {
                padding: 2.5rem 1rem;
                font-size: clamp(0.8rem, 0.76rem + 0.15vw, 0.9rem);
            }

            .panelist-table {
                --bs-table-bg: transparent;
                font-size: clamp(0.78rem, 0.74rem + 0.18vw, 0.9rem);
            }

            .panelist-table > :not(caption) > * > * {
                padding-top: 0.45rem;
                padding-bottom: 0.45rem;
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

            @media (max-width: 991.98px) {
                .pm-page {
                    width: 100%;
                }
            }

            @media (max-width: 575.98px) {
                .admin-content:has(.pm-page) {
                    padding: 1rem;
                }

                .pm-search {
                    max-width: none;
                    flex-basis: 100%;
                }
            }

            .btn-icon {
                width: 1.05rem;
                height: 1.05rem;
                flex-shrink: 0;
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

            .dropdown-item-icon {
                width: 1rem;
                height: 1rem;
                flex-shrink: 0;
            }
        </style>
    @endpush
@endsection
