@extends('layouts.super-admin')

@section('title', 'Admin Accounts')

@section('content')
    <div class="aa-page">
        <div class="aa-toolbar">
            <div>
                <h2 class="h4 mb-1">Admin Accounts</h2>
                <p class="text-brand-muted mb-0 aa-subtitle">Create, activate, deactivate, and reset passwords for Administrator accounts.</p>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="position-relative aa-search">
                    <svg class="search-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="search" id="aa-search-input" class="form-control search-field-input" placeholder="Search here">
                </div>

                <a href="{{ route('super-admin.administrators.create') }}" class="btn btn-brand"><x-icon name="plus" /> New Admin Account</a>
            </div>
        </div>

        @if ($admins->isEmpty())
            <div class="aa-list aa-empty text-center text-brand-muted">
                No Administrator accounts yet.
            </div>
        @else
            <div class="aa-list">
                <table class="table mb-0 align-middle admin-account-table" id="admin-account-table">
                    <thead>
                        <tr>
                            <th class="ps-3">Admin</th>
                            <th class="d-none d-sm-table-cell">College</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($admins as $admin)
                            @php
                                $avatarPalettes = ['badge-brand-tint', 'badge-success-tint', 'badge-info-tint', 'badge-danger-tint', 'badge-muted-tint'];
                                $fullName = trim(($admin->profile->first_name ?? '').' '.($admin->profile->last_name ?? '')) ?: '—';
                                $initials = strtoupper(mb_substr($admin->profile->first_name ?? $admin->username, 0, 1).mb_substr($admin->profile->last_name ?? '', 0, 1));
                                $avatarClass = $avatarPalettes[$loop->index % count($avatarPalettes)];
                                $collegeName = $admin->administratorProfile->college->name ?? '—';

                                $statusBadge = match ($admin->accountStatus->code) {
                                    'ACTIVE' => 'badge-success-tint',
                                    'INACTIVE' => 'badge-muted-tint',
                                    'LOCKED' => 'badge-danger-tint',
                                    default => 'badge-info-tint',
                                };

                                $searchKey = strtolower($admin->username.' '.$fullName.' '.$collegeName);
                            @endphp
                            <tr class="admin-account-row" data-search="{{ $searchKey }}">
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar-circle {{ $avatarClass }}">{{ $initials }}</span>
                                        <div>
                                            <div class="fw-semibold aa-name">{{ $fullName }}</div>
                                            <div class="text-brand-muted small">{{ $admin->username }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-sm-table-cell aa-college">{{ $collegeName }}</td>
                                <td>
                                    <span class="badge status-pill {{ $statusBadge }}">
                                        <span class="status-pill-dot"></span>{{ $admin->accountStatus->name }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="dropdown">
                                        <button type="button" class="row-actions-btn" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Row actions">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.75"></circle><circle cx="12" cy="12" r="1.75"></circle><circle cx="12" cy="19" r="1.75"></circle></svg>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a href="{{ route('super-admin.administrators.edit', $admin) }}" class="dropdown-item d-flex align-items-center gap-2">
                                                    <x-icon name="edit" class="dropdown-item-icon" />
                                                    Edit
                                                </a>
                                            </li>
                                            <li>
                                                @if ($admin->accountStatus->code === 'INACTIVE')
                                                    <form method="POST" action="{{ route('super-admin.administrators.activate', $admin) }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 w-100">
                                                            <x-icon name="power" class="dropdown-item-icon" />
                                                            Activate
                                                        </button>
                                                    </form>
                                                @else
                                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2 w-100"
                                                            data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                                                            data-confirm-action="{{ route('super-admin.administrators.deactivate', $admin) }}"
                                                            data-confirm-method="POST"
                                                            data-confirm-title="Deactivate Admin Account"
                                                            data-confirm-message="Deactivate {{ $admin->username }}? They won't be able to sign in until the account is activated again."
                                                            data-confirm-submit-label="Deactivate"
                                                            data-confirm-variant="btn-outline-danger-brand">
                                                        <x-icon name="power" class="dropdown-item-icon" />
                                                        Deactivate
                                                    </button>
                                                @endif
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item d-flex align-items-center gap-2 w-100"
                                                        data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                                                        data-confirm-action="{{ route('super-admin.administrators.reset-password', $admin) }}"
                                                        data-confirm-method="POST"
                                                        data-confirm-title="Reset Password"
                                                        data-confirm-message="Issue a new temporary password for {{ $admin->username }}? Their current password stops working."
                                                        data-confirm-submit-label="Reset Password"
                                                        data-confirm-variant="btn-brand">
                                                    <x-icon name="key" class="dropdown-item-icon" />
                                                    Reset Password
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button type="button" class="dropdown-item d-flex align-items-center gap-2 w-100 text-danger-brand"
                                                        data-bs-toggle="modal" data-bs-target="#confirm-action-modal"
                                                        data-confirm-action="{{ route('super-admin.administrators.destroy', $admin) }}"
                                                        data-confirm-method="DELETE"
                                                        data-confirm-title="Delete Admin Account"
                                                        data-confirm-message="Permanently delete {{ $admin->username }}? The account is removed and signed out. Categories, schedules and records they worked on stay with the college. This cannot be undone."
                                                        data-confirm-submit-label="Delete"
                                                        data-confirm-variant="btn-outline-danger-brand">
                                                    <x-icon name="trash" class="dropdown-item-icon" />
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
        @endif
    </div>

    @include('admin.partials.confirm-action-modal')

    @push('scripts')
        <script>
            (function () {
                const input = document.getElementById('aa-search-input');
                const rows = document.querySelectorAll('#admin-account-table .admin-account-row');
                if (!input || !rows.length) return;

                input.addEventListener('input', function () {
                    const q = input.value.trim().toLowerCase();
                    rows.forEach(function (row) {
                        row.classList.toggle('d-none', q !== '' && !row.dataset.search.includes(q));
                    });
                });
            })();
        </script>
    @endpush

    @push('styles')
        <style>
            [data-bs-theme="light"] .admin-content {
                background-color: #ffffff;
            }

            /* The page never scrolls; only the list does. The toolbar stays put
               and the list takes whatever height is left, up to its content. */
            .admin-content:has(.aa-page) {
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }

            .aa-page {
                flex: 1 1 auto;
                min-height: 0;
                width: 85%;
                margin-inline: auto;
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }

            .aa-toolbar {
                flex-shrink: 0;
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                align-items: center;
                gap: 0.75rem;
            }

            .aa-subtitle {
                font-size: clamp(0.78rem, 0.74rem + 0.15vw, 0.88rem);
            }

            .aa-toolbar .btn,
            .aa-toolbar .form-control {
                font-size: clamp(0.8rem, 0.76rem + 0.15vw, 0.9rem);
            }

            .aa-search {
                flex: 1 1 12rem;
                max-width: 18rem;
            }

            .aa-list {
                flex: 0 1 auto;
                min-height: 0;
                overflow: auto;
                background-color: var(--brand-surface-alt);
                border: 1px solid var(--brand-border);
                border-radius: 1rem;
            }

            .aa-empty {
                padding: 2.5rem 1rem;
                font-size: clamp(0.8rem, 0.76rem + 0.15vw, 0.9rem);
            }

            .admin-account-table {
                --bs-table-bg: transparent;
                font-size: clamp(0.78rem, 0.74rem + 0.18vw, 0.9rem);
            }

            .admin-account-table > :not(caption) > * > * {
                padding-top: 0.45rem;
                padding-bottom: 0.45rem;
            }

            .admin-account-table thead th,
            .aa-name {
                white-space: nowrap;
            }

            .aa-college {
                min-width: 9rem;
            }

            .admin-account-table .status-pill {
                white-space: normal;
                text-align: left;
                line-height: 1.25;
            }

            .admin-account-table thead th {
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

            .admin-account-table .badge {
                font-size: 0.72rem;
            }

            @media (max-width: 991.98px) {
                .aa-page {
                    width: 100%;
                }
            }

            @media (max-width: 575.98px) {
                .admin-content:has(.aa-page) {
                    padding: 1rem;
                }

                .aa-search {
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

            .dropdown-item-icon {
                width: 1rem;
                height: 1rem;
                flex-shrink: 0;
            }
        </style>
    @endpush
@endsection
