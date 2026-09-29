{{--
    Panelist Oversight drawer (2026-09-20) — replaces the old full-page
    super-admin.panelists.show view with a slide-in panel, same mechanism
    as Admin Panelist Management's drawer (admin/panelists/partials/
    detail-drawer.blade.php) but view-only: no actions dropdown, no
    edit/status/reset/delete. Populated entirely by drawer-scripts.blade.php
    via fetch(); this file is just the static shell + row template.
--}}
<div class="view-all-overlay panelist-drawer-overlay" data-panelist-drawer>
    <div class="view-all-overlay-backdrop" data-drawer-close></div>
    <div class="view-all-overlay-panel panelist-drawer-panel">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-shrink-0">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-brand" data-drawer-prev aria-label="Previous panelist">&lsaquo;</button>
                <span class="small text-brand-muted" data-drawer-position>&nbsp;</span>
                <button type="button" class="btn btn-sm btn-outline-brand" data-drawer-next aria-label="Next panelist">&rsaquo;</button>
            </div>
            <button type="button" class="btn-close" data-drawer-close aria-label="Close"></button>
        </div>

        <div data-drawer-loading class="text-center text-brand-muted py-5">Loading&hellip;</div>

        <div data-drawer-content class="d-none flex-grow-1 d-flex flex-column" style="min-height: 0;">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3 flex-shrink-0 flex-wrap">
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar-circle badge-brand-tint panelist-drawer-avatar" data-drawer-initials></span>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h3 class="h6 mb-0" data-drawer-name></h3>
                                <span class="badge badge-danger-tint d-none" data-drawer-conflict-badge>Conflict</span>
                            </div>
                            <div class="text-brand-muted small" data-drawer-username></div>
                        </div>
                    </div>

                    <div class="drawer-info-block">
                        <div class="drawer-info-label">Sex</div>
                        <div class="drawer-info-value" data-drawer-sex>&mdash;</div>
                    </div>
                    <div class="drawer-info-block">
                        <div class="drawer-info-label">Contact Number</div>
                        <div class="drawer-info-value" data-drawer-contact>&mdash;</div>
                    </div>
                    <div class="drawer-info-block">
                        <div class="drawer-info-label">College</div>
                        <div class="drawer-info-value" data-drawer-college>&mdash;</div>
                    </div>
                    <div class="drawer-info-block">
                        <div class="drawer-info-label">Field of Specialization</div>
                        <div class="drawer-info-value" data-drawer-specialization>&mdash;</div>
                    </div>
                </div>

                <span class="badge badge-brand-tint flex-shrink-0" data-drawer-status></span>
            </div>

            <div class="alert alert-danger py-2 px-3 small d-none flex-shrink-0" data-drawer-conflict-alert></div>

            <div class="panelist-stats mb-3 flex-shrink-0">
                <div class="panelist-stat">
                    <span class="panelist-stat-icon badge-brand-tint">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg>
                    </span>
                    <div class="panelist-stat-text">
                        <div class="panelist-stat-value" data-drawer-stat-categories>0</div>
                        <div class="panelist-stat-label">Categories</div>
                    </div>
                </div>
                <div class="panelist-stat">
                    <span class="panelist-stat-icon badge-info-tint">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </span>
                    <div class="panelist-stat-text">
                        <div class="panelist-stat-value" data-drawer-stat-groups>0</div>
                        <div class="panelist-stat-label">Assigned Groups</div>
                    </div>
                </div>
                <div class="panelist-stat">
                    <span class="panelist-stat-icon badge-success-tint">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    </span>
                    <div class="panelist-stat-text">
                        <div class="panelist-stat-value" data-drawer-stat-evaluated>0</div>
                        <div class="panelist-stat-label">Evaluated</div>
                    </div>
                </div>
            </div>

            <div class="position-relative mb-2 flex-shrink-0 panelist-drawer-search">
                <svg class="search-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="search" class="form-control form-control-sm search-field-input" placeholder="Search assigned groups" data-drawer-search>
            </div>

            <div class="notification-tabs mb-2 flex-shrink-0" role="tablist">
                <button type="button" class="notification-tab active" data-drawer-tab="all" role="tab">All</button>
                <button type="button" class="notification-tab" data-drawer-tab="completed" role="tab">Completed</button>
                <button type="button" class="notification-tab" data-drawer-tab="deferred" role="tab">Deferred</button>
            </div>

            <div class="panelist-drawer-scroll" data-drawer-scroll>
                <table class="table table-sm align-middle small mb-0">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Title / Leader</th>
                            <th>Other Panels</th>
                            <th>Category</th>
                        </tr>
                    </thead>
                    <tbody data-drawer-rows></tbody>
                </table>
                <div class="text-center text-brand-muted small py-4 d-none" data-drawer-empty>No assigned groups.</div>
            </div>
        </div>
    </div>
</div>

@push('styles')
    <style>
        .panelist-drawer-overlay {
            left: 0;
            right: 0;
            width: 100%;
        }

        .panelist-drawer-panel {
            overflow: hidden !important;
            display: flex;
            flex-direction: column;
            width: 50vw;
        }

        @media (max-width: 1199.98px) {
            .panelist-drawer-panel {
                width: 75vw;
            }
        }

        @media (max-width: 767.98px) {
            .panelist-drawer-panel {
                width: 100vw;
                border-left: 0;
            }
        }

        .panelist-drawer-avatar {
            width: 3rem;
            height: 3rem;
            font-size: 1rem;
        }

        .drawer-info-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: var(--brand-muted);
            white-space: nowrap;
        }

        .drawer-info-value {
            font-size: 0.85rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .panelist-drawer-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            border-top: 1px solid var(--brand-border);
            padding-top: 0.5rem;
        }

        .panelist-drawer-search {
            width: 100%;
            max-width: 16rem;
        }

        .panelist-drawer-search .search-field-icon {
            left: 0.7rem;
            width: 0.9rem;
            height: 0.9rem;
        }

        .panelist-drawer-search .search-field-input {
            padding-left: 2rem;
            font-size: 0.8rem;
        }

        @media (max-width: 575.98px) {
            .panelist-drawer-search {
                max-width: none;
            }
        }

        .panelist-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            background-color: var(--brand-surface-alt);
            border-radius: 0.75rem;
            padding: 0.55rem 0;
        }

        .panelist-stat {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            min-width: 0;
            padding: 0 0.85rem;
        }

        .panelist-stat + .panelist-stat {
            border-left: 1px solid var(--brand-border);
        }

        .panelist-stat-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 0.55rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .panelist-stat-icon svg {
            width: 1rem;
            height: 1rem;
        }

        .panelist-stat-text {
            min-width: 0;
        }

        .panelist-stat-value {
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }

        .panelist-stat-label {
            font-size: 0.68rem;
            line-height: 1.2;
            color: var(--brand-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width: 575.98px) {
            .panelist-stat {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.35rem;
                padding: 0 0.65rem;
            }

            .panelist-stat-icon {
                width: 1.7rem;
                height: 1.7rem;
            }

            .panelist-stat-icon svg {
                width: 0.85rem;
                height: 0.85rem;
            }
        }
    </style>
@endpush
